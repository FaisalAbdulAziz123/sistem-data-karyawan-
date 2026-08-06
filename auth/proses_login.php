<?php
session_start();
require_once "../config/koneksi.php";

if (!isset($conn) || $conn === false) {
    $_SESSION['error'] = 'Koneksi database gagal.';
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

$role = $_POST['role'] ?? 'karyawan';
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($username === '' || $password === '') {
    $_SESSION['error'] = 'Username dan password harus diisi.';
    header("Location: login.php");
    exit;
}

// -----------------------------------------------------
// LOGIKA LOGIN UNTUK ADMIN (Tabel: users)
// -----------------------------------------------------
if ($role === 'admin') {
    $sql = "SELECT id, nama_lengkap, password FROM users WHERE username = ? OR email = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $username, $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) === 1) {
        // Inisialisasi variabel awal agar linter editor tidak mendeteksi error
        $id = null; $nama_lengkap = null; $db_password = null;
        
        mysqli_stmt_bind_result($stmt, $id, $nama_lengkap, $db_password);
        mysqli_stmt_fetch($stmt);

        // Perbaikan: Konversi $password dan $db_password menjadi (string) secara eksplisit
        if (password_verify((string)$password, (string)$db_password)) {
            $_SESSION['login'] = true;
            $_SESSION['role'] = 'admin';
            $_SESSION['nama'] = $nama_lengkap;
            $_SESSION['user_id'] = $id;
            
            mysqli_stmt_close($stmt);
            header("Location: ../dashboard/index.php"); // Arahkan Admin ke Dashboard Utama
            exit;
        } else {
            $_SESSION['error'] = 'Password Admin salah.';
        }
    } else {
        $_SESSION['error'] = 'Akun Admin tidak ditemukan.';
    }
    mysqli_stmt_close($stmt);
    header("Location: login.php");
    exit;
}

// -----------------------------------------------------
// LOGIKA LOGIN UNTUK KARYAWAN (Tabel: karyawan)
// -----------------------------------------------------
else if ($role === 'karyawan') {
    $sql = "SELECT nip, nama_karyawan, password, jabatan, status FROM karyawan WHERE nip = ? OR email = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $username, $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) === 1) {
        // Inisialisasi variabel awal agar linter editor tidak mendeteksi error
        $nip = null; $nama_karyawan = null; $db_password = null; $jabatan = null; $status = null;
        
        mysqli_stmt_bind_result($stmt, $nip, $nama_karyawan, $db_password, $jabatan, $status);
        mysqli_stmt_fetch($stmt);

        if ($status !== 'Aktif') {
            $_SESSION['error'] = 'Akun Anda berstatus ' . $status . '. Silakan hubungi Admin.';
            mysqli_stmt_close($stmt);
            header("Location: login.php");
            exit;
        }

        // Perbaikan: Konversi $password dan $db_password menjadi (string) secara eksplisit
        if (password_verify((string)$password, (string)$db_password)) {
            $_SESSION['login'] = true;
            $_SESSION['role'] = 'karyawan';
            $_SESSION['nip'] = $nip; 
            $_SESSION['nama'] = $nama_karyawan;
            $_SESSION['jabatan'] = $jabatan;

            mysqli_stmt_close($stmt);
            header("Location: ../karyawan/dashboard_karyawan.php"); // Arahkan Karyawan ke Portal
            exit;
        } else {
            $_SESSION['error'] = 'Password Karyawan salah.';
        }
    } else {
        $_SESSION['error'] = 'NIP atau Email Karyawan tidak ditemukan.';
    }
    mysqli_stmt_close($stmt);
    header("Location: login.php");
    exit;
}
?>