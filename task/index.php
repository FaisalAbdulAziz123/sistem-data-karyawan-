<?php
session_start();

if (!isset($_SESSION['login'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/koneksi.php";

if (!isset($conn) || $conn === false) {
    die("Koneksi database gagal.");
}

mysqli_set_charset($conn, 'utf8mb4');

// Memastikan tabel karyawan tersedia (sebagai referensi)
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS karyawan (
        nip VARCHAR(30) NOT NULL PRIMARY KEY,
        nama_karyawan VARCHAR(120) NOT NULL,
        email VARCHAR(120) NOT NULL,
        password VARCHAR(255) NOT NULL,
        jabatan VARCHAR(100) NOT NULL,
        departemen VARCHAR(100) NOT NULL,
        no_telp VARCHAR(20) DEFAULT NULL,
        tanggal_masuk DATE DEFAULT NULL,
        alamat TEXT DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Aktif',
        foto VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Memastikan tabel Tugas Karyawan tersedia beserta kolom file admin, file karyawan, keterangan, dan revisi
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS task (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nip VARCHAR(30) NOT NULL,
        judul VARCHAR(150) NOT NULL,
        deskripsi TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Belum Selesai',
        file_tugas VARCHAR(255) DEFAULT NULL,
        file_tugas_karyawan VARCHAR(255) DEFAULT NULL,
        keterangan_karyawan TEXT DEFAULT NULL,
        catatan_revisi TEXT DEFAULT NULL,
        file_revisi VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (nip) REFERENCES karyawan(nip) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Fitur Auto-Update: Pastikan kolom yang dibutuhkan ada
$checkTask = mysqli_query($conn, "SHOW COLUMNS FROM task LIKE 'judul'");
if ($checkTask && mysqli_num_rows($checkTask) === 0) {
    mysqli_query($conn, "ALTER TABLE task ADD COLUMN judul VARCHAR(150) NOT NULL AFTER nip");
}

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

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$activeNav = 'task';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function csrfValid(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

const STATUS_TASK_OPTIONS = ['Belum Selesai', 'Proses', 'Selesai'];

$successMessage = '';
$errorMessage = '';
$formData = [
    'id' => '', 'nip' => '', 'judul' => '', 'deskripsi' => '', 'status' => 'Belum Selesai', 'file_tugas' => ''
];

// --- PROSES CRUD TUGAS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        $errorMessage = 'Sesi tidak valid, silakan muat ulang halaman.';
    } else {
        $action = $_POST['action'] ?? '';

        // TAMBAH / EDIT TUGAS (Oleh Admin)
        if ($action === 'save') {
            $formData['id'] = trim($_POST['id'] ?? '');
            $formData['nip'] = trim($_POST['nip'] ?? '');
            $formData['judul'] = trim($_POST['judul'] ?? '');
            $formData['deskripsi'] = trim($_POST['deskripsi'] ?? '');
            $formData['status'] = trim($_POST['status'] ?? 'Belum Selesai');
            $current_file_tugas = trim($_POST['current_file_tugas'] ?? '');

            if (!in_array($formData['status'], STATUS_TASK_OPTIONS, true)) {
                $formData['status'] = 'Belum Selesai';
            }

            // Proses Upload File Tugas dari Admin -> Masuk ke kolom `file_tugas`
            $file_tugas_name = $current_file_tugas !== '' ? $current_file_tugas : null;
            if (isset($_FILES['file_tugas']) && $_FILES['file_tugas']['error'] === UPLOAD_ERR_OK) {
                $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
                $file_ext = strtolower(pathinfo($_FILES['file_tugas']['name'], PATHINFO_EXTENSION));
                $file_size = $_FILES['file_tugas']['size'];

                if (in_array($file_ext, $allowed_ext) && $file_size <= 5 * 1024 * 1024) {
                    $upload_dir = '../assets/uploads/tugas/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $file_tugas_name = 'AdminTugas_' . time() . '.' . $file_ext;
                    
                    if ($current_file_tugas !== '' && file_exists($upload_dir . $current_file_tugas)) {
                        @unlink($upload_dir . $current_file_tugas);
                    }

                    move_uploaded_file($_FILES['file_tugas']['tmp_name'], $upload_dir . $file_tugas_name);
                }
            }

            if ($formData['nip'] === '' || $formData['judul'] === '' || $formData['deskripsi'] === '') {
                $errorMessage = 'Pilih Karyawan, isi Judul, dan Deskripsi Tugas!';
            } else {
                if ($formData['id'] === '') {
                    $stmt = mysqli_prepare($conn, 'INSERT INTO task (nip, judul, deskripsi, status, file_tugas) VALUES (?, ?, ?, ?, ?)');
                    mysqli_stmt_bind_param($stmt, 'sssss', $formData['nip'], $formData['judul'], $formData['deskripsi'], $formData['status'], $file_tugas_name);

                    if (mysqli_stmt_execute($stmt)) {
                        $successMessage = 'Tugas berhasil ditambahkan.';
                        $formData = ['id' => '', 'nip' => '', 'judul' => '', 'deskripsi' => '', 'status' => 'Belum Selesai', 'file_tugas' => ''];
                    } else {
                        $errorMessage = 'Gagal menambahkan tugas.';
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $stmt = mysqli_prepare($conn, 'UPDATE task SET nip = ?, judul = ?, deskripsi = ?, status = ?, file_tugas = ? WHERE id = ?');
                    mysqli_stmt_bind_param($stmt, 'sssssi', $formData['nip'], $formData['judul'], $formData['deskripsi'], $formData['status'], $file_tugas_name, $formData['id']);

                    if (mysqli_stmt_execute($stmt)) {
                        $successMessage = 'Tugas berhasil diperbarui.';
                        $formData = ['id' => '', 'nip' => '', 'judul' => '', 'deskripsi' => '', 'status' => 'Belum Selesai', 'file_tugas' => ''];
                    } else {
                        $errorMessage = 'Gagal memperbarui tugas.';
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }

        // APPROVE TUGAS (MENGUBAH STATUS JADI SELESAI)
        if ($action === 'approve') {
            $approveId = trim($_POST['id'] ?? '');
            if ($approveId !== '') {
                $stmt = mysqli_prepare($conn, "UPDATE task SET status = 'Selesai' WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $approveId);

                if (mysqli_stmt_execute($stmt)) {
                    $successMessage = 'Tugas berhasil di-Approve (Selesai).';
                } else {
                    $errorMessage = 'Gagal melakukan Approve pada tugas.';
                }
                mysqli_stmt_close($stmt);
            }
        }

        // KIRIM REVISI TUGAS (DENGAN UPLOAD BERKAS REVISI)
        if ($action === 'revisi') {
            $revisiId = trim($_POST['id'] ?? '');
            $catatanRevisi = trim($_POST['catatan_revisi'] ?? '');
            $file_revisi_name = null;

            if ($revisiId !== '' && $catatanRevisi !== '') {
                if (isset($_FILES['file_revisi']) && $_FILES['file_revisi']['error'] === UPLOAD_ERR_OK) {
                    $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
                    $file_ext = strtolower(pathinfo($_FILES['file_revisi']['name'], PATHINFO_EXTENSION));
                    $file_size = $_FILES['file_revisi']['size'];

                    if (in_array($file_ext, $allowed_ext) && $file_size <= 5 * 1024 * 1024) {
                        $upload_dir = '../assets/uploads/revisi/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $file_revisi_name = 'Revisi_' . $revisiId . '_' . time() . '.' . $file_ext;
                        move_uploaded_file($_FILES['file_revisi']['tmp_name'], $upload_dir . $file_revisi_name);
                    }
                }

                if ($file_revisi_name) {
                    $stmt = mysqli_prepare($conn, "UPDATE task SET status = 'Proses', catatan_revisi = ?, file_revisi = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, 'ssi', $catatanRevisi, $file_revisi_name, $revisiId);
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE task SET status = 'Proses', catatan_revisi = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, 'si', $catatanRevisi, $revisiId);
                }

                if (mysqli_stmt_execute($stmt)) {
                    $successMessage = 'Catatan dan berkas revisi berhasil dikirim ke karyawan.';
                } else {
                    $errorMessage = 'Gagal mengirim catatan revisi.';
                }
                mysqli_stmt_close($stmt);
            } else {
                $errorMessage = 'Catatan revisi tidak boleh kosong!';
            }
        }

        // HAPUS TUGAS
        if ($action === 'delete') {
            $deleteId = trim($_POST['id'] ?? '');
            if ($deleteId !== '') {
                $stmt_file = mysqli_prepare($conn, 'SELECT file_tugas, file_tugas_karyawan, file_revisi FROM task WHERE id = ?');
                mysqli_stmt_bind_param($stmt_file, 'i', $deleteId);
                mysqli_stmt_execute($stmt_file);
                $res_file = mysqli_stmt_get_result($stmt_file);
                if ($row_file = mysqli_fetch_assoc($res_file)) {
                    if (!empty($row_file['file_tugas']) && file_exists('../assets/uploads/tugas/' . $row_file['file_tugas'])) {
                        @unlink('../assets/uploads/tugas/' . $row_file['file_tugas']);
                    }
                    if (!empty($row_file['file_tugas_karyawan']) && file_exists('../assets/uploads/tugas/' . $row_file['file_tugas_karyawan'])) {
                        @unlink('../assets/uploads/tugas/' . $row_file['file_tugas_karyawan']);
                    }
                    if (!empty($row_file['file_revisi']) && file_exists('../assets/uploads/revisi/' . $row_file['file_revisi'])) {
                        @unlink('../assets/uploads/revisi/' . $row_file['file_revisi']);
                    }
                }
                mysqli_stmt_close($stmt_file);

                $stmt = mysqli_prepare($conn, 'DELETE FROM task WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $deleteId);

                if (mysqli_stmt_execute($stmt)) {
                    $successMessage = 'Tugas berhasil dihapus.';
                } else {
                    $errorMessage = 'Gagal menghapus tugas.';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// AMBIL DAFTAR KARYAWAN UNTUK DROPDOWN
$listKaryawan = [];
$resKaryawan = mysqli_query($conn, "SELECT nip, nama_karyawan, jabatan, departemen FROM karyawan ORDER BY nama_karyawan ASC");
if ($resKaryawan) {
    while ($row = mysqli_fetch_assoc($resKaryawan)) {
        $listKaryawan[] = $row;
    }
}

// AMBIL DATA TUGAS + JOIN KARYAWAN 
$search = trim($_GET['q'] ?? '');
$taskList = [];

$queryStr = "SELECT t.*, k.nama_karyawan, k.departemen, k.foto, DATE_FORMAT(t.created_at, '%d %M %Y %H:%i') as tanggal_dibuat 
             FROM task t JOIN karyawan k ON t.nip = k.nip";
if ($search !== '') {
    $likeSearch = '%' . $search . '%';
    $queryStr .= ' WHERE k.nama_karyawan LIKE ? OR t.judul LIKE ? OR t.deskripsi LIKE ? OR k.departemen LIKE ? OR t.status LIKE ? ORDER BY t.created_at DESC';
    $stmt = mysqli_prepare($conn, $queryStr);
    mysqli_stmt_bind_param($stmt, 'sssss', $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $queryStr .= ' ORDER BY t.created_at DESC';
    $result = mysqli_query($conn, $queryStr);
}

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $taskList[] = $row;
    }
}

// HITUNG STATISTIK TUGAS
$totalTask = count($taskList);
$taskSelesai = 0;
$taskProses = 0;
$taskBelum = 0;

foreach ($taskList as $t) {
    if ($t['status'] === 'Selesai') $taskSelesai++;
    elseif ($t['status'] === 'Proses') $taskProses++;
    elseif ($t['status'] === 'Belum Selesai') $taskBelum++;
}

$openModal = !empty($errorMessage) && $_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tugas Karyawan</title>
    <link rel="stylesheet" href="../assets/css/dasboard.css">
    <style>
        .form-group input[type="text"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 8px;
            font-size: 0.9rem;
            color: var(--text-main, #111827);
            outline: none;
            transition: all 0.2s ease;
            box-sizing: border-box;
            font-family: inherit;
        }
        .form-group input[type="text"]:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color, #4f46e5);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .required::after {
            content: " *";
            color: #ef4444;
            font-weight: bold;
        }

        .action-buttons {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        .btn-icon-only {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            border: none !important;
            background: transparent !important;
            cursor: pointer;
            transition: transform 0.1s ease, color 0.2s ease;
        }
        .btn-icon-only svg { width: 18px; height: 18px; pointer-events: none; }
        .btn-icon-only:not(:disabled):hover { transform: scale(1.15); }
        
        .btn-success.btn-icon-only { color: #10b981; }
        .btn-info.btn-icon-only { color: #3b82f6; }
        .btn-warning.btn-icon-only { color: #f59e0b; }
        .btn-edit.btn-icon-only { color: #4f46e5; }
        .btn-delete.btn-icon-only { color: #ef4444; }

        .badge-selesai { background-color: rgba(16, 185, 129, 0.12) !important; color: #047857 !important; }
        .badge-proses { background-color: rgba(245, 158, 11, 0.14) !important; color: #b45309 !important; }
        .badge-belumselesai { background-color: rgba(239, 68, 68, 0.12) !important; color: #b91c1c !important; }

        .submission-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.85rem;
        }
        .submission-box p { margin: 0 0 4px 0; color: #374151; word-break: break-word; }
        .submission-box a { color: #4f46e5; text-decoration: none; font-weight: 600; }
        .submission-box a:hover { text-decoration: underline; }

        .stats-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }
        @media (max-width: 1024px) {
            .stats-grid-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 600px) {
            .stats-grid-4 { grid-template-columns: 1fr; }
        }

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

    <main class="dashboard-main">
        <section class="hero-card reveal" style="--delay:.05s">
            <div>
                <p class="eyebrow">Manajemen Pekerjaan</p>
                <h2>Tugas Karyawan</h2>
                <p>Kelola dan pantau progres pekerjaan karyawan beserta catatan & berkas revisi.</p>
            </div>
            <div class="hero-actions">
                <button type="button" class="btn btn-primary btn-icon" data-modal-open="taskModal">
                    <span class="icon-plus">+</span>
                    <span>Buat Tugas Baru</span>
                </button>
                <div class="hero-search">
                    <form method="GET" action="" class="search-form search-form-hero">
                        <input type="text" name="q" value="<?= e($search); ?>" placeholder="Cari nama, tugas, departemen...">
                        <button type="submit" class="btn btn-secondary">Cari</button>
                        <?php if ($search): ?>
                            <a href="./index.php" class="btn btn-outline">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
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
                <p class="stat-number" data-count="<?= (int) $taskSelesai; ?>">0</p>
                <small class="text-muted">Tugas Disetujui</small>
            </div>
            <div class="stat-card warning reveal" style="--delay:.18s">
                <h3>Dalam Proses</h3>
                <p class="stat-number" data-count="<?= (int) $taskProses; ?>">0</p>
                <small class="text-muted">Sedang Dikerjakan/Revisi</small>
            </div>
            <div class="stat-card danger reveal" style="--delay:.22s">
                <h3>Belum Selesai</h3>
                <p class="stat-number" data-count="<?= (int) $taskBelum; ?>">0</p>
                <small class="text-muted">Belum Dimulai</small>
            </div>
        </section>

        <?php if ($successMessage): ?><div class="alert alert-success alert-in"><?= e($successMessage); ?></div><?php endif; ?>
        <?php if ($errorMessage): ?><div class="alert alert-error alert-in"><?= e($errorMessage); ?></div><?php endif; ?>

        <section class="table-section card reveal" style="--delay:.28s">
            <div class="card-header flex-between table-header">
                <div><h2>Daftar Tugas</h2><p class="text-muted">Progres pekerjaan, catatan, dan lampiran berkas dari karyawan.</p></div>
                <span class="table-count"><?= number_format($totalTask); ?> tugas</span>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Karyawan</th>
                            <th>Judul Tugas</th>
                            <th style="width: 20%;">Deskripsi Tugas</th>
                            <th style="width: 22%;">Hasil &amp; Lampiran Karyawan</th>
                            <th>Departemen</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($taskList)): ?>
                            <?php foreach ($taskList as $i => $t): ?>
                                <tr class="row-in" style="--row-delay:<?= min($i, 12) * 0.035; ?>s">
                                    <td data-label="Nama Karyawan">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <?php if (!empty($t['foto'])): ?>
                                                <img src="../assets/uploads/karyawan/<?= e($t['foto']); ?>" alt="Foto" class="avatar-photo" style="width:35px; height:35px;">
                                            <?php else: ?>
                                                <span class="avatar-fallback" style="width:35px; height:35px; font-size:10px;">N/A</span>
                                            <?php endif; ?>
                                            <strong style="color: #111827;"><?= e($t['nama_karyawan']); ?></strong>
                                        </div>
                                    </td>
                                    <td data-label="Judul Tugas"><strong><?= e($t['judul']); ?></strong></td>
                                    <td data-label="Deskripsi Tugas">
                                        <div style="white-space: pre-wrap; word-break: break-word; font-size: 0.9rem; line-height: 1.5; max-height: 4.5em; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;"><?= e($t['deskripsi']); ?></div>
                                    </td>
                                    <td data-label="Hasil Karyawan">
                                        <?php if (!empty($t['keterangan_karyawan']) || !empty($t['file_tugas_karyawan'])): ?>
                                            <div class="submission-box">
                                                <?php if (!empty($t['keterangan_karyawan'])): ?>
                                                    <p><strong>Catatan/Link:</strong><br><?= nl2br(e($t['keterangan_karyawan'])); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($t['file_tugas_karyawan'])): ?>
                                                    <p style="margin-top: <?= !empty($t['keterangan_karyawan']) ? '6px' : '0'; ?>;">
                                                        <strong>File:</strong> 
                                                        <a href="../assets/uploads/tugas/<?= e($t['file_tugas_karyawan']); ?>" target="_blank">Lihat/Unduh Berkas</a>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.85rem; font-style: italic;">Belum ada laporan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Departemen"><?= e($t['departemen']); ?></td>
                                    <td data-label="Status">
                                        <?php $badgeClass = str_replace(' ', '', strtolower($t['status'])); ?>
                                        <span class="badge badge-<?= e($badgeClass); ?>">
                                            <?= e($t['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Aksi" class="action-buttons">
                                        
                                        <!-- Tombol Detail (Diperbaiki agar data-file-karyawan membaca kolom file_tugas_karyawan) -->
                                        <button type="button" class="btn-sm btn-info btn-icon-only" title="Lihat Detail Tugas" data-modal-open="detailModal"
                                            data-nama="<?= e($t['nama_karyawan']); ?>"
                                            data-judul="<?= e($t['judul']); ?>"
                                            data-deskripsi="<?= e($t['deskripsi']); ?>"
                                            data-status="<?= e($t['status']); ?>"
                                            data-keterangan="<?= e($t['keterangan_karyawan'] ?? 'Tidak ada catatan'); ?>"
                                            data-file-admin="<?= e($t['file_tugas'] ?? ''); ?>"
                                            data-file-karyawan="<?= e($t['file_tugas_karyawan'] ?? ''); ?>"
                                            data-revisi="<?= e($t['catatan_revisi'] ?? 'Tidak ada catatan revisi'); ?>"
                                            data-file-revisi="<?= e($t['file_revisi'] ?? ''); ?>"
                                            data-tanggal="<?= e($t['tanggal_dibuat']); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>

                                        <!-- Tombol Revisi -->
                                        <button type="button" class="btn-sm btn-warning btn-icon-only" title="Minta Revisi" data-modal-open="revisiModal"
                                            data-id="<?= e($t['id']); ?>"
                                            data-judul="<?= e($t['judul']); ?>"
                                            data-nama="<?= e($t['nama_karyawan']); ?>"
                                            data-revisi="<?= e($t['catatan_revisi'] ?? ''); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>

                                        <!-- Tombol Approve -->
                                        <form method="POST" action="" onsubmit="return confirm('Approve tugas ini menjadi Selesai?');" style="display:inline; margin:0; padding:0;">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken); ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="id" value="<?= e($t['id']); ?>">
                                            <button type="submit" class="btn-sm btn-success btn-icon-only" title="Approve Tugas (Selesai)" <?= $t['status'] === 'Selesai' ? 'disabled style="opacity: 0.3; cursor: not-allowed;"' : '' ?>>
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                        </form>

                                        <!-- Tombol Edit -->
                                        <button type="button" class="btn-sm btn-edit btn-icon-only" title="Edit Tugas" data-edit="1" data-modal-open="taskModal"
                                            data-id="<?= e($t['id']); ?>"
                                            data-nip="<?= e($t['nip']); ?>"
                                            data-judul="<?= e($t['judul']); ?>"
                                            data-deskripsi="<?= e($t['deskripsi']); ?>"
                                            data-status="<?= e($t['status']); ?>"
                                            data-file-tugas="<?= e($t['file_tugas'] ?? ''); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </button>
                                        
                                        <!-- Tombol Hapus -->
                                        <form method="POST" action="" onsubmit="return confirm('Hapus tugas ini secara permanen?');" style="display:inline; margin:0; padding:0;">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= e($t['id']); ?>">
                                            <button type="submit" class="btn-sm btn-delete btn-icon-only" title="Hapus Tugas">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center empty-state">Belum ada tugas yang diberikan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- FOOTER LISENSI -->
    <footer class="app-footer reveal">
        <div class="footer-content">
            <div>
                <p style="margin: 0; font-weight: 700; color: #111827; font-size: 0.95rem; letter-spacing: 0.5px;">
                    Sistem Data Karyawan
                </p>
                <p style="margin: 4px 0 0 0; font-size: 0.8rem; color: #6b7280;">
                    &copy; <?= date('Y'); ?> — Seluruh Hak Cipta Dilindungi.
                </p>
            </div>
            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;" class="footer-right">
                <span style="font-size: 0.8rem; font-weight: 600; color: #4f46e5; background: #eef2ff; padding: 4px 10px; border-radius: 999px;">
                    Platform Manajemen & Penugasan Karyawan Terpadu
                </span>
                <span style="font-size: 0.75rem; color: #9ca3af; font-weight: 500;">
                    Developed with ❤️ By: <strong style="color: #374151;">Faisal Abdul Aziz</strong>
                </span>
            </div>
        </div>
    </footer>
</div>

<!-- ===================================== -->
<!-- MODAL DETAIL TUGAS & LAPORAN          -->
<!-- ===================================== -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <div>
                <h2>Detail Tugas & Laporan</h2>
                <p class="modal-subtitle">Informasi lengkap instruksi dan hasil kerja karyawan.</p>
            </div>
            <button type="button" class="close-btn" data-modal-close="detailModal" aria-label="Tutup">&times;</button>
        </div>

        <div style="display: flex; flex-direction: column; gap: 16px; font-size: 0.95rem; color: #374151;">
            <div>
                <strong style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; display: block; margin-bottom: 2px;">Karyawan</strong>
                <span id="det_nama" style="font-weight: 600; color: #111827; font-size: 1.05rem;">-</span>
            </div>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                <div>
                    <strong style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; display: block; margin-bottom: 2px;">Judul Tugas</strong>
                    <span id="det_judul" style="font-weight: 600; color: #111827;">-</span>
                </div>
                <div>
                    <strong style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; display: block; margin-bottom: 2px;">Tanggal Dibuat</strong>
                    <span id="det_tanggal" style="color: #4b5563;">-</span>
                </div>
            </div>
            <div>
                <strong style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; display: block; margin-bottom: 2px;">Instruksi / Deskripsi Tugas</strong>
                <div id="det_deskripsi" style="background: #f9fafb; padding: 12px; border-radius: 8px; border: 1px solid #e5e7eb; white-space: pre-wrap; word-break: break-word;">-</div>
            </div>
            <div>
                <strong style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; display: block; margin-bottom: 2px;">Lampiran Berkas dari Admin</strong>
                <div id="det_file_admin_wrapper">-</div>
            </div>
            <div>
                <strong style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; display: block; margin-bottom: 2px;">Catatan / Link Laporan Karyawan</strong>
                <div id="det_keterangan" style="background: #eff6ff; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe; color: #1e40af; white-space: pre-wrap; word-break: break-word;">-</div>
            </div>
            <div>
                <strong style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; display: block; margin-bottom: 2px;">Lampiran Berkas Hasil Kerja Karyawan</strong>
                <div id="det_file_karyawan_wrapper">-</div>
            </div>
            <div>
                <strong style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; display: block; margin-bottom: 2px;">Riwayat Catatan & Berkas Revisi Admin</strong>
                <div id="det_revisi" style="background: #fffbeb; padding: 12px; border-radius: 8px; border: 1px solid #fde68a; color: #92400e; white-space: pre-wrap; word-break: break-word;">-</div>
            </div>
        </div>

        <div class="modal-actions" style="margin-top: 24px;">
            <button type="button" class="btn btn-primary btn-block" data-modal-close="detailModal">Tutup</button>
        </div>
    </div>
</div>

<!-- ===================================== -->
<!-- MODAL KIRIM REVISI TUGAS              -->
<!-- ===================================== -->
<div class="modal-overlay" id="revisiModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <div>
                <h2>Form Permintaan Revisi</h2>
                <p class="modal-subtitle">Berikan catatan dan unggah berkas perbaikan untuk karyawan.</p>
            </div>
            <button type="button" class="close-btn" data-modal-close="revisiModal" aria-label="Tutup">&times;</button>
        </div>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken); ?>">
            <input type="hidden" name="action" value="revisi">
            <input type="hidden" name="id" id="revisi_id">

            <div class="form-group" style="margin-bottom: 12px;">
                <label>Karyawan & Tugas</label>
                <input type="text" id="revisi_info" disabled style="background: #f3f4f6; font-weight: 600;">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="catatan_revisi" class="required">Catatan / Poin Revisi</label>
                <textarea name="catatan_revisi" id="catatan_revisi" rows="4" placeholder="Tuliskan instruksi perbaikan..." required></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="file_revisi">Upload Berkas / File Revisi (Opsional, Maks 5MB):</label>
                <input type="file" name="file_revisi" id="file_revisi" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.rar" style="font-size: 0.85rem; width: 100%; padding: 8px; background: #fff; border: 1px solid #d1d5db; border-radius: 6px;">
            </div>

            <div class="modal-actions">
                <button type="submit" class="btn btn-warning btn-block" style="background: #f59e0b; color: #fff;">Kirim Revisi & Ubah Status ke Proses</button>
                <button type="button" class="btn btn-outline btn-block" data-modal-close="revisiModal">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- ===================================== -->
<!-- MODAL FORM TAMBAH / EDIT TUGAS        -->
<!-- ===================================== -->
<div class="modal-overlay <?= $openModal ? 'active' : ''; ?>" id="taskModal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
            <div>
                <h2 id="modalTitle">Buat Tugas Baru</h2>
                <p class="modal-subtitle">Berikan instruksi pekerjaan kepada karyawan.</p>
            </div>
            <button type="button" class="close-btn" data-modal-close="taskModal" aria-label="Tutup">&times;</button>
        </div>

        <form method="POST" action="" id="taskForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="form_id" value="<?= e($formData['id']); ?>">
            <input type="hidden" name="current_file_tugas" id="current_file_tugas" value="">

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="form_nip" class="required">Pilih Karyawan</label>
                <select name="nip" id="form_nip" required>
                    <option value="">-- Pilih Karyawan --</option>
                    <?php foreach ($listKaryawan as $k): ?>
                        <option value="<?= e($k['nip']); ?>" <?= $formData['nip'] === $k['nip'] ? 'selected' : ''; ?>>
                            <?= e($k['nama_karyawan']); ?> (<?= e($k['departemen']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="form_judul" class="required">Judul Tugas</label>
                <input type="text" name="judul" id="form_judul" value="<?= e($formData['judul']); ?>" placeholder="Cth: Perbaikan Bug Aplikasi" required>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="form_deskripsi" class="required">Deskripsi Tugas</label>
                <textarea name="deskripsi" id="form_deskripsi" rows="4" placeholder="Ketik rincian pekerjaan di sini..." required><?= e($formData['deskripsi']); ?></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="file_tugas">Upload Berkas / Lampiran Tugas dari Admin (Opsional):</label>
                <input type="file" name="file_tugas" id="file_tugas" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.rar" style="font-size: 0.85rem; width: 100%; padding: 8px; background: #fff; border: 1px solid #d1d5db; border-radius: 6px;">
                <small id="currentFileLabel" class="text-muted" style="display: block; margin-top: 4px; font-style: italic;"></small>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="form_status" class="required">Status Pekerjaan</label>
                <select name="status" id="form_status" required>
                    <?php foreach (STATUS_TASK_OPTIONS as $opt): ?>
                        <option value="<?= e($opt); ?>" <?= $formData['status'] === $opt ? 'selected' : ''; ?>><?= e($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-actions">
                <button type="submit" class="btn btn-primary btn-block" id="modalSubmitButton">Simpan Tugas</button>
                <button type="button" class="btn btn-outline btn-block" data-modal-close="taskModal">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const taskModal = document.getElementById('taskModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalSubmitButton = document.getElementById('modalSubmitButton');
    const taskForm = document.getElementById('taskForm');
    const fields = {
        id: document.getElementById('form_id'),
        nip: document.getElementById('form_nip'),
        judul: document.getElementById('form_judul'),
        deskripsi: document.getElementById('form_deskripsi'),
        status: document.getElementById('form_status'),
        current_file_tugas: document.getElementById('current_file_tugas'),
        file_tugas: document.getElementById('file_tugas'),
        currentFileLabel: document.getElementById('currentFileLabel')
    };

    function openModal(mode, data) {
        taskModal.classList.add('active');
        document.body.classList.add('modal-open');

        if (mode === 'edit') {
            modalTitle.textContent = 'Edit Tugas Karyawan';
            modalSubmitButton.textContent = 'Simpan Perubahan';
            fields.id.value = data.id || '';
            fields.nip.value = data.nip || '';
            fields.judul.value = data.judul || '';
            fields.deskripsi.value = data.deskripsi || '';
            fields.status.value = data.status || 'Belum Selesai';
            fields.current_file_tugas.value = data.fileTugas || '';
            fields.file_tugas.value = '';

            if (data.fileTugas) {
                fields.currentFileLabel.textContent = 'File saat ini: ' + data.fileTugas;
            } else {
                fields.currentFileLabel.textContent = 'Belum ada file terlampir.';
            }
        } else {
            modalTitle.textContent = 'Buat Tugas Baru';
            modalSubmitButton.textContent = 'Simpan Tugas';
            taskForm.reset();
            fields.id.value = '';
            fields.status.value = 'Belum Selesai';
            fields.current_file_tugas.value = '';
            fields.currentFileLabel.textContent = '';
        }
        setTimeout(() => fields.judul.focus(), 120);
    }

    function closeAllModals() {
        document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
        document.body.classList.remove('modal-open');
    }

    document.querySelectorAll('[data-modal-open]').forEach((button) => {
        button.addEventListener('click', function () {
            const targetModal = this.getAttribute('data-modal-open');
            
            if (targetModal === 'taskModal') {
                const data = {
                    id: this.dataset.id || '',
                    nip: this.dataset.nip || '',
                    judul: this.dataset.judul || '',
                    deskripsi: this.dataset.deskripsi || '',
                    status: this.dataset.status || 'Belum Selesai',
                    fileTugas: this.dataset.fileTugas || ''
                };
                const mode = this.getAttribute('data-edit') === '1' ? 'edit' : 'add';
                openModal(mode, data);
            } 
            else if (targetModal === 'detailModal') {
                document.getElementById('det_nama').textContent = this.dataset.nama;
                document.getElementById('det_judul').textContent = this.dataset.judul;
                document.getElementById('det_tanggal').textContent = this.dataset.tanggal;
                document.getElementById('det_deskripsi').textContent = this.dataset.deskripsi;
                document.getElementById('det_keterangan').textContent = this.dataset.keterangan;
                
                // 1. Catatan & Berkas Revisi Admin
                const revisiText = this.dataset.revisi;
                const fileRevisiName = this.dataset.fileRevisi;
                let revisiHtml = revisiText;
                if (fileRevisiName) {
                    revisiHtml += `<br><a href="../assets/uploads/revisi/${fileRevisiName}" target="_blank" style="color: #b45309; font-weight: 600; text-decoration: underline; margin-top: 6px; display: inline-block;">📎 Unduh Berkas Revisi (${fileRevisiName})</a>`;
                }
                document.getElementById('det_revisi').innerHTML = revisiHtml;
                
                // 2. Lampiran Berkas dari Admin
                const fileAdminWrapper = document.getElementById('det_file_admin_wrapper');
                const fileAdminName = this.dataset.fileAdmin;
                if (fileAdminName) {
                    fileAdminWrapper.innerHTML = `<a href="../assets/uploads/tugas/${fileAdminName}" target="_blank" style="color: #4f46e5; font-weight: 600; text-decoration: underline;">Unduh / Lihat Berkas Admin (${fileAdminName})</a>`;
                } else {
                    fileAdminWrapper.innerHTML = `<span style="color: #6b7280; font-style: italic;">Tidak ada berkas dari admin</span>`;
                }

                // 3. Lampiran Berkas Hasil Kerja Karyawan
                const fileKaryawanWrapper = document.getElementById('det_file_karyawan_wrapper');
                const fileKaryawanName = this.dataset.fileKaryawan;
                if (fileKaryawanName) {
                    fileKaryawanWrapper.innerHTML = `<a href="../assets/uploads/tugas/${fileKaryawanName}" target="_blank" style="color: #047857; font-weight: 600; text-decoration: underline;">Unduh / Lihat Laporan Karyawan (${fileKaryawanName})</a>`;
                } else {
                    fileKaryawanWrapper.innerHTML = `<span style="color: #6b7280; font-style: italic;">Belum ada berkas hasil kerja yang diunggah karyawan</span>`;
                }

                document.getElementById('detailModal').classList.add('active');
                document.body.classList.add('modal-open');
            }
            else if (targetModal === 'revisiModal') {
                document.getElementById('revisi_id').value = this.dataset.id;
                document.getElementById('revisi_info').value = this.dataset.nama + ' - ' + this.dataset.judul;
                document.getElementById('catatan_revisi').value = this.dataset.revisi !== 'Tidak ada catatan revisi' ? this.dataset.revisi : '';

                document.getElementById('revisiModal').classList.add('active');
                document.body.classList.add('modal-open');
            }
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', closeAllModals);
    });

    document.querySelectorAll('.modal-overlay').forEach((overlay) => {
        overlay.addEventListener('click', function (event) {
            if (event.target === this) {
                closeAllModals();
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });

    <?php if ($openModal): ?>
    document.body.classList.add('modal-open');
    <?php endif; ?>

    // Animasi Angka
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

    // Timeout Notifikasi Alert
    document.querySelectorAll('.alert-in').forEach((alertEl) => {
        setTimeout(() => {
            alertEl.classList.add('alert-out');
            setTimeout(() => alertEl.remove(), 400);
        }, 4000);
    });
})();
</script>

</body>
</html>