<?php
declare(strict_types=1);

header('Content-Type: application/json');

/* ================= CONFIG ================= */

define('BITRIX_WEBHOOK_BASE', 'https://prue-dubai.bitrix24.ru/rest/1136/tnt0k89577f6vfcg');
define('ENTITY_TYPE_ID', 1120);
define('FIELD_TOTAL_LEAVE_DAYS', 'UF_CRM_30_1767017263547');

/* ================= INPUT ================= */

$start = $_GET['start_date'] ?? null;
$end   = $_GET['end_date'] ?? null;
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

if (!$start || !$end || $itemId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid parameters']);
    exit;
}

/* ================= DATE PARSING ================= */

$startDate = DateTime::createFromFormat('d.m.Y', $start);
$endDate   = DateTime::createFromFormat('d.m.Y', $end);

if (!$startDate || !$endDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format. Expected dd.mm.yyyy']);
    exit;
}

/* ================= WORKING DAYS ================= */

$workingDays = 0;
$cursor = clone $startDate;

while ($cursor <= $endDate) {
    if ((int)$cursor->format('N') < 6) {
        $workingDays++;
    }
    $cursor->modify('+1 day');
}

/* ================= BITRIX REQUEST ================= */

$bitrixUrl = rtrim(BITRIX_WEBHOOK_BASE, '/') . '/crm.item.update.json';

$payload = [
    'entityTypeId' => ENTITY_TYPE_ID,
    'id' => $itemId,
    'fields' => [
        FIELD_TOTAL_LEAVE_DAYS => $workingDays
    ]
];

$ch = curl_init($bitrixUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 20
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

/* ================= RESPONSE ================= */

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
    'item_id' => $itemId,
    'working_days' => $workingDays,
    'bitrix_response' => json_decode($response, true)
]);
