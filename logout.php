<?php
session_start();

// Session'daki tüm verileri temizle
$_SESSION = array();

// Session cookie'sini sil
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Session'ı sonlandır
session_destroy();

// Ana sayfaya yönlendir
header("Location: giris.php");
exit();
?>