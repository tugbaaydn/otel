<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/header.php';

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$status = trim($_GET['status'] ?? '');
$roomType = trim($_GET['room_type'] ?? '');

$roomTypes = $pdo->query("
    SELECT DISTINCT room_type
    FROM rooms
    ORDER BY room_type ASC
")->fetchAll();

$where = "
    WHERE r.check_in BETWEEN ? AND ?
";

$params = [
    $dateFrom,
    $dateTo
];

if ($status !== '') {
    $where .= " AND r.status = ?";
    $params[] = $status;
}

if ($roomType !== '') {
    $where .= " AND rm.room_type = ?";
    $params[] = $roomType;
}

$summaryStmt = $pdo->prepare("
    SELECT
        COUNT(r.id) AS total_reservations,

        IFNULL(SUM(
            CASE 
                WHEN r.status <> 'İptal' 
                THEN r.total_price 
                ELSE 0 
            END
        ), 0) AS total_revenue,

        SUM(CASE WHEN r.status = 'Aktif' THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN r.status = 'İptal' THEN 1 ELSE 0 END) AS cancelled_count,
        SUM(CASE WHEN r.status = 'Tamamlandı' THEN 1 ELSE 0 END) AS completed_count
    FROM reservations r
    INNER JOIN rooms rm ON rm.id = r.room_id
    $where
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$listStmt = $pdo->prepare("
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
    $where
    ORDER BY r.check_in DESC, r.created_at DESC
");
$listStmt->execute($params);
$reservations = $listStmt->fetchAll();

$roomIncomeStmt = $pdo->prepare("
    SELECT
        rm.room_type,
        COUNT(r.id) AS reservation_count,
        IFNULL(SUM(
            CASE 
                WHEN r.status <> 'İptal' 
                THEN r.total_price 
                ELSE 0 
            END
        ), 0) AS income
    FROM reservations r
    INNER JOIN rooms rm ON rm.id = r.room_id
    $where
    GROUP BY rm.room_type
    ORDER BY income DESC
");
$roomIncomeStmt->execute($params);
$roomIncomeReports = $roomIncomeStmt->fetchAll();

$dailyIncomeStmt = $pdo->prepare("
    SELECT
        r.check_in,
        COUNT(r.id) AS reservation_count,
        IFNULL(SUM(
            CASE 
                WHEN r.status <> 'İptal' 
                THEN r.total_price 
                ELSE 0 
            END
        ), 0) AS income
    FROM reservations r
    INNER JOIN rooms rm ON rm.id = r.room_id
    $where
    GROUP BY r.check_in
    ORDER BY r.check_in ASC
");
$dailyIncomeStmt->execute($params);
$dailyIncomeReports = $dailyIncomeStmt->fetchAll();

function moneyFormat($value) {
    return number_format((float)$value, 2, ',', '.') . ' ₺';
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

function guestInitial($name) {
    return mb_substr($name, 0, 1, 'UTF-8');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Raporlar</h4>
        <p class="text-muted mb-0">
            Aydın Hotel rezervasyon, gelir ve oda tipi raporlarını buradan inceleyebilirsiniz.
        </p>
    </div>

    <a href="reservation_add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i>
        Yeni Rezervasyon
    </a>
</div>

<div class="panel-card mb-4">
    <div class="panel-header">
        <h5>Rapor Filtreleri</h5>
    </div>

    <div class="panel-body">
        <form method="GET" class="row g-3 align-items-end">

            <div class="col-md-3">
                <label class="form-label">Başlangıç Tarihi</label>
                <input 
                    type="date" 
                    name="date_from" 
                    class="form-control"
                    value="<?php echo htmlspecialchars($dateFrom); ?>"
                >
            </div>

            <div class="col-md-3">
                <label class="form-label">Bitiş Tarihi</label>
                <input 
                    type="date" 
                    name="date_to" 
                    class="form-control"
                    value="<?php echo htmlspecialchars($dateTo); ?>"
                >
            </div>

            <div class="col-md-2">
                <label class="form-label">Durum</label>
                <select name="status" class="form-select">
                    <option value="">Tüm Durumlar</option>
                    <option value="Beklemede" <?php echo $status === 'Beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                    <option value="Onaylandı" <?php echo $status === 'Onaylandı' ? 'selected' : ''; ?>>Onaylandı</option>
                    <option value="Aktif" <?php echo $status === 'Aktif' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="Tamamlandı" <?php echo $status === 'Tamamlandı' ? 'selected' : ''; ?>>Tamamlandı</option>
                    <option value="İptal" <?php echo $status === 'İptal' ? 'selected' : ''; ?>>İptal</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Oda Tipi</label>
                <select name="room_type" class="form-select">
                    <option value="">Tüm Odalar</option>

                    <?php foreach ($roomTypes as $type): ?>
                        <option 
                            value="<?php echo htmlspecialchars($type['room_type']); ?>"
                            <?php echo $roomType === $type['room_type'] ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($type['room_type']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-filter"></i>
                    Filtrele
                </button>

                <a href="reports.php" class="btn btn-light border">
                    Temizle
                </a>
            </div>

        </form>
    </div>
</div>

<div class="row g-4 mb-4">

    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon primary">
                    <i class="bi bi-calendar2-check"></i>
                </div>
                <div>
                    <p>Toplam Rezervasyon</p>
                    <h3 class="primary">
                        <?php echo (int)$summary['total_reservations']; ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon cyan">
                    <i class="bi bi-currency-exchange"></i>
                </div>
                <div>
                    <p>Toplam Gelir</p>
                    <h3 class="cyan">
                        <?php echo moneyFormat($summary['total_revenue']); ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon success">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <p>Aktif Rezervasyon</p>
                    <h3 class="success">
                        <?php echo (int)$summary['active_count']; ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon orange">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div>
                    <p>İptal Edilen</p>
                    <h3 class="orange">
                        <?php echo (int)$summary['cancelled_count']; ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="row g-4 mb-4">

    <div class="col-xl-6">
        <div class="panel-card">
            <div class="panel-header">
                <h5>Oda Tipine Göre Gelir</h5>
                <span class="badge bg-light text-dark">
                    <?php echo count($roomIncomeReports); ?> oda tipi
                </span>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Oda Tipi</th>
                            <th>Rezervasyon</th>
                            <th>Gelir</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($roomIncomeReports) > 0): ?>
                            <?php foreach ($roomIncomeReports as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['room_type']); ?></strong>
                                    </td>

                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo (int)$row['reservation_count']; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <strong><?php echo moneyFormat($row['income']); ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    Oda tipine göre gelir verisi bulunamadı.
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
                <h5>Günlük Gelir Özeti</h5>
                <span class="badge bg-light text-dark">
                    <?php echo count($dailyIncomeReports); ?> gün
                </span>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Rezervasyon</th>
                            <th>Gelir</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($dailyIncomeReports) > 0): ?>
                            <?php foreach ($dailyIncomeReports as $row): ?>
                                <tr>
                                    <td>
                                        <?php echo date('d.m.Y', strtotime($row['check_in'])); ?>
                                    </td>

                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo (int)$row['reservation_count']; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <strong><?php echo moneyFormat($row['income']); ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    Günlük gelir verisi bulunamadı.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="panel-card">
    <div class="panel-header">
        <h5>Rezervasyon Rapor Listesi</h5>

        <span class="badge bg-light text-dark">
            Toplam: <?php echo count($reservations); ?>
        </span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Misafir</th>
                    <th>Telefon</th>
                    <th>Oda</th>
                    <th>Giriş</th>
                    <th>Çıkış</th>
                    <th>Durum</th>
                    <th>Tutar</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($reservations) > 0): ?>
                    <?php foreach ($reservations as $row): ?>
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
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['phone']); ?>
                            </td>

                            <td>
                                <strong>
                                    Oda <?php echo htmlspecialchars($row['room_number']); ?>
                                </strong>

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
                                <span class="badge-status <?php echo statusClass($row['status']); ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>

                            <td>
                                <strong><?php echo moneyFormat($row['total_price']); ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-bar-chart fs-1 d-block mb-2"></i>
                            Seçilen filtrelere uygun rapor bulunamadı.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>