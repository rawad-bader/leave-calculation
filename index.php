<?php

/* =========================================
   1. Read parameters sent from Bitrix
========================================= */
$start  = $_GET['start_date'] ?? null;
$end    = $_GET['end_date'] ?? null;
$itemId = $_GET['item_id'] ?? null;

if (!$start || !$end || !$itemId) {
    http_response_code(400);
    exit('Missing parameters');
}

/* =========================================
   2. Calculate working days
   (Saturday & Sunday are weekends)
========================================= */
$startDt = new DateTime($start);
$endDt   = new DateTime($end);
$endDt->modify('+1 day'); // inclusive

$period = new DatePeriod($startDt, new DateInterval('P1D'), $endDt);

$workingDays = 0;
foreach ($period as $day) {
    if ((int)$day->format('N') < 6) { // 1–5 = Mon–Fri
        $workingDays++;
    }
}

/* =========================================
   3. Update Bitrix Leave Request via Inbound Webhook
========================================= */
$BITRIX_WEBHOOK = 'https://prue-dubai.bitrix24.ru/rest/1136/5gkcxwvevwdstufo/';
$ENTITY_TYPE_ID = 1120;
$FIELD_CODE     = 'UF_CRM_30_1767017263547';

$url = $BITRIX_WEBHOOK . 'crm.item.update.json';

$params = [
    'entityTypeId' => $ENTITY_TYPE_ID,
    'id' => $itemId,
    'fields' => [
        $FIELD_CODE => $workingDays
    ]
];

file_get_contents($url . '?' . http_build_query($params));

echo 'OK';
