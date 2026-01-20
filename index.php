<?php
header('Content-Type: application/json');

/**
 * 1. Validate input
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
 * 2. Calculate working days (example logic)
 */
$startDate = new DateTime($start);
$endDate   = new DateTime($end);
$days = 0;

while ($startDate <= $endDate) {
    $dayOfWeek = $startDate->format('N');
    if ($dayOfWeek < 6) { // Mon–Fri
        $days++;
    }
    $startDate->modify('+1 day');
}

/**
 * 3. Prepare Bitrix update
 */
$BITRIX_WEBHOOK = 'https://prue-dubai.bitrix24.ru/rest/1136/5gkcxxvewdstufo/';

$url = $BITRIX_WEBHOOK . 'crm.item.update.json';

$postData = [
    'entityTypeId' => 1120,
    'id' => (int)$itemId,
    'fields' => [
        'UF_CRM_30_1767017263547' => $days
    ]
];

/**
 * 4. Send POST request (IMPORTANT)
 */
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

/**
 * 5. Handle response
 */
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
    'working_days' => $days,
    'bitrix_response' => json_decode($response, true)
]);
