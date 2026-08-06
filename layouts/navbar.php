<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$activeNav = $activeNav ?? 'dashboard';
$sessionNama = htmlspecialchars((string) ($_SESSION['nama'] ?? 'User'), ENT_QUOTES, 'UTF-8');
$sessionRole = $_SESSION['role'] ?? 'karyawan'; // 

$baseUrl = '/pengelolaan_data_karyawan';
$dashboardLink = ($sessionRole === 'admin') ? $baseUrl . '/dashboard/index.php' : $baseUrl . '/karyawan/dashboard_karyawan.php';

// Hitung jumlah tugas yang memiliki catatan revisi khusus untuk karyawan yang sedang login
$badgeRevisiCount = 0;
$nip_active = $_SESSION['nip'] ?? '';
if ($sessionRole === 'karyawan' && !empty($nip_active) && $activeNav !== 'revisi') {
    if (!isset($conn) || $conn === false) {
        @include_once __DIR__ . '/../config/koneksi.php';
    }
    if (isset($conn) && $conn !== false) {
        $qNotif = mysqli_query($conn, "SELECT COUNT(*) as jml FROM task WHERE nip = '$nip_active' AND (catatan_revisi IS NOT NULL AND catatan_revisi != '')");
        if ($qNotif && $rNotif = mysqli_fetch_assoc($qNotif)) {
            $badgeRevisiCount = (int) $rNotif['jml'];
        }
    }
}
?>

<style>
    .nav-link {
        position: relative;
    }
    .notification-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        background-color: #ef4444;
        color: white;
        font-size: 10px;
        font-weight: 800;
        padding: 2px 6px;
        border-radius: 999px;
        line-height: 1;
        box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
        pointer-events: none;
    }
    .btn-logout-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background-color: #ef4444;
        color: #ffffff;
        border-radius: 8px;
        text-decoration: none;
        transition: background-color 0.2s ease, transform 0.1s ease;
        box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
    }
    .btn-logout-icon:hover {
        background-color: #dc2626;
        transform: scale(1.05);
    }
    .btn-logout-icon svg {
        width: 18px;
        height: 18px;
        pointer-events: none;
    }
    .top-nav-right {
        display: flex;
        align-items: center;
        gap: 16px;
    }
</style>

<nav class="top-nav reveal">
    <div class="top-nav-left">
        <a href="<?= $dashboardLink; ?>" class="brand-link">
            <span class="brand-badge">SK</span>
            <span class="brand-text">Sistem Data Karyawan</span>
        </a>
        <div class="nav-links">
            
            <?php if ($sessionRole === 'admin'): ?>
                <!-- MENU KHUSUS ADMIN -->
                <a href="<?= $baseUrl; ?>/dashboard/index.php" class="nav-link <?= $activeNav === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
                <a href="<?= $baseUrl; ?>/karyawan/index.php" class="nav-link <?= $activeNav === 'karyawan' ? 'active' : ''; ?>">Data Karyawan</a>
                <a href="<?= $baseUrl; ?>/task/index.php" class="nav-link <?= $activeNav === 'task' ? 'active' : ''; ?>">Tugas Karyawan</a>
            <?php else: ?>
                <!-- MENU KHUSUS KARYAWAN -->
                <a href="<?= $baseUrl; ?>/karyawan/dashboard_karyawan.php" class="nav-link <?= $activeNav === 'dashboard_karyawan' ? 'active' : ''; ?>">Portal Karyawan</a>
                <!-- Diarahkan ke halaman tugas.php -->
                <a href="<?= $baseUrl; ?>/karyawan/tugas.php" class="nav-link <?= $activeNav === 'tugas_saya' ? 'active' : ''; ?>">Tugas Saya</a>
                <a href="<?= $baseUrl; ?>/karyawan/revisi.php" class="nav-link <?= $activeNav === 'revisi' ? 'active' : ''; ?>">
                    Revisi Tugas
                    <?php if ($badgeRevisiCount > 0): ?>
                        <span class="notification-badge"><?= $badgeRevisiCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= $baseUrl; ?>/karyawan/profile.php" class="nav-link <?= $activeNav === 'profile' ? 'active' : ''; ?>">Profil Saya</a>
            <?php endif; ?> 

        </div>
    </div>

    <div class="top-nav-right">
        <span class="account-name"><?= $sessionNama; ?> (<?= ucfirst($sessionRole); ?>)</span>
        <a class="btn-logout-icon" href="<?= $baseUrl; ?>/auth/logout.php" title="Keluar / Logout">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
        </a>
    </div>
</nav>