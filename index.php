<?php
declare(strict_types=1);

/* =====================================================
   PRODUCTION SETTINGS (Render Safe)
===================================================== */

header("Content-Type: application/json");

set_time_limit(30);
error_reporting(E_ALL);
ini_set("display_errors", "0"); // DO NOT show warnings to gateway

/* =====================================================
   CONFIGURATION
===================================================== */

const BITRIX_WEBHOOK_BASE =
    "https://prue-dubai.bitrix24.ru/rest/1136/tnt0k89577f6vfcg/";

const ENTITY_TYPE_ID = 1120;

/* =====================================================
   SMART PROCESS FIELD RULE

   GET returns lowercase keys:
     ufCrm30_xxxx

   UPDATE requires uppercase:
     UF_CRM_30_xxxx
===================================================== */

/* ---- READ FROM GET ---- */
const FIELD_START_GET = "ufCrm30_1767183075771";
const FIELD_END_GET   = "ufCrm30_1767183196848";
const FIELD_BAL_GET   = "ufCrm30_1767791217511";

/* ---- WRITE TO UPDATE ---- */
const FIELD_TOTAL_UPD = "UF_CRM_30_1767017263547";
const FIELD_REM_UPD   = "UF_CRM_30_1767791545080";

/* =====================================================
   SIMPLE LOGGING FUNCTION
===================================================== */
function logMessage(string $msg): void
{
    file_put_contents(
        __DIR__ . "/leave_log.txt",
        "[" . date("Y-m-d H:i:s") . "] " . $msg . PHP_EOL,
        FILE_APPEND
    );
}

/* =====================================================
   INPUT
===================================================== */

$itemId = (int)($_REQUEST["ID"] ?? 0);

if ($itemId <= 0) {
    echo json_encode([
        "status" => "error",
        "reason" => "Missing ID"
    ]);
    exit;
}

/* =====================================================
   STEP 1: FETCH ITEM
===================================================== */

$ch = curl_init(BITRIX_WEBHOOK_BASE . "crm.item.get.json");

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode([
        "entityTypeId" => ENTITY_TYPE_ID,
        "id" => $itemId
    ])
]);

$response = curl_exec($ch);

if ($response === false) {
    logMessage("GET ERROR: " . curl_error($ch));
    echo json_encode([
        "status" => "error",
        "reason" => "Bitrix GET failed"
    ]);
    exit;
}

curl_close($ch);

$data = json_decode($response, true);
$item = $data["result"]["item"] ?? null;

if (!$item) {
    logMessage("Item not found: " . $response);
    echo json_encode([
        "status" => "error",
        "reason" => "Item not found"
    ]);
    exit;
}

/* =====================================================
   STEP 2: READ VALUES
===================================================== */

$startRaw = $item[FIELD_START_GET] ?? null;
$endRaw   = $item[FIELD_END_GET] ?? null;
$balance  = (int)($item[FIELD_BAL_GET] ?? 0);

if (!$startRaw || !$endRaw) {
    logMessage("Dates missing for item $itemId");
    echo json_encode([
        "status" => "error",
        "reason" => "Dates missing"
    ]);
    exit;
}

/* =====================================================
   STEP 3: PARSE DATES
===================================================== */

try {
    $startDate = new DateTime(substr($startRaw, 0, 10));
    $endDate   = new DateTime(substr($endRaw, 0, 10));
} catch (Exception $e) {
    logMessage("Date parse error: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "reason" => "Invalid date format"
    ]);
    exit;
}

if ($startDate > $endDate) {
    echo json_encode([
        "status" => "error",
        "reason" => "Invalid date range"
    ]);
    exit;
}

/* =====================================================
   STEP 4: CALCULATE WORKING DAYS (MON–FRI)
===================================================== */

$workingDays = 0;
$cursor = clone $startDate;

while ($cursor <= $endDate) {

    if ((int)$cursor->format("N") <= 5) {
        $workingDays++;
    }

    $cursor->modify("+1 day");
}

/* =====================================================
   STEP 5: CALCULATE REMAINING BALANCE
===================================================== */

$remaining = max(0, $balance - $workingDays);

/* =====================================================
   STEP 6: UPDATE BITRIX ITEM
===================================================== */

$ch = curl_init(BITRIX_WEBHOOK_BASE . "crm.item.update.json");

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode([
        "entityTypeId" => ENTITY_TYPE_ID,
        "id" => $itemId,
        "fields" => [
            FIELD_TOTAL_UPD => $workingDays,
            FIELD_REM_UPD   => $remaining
        ]
    ])
]);

$updateResponse = curl_exec($ch);

if ($updateResponse === false) {
    logMessage("UPDATE ERROR: " . curl_error($ch));
    echo json_encode([
        "status" => "error",
        "reason" => "Bitrix UPDATE failed"
    ]);
    exit;
}

curl_close($ch);

$updateData = json_decode($updateResponse, true);

if (!isset($updateData["result"])) {
    logMessage("Update failed: " . $updateResponse);
    echo json_encode([
        "status" => "error",
        "reason" => "Update rejected",
        "bitrix_response" => $updateData
    ]);
    exit;
}

/* =====================================================
   FINAL SUCCESS RESPONSE
===================================================== */

echo json_encode([
    "status" => "success",
    "item_id" => $itemId,
    "working_days" => $workingDays,
    "original_balance" => $balance,
    "remaining_balance" => $remaining
]);
exit;
