<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/header.php';

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
        ");
        $checkStmt->execute([$roomNumber]);
        $exists = $checkStmt->fetchColumn();

        if ($exists > 0) {
            $errors[] = 'Bu oda numarası zaten kayıtlı.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO rooms 
                (room_number, room_type, room_description, room_image, price_per_day, status)
            VALUES 
                (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $roomNumber,
            $roomType,
            $roomDescription,
            $roomImage,
            $pricePerDay,
            $status
        ]);

        header('Location: rooms.php?success=created');
        exit;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Yeni Oda Ekle</h4>
        <p class="text-muted mb-0">
            Aydın Hotel için oda bilgilerini ve oda görselini ekleyebilirsiniz.
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
                            placeholder="Örn: 101"
                            required
                            value="<?php echo htmlspecialchars($_POST['room_number'] ?? ''); ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Oda Tipi</label>
                        <select name="room_type" class="form-select" required>
                            <option value="">Oda tipi seçiniz</option>

                            <option value="Standart Oda" <?php echo (($_POST['room_type'] ?? '') === 'Standart Oda') ? 'selected' : ''; ?>>
                                Standart Oda
                            </option>

                            <option value="Deluxe Oda" <?php echo (($_POST['room_type'] ?? '') === 'Deluxe Oda') ? 'selected' : ''; ?>>
                                Deluxe Oda
                            </option>

                            <option value="Suit Oda" <?php echo (($_POST['room_type'] ?? '') === 'Suit Oda') ? 'selected' : ''; ?>>
                                Suit Oda
                            </option>

                            <option value="Aile Odası" <?php echo (($_POST['room_type'] ?? '') === 'Aile Odası') ? 'selected' : ''; ?>>
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
                            placeholder="Örn: Deluxe odada minibar, klima, TV, Wi-Fi ve özel banyo bulunur."
                        ><?php echo htmlspecialchars($_POST['room_description'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Oda Görsel Linki</label>
                        <input 
                            type="text" 
                            name="room_image" 
                            id="room_image"
                            class="form-control" 
                            placeholder="https://..."
                            value="<?php echo htmlspecialchars($_POST['room_image'] ?? ''); ?>"
                        >

                        <small class="text-muted">
                            Görsel linki ekleyebilirsin. Örneğin Unsplash görsel linki kullanılabilir.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Gecelik Fiyat</label>
                        <input 
                            type="number" 
                            name="price_per_day" 
                            class="form-control" 
                            placeholder="Örn: 2500"
                            min="1"
                            step="0.01"
                            required
                            value="<?php echo htmlspecialchars($_POST['price_per_day'] ?? ''); ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Oda Durumu</label>
                        <select name="status" class="form-select">
                            <option value="Boş" <?php echo (($_POST['status'] ?? '') === 'Boş') ? 'selected' : ''; ?>>
                                Boş
                            </option>

                            <option value="Dolu" <?php echo (($_POST['status'] ?? '') === 'Dolu') ? 'selected' : ''; ?>>
                                Dolu
                            </option>

                            <option value="Temizlikte" <?php echo (($_POST['status'] ?? '') === 'Temizlikte') ? 'selected' : ''; ?>>
                                Temizlikte
                            </option>

                            <option value="Bakımda" <?php echo (($_POST['status'] ?? '') === 'Bakımda') ? 'selected' : ''; ?>>
                                Bakımda
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i>
                        Odayı Kaydet
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
                        src="https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1000&q=80" 
                        id="previewImage"
                        class="room-form-preview"
                        alt="Oda Görseli"
                    >

                    <div class="alert alert-info mt-3 mb-0">
                        <strong>Örnek Deluxe Oda:</strong><br>
                        Minibar, klima, TV, ücretsiz Wi-Fi, özel banyo ve kasa gibi özellikler eklenebilir.
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