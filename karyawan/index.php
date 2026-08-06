<?php
session_start();

require_once "../config/koneksi.php";

if (!isset($conn) || $conn === false) {
    die("Koneksi database gagal.");
}

mysqli_set_charset($conn, 'utf8mb4');

// --- FITUR DOWNLOAD LAPORAN EXCEL (DIPINDAHKAN KE PALING ATAS) ---
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Bersihkan buffer output agar tidak ada spasi/HTML yang ikut terkirim ke file download
    if (ob_get_level()) {
        ob_end_clean();
    }

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Laporan_Data_Karyawan_" . date('Y-m-d') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    $exportQuery = mysqli_query($conn, "SELECT nip, nama_karyawan, email, jabatan, departemen, no_telp, tanggal_masuk, alamat, status FROM karyawan ORDER BY nama_karyawan ASC");
    
    echo "<table border='1'>";
    echo "<tr>
            <th>NIP</th>
            <th>Nama Karyawan</th>
            <th>Email</th>
            <th>Jabatan</th>
            <th>Departemen</th>
            <th>No. Telepon</th>
            <th>Tanggal Masuk</th>
            <th>Alamat</th>
            <th>Status</th>
          </tr>";
    
    if ($exportQuery) {
        while ($row = mysqli_fetch_assoc($exportQuery)) {
            echo "<tr>
                    <td>&nbsp;" . htmlspecialchars($row['nip']) . "</td>
                    <td>" . htmlspecialchars($row['nama_karyawan']) . "</td>
                    <td>" . htmlspecialchars($row['email']) . "</td>
                    <td>" . htmlspecialchars($row['jabatan']) . "</td>
                    <td>" . htmlspecialchars($row['departemen']) . "</td>
                    <td>&nbsp;" . htmlspecialchars($row['no_telp']) . "</td>
                    <td>" . htmlspecialchars($row['tanggal_masuk']) . "</td>
                    <td>" . htmlspecialchars($row['alamat']) . "</td>
                    <td>" . htmlspecialchars($row['status']) . "</td>
                  </tr>";
        }
    }
    echo "</table>";
    exit;
}

if (!isset($_SESSION['login'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Memastikan tabel karyawan memiliki struktur terbaru
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

// Fitur Auto-Update Kolom Database 
$newColumns = [
    'foto' => "VARCHAR(255) DEFAULT NULL AFTER status",
    'password' => "VARCHAR(255) NOT NULL AFTER email",
    'no_telp' => "VARCHAR(20) DEFAULT NULL AFTER departemen",
    'tanggal_masuk' => "DATE DEFAULT NULL AFTER no_telp",
    'alamat' => "TEXT DEFAULT NULL AFTER tanggal_masuk"
];

foreach ($newColumns as $col => $def) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM karyawan LIKE '$col'");
    if ($check && mysqli_num_rows($check) === 0) {
        mysqli_query($conn, "ALTER TABLE karyawan ADD COLUMN $col $def");
    }
}

// Pastikan tabel task memiliki kolom revisi
$checkTaskRev = mysqli_query($conn, "SHOW COLUMNS FROM task LIKE 'catatan_revisi'");
if ($checkTaskRev && mysqli_num_rows($checkTaskRev) === 0) {
    mysqli_query($conn, "ALTER TABLE task ADD COLUMN catatan_revisi TEXT DEFAULT NULL");
}
$checkTaskRevFile = mysqli_query($conn, "SHOW COLUMNS FROM task LIKE 'file_revisi'");
if ($checkTaskRevFile && mysqli_num_rows($checkTaskRevFile) === 0) {
    mysqli_query($conn, "ALTER TABLE task ADD COLUMN file_revisi VARCHAR(255) DEFAULT NULL AFTER catatan_revisi");
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function buildUploadPath(string $filename): string
{
    return __DIR__ . '/../assets/uploads/karyawan/' . $filename;
}

function deletePhotoFile(?string $filename): void
{
    if (!$filename) {
        return;
    }

    $fullPath = buildUploadPath($filename);
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function handlePhotoUpload(array $file, ?string &$errorMessage): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = 'Upload foto gagal, silakan coba lagi.';
        return null;
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $errorMessage = 'File upload tidak valid.';
        return null;
    }

    if (($file['size'] ?? 0) > (2 * 1024 * 1024)) {
        $errorMessage = 'Ukuran foto maksimal 2MB.';
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMimeMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimeMap[$mime])) {
        $errorMessage = 'Format foto harus JPG, PNG, atau WEBP.';
        return null;
    }

    $ext = $allowedMimeMap[$mime];
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetPath = buildUploadPath($safeName);

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $errorMessage = 'Gagal menyimpan file foto.';
        return null;
    }

    return $safeName;
}

$activeNav = 'karyawan';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function csrfValid(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

const STATUS_OPTIONS = ['Aktif', 'Nonaktif', 'Cuti'];

$successMessage = '';
$errorMessage = '';
$isEditing = false;
$formData = [
    'old_nip' => '', 'nip' => '', 'nama_karyawan' => '', 'email' => '', 'password' => '',
    'jabatan' => '', 'departemen' => '', 'no_telp' => '', 'tanggal_masuk' => '', 'alamat' => '',
    'status' => 'Aktif', 'foto' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        $errorMessage = 'Sesi tidak valid, silakan muat ulang halaman dan coba lagi.';
    } else {
        $action = $_POST['action'] ?? '';

        // AKSI: SIMPAN / UPDATE KARYAWAN
        if ($action === 'save') {
            $formData['old_nip'] = trim($_POST['old_nip'] ?? '');
            $formData['nip'] = trim($_POST['nip'] ?? '');
            $formData['nama_karyawan'] = trim($_POST['nama_karyawan'] ?? '');
            $formData['email'] = trim($_POST['email'] ?? '');
            $rawPassword = trim($_POST['password'] ?? ''); 
            $formData['jabatan'] = trim($_POST['jabatan'] ?? '');
            $formData['departemen'] = trim($_POST['departemen'] ?? '');
            $formData['no_telp'] = trim($_POST['no_telp'] ?? '');
            $formData['tanggal_masuk'] = trim($_POST['tanggal_masuk'] ?? '');
            $formData['alamat'] = trim($_POST['alamat'] ?? '');
            $formData['status'] = trim($_POST['status'] ?? 'Aktif');
            $formData['foto'] = trim($_POST['current_foto'] ?? '');
            $removeFoto = isset($_POST['remove_foto']) && $_POST['remove_foto'] === '1';

            $newPhotoName = handlePhotoUpload($_FILES['foto'] ?? [], $errorMessage);
            if ($errorMessage !== '') {
                $newPhotoName = null;
            }

            if (!in_array($formData['status'], STATUS_OPTIONS, true)) {
                $formData['status'] = 'Aktif';
            }

            if ($formData['nip'] === '' || $formData['nama_karyawan'] === '' || $formData['email'] === '' || $formData['jabatan'] === '' || $formData['departemen'] === '') {
                $errorMessage = 'Semua field wajib yang bertanda bintang (*) harus diisi.';
            } elseif ($formData['old_nip'] === '' && $rawPassword === '') {
                $errorMessage = 'Password wajib diisi untuk karyawan baru.';
            } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                $errorMessage = 'Format email tidak valid.';
            } else {
                if ($formData['old_nip'] === '') {
                    $checkStmt = mysqli_prepare($conn, 'SELECT nip FROM karyawan WHERE nip = ? OR email = ? LIMIT 1');
                    mysqli_stmt_bind_param($checkStmt, 'ss', $formData['nip'], $formData['email']);
                    mysqli_stmt_execute($checkStmt);
                    mysqli_stmt_store_result($checkStmt);

                    if (mysqli_stmt_num_rows($checkStmt) > 0) {
                        $errorMessage = 'NIP atau email sudah digunakan.';
                    } else {
                        $fotoToSave = $newPhotoName;
                        $hashedPassword = password_hash($rawPassword, PASSWORD_DEFAULT);
                        
                        $stmt = mysqli_prepare($conn, 'INSERT INTO karyawan (nip, nama_karyawan, email, password, jabatan, departemen, no_telp, tanggal_masuk, alamat, status, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                        mysqli_stmt_bind_param($stmt, 'sssssssssss', $formData['nip'], $formData['nama_karyawan'], $formData['email'], $hashedPassword, $formData['jabatan'], $formData['departemen'], $formData['no_telp'], $formData['tanggal_masuk'], $formData['alamat'], $formData['status'], $fotoToSave);

                        if (mysqli_stmt_execute($stmt)) {
                            $successMessage = 'Data karyawan berhasil ditambahkan.';
                            $formData = [
                                'old_nip' => '', 'nip' => '', 'nama_karyawan' => '', 'email' => '', 'password' => '',
                                'jabatan' => '', 'departemen' => '', 'no_telp' => '', 'tanggal_masuk' => '', 'alamat' => '',
                                'status' => 'Aktif', 'foto' => '',
                            ];
                        } else {
                            $errorMessage = 'Gagal menambahkan data karyawan.';
                            deletePhotoFile($newPhotoName);
                        }
                        mysqli_stmt_close($stmt);
                    }
                    mysqli_stmt_close($checkStmt);

                } else {
                    $currentStmt = mysqli_prepare($conn, 'SELECT foto FROM karyawan WHERE nip = ? LIMIT 1');
                    mysqli_stmt_bind_param($currentStmt, 's', $formData['old_nip']);
                    mysqli_stmt_execute($currentStmt);
                    $currentResult = mysqli_stmt_get_result($currentStmt);
                    $existingRow = $currentResult ? mysqli_fetch_assoc($currentResult) : null;
                    $oldPhotoName = $existingRow['foto'] ?? '';
                    mysqli_stmt_close($currentStmt);

                    $checkStmt = mysqli_prepare($conn, 'SELECT nip FROM karyawan WHERE (nip = ? OR email = ?) AND nip <> ? LIMIT 1');
                    mysqli_stmt_bind_param($checkStmt, 'sss', $formData['nip'], $formData['email'], $formData['old_nip']);
                    mysqli_stmt_execute($checkStmt);
                    mysqli_stmt_store_result($checkStmt);

                    if (mysqli_stmt_num_rows($checkStmt) > 0) {
                        $errorMessage = 'NIP atau email sudah digunakan oleh data lain.';
                        deletePhotoFile($newPhotoName);
                    } else {
                        $photoToSave = $oldPhotoName;
                        if ($removeFoto) {
                            $photoToSave = null;
                        }
                        if ($newPhotoName !== null) {
                            $photoToSave = $newPhotoName;
                        }

                        if ($rawPassword !== '') {
                            $hashedPassword = password_hash($rawPassword, PASSWORD_DEFAULT);
                            $stmt = mysqli_prepare($conn, 'UPDATE karyawan SET nip = ?, nama_karyawan = ?, email = ?, password = ?, jabatan = ?, departemen = ?, no_telp = ?, tanggal_masuk = ?, alamat = ?, status = ?, foto = ? WHERE nip = ?');
                            mysqli_stmt_bind_param($stmt, 'ssssssssssss', $formData['nip'], $formData['nama_karyawan'], $formData['email'], $hashedPassword, $formData['jabatan'], $formData['departemen'], $formData['no_telp'], $formData['tanggal_masuk'], $formData['alamat'], $formData['status'], $photoToSave, $formData['old_nip']);
                        } else {
                            $stmt = mysqli_prepare($conn, 'UPDATE karyawan SET nip = ?, nama_karyawan = ?, email = ?, jabatan = ?, departemen = ?, no_telp = ?, tanggal_masuk = ?, alamat = ?, status = ?, foto = ? WHERE nip = ?');
                            mysqli_stmt_bind_param($stmt, 'sssssssssss', $formData['nip'], $formData['nama_karyawan'], $formData['email'], $formData['jabatan'], $formData['departemen'], $formData['no_telp'], $formData['tanggal_masuk'], $formData['alamat'], $formData['status'], $photoToSave, $formData['old_nip']);
                        }

                        if (mysqli_stmt_execute($stmt)) {
                            if ($newPhotoName !== null && $oldPhotoName !== '') {
                                deletePhotoFile($oldPhotoName);
                            }
                            if ($removeFoto && $oldPhotoName !== '' && $newPhotoName === null) {
                                deletePhotoFile($oldPhotoName);
                            }

                            $successMessage = 'Data karyawan berhasil diperbarui.';
                            $formData = [
                                'old_nip' => '', 'nip' => '', 'nama_karyawan' => '', 'email' => '', 'password' => '',
                                'jabatan' => '', 'departemen' => '', 'no_telp' => '', 'tanggal_masuk' => '', 'alamat' => '',
                                'status' => 'Aktif', 'foto' => '',
                            ];
                            $isEditing = false;
                        } else {
                            $errorMessage = 'Gagal memperbarui data karyawan.';
                            deletePhotoFile($newPhotoName);
                        }
                        mysqli_stmt_close($stmt);
                    }
                    mysqli_stmt_close($checkStmt);
                }
            }
        }

        // AKSI: HAPUS KARYAWAN
        if ($action === 'delete') {
            $deleteNip = trim($_POST['nip'] ?? '');
            if ($deleteNip !== '') {
                $photoToDelete = '';
                $photoStmt = mysqli_prepare($conn, 'SELECT foto FROM karyawan WHERE nip = ? LIMIT 1');
                mysqli_stmt_bind_param($photoStmt, 's', $deleteNip);
                mysqli_stmt_execute($photoStmt);
                $photoResult = mysqli_stmt_get_result($photoStmt);
                if ($photoResult && ($photoRow = mysqli_fetch_assoc($photoResult))) {
                    $photoToDelete = (string) ($photoRow['foto'] ?? '');
                }
                mysqli_stmt_close($photoStmt);

                $stmtTugas = mysqli_prepare($conn, 'DELETE FROM task WHERE nip = ?');
                mysqli_stmt_bind_param($stmtTugas, 's', $deleteNip);
                mysqli_stmt_execute($stmtTugas);
                mysqli_stmt_close($stmtTugas);

                $stmt = mysqli_prepare($conn, 'DELETE FROM karyawan WHERE nip = ?');
                mysqli_stmt_bind_param($stmt, 's', $deleteNip);

                if (mysqli_stmt_execute($stmt)) {
                    $successMessage = 'Data karyawan berhasil dihapus.';
                    if ($photoToDelete !== '') {
                        deletePhotoFile($photoToDelete);
                    }
                } else {
                    $errorMessage = 'Gagal menghapus data karyawan.';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// AMBIL SEMUA DATA TUGAS DARI TABEL 'task'
$tugasQuery = mysqli_query($conn, "SELECT id, nip, judul, deskripsi, status, file_tugas, keterangan_karyawan, catatan_revisi, file_revisi, DATE_FORMAT(created_at, '%d-%m-%Y') as tgl FROM task ORDER BY created_at DESC");
$allTugas = [];
if ($tugasQuery) {
    while ($t = mysqli_fetch_assoc($tugasQuery)) {
        $allTugas[$t['nip']][] = $t;
    }
}

$search = trim($_GET['q'] ?? '');
$karyawanList = [];

$queryStr = 'SELECT nip, nama_karyawan, email, jabatan, departemen, no_telp, tanggal_masuk, alamat, status, foto FROM karyawan';
if ($search !== '') {
    $likeSearch = '%' . $search . '%';
    $queryStr .= ' WHERE nip LIKE ? OR nama_karyawan LIKE ? OR email LIKE ? OR jabatan LIKE ? OR departemen LIKE ? OR status LIKE ? ORDER BY nama_karyawan ASC';
    $stmt = mysqli_prepare($conn, $queryStr);
    mysqli_stmt_bind_param($stmt, 'ssssss', $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $queryStr .= ' ORDER BY nama_karyawan ASC';
    $result = mysqli_query($conn, $queryStr);
}

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $karyawanList[] = $row;
    }
}

// --- PENGHITUNGAN STATISTIK KARYAWAN ---
$qTotal = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM karyawan');
$totalKaryawan = (int) (mysqli_fetch_assoc($qTotal)['total'] ?? 0);

$qAktif = mysqli_query($conn, "SELECT COUNT(*) AS total FROM karyawan WHERE status = 'Aktif'");
$totalAktif = (int) (mysqli_fetch_assoc($qAktif)['total'] ?? 0);

$qCuti = mysqli_query($conn, "SELECT COUNT(*) AS total FROM karyawan WHERE status = 'Cuti'");
$totalCuti = (int) (mysqli_fetch_assoc($qCuti)['total'] ?? 0);

$qNonaktif = mysqli_query($conn, "SELECT COUNT(*) AS total FROM karyawan WHERE status = 'Nonaktif'");
$totalNonaktif = (int) (mysqli_fetch_assoc($qNonaktif)['total'] ?? 0);

$formTitle = $isEditing ? 'Edit Data Karyawan' : 'Tambah Karyawan';
$formButton = $isEditing ? 'Simpan Perubahan' : 'Simpan Karyawan';
$openModal = $isEditing || (!empty($errorMessage) && $_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Karyawan & Tugas</title>
    <link rel="stylesheet" href="../assets/css/dasboard.css">
    <style>
        .form-group input[type="date"], .form-group textarea {
            width: 100%; padding: 10px 14px; border: 1px solid var(--border-color, #e5e7eb); border-radius: 8px; font-size: 0.9rem; color: var(--text-main, #111827); outline: none; box-sizing: border-box; background: #fff; font-family: inherit;
        }
        .form-group input[type="date"]:focus, .form-group textarea:focus { border-color: var(--primary-color, #4f46e5); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        
        .btn-icon-only {
            display: inline-flex; align-items: center; justify-content: center; padding: 4px; width: 34px; height: 34px; border-radius: 8px; border: none !important; background: transparent !important; cursor: pointer; transition: transform 0.1s ease, color 0.2s ease;
        }
        .btn-icon-only svg { width: 18px; height: 18px; pointer-events: none; }

        .btn-info.btn-icon-only { color: #0ea5e9; }
        .btn-info.btn-icon-only:hover { color: #0284c7; transform: scale(1.15); }
        
        .btn-edit.btn-icon-only { color: #4f46e5; }
        .btn-edit.btn-icon-only:hover { color: #4338ca; transform: scale(1.15); }

        .btn-delete.btn-icon-only { color: #ef4444; }
        .btn-delete.btn-icon-only:hover { color: #dc2626; transform: scale(1.15); }

        .btn-warning.btn-icon-only { color: #f59e0b; }
        .btn-warning.btn-icon-only:hover { color: #d97706; transform: scale(1.15); }
        
        .required::after { content: " *"; color: #ef4444; font-weight: bold; }

        .detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px 16px; margin-top: 16px; background: #f9fafb; padding: 24px; border-radius: 12px; border: 1px solid #e5e7eb; }
        .detail-group { display: flex; flex-direction: column; gap: 6px; }
        .detail-group.full-width { grid-column: 1 / -1; }
        .detail-label { font-size: 0.75rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .detail-value { font-size: 0.95rem; color: #111827; font-weight: 500; word-break: break-word; white-space: pre-wrap; }

        .tugas-list-item { background: #ffffff; border: 1px solid #e5e7eb; padding: 14px; border-radius: 8px; margin-bottom: 10px; }
        .tugas-list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .tugas-list-date { font-size: 0.75rem; color: #6b7280; }
        
        .badge-selesai { background-color: rgba(16, 185, 129, 0.12) !important; color: #047857 !important; }
        .badge-proses { background-color: rgba(245, 158, 11, 0.14) !important; color: #b45309 !important; }
        .badge-belumselesai { background-color: rgba(239, 68, 68, 0.12) !important; color: #b91c1c !important; }

        /* Grid Statistik 4 Kolom */
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
    </style>
</head>
<body class="dashboard-page">

<div class="dashboard-shell">
    <?php require_once "../layouts/navbar.php"; ?>

    <main class="dashboard-main">
        <section class="hero-card reveal" style="--delay:.05s">
            <div>
                <p class="eyebrow">Manajemen Data</p>
                <h2>Data Karyawan</h2>
                <p>Kelola data karyawan, pantau status kepegawaian, dan unduh laporan lengkap.</p>
            </div>
            <div class="hero-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="btn btn-primary btn-icon" data-modal-open="employeeModal">
                    <span class="icon-plus">+</span>
                    <span>Tambah Karyawan</span>
                </button>
                <!-- TOMBOL DOWNLOAD LAPORAN -->
                <a href="?export=excel" class="btn btn-success" style="background: #10b981; color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Download Laporan</span>
                </a>
                <div class="hero-search" style="width: 100%; margin-top: 8px;">
                    <form method="GET" action="" class="search-form search-form-hero">
                        <input type="text" name="q" value="<?= e($search); ?>" placeholder="Cari nip, nama, email, jabatan...">
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
                <h3>Total Karyawan</h3>
                <p class="stat-number" data-count="<?= (int) $totalKaryawan; ?>">0</p>
                <small class="text-muted">Keseluruhan Pegawai</small>
            </div>
            <div class="stat-card success reveal" style="--delay:.14s">
                <h3>Karyawan Aktif</h3>
                <p class="stat-number" data-count="<?= (int) $totalAktif; ?>">0</p>
                <small class="text-muted">Pegawai Bertugas</small>
            </div>
            <div class="stat-card warning reveal" style="--delay:.18s">
                <h3>Karyawan Cuti</h3>
                <p class="stat-number" data-count="<?= (int) $totalCuti; ?>">0</p>
                <small class="text-muted">Sedang Mengambil Cuti</small>
            </div>
            <div class="stat-card danger reveal" style="--delay:.22s">
                <h3>Karyawan Nonaktif</h3>
                <p class="stat-number" data-count="<?= (int) $totalNonaktif; ?>">0</p>
                <small class="text-muted">Status Nonaktif</small>
            </div>
        </section>

        <?php if ($successMessage): ?><div class="alert alert-success alert-in"><?= e($successMessage); ?></div><?php endif; ?>
        <?php if ($errorMessage): ?><div class="alert alert-error alert-in"><?= e($errorMessage); ?></div><?php endif; ?>

        <section class="table-section card reveal" style="--delay:.28s">
            <div class="card-header flex-between table-header">
                <div><h2>Data Karyawan</h2><p class="text-muted">Klik ikon untuk melihat detail, edit, atau hapus data.</p></div>
                <span class="table-count"><?= number_format(count($karyawanList)); ?> data</span>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Foto</th><th>NIP</th><th>Nama Karyawan</th><th>Email</th><th>Jabatan</th><th>Departemen</th><th>Status</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($karyawanList)): ?>
                            <?php foreach ($karyawanList as $i => $k): ?>
                                <?php 
                                    $tugasKaryawanJSON = htmlspecialchars(json_encode($allTugas[$k['nip']] ?? []), ENT_QUOTES, 'UTF-8'); 
                                ?>
                                <tr class="row-in" style="--row-delay:<?= min($i, 12) * 0.035; ?>s">
                                    <td data-label="Foto">
                                        <?php if (!empty($k['foto'])): ?>
                                            <img src="../assets/uploads/karyawan/<?= e($k['foto']); ?>" alt="Foto" class="avatar-photo">
                                        <?php else: ?>
                                            <span class="avatar-fallback">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="NIP"><?= e($k['nip']); ?></td>
                                    <td data-label="Nama Karyawan"><strong><?= e($k['nama_karyawan']); ?></strong></td>
                                    <td data-label="Email"><?= e($k['email']); ?></td>
                                    <td data-label="Jabatan"><?= e($k['jabatan']); ?></td>
                                    <td data-label="Departemen"><small class="text-muted"><?= e($k['departemen']); ?></small></td>
                                    <td data-label="Status">
                                        <span class="badge badge-<?= e(strtolower($k['status'])); ?>"><?= e($k['status']); ?></span>
                                    </td>
                                    <td data-label="Aksi" class="action-buttons" style="display:flex; gap:4px; align-items:center;">
                                        <!-- Tombol Lihat Detail -->
                                        <button type="button" class="btn-sm btn-info btn-icon-only" title="Lihat Detail & Tugas" data-modal-open="detailModal"
                                            data-nip="<?= e($k['nip']); ?>" data-nama_karyawan="<?= e($k['nama_karyawan']); ?>" data-email="<?= e($k['email']); ?>" data-jabatan="<?= e($k['jabatan']); ?>" data-departemen="<?= e($k['departemen']); ?>" data-no_telp="<?= e($k['no_telp'] ?? '-'); ?>" data-tanggal_masuk="<?= e($k['tanggal_masuk'] ?? '-'); ?>" data-alamat="<?= e($k['alamat'] ?? '-'); ?>" data-status="<?= e($k['status']); ?>" data-foto="<?= e($k['foto'] ?? ''); ?>" data-tugas="<?= $tugasKaryawanJSON; ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </button>

                                        <!-- Tombol Edit Data -->
                                        <button type="button" class="btn-sm btn-edit btn-icon-only" title="Edit Data" data-edit="1" data-modal-open="employeeModal"
                                            data-nip="<?= e($k['nip']); ?>" data-nama_karyawan="<?= e($k['nama_karyawan']); ?>" data-email="<?= e($k['email']); ?>" data-jabatan="<?= e($k['jabatan']); ?>" data-departemen="<?= e($k['departemen']); ?>" data-no_telp="<?= e($k['no_telp'] ?? ''); ?>" data-tanggal_masuk="<?= e($k['tanggal_masuk'] ?? ''); ?>" data-alamat="<?= e($k['alamat'] ?? ''); ?>" data-status="<?= e($k['status']); ?>" data-foto="<?= e($k['foto'] ?? ''); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                        </button>
                                        
                                        <!-- Tombol Hapus Data -->
                                        <form method="POST" action="" onsubmit="return confirm('Yakin ingin menghapus data ini?');" style="display:inline; margin:0; padding:0;">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="nip" value="<?= e($k['nip']); ?>">
                                            <button type="submit" class="btn-sm btn-delete btn-icon-only" title="Hapus"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center empty-state">Data tidak ditemukan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<!-- ===================================== -->
<!-- MODAL DETAIL / RIWAYAT TUGAS          -->
<!-- ===================================== -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-content" style="max-width: 750px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <div><h2>Profil & Riwayat Tugas</h2><p class="modal-subtitle">Informasi lengkap data karyawan dan status tugas.</p></div>
            <button type="button" class="close-btn" data-modal-close="detailModal">&times;</button>
        </div>
        
        <div class="detail-body">
            <div style="text-align: center; margin-bottom: 20px;">
                <img id="det_foto" src="" alt="Foto" style="width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 3px solid #e5e7eb; display: none;">
                <div id="det_foto_fallback" style="width: 110px; height: 110px; border-radius: 50%; background: #f3f4f6; color: #9ca3af; display: flex; align-items: center; justify-content: center; margin: 0 auto; border: 1px dashed #d1d5db; font-size: 1.5rem; font-weight: bold;">N/A</div>
            </div>
            
            <div class="detail-grid">
                <div class="detail-group"><span class="detail-label">NIP</span><span class="detail-value" id="det_nip"></span></div>
                <div class="detail-group"><span class="detail-label">Nama Lengkap</span><span class="detail-value" id="det_nama" style="font-weight: 700;"></span></div>
                <div class="detail-group"><span class="detail-label">Email</span><span class="detail-value" id="det_email"></span></div>
                <div class="detail-group"><span class="detail-label">No. Telepon</span><span class="detail-value" id="det_notelp"></span></div>
                <div class="detail-group"><span class="detail-label">Jabatan</span><span class="detail-value" id="det_jabatan"></span></div>
                <div class="detail-group"><span class="detail-label">Departemen</span><span class="detail-value" id="det_departemen"></span></div>
                <div class="detail-group"><span class="detail-label">Tanggal Masuk</span><span class="detail-value" id="det_tgl"></span></div>
                <div class="detail-group"><span class="detail-label">Status</span><div class="detail-value"><span id="det_status" class="badge"></span></div></div>
                <div class="detail-group full-width"><span class="detail-label">Alamat Lengkap</span><span class="detail-value" id="det_alamat"></span></div>
            </div>

            <div style="margin-top: 24px; border-top: 2px dashed #e5e7eb; padding-top: 20px;">
                <h3 style="font-size: 1.1rem; color: #111827; margin-bottom: 12px; font-weight: 700;">Daftar Tugas Karyawan</h3>
                <div id="det_tugas_list"></div>
            </div>
        </div>

        <div class="modal-actions" style="margin-top: 30px;"><button type="button" class="btn btn-outline btn-block" data-modal-close="detailModal">Tutup Jendela</button></div>
    </div>
</div>

<!-- ===================================== -->
<!-- MODAL TAMBAH / EDIT KARYAWAN          -->
<!-- ===================================== -->
<div class="modal-overlay <?= $openModal ? 'active' : ''; ?>" id="employeeModal">
    <div class="modal-content">
        <div class="modal-header">
            <div><h2 id="modalTitle"><?= e($formTitle); ?></h2><p class="modal-subtitle">Form data karyawan.</p></div>
            <button type="button" class="close-btn" data-modal-close="employeeModal">&times;</button>
        </div>
        <form method="POST" action="" id="employeeForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="old_nip" id="old_nip" value="<?= e($formData['old_nip']); ?>">
            <input type="hidden" name="current_foto" id="current_foto" value="<?= e($formData['foto']); ?>">

            <div class="modal-form-grid">
                <div class="form-group"><label class="required">NIP</label><input type="text" name="nip" id="nip" required></div>
                <div class="form-group"><label class="required">Nama Lengkap</label><input type="text" name="nama_karyawan" id="nama_karyawan" required></div>
                <div class="form-group"><label class="required">Email</label><input type="email" name="email" id="email" required></div>
                <div class="form-group"><label id="labelPassword" class="required">Password</label><input type="password" name="password" id="password" placeholder="Min 6 karakter"></div>
                <div class="form-group"><label class="required">Jabatan</label><input type="text" name="jabatan" id="jabatan" required></div>
                <div class="form-group"><label class="required">Departemen</label><input type="text" name="departemen" id="departemen" required></div>
                <div class="form-group"><label>No. Telepon</label><input type="text" name="no_telp" id="no_telp"></div>
                <div class="form-group"><label>Tanggal Masuk</label><input type="date" name="tanggal_masuk" id="tanggal_masuk"></div>
                <div class="form-group"><label class="required">Status</label><select name="status" id="status" required><?php foreach (STATUS_OPTIONS as $opt): ?><option value="<?= e($opt); ?>"><?= e($opt); ?></option><?php endforeach; ?></select></div>
                <div class="form-group form-group-full"><label>Alamat</label><textarea name="alamat" id="alamat" rows="2"></textarea></div>
                <div class="form-group form-group-full"><label>Foto</label><input type="file" name="foto" id="foto" accept="image/*"></div>
            </div>
            <div class="modal-actions"><button type="submit" class="btn btn-primary btn-block" id="modalSubmitButton"><?= e($formButton); ?></button></div>
        </form>
    </div>
</div>

<script>
(function () {
    function closeAllModals() {
        document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
        document.body.classList.remove('modal-open');
    }

    document.querySelectorAll('[data-modal-open]').forEach((button) => {
        button.addEventListener('click', function () {
            const target = this.getAttribute('data-modal-open');
            document.body.classList.add('modal-open');

            if (target === 'employeeModal') {
                document.getElementById('employeeModal').classList.add('active');
                if (this.getAttribute('data-edit') === '1') {
                    document.getElementById('modalTitle').textContent = 'Edit Data Karyawan';
                    document.getElementById('old_nip').value = this.dataset.nip;
                    document.getElementById('nip').value = this.dataset.nip;
                    document.getElementById('nama_karyawan').value = this.dataset.nama_karyawan;
                    document.getElementById('email').value = this.dataset.email;
                    document.getElementById('jabatan').value = this.dataset.jabatan;
                    document.getElementById('departemen').value = this.dataset.departemen;
                    document.getElementById('no_telp').value = this.dataset.no_telp;
                    document.getElementById('tanggal_masuk').value = this.dataset.tanggal_masuk;
                    document.getElementById('alamat').value = this.dataset.alamat;
                    document.getElementById('status').value = this.dataset.status;
                    document.getElementById('current_foto').value = this.dataset.foto;
                } else {
                    document.getElementById('employeeForm').reset();
                    document.getElementById('modalTitle').textContent = 'Tambah Karyawan';
                    document.getElementById('old_nip').value = '';
                }
            } else if (target === 'detailModal') {
                document.getElementById('detailModal').classList.add('active');
                document.getElementById('det_nip').textContent = this.dataset.nip;
                document.getElementById('det_nama').textContent = this.dataset.nama_karyawan;
                document.getElementById('det_email').textContent = this.dataset.email;
                document.getElementById('det_notelp').textContent = this.dataset.no_telp;
                document.getElementById('det_jabatan').textContent = this.dataset.jabatan;
                document.getElementById('det_departemen').textContent = this.dataset.departemen;
                document.getElementById('det_tgl').textContent = this.dataset.tanggal_masuk;
                document.getElementById('det_alamat').textContent = this.dataset.alamat;
                
                const statEl = document.getElementById('det_status');
                statEl.textContent = this.dataset.status;
                statEl.className = 'badge badge-' + this.dataset.status.toLowerCase();

                const foto = this.dataset.foto;
                if (foto) {
                    document.getElementById('det_foto').src = '../assets/uploads/karyawan/' + foto;
                    document.getElementById('det_foto').style.display = 'inline-block';
                    document.getElementById('det_foto_fallback').style.display = 'none';
                } else {
                    document.getElementById('det_foto').style.display = 'none';
                    document.getElementById('det_foto_fallback').style.display = 'flex';
                }

                // Render Tugas
                const tugasData = JSON.parse(this.dataset.tugas || '[]');
                const listEl = document.getElementById('det_tugas_list');
                listEl.innerHTML = '';

                if (tugasData.length === 0) {
                    listEl.innerHTML = '<p style="font-size: 0.85rem; color: #6b7280; font-style: italic; background:#f9fafb; padding:12px; border-radius:8px; border:1px solid #e5e7eb;">Belum ada tugas.</p>';
                } else {
                    tugasData.forEach(t => {
                        const div = document.createElement('div');
                        div.className = 'tugas-list-item';
                        const badgeClass = t.status.replace(/\s+/g, '').toLowerCase();
                        div.innerHTML = `
                            <div class="tugas-list-header">
                                <strong style="font-size: 0.95rem; color: #111827;">${t.judul}</strong>
                                <span class="badge badge-${badgeClass}" style="font-size: 10px; padding: 2px 6px;">${t.status}</span>
                            </div>
                            <div style="font-size: 0.85rem; color: #4b5563;">${t.deskripsi}</div>
                        `;
                        listEl.appendChild(div);
                    });
                }
            }
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(b => b.addEventListener('click', closeAllModals));
    document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if (e.target === o) closeAllModals(); }));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAllModals(); });

    // Animasi Angka
    document.querySelectorAll('.stat-number[data-count]').forEach((el) => {
        const target = parseInt(el.dataset.count, 10) || 0;
        const duration = 700;
        const start = performance.now();

        function tick(now) {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(target * eased).toLocaleString('id-ID');
            if (progress < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    });
})();
</script>
    <?php require_once "../layouts/footer.php"; ?>

</body>
</html>