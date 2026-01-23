<?php
declare(strict_types=1);

header('Content-Type: application/json');

/* =====================================================
   CONFIGURATION
===================================================== */
const BITRIX_WEBHOOK_BASE = 'https://prue-dubai.bitrix24.ru/rest/1136/tnt0k89577f6vfcg/';
const ENTITY_TYPE_ID     = 1120;

/* =====================================================
   SOURCE FIELDS (READ FROM ITEM)
===================================================== */
const FIELD_START_DATE   = 'UF_CRM_30_1767183075771'; // Start Date
const FIELD_END_DATE     = 'UF_CRM_30_1767183196848'; // End Date
const FIELD_LEAVE_BAL    = 'UF_CRM_30_1767791217511'; // Leave Balance

/* =====================================================
   TARGET FIELDS (WRITE TO ITEM)
===================================================== */
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
   FETCH ITEM
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
    echo json_encode(['status' => 'error', 'reason' => 'item not found']);
    exit;
}

/* =====================================================
   READ VALUES
===================================================== */
$startRaw = $item[FIELD_START_DATE] ?? null;
$endRaw   = $item[FIELD_END_DATE] ?? null;
$balance  = (int)($item[FIELD_LEAVE_BAL] ?? 0);

if (!$startRaw || !$endRaw) {
    echo json_encode([
        'status' => 'ignored',
        'reason' => 'dates missing',
        'debug'  => [
            'start' => $startRaw,
            'end'   => $endRaw
        ]
    ]);
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
   CALCULATE WORKING DAYS (Mon–Fri)
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
   UPDATE ITEM (SINGLE CALL)
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

$updateResponse = curl_exec($ch);
curl_close($ch);

/* =====================================================
   SUCCESS RESPONSE
===================================================== */
echo json_encode([
    'status'             => 'success',
    'item_id'            => $itemId,
    'working_days'       => $workingDays,
    'original_balance'   => $balance,
    'remaining_balance' => $remaining
]);
