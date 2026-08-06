<?php
session_start();

// Cek apakah pengguna sudah login
if (!isset($_SESSION['login']) || !isset($_SESSION['nip'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/koneksi.php";

if (!isset($conn) || $conn === false) {
    die("Koneksi database gagal.");
}

mysqli_set_charset($conn, 'utf8mb4');

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$nip_login = $_SESSION['nip'];
// Aktifkan menu dashboard_karyawan agar navbar tetap menyorot menu yang benar
$activeNav = 'dashboard_karyawan'; 

// --- AMBIL DATA PROFIL KARYAWAN ---
$stmt = mysqli_prepare($conn, "SELECT * FROM karyawan WHERE nip = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $nip_login);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profil = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$profil) {
    die("Data profil tidak ditemukan.");
}

// Format Tanggal Masuk ke Bahasa Indonesia
$tanggal_masuk_indo = '-';
if (!empty($profil['tanggal_masuk'])) {
    $bulan_indo = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $timestamp = strtotime($profil['tanggal_masuk']);
    $tanggal_masuk_indo = date('d', $timestamp) . ' ' . $bulan_indo[(int)date('m', $timestamp) - 1] . ' ' . date('Y', $timestamp);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Sistem Data Karyawan</title>
    <link rel="stylesheet" href="../assets/css/dasboard.css">
    <style>
        .portal-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 10px;
        }

        /* Header Profil */
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .profile-title-area h1 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #111827;
            margin: 0 0 6px 0;
            letter-spacing: -0.5px;
        }
        .profile-title-area p {
            margin: 0;
            color: #6b7280;
            font-size: 0.95rem;
        }
        .btn-back {
            background-color: #ffffff;
            color: #4b5563;
            border: 1px solid #d1d5db;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-back:hover {
            background-color: #f3f4f6;
            color: #111827;
        }

        /* Layout Grid Profil */
        .profile-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 24px;
            align-items: start;
        }

        /* Kartu Kiri (Foto & Ringkasan) */
        .card-left {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 32px 24px;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .avatar-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 4px 14px rgba(0,0,0,0.1);
            margin: 0 auto 20px;
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #9ca3af;
            font-weight: bold;
        }
        .user-name {
            font-size: 1.4rem;
            font-weight: 800;
            color: #111827;
            margin: 0 0 4px 0;
        }
        .user-role {
            font-size: 1rem;
            color: #4f46e5;
            font-weight: 600;
            margin: 0 0 16px 0;
        }
        
        .badge-status {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .badge-aktif { background-color: rgba(16, 185, 129, 0.15); color: #047857; }
        .badge-cuti { background-color: rgba(245, 158, 11, 0.15); color: #b45309; }
        .badge-nonaktif { background-color: rgba(239, 68, 68, 0.15); color: #b91c1c; }

        /* Kartu Kanan (Detail Informasi) */
        .card-right {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #111827;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px 20px;
        }
        .detail-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .detail-group.full {
            grid-column: 1 / -1;
        }
        .label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .value {
            font-size: 1rem;
            color: #111827;
            font-weight: 500;
            background: #f9fafb;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            word-break: break-word;
            white-space: pre-wrap;
        }

        @media (max-width: 768px) {
            .profile-layout { grid-template-columns: 1fr; }
            .detail-grid { grid-template-columns: 1fr; }
            .profile-header { flex-direction: column; align-items: flex-start; gap: 16px; }
        }
    </style>
</head>
<body class="dashboard-page">

<div class="dashboard-shell">
    <?php require_once "../layouts/navbar.php"; ?>

    <main class="dashboard-main portal-container reveal" style="--delay:.05s">
        
        <!-- Header -->
        <div class="profile-header">
            <div class="profile-title-area">
                <h1>Profil Saya</h1>
                <p>Informasi detail akun dan data diri Anda yang terdaftar di sistem.</p>
            </div>
            <div>
                <!-- Tombol Kembali ke Dashboard -->
                <a href="dashboard_karyawan.php" class="btn-back">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:18px;height:18px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Kembali ke Dashboard
                </a>
            </div>
        </div>

        <!-- Layout Profil -->
        <div class="profile-layout">
            
            <!-- Kolom Kiri: Foto & Ringkasan -->
            <div class="card-left">
                <?php if (!empty($profil['foto'])): ?>
                    <img src="../assets/uploads/karyawan/<?= e($profil['foto']); ?>" alt="Foto Profil" class="avatar-large">
                <?php else: ?>
                    <div class="avatar-large">N/A</div>
                <?php endif; ?>
                
                <h2 class="user-name"><?= e($profil['nama_karyawan']); ?></h2>
                <p class="user-role"><?= e($profil['jabatan']); ?></p>
                
                <?php $statusClass = str_replace(' ', '', strtolower($profil['status'])); ?>
                <div class="badge-status badge-<?= e($statusClass) ?>">
                    <?= e($profil['status']); ?>
                </div>
            </div>

            <!-- Kolom Kanan: Detail Informasi Lengkap -->
            <div class="card-right">
                <h3 class="section-title">Informasi Pribadi & Pekerjaan</h3>
                
                <div class="detail-grid">
                    <div class="detail-group">
                        <span class="label">Nomor Induk Karyawan (NIP)</span>
                        <div class="value" style="font-weight: 700; color: #4f46e5;"><?= e($profil['nip']); ?></div>
                    </div>
                    
                    <div class="detail-group">
                        <span class="label">Departemen</span>
                        <div class="value"><?= e($profil['departemen']); ?></div>
                    </div>

                    <div class="detail-group">
                        <span class="label">Alamat Email</span>
                        <div class="value"><?= e($profil['email']); ?></div>
                    </div>

                    <div class="detail-group">
                        <span class="label">Nomor Telepon</span>
                        <div class="value"><?= !empty($profil['no_telp']) ? e($profil['no_telp']) : '<em style="color:#9ca3af;">Belum diisi</em>'; ?></div>
                    </div>

                    <div class="detail-group full">
                        <span class="label">Tanggal Bergabung</span>
                        <div class="value"><?= $tanggal_masuk_indo; ?></div>
                    </div>

                    <div class="detail-group full">
                        <span class="label">Alamat Lengkap</span>
                        <div class="value"><?= !empty($profil['alamat']) ? e($profil['alamat']) : '<em style="color:#9ca3af;">Belum diisi</em>'; ?></div>
                    </div>
                </div>
            </div>

        </div>

    </main>
</div>
    <?php require_once "../layouts/footer.php"; ?>

</body>
</html>