<?php
declare(strict_types=1);

header('Content-Type: application/json');

/* =====================================================
   CONFIGURATION
===================================================== */
const BITRIX_WEBHOOK_BASE = 'https://prue-dubai.bitrix24.ru/rest/1136/tnt0k89577f6vfcg/';
const ENTITY_TYPE_ID = 1120;

/* =====================================================
   FIELD IDS (MUST MATCH crm.item.get RESPONSE EXACTLY)
===================================================== */
const FIELD_START_DATE = 'ufCrm30_1767183075771'; // Start Date
const FIELD_END_DATE   = 'ufCrm30_1767183196848'; // End Date
const FIELD_LEAVE_BAL  = 'ufCrm30_1767791217511'; // Original Leave Balance

const FIELD_TOTAL_DAYS = 'ufCrm30_1767017263547'; // Total Leave Days
const FIELD_REMAINING  = 'ufCrm30_1767791545080'; // Remaining Leave Balance

/* =====================================================
   INPUT
===================================================== */
$itemId = (int)($_REQUEST['ID'] ?? 0);
if ($itemId <= 0) {
    echo json_encode(['status' => 'ignored', 'reason' => 'missing ID']);
    exit;
}

/* =====================================================
   FETCH ITEM
===================================================== */
$ch = curl_init(BITRIX_WEBHOOK_BASE . 'crm.item.get.json');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'entityTypeId' => ENTITY_TYPE_ID,
        'id' => $itemId
    ])
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$item = $data['result']['item'] ?? null;

if (!$item) {
    echo json_encode(['status' => 'error', 'reason' => 'item not found']);
    exit;
}

/* =====================================================
   PREVENT DOUBLE CALCULATION (IMPORTANT)
===================================================== */
if (!empty($item[FIELD_TOTAL_DAYS])) {
    echo json_encode(['status' => 'ignored', 'reason' => 'already calculated']);
    exit;
}

/* =====================================================
   READ VALUES
===================================================== */
$startRaw = $item[FIELD_START_DATE] ?? null;
$endRaw   = $item[FIELD_END_DATE] ?? null;
$balance  = (int)($item[FIELD_LEAVE_BAL] ?? 0);

if (!$startRaw || !$endRaw) {
    echo json_encode(['status' => 'ignored', 'reason' => 'dates missing']);
    exit;
}

/* =====================================================
   PARSE DATES
===================================================== */
$startDate = new DateTime(substr($startRaw, 0, 10));
$endDate   = new DateTime(substr($endRaw, 0, 10));

if ($startDate > $endDate) {
    echo json_encode(['status' => 'error', 'reason' => 'invalid date range']);
    exit;
}

/* =====================================================
   CALCULATE WORKING DAYS (MON–FRI)
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
$remaining = max(0, $balance - $workingDays);

/* =====================================================
   UPDATE ITEM (SINGLE SAFE CALL)
===================================================== */
$ch = curl_init(BITRIX_WEBHOOK_BASE . 'crm.item.update.json');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'entityTypeId' => ENTITY_TYPE_ID,
        'id' => $itemId,
        'fields' => [
            FIELD_TOTAL_DAYS => $workingDays,
            FIELD_REMAINING  => $remaining
        ]
    ])
]);

curl_exec($ch);
curl_close($ch);

/* =====================================================
   SUCCESS RESPONSE
===================================================== */
echo json_encode([
    'status' => 'success',
    'item_id' => $itemId,
    'working_days' => $workingDays,
    'remaining_balance' => $remaining
]);
