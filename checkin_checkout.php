<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if ($id > 0 && in_array($action, ['checkin', 'checkout'])) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM reservations
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $reservation = $stmt->fetch();

    if ($reservation) {
        try {
            $pdo->beginTransaction();

            if ($action === 'checkin') {
                $updateReservation = $pdo->prepare("
                    UPDATE reservations
                    SET status = 'Aktif'
                    WHERE id = ?
                ");
                $updateReservation->execute([$id]);

                $updateRoom = $pdo->prepare("
                    UPDATE rooms
                    SET status = 'Dolu'
                    WHERE id = ?
                ");
                $updateRoom->execute([$reservation['room_id']]);

                $pdo->commit();

                header('Location: checkin_checkout.php?success=checkin');
                exit;
            }

            if ($action === 'checkout') {
                $updateReservation = $pdo->prepare("
                    UPDATE reservations
                    SET status = 'Tamamlandı'
                    WHERE id = ?
                ");
                $updateReservation->execute([$id]);

                $updateRoom = $pdo->prepare("
                    UPDATE rooms
                    SET status = 'Temizlikte'
                    WHERE id = ?
                ");
                $updateRoom->execute([$reservation['room_id']]);

                $pdo->commit();

                header('Location: checkin_checkout.php?success=checkout');
                exit;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            header('Location: checkin_checkout.php?error=process');
            exit;
        }
    }
}

require_once 'includes/header.php';

$todayCheckIns = $pdo->query("
    SELECT 
        r.*,
        c.first_name,
        c.last_name,
        c.phone,
        rm.room_number,
        rm.room_type
    FROM reservations r
    INNER JOIN customers c ON c.id = r.customer_id
    INNER JOIN rooms rm ON rm.id = r.room_id
    WHERE r.check_in = CURDATE()
      AND r.status IN ('Beklemede', 'Onaylandı')
    ORDER BY r.check_in ASC, r.created_at DESC
")->fetchAll();

$activeGuests = $pdo->query("
    SELECT 
        r.*,
        c.first_name,
        c.last_name,
        c.phone,
        rm.room_number,
        rm.room_type,
        DATEDIFF(r.check_out, CURDATE()) AS remaining_days
    FROM reservations r
    INNER JOIN customers c ON c.id = r.customer_id
    INNER JOIN rooms rm ON rm.id = r.room_id
    WHERE r.check_in <= CURDATE()
      AND r.check_out > CURDATE()
      AND r.status = 'Aktif'
    ORDER BY r.check_out ASC
")->fetchAll();

$todayCheckOuts = $pdo->query("
    SELECT 
        r.*,
        c.first_name,
        c.last_name,
        c.phone,
        rm.room_number,
        rm.room_type
    FROM reservations r
    INNER JOIN customers c ON c.id = r.customer_id
    INNER JOIN rooms rm ON rm.id = r.room_id
    WHERE r.check_out = CURDATE()
      AND r.status IN ('Aktif', 'Onaylandı')
    ORDER BY r.check_out ASC
")->fetchAll();

function moneyFormat($value) {
    return number_format((float)$value, 2, ',', '.') . ' ₺';
}

function guestInitial($name) {
    return mb_substr($name, 0, 1, 'UTF-8');
}

function statusClass($status) {
    $map = [
        'Beklemede' => 'badge-beklemede',
        'Onaylandı' => 'badge-onaylandi',
        'Aktif' => 'badge-aktif',
        'Tamamlandı' => 'badge-tamamlandi',
        'İptal' => 'badge-iptal'
    ];

    return $map[$status] ?? 'badge-tamamlandi';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Giriş-Çıkış İşlemleri</h4>
        <p class="text-muted mb-0">
            Aydın Hotel için bugünkü girişleri, aktif konaklayanları ve çıkışları buradan takip edebilirsiniz.
        </p>
    </div>

    <a href="reservation_add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i>
        Yeni Rezervasyon
    </a>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] === 'checkin'): ?>
    <div class="alert alert-success">
        Müşteri girişi başarıyla yapıldı. Oda durumu <strong>Dolu</strong> olarak güncellendi.
    </div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === 'checkout'): ?>
    <div class="alert alert-success">
        Müşteri çıkışı başarıyla yapıldı. Oda durumu <strong>Temizlikte</strong> olarak güncellendi.
    </div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'process'): ?>
    <div class="alert alert-danger">
        İşlem sırasında bir hata oluştu.
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">

    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon purple">
                    <i class="bi bi-box-arrow-in-right"></i>
                </div>
                <div>
                    <p>Bugünkü Girişler</p>
                    <h3 class="purple"><?php echo count($todayCheckIns); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon success">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <p>Aktif Konaklayan</p>
                    <h3 class="success"><?php echo count($activeGuests); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon orange">
                    <i class="bi bi-box-arrow-right"></i>
                </div>
                <div>
                    <p>Bugünkü Çıkışlar</p>
                    <h3 class="orange"><?php echo count($todayCheckOuts); ?></h3>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="row g-4">

    <div class="col-xl-6">
        <div class="panel-card">
            <div class="panel-header">
                <h5>Bugünkü Girişler</h5>
                <span class="badge bg-light text-dark">
                    <?php echo count($todayCheckIns); ?> kayıt
                </span>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Misafir</th>
                            <th>Oda</th>
                            <th>Durum</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($todayCheckIns) > 0): ?>
                            <?php foreach ($todayCheckIns as $row): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="guest-avatar">
                                                <?php echo htmlspecialchars(guestInitial($row['first_name'])); ?>
                                            </div>

                                            <div>
                                                <strong>
                                                    <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                </strong>
                                                <small class="d-block text-muted">
                                                    <?php echo htmlspecialchars($row['phone']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <strong>Oda <?php echo htmlspecialchars($row['room_number']); ?></strong>
                                        <small class="d-block text-muted">
                                            <?php echo htmlspecialchars($row['room_type']); ?>
                                        </small>
                                    </td>

                                    <td>
                                        <span class="badge-status <?php echo statusClass($row['status']); ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>

                                    <td class="text-end">
                                        <a 
                                            href="checkin_checkout.php?action=checkin&id=<?php echo $row['id']; ?>" 
                                            class="btn btn-sm btn-success"
                                            onclick="return confirm('Bu müşteri için giriş işlemi yapılsın mı?');"
                                        >
                                            <i class="bi bi-box-arrow-in-right"></i>
                                            Giriş Yap
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="bi bi-calendar-check fs-1 d-block mb-2"></i>
                                    Bugün giriş yapacak müşteri bulunmuyor.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="panel-card">
            <div class="panel-header">
                <h5>Bugünkü Çıkışlar</h5>
                <span class="badge bg-light text-dark">
                    <?php echo count($todayCheckOuts); ?> kayıt
                </span>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Misafir</th>
                            <th>Oda</th>
                            <th>Tutar</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($todayCheckOuts) > 0): ?>
                            <?php foreach ($todayCheckOuts as $row): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="guest-avatar">
                                                <?php echo htmlspecialchars(guestInitial($row['first_name'])); ?>
                                            </div>

                                            <div>
                                                <strong>
                                                    <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                </strong>
                                                <small class="d-block text-muted">
                                                    <?php echo htmlspecialchars($row['phone']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <strong>Oda <?php echo htmlspecialchars($row['room_number']); ?></strong>
                                        <small class="d-block text-muted">
                                            <?php echo htmlspecialchars($row['room_type']); ?>
                                        </small>
                                    </td>

                                    <td>
                                        <strong><?php echo moneyFormat($row['total_price']); ?></strong>
                                    </td>

                                    <td class="text-end">
                                        <a 
                                            href="checkin_checkout.php?action=checkout&id=<?php echo $row['id']; ?>" 
                                            class="btn btn-sm btn-warning"
                                            onclick="return confirm('Bu müşteri için çıkış işlemi yapılsın mı?');"
                                        >
                                            <i class="bi bi-box-arrow-right"></i>
                                            Çıkış Yap
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                    Bugün çıkış yapacak müşteri bulunmuyor.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="panel-card mt-4">
    <div class="panel-header">
        <h5>Aktif Konaklayan Müşteriler</h5>
        <span class="badge bg-light text-dark">
            <?php echo count($activeGuests); ?> müşteri
        </span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Misafir</th>
                    <th>Telefon</th>
                    <th>Oda</th>
                    <th>Giriş Tarihi</th>
                    <th>Çıkış Tarihi</th>
                    <th>Kalan Gün</th>
                    <th>Tutar</th>
                    <th class="text-end">İşlem</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($activeGuests) > 0): ?>
                    <?php foreach ($activeGuests as $row): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="guest-avatar">
                                        <?php echo htmlspecialchars(guestInitial($row['first_name'])); ?>
                                    </div>

                                    <div>
                                        <strong>
                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                        </strong>
                                        <small class="d-block text-muted">
                                            Aktif konaklıyor
                                        </small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['phone']); ?>
                            </td>

                            <td>
                                <strong>Oda <?php echo htmlspecialchars($row['room_number']); ?></strong>
                                <small class="d-block text-muted">
                                    <?php echo htmlspecialchars($row['room_type']); ?>
                                </small>
                            </td>

                            <td>
                                <?php echo date('d.m.Y', strtotime($row['check_in'])); ?>
                            </td>

                            <td>
                                <?php echo date('d.m.Y', strtotime($row['check_out'])); ?>
                            </td>

                            <td>
                                <span class="badge bg-info">
                                    <?php echo max(0, (int)$row['remaining_days']); ?> gün
                                </span>
                            </td>

                            <td>
                                <strong><?php echo moneyFormat($row['total_price']); ?></strong>
                            </td>

                            <td class="text-end">
                                <a 
                                    href="checkin_checkout.php?action=checkout&id=<?php echo $row['id']; ?>" 
                                    class="btn btn-sm btn-warning"
                                    onclick="return confirm('Bu müşteri için çıkış işlemi yapılsın mı?');"
                                >
                                    <i class="bi bi-box-arrow-right"></i>
                                    Çıkış Yap
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-people fs-1 d-block mb-2"></i>
                            Şu anda aktif konaklayan müşteri bulunmuyor.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>