<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
    Login kontrolü
*/
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);

function activeMenu($pages, $currentPage) {
    if (is_array($pages)) {
        return in_array($currentPage, $pages) ? 'active' : '';
    }

    return $pages === $currentPage ? 'active' : '';
}

/*
    Otel adı
*/
$hotelName = 'Aydın Hotel';

try {
    if (isset($pdo)) {
        $settingsStmt = $pdo->query("
            SELECT hotel_name 
            FROM settings 
            WHERE id = 1
            LIMIT 1
        ");

        $settingsRow = $settingsStmt->fetch();

        if ($settingsRow && !empty($settingsRow['hotel_name'])) {
            $hotelName = $settingsRow['hotel_name'];
        }
    }
} catch (Exception $e) {
    $hotelName = 'Aydın Hotel';
}

/*
    Logo yazıları
*/
$brandMain = preg_replace('/\s+(hotel|otel)$/iu', '', $hotelName);
$brandMain = trim($brandMain);

if ($brandMain === '') {
    $brandMain = $hotelName;
}

$brandMain = mb_strtoupper($brandMain, 'UTF-8');

$brandInitial = mb_substr($hotelName, 0, 1, 'UTF-8');
$brandInitial = mb_strtoupper($brandInitial, 'UTF-8');

/*
    Admin bilgileri
*/
$adminName = $_SESSION['fullname'] ?? 'Admin';
$adminInitial = mb_substr($adminName, 0, 1, 'UTF-8');
$adminInitial = mb_strtoupper($adminInitial, 'UTF-8');
$playWelcomeVoice = false;

if (isset($_SESSION['welcome_voice']) && $_SESSION['welcome_voice'] === true) {
    $playWelcomeVoice = true;
    unset($_SESSION['welcome_voice']);
}
/*
    Bildirimler
*/
$notifications = [];

try {
    if (isset($pdo)) {

        /*
            Temizlikte olan odalar
        */
        $cleaningRoomsNotify = $pdo->query("
            SELECT room_number, room_type
            FROM rooms
            WHERE status = 'Temizlikte'
            ORDER BY CAST(room_number AS UNSIGNED) ASC
            LIMIT 5
        ")->fetchAll();

        foreach ($cleaningRoomsNotify as $room) {
            $notifications[] = [
                'icon' => 'bi-brush',
                'color' => 'warning',
                'title' => 'Oda ' . $room['room_number'] . ' temizlikte',
                'text' => $room['room_type'] . ' temizleniyor.',
                'link' => 'rooms.php?status=Temizlikte'
            ];
        }

        /*
            Bakımda olan odalar
        */
        $maintenanceRoomsNotify = $pdo->query("
            SELECT room_number, room_type
            FROM rooms
            WHERE status = 'Bakımda'
            ORDER BY CAST(room_number AS UNSIGNED) ASC
            LIMIT 5
        ")->fetchAll();

        foreach ($maintenanceRoomsNotify as $room) {
            $notifications[] = [
                'icon' => 'bi-wrench',
                'color' => 'secondary',
                'title' => 'Oda ' . $room['room_number'] . ' bakımda',
                'text' => $room['room_type'] . ' kullanıma kapalı.',
                'link' => 'rooms.php?status=Bakımda'
            ];
        }

        /*
            Bugünkü girişler
        */
        $todayCheckins = $pdo->query("
            SELECT 
                r.id,
                c.first_name,
                c.last_name,
                rm.room_number
            FROM reservations r
            INNER JOIN customers c ON c.id = r.customer_id
            INNER JOIN rooms rm ON rm.id = r.room_id
            WHERE r.check_in = CURDATE()
              AND r.status IN ('Beklemede', 'Onaylandı')
            ORDER BY r.check_in ASC
            LIMIT 5
        ")->fetchAll();

        foreach ($todayCheckins as $reservation) {
            $notifications[] = [
                'icon' => 'bi-box-arrow-in-right',
                'color' => 'primary',
                'title' => 'Bugün giriş var',
                'text' => $reservation['first_name'] . ' ' . $reservation['last_name'] . ' - Oda ' . $reservation['room_number'],
                'link' => 'checkin_checkout.php'
            ];
        }

        /*
            Bugünkü çıkışlar
        */
        $todayCheckouts = $pdo->query("
            SELECT 
                r.id,
                c.first_name,
                c.last_name,
                rm.room_number
            FROM reservations r
            INNER JOIN customers c ON c.id = r.customer_id
            INNER JOIN rooms rm ON rm.id = r.room_id
            WHERE r.check_out = CURDATE()
              AND r.status = 'Aktif'
            ORDER BY r.check_out ASC
            LIMIT 5
        ")->fetchAll();

        foreach ($todayCheckouts as $reservation) {
            $notifications[] = [
                'icon' => 'bi-box-arrow-right',
                'color' => 'danger',
                'title' => 'Bugün çıkış var',
                'text' => $reservation['first_name'] . ' ' . $reservation['last_name'] . ' - Oda ' . $reservation['room_number'],
                'link' => 'checkin_checkout.php'
            ];
        }

        /*
            Bekleyen rezervasyonlar
        */
        $pendingCount = $pdo->query("
            SELECT COUNT(*)
            FROM reservations
            WHERE status = 'Beklemede'
        ")->fetchColumn();

        if ($pendingCount > 0) {
            $notifications[] = [
                'icon' => 'bi-hourglass-split',
                'color' => 'warning',
                'title' => $pendingCount . ' bekleyen rezervasyon var',
                'text' => 'Onay bekleyen rezervasyonları kontrol ediniz.',
                'link' => 'reservations.php?status=Beklemede'
            ];
        }
    }
} catch (Exception $e) {
    $notifications = [];
}

$notificationCount = count($notifications);
?>

<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo htmlspecialchars($hotelName); ?> Rezervasyon Yönetim Sistemi</title>

    <link 
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" 
        rel="stylesheet"
    >

    <link 
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" 
        rel="stylesheet"
    >

    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

<script>
window.playWelcomeVoice = <?php echo $playWelcomeVoice ? 'true' : 'false'; ?>;
</script>
</head>

<body>

<div class="app-layout">

    <aside class="sidebar">

        <div class="brand-box text-center" id="hotelBrandVoice" title="Otelimize hoş geldiniz">
            <div class="brand-logo">
                <?php echo htmlspecialchars($brandInitial); ?>
            </div>
            <h5><?php echo htmlspecialchars($brandMain); ?></h5>
            <small>HOTEL</small>
        </div>

        <nav class="sidebar-menu">

            <a 
                class="<?php echo activeMenu('index.php', $currentPage); ?>" 
                href="index.php"
            >
                <i class="bi bi-house-door-fill"></i>
                Dashboard
            </a>

            <a 
                class="<?php echo activeMenu(['reservations.php', 'reservation_add.php', 'reservation_edit.php'], $currentPage); ?>" 
                href="reservations.php"
            >
                <i class="bi bi-calendar2-check"></i>
                Rezervasyonlar
            </a>

            <a 
                class="<?php echo activeMenu('reservation_add.php', $currentPage); ?>" 
                href="reservation_add.php"
            >
                <i class="bi bi-calendar-plus"></i>
                Rezervasyon Ekle
            </a>

            <a 
                class="<?php echo activeMenu(['customers.php', 'customer_edit.php'], $currentPage); ?>" 
                href="customers.php"
            >
                <i class="bi bi-people"></i>
                Müşteriler
            </a>

            <a 
                class="<?php echo activeMenu(['rooms.php', 'room_add.php', 'room_edit.php'], $currentPage); ?>" 
                href="rooms.php"
            >
                <i class="bi bi-door-open"></i>
                Odalar
            </a>

            <a 
                class="<?php echo activeMenu('checkin_checkout.php', $currentPage); ?>" 
                href="checkin_checkout.php"
            >
                <i class="bi bi-box-arrow-in-right"></i>
                Giriş - Çıkış
            </a>

            <a 
                class="<?php echo activeMenu('reports.php', $currentPage); ?>" 
                href="reports.php"
            >
                <i class="bi bi-bar-chart"></i>
                Raporlar
            </a>

            <a 
                class="<?php echo activeMenu('settings.php', $currentPage); ?>" 
                href="settings.php"
            >
                <i class="bi bi-gear"></i>
                Ayarlar
            </a>

        </nav>

        <div class="sidebar-welcome">
            <i class="bi bi-buildings"></i>

            <div>
                <strong><?php echo htmlspecialchars($hotelName); ?></strong>
                <small>Hoş geldiniz!</small>
            </div>
        </div>

    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <header class="topbar">

            <div class="topbar-left">

                <button 
                    type="button" 
                    class="menu-toggle-btn" 
                    id="sidebarToggle"
                >
                    <i class="bi bi-list"></i>
                </button>

                <div>
                    <h4><?php echo htmlspecialchars($hotelName); ?> Rezervasyon Yönetim Sistemi</h4>
                    <small>Rezervasyon, oda, giriş-çıkış ve gelir takibi</small>
                </div>

            </div>

            <div class="topbar-right">

                <div class="search-box d-none d-lg-flex">
                    <input type="text" placeholder="Arama yapın...">
                    <i class="bi bi-search"></i>
                </div>

                <div class="dropdown notification-dropdown">
                    <button 
                        type="button" 
                        class="notification-box"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >
                        <i class="bi bi-bell"></i>

                        <?php if ($notificationCount > 0): ?>
                            <span><?php echo $notificationCount; ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="dropdown-menu dropdown-menu-end notification-menu">

                        <div class="notification-menu-header">
                            <strong>Bildirimler</strong>
                            <small><?php echo $notificationCount; ?> yeni bildirim</small>
                        </div>

                        <?php if ($notificationCount > 0): ?>

                            <?php foreach ($notifications as $notification): ?>
                                <a 
                                    href="<?php echo htmlspecialchars($notification['link']); ?>" 
                                    class="notification-item"
                                >
                                    <div class="notification-item-icon <?php echo htmlspecialchars($notification['color']); ?>">
                                        <i class="bi <?php echo htmlspecialchars($notification['icon']); ?>"></i>
                                    </div>

                                    <div>
                                        <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                        <small><?php echo htmlspecialchars($notification['text']); ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>

                        <?php else: ?>

                            <div class="notification-empty">
                                <i class="bi bi-check-circle"></i>
                                <p>Yeni bildirim yok.</p>
                            </div>

                        <?php endif; ?>

                    </div>
                </div>

                <div class="dropdown admin-dropdown">
                    <button 
                        type="button" 
                        class="admin-box admin-dropdown-btn"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >
                        <div class="admin-avatar">
                            <?php echo htmlspecialchars($adminInitial); ?>
                        </div>

                        <div>
                            <strong><?php echo htmlspecialchars($adminName); ?></strong>
                            <small>Yönetici</small>
                        </div>

                        <i class="bi bi-chevron-down ms-2"></i>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end admin-menu">

                        <li>
                            <span class="dropdown-item-text">
                                <strong><?php echo htmlspecialchars($adminName); ?></strong><br>
                                <small>Oturum açık</small>
                            </span>
                        </li>

                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <li>
                            <a class="dropdown-item text-danger" href="auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i>
                                Çıkış Yap
                            </a>
                        </li>

                    </ul>
                </div>

            </div>

        </header>