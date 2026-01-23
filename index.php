<?php
declare(strict_types=1);

header('Content-Type: application/json');

/* ==============================
   CONFIG
   ============================== */
const BITRIX_WEBHOOK_BASE =
    'https://prue-dubai.bitrix24.ru/rest/1136/tnt0k89577f6vfcg/';
const ENTITY_TYPE_ID = 1120;
const TARGET_FIELD  = 'UF_CRM_30_1767017263547';

/* ==============================
   INPUT (Bitrix sends REQUEST vars)
   ============================== */
$startRaw = $_REQUEST['start_date'] ?? null;
$endRaw   = $_REQUEST['end_date'] ?? null;
$itemId   = isset($_REQUEST['item_id']) ? (int)$_REQUEST['item_id'] : 0;

if (!$startRaw || !$endRaw || $itemId <= 0) {
    http_response_code(400);
    echo json_encode([
        'error'    => 'Missing parameters',
        'received' => $_REQUEST
    ]);
    exit;
}

/* ==============================
   DATE PARSING
   ============================== */
$startDate = DateTime::createFromFormat('d.m.Y', $startRaw);
$endDate   = DateTime::createFromFormat('d.m.Y', $endRaw);

if (!$startDate || !$endDate || $startDate > $endDate) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid dates',
        'start' => $startRaw,
        'end'   => $endRaw
    ]);
    exit;
}

/* ==============================
   CALCULATION
   ============================== */
$workingDays = 0;
$cursor = clone $startDate;
while ($cursor <= $endDate) {
    if ((int)$cursor->format('N') <= 5) {
        $workingDays++;
    }
    $cursor->modify('+1 day');
}

/* ==============================
   UPDATE BITRIX
   ============================== */
$payload = [
    'entityTypeId' => ENTITY_TYPE_ID,
    'id'           => $itemId,
    'fields'       => [
        TARGET_FIELD => $workingDays
    ]
];

$ch = curl_init(BITRIX_WEBHOOK_BASE . 'crm.item.update.json');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload),
]);

$response = curl_exec($ch);
curl_close($ch);

/* ==============================
   RESPONSE
   ============================== */
echo json_encode([
    'status'       => 'success',
    'item_id'      => $itemId,
    'working_days' => $workingDays,
    'received'     => $_REQUEST
]);
