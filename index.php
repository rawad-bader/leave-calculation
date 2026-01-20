<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('UTC'); // change to 'Asia/Dubai' if you want

// Only allow POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

// Read JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

$start = $data['START_DATE'] ?? null;
$end   = $data['END_DATE'] ?? null;

if (!$start || !$end) {
    http_response_code(400);
    echo json_encode(['error' => 'START_DATE and END_DATE are required']);
    exit;
}

try {
    $startDt = new DateTime(substr((string)$start, 0, 10));
    $endDt   = new DateTime(substr((string)$end, 0, 10));
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

// Inclusive range
$endDt->modify('+1 day');

$period = new DatePeriod($startDt, new DateInterval('P1D'), $endDt);

$workingDays = 0;
foreach ($period as $day) {
    $dow = (int)$day->format('N'); // 1=Mon ... 6=Sat 7=Sun
    if ($dow !== 6 && $dow !== 7) { // weekend: Sat+Sun
        $workingDays++;
    }
}

echo json_encode(['result' => $workingDays]);
