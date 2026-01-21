<?php
declare(strict_types=1);

header('Content-Type: application/json');

/**
 * ==============================
 * CONFIGURATION
 * ==============================
 */
// Ensure this is your dedicated INBOUND webhook URL for writing data back
const BITRIX_WEBHOOK_BASE = 'https://prue-dubai.bitrix24.ru';
const ENTITY_TYPE_ID = 1120;
const TARGET_FIELD = 'UF_CRM_30_1767017263547';

/**
 * ==============================
 * 1. INPUT VALIDATION (UPDATED)
 * ==============================
 */

// Read the raw POST data sent by the Bitrix24 Outbound Webhook
$postData = json_decode(file_get_contents('php://input'), true);

// Bitrix wraps parameters in a 'data' object when sending via Outbound Webhook POST
$params = $postData['data'] ?? [];

$startRaw = $params['start_date'] ?? null;
$endRaw   = $params['end_date'] ?? null;
$itemId   = isset($params['item_id']) ? (int)$params['item_id'] : 0;

if (!$startRaw || !$endRaw || $itemId <= 0) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing or invalid parameters in POST data',
        'received_data' => $postData // Helpful for debugging
    ]);
    exit;
}

/**
 * ==============================
 * 2. DATE PARSING (Bitrix format)
 * ==============================
 */
$startDate = DateTime::createFromFormat('d.m.Y', $startRaw);
$endDate   = DateTime::createFromFormat('d.m.Y', $endRaw);

if (!$startDate || !$endDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format. Expected dd.mm.yyyy']);
    exit;
}
if ($startDate > $endDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Start date cannot be after end date']);
    exit;
}

/**
 * ==============================
 * 3. WORKING DAYS CALCULATION
 * ==============================
 */
$workingDays = 0;
$cursor = clone $startDate;

while ($cursor <= $endDate) {
    $dayOfWeek = (int)$cursor->format('N'); // 1 = Mon, 7 = Sun
    if ($dayOfWeek <= 5) {
        $workingDays++;
    }
    $cursor->modify('+1 day');
}

/**
 * ==============================
 * 4. PREPARE BITRIX REQUEST
 * ==============================
 */
$bitrixUrl = BITRIX_WEBHOOK_BASE . 'crm.item.update.json';

$payload = [
    'entityTypeId' => ENTITY_TYPE_ID,
    'id'           => $itemId,
    'fields'       => [
        TARGET_FIELD => $workingDays
    ]
];

/**
 * ==============================
 * 5. SEND REQUEST (cURL)
 * ==============================
 */
$ch = curl_init($bitrixUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 10
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    http_response_code(500);
    echo json_encode([
        'error' => 'cURL error',
        'details' => curl_error($ch)
    ]);
    curl_close($ch);
    exit;
}

curl_close($ch);

/**
 * ==============================
 * 6. RESPONSE HANDLING & SUCCESS
 * ==============================
 */
$result = json_decode($response, true);

if ($httpCode !== 200 || isset($result['error'])) {
    http_response_code(500);
    echo json_encode([
        'status'    => 'bitrix_error',
        'http_code' => $httpCode,
        'response'  => $result
    ]);
    exit;
}

echo json_encode([
    'status'       => 'success',
    'item_id'      => $itemId,
    'working_days' => $workingDays
]);
