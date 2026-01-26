<?php
declare(strict_types=1);

header("Content-Type: application/json");

/* =====================================================
   CONFIGURATION
===================================================== */

const BITRIX_WEBHOOK_BASE =
    "https://prue-dubai.bitrix24.ru/rest/1136/tnt0k89577f6vfcg/";

const ENTITY_TYPE_ID = 1120;


/* =====================================================
   FIELD CODES (IMPORTANT BITRIX RULE)

   - GET returns Smart Process custom fields in lowercase:
       ufCrm30_xxxxx

   - UPDATE requires uppercase:
       UF_CRM_30_xxxxx
===================================================== */

/* ----- READ (GET) KEYS ----- */
const FIELD_START_DATE_GET = "ufCrm30_1767183075771";
const FIELD_END_DATE_GET   = "ufCrm30_1767183196848";
const FIELD_BALANCE_GET    = "ufCrm30_1767791217511";

/* ----- WRITE (UPDATE) KEYS ----- */
const FIELD_TOTAL_DAYS_UPD = "UF_CRM_30_1767017263547";
const FIELD_REMAINING_UPD  = "UF_CRM_30_1767791545080";


/* =====================================================
   INPUT
===================================================== */

$itemId = (int)($_REQUEST["ID"] ?? 0);

if ($itemId <= 0) {
    echo json_encode([
        "status" => "error",
        "reason" => "Missing ID parameter"
    ]);
    exit;
}


/* =====================================================
   STEP 1: FETCH ITEM FROM BITRIX
===================================================== */

$ch = curl_init(BITRIX_WEBHOOK_BASE . "crm.item.get.json");

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
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
    echo json_encode([
        "status" => "error",
        "reason" => "Item not found",
        "bitrix_response" => $data
    ]);
    exit;
}


/* =====================================================
   STEP 2: READ VALUES (LOWERCASE KEYS)
===================================================== */

$startRaw = $item[FIELD_START_DATE_GET] ?? null;
$endRaw   = $item[FIELD_END_DATE_GET] ?? null;
$balance  = (int)($item[FIELD_BALANCE_GET] ?? 0);

if (!$startRaw || !$endRaw) {
    echo json_encode([
        "status" => "error",
        "reason" => "Dates missing",
        "start_value" => $startRaw,
        "end_value" => $endRaw,
        "available_keys_sample" => array_slice(array_keys($item), 0, 15)
    ]);
    exit;
}


/* =====================================================
   STEP 3: PARSE DATES
===================================================== */

$startDate = new DateTime(substr($startRaw, 0, 10));
$endDate   = new DateTime(substr($endRaw, 0, 10));

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

    // Monday = 1 ... Friday = 5
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
   STEP 6: UPDATE ITEM BACK INTO BITRIX
===================================================== */

$ch = curl_init(BITRIX_WEBHOOK_BASE . "crm.item.update.json");

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode([
        "entityTypeId" => ENTITY_TYPE_ID,
        "id" => $itemId,
        "fields" => [
            FIELD_TOTAL_DAYS_UPD => $workingDays,
            FIELD_REMAINING_UPD  => $remaining
        ]
    ])
]);

$updateResponse = curl_exec($ch);
curl_close($ch);

$updateData = json_decode($updateResponse, true);


/* =====================================================
   STEP 7: VERIFY UPDATE SUCCESS
===================================================== */

if (!isset($updateData["result"])) {

    echo json_encode([
        "status" => "error",
        "reason" => "Bitrix update failed",
        "bitrix_response" => $updateData
    ]);
    exit;
}


/* =====================================================
   FINAL SUCCESS OUTPUT
===================================================== */

echo json_encode([
    "status" => "success",
    "item_id" => $itemId,
    "working_days" => $workingDays,
    "original_balance" => $balance,
    "remaining_balance" => $remaining,
    "bitrix_update_result" => $updateData["result"]
]);
