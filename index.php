<?php
declare(strict_types=1);

header('Content-Type: application/json');

/**
 * ==============================
 * CONFIGURATION
 * ==============================
 */
const BITRIX_WEBHOOK_BASE = 'https://prue-dubai.bitrix24.ru/rest/1136/tnt0k89577f6vfcg/';
const ENTITY_TYPE_ID     = 1120;

// REAL field codes (replace after debug if needed)
const FIELD_START_DATE = 'UF_CRM_30_1767183075771';
const FIELD_END_DATE   = 'UF_CRM_30_1767183196848';
const FIELD_TOTAL_DAYS = 'UF_CRM_30_1767017263547';

/**
 * ==============================
 * READ INPUT (BITRIX EVENT OR BROWSER)
 * ==============================
 */

// Bitrix REST Event
$data = $_REQUEST['data'] ?? null;

if ($data) {
    $itemId       = (int)($data['ID'] ?? 0);
    $entityTypeId = (int)($data['ENTITY_TYPE_ID'] ?? 0);
}
// Browser / Postman test
else {
    $itemId       = (int)($_REQUEST['ID'] ?? 0);
    $entityTypeId = (int)($_REQUEST['ENTITY_TYPE_ID'] ?? 0);
}

if ($itemId <= 0 || $entityTypeId !== ENTITY_TYPE_ID) {
    echo json_encode([
        'status' => 'ignored',
        'reason' => 'invalid item or entity',
        'received' => $_REQUEST
    ]);
    exit;
}

/**
 * ==============================
 * FETCH FULL ITEM
 * ==============================
 */
$getUrl = BITRIX_WEBHOOK_BASE . 'crm.item.get.json';

$getPayload = [
    'entityTypeId' => ENTITY_TYPE_ID,
    'id' => $itemId
];

$ch = curl_init($getUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($getPayload),
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
$item = $result['result']['item'] ?? null;

if (!$item) {
    echo json_encode([
        'status' => 'error',
        'reason' => 'item not found',
        'raw' => $result
    ]);
    exit;
}

/**
 * ==============================
 * DEBUG (TEMPORARY)
 * ==============================
 */
if (isset($_REQUEST['DEBUG'])) {
    echo json_encode([
        'status' => 'DEBUG',
        'item_keys' => array_keys($item),
        'item' => $item
    ], JSON_PRETTY_PRINT);
    exit;
}

/**
 * ==============================
 * READ DATES
 * ==============================
 */
$startRaw = $item[FIELD_START_DATE] ?? null;
$endRaw   = $item[FIELD_END_DATE]   ?? null;

if (!$startRaw || !$endRaw) {
    echo json_encode([
        'status' => 'ignored',
        'reason' => 'dates missing',
        'available_fields' => array_keys($item)
    ]);
    exit;
}

/**
 * ==============================
 * PARSE DATES
 * ==============================
 */
$startDate = DateTime::createFromFormat('Y-m-d', substr($startRaw, 0, 10));
$endDate   = DateTime::createFromFormat('Y-m-d', substr($endRaw, 0, 10));

if (!$startDate || !$endDate || $startDate > $endDate) {
    echo json_encode(['status' => 'invalid dates']);
    exit;
}

/**
 * ==============================
 * CALCULATE WORKING DAYS
 * ==============================
 */
$workingDays = 0;
$cursor = clone $startDate;

while ($cursor <= $endDate) {
    if ((int)$cursor->format('N') <= 5) {
        $workingDays++;
    }
    $cursor->modify('+1 day');
}

/**
 * ==============================
 * LOOP PROTECTION
 * ==============================
 */
if ((int)($item[FIELD_TOTAL_DAYS] ?? 0) === $workingDays) {
    echo json_encode([
        'status' => 'skipped',
        'working_days' => $workingDays
    ]);
    exit;
}

/**
 * ==============================
 * UPDATE ITEM
 * ==============================
 */
$updateUrl = BITRIX_WEBHOOK_BASE . 'crm.item.update.json';

$updatePayload = [
    'entityTypeId' => ENTITY_TYPE_ID,
    'id' => $itemId,
    'fields' => [
        FIELD_TOTAL_DAYS => $workingDays
    ]
];

$ch = curl_init($updateUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($updatePayload),
]);

curl_exec($ch);
curl_close($ch);

echo json_encode([
    'status' => 'success',
    'item_id' => $itemId,
    'working_days' => $workingDays
]);
