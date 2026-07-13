<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/header.php';

$errors = [];

$stmt = $pdo->query("
    SELECT *
    FROM settings
    WHERE id = 1
");
$settings = $stmt->fetch();

if (!$settings) {
    $pdo->query("
        INSERT INTO settings 
            (id, hotel_name, phone, email, address, tax_no, checkin_time, checkout_time)
        VALUES
            (1, 'Aydın Hotel', '', '', '', '', '14:00:00', '12:00:00')
    ");

    $stmt = $pdo->query("
        SELECT *
        FROM settings
        WHERE id = 1
    ");
    $settings = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hotelName = trim($_POST['hotel_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $taxNo = trim($_POST['tax_no'] ?? '');
    $checkinTime = trim($_POST['checkin_time'] ?? '');
    $checkoutTime = trim($_POST['checkout_time'] ?? '');

    if ($hotelName === '') {
        $errors[] = 'Otel adı boş bırakılamaz.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir e-posta adresi giriniz.';
    }

    if ($checkinTime === '') {
        $errors[] = 'Giriş saati boş bırakılamaz.';
    }

    if ($checkoutTime === '') {
        $errors[] = 'Çıkış saati boş bırakılamaz.';
    }

    if (empty($errors)) {
        $updateStmt = $pdo->prepare("
            UPDATE settings
            SET 
                hotel_name = ?,
                phone = ?,
                email = ?,
                address = ?,
                tax_no = ?,
                checkin_time = ?,
                checkout_time = ?
            WHERE id = 1
        ");

        $updateStmt->execute([
            $hotelName,
            $phone,
            $email,
            $address,
            $taxNo,
            $checkinTime,
            $checkoutTime
        ]);

        header('Location: settings.php?success=updated');
        exit;
    }
}

function oldValue($key, $settings) {
    return htmlspecialchars($_POST[$key] ?? $settings[$key] ?? '');
}

function timeValue($key, $settings) {
    $value = $_POST[$key] ?? $settings[$key] ?? '';
    return $value ? substr($value, 0, 5) : '';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Ayarlar</h4>
        <p class="text-muted mb-0">
            Aydın Hotel sistem bilgilerini buradan güncelleyebilirsiniz.
        </p>
    </div>

    <a href="index.php" class="btn btn-light border">
        <i class="bi bi-arrow-left"></i>
        Dashboard
    </a>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] === 'updated'): ?>
    <div class="alert alert-success">
        Ayarlar başarıyla güncellendi.
    </div>
<?php endif; ?>

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

        <div class="col-xl-8">
            <div class="panel-card">
                <div class="panel-header">
                    <h5>Otel Bilgileri</h5>
                </div>

                <div class="panel-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Otel Adı</label>
                            <input 
                                type="text" 
                                name="hotel_name" 
                                class="form-control" 
                                required
                                value="<?php echo oldValue('hotel_name', $settings); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Telefon</label>
                            <input 
                                type="text" 
                                name="phone" 
                                class="form-control"
                                placeholder="Örn: 0454 000 00 00"
                                value="<?php echo oldValue('phone', $settings); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">E-posta</label>
                            <input 
                                type="email" 
                                name="email" 
                                class="form-control"
                                placeholder="info@aydinhotel.com"
                                value="<?php echo oldValue('email', $settings); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Vergi No</label>
                            <input 
                                type="text" 
                                name="tax_no" 
                                class="form-control"
                                value="<?php echo oldValue('tax_no', $settings); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Giriş Saati</label>
                            <input 
                                type="time" 
                                name="checkin_time" 
                                class="form-control"
                                value="<?php echo timeValue('checkin_time', $settings); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Çıkış Saati</label>
                            <input 
                                type="time" 
                                name="checkout_time" 
                                class="form-control"
                                value="<?php echo timeValue('checkout_time', $settings); ?>"
                            >
                        </div>

                        <div class="col-12">
                            <label class="form-label">Adres</label>
                            <textarea 
                                name="address" 
                                class="form-control" 
                                rows="4"
                                placeholder="Otel adresini yazınız..."
                            ><?php echo oldValue('address', $settings); ?></textarea>
                        </div>

                    </div>

                    <button type="submit" class="btn btn-primary mt-4">
                        <i class="bi bi-check-circle"></i>
                        Ayarları Kaydet
                    </button>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="panel-card mb-4">
                <div class="panel-header">
                    <h5>Otel Kartı</h5>
                </div>

                <div class="panel-body">
                    <div class="hotel-preview-card">
                        <div class="hotel-preview-logo">
                            A
                        </div>

                        <h4>
                            <?php echo htmlspecialchars($settings['hotel_name']); ?>
                        </h4>

                        <p class="text-muted mb-2">
                            <i class="bi bi-geo-alt"></i>
                            <?php echo !empty($settings['address']) ? htmlspecialchars($settings['address']) : 'Adres eklenmemiş'; ?>
                        </p>

                        <p class="text-muted mb-2">
                            <i class="bi bi-telephone"></i>
                            <?php echo !empty($settings['phone']) ? htmlspecialchars($settings['phone']) : 'Telefon eklenmemiş'; ?>
                        </p>

                        <p class="text-muted mb-0">
                            <i class="bi bi-envelope"></i>
                            <?php echo !empty($settings['email']) ? htmlspecialchars($settings['email']) : 'E-posta eklenmemiş'; ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="panel-card">
                <div class="panel-header">
                    <h5>Konaklama Saatleri</h5>
                </div>

                <div class="panel-body">
                    <div class="setting-time-box mb-3">
                        <div>
                            <small>Giriş Saati</small>
                            <strong><?php echo substr($settings['checkin_time'], 0, 5); ?></strong>
                        </div>

                        <i class="bi bi-box-arrow-in-right"></i>
                    </div>

                    <div class="setting-time-box">
                        <div>
                            <small>Çıkış Saati</small>
                            <strong><?php echo substr($settings['checkout_time'], 0, 5); ?></strong>
                        </div>

                        <i class="bi bi-box-arrow-right"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>
</form>

<?php require_once 'includes/footer.php'; ?>