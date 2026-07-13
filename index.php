<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/header.php';

function moneyFormat($value) {
    return number_format((float)$value, 2, ',', '.') . ' ₺';
}

function statusClass($status) {
    $map = [
        'Beklemede' => 'status-waiting',
        'Onaylandı' => 'status-approved',
        'Aktif' => 'status-active',
        'Tamamlandı' => 'status-completed',
        'İptal' => 'status-cancelled'
    ];

    return $map[$status] ?? 'status-waiting';
}

function guestInitial($name) {
    $name = trim((string)$name);

    if ($name === '') {
        return 'M';
    }

    return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
}

/*
    Üst kart verileri
*/
$totalReservations = $pdo->query("
    SELECT COUNT(*) 
    FROM reservations
")->fetchColumn();

$totalRooms = $pdo->query("
    SELECT COUNT(*) 
    FROM rooms
")->fetchColumn();

$emptyRooms = $pdo->query("
    SELECT COUNT(*) 
    FROM rooms 
    WHERE status = 'Boş'
")->fetchColumn();

$fullRooms = $pdo->query("
    SELECT COUNT(*) 
    FROM rooms 
    WHERE status = 'Dolu'
")->fetchColumn();

$cleaningRooms = $pdo->query("
    SELECT COUNT(*) 
    FROM rooms 
    WHERE status = 'Temizlikte'
")->fetchColumn();

$maintenanceRooms = $pdo->query("
    SELECT COUNT(*) 
    FROM rooms 
    WHERE status = 'Bakımda'
")->fetchColumn();

$todayRevenue = $pdo->query("
    SELECT IFNULL(SUM(total_price), 0)
    FROM reservations
    WHERE status = 'Tamamlandı'
      AND check_out = CURDATE()
")->fetchColumn();

/*
    Rezervasyon istatistikleri
*/
$completedReservations = $pdo->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE status = 'Tamamlandı'
")->fetchColumn();

$waitingReservations = $pdo->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE status IN ('Beklemede', 'Onaylandı')
")->fetchColumn();

$cancelledReservations = $pdo->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE status = 'İptal'
")->fetchColumn();

/*
    Aktif rezervasyonlar
*/
$activeReservations = $pdo->query("
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
    WHERE r.status IN ('Beklemede', 'Onaylandı', 'Aktif')
      AND r.check_out >= CURDATE()
    ORDER BY r.check_in ASC, r.created_at DESC
    LIMIT 5
")->fetchAll();

/*
    Katlara göre oda dağılımı
*/
$floorStats = $pdo->query("
    SELECT 
        CASE 
            WHEN LEFT(room_number, 1) = '0' THEN 'Zemin Kat'
            ELSE CONCAT(LEFT(room_number, 1), '. Kat')
        END AS floor_name,
        CAST(LEFT(room_number, 1) AS UNSIGNED) AS floor_order,
        COUNT(*) AS total_count,
        SUM(CASE WHEN status = 'Boş' THEN 1 ELSE 0 END) AS empty_count
    FROM rooms
    GROUP BY floor_name, floor_order
    ORDER BY floor_order ASC
")->fetchAll();

$maxFloorTotal = 1;

foreach ($floorStats as $floor) {
    if ((int)$floor['total_count'] > $maxFloorTotal) {
        $maxFloorTotal = (int)$floor['total_count'];
    }
}

/*
    Oda durum yüzdeleri
*/
$roomTotalSafe = $totalRooms > 0 ? $totalRooms : 1;

$emptyPercent = round(($emptyRooms / $roomTotalSafe) * 100);
$fullPercent = round(($fullRooms / $roomTotalSafe) * 100);
$cleaningPercent = round(($cleaningRooms / $roomTotalSafe) * 100);
$maintenancePercent = round(($maintenanceRooms / $roomTotalSafe) * 100);

$emptyDeg = ($emptyRooms / $roomTotalSafe) * 360;
$fullDeg = $emptyDeg + (($fullRooms / $roomTotalSafe) * 360);
$cleaningDeg = $fullDeg + (($cleaningRooms / $roomTotalSafe) * 360);
?>

<div class="dashboard-top-title mb-4">
    <div>
        <h4 class="fw-bold mb-1">Dashboard</h4>
        <p class="text-muted mb-0">
            Aydın Hotel rezervasyon, oda durumu ve gelir takibi
        </p>
    </div>

    <a href="reservation_add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i>
        Rezervasyon Ekle
    </a>
</div>

<div class="row g-4 mb-4">

    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="dashboard-stat-card">
            <div class="dashboard-stat-icon primary">
                <i class="bi bi-calendar2-check"></i>
            </div>

            <div>
                <span>Toplam Rezervasyon</span>
                <h3><?php echo (int)$totalReservations; ?></h3>
                <small>Tüm kayıtlar</small>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="dashboard-stat-card">
            <div class="dashboard-stat-icon success">
                <i class="bi bi-door-open"></i>
            </div>

            <div>
                <span>Boş Odalar</span>
                <h3><?php echo (int)$emptyRooms; ?></h3>
                <small>Toplam <?php echo (int)$totalRooms; ?> odadan</small>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="dashboard-stat-card">
            <div class="dashboard-stat-icon danger">
                <i class="bi bi-person-fill-lock"></i>
            </div>

            <div>
                <span>Dolu Odalar</span>
                <h3><?php echo (int)$fullRooms; ?></h3>
                <small>Konaklayan müşteri</small>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="dashboard-stat-card">
            <div class="dashboard-stat-icon warning">
                <i class="bi bi-brush"></i>
            </div>

            <div>
                <span>Temizlikte</span>
                <h3><?php echo (int)$cleaningRooms; ?></h3>
                <small>Hazırlanan oda</small>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="dashboard-stat-card">
            <div class="dashboard-stat-icon secondary">
                <i class="bi bi-wrench"></i>
            </div>

            <div>
                <span>Bakımda</span>
                <h3><?php echo (int)$maintenanceRooms; ?></h3>
                <small>Kullanıma kapalı</small>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="dashboard-stat-card">
            <div class="dashboard-stat-icon purple">
                <i class="bi bi-cash-stack"></i>
            </div>

            <div>
                <span>Bugünkü Gelir</span>
                <h3 class="money-text"><?php echo moneyFormat($todayRevenue); ?></h3>
                <small>Bugün çıkış yapanlar</small>
            </div>
        </div>
    </div>

</div>

<div class="row g-4 mb-4">

    <div class="col-xl-6">
        <div class="panel-card h-100">
            <div class="panel-header">
                <div>
                    <h5>Rezervasyon İstatistikleri</h5>
                    <small class="text-muted">Genel rezervasyon durumları</small>
                </div>

                <select class="form-select dashboard-select">
                    <option>Bu Ay</option>
                    <option>Bu Hafta</option>
                    <option>Bu Yıl</option>
                </select>
            </div>

            <div class="panel-body">
                <div class="reservation-summary-grid">

                    <div class="reservation-summary-box">
                        <h4><?php echo (int)$totalReservations; ?></h4>
                        <span>Toplam Rezervasyon</span>
                        <small class="text-success">
                            <i class="bi bi-arrow-up"></i>
                            Genel kayıt
                        </small>
                    </div>

                    <div class="reservation-summary-box">
                        <h4><?php echo (int)$completedReservations; ?></h4>
                        <span>Tamamlanan</span>
                        <small class="text-success">
                            <i class="bi bi-check-circle"></i>
                            Başarılı
                        </small>
                    </div>

                    <div class="reservation-summary-box">
                        <h4><?php echo (int)$waitingReservations; ?></h4>
                        <span>Beklemede</span>
                        <small class="text-warning">
                            <i class="bi bi-clock"></i>
                            İşlem bekliyor
                        </small>
                    </div>

                    <div class="reservation-summary-box danger-soft">
                        <h4><?php echo (int)$cancelledReservations; ?></h4>
                        <span>İptal Edilen</span>
                        <small class="text-danger">
                            <i class="bi bi-x-circle"></i>
                            İptal
                        </small>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="panel-card h-100">
            <div class="panel-header">
                <div>
                    <h5>Aktif Rezervasyonlar</h5>
                    <small class="text-muted">
                        Bekleyen, onaylanan ve aktif rezervasyonlar
                    </small>
                </div>

                <a href="reservations.php" class="btn btn-light border">
                    Tümünü Gör
                </a>
            </div>

            <div class="panel-body">
                <?php if (count($activeReservations) > 0): ?>

                    <div class="active-reservation-list">
                        <?php foreach ($activeReservations as $row): ?>
                            <div class="active-reservation-row">
                                <div class="guest-info">
                                    <div class="guest-avatar">
                                        <?php echo htmlspecialchars(guestInitial($row['first_name'])); ?>
                                    </div>

                                    <div>
                                        <strong>
                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                        </strong>

                                        <small>
                                            <?php echo htmlspecialchars($row['phone']); ?>
                                        </small>
                                    </div>
                                </div>

                                <div>
                                    <strong>Oda <?php echo htmlspecialchars($row['room_number']); ?></strong>
                                    <small><?php echo htmlspecialchars($row['room_type']); ?></small>
                                </div>

                                <div>
                                    <strong><?php echo date('d.m.Y', strtotime($row['check_in'])); ?></strong>
                                    <small>Giriş</small>
                                </div>

                                <div>
                                    <strong><?php echo date('d.m.Y', strtotime($row['check_out'])); ?></strong>
                                    <small>Çıkış</small>
                                </div>

                                <div class="text-end">
                                    <strong><?php echo moneyFormat($row['total_price']); ?></strong>
                                    <span class="badge-status <?php echo statusClass($row['status']); ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <a href="reservation_add.php" class="btn btn-primary w-100 mt-3">
                        <i class="bi bi-plus-circle"></i>
                        Rezervasyon Ekle
                    </a>

                <?php else: ?>

                    <div class="empty-dashboard-box">
                        <div class="empty-icon">
                            <i class="bi bi-calendar-plus"></i>
                        </div>

                        <h5>Henüz aktif rezervasyon yok</h5>

                        <p>
                            Yeni rezervasyon oluşturarak aktif rezervasyonları burada görüntüleyebilirsin.
                        </p>

                        <a href="reservation_add.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i>
                            Rezervasyon Ekle
                        </a>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<div class="row g-4">

    <div class="col-xl-4">
        <div class="panel-card h-100">
            <div class="panel-header">
                <h5>Oda Durumu Özeti</h5>
            </div>

            <div class="panel-body">
                <div class="room-donut-wrapper">
                    <div 
                        class="room-donut"
                        style="background: conic-gradient(
                            #22c55e 0deg <?php echo $emptyDeg; ?>deg,
                            #ef4444 <?php echo $emptyDeg; ?>deg <?php echo $fullDeg; ?>deg,
                            #f59e0b <?php echo $fullDeg; ?>deg <?php echo $cleaningDeg; ?>deg,
                            #64748b <?php echo $cleaningDeg; ?>deg 360deg
                        );"
                    >
                        <div class="room-donut-inner">
                            <h3><?php echo (int)$totalRooms; ?></h3>
                            <span>Toplam Oda</span>
                        </div>
                    </div>
                </div>

                <div class="room-summary-list mt-4">

                    <div class="room-summary-item">
                        <span><i class="dot-success"></i> Boş Odalar</span>
                        <strong><?php echo (int)$emptyRooms; ?></strong>
                        <small>%<?php echo $emptyPercent; ?></small>
                    </div>

                    <div class="room-summary-item">
                        <span><i class="dot-danger"></i> Dolu Odalar</span>
                        <strong><?php echo (int)$fullRooms; ?></strong>
                        <small>%<?php echo $fullPercent; ?></small>
                    </div>

                    <div class="room-summary-item">
                        <span><i class="dot-warning"></i> Temizlikte</span>
                        <strong><?php echo (int)$cleaningRooms; ?></strong>
                        <small>%<?php echo $cleaningPercent; ?></small>
                    </div>

                    <div class="room-summary-item">
                        <span><i class="dot-secondary"></i> Bakımda</span>
                        <strong><?php echo (int)$maintenanceRooms; ?></strong>
                        <small>%<?php echo $maintenancePercent; ?></small>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="panel-card h-100">
            <div class="panel-header">
                <h5>Katlara Göre Oda Dağılımı</h5>
            </div>

            <div class="panel-body">
                <?php if (count($floorStats) > 0): ?>
                    <div class="floor-bars">

                        <?php foreach ($floorStats as $floor): ?>
                            <?php
                                $totalWidth = ((int)$floor['total_count'] / $maxFloorTotal) * 100;
                                $emptyWidth = ((int)$floor['empty_count'] / $maxFloorTotal) * 100;
                            ?>

                            <div class="floor-bar-row">
                                <div class="floor-bar-label">
                                    <?php echo htmlspecialchars($floor['floor_name']); ?>
                                </div>

                                <div class="floor-bar-area">
                                    <div 
                                        class="floor-bar-total"
                                        style="width: <?php echo $totalWidth; ?>%;"
                                    ></div>

                                    <div 
                                        class="floor-bar-empty"
                                        style="width: <?php echo $emptyWidth; ?>%;"
                                    ></div>
                                </div>

                                <div class="floor-bar-count">
                                    <?php echo (int)$floor['total_count']; ?>
                                </div>
                            </div>

                        <?php endforeach; ?>

                    </div>

                    <div class="bar-legend mt-3">
                        <span><i class="bar-dot total"></i> Toplam</span>
                        <span><i class="bar-dot empty"></i> Boş</span>
                    </div>
                <?php else: ?>
                    <div class="text-muted text-center py-5">
                        Henüz oda kaydı bulunmuyor.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="panel-card h-100">
            <div class="panel-header">
                <h5>Hızlı İşlemler</h5>
            </div>

            <div class="panel-body">
                <div class="quick-action-grid">

                    <a href="reservation_add.php" class="quick-action-card blue">
                        <div>
                            <i class="bi bi-calendar-plus"></i>
                        </div>

                        <span>Rezervasyon Ekle</span>
                        <i class="bi bi-chevron-right"></i>
                    </a>

                    <a href="room_add.php" class="quick-action-card green">
                        <div>
                            <i class="bi bi-door-open"></i>
                        </div>

                        <span>Oda Ekle</span>
                        <i class="bi bi-chevron-right"></i>
                    </a>

                    <a href="customers.php" class="quick-action-card purple">
                        <div>
                            <i class="bi bi-person-plus"></i>
                        </div>

                        <span>Müşteri Listesi</span>
                        <i class="bi bi-chevron-right"></i>
                    </a>

                    <a href="reports.php" class="quick-action-card orange">
                        <div>
                            <i class="bi bi-bar-chart"></i>
                        </div>

                        <span>Raporlar</span>
                        <i class="bi bi-chevron-right"></i>
                    </a>

                    <a href="rooms.php?status=Boş" class="quick-action-card green">
                        <div>
                            <i class="bi bi-check-circle"></i>
                        </div>

                        <span>Boş Odalar</span>
                        <i class="bi bi-chevron-right"></i>
                    </a>

                    <a href="rooms.php?status=Temizlikte" class="quick-action-card orange">
                        <div>
                            <i class="bi bi-brush"></i>
                        </div>

                        <span>Temizlikte Odalar</span>
                        <i class="bi bi-chevron-right"></i>
                    </a>

                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>