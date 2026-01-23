<?php
declare(strict_types=1);
header('Content-Type: application/json');

const BITRIX_WEBHOOK_BASE = 'https://prue-dubai.bitrix24.ru/rest/1136/tnt0k89577f6vfcg/';
const ENTITY_TYPE_ID     = 1120;

const FIELD_START_DATE = 'UF_CRM_30_1767183075771';
const FIELD_END_DATE   = 'UF_CRM_30_1767183196848';
const FIELD_TOTAL_DAYS = 'UF_CRM_30_1767017263547';

/**
 * ==============================
 * RECEIVE EVENT (ID ONLY)
 * ==============================
 */
$data = $_REQUEST['data'] ?? [];

$itemId = (int)($data['ID'] ?? 0);
$entityTypeId = (int)($data['ENTITY_TYPE_ID'] ?? 0);

if ($itemId <= 0 || $entityTypeId !== ENTITY_TYPE_ID) {
    exit(json_encode(['status' => 'ignored']));
}

/**
 * ==============================
 * FETCH FULL ITEM (CRITICAL STEP)
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
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
$item = $result['result']['item'] ?? null;

if (!$item) {
    exit(json_encode(['status' => 'error', 'reason' => 'item not found']));
}

$startRaw = $item[FIELD_START_DATE] ?? null;
$endRaw   = $item[FIELD_END_DATE]   ?? null;

if (!$startRaw || !$endRaw) {
    exit(json_encode(['status' => 'ignored', 'reason' => 'dates missing']));
}

/**
 * ==============================
 * PARSE DATES
 * ==============================
 */
$startDate = DateTime::createFromFormat('Y-m-d', substr($startRaw, 0, 10));
$endDate   = DateTime::createFromFormat('Y-m-d', substr($endRaw, 0, 10));

if (!$startDate || !$endDate || $startDate > $endDate) {
    exit(json_encode(['status' => 'invalid dates']));
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
    exit(json_encode(['status' => 'skipped']));
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
