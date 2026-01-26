<?php
declare(strict_types=1);

header("Content-Type: application/json");

/* =====================================================
   CONFIGURATION
===================================================== */

const BITRIX_WEBHOOK_BASE =
    "https://prue-dubai.bitrix24.ru/rest/1136/tnt0k89577f6vfcg/";

const ENTITY_TYPE_ID = 1120;


/* =====================================================
   INPUT
===================================================== */

$itemId = (int)($_REQUEST["ID"] ?? 0);

if ($itemId <= 0) {
    echo json_encode([
        "status" => "error",
        "reason" => "Missing ID parameter"
    ]);
    exit;
}


/* =====================================================
   FETCH ITEM FROM BITRIX
===================================================== */

$ch = curl_init(BITRIX_WEBHOOK_BASE . "crm.item.get.json");

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode([
        "entityTypeId" => ENTITY_TYPE_ID,
        "id" => $itemId
    ])
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

$item = $data["result"]["item"] ?? null;

if (!$item) {
    echo json_encode([
        "status" => "error",
        "reason" => "Item not found",
        "bitrix_response" => $data
    ]);
    exit;
}


/* =====================================================
   DEBUG OUTPUT (MANDATORY STEP)
===================================================== */

echo json_encode([
    "status" => "debug",
    "item_id" => $itemId,
    "all_fields_returned_by_bitrix" => $item
], JSON_PRETTY_PRINT);

exit;
