<?php
$host = "sql306.infinityfree.com";
$user = "if0_42594263";
$pass = "JyLQCtrDGm0fke"; 
$db   = "if0_42594263_karyawan";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi database gagal.");
}

mysqli_set_charset($conn, 'utf8mb4');
?>