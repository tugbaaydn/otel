<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/header.php';

$rooms = $pdo->query("
    SELECT *
    FROM rooms
    ORDER BY room_number ASC
")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tcNo = trim($_POST['tc_no'] ?? '');
    $roomId = (int) ($_POST['room_id'] ?? 0);
    $checkIn = trim($_POST['check_in'] ?? '');
    $checkOut = trim($_POST['check_out'] ?? '');
    $guestCount = (int) ($_POST['guest_count'] ?? 1);
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

    if ($checkIn !== '' && $checkOut !== '') {
        $today = new DateTime(date('Y-m-d'));
        $checkInDate = new DateTime($checkIn);
        $checkOutDate = new DateTime($checkOut);

        if ($checkInDate < $today) {
            $errors[] = 'Geçmiş tarihli rezervasyon oluşturulamaz.';
        }

        if ($checkOutDate <= $checkInDate) {
            $errors[] = 'Çıkış tarihi, giriş tarihinden sonra olmalıdır.';
        }
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
              AND status <> 'İptal'
              AND check_in < ?
              AND check_out > ?
        ");

        $conflictStmt->execute([
            $roomId,
            $checkOut,
            $checkIn
        ]);

        $conflictCount = $conflictStmt->fetchColumn();

        if ($conflictCount > 0) {
            $errors[] = 'Bu oda seçilen tarih aralığında dolu görünüyor.';
        }
    }

    if (empty($errors)) {
        $nightCount = max(1, (int) ((strtotime($checkOut) - strtotime($checkIn)) / 86400));
        $totalPrice = $nightCount * (float) $room['price_per_day'];

        $reservationNo = 'AYD-' . date('Ymd') . '-' . rand(1000, 9999);

        try {
            $pdo->beginTransaction();

            $customerStmt = $pdo->prepare("
                INSERT INTO customers 
                    (first_name, last_name, phone, email, tc_no)
                VALUES 
                    (?, ?, ?, ?, ?)
            ");

            $customerStmt->execute([
                $firstName,
                $lastName,
                $phone,
                $email,
                $tcNo
            ]);

            $customerId = $pdo->lastInsertId();

            $reservationStmt = $pdo->prepare("
                INSERT INTO reservations
                    (reservation_no, customer_id, room_id, check_in, check_out, guest_count, total_price, status)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $reservationStmt->execute([
                $reservationNo,
                $customerId,
                $roomId,
                $checkIn,
                $checkOut,
                $guestCount,
                $totalPrice,
                $status
            ]);

            $pdo->commit();

            header('Location: reservations.php?success=created');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Kayıt sırasında hata oluştu: ' . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Yeni Rezervasyon</h4>
        <p class="text-muted mb-0">
            Aydın Hotel için yeni müşteri ve rezervasyon kaydı oluşturabilirsiniz.
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
                            <input type="text" name="first_name" class="form-control" required
                                value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Soyad</label>
                            <input type="text" name="last_name" class="form-control" required
                                value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Telefon</label>
                            <input type="text" name="phone" class="form-control" placeholder="05xx xxx xx xx" required
                                value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">E-posta</label>
                            <input type="email" name="email" class="form-control"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">TC Kimlik No</label>
                            <input type="text" name="tc_no" class="form-control"
                                value="<?php echo htmlspecialchars($_POST['tc_no'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Kişi Sayısı</label>
                            <input type="number" name="guest_count" class="form-control" min="1" required
                                value="<?php echo htmlspecialchars($_POST['guest_count'] ?? 1); ?>">
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
                                <option value="<?php echo $room['id']; ?>"
                                    data-price="<?php echo $room['price_per_day']; ?>"
                                    data-description="<?php echo htmlspecialchars($room['room_description'] ?? '', ENT_QUOTES); ?>"
                                    <?php echo (($_POST['room_id'] ?? '') == $room['id']) ? 'selected' : ''; ?>>
                                    Oda <?php echo htmlspecialchars($room['room_number']); ?>
                                    -
                                    <?php echo htmlspecialchars($room['room_type']); ?>
                                    -
                                    <?php echo number_format((float) $room['price_per_day'], 2, ',', '.'); ?> ₺ / gece
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="room_description_box" class="alert alert-info d-none">
                        <strong>Oda Özellikleri:</strong>
                        <p id="room_description_text" class="mb-0 mt-1"></p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Giriş Tarihi</label>
                        <input type="date" name="check_in" id="check_in" class="form-control"
                            min="<?php echo date('Y-m-d'); ?>" required
                            value="<?php echo htmlspecialchars($_POST['check_in'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Çıkış Tarihi</label>
                        <input type="date" name="check_out" id="check_out" class="form-control"
                            min="<?php echo date('Y-m-d'); ?>" required
                            value="<?php echo htmlspecialchars($_POST['check_out'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Rezervasyon Durumu</label>
                        <select name="status" class="form-select">
                            <option value="Beklemede" <?php echo (($_POST['status'] ?? '') === 'Beklemede') ? 'selected' : ''; ?>>
                                Beklemede
                            </option>

                            <option value="Onaylandı" <?php echo (($_POST['status'] ?? '') === 'Onaylandı') ? 'selected' : ''; ?>>
                                Onaylandı
                            </option>

                            <option value="Aktif" <?php echo (($_POST['status'] ?? '') === 'Aktif') ? 'selected' : ''; ?>>
                                Aktif
                            </option>
                        </select>
                    </div>

                    <div class="price-preview">
                        <small>Tahmini Toplam Tutar</small>
                        <h3 id="total_price_preview">0,00 ₺</h3>
                        <p class="mb-0 text-muted">
                            Tutar seçilen oda fiyatı ve gece sayısına göre hesaplanır.
                        </p>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-4">
                        <i class="bi bi-check-circle"></i>
                        Rezervasyonu Kaydet
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

const roomDescriptionBox = document.getElementById('room_description_box');
const roomDescriptionText = document.getElementById('room_description_text');

const originalOptionTexts = {};

Array.from(roomSelect.options).forEach(function(option) {
    if (option.value !== '') {
        originalOptionTexts[option.value] = option.textContent.trim();
    }
});

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

function showRoomDescription() {
    const selectedRoom = roomSelect.options[roomSelect.selectedIndex];
    const description = selectedRoom?.dataset?.description || '';

    if (description.trim() !== '') {
        roomDescriptionText.textContent = description;
        roomDescriptionBox.classList.remove('d-none');
    } else {
        roomDescriptionText.textContent = '';
        roomDescriptionBox.classList.add('d-none');
    }
}

function resetRoomOptions() {
    Array.from(roomSelect.options).forEach(function(option) {
        if (option.value !== '') {
            option.disabled = false;
            option.textContent = originalOptionTexts[option.value];
        }
    });
}

function checkRoomAvailability() {
    const checkIn = checkInInput.value;
    const checkOut = checkOutInput.value;

    resetRoomOptions();

    if (!checkIn || !checkOut) {
        return;
    }

    if (checkOut <= checkIn) {
        roomSelect.value = '';
        calculateTotal();
        showRoomDescription();
        return;
    }

    fetch(`check_room_availability.php?check_in=${checkIn}&check_out=${checkOut}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message);
                roomSelect.value = '';
                calculateTotal();
                showRoomDescription();
                return;
            }

            const unavailableRooms = data.unavailable_rooms.map(String);

            Array.from(roomSelect.options).forEach(function(option) {
                if (option.value !== '' && unavailableRooms.includes(option.value)) {
                    option.disabled = true;
                    option.textContent = originalOptionTexts[option.value] + ' - DOLU / UYGUN DEĞİL';
                }
            });

            if (roomSelect.value && unavailableRooms.includes(roomSelect.value)) {
                alert('Seçtiğiniz oda bu tarih aralığında dolu. Lütfen başka oda seçiniz.');
                roomSelect.value = '';
            }

            calculateTotal();
            showRoomDescription();
        })
        .catch(error => {
            console.error(error);
            alert('Oda müsaitlik kontrolü yapılırken hata oluştu.');
        });
}

roomSelect.addEventListener('change', function () {
    calculateTotal();
    showRoomDescription();
});

checkInInput.addEventListener('change', function () {
    checkOutInput.min = checkInInput.value;

    if (checkOutInput.value && checkOutInput.value <= checkInInput.value) {
        checkOutInput.value = '';
    }

    checkRoomAvailability();
    calculateTotal();
});

checkOutInput.addEventListener('change', function () {
    checkRoomAvailability();
    calculateTotal();
});

calculateTotal();
showRoomDescription();
checkRoomAvailability();
</script>

<?php require_once 'includes/footer.php'; ?>