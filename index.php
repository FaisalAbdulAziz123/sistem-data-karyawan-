<?php
session_start();

if (isset($_SESSION['login'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: dashboard/index.php");
    } else {
        header("Location: auth/login.php");
    }
} else {
    header("Location: auth/login.php");
}
exit;