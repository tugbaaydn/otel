<?php
session_start();

require_once '../config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

/*
    Sistemde hiç kullanıcı yoksa otomatik admin oluşturur.
    Kullanıcı adı: admin
    Şifre: 123456
*/
$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

if ($userCount == 0) {
    $defaultPassword = password_hash('123456', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users (username, password, fullname)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        'admin',
        $defaultPassword,
        'Admin'
    ]);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Kullanıcı adı ve şifre boş bırakılamaz.';
    } else {
        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];

            // Girişten sonra bir kere hoş geldiniz sesi çalsın
            $_SESSION['welcome_voice'] = true;

            header('Location: ../index.php');
            exit;
        } else
           {  $error = 'Kullanıcı adı veya şifre hatalı.';
        }
    }
}
?>

<!doctype html>
<html lang="tr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aydın Hotel | Giriş Yap</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #07172d, #123766);
            display: grid;
            place-items: center;
            font-family: Arial, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 430px;
            background: #fff;
            border-radius: 24px;
            padding: 34px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, .25);
        }

        .login-logo {
            width: 72px;
            height: 72px;
            border-radius: 22px;
            display: grid;
            place-items: center;
            margin: 0 auto 16px;
            background: #eef4ff;
            color: #2563eb;
            font-size: 38px;
        }

        .login-title {
            text-align: center;
            margin-bottom: 26px;
        }

        .login-title h3 {
            font-weight: 900;
            margin-bottom: 4px;
        }

        .login-title p {
            color: #64748b;
            margin: 0;
        }

        .form-control {
            height: 48px;
            border-radius: 14px;
        }

        .btn-login {
            height: 50px;
            border-radius: 14px;
            font-weight: 800;
        }

        .default-info {
            background: #f8fafc;
            border: 1px solid #e5eaf2;
            border-radius: 14px;
            padding: 12px;
            color: #475569;
            font-size: 14px;
        }
    </style>
</head>

<body>

    <div class="login-card">

        <div class="login-logo">
            <i class="bi bi-buildings"></i>
        </div>

        <div class="login-title">
            <h3>Aydın Hotel</h3>
            <p>Yönetim paneline giriş yap</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="mb-3">
                <label class="form-label">Kullanıcı Adı</label>
                <input type="text" name="username" class="form-control" placeholder="admin" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Şifre</label>
                <input type="password" name="password" class="form-control" placeholder="Şifrenizi giriniz" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-login">
                <i class="bi bi-box-arrow-in-right"></i>
                Giriş Yap
            </button>

        </form>


    </div>

</body>

</html>