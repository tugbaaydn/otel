<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: rooms.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM rooms
    WHERE id = ?
");
$stmt->execute([$id]);
$room = $stmt->fetch();

if (!$room) {
    echo '<div class="alert alert-danger">Oda bulunamadı.</div>';
    require_once 'includes/footer.php';
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomNumber = trim($_POST['room_number'] ?? '');
    $roomType = trim($_POST['room_type'] ?? '');
    $roomDescription = trim($_POST['room_description'] ?? '');
    $roomImage = trim($_POST['room_image'] ?? '');
    $pricePerDay = trim($_POST['price_per_day'] ?? '');
    $status = trim($_POST['status'] ?? 'Boş');

    if ($roomNumber === '') {
        $errors[] = 'Oda numarası boş bırakılamaz.';
    }

    if ($roomType === '') {
        $errors[] = 'Oda tipi boş bırakılamaz.';
    }

    if ($pricePerDay === '' || !is_numeric($pricePerDay) || $pricePerDay <= 0) {
        $errors[] = 'Geçerli bir gecelik fiyat giriniz.';
    }

    if (empty($errors)) {
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM rooms
            WHERE room_number = ?
              AND id <> ?
        ");
        $checkStmt->execute([$roomNumber, $id]);
        $exists = $checkStmt->fetchColumn();

        if ($exists > 0) {
            $errors[] = 'Bu oda numarası başka bir odada kullanılıyor.';
        }
    }

    if (empty($errors)) {
        $updateStmt = $pdo->prepare("
            UPDATE rooms
            SET 
                room_number = ?,
                room_type = ?,
                room_description = ?,
                room_image = ?,
                price_per_day = ?,
                status = ?
            WHERE id = ?
        ");

        $updateStmt->execute([
            $roomNumber,
            $roomType,
            $roomDescription,
            $roomImage,
            $pricePerDay,
            $status,
            $id
        ]);

        header('Location: rooms.php?success=updated');
        exit;
    }
}

function oldValue($key, $room) {
    return htmlspecialchars($_POST[$key] ?? $room[$key] ?? '');
}

function selectedValue($value, $current) {
    return $value === $current ? 'selected' : '';
}

$previewImage = !empty($room['room_image'])
    ? $room['room_image']
    : 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1000&q=80';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Oda Düzenle</h4>
        <p class="text-muted mb-0">
            Oda numarası, tipi, açıklaması, görseli, fiyatı ve durumunu güncelleyebilirsiniz.
        </p>
    </div>

    <a href="rooms.php" class="btn btn-light border">
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
                    <h5>Oda Bilgileri</h5>
                </div>

                <div class="panel-body">

                    <div class="mb-3">
                        <label class="form-label">Oda Numarası</label>
                        <input 
                            type="text" 
                            name="room_number" 
                            class="form-control" 
                            required
                            value="<?php echo oldValue('room_number', $room); ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Oda Tipi</label>
                        <?php $currentRoomType = $_POST['room_type'] ?? $room['room_type']; ?>

                        <select name="room_type" class="form-select" required>
                            <option value="">Oda tipi seçiniz</option>

                            <option value="Standart Oda" <?php echo selectedValue('Standart Oda', $currentRoomType); ?>>
                                Standart Oda
                            </option>

                            <option value="Deluxe Oda" <?php echo selectedValue('Deluxe Oda', $currentRoomType); ?>>
                                Deluxe Oda
                            </option>

                            <option value="Suit Oda" <?php echo selectedValue('Suit Oda', $currentRoomType); ?>>
                                Suit Oda
                            </option>

                            <option value="Aile Odası" <?php echo selectedValue('Aile Odası', $currentRoomType); ?>>
                                Aile Odası
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Oda Açıklaması</label>
                        <textarea 
                            name="room_description" 
                            class="form-control" 
                            rows="4"
                            placeholder="Odanın özelliklerini yazınız..."
                        ><?php echo oldValue('room_description', $room); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Oda Görsel Linki</label>
                        <input 
                            type="text" 
                            name="room_image" 
                            id="room_image"
                            class="form-control"
                            placeholder="https://..."
                            value="<?php echo oldValue('room_image', $room); ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Gecelik Fiyat</label>
                        <input 
                            type="number" 
                            name="price_per_day" 
                            class="form-control" 
                            min="1"
                            step="0.01"
                            required
                            value="<?php echo oldValue('price_per_day', $room); ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Oda Durumu</label>
                        <?php $currentStatus = $_POST['status'] ?? $room['status']; ?>

                        <select name="status" class="form-select">
                            <option value="Boş" <?php echo selectedValue('Boş', $currentStatus); ?>>
                                Boş
                            </option>

                            <option value="Dolu" <?php echo selectedValue('Dolu', $currentStatus); ?>>
                                Dolu
                            </option>

                            <option value="Temizlikte" <?php echo selectedValue('Temizlikte', $currentStatus); ?>>
                                Temizlikte
                            </option>

                            <option value="Bakımda" <?php echo selectedValue('Bakımda', $currentStatus); ?>>
                                Bakımda
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i>
                        Odayı Güncelle
                    </button>

                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="panel-card">
                <div class="panel-header">
                    <h5>Görsel Önizleme</h5>
                </div>

                <div class="panel-body">
                    <img 
                        src="<?php echo htmlspecialchars($previewImage); ?>" 
                        id="previewImage"
                        class="room-form-preview"
                        alt="Oda Görseli"
                    >

                    <div class="alert alert-info mt-3 mb-0">
                        Buradaki görsel, odalar sayfasında oda kartına basınca detay penceresinde gösterilir.
                    </div>
                </div>
            </div>
        </div>

    </div>
</form>

<script>
const imageInput = document.getElementById('room_image');
const previewImage = document.getElementById('previewImage');

imageInput.addEventListener('input', function () {
    const imageUrl = imageInput.value.trim();

    if (imageUrl !== '') {
        previewImage.src = imageUrl;
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>