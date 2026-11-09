<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$name  = trim($input['name']  ?? '');
$phone = trim($input['phone'] ?? '');
$date  = trim($input['date']  ?? '');

if ($name === '' || $phone === '' || $date === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Заполните все поля']);
    exit;
}

if (mb_strlen($name) > 100 || mb_strlen($phone) > 50 || mb_strlen($date) > 100) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Слишком длинное значение']);
    exit;
}

$stmt = $pdo->prepare('INSERT INTO bookings (name, phone, date) VALUES (?, ?, ?)');
$stmt->execute([$name, $phone, $date]);

echo json_encode(['ok' => true]);