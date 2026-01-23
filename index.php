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

/**
 * ==============================
 * RECEIVE EVENT PAYLOAD
 * ==============================
 * Expected from: ONCRMDYNAMICITEMUPDATE
 */
$data = $_REQUEST['data'] ?? [];

$itemId        = (int)($data['ID'] ?? 0);
$entityTypeId  = (int)($data['ENTITY_TYPE_ID'] ?? 0);

if ($itemId <= 0 || $entityTypeId !== ENTITY_TYPE_ID) {
    echo json_encode([
        'status' => 'ignored',
        'reason' => 'invalid item or entity',
        'received_data' => $data
    ]);
    exit;
}

/**
 * ==============================
 * FETCH FULL ITEM FROM BITRIX
 * ==============================
 */
$getUrl = BITRIX_WEBHOOK_BASE . 'crm.item.get.json';

$getPayload = [
    'entityTypeId' => ENTITY_TYPE_ID,
    'id' => $itemId
];

$ch = curl_init($getUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($getPayload),
    CURLOPT_TIMEOUT        => 10
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

$item = $result['result']['item'] ?? null;

if (!$item) {
    echo json_encode([
        'status' => 'error',
        'reason' => 'item not found',
        'raw_response' => $result
    ]);
    exit;
}

/**
 * ==============================
 * DEBUG BLOCK — VERY IMPORTANT
 * ==============================
 * This shows the REAL field codes stored on the item
 * Copy the date field codes from this output
 */
echo json_encode([
    'status' => 'DEBUG_MODE',
    'item_id' => $itemId,
    'item_keys' => array_keys($item),
    'item_values' => $item
], JSON_PRETTY_PRINT);
exit;

/**
 * =====================================================
 * THE CODE BELOW IS DISABLED TEMPORARILY (BY DESIGN)
 * =====================================================
 * Once we identify the real field codes, we will:
 * 1. Remove the debug block above
 * 2. Enable the calculation logic
 */
