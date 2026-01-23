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

// Smart Process field codes
const FIELD_START_DATE   = 'UF_CRM_30_1767183075771';
const FIELD_END_DATE     = 'UF_CRM_30_1767183196848';
const FIELD_TOTAL_DAYS   = 'UF_CRM_30_1767017263547';

/**
 * ==============================
 * RECEIVE EVENT PAYLOAD
 * ==============================
 * Triggered by: ONCRMDYNAMICITEMUPDATE
 */
$data   = $_REQUEST['data']   ?? [];
$fields = $data['FIELDS']     ?? [];

// Validate entity
if (($data['ENTITY_TYPE_ID'] ?? null) != ENTITY_TYPE_ID) {
    exit(json_encode(['status' => 'ignored', 'reason' => 'not target entity']));
}

// Validate item ID
$itemId = (int)($data['ID'] ?? 0);
if ($itemId <= 0) {
    exit(json_encode(['status' => 'ignored', 'reason' => 'invalid item id']));
}

// Read required fields
$startRaw = $fields[FIELD_START_DATE] ?? null;
$endRaw   = $fields[FIELD_END_DATE]   ?? null;

if (!$startRaw || !$endRaw) {
    exit(json_encode(['status' => 'ignored', 'reason' => 'dates not set']));
}

/**
 * ==============================
 * PARSE BITRIX DATE FORMAT
 * Example: 2026-01-23T00:00:00+03:00
 * ==============================
 */
$startDate = DateTime::createFromFormat('Y-m-d', substr($startRaw, 0, 10));
$endDate   = DateTime::createFromFormat('Y-m-d', substr($endRaw,   0, 10));

if (!$startDate || !$endDate || $startDate > $endDate) {
    exit(json_encode(['status' => 'ignored', 'reason' => 'invalid dates']));
}

/**
 * ==============================
 * CALCULATE WORKING DAYS (Mon–Fri)
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
 * If value already set correctly, do nothing
 */
$currentValue = (int)($fields[FIELD_TOTAL_DAYS] ?? 0);
if ($currentValue === $workingDays) {
    exit(json_encode([
        'status' => 'skipped',
        'reason' => 'already calculated',
        'working_days' => $workingDays
    ]));
}

/**
 * ==============================
 * UPDATE SMART PROCESS ITEM
 * ==============================
 */
$bitrixUrl = BITRIX_WEBHOOK_BASE . 'crm.item.update.json';

$payload = [
    'entityTypeId' => ENTITY_TYPE_ID,
    'id' => $itemId,
    'fields' => [
        FIELD_TOTAL_DAYS => $workingDays
    ]
];

$ch = curl_init($bitrixUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 10
]);

curl_exec($ch);
curl_close($ch);

/**
 * ==============================
 * SUCCESS
 * ==============================
 */
echo json_encode([
    'status' => 'success',
    'item_id' => $itemId,
    'working_days' => $workingDays
]);
