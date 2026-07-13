<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: reservations.php');
    exit;
}

$errors = [];

$stmt = $pdo->prepare("
    SELECT 
        r.*,
        c.first_name,
        c.last_name,
        c.phone,
        c.email,
        c.tc_no
    FROM reservations r
    INNER JOIN customers c ON c.id = r.customer_id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    echo '<div class="alert alert-danger">Rezervasyon bulunamadı.</div>';
    require_once 'includes/footer.php';
    exit;
}

$rooms = $pdo->query("
    SELECT *
    FROM rooms
    ORDER BY room_number ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tcNo = trim($_POST['tc_no'] ?? '');
    $roomId = (int)($_POST['room_id'] ?? 0);
    $checkIn = trim($_POST['check_in'] ?? '');
    $checkOut = trim($_POST['check_out'] ?? '');
    $guestCount = (int)($_POST['guest_count'] ?? 1);
    $status = trim($_POST['status'] ?? 'Beklemede');

    if ($firstName === '') {
        $errors[] = 'Müşteri adı boş bırakılamaz.';
    }

    if ($lastName === '') {
        $errors[] = 'Müşteri soyadı boş bırakılamaz.';
    }

    if ($phone === '') {
        $errors[] = 'Telefon numarası boş bırakılamaz.';
    }

    if ($roomId <= 0) {
        $errors[] = 'Lütfen oda seçiniz.';
    }

    if ($checkIn === '') {
        $errors[] = 'Giriş tarihi seçilmelidir.';
    }

    if ($checkOut === '') {
        $errors[] = 'Çıkış tarihi seçilmelidir.';
    }

    if ($guestCount <= 0) {
        $errors[] = 'Kişi sayısı en az 1 olmalıdır.';
    }

    if ($checkIn !== '' && $checkOut !== '') {
        $checkInDate = new DateTime($checkIn);
        $checkOutDate = new DateTime($checkOut);

        if ($checkOutDate <= $checkInDate) {
            $errors[] = 'Çıkış tarihi, giriş tarihinden sonra olmalıdır.';
        }
    }

    if (empty($errors)) {
        $roomStmt = $pdo->prepare("
            SELECT *
            FROM rooms
            WHERE id = ?
        ");
        $roomStmt->execute([$roomId]);
        $room = $roomStmt->fetch();

        if (!$room) {
            $errors[] = 'Seçilen oda bulunamadı.';
        }
    }

    if (empty($errors)) {
        $conflictStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM reservations
            WHERE room_id = ?
              AND id <> ?
              AND status <> 'İptal'
              AND check_in < ?
              AND check_out > ?
        ");

        $conflictStmt->execute([
            $roomId,
            $id,
            $checkOut,
            $checkIn
        ]);

        $conflictCount = $conflictStmt->fetchColumn();

        if ($conflictCount > 0) {
            $errors[] = 'Bu oda seçilen tarih aralığında başka bir rezervasyonda kullanılıyor.';
        }
    }

    if (empty($errors)) {
        $nightCount = $checkInDate->diff($checkOutDate)->days;
        $nightCount = max(1, $nightCount);

        $totalPrice = $nightCount * (float)$room['price_per_day'];

        try {
            $pdo->beginTransaction();

            $customerUpdate = $pdo->prepare("
                UPDATE customers
                SET 
                    first_name = ?,
                    last_name = ?,
                    phone = ?,
                    email = ?,
                    tc_no = ?
                WHERE id = ?
            ");

            $customerUpdate->execute([
                $firstName,
                $lastName,
                $phone,
                $email,
                $tcNo,
                $reservation['customer_id']
            ]);

            $reservationUpdate = $pdo->prepare("
                UPDATE reservations
                SET 
                    room_id = ?,
                    check_in = ?,
                    check_out = ?,
                    guest_count = ?,
                    total_price = ?,
                    status = ?
                WHERE id = ?
            ");

            $reservationUpdate->execute([
                $roomId,
                $checkIn,
                $checkOut,
                $guestCount,
                $totalPrice,
                $status,
                $id
            ]);

            $pdo->commit();

            header('Location: reservations.php?success=updated');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Güncelleme sırasında hata oluştu: ' . $e->getMessage();
        }
    }
}

function oldValue($key, $reservation) {
    return htmlspecialchars($_POST[$key] ?? $reservation[$key] ?? '');
}

function selectedValue($value, $current) {
    return $value == $current ? 'selected' : '';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Rezervasyon Düzenle</h4>
        <p class="text-muted mb-0">
            Müşteri, oda, tarih ve durum bilgilerini güncelleyebilirsiniz.
        </p>
    </div>

    <a href="reservations.php" class="btn btn-light border">
        <i class="bi bi-arrow-left"></i>
        Geri Dön
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong>Hata!</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST">
    <div class="row g-4">

        <div class="col-xl-7">
            <div class="panel-card">
                <div class="panel-header">
                    <h5>Müşteri Bilgileri</h5>
                </div>

                <div class="panel-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Ad</label>
                            <input 
                                type="text" 
                                name="first_name" 
                                class="form-control" 
                                required
                                value="<?php echo oldValue('first_name', $reservation); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Soyad</label>
                            <input 
                                type="text" 
                                name="last_name" 
                                class="form-control" 
                                required
                                value="<?php echo oldValue('last_name', $reservation); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Telefon</label>
                            <input 
                                type="text" 
                                name="phone" 
                                class="form-control" 
                                required
                                value="<?php echo oldValue('phone', $reservation); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">E-posta</label>
                            <input 
                                type="email" 
                                name="email" 
                                class="form-control"
                                value="<?php echo oldValue('email', $reservation); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">TC Kimlik No</label>
                            <input 
                                type="text" 
                                name="tc_no" 
                                class="form-control"
                                value="<?php echo oldValue('tc_no', $reservation); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Kişi Sayısı</label>
                            <input 
                                type="number" 
                                name="guest_count" 
                                class="form-control" 
                                min="1"
                                value="<?php echo oldValue('guest_count', $reservation); ?>"
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="panel-card">
                <div class="panel-header">
                    <h5>Rezervasyon Bilgileri</h5>
                </div>

                <div class="panel-body">
                    <div class="mb-3">
                        <label class="form-label">Oda Seçiniz</label>
                        <select name="room_id" id="room_id" class="form-select" required>
                            <option value="">Oda seçiniz</option>

                            <?php foreach ($rooms as $room): ?>
                                <?php
                                    $currentRoomId = $_POST['room_id'] ?? $reservation['room_id'];
                                ?>

                                <option 
                                    value="<?php echo $room['id']; ?>"
                                    data-price="<?php echo $room['price_per_day']; ?>"
                                    <?php echo selectedValue($room['id'], $currentRoomId); ?>
                                >
                                    Oda <?php echo htmlspecialchars($room['room_number']); ?>
                                    -
                                    <?php echo htmlspecialchars($room['room_type']); ?>
                                    -
                                    <?php echo number_format($room['price_per_day'], 2, ',', '.'); ?> ₺ / gece
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Giriş Tarihi</label>
                        <input 
                            type="date" 
                            name="check_in" 
                            id="check_in" 
                            class="form-control" 
                            required
                            value="<?php echo oldValue('check_in', $reservation); ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Çıkış Tarihi</label>
                        <input 
                            type="date" 
                            name="check_out" 
                            id="check_out" 
                            class="form-control" 
                            required
                            value="<?php echo oldValue('check_out', $reservation); ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Rezervasyon Durumu</label>
                        <?php $currentStatus = $_POST['status'] ?? $reservation['status']; ?>

                        <select name="status" class="form-select">
                            <option value="Beklemede" <?php echo selectedValue('Beklemede', $currentStatus); ?>>
                                Beklemede
                            </option>
                            <option value="Onaylandı" <?php echo selectedValue('Onaylandı', $currentStatus); ?>>
                                Onaylandı
                            </option>
                            <option value="Aktif" <?php echo selectedValue('Aktif', $currentStatus); ?>>
                                Aktif
                            </option>
                            <option value="Tamamlandı" <?php echo selectedValue('Tamamlandı', $currentStatus); ?>>
                                Tamamlandı
                            </option>
                            <option value="İptal" <?php echo selectedValue('İptal', $currentStatus); ?>>
                                İptal
                            </option>
                        </select>
                    </div>

                    <div class="price-preview">
                        <small>Tahmini Toplam Tutar</small>
                        <h3 id="total_price_preview">
                            <?php echo number_format((float)$reservation['total_price'], 2, ',', '.'); ?> ₺
                        </h3>
                        <p class="mb-0 text-muted">
                            Tutar seçilen oda fiyatı ve gece sayısına göre hesaplanır.
                        </p>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-4">
                        <i class="bi bi-check-circle"></i>
                        Değişiklikleri Kaydet
                    </button>
                </div>
            </div>
        </div>

    </div>
</form>

<script>
const roomSelect = document.getElementById('room_id');
const checkInInput = document.getElementById('check_in');
const checkOutInput = document.getElementById('check_out');
const totalPreview = document.getElementById('total_price_preview');

function formatMoney(value) {
    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: 'TRY'
    }).format(value);
}

function calculateTotal() {
    const selectedRoom = roomSelect.options[roomSelect.selectedIndex];
    const price = parseFloat(selectedRoom?.dataset?.price || 0);

    const checkIn = checkInInput.value;
    const checkOut = checkOutInput.value;

    if (!price || !checkIn || !checkOut) {
        totalPreview.textContent = '0,00 ₺';
        return;
    }

    const startDate = new Date(checkIn);
    const endDate = new Date(checkOut);

    const diffTime = endDate - startDate;
    const nightCount = diffTime / (1000 * 60 * 60 * 24);

    if (nightCount <= 0) {
        totalPreview.textContent = '0,00 ₺';
        return;
    }

    const total = price * nightCount;
    totalPreview.textContent = formatMoney(total);
}

roomSelect.addEventListener('change', calculateTotal);
checkInInput.addEventListener('change', calculateTotal);
checkOutInput.addEventListener('change', calculateTotal);

calculateTotal();
</script>

<?php require_once 'includes/footer.php'; ?>