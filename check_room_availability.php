<?php
require_once 'config/db.php';

header('Content-Type: application/json; charset=utf-8');

$checkIn = $_GET['check_in'] ?? '';
$checkOut = $_GET['check_out'] ?? '';

if ($checkIn === '' || $checkOut === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Tarih bilgisi eksik.',
        'unavailable_rooms' => []
    ]);
    exit;
}

if (strtotime($checkIn) < strtotime(date('Y-m-d'))) {
    echo json_encode([
        'success' => false,
        'message' => 'Geçmiş tarihli rezervasyon oluşturulamaz.',
        'unavailable_rooms' => []
    ]);
    exit;
}

if (strtotime($checkOut) <= strtotime($checkIn)) {
    echo json_encode([
        'success' => false,
        'message' => 'Çıkış tarihi giriş tarihinden sonra olmalıdır.',
        'unavailable_rooms' => []
    ]);
    exit;
}

/*
    Seçilen tarih aralığında çakışan odaları bulur.
    Örnek:
    Oda 101, 15-18 Temmuz arasında doluysa,
    16-17 Temmuz için tekrar seçilemez.
*/
$stmt = $pdo->prepare("
    SELECT DISTINCT room_id
    FROM reservations
    WHERE status <> 'İptal'
      AND check_in < ?
      AND check_out > ?
");

$stmt->execute([
    $checkOut,
    $checkIn
]);

$bookedRooms = $stmt->fetchAll(PDO::FETCH_COLUMN);

/*
    Bakımda olan odalar da seçilemesin.
*/
$maintenanceStmt = $pdo->query("
    SELECT id
    FROM rooms
    WHERE status = 'Bakımda'
");

$maintenanceRooms = $maintenanceStmt->fetchAll(PDO::FETCH_COLUMN);

$unavailableRooms = array_unique(array_merge($bookedRooms, $maintenanceRooms));

echo json_encode([
    'success' => true,
    'message' => 'Oda müsaitlik kontrolü yapıldı.',
    'unavailable_rooms' => array_values($unavailableRooms)
]);
exit;