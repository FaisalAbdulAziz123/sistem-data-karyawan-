<?php
session_start();

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/koneksi.php";

if (!isset($conn) || $conn === false) {
    die("Koneksi database gagal.");
}

mysqli_set_charset($conn, 'utf8mb4');

$activeNav = 'dashboard';
$sessionNama = htmlspecialchars((string) ($_SESSION['nama'] ?? 'Admin'), ENT_QUOTES, 'UTF-8');

// --- AMBIL STATISTIK KARYAWAN SECARA AKURAT ---
// 1. Total Karyawan
$qTotal = mysqli_query($conn, "SELECT COUNT(*) AS total FROM karyawan");
$totalKaryawan = (int) (mysqli_fetch_assoc($qTotal)['total'] ?? 0);

// 2. Karyawan Aktif
$qAktif = mysqli_query($conn, "SELECT COUNT(*) AS total FROM karyawan WHERE status = 'Aktif'");
$totalAktif = (int) (mysqli_fetch_assoc($qAktif)['total'] ?? 0);

// 3. Karyawan Nonaktif
$qNonaktif = mysqli_query($conn, "SELECT COUNT(*) AS total FROM karyawan WHERE status = 'Nonaktif'");
$totalNonaktif = (int) (mysqli_fetch_assoc($qNonaktif)['total'] ?? 0);

// 4. Karyawan Cuti
$qCuti = mysqli_query($conn, "SELECT COUNT(*) AS total FROM karyawan WHERE status = 'Cuti'");
$totalCuti = (int) (mysqli_fetch_assoc($qCuti)['total'] ?? 0);

// --- AMBIL DATA SEMUA KARYAWAN UNTUK DITAMPILKAN DI DASHBOARD ---
$karyawanList = [];
$resAllKaryawan = mysqli_query($conn, "SELECT nip, nama_karyawan, email, jabatan, departemen, status, foto FROM karyawan ORDER BY nama_karyawan ASC");
if ($resAllKaryawan) {
    while ($row = mysqli_fetch_assoc($resAllKaryawan)) {
        $karyawanList[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Data Karyawan</title>
    <link rel="stylesheet" href="../assets/css/dasboard.css">
    <style>
        /* Penyesuaian grid statistik menjadi 4 kolom agar pas */
        .stats-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }
        @media (max-width: 1024px) {
            .stats-grid-4 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 600px) {
            .stats-grid-4 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="dashboard-page">

<div class="dashboard-shell">
    <?php require_once "../layouts/navbar.php"; ?>

    <main class="dashboard-main">
        
        <!-- HEADER / HERO CARD -->
        <section class="hero-card reveal" style="--delay:.05s">
            <div>
                <p class="eyebrow">PANEL UTAMA ADMINISTRATOR</p>
                <h2>Selamat Datang, <?= $sessionNama; ?></h2>
                <p>Sistem Pengelolaan Data Karyawan siap membantu Anda memantau kinerja pegawai, mendistribusikan penugasan, serta mengelola sistem secara efisien.</p>
            </div>
            <div class="hero-actions">
                <a href="../karyawan/index.php" class="btn btn-primary" style="text-decoration: none;">Kelola Data Karyawan</a>
                <a href="../task/index.php" class="btn btn-secondary" style="text-decoration: none;">Kelola Tugas</a>
            </div>
        </section>

        <!-- STATISTIK UTAMA SISTEM (4 KOTAK) -->
        <section class="stats-grid-4">
            <div class="stat-card reveal" style="--delay:.1s">
                <h3>Total Karyawan</h3>
                <p class="stat-number" data-count="<?= $totalKaryawan; ?>">0</p>
                <small class="text-muted">Keseluruhan Pegawai</small>
            </div>
            <div class="stat-card success reveal" style="--delay:.14s">
                <h3>Karyawan Aktif</h3>
                <p class="stat-number" data-count="<?= $totalAktif; ?>">0</p>
                <small class="text-muted">Pegawai Bertugas</small>
            </div>
            <div class="stat-card warning reveal" style="--delay:.18s">
                <h3>Karyawan Cuti</h3>
                <p class="stat-number" data-count="<?= $totalCuti; ?>">0</p>
                <small class="text-muted">Sedang Mengambil Cuti</small>
            </div>
            <div class="stat-card danger reveal" style="--delay:.22s">
                <h3>Karyawan Nonaktif</h3>
                <p class="stat-number" data-count="<?= $totalNonaktif; ?>">0</p>
                <small class="text-muted">Status Nonaktif</small>
            </div>
        </section>

        <!-- TABEL DIREKTORI SEMUA KARYAWAN DI DASHBOARD -->
        <section class="table-section card reveal" style="--delay:.28s">
            <div class="card-header flex-between table-header">
                <div>
                    <h2>Direktori Seluruh Karyawan</h2>
                    <p class="text-muted">Daftar lengkap pegawai yang terdaftar dalam sistem perusahaan.</p>
                </div>
                <span class="table-count"><?= count($karyawanList); ?> Terdaftar</span>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>NIP</th>
                            <th>Nama Karyawan</th>
                            <th>Email</th>
                            <th>Jabatan</th>
                            <th>Departemen</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($karyawanList)): ?>
                            <?php foreach ($karyawanList as $i => $k): ?>
                                <tr class="row-in" style="--row-delay:<?= min($i, 12) * 0.035; ?>s">
                                    <td data-label="Foto">
                                        <?php if (!empty($k['foto'])): ?>
                                            <img src="../assets/uploads/karyawan/<?= htmlspecialchars($k['foto']); ?>" alt="Foto" class="avatar-photo" style="width: 35px; height: 35px;">
                                        <?php else: ?>
                                            <span class="avatar-fallback" style="width: 35px; height: 35px; font-size: 10px;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="NIP"><?= htmlspecialchars($k['nip']); ?></td>
                                    <td data-label="Nama Karyawan"><strong><?= htmlspecialchars($k['nama_karyawan']); ?></strong></td>
                                    <td data-label="Email"><?= htmlspecialchars($k['email']); ?></td>
                                    <td data-label="Jabatan"><?= htmlspecialchars($k['jabatan']); ?></td>
                                    <td data-label="Departemen"><small class="text-muted"><?= htmlspecialchars($k['departemen']); ?></small></td>
                                    <td data-label="Status">
                                        <?php $statusClass = strtolower($k['status']); ?>
                                        <span class="badge badge-<?= htmlspecialchars($statusClass); ?>"><?= htmlspecialchars($k['status']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center empty-state">Belum ada data karyawan yang terdaftar.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <!-- FOOTER LISENSI -->
    <?php require_once "../layouts/footer.php"; ?>
</div>

<script>
    // Animasi Angka Statistik
    document.querySelectorAll('.stat-number[data-count]').forEach((el) => {
        const target = parseInt(el.dataset.count, 10) || 0;
        const duration = 700;
        const start = performance.now();

        function tick(now) {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(target * eased).toLocaleString('id-ID');
            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        }
        requestAnimationFrame(tick);
    });
</script>

</body>
</html>