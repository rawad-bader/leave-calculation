<?php
// -------------------------------------------------
// CONFIG
// -------------------------------------------------

$BITRIX_WEBHOOK_URL = "https://prue-dubai.bitrix24.ru/rest/1136/5gkcxwvewdstufo/crm.item.update.json";
$ENTITY_TYPE_ID = 1120;
$TARGET_FIELD = "UF_CRM_30_1767017263547";

// -------------------------------------------------
// INPUT VALIDATION
// -------------------------------------------------

$startDate = $_GET['start_date'] ?? null;
$endDate   = $_GET['end_date'] ?? null;
$itemId    = $_GET['item_id'] ?? null;

if (!$startDate || !$endDate || !$itemId) {
    http_response_code(400);
    echo "Missing parameters";
    exit;
}

// -------------------------------------------------
// DATE PARSING (dd.mm.yyyy)
// -------------------------------------------------

$start = DateTime::createFromFormat('d.m.Y', $startDate);
$end   = DateTime::createFromFormat('d.m.Y', $endDate);

if (!$start || !$end) {
    http_response_code(400);
    echo "Invalid date format";
    exit;
}

// -------------------------------------------------
// CALCULATE WORKING DAYS (Mon–Fri)
// -------------------------------------------------

$workingDays = 0;
$current = clone $start;

while ($current <= $end) {
    $dayOfWeek = (int)$current->format('N'); // 1 = Mon, 7 = Sun
    if ($dayOfWeek <= 5) {
        $workingDays++;
    }
    $current->modify('+1 day');
}

// -------------------------------------------------
// PREPARE BITRIX REQUEST (POST + JSON)
// -------------------------------------------------

$payload = [
    "entityTypeId" => $ENTITY_TYPE_ID,
    "id" => (int)$itemId,
    "fields" => [
        $TARGET_FIELD => $workingDays
    ]
];

$options = [
    "http" => [
        "method"  => "POST",
        "header"  => "Content-Type: application/json",
        "content" => json_encode($payload),
        "timeout" => 15
    ]
];

$context = stream_context_create($options);
$response = file_get_contents($BITRIX_WEBHOOK_URL, false, $context);

// -------------------------------------------------
// ERROR HANDLING
// -------------------------------------------------

if ($response === false) {
    http_response_code(500);
    echo "Bitrix update failed";
    exit;
}

// -------------------------------------------------
// SUCCESS
// -------------------------------------------------

header('Content-Type: application/json');
echo json_encode([
    "status" => "OK",
    "item_id" => (int)$itemId,
    "working_days" => $workingDays
]);
