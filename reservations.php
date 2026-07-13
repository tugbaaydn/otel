<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/header.php';

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "
    SELECT 
        r.*,
        c.first_name,
        c.last_name,
        c.phone,
        c.email,
        rm.room_number,
        rm.room_type
    FROM reservations r
    INNER JOIN customers c ON c.id = r.customer_id
    INNER JOIN rooms rm ON rm.id = r.room_id
    WHERE 1=1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            c.first_name LIKE ?
            OR c.last_name LIKE ?
            OR c.phone LIKE ?
            OR c.email LIKE ?
            OR rm.room_number LIKE ?
            OR rm.room_type LIKE ?
        )
    ";

    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status !== '') {
    $sql .= " AND r.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY r.check_in DESC, r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

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
        <h4 class="fw-bold mb-1">Rezervasyonlar</h4>
        <p class="text-muted mb-0">
            Otel rezervasyonlarını buradan takip edebilirsiniz.
        </p>
    </div>

    <a href="reservation_add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i>
        Yeni Rezervasyon
    </a>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] === 'created'): ?>
    <div class="alert alert-success">
        Rezervasyon başarıyla eklendi.
    </div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === 'updated'): ?>
    <div class="alert alert-success">
        Rezervasyon başarıyla güncellendi.
    </div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
    <div class="alert alert-success">
        Rezervasyon başarıyla silindi.
    </div>
<?php endif; ?>

<div class="panel-card mb-4">
    <div class="panel-body">
        <form method="GET" class="row g-3 align-items-end">

            <div class="col-md-5">
                <label class="form-label">Arama</label>
                <input 
                    type="text" 
                    name="search" 
                    class="form-control" 
                    placeholder="Müşteri adı, telefon, e-posta veya oda no..."
                    value="<?php echo htmlspecialchars($search); ?>"
                >
            </div>

            <div class="col-md-4">
                <label class="form-label">Durum</label>
                <select name="status" class="form-select">
                    <option value="">Tüm Durumlar</option>

                    <option value="Beklemede" <?php echo $status === 'Beklemede' ? 'selected' : ''; ?>>
                        Beklemede
                    </option>

                    <option value="Onaylandı" <?php echo $status === 'Onaylandı' ? 'selected' : ''; ?>>
                        Onaylandı
                    </option>

                    <option value="Aktif" <?php echo $status === 'Aktif' ? 'selected' : ''; ?>>
                        Aktif
                    </option>

                    <option value="Tamamlandı" <?php echo $status === 'Tamamlandı' ? 'selected' : ''; ?>>
                        Tamamlandı
                    </option>

                    <option value="İptal" <?php echo $status === 'İptal' ? 'selected' : ''; ?>>
                        İptal
                    </option>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i>
                    Filtrele
                </button>

                <a href="reservations.php" class="btn btn-light border">
                    Temizle
                </a>
            </div>

        </form>
    </div>
</div>

<div class="panel-card">
    <div class="panel-header">
        <h5>Rezervasyon Listesi</h5>

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
                    <th>Kişi</th>
                    <th>Durum</th>
                    <th>Tutar</th>
                    <th class="text-end">İşlem</th>
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

                                        <?php if (!empty($row['email'])): ?>
                                            <small class="d-block text-muted">
                                                <?php echo htmlspecialchars($row['email']); ?>
                                            </small>
                                        <?php endif; ?>
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
                                <?php echo (int)$row['guest_count']; ?>
                            </td>

                            <td>
                                <span class="badge-status <?php echo statusClass($row['status']); ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>

                            <td>
                                <strong>
                                    <?php echo moneyFormat($row['total_price']); ?>
                                </strong>
                            </td>

                            <td class="text-end">
                                <a 
                                    href="reservation_edit.php?id=<?php echo $row['id']; ?>" 
                                    class="btn btn-sm btn-light border"
                                    title="Düzenle"
                                >
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a 
                                    href="reservation_delete.php?id=<?php echo $row['id']; ?>" 
                                    class="btn btn-sm btn-danger"
                                    title="Sil"
                                    onclick="return confirm('Bu rezervasyonu silmek istediğine emin misin?');"
                                >
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                            Rezervasyon bulunamadı.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>