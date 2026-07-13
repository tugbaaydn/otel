<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/header.php';

/*
    Filtre değerleri
*/
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$roomTypeFilter = trim($_GET['room_type'] ?? '');
$floorFilter = trim($_GET['floor'] ?? '');

/*
    Oda tiplerini filtre için çekiyoruz
*/
$roomTypes = $pdo->query("
    SELECT DISTINCT room_type
    FROM rooms
    ORDER BY room_type ASC
")->fetchAll(PDO::FETCH_COLUMN);

/*
    Filtre sorgusu
*/
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "
        (
            rm.room_number LIKE ?
            OR rm.room_type LIKE ?
            OR rm.room_description LIKE ?
        )
    ";

    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter !== '') {
    $where[] = "rm.status = ?";
    $params[] = $statusFilter;
}

if ($roomTypeFilter !== '') {
    $where[] = "rm.room_type = ?";
    $params[] = $roomTypeFilter;
}

if ($floorFilter !== '') {
    $where[] = "LEFT(rm.room_number, 1) = ?";
    $params[] = $floorFilter;
}

$whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT 
        rm.*,
        IFNULL(rc.reservation_count, 0) AS reservation_count
    FROM rooms rm
    LEFT JOIN (
        SELECT room_id, COUNT(*) AS reservation_count
        FROM reservations
        GROUP BY room_id
    ) rc ON rc.room_id = rm.id
    $whereSql
    ORDER BY CAST(rm.room_number AS UNSIGNED) ASC
");

$stmt->execute($params);
$rooms = $stmt->fetchAll();

/*
    Yardımcı fonksiyonlar
*/
function moneyFormat($value) {
    return number_format((float)$value, 2, ',', '.') . ' ₺';
}

function roomStatusClass($status) {
    $map = [
        'Boş' => 'room-empty',
        'Dolu' => 'room-full',
        'Temizlikte' => 'room-cleaning',
        'Bakımda' => 'room-maintenance'
    ];

    return $map[$status] ?? 'room-empty';
}

function roomStatusBadge($status) {
    $map = [
        'Boş' => 'success',
        'Dolu' => 'danger',
        'Temizlikte' => 'warning',
        'Bakımda' => 'secondary'
    ];

    return $map[$status] ?? 'success';
}

function defaultRoomImage($roomType) {
    $images = [
        'Standart Oda' => 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=1000&q=80',
        'Deluxe Oda' => 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1000&q=80',
        'Suit Oda' => 'https://images.unsplash.com/photo-1591088398332-8a7791972843?auto=format&fit=crop&w=1000&q=80',
        'Aile Odası' => 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?auto=format&fit=crop&w=1000&q=80'
    ];

    return $images[$roomType] ?? $images['Standart Oda'];
}

function getFloorName($roomNumber) {
    $roomNumber = trim((string)$roomNumber);

    if ($roomNumber === '') {
        return 'Diğer Odalar';
    }

    $firstDigit = substr($roomNumber, 0, 1);

    if (!is_numeric($firstDigit)) {
        return 'Diğer Odalar';
    }

    if ($firstDigit == 0) {
        return 'Zemin Kat';
    }

    return $firstDigit . '. Kat';
}

/*
    Oda özellikleri
*/
function roomFeatures($roomType) {
    $features = [
        'Standart Oda' => [
            ['bi-wifi', 'Ücretsiz Wi-Fi', 'Yüksek hızlı internet'],
            ['bi-snow', 'Klima', 'Merkezi iklimlendirme'],
            ['bi-tv', 'TV', 'Düz ekran televizyon'],
            ['bi-droplet', 'Özel Banyo', 'Duş ve havlu seti']
        ],
        'Deluxe Oda' => [
            ['bi-cup-straw', 'Minibar', 'İçecek ve atıştırmalık seçenekleri'],
            ['bi-wifi', 'Ücretsiz Wi-Fi', 'Yüksek hızlı internet erişimi'],
            ['bi-snow', 'Klima', 'Merkezi iklimlendirme sistemi'],
            ['bi-droplet', 'Özel Banyo', 'Duş, saç kurutma makinesi ve havlular'],
            ['bi-tv', 'TV', 'Uydu yayınlı düz ekran TV'],
            ['bi-safe', 'Kasa', 'Değerli eşyalar için güvenli kasa']
        ],
        'Suit Oda' => [
            ['bi-house-heart', 'Oturma Alanı', 'Geniş ve konforlu oturma alanı'],
            ['bi-cup-straw', 'Minibar', 'Özel minibar'],
            ['bi-wifi', 'Ücretsiz Wi-Fi', 'Yüksek hızlı internet'],
            ['bi-tv', 'TV', 'Büyük ekran televizyon'],
            ['bi-briefcase', 'Çalışma Masası', 'İş seyahatleri için uygun'],
            ['bi-droplet', 'Özel Banyo', 'Konforlu banyo alanı']
        ],
        'Aile Odası' => [
            ['bi-people', 'Aile Kullanımı', 'Geniş yatak düzeni'],
            ['bi-wifi', 'Ücretsiz Wi-Fi', 'Yüksek hızlı internet'],
            ['bi-snow', 'Klima', 'Merkezi iklimlendirme'],
            ['bi-tv', 'TV', 'Uydu yayınlı TV'],
            ['bi-droplet', 'Özel Banyo', 'Duş ve havlu seti']
        ]
    ];

    return $features[$roomType] ?? $features['Standart Oda'];
}

/*
    Odaları katlara göre gruplama
*/
$roomsByFloor = [];

foreach ($rooms as $room) {
    $floorName = getFloorName($room['room_number']);
    $roomsByFloor[$floorName][] = $room;
}

/*
    Sayı kartları
*/
$totalRooms = count($rooms);
$emptyRooms = 0;
$fullRooms = 0;
$cleaningRooms = 0;
$maintenanceRooms = 0;

foreach ($rooms as $room) {
    if ($room['status'] === 'Boş') {
        $emptyRooms++;
    } elseif ($room['status'] === 'Dolu') {
        $fullRooms++;
    } elseif ($room['status'] === 'Temizlikte') {
        $cleaningRooms++;
    } elseif ($room['status'] === 'Bakımda') {
        $maintenanceRooms++;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Odalar</h4>
        <p class="text-muted mb-0">
            Aydın Hotel odalarını katlara göre kart görünümünde takip edebilirsiniz.
        </p>
    </div>

    <a href="room_add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i>
        Yeni Oda Ekle
    </a>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] === 'created'): ?>
    <div class="alert alert-success">Oda başarıyla eklendi.</div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === 'updated'): ?>
    <div class="alert alert-success">Oda başarıyla güncellendi.</div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
    <div class="alert alert-success">Oda başarıyla silindi.</div>
<?php endif; ?>

<div class="row g-4 mb-4">

    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon primary">
                    <i class="bi bi-door-open"></i>
                </div>
                <div>
                    <p>Toplam Oda</p>
                    <h3 class="primary"><?php echo $totalRooms; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon success">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <p>Boş Oda</p>
                    <h3 class="success"><?php echo $emptyRooms; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon orange">
                    <i class="bi bi-person-fill-lock"></i>
                </div>
                <div>
                    <p>Dolu Oda</p>
                    <h3 class="orange"><?php echo $fullRooms; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon purple">
                    <i class="bi bi-stars"></i>
                </div>
                <div>
                    <p>Temizlikte</p>
                    <h3 class="purple"><?php echo $cleaningRooms; ?></h3>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="panel-card room-filter-card mb-4">
    <div class="panel-body">
        <form method="GET" class="row g-3 align-items-end">

            <div class="col-xl-3 col-md-6">
                <label class="form-label">Arama</label>
                <input 
                    type="text" 
                    name="search" 
                    class="form-control"
                    placeholder="Oda no, oda tipi veya açıklama..."
                    value="<?php echo htmlspecialchars($search); ?>"
                >
            </div>

            <div class="col-xl-2 col-md-6">
                <label class="form-label">Kat</label>
                <select name="floor" class="form-select">
                    <option value="">Tüm Katlar</option>
                    <option value="0" <?php echo $floorFilter === '0' ? 'selected' : ''; ?>>Zemin Kat</option>
                    <option value="1" <?php echo $floorFilter === '1' ? 'selected' : ''; ?>>1. Kat</option>
                    <option value="2" <?php echo $floorFilter === '2' ? 'selected' : ''; ?>>2. Kat</option>
                    <option value="3" <?php echo $floorFilter === '3' ? 'selected' : ''; ?>>3. Kat</option>
                    <option value="4" <?php echo $floorFilter === '4' ? 'selected' : ''; ?>>4. Kat</option>
                    <option value="5" <?php echo $floorFilter === '5' ? 'selected' : ''; ?>>5. Kat</option>
                </select>
            </div>

            <div class="col-xl-2 col-md-6">
                <label class="form-label">Durum</label>
                <select name="status" class="form-select">
                    <option value="">Tüm Durumlar</option>
                    <option value="Boş" <?php echo $statusFilter === 'Boş' ? 'selected' : ''; ?>>Boş</option>
                    <option value="Dolu" <?php echo $statusFilter === 'Dolu' ? 'selected' : ''; ?>>Dolu</option>
                    <option value="Temizlikte" <?php echo $statusFilter === 'Temizlikte' ? 'selected' : ''; ?>>Temizlikte</option>
                    <option value="Bakımda" <?php echo $statusFilter === 'Bakımda' ? 'selected' : ''; ?>>Bakımda</option>
                </select>
            </div>

            <div class="col-xl-2 col-md-6">
                <label class="form-label">Oda Tipi</label>
                <select name="room_type" class="form-select">
                    <option value="">Tüm Oda Tipleri</option>

                    <?php foreach ($roomTypes as $type): ?>
                        <option 
                            value="<?php echo htmlspecialchars($type); ?>"
                            <?php echo $roomTypeFilter === $type ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-xl-3 col-md-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-filter"></i>
                    Filtrele
                </button>

                <a href="rooms.php" class="btn btn-light border w-100">
                    Temizle
                </a>
            </div>

        </form>
    </div>
</div>

<div class="room-legend mb-4">
    <span><i class="legend-dot empty"></i> Boş</span>
    <span><i class="legend-dot full"></i> Dolu</span>
    <span><i class="legend-dot cleaning"></i> Temizlikte</span>
    <span><i class="legend-dot maintenance"></i> Bakımda</span>
</div>

<?php if (count($roomsByFloor) > 0): ?>

    <?php $floorIndex = 0; ?>

    <?php foreach ($roomsByFloor as $floorName => $floorRooms): ?>
        <?php 
            $floorIndex++;
            $floorCollapseId = 'floorCollapse' . $floorIndex;
        ?>

        <div class="floor-section mb-4">
            <button 
                type="button" 
                class="floor-title floor-toggle-btn"
                data-bs-toggle="collapse"
                data-bs-target="#<?php echo $floorCollapseId; ?>"
                aria-expanded="true"
            >
                <div>
                    <h5>
                        <i class="bi bi-building me-1"></i>
                        <?php echo htmlspecialchars($floorName); ?>
                    </h5>
                    <small><?php echo count($floorRooms); ?> oda listeleniyor</small>
                </div>

                <div class="floor-right">
                    <span><?php echo count($floorRooms); ?> oda</span>
                    <i class="bi bi-chevron-down"></i>
                </div>
            </button>

            <div class="collapse show" id="<?php echo $floorCollapseId; ?>">
                <div class="row g-3 mt-2">

                    <?php foreach ($floorRooms as $room): ?>
                        <?php
                            $description = $room['room_description'] ?? 'Açıklama eklenmemiş.';

                            $roomImage = !empty($room['room_image'])
                                ? $room['room_image']
                                : defaultRoomImage($room['room_type']);

                            $statusClass = roomStatusClass($room['status']);
                            $badgeClass = roomStatusBadge($room['status']);
                        ?>

                        <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-4 col-sm-6">
                            <div 
                                class="room-card <?php echo $statusClass; ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#roomModal"
                                data-room-number="<?php echo htmlspecialchars($room['room_number']); ?>"
                                data-room-type="<?php echo htmlspecialchars($room['room_type']); ?>"
                                data-room-status="<?php echo htmlspecialchars($room['status']); ?>"
                                data-room-price="<?php echo htmlspecialchars(moneyFormat($room['price_per_day'])); ?>"
                                data-room-description="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>"
                                data-room-image="<?php echo htmlspecialchars($roomImage, ENT_QUOTES, 'UTF-8'); ?>"
                                data-room-reservation="<?php echo (int)$room['reservation_count']; ?>"
                            >
                                <div class="room-card-top">
                                    <span class="room-number">
                                        <?php echo htmlspecialchars($room['room_number']); ?>
                                    </span>

                                    <span class="room-mini-icon bg-<?php echo $badgeClass; ?>">
                                        <?php if ($room['status'] === 'Boş'): ?>
                                            <i class="bi bi-door-open"></i>
                                        <?php elseif ($room['status'] === 'Dolu'): ?>
                                            <i class="bi bi-person-fill-lock"></i>
                                        <?php elseif ($room['status'] === 'Temizlikte'): ?>
                                            <i class="bi bi-brush"></i>
                                        <?php else: ?>
                                            <i class="bi bi-wrench"></i>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="room-card-image">
                                    <img 
                                        src="<?php echo htmlspecialchars($roomImage); ?>" 
                                        alt="<?php echo htmlspecialchars($room['room_type']); ?>"
                                    >
                                </div>

                                <div class="room-type">
                                    <?php echo htmlspecialchars($room['room_type']); ?>
                                </div>

                                <div class="room-price">
                                    <?php echo moneyFormat($room['price_per_day']); ?>
                                    <small>/ gece</small>
                                </div>

                                <span class="room-status-badge bg-<?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars($room['status']); ?>
                                </span>

                                <div class="room-card-footer">
                                    <small>
                                        <i class="bi bi-calendar2-check"></i>
                                        <?php echo (int)$room['reservation_count']; ?> Rezervasyon
                                    </small>
                                </div>
                            </div>

                            <div class="room-actions mt-2">
                                <a 
                                    href="room_edit.php?id=<?php echo $room['id']; ?>" 
                                    class="btn btn-sm btn-light border w-50"
                                >
                                    <i class="bi bi-pencil"></i>
                                    Düzenle
                                </a>

                                <a 
                                    href="room_delete.php?id=<?php echo $room['id']; ?>" 
                                    class="btn btn-sm btn-danger w-50"
                                    onclick="return confirm('Bu odayı silmek istediğine emin misin?');"
                                >
                                    <i class="bi bi-trash"></i>
                                    Sil
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
            </div>
        </div>

    <?php endforeach; ?>

<?php else: ?>

    <div class="panel-card">
        <div class="panel-body text-center text-muted py-5">
            <i class="bi bi-door-closed fs-1 d-block mb-2"></i>
            Seçilen filtrelere uygun oda bulunamadı.
        </div>
    </div>

<?php endif; ?>

<div class="modal fade" id="roomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content room-modal-content">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title">
                        Oda <span id="modalRoomNumber"></span>
                        -
                        <span id="modalRoomTypeTitle"></span>
                        <span id="modalStatusPill" class="badge ms-2"></span>
                    </h5>
                    <small class="text-muted">Aydın Hotel oda detayları</small>
                </div>

                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0">
                <ul class="nav nav-tabs room-tabs px-4 pt-3" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#roomGeneral" type="button">
                            Genel Bilgiler
                        </button>
                    </li>

                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#roomFeatures" type="button">
                            Özellikler
                        </button>
                    </li>

                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#roomPricing" type="button">
                            Fiyatlandırma
                        </button>
                    </li>

                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#roomImageTab" type="button">
                            Oda Görseli
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-4">

                    <div class="tab-pane fade show active" id="roomGeneral">
                        <div id="modalStatusAlert" class="alert mb-4">
                            <strong>Oda Durumu:</strong>
                            <span id="modalRoomStatus"></span>
                        </div>

                        <div class="room-description-modal">
                            <small>Oda Açıklaması</small>
                            <p id="modalRoomDescription"></p>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="roomFeatures">
                        <div class="row g-3" id="modalFeatureList"></div>
                    </div>

                    <div class="tab-pane fade" id="roomPricing">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="room-detail-box">
                                    <div>
                                        <small>Gecelik Fiyat</small>
                                        <strong id="modalRoomPrice"></strong>
                                    </div>
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="room-detail-box">
                                    <div>
                                        <small>Rezervasyon Sayısı</small>
                                        <strong id="modalReservationCount"></strong>
                                    </div>
                                    <i class="bi bi-calendar2-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="roomImageTab">
                        <h6 class="fw-bold mb-3">Oda Görseli</h6>

                        <img 
                            src="" 
                            id="modalRoomImage" 
                            class="room-modal-image" 
                            alt="Oda Görseli"
                        >
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-primary" data-bs-dismiss="modal">
                    Kapat
                </button>
            </div>

        </div>
    </div>
</div>

<script>
const roomModal = document.getElementById('roomModal');

const featureData = {
    'Standart Oda': [
        ['bi-wifi', 'Ücretsiz Wi-Fi', 'Yüksek hızlı internet'],
        ['bi-snow', 'Klima', 'Merkezi iklimlendirme'],
        ['bi-tv', 'TV', 'Düz ekran televizyon'],
        ['bi-droplet', 'Özel Banyo', 'Duş ve havlu seti']
    ],
    'Deluxe Oda': [
        ['bi-cup-straw', 'Minibar', 'İçecek ve atıştırmalık seçenekleri'],
        ['bi-wifi', 'Ücretsiz Wi-Fi', 'Yüksek hızlı internet erişimi'],
        ['bi-snow', 'Klima', 'Merkezi iklimlendirme sistemi'],
        ['bi-droplet', 'Özel Banyo', 'Duş, saç kurutma makinesi ve havlular'],
        ['bi-tv', 'TV', 'Uydu yayınlı düz ekran TV'],
        ['bi-safe', 'Kasa', 'Değerli eşyalar için güvenli kasa']
    ],
    'Suit Oda': [
        ['bi-house-heart', 'Oturma Alanı', 'Geniş ve konforlu oturma alanı'],
        ['bi-cup-straw', 'Minibar', 'Özel minibar'],
        ['bi-wifi', 'Ücretsiz Wi-Fi', 'Yüksek hızlı internet'],
        ['bi-tv', 'TV', 'Büyük ekran televizyon'],
        ['bi-briefcase', 'Çalışma Masası', 'İş seyahatleri için uygun'],
        ['bi-droplet', 'Özel Banyo', 'Konforlu banyo alanı']
    ],
    'Aile Odası': [
        ['bi-people', 'Aile Kullanımı', 'Geniş yatak düzeni'],
        ['bi-wifi', 'Ücretsiz Wi-Fi', 'Yüksek hızlı internet'],
        ['bi-snow', 'Klima', 'Merkezi iklimlendirme'],
        ['bi-tv', 'TV', 'Uydu yayınlı TV'],
        ['bi-droplet', 'Özel Banyo', 'Duş ve havlu seti']
    ]
};

if (roomModal) {
    roomModal.addEventListener('show.bs.modal', function (event) {
        const card = event.relatedTarget;

        const roomNumber = card.getAttribute('data-room-number');
        const roomType = card.getAttribute('data-room-type');
        const roomStatus = card.getAttribute('data-room-status');
        const roomPrice = card.getAttribute('data-room-price');
        const roomDescription = card.getAttribute('data-room-description');
        const roomImage = card.getAttribute('data-room-image');
        const reservationCount = card.getAttribute('data-room-reservation');

        document.getElementById('modalRoomNumber').textContent = roomNumber;
        document.getElementById('modalRoomTypeTitle').textContent = roomType;
        document.getElementById('modalRoomStatus').textContent = roomStatus;
        document.getElementById('modalRoomPrice').textContent = roomPrice;
        document.getElementById('modalReservationCount').textContent = reservationCount + ' rezervasyon';
        document.getElementById('modalRoomDescription').textContent = roomDescription;
        document.getElementById('modalRoomImage').src = roomImage;

        const statusAlert = document.getElementById('modalStatusAlert');
        const statusPill = document.getElementById('modalStatusPill');

        statusAlert.className = 'alert mb-4';
        statusPill.className = 'badge ms-2';

        if (roomStatus === 'Boş') {
            statusAlert.classList.add('alert-success');
            statusPill.classList.add('bg-success');
        } else if (roomStatus === 'Dolu') {
            statusAlert.classList.add('alert-danger');
            statusPill.classList.add('bg-danger');
        } else if (roomStatus === 'Temizlikte') {
            statusAlert.classList.add('alert-warning');
            statusPill.classList.add('bg-warning');
        } else {
            statusAlert.classList.add('alert-secondary');
            statusPill.classList.add('bg-secondary');
        }

        statusPill.textContent = roomStatus;

        const features = featureData[roomType] || featureData['Standart Oda'];
        const featureList = document.getElementById('modalFeatureList');

        featureList.innerHTML = '';

        features.forEach(function (feature) {
            featureList.innerHTML += `
                <div class="col-md-6">
                    <div class="room-feature-item">
                        <div class="room-feature-icon">
                            <i class="bi ${feature[0]}"></i>
                        </div>

                        <div>
                            <strong>${feature[1]}</strong>
                            <small>${feature[2]}</small>
                        </div>
                    </div>
                </div>
            `;
        });
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>