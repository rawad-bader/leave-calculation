<?php
declare(strict_types=1);

/* =====================================================
   HIT LOGGING (PROOF BITRIX CALLED SCRIPT)
===================================================== */
file_put_contents(
    __DIR__ . "/hits.log",
    date("Y-m-d H:i:s") . " HIT: " . json_encode($_REQUEST) . PHP_EOL,
    FILE_APPEND
);

header("Content-Type: application/json");

set_time_limit(30);
error_reporting(0);

/* =====================================================
   CONFIGURATION
===================================================== */

const BITRIX_WEBHOOK_BASE =
"https://prue-dubai.bitrix24.ru/rest/1136/tnt0k89577f6vfcg/";

const ENTITY_TYPE_ID = 1120;

/* =====================================================
   FIELD KEYS (CONFIRMED)
===================================================== */

const FIELD_START_GET = "ufCrm30_1767183075771";
const FIELD_END_GET   = "ufCrm30_1767183196848";
const FIELD_BAL_GET   = "ufCrm30_1767791217511";

const FIELD_TOTAL_UPD = "UF_CRM_30_1767017263547";
const FIELD_REM_UPD   = "UF_CRM_30_1767791545080";

/* =====================================================
   INPUT
===================================================== */

$itemId = (int)($_REQUEST["ID"] ?? 0);

if ($itemId <= 0) {
    echo json_encode(["status"=>"error","reason"=>"Missing ID"]);
    exit;
}

/* =====================================================
   FETCH ITEM
===================================================== */

$ch = curl_init(BITRIX_WEBHOOK_BASE . "crm.item.get.json");

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode([
        "entityTypeId" => ENTITY_TYPE_ID,
        "id" => $itemId
    ])
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$item = $data["result"]["item"] ?? null;

if (!$item) {
    echo json_encode(["status"=>"error","reason"=>"Item not found"]);
    exit;
}

/* =====================================================
   READ VALUES
===================================================== */

$startRaw = $item[FIELD_START_GET] ?? null;
$endRaw   = $item[FIELD_END_GET] ?? null;
$balance  = (int)($item[FIELD_BAL_GET] ?? 0);

if (!$startRaw || !$endRaw) {
    echo json_encode(["status"=>"error","reason"=>"Dates missing"]);
    exit;
}

/* =====================================================
   CALCULATE WORKING DAYS
===================================================== */

$startDate = new DateTime(substr($startRaw, 0, 10));
$endDate   = new DateTime(substr($endRaw, 0, 10));

$workingDays = 0;
$cursor = clone $startDate;

while ($cursor <= $endDate) {
    if ((int)$cursor->format("N") <= 5) {
        $workingDays++;
    }
    $cursor->modify("+1 day");
}

$remaining = max(0, $balance - $workingDays);

/* =====================================================
   UPDATE BITRIX ITEM
===================================================== */

$ch = curl_init(BITRIX_WEBHOOK_BASE . "crm.item.update.json");

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
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
curl_close($ch);

/* =====================================================
   SUCCESS
===================================================== */

echo json_encode([
    "status" => "success",
    "item_id" => $itemId,
    "working_days" => $workingDays,
    "remaining_balance" => $remaining
]);
exit;
