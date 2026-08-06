<?php
session_start();

// Cek apakah pengguna sudah login sebagai karyawan
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
$activeNav = 'revisi';

// --- AMBIL DATA TUGAS YANG MEMILIKI CATATAN REVISI ATAU FILE REVISI ---
$tasksRevisi = [];
$stmt_task = mysqli_prepare($conn, "SELECT id, judul, deskripsi, status, file_tugas, keterangan_karyawan, catatan_revisi, file_revisi, DATE_FORMAT(created_at, '%d %b %Y') as tanggal_dibuat FROM task WHERE nip = ? AND (catatan_revisi IS NOT NULL AND catatan_revisi != '') ORDER BY updated_at DESC");
mysqli_stmt_bind_param($stmt_task, "s", $nip_login);
mysqli_stmt_execute($stmt_task);
$result_task = mysqli_stmt_get_result($stmt_task);

while ($row = mysqli_fetch_assoc($result_task)) {
    $tasksRevisi[] = $row;
}
mysqli_stmt_close($stmt_task);
$totalRevisi = count($tasksRevisi);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Revisi Tugas</title>
    <link rel="stylesheet" href="../assets/css/dasboard.css">
    <style>
        /* Lebar disamakan penuh dengan navbar di atasnya (menghapus max-width fix) */
        .portal-container { width: 100%; margin: 0 auto; padding: 0; box-sizing: border-box; }
        
        .revisi-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }
        .revisi-card:hover {
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
            border-color: #d1d5db;
        }
        .revisi-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            border-bottom: 1px dashed #f3f4f6;
            padding-bottom: 12px;
        }
        .revisi-title { font-size: 1.15rem; font-weight: 800; color: #111827; margin-bottom: 4px; }
        .revisi-date { font-size: 0.8rem; color: #6b7280; }
        
        .revisi-alert-box {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
            padding: 16px;
            border-radius: 4px 8px 8px 4px;
            margin-bottom: 16px;
        }
        .revisi-alert-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #b45309;
            text-transform: uppercase;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .revisi-alert-content {
            font-size: 0.95rem;
            color: #92400e;
            line-height: 1.5;
            white-space: pre-wrap;
            margin-bottom: 12px;
        }
        .revisi-file-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #92400e;
            background: #fef3c7;
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            border: 1px solid #fcd34d;
            transition: background 0.2s;
        }
        .revisi-file-btn:hover { background: #fde68a; }

        .badge-proses { background-color: rgba(245, 158, 11, 0.14) !important; color: #b45309 !important; }
        .badge-selesai { background-color: rgba(16, 185, 129, 0.12) !important; color: #047857 !important; }
        
        .btn-action-portal {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #4f46e5;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: background 0.2s;
        }
        .btn-action-portal:hover { background: #4338ca; }
    </style>
</head>
<body class="dashboard-page">

<div class="dashboard-shell">
    <?php require_once "../layouts/navbar.php"; ?>

    <main class="dashboard-main portal-container reveal" style="--delay:.05s">
        
        <!-- HEADER HALAMAN -->
        <section class="hero-card">
            <div>
                <p class="eyebrow">PUSAT REVISI TUGAS</p>
                <h2>Daftar Tugas Revisi</h2>
                <p>Berikut adalah daftar tugas yang memerlukan perbaikan atau catatan khusus dari admin.</p>
            </div>
        </section>

        <!-- KONTEN DAFTAR REVISI -->
        <div class="reveal" style="--delay:.15s">
            <?php if (!empty($tasksRevisi)): ?>
                <?php foreach ($tasksRevisi as $t): ?>
                    <div class="revisi-card">
                        <div class="revisi-header">
                            <div>
                                <div class="revisi-title"><?= e($t['judul']); ?></div>
                                <div class="revisi-date">Diberikan pada: <?= e($t['tanggal_dibuat']); ?></div>
                            </div>
                            <?php $badgeClass = str_replace(' ', '', strtolower($t['status'])); ?>
                            <span class="badge badge-<?= e($badgeClass); ?>" style="font-size: 0.85rem; padding: 6px 14px;"><?= e($t['status']); ?></span>
                        </div>
                        
                        <!-- KOTAK CATATAN & BERKAS REVISI DARI ADMIN -->
                        <div class="revisi-alert-box">
                            <div class="revisi-alert-title">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                Catatan Revisi dari Admin:
                            </div>
                            <?php if (!empty($t['catatan_revisi'])): ?>
                                <div class="revisi-alert-content"><?= nl2br(e($t['catatan_revisi'])); ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($t['file_revisi'])): ?>
                                <a href="../assets/uploads/revisi/<?= e($t['file_revisi']); ?>" target="_blank" class="revisi-file-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Buka / Unduh Berkas Lampiran Revisi Admin (<?= e($t['file_revisi']); ?>)
                                </a>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <a href="dashboard_karyawan.php" class="btn-action-portal">
                                Perbaiki & Upload Ulang di Dashboard
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="padding: 50px; text-align:center; background:#fff; border-radius:12px; border:1px dashed #d1d5db;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#9ca3af" style="width:48px; height:48px; margin:0 auto 12px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p style="color:#6b7280; font-weight: 600; margin:0;">Bagus sekali! Tidak ada tugas yang memerlukan revisi saat ini.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>
    <?php require_once "../layouts/footer.php"; ?>

</body>
</html>