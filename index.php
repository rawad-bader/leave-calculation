<?php
declare(strict_types=1);

header('Content-Type: application/json');

/* =====================================================
   CONFIGURATION
===================================================== */
const BITRIX_WEBHOOK_BASE = 'https://prue-dubai.bitrix24.ru/rest/1136/tnt0k89577f6vfcg/';
const ENTITY_TYPE_ID     = 1120;

/* =====================================================
   FIELD IDS (EXACT – FROM YOUR SCREENSHOT)
===================================================== */
const FIELD_START_DATE   = 'UF_CRM_30_1767183075771'; // Start Date
const FIELD_END_DATE     = 'UF_CRM_30_1767183196848'; // End Date
const FIELD_LEAVE_BAL    = 'UF_CRM_30_1767791217511'; // Leave Balance

const FIELD_TOTAL_DAYS   = 'UF_CRM_30_1767017263547'; // Total Number of Leave Days
const FIELD_REMAINING    = 'UF_CRM_30_1767791545080'; // Remaining Leave Balance

/* =====================================================
   INPUT
===================================================== */
$itemId = (int)($_REQUEST['ID'] ?? 0);

if ($itemId <= 0) {
    echo json_encode(['status' => 'ignored', 'reason' => 'missing or invalid ID']);
    exit;
}

/* =====================================================
   FETCH ITEM FROM BITRIX
===================================================== */
$getUrl = BITRIX_WEBHOOK_BASE . 'crm.item.get.json';

$ch = curl_init($getUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode([
        'entityTypeId' => ENTITY_TYPE_ID,
        'id'           => $itemId
    ])
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$item = $data['result']['item'] ?? null;

if (!$item) {
    echo json_encode([
        'status' => 'error',
        'reason' => 'item not found',
        'raw_response' => $data
    ]);
    exit;
}

/* =====================================================
   HARD DEBUG — STOP HERE
   (THIS WILL PROVE THE ROOT CAUSE)
===================================================== */
$startRaw = $item[FIELD_START_DATE] ?? null;
$endRaw   = $item[FIELD_END_DATE] ?? null;
$balance  = $item[FIELD_LEAVE_BAL] ?? null;

echo json_encode([
    'DEBUG' => true,
    'item_id' => $itemId,
    'entityTypeId' => ENTITY_TYPE_ID,
    'start_field_id' => FIELD_START_DATE,
    'end_field_id' => FIELD_END_DATE,
    'start_value' => $startRaw,
    'end_value' => $endRaw,
    'leave_balance' => $balance,
    'item_keys_sample' => array_slice(array_keys($item), 0, 50),
]);
exit;

/* =====================================================
   THE CODE BELOW WILL NOT RUN UNTIL DEBUG IS REMOVED
===================================================== */

/* =====================================================
   VALIDATE DATES
===================================================== */
if (!$startRaw || !$endRaw) {
    echo json_encode(['status' => 'ignored', 'reason' => 'dates missing']);
    exit;
}

/* =====================================================
   PARSE DATES
===================================================== */
$startDate = DateTime::createFromFormat('Y-m-d', substr($startRaw, 0, 10));
$endDate   = DateTime::createFromFormat('Y-m-d', substr($endRaw, 0, 10));

if (!$startDate || !$endDate || $startDate > $endDate) {
    echo json_encode(['status' => 'error', 'reason' => 'invalid date range']);
    exit;
}

/* =====================================================
   CALCULATE WORKING DAYS
===================================================== */
$workingDays = 0;
$cursor = clone $startDate;

while ($cursor <= $endDate) {
    if ((int)$cursor->format('N') <= 5) {
        $workingDays++;
    }
    $cursor->modify('+1 day');
}

/* =====================================================
   CALCULATE REMAINING BALANCE
===================================================== */
$remaining = max(0, (int)$balance - $workingDays);

/* =====================================================
   UPDATE ITEM
===================================================== */
$updateUrl = BITRIX_WEBHOOK_BASE . 'crm.item.update.json';

$ch = curl_init($updateUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode([
        'entityTypeId' => ENTITY_TYPE_ID,
        'id'           => $itemId,
        'fields'       => [
            FIELD_TOTAL_DAYS => $workingDays,
            FIELD_REMAINING  => $remaining
        ]
    ])
]);

curl_exec($ch);
curl_close($ch);

echo json_encode([
    'status' => 'success',
    'item_id' => $itemId,
    'working_days' => $workingDays,
    'remaining_balance' => $remaining
]);
