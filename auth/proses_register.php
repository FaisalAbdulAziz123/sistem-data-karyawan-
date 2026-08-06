<?php
session_start();
require '../config/koneksi.php';

// Pastikan koneksi tersedia
if (!isset($conn) || $conn === false) {
    $_SESSION['error'] = 'Koneksi database gagal.';
    header("Location: register.php");
    exit;
}

// Fitur Auto-Update: Pastikan tabel users (Untuk Admin) tersedia
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_lengkap VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$nama = trim($_POST['nama_lengkap'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$konfirmasi = $_POST['konfirmasi_password'] ?? '';

// 1. Cek form kosong
if ($nama === '' || $username === '' || $email === '' || $password === '') {
    $_SESSION['error'] = 'Semua kolom wajib diisi.';
    header("Location: register.php");
    exit;
}

// 2. Cek kecocokan password
if ($password !== $konfirmasi) {
    $_SESSION['error'] = 'Konfirmasi password tidak sama!';
    header("Location: register.php");
    exit;
}

// 3. Cek apakah username atau email sudah ada di database (Mencegah Duplikat)
$stmt_cek = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
mysqli_stmt_bind_param($stmt_cek, "ss", $username, $email);
mysqli_stmt_execute($stmt_cek);
mysqli_stmt_store_result($stmt_cek);

if (mysqli_stmt_num_rows($stmt_cek) > 0) {
    // KEMBALIKAN KE HALAMAN REGISTER DENGAN PESAN ERROR (Bukan layar putih)
    $_SESSION['error'] = 'Username atau Email sudah terdaftar! Silakan gunakan yang lain.';
    mysqli_stmt_close($stmt_cek);
    header("Location: register.php");
    exit;
}
mysqli_stmt_close($stmt_cek);

// 4. Hash Password untuk keamanan
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// 5. Masukkan data admin baru ke tabel users
$stmt_insert = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, username, email, password) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt_insert, "ssss", $nama, $username, $email, $passwordHash);
$query = mysqli_stmt_execute($stmt_insert);

if ($query) {
    // Registrasi Berhasil -> Arahkan ke halaman Login
    $_SESSION['error'] = 'Registrasi berhasil! Silakan login.';
    mysqli_stmt_close($stmt_insert);
    header("Location: login.php");
    exit;
} else {
    // Jika query gagal (misal server error)
    $_SESSION['error'] = 'Registrasi gagal. Terjadi kesalahan pada server.';
    mysqli_stmt_close($stmt_insert);
    header("Location: register.php");
    exit;
}
?>