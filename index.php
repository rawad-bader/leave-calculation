<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Leave Working Days Calculator – Bitrix24 Compatible
|--------------------------------------------------------------------------
| Compatible with:
| - Sequential Business Process → Webhook robot
| - POST + JSON payload
| - Result of previous robot
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json');

/* ==============================
   CONFIGURATION
   ============================== */
const BITRIX_WEBHOOK_BASE =
    'https://prue-dubai.bitrix24.ru/rest/1136/tnt0k89577f6vfcg/';
const ENTITY_TYPE_ID = 1120; // Smart Process entity type
const TARGET_FIELD  = 'UF_CRM_30_1767017263547'; // Total Number of Leave Days

/* ==============================
   1. READ INPUT (POST JSON)
   ============================== */
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid JSON payload',
        'raw'   => $rawInput
    ]);
    exit;
}

$startRaw = $data['START_DATE'] ?? null;
$endRaw   = $data['END_DATE'] ?? null;
$itemId   = isset($data['ID']) ? (int)$data['ID'] : 0;

if (!$startRaw || !$endRaw || $itemId <= 0) {
    http_response_code(400);
    echo json_encode([
        'error'    => 'Missing or invalid parameters',
        'received' => $data
    ]);
    exit;
}

/* ==============================
   2. DATE PARSING
   ============================== */
$startDate = DateTime::createFromFormat('d.m.Y', $startRaw);
$endDate   = DateTime::createFromFormat('d.m.Y', $endRaw);

if (!$startDate || !$endDate) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid date format, expected dd.mm.yyyy'
    ]);
    exit;
}

if ($startDate > $endDate) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Start date is after end date'
    ]);
    exit;
}

/* ==============================
   3. WORKING DAYS CALCULATION
   ============================== */
$workingDays = 0;
$cursor = clone $startDate;

while ($cursor <= $endDate) {
    // ISO-8601: 1 = Monday, 7 = Sunday
    if ((int)$cursor->format('N') <= 5) {
        $workingDays++;
    }
    $cursor->modify('+1 day');
}

/* ==============================
   4. UPDATE BITRIX ITEM
   ============================== */
$bitrixUrl = BITRIX_WEBHOOK_BASE . 'crm.item.update.json';

$payload = [
    'entityTypeId' => ENTITY_TYPE_ID,
    'id'           => $itemId,
    'fields'       => [
        TARGET_FIELD => $workingDays
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

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'cURL execution failed',
        'details'=> curl_error($ch)
    ]);
    curl_close($ch);
    exit;
}

curl_close($ch);

$result = json_decode($response, true);

if ($httpCode !== 200 || isset($result['error'])) {
    http_response_code(500);
    echo json_encode([
        'status'    => 'bitrix_error',
        'http_code'=> $httpCode,
        'response' => $result
    ]);
    exit;
}

/* ==============================
   5. SUCCESS RESPONSE
   ============================== */
echo json_encode([
    'status'        => 'success',
    'working_days'  => $workingDays
]);
