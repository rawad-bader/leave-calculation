<?php

// ================= CONFIG =================
$BITRIX_WEBHOOK =
    'https://prue-dubai.bitrix24.ru/rest/1136/5gkcxwvewdstufo/crm.item.update.json';

$ENTITY_TYPE_ID = 1120;
$FIELD_CODE = 'UF_CRM_30_1767017263547';

// ================= INPUT =================
$start = $_GET['start_date'] ?? null;
$end   = $_GET['end_date'] ?? null;
$item  = $_GET['item_id'] ?? null;

if (!$start || !$end || !$item) {
    http_response_code(400);
    echo 'Missing parameters';
    exit;
}

// ================= DATE PARSE =================
$startDate = DateTime::createFromFormat('d.m.Y', $start);
$endDate   = DateTime::createFromFormat('d.m.Y', $end);

if (!$startDate || !$endDate) {
    http_response_code(400);
    echo 'Invalid date format';
    exit;
}

// ================= CALCULATE WORKDAYS =================
$days = 0;
$cursor = clone $startDate;

while ($cursor <= $endDate) {
    if ((int)$cursor->format('N') <= 5) {
        $days++;
    }
    $cursor->modify('+1 day');
}

// ================= BITRIX PAYLOAD =================
$payload = json_encode([
    'entityTypeId' => $ENTITY_TYPE_ID,
    'id' => (int)$item,
    'fields' => [
        $FIELD_CODE => $days
    ]
]);

$context = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => 20
    ]
]);

$response = file_get_contents($BITRIX_WEBHOOK, false, $context);

// ================= RESULT =================
if ($response === false) {
    http_response_code(500);
    echo 'Bitrix API call failed';
    exit;
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'OK',
    'item_id' => (int)$item,
    'working_days' => $days,
    'bitrix_response' => json_decode($response, true)
]);
