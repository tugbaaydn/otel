<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: customers.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM customers
    WHERE id = ?
");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    echo '<div class="alert alert-danger">Müşteri bulunamadı.</div>';
    require_once 'includes/footer.php';
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tcNo = trim($_POST['tc_no'] ?? '');

    if ($firstName === '') {
        $errors[] = 'Müşteri adı boş bırakılamaz.';
    }

    if ($lastName === '') {
        $errors[] = 'Müşteri soyadı boş bırakılamaz.';
    }

    if ($phone === '') {
        $errors[] = 'Telefon numarası boş bırakılamaz.';
    }

    if (empty($errors)) {
        $updateStmt = $pdo->prepare("
            UPDATE customers
            SET 
                first_name = ?,
                last_name = ?,
                phone = ?,
                email = ?,
                tc_no = ?
            WHERE id = ?
        ");

        $updateStmt->execute([
            $firstName,
            $lastName,
            $phone,
            $email,
            $tcNo,
            $id
        ]);

        header('Location: customers.php?success=updated');
        exit;
    }
}

function oldValue($key, $customer) {
    return htmlspecialchars($_POST[$key] ?? $customer[$key] ?? '');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Müşteri Düzenle</h4>
        <p class="text-muted mb-0">
            Müşteri iletişim ve kimlik bilgilerini güncelleyebilirsiniz.
        </p>
    </div>

    <a href="customers.php" class="btn btn-light border">
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
                                value="<?php echo oldValue('first_name', $customer); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Soyad</label>
                            <input 
                                type="text" 
                                name="last_name" 
                                class="form-control" 
                                required
                                value="<?php echo oldValue('last_name', $customer); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Telefon</label>
                            <input 
                                type="text" 
                                name="phone" 
                                class="form-control" 
                                required
                                value="<?php echo oldValue('phone', $customer); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">E-posta</label>
                            <input 
                                type="email" 
                                name="email" 
                                class="form-control"
                                value="<?php echo oldValue('email', $customer); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">TC Kimlik No</label>
                            <input 
                                type="text" 
                                name="tc_no" 
                                class="form-control"
                                value="<?php echo oldValue('tc_no', $customer); ?>"
                            >
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-4">
                        <i class="bi bi-check-circle"></i>
                        Müşteriyi Güncelle
                    </button>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="panel-card">
                <div class="panel-header">
                    <h5>Bilgilendirme</h5>
                </div>

                <div class="panel-body">
                    <div class="alert alert-info mb-0">
                        Bu sayfada yapılan değişiklikler, müşterinin eski rezervasyonlarında da güncel olarak görünür.
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once 'includes/footer.php'; ?>