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

// Fitur Auto-Update: Pastikan kolom database tersedia
$checkTaskFile = mysqli_query($conn, "SHOW COLUMNS FROM task LIKE 'file_tugas'");
if ($checkTaskFile && mysqli_num_rows($checkTaskFile) === 0) {
    mysqli_query($conn, "ALTER TABLE task ADD COLUMN file_tugas VARCHAR(255) DEFAULT NULL AFTER deskripsi");
}

$checkTaskFileKaryawan = mysqli_query($conn, "SHOW COLUMNS FROM task LIKE 'file_tugas_karyawan'");
if ($checkTaskFileKaryawan && mysqli_num_rows($checkTaskFileKaryawan) === 0) {
    mysqli_query($conn, "ALTER TABLE task ADD COLUMN file_tugas_karyawan VARCHAR(255) DEFAULT NULL AFTER file_tugas");
}

$checkTaskNote = mysqli_query($conn, "SHOW COLUMNS FROM task LIKE 'keterangan_karyawan'");
if ($checkTaskNote && mysqli_num_rows($checkTaskNote) === 0) {
    mysqli_query($conn, "ALTER TABLE task ADD COLUMN keterangan_karyawan TEXT DEFAULT NULL AFTER file_tugas_karyawan");
}

$checkTaskRev = mysqli_query($conn, "SHOW COLUMNS FROM task LIKE 'catatan_revisi'");
if ($checkTaskRev && mysqli_num_rows($checkTaskRev) === 0) {
    mysqli_query($conn, "ALTER TABLE task ADD COLUMN catatan_revisi TEXT DEFAULT NULL AFTER keterangan_karyawan");
}

$checkTaskRevFile = mysqli_query($conn, "SHOW COLUMNS FROM task LIKE 'file_revisi'");
if ($checkTaskRevFile && mysqli_num_rows($checkTaskRevFile) === 0) {
    mysqli_query($conn, "ALTER TABLE task ADD COLUMN file_revisi VARCHAR(255) DEFAULT NULL AFTER catatan_revisi");
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$nip_login = $_SESSION['nip'];
$activeNav = 'dashboard_karyawan';

// --- PROSES UPDATE STATUS & UPLOAD TUGAS OLEH KARYAWAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status_tugas') {
    $task_id = $_POST['task_id'] ?? '';
    $new_status = $_POST['new_status'] ?? '';
    $keterangan_karyawan = trim($_POST['keterangan_karyawan'] ?? '');
    $file_name = null;
    
    $valid_statuses = ['Belum Selesai', 'Proses', 'Selesai'];
    if (in_array($new_status, $valid_statuses) && !empty($task_id)) {
        
        // Proses Upload File Hasil Kerja Karyawan (Masuk ke file_tugas_karyawan)
        if (isset($_FILES['file_tugas_karyawan']) && $_FILES['file_tugas_karyawan']['error'] === UPLOAD_ERR_OK) {
            $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
            $file_ext = strtolower(pathinfo($_FILES['file_tugas_karyawan']['name'], PATHINFO_EXTENSION));
            $file_size = $_FILES['file_tugas_karyawan']['size'];
            
            if (in_array($file_ext, $allowed_ext) && $file_size <= 5 * 1024 * 1024) {
                $upload_dir = '../assets/uploads/tugas/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = 'Karyawan_' . $nip_login . '_' . time() . '.' . $file_ext;
                
                // Hapus file lama karyawan jika ada
                $stmt_old = mysqli_prepare($conn, "SELECT file_tugas_karyawan FROM task WHERE id = ?");
                mysqli_stmt_bind_param($stmt_old, "i", $task_id);
                mysqli_stmt_execute($stmt_old);
                $res_old = mysqli_stmt_get_result($stmt_old);
                if ($old = mysqli_fetch_assoc($res_old)) {
                    if (!empty($old['file_tugas_karyawan']) && file_exists($upload_dir . $old['file_tugas_karyawan'])) {
                        unlink($upload_dir . $old['file_tugas_karyawan']);
                    }
                }
                mysqli_stmt_close($stmt_old);

                move_uploaded_file($_FILES['file_tugas_karyawan']['tmp_name'], $upload_dir . $file_name);
            }
        }

        // Simpan ke Database
        if ($file_name) {
            $stmt_upd = mysqli_prepare($conn, "UPDATE task SET status = ?, file_tugas_karyawan = ?, keterangan_karyawan = ? WHERE id = ? AND nip = ?");
            mysqli_stmt_bind_param($stmt_upd, "sssis", $new_status, $file_name, $keterangan_karyawan, $task_id, $nip_login);
        } else {
            $stmt_upd = mysqli_prepare($conn, "UPDATE task SET status = ?, keterangan_karyawan = ? WHERE id = ? AND nip = ?");
            mysqli_stmt_bind_param($stmt_upd, "ssis", $new_status, $keterangan_karyawan, $task_id, $nip_login);
        }
        
        mysqli_stmt_execute($stmt_upd);
        mysqli_stmt_close($stmt_upd);
    }
    
    header("Location: dashboard_karyawan.php");
    exit;
}

// --- AMBIL DATA PROFIL KARYAWAN ---
$stmt_karyawan = mysqli_prepare($conn, "SELECT * FROM karyawan WHERE nip = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_karyawan, "s", $nip_login);
mysqli_stmt_execute($stmt_karyawan);
$result_karyawan = mysqli_stmt_get_result($stmt_karyawan);
$profil = mysqli_fetch_assoc($result_karyawan);
mysqli_stmt_close($stmt_karyawan);

if (!$profil) {
    die("Data profil tidak ditemukan.");
}

// Format Tanggal Bergabung
$tanggal_bergabung = '-';
if (!empty($profil['tanggal_masuk'])) {
    $bulan_indo = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
    $timestamp = strtotime($profil['tanggal_masuk']);
    $tanggal_bergabung = date('d', $timestamp) . ' ' . $bulan_indo[(int)date('m', $timestamp) - 1] . ' ' . date('Y', $timestamp);
}

// --- AMBIL DATA TUGAS KARYAWAN ---
$tasksToWork = [];   // Tugas yang harus dikerjakan (Belum Selesai / Proses tanpa file upload atau baru)
$tasksSubmitted = []; // Kumpulan tugas yang sudah dikumpulkan (Sudah upload file atau status Selesai)

$statSelesai = 0;
$statProses = 0;
$statBelum = 0;

$stmt_task = mysqli_prepare($conn, "SELECT id, judul, deskripsi, status, file_tugas, file_tugas_karyawan, keterangan_karyawan, catatan_revisi, file_revisi, DATE_FORMAT(created_at, '%d %b %Y') as tanggal_dibuat FROM task WHERE nip = ? ORDER BY created_at DESC");
mysqli_stmt_bind_param($stmt_task, "s", $nip_login);
mysqli_stmt_execute($stmt_task);
$result_task = mysqli_stmt_get_result($stmt_task);

while ($row = mysqli_fetch_assoc($result_task)) {
    if ($row['status'] === 'Selesai' || !empty($row['file_tugas_karyawan'])) {
        $tasksSubmitted[] = $row;
    } else {
        $tasksToWork[] = $row;
    }

    if ($row['status'] === 'Selesai') $statSelesai++;
    elseif ($row['status'] === 'Proses') $statProses++;
    elseif ($row['status'] === 'Belum Selesai') $statBelum++;
}
mysqli_stmt_close($stmt_task);
$totalTask = count($tasksToWork) + count($tasksSubmitted);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Karyawan</title>
    <link rel="stylesheet" href="../assets/css/dasboard.css">
    <style>
        .portal-container { width: 100%; margin: 0 auto; padding: 0; box-sizing: border-box; }
        
        .id-card-theme { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 32px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .id-card-left h2 { font-size: 1.8rem; margin: 0 0 4px 0; font-weight: 800; color: #111827; }
        .id-card-left p { margin: 0; font-size: 0.95rem; font-weight: 600; color: #6b7280; text-transform: uppercase; }
        .id-card-right { text-align: right; }
        .nip-label { display: block; font-size: 0.7rem; font-weight: 700; color: #9ca3af; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 4px; }
        .nip-value { font-size: 1.6rem; font-weight: 900; letter-spacing: 2px; color: #4f46e5; }

        .info-cards-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px; }
        .info-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 20px; display: flex; align-items: flex-start; gap: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .info-icon { background-color: #eff6ff; color: #3b82f6; width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .info-icon svg { width: 22px; height: 22px; }
        .info-text-area { flex: 1; }
        .info-label { font-size: 0.75rem; font-weight: 700; color: #6b7280; margin: 0 0 4px 0; }
        .info-val { font-size: 1rem; font-weight: 700; color: #111827; margin: 0 0 4px 0; }
        .info-sub { font-size: 0.8rem; color: #9ca3af; margin: 0; }

        .stats-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        @media (max-width: 1024px) {
            .stats-grid-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 600px) {
            .stats-grid-4 { grid-template-columns: 1fr; }
        }

        .system-info-header {
            background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
            border: 1px solid #e2e8f0;
            border-left: 5px solid #4f46e5;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 32px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .system-info-header h4 {
            margin: 0 0 6px 0;
            font-size: 1.05rem;
            font-weight: 800;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .system-info-header p {
            margin: 0;
            font-size: 0.9rem;
            color: #475569;
            line-height: 1.6;
        }

        .section-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; margin-top: 36px; }
        .section-icon { background: #3b82f6; color: white; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .section-icon.submitted { background: #10b981; }
        .section-icon svg { width: 18px; height: 18px; }
        .section-title { font-size: 1.2rem; font-weight: 800; color: #111827; margin: 0; }

        .task-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .task-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 1px dashed #f3f4f6; padding-bottom: 16px; }
        .task-title { font-size: 1.15rem; font-weight: 800; color: #111827; margin-bottom: 4px;}
        .task-date { font-size: 0.8rem; color: #6b7280; }
        
        .task-instruction-label { font-size: 0.75rem; font-weight: 700; color: #6b7280; text-transform: uppercase; margin-bottom: 8px; }
        .task-desc-box { background-color: #f9fafb; border-left: 4px solid #4f46e5; padding: 12px 16px; border-radius: 4px 8px 8px 4px; font-size: 0.95rem; color: #374151; line-height: 1.6; margin-bottom: 20px; white-space: pre-wrap; }

        /* Kotak Lampiran Berkas dari Admin */
        .admin-file-box {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-left: 4px solid #22c55e;
            padding: 12px 16px;
            border-radius: 4px 8px 8px 4px;
            margin-bottom: 20px;
        }
        .admin-file-box a { color: #15803d; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .admin-file-box a:hover { text-decoration: underline; }

        .revisi-alert-box { background-color: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; padding: 14px 16px; border-radius: 4px 8px 8px 4px; margin-bottom: 20px; }
        .revisi-alert-title { font-size: 0.8rem; font-weight: 700; color: #b45309; text-transform: uppercase; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
        .revisi-alert-content { font-size: 0.95rem; color: #92400e; line-height: 1.5; white-space: pre-wrap; margin-bottom: 12px; }
        
        .revisi-file-btn { display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600; color: #92400e; background: #fef3c7; padding: 8px 14px; border-radius: 6px; text-decoration: none; border: 1px solid #fcd34d; transition: background 0.2s; }
        .revisi-file-btn:hover { background: #fde68a; }

        .task-action-box { background: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; }
        .task-upload-area { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px dashed #d1d5db; }
        .uploaded-file-info { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; margin-top: 12px; padding: 10px 14px; background: #eff6ff; border-radius: 6px; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .uploaded-file-info a { color: #1d4ed8; text-decoration: none; font-weight: 600; }
        
        .upload-input-group { margin-bottom: 16px; }
        .upload-input-group label { display: block; font-size: 0.85rem; font-weight: 600; color: #374151; margin-bottom: 8px; }
        .upload-input-group input[type="file"], .upload-input-group textarea { font-size: 0.9rem; width: 100%; padding: 10px; background: #fff; border: 1px solid #d1d5db; border-radius: 6px; color: #4b5563; font-family: inherit; box-sizing: border-box; }
        .upload-input-group textarea { resize: vertical; }

        .status-updater { display: flex; align-items: center; gap: 12px; }
        .status-updater select { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem; outline: none; background: white; min-width: 150px; }
        .status-updater button { padding: 8px 20px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .status-updater button:hover { background: #4338ca; }

        .badge-selesai { background-color: rgba(16, 185, 129, 0.12) !important; color: #047857 !important; }
        .badge-proses { background-color: rgba(245, 158, 11, 0.14) !important; color: #b45309 !important; }
        .badge-belumselesai { background-color: rgba(239, 68, 68, 0.12) !important; color: #b91c1c !important; }

        /* Footer Terpadu */
        .app-footer {
            margin-top: 50px;
            padding: 32px 24px;
            border-top: 1px solid var(--border-color, #e5e7eb);
            background: #ffffff;
            border-radius: 16px 16px 0 0;
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.01);
        }
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            text-align: left;
        }
        @media (max-width: 768px) {
            .footer-content {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body class="dashboard-page">
<div class="dashboard-shell">
    <?php require_once "../layouts/navbar.php"; ?>
    <main class="dashboard-main portal-container reveal" style="--delay:.05s">
        
        <section class="hero-card">
            <div>
                <p class="eyebrow">Dashboard KARYAWAN</p>
                <h2>Halo, <?= e($profil['nama_karyawan']); ?></h2>
                <p>Selamat datang di sistem informasi karyawan. Kelola dan pantau progres pekerjaan Anda di sini.</p>
            </div>
            <div class="hero-actions">
                <a href="profile.php" class="btn btn-secondary" style="text-decoration: none;">Lihat Profil</a>
            </div>
        </section>

        <!-- STATISTIK 4 KOLOM -->
        <section class="stats-grid-4">
            <div class="stat-card reveal" style="--delay:.1s">
                <h3>Total Tugas</h3>
                <p class="stat-number" data-count="<?= (int) $totalTask; ?>">0</p>
                <small class="text-muted">Keseluruhan Penugasan</small>
            </div>
            <div class="stat-card success reveal" style="--delay:.14s">
                <h3>Selesai</h3>
                <p class="stat-number" data-count="<?= (int) $statSelesai; ?>">0</p>
                <small class="text-muted">Tugas Disetujui</small>
            </div>
            <div class="stat-card warning reveal" style="--delay:.18s">
                <h3>Dalam Proses</h3>
                <p class="stat-number" data-count="<?= (int) $statProses; ?>">0</p>
                <small class="text-muted">Sedang Dikerjakan/Revisi</small>
            </div>
            <div class="stat-card danger reveal" style="--delay:.22s">
                <h3>Belum Selesai</h3>
                <p class="stat-number" data-count="<?= (int) $statBelum; ?>">0</p>
                <small class="text-muted">Belum Dimulai</small>
            </div>
        </section>

        <div class="id-card-theme reveal" style="--delay:.28s">
            <div class="id-card-left">
                <span class="badge badge-<?= e(strtolower($profil['status'])); ?>" style="margin-bottom: 16px; display: inline-block; padding: 6px 14px; border-radius: 999px; font-size: 0.8rem;">● Status <?= e($profil['status']); ?></span>
                <h2><?= e($profil['jabatan']); ?></h2>
                <p><?= e($profil['departemen']); ?></p>
            </div>
            <div class="id-card-right">
                <span class="nip-label">Nomor Induk Karyawan</span>
                <span class="nip-value"><?= e($profil['nip']); ?></span>
            </div>
        </div>

        <div class="info-cards-grid reveal" style="--delay:.34s">
            <div class="info-card">
                <div class="info-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg></div>
                <div class="info-text-area"><p class="info-label">Email Perusahaan</p><p class="info-val"><?= e($profil['email']); ?></p><p class="info-sub">Digunakan untuk login</p></div>
            </div>
            <div class="info-card">
                <div class="info-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg></div>
                <div class="info-text-area"><p class="info-label">Tanggal Bergabung</p><p class="info-val"><?= $tanggal_bergabung ?></p><p class="info-sub">Sesuai dokumen</p></div>
            </div>
        </div>

        <!-- HEADER INFORMASI SISTEM PROFESIONAL -->
        <div class="system-info-header reveal" style="--delay:.38s">
            <h4>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:20px;height:20px;color:#4f46e5;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Pusat Informasi & Koordinasi Penugasan
            </h4>
            <p>Seluruh rincian penugasan, instruksi kerja, target progres, serta catatan evaluasi atau berkas revisi di dalam portal ini dikelola dan diperbarui secara terpusat oleh Administrator/HRD perusahaan. Silakan tinjau instruksi dengan teliti dan unggah laporan hasil kerja Anda secara berkala.</p>
        </div>

        <!-- ================================================= -->
        <!-- BAGIAN 1: DAFTAR TUGAS YANG HARUS DIKERJAKAN      -->
        <!-- ================================================= -->
        <div class="section-header reveal" style="--delay:.4s;">
            <div class="section-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg></div>
            <div>
                <h3 class="section-title">Daftar Tugas yang Harus Dikerjakan</h3>
                <p style="margin: 4px 0 0 0; font-size:0.85rem; color:#6b7280;">Tugas baru atau tugas yang memerlukan perbaikan dari admin.</p>
            </div>
        </div>

        <div class="reveal" style="--delay:.46s">
            <?php if (!empty($tasksToWork)): ?>
                <?php foreach ($tasksToWork as $t): ?>
                    <div class="task-card">
                        <div class="task-header">
                            <div>
                                <div class="task-title"><?= e($t['judul']); ?></div>
                                <div class="task-date">Diberikan pada: <?= e($t['tanggal_dibuat']); ?></div>
                            </div>
                            <?php $badgeClass = str_replace(' ', '', strtolower($t['status'])); ?>
                            <span class="badge badge-<?= e($badgeClass); ?>" style="font-size: 0.85rem; padding: 6px 14px;"><?= e($t['status']); ?></span>
                        </div>
                        
                        <div class="task-instruction-label">Deskripsi / Instruksi Tugas:</div>
                        <div class="task-desc-box"><?= e($t['deskripsi']); ?></div>

                        <!-- LAMPIRAN BERKAS DARI ADMIN -->
                        <?php if (!empty($t['file_tugas'])): ?>
                            <div class="admin-file-box">
                                <a href="../assets/uploads/tugas/<?= e($t['file_tugas']); ?>" target="_blank">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Unduh / Lihat Berkas Lampiran dari Admin (<?= e($t['file_tugas']); ?>)
                                </a>
                            </div>
                        <?php endif; ?>

                        <!-- KOTAK CATATAN & BERKAS REVISI DARI ADMIN -->
                        <?php if (!empty($t['catatan_revisi']) || !empty($t['file_revisi'])): ?>
                            <div class="revisi-alert-box">
                                <div class="revisi-alert-title">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    Catatan Revisi dari Admin:
                                </div>
                                <?php if (!empty($t['catatan_revisi'])): ?>
                                    <div class="revisi-alert-content"><?= nl2br(e($t['catatan_revisi'])); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($t['file_revisi'])): ?>
                                    <a href="../assets/uploads/revisi/<?= e($t['file_revisi']); ?>" target="_blank" class="revisi-file-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                        Buka / Unduh Berkas Revisi Admin (<?= e($t['file_revisi']); ?>)
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Form Update & Upload Tugas Karyawan -->
                        <form method="POST" action="" enctype="multipart/form-data" class="task-action-box">
                            <input type="hidden" name="action" value="update_status_tugas">
                            <input type="hidden" name="task_id" value="<?= e($t['id']); ?>">
                            
                            <div class="task-upload-area">
                                <div class="upload-input-group">
                                    <label>Keterangan / Link Hasil Tugas (Opsional):</label>
                                    <textarea name="keterangan_karyawan" rows="2" placeholder="Tempelkan link atau catatan laporan..."><?= e($t['keterangan_karyawan'] ?? '') ?></textarea>
                                </div>

                                <div class="upload-input-group">
                                    <label>Upload File Hasil / Perbaikan Tugas (Maks 5MB):</label>
                                    <input type="file" name="file_tugas_karyawan" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.rar">
                                </div>
                            </div>

                            <div class="status-updater">
                                <span style="font-size: 0.9rem; font-weight: 600; color:#4b5563;">Progress Status:</span>
                                <select name="new_status">
                                    <option value="Belum Selesai" <?= $t['status'] === 'Belum Selesai' ? 'selected' : ''; ?>>Belum Selesai</option>
                                    <option value="Proses" <?= $t['status'] === 'Proses' ? 'selected' : ''; ?>>Proses</option>
                                    <option value="Selesai" <?= $t['status'] === 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                                </select>
                                <button type="submit">Simpan & Update</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="padding: 30px; text-align:center; background:#fff; border-radius:12px; border:1px dashed #d1d5db; margin-bottom: 20px;">
                    <p style="color:#6b7280; margin:0;">Bagus sekali! Tidak ada tugas yang perlu dikerjakan saat ini.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ================================================= -->
        <!-- BAGIAN 2: KUMPULAN TUGAS YANG SUDAH DIKUMPULKAN   -->
        <!-- ================================================= -->
        <div class="section-header reveal" style="--delay:.5s;">
            <div class="section-icon submitted"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg></div>
            <div>
                <h3 class="section-title">Kumpulan Tugas yang Sudah Dikumpulkan</h3>
                <p style="margin: 4px 0 0 0; font-size:0.85rem; color:#6b7280;">Daftar tugas yang telah Anda kumpulkan atau disetujui oleh admin.</p>
            </div>
        </div>

        <div class="reveal" style="--delay:.55s">
            <?php if (!empty($tasksSubmitted)): ?>
                <?php foreach ($tasksSubmitted as $t): ?>
                    <div class="task-card" style="border-left: 4px solid #10b981;">
                        <div class="task-header">
                            <div>
                                <div class="task-title"><?= e($t['judul']); ?></div>
                                <div class="task-date">Diberikan pada: <?= e($t['tanggal_dibuat']); ?></div>
                            </div>
                            <?php $badgeClass = str_replace(' ', '', strtolower($t['status'])); ?>
                            <span class="badge badge-<?= e($badgeClass); ?>" style="font-size: 0.85rem; padding: 6px 14px;"><?= e($t['status']); ?></span>
                        </div>
                        
                        <div class="task-instruction-label">Deskripsi / Instruksi Tugas:</div>
                        <div class="task-desc-box"><?= e($t['deskripsi']); ?></div>

                        <!-- LAMPIRAN BERKAS DARI ADMIN -->
                        <?php if (!empty($t['file_tugas'])): ?>
                            <div class="admin-file-box">
                                <a href="../assets/uploads/tugas/<?= e($t['file_tugas']); ?>" target="_blank">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Unduh / Lihat Berkas Lampiran dari Admin (<?= e($t['file_tugas']); ?>)
                                </a>
                            </div>
                        <?php endif; ?>

                        <!-- Form Update & Upload Tugas Karyawan (Riwayat Pengumpulan) -->
                        <form method="POST" action="" enctype="multipart/form-data" class="task-action-box">
                            <input type="hidden" name="action" value="update_status_tugas">
                            <input type="hidden" name="task_id" value="<?= e($t['id']); ?>">
                            
                            <div class="task-upload-area">
                                <div class="upload-input-group">
                                    <label>Keterangan / Link Hasil Tugas (Opsional):</label>
                                    <textarea name="keterangan_karyawan" rows="2" placeholder="Tempelkan link atau catatan laporan..."><?= e($t['keterangan_karyawan'] ?? '') ?></textarea>
                                </div>

                                <div class="upload-input-group">
                                    <label>Perbarui File Hasil / Perbaikan Tugas (Maks 5MB):</label>
                                    <input type="file" name="file_tugas_karyawan" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.rar">
                                </div>
                                
                                <?php if (!empty($t['file_tugas_karyawan'])): ?>
                                    <div class="uploaded-file-info">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:20px;height:20px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                        <a href="../assets/uploads/tugas/<?= e($t['file_tugas_karyawan']); ?>" target="_blank">Lihat Berkas Tugas Terakhir yang Anda Kirim (<?= e($t['file_tugas_karyawan']); ?>)</a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="status-updater">
                                <span style="font-size: 0.9rem; font-weight: 600; color:#4b5563;">Progress Status:</span>
                                <select name="new_status">
                                    <option value="Belum Selesai" <?= $t['status'] === 'Belum Selesai' ? 'selected' : ''; ?>>Belum Selesai</option>
                                    <option value="Proses" <?= $t['status'] === 'Proses' ? 'selected' : ''; ?>>Proses</option>
                                    <option value="Selesai" <?= $t['status'] === 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                                </select>
                                <button type="submit">Simpan & Update</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="padding: 30px; text-align:center; background:#fff; border-radius:12px; border:1px dashed #d1d5db;">
                    <p style="color:#6b7280; margin:0;">Belum ada tugas yang dikumpulkan.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>
    <?php require_once "../layouts/footer.php"; ?>

</div>
<script>
    document.querySelectorAll('.stat-number[data-count]').forEach((el) => {
        const target = parseInt(el.dataset.count, 10) || 0;
        const duration = 700; const start = performance.now();
        function tick(now) {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(target * eased).toLocaleString('id-ID');
            if (progress < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    });
</script>
</body>
</html>