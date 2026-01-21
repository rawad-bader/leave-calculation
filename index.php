<?php
header('Content-Type: application/json');

/**
 * CONFIG
 */
define('BITRIX_WEBHOOK_BASE', 'https://prue-dubai.bitrix24.ru/rest/1136/tnt0k89577f6vfcg/');
define('ENTITY_TYPE_ID', 1120);
define('FIELD_TOTAL_DAYS', 'UF_CRM_30_1767017263547');

/**
 * INPUT VALIDATION
 */
$start = $_GET['start_date'] ?? null;
$end   = $_GET['end_date'] ?? null;
$itemId = $_GET['item_id'] ?? null;

if (!$start || !$end || !$itemId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

/**
 * DATE PARSING (dd.mm.yyyy)
 */
$startDate = DateTime::createFromFormat('d.m.Y', $start);
$endDate   = DateTime::createFromFormat('d.m.Y', $end);

if (!$startDate || !$endDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format. Expected dd.mm.yyyy']);
    exit;
}

/**
 * WORKING DAYS CALCULATION
 */
$days = 0;
$cursor = clone $startDate;

while ($cursor <= $endDate) {
    if ((int)$cursor->format('N') < 6) {
        $days++;
    }
    $cursor->modify('+1 day');
}

/**
 * BITRIX REQUEST (FORM-ENCODED)
 */
$url = BITRIX_WEBHOOK_BASE . 'crm.item.update';

$postFields = http_build_query([
    'entityTypeId' => ENTITY_TYPE_ID,
    'id' => (int)$itemId,
    'fields' => [
        FIELD_TOTAL_DAYS => $days
    ]
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => $postFields
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode([
        'status' => 'bitrix_error',
        'http_code' => $httpCode,
        'response' => $response
    ]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'item_id' => (int)$itemId,
    'working_days' => $days,
    'bitrix_response' => json_decode($response, true)
]);
