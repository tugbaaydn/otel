<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/header.php';

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT 
        c.*,
        COUNT(r.id) AS reservation_count,
        IFNULL(SUM(CASE WHEN r.status <> 'İptal' THEN r.total_price ELSE 0 END), 0) AS total_spent,
        MAX(r.check_in) AS last_check_in
    FROM customers c
    LEFT JOIN reservations r ON r.customer_id = c.id
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
            OR c.tc_no LIKE ?
        )
    ";

    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= "
    GROUP BY c.id
    ORDER BY c.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

function moneyFormat($value) {
    return number_format((float)$value, 2, ',', '.') . ' ₺';
}

function customerInitial($name) {
    return mb_substr($name, 0, 1, 'UTF-8');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Müşteriler</h4>
        <p class="text-muted mb-0">
            Otel müşterilerini, rezervasyon sayılarını ve toplam harcamalarını buradan takip edebilirsiniz.
        </p>
    </div>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] === 'updated'): ?>
    <div class="alert alert-success">
        Müşteri bilgileri başarıyla güncellendi.
    </div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
    <div class="alert alert-success">
        Müşteri başarıyla silindi.
    </div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'has_reservation'): ?>
    <div class="alert alert-danger">
        Bu müşteriye ait rezervasyon olduğu için müşteri silinemez.
    </div>
<?php endif; ?>

<div class="panel-card mb-4">
    <div class="panel-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-9">
                <label class="form-label">Müşteri Ara</label>
                <input 
                    type="text" 
                    name="search" 
                    class="form-control" 
                    placeholder="Ad, soyad, telefon, e-posta veya TC ile ara..."
                    value="<?php echo htmlspecialchars($search); ?>"
                >
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i>
                    Ara
                </button>

                <a href="customers.php" class="btn btn-light border">
                    Temizle
                </a>
            </div>
        </form>
    </div>
</div>

<div class="panel-card">
    <div class="panel-header">
        <h5>Müşteri Listesi</h5>

        <span class="badge bg-light text-dark">
            Toplam: <?php echo count($customers); ?>
        </span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Müşteri</th>
                    <th>Telefon</th>
                    <th>E-posta</th>
                    <th>TC Kimlik No</th>
                    <th>Rezervasyon</th>
                    <th>Toplam Harcama</th>
                    <th>Son Giriş</th>
                    <th class="text-end">İşlem</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($customers) > 0): ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="guest-avatar">
                                        <?php echo htmlspecialchars(customerInitial($customer['first_name'])); ?>
                                    </div>

                                    <div>
                                        <strong>
                                            <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                        </strong>

                                        <small class="d-block text-muted">
                                            Kayıt: <?php echo date('d.m.Y', strtotime($customer['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($customer['phone']); ?>
                            </td>

                            <td>
                                <?php echo !empty($customer['email']) ? htmlspecialchars($customer['email']) : '-'; ?>
                            </td>

                            <td>
                                <?php echo !empty($customer['tc_no']) ? htmlspecialchars($customer['tc_no']) : '-'; ?>
                            </td>

                            <td>
                                <span class="badge bg-primary">
                                    <?php echo (int)$customer['reservation_count']; ?>
                                </span>
                            </td>

                            <td>
                                <strong>
                                    <?php echo moneyFormat($customer['total_spent']); ?>
                                </strong>
                            </td>

                            <td>
                                <?php if (!empty($customer['last_check_in'])): ?>
                                    <?php echo date('d.m.Y', strtotime($customer['last_check_in'])); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <a 
                                    href="customer_edit.php?id=<?php echo $customer['id']; ?>" 
                                    class="btn btn-sm btn-light border"
                                    title="Düzenle"
                                >
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a 
                                    href="customer_delete.php?id=<?php echo $customer['id']; ?>" 
                                    class="btn btn-sm btn-danger"
                                    title="Sil"
                                    onclick="return confirm('Bu müşteriyi silmek istediğine emin misin?');"
                                >
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-people fs-1 d-block mb-2"></i>
                            Müşteri bulunamadı.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>