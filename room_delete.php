<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: rooms.php');
    exit;
}

try {
    $pdo->beginTransaction();

    /*
        Önce bu odaya ait rezervasyonları siliyoruz.
        Çünkü rezervasyon varken oda silinirse foreign key hatası verebilir.
    */
    $deleteReservations = $pdo->prepare("
        DELETE FROM reservations
        WHERE room_id = ?
    ");
    $deleteReservations->execute([$id]);

    /*
        Sonra odayı siliyoruz.
    */
    $deleteRoom = $pdo->prepare("
        DELETE FROM rooms
        WHERE id = ?
    ");
    $deleteRoom->execute([$id]);

    $pdo->commit();

    header('Location: rooms.php?success=deleted');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();

    echo "Silme sırasında hata oluştu: " . $e->getMessage();
    exit;
}