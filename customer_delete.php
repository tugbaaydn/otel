<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: customers.php');
    exit;
}

$checkStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservations
    WHERE customer_id = ?
");
$checkStmt->execute([$id]);
$reservationCount = $checkStmt->fetchColumn();

if ($reservationCount > 0) {
    header('Location: customers.php?error=has_reservation');
    exit;
}

$deleteStmt = $pdo->prepare("
    DELETE FROM customers
    WHERE id = ?
");
$deleteStmt->execute([$id]);

header('Location: customers.php?success=deleted');
exit;