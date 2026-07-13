<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM reservations WHERE id = ?");
    $stmt->execute([$id]);
}
header('Location: reservations.php');
exit;
?>
