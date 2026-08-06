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
$activeNav = 'tugas_saya'; // Sesuai dengan penanda menu di navbar

$successMessage = '';
$errorMessage = '';

// --- PROSES KUMPULKAN / UPDATE TUGAS OLEH KARYAWAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kumpul_tugas') {
    $task_id = $_POST['task_id'] ?? '';
    $new_status = $_POST['new_status'] ?? 'Proses';
    $keterangan_karyawan = trim($_POST['keterangan_karyawan'] ?? '');
    $file_name = null;
    
    $valid_statuses = ['Belum Selesai', 'Proses', 'Selesai'];
    if (in_array($new_status, $valid_statuses) && !empty($task_id)) {
        
        // Proses Upload File Hasil Kerja Karyawan
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
                        @unlink($upload_dir . $old['file_tugas_karyawan']);
                    }
                }
                mysqli_stmt_close($stmt_old);

                move_uploaded_file($_FILES['file_tugas_karyawan']['tmp_name'], $upload_dir . $file_name);
            } else {
                $errorMessage = 'Format file tidak didukung atau ukuran melebihi 5MB.';
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
        
        if (mysqli_stmt_execute($stmt_upd)) {
            $successMessage = 'Tugas berhasil dikumpulkan.';
        } else {
            $errorMessage = 'Gagal menyimpan pengumpulan tugas.';
        }
        mysqli_stmt_close($stmt_upd);
    }
}

// --- AMBIL DATA TUGAS YANG HARUS DIKERJAKAN (Belum Selesai / Proses) ---
$tasksToWork = [];
$stmt_task = mysqli_prepare($conn, "SELECT id, judul, deskripsi, status, file_tugas, file_tugas_karyawan, keterangan_karyawan, catatan_revisi, file_revisi, DATE_FORMAT(created_at, '%d %b %Y') as tanggal_dibuat FROM task WHERE nip = ? AND status != 'Selesai' ORDER BY created_at DESC");
mysqli_stmt_bind_param($stmt_task, "s", $nip_login);
mysqli_stmt_execute($stmt_task);
$result_task = mysqli_stmt_get_result($stmt_task);

while ($row = mysqli_fetch_assoc($result_task)) {
    $tasksToWork[] = $row;
}
mysqli_stmt_close($stmt_task);
$totalToWork = count($tasksToWork);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tugas Saya - Sistem Data Karyawan</title>
    <link rel="stylesheet" href="../assets/css/dasboard.css">
    <style>
        .portal-container { width: 100%; margin: 0 auto; padding: 0; box-sizing: border-box; }
        
        .section-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .section-icon { background: #3b82f6; color: white; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .section-icon svg { width: 18px; height: 18px; }
        .section-title { font-size: 1.2rem; font-weight: 800; color: #111827; margin: 0; }

        .task-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
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

        /* Kotak Revisi */
        .revisi-alert-box { background-color: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; padding: 14px 16px; border-radius: 4px 8px 8px 4px; margin-bottom: 20px; }
        .revisi-alert-title { font-size: 0.8rem; font-weight: 700; color: #b45309; text-transform: uppercase; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
        .revisi-alert-content { font-size: 0.95rem; color: #92400e; line-height: 1.5; white-space: pre-wrap; margin-bottom: 12px; }
        .revisi-file-btn { display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600; color: #92400e; background: #fef3c7; padding: 8px 14px; border-radius: 6px; text-decoration: none; border: 1px solid #fcd34d; transition: background 0.2s; }
        .revisi-file-btn:hover { background: #fde68a; }

        /* Informasi Tugas yang sudah dikumpul */
        .submission-status-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .badge-proses { background-color: rgba(245, 158, 11, 0.14) !important; color: #b45309 !important; }
        .badge-belumselesai { background-color: rgba(239, 68, 68, 0.12) !important; color: #b91c1c !important; }
    </style>
</head>
<body class="dashboard-page">
<div class="dashboard-shell">
    <?php require_once "../layouts/navbar.php"; ?>
    <main class="dashboard-main portal-container reveal" style="--delay:.05s">
        
        <!-- HEADER HALAMAN -->
        <section class="hero-card">
            <div>
                <p class="eyebrow">DAFTAR PENUGASAN</p>
                <h2>Tugas yang Harus Dikerjakan</h2>
                <p>Berikut adalah daftar tugas aktif yang dikirimkan oleh admin untuk segera Anda selesaikan dan kumpulkan.</p>
            </div>
        </section>

        <?php if ($successMessage): ?><div class="alert alert-success alert-in"><?= e($successMessage); ?></div><?php endif; ?>
        <?php if ($errorMessage): ?><div class="alert alert-error alert-in"><?= e($errorMessage); ?></div><?php endif; ?>

        <!-- KONTEN DAFTAR TUGAS -->
        <div class="reveal" style="--delay:.15s">
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

                        <!-- STATUS & TOMBOL BUKA POPUP PENGUMPULAN TUGAS -->
                        <div class="submission-status-box">
                            <div>
                                <span style="font-size: 0.85rem; color: #6b7280; display: block; font-weight: 600;">Status Pengiriman:</span>
                                <?php if (!empty($t['file_tugas_karyawan']) || !empty($t['keterangan_karyawan'])): ?>
                                    <span style="color: #059669; font-weight: 700; font-size: 0.95rem;">✓ Laporan sudah dikirim</span>
                                    <?php if (!empty($t['file_tugas_karyawan'])): ?>
                                        <br><a href="../assets/uploads/tugas/<?= e($t['file_tugas_karyawan']); ?>" target="_blank" style="font-size: 0.85rem; color: #2563eb; text-decoration: underline;">Lihat berkas terkirim</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #d97706; font-weight: 700; font-size: 0.95rem;">Belum mengumpulkan laporan</span>
                                <?php endif; ?>
                            </div>

                            <button type="button" class="btn btn-primary" style="width: auto; padding: 10px 20px; font-weight: 600;" 
                                data-modal-open="kumpulModal"
                                data-id="<?= e($t['id']); ?>"
                                data-judul="<?= e($t['judul']); ?>"
                                data-status="<?= e($t['status']); ?>"
                                data-keterangan="<?= e($t['keterangan_karyawan'] ?? ''); ?>"
                                data-file="<?= e($t['file_tugas_karyawan'] ?? ''); ?>">
                                📤 Kumpulkan Tugas
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="padding: 50px; text-align:center; background:#fff; border-radius:12px; border:1px dashed #d1d5db;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#9ca3af" style="width:48px; height:48px; margin:0 auto 12px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p style="color:#6b7280; font-weight: 600; margin:0;">Bagus sekali! Tidak ada tugas yang harus dikerjakan saat ini.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <?php require_once "../layouts/footer.php"; ?>


<!-- ===================================== -->
<!-- MODAL FORM PENGUMPULAN TUGAS          -->
<!-- ===================================== -->
<div class="modal-overlay" id="kumpulModal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <div>
                <h2>Form Pengumpulan Tugas</h2>
                <p class="modal-subtitle">Unggah hasil kerja atau lampirkan catatan laporan Anda.</p>
            </div>
            <button type="button" class="close-btn" data-modal-close="kumpulModal" aria-label="Tutup">&times;</button>
        </div>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="kumpul_tugas">
            <input type="hidden" name="task_id" id="modal_task_id">

            <div class="form-group" style="margin-bottom: 16px;">
                <label>Judul Tugas</label>
                <input type="text" id="modal_task_judul" disabled style="background: #f3f4f6; font-weight: 600;">
            </div>

            <!-- Area teks Keterangan / Link diperlebar dan dipertinggi -->
            <div class="form-group" style="margin-bottom: 16px;">
                <label for="modal_keterangan">Keterangan / Link Hasil Tugas (Opsional)</label>
                <textarea name="keterangan_karyawan" id="modal_keterangan" rows="6" placeholder="Tempelkan tautan Google Drive, Figma, atau catatan laporan..." style="width: 100%; min-height: 140px; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.95rem; font-family: inherit; resize: vertical; box-sizing: border-box;"></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="modal_file">Upload File Hasil / Perbaikan Tugas (Maks 5MB)</label>
                <input type="file" name="file_tugas_karyawan" id="modal_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.rar" style="padding: 10px; background: #fff; border: 1px solid #d1d5db; border-radius: 8px; width: 100%; box-sizing: border-box;">
                <small id="modal_file_info" class="text-muted" style="display: block; margin-top: 6px; font-style: italic;"></small>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="modal_status">Progress Status</label>
                <select name="new_status" id="modal_status" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; box-sizing: border-box;">
                    <option value="Belum Selesai">Belum Selesai</option>
                    <option value="Proses">Proses</option>
                    <option value="Selesai">Selesai</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="submit" class="btn btn-primary btn-block">Kirim &amp; Simpan Laporan</button>
                <button type="button" class="btn btn-outline btn-block" data-modal-close="kumpulModal">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const kumpulModal = document.getElementById('kumpulModal');
    
    function closeAllModals() {
        kumpulModal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }

    document.querySelectorAll('[data-modal-open="kumpulModal"]').forEach((btn) => {
        btn.addEventListener('click', function () {
            document.getElementById('modal_task_id').value = this.dataset.id;
            document.getElementById('modal_task_judul').value = this.dataset.judul;
            document.getElementById('modal_keterangan').value = this.dataset.keterangan !== 'null' ? this.dataset.keterangan : '';
            document.getElementById('modal_status').value = this.dataset.status;
            
            const fileInfo = document.getElementById('modal_file_info');
            if (this.dataset.file) {
                fileInfo.innerHTML = `File terunggah saat ini: <a href="../assets/uploads/tugas/${this.dataset.file}" target="_blank" style="color:#2563eb; text-decoration:underline;">${this.dataset.file}</a>`;
            } else {
                fileInfo.textContent = 'Belum ada file yang diunggah.';
            }

            kumpulModal.classList.add('active');
            document.body.classList.add('modal-open');
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(b => b.addEventListener('click', closeAllModals));
    kumpulModal.addEventListener('click', e => { if (e.target === kumpulModal) closeAllModals(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAllModals(); });

    // Animasi Angka
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

    // Timeout Alert
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