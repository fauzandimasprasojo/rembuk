<?php
require_once '../includes/init.php';
requireLogin(); // Logout hanya bermakna bila ada sesi aktif.

// Kosongkan data sesi, hapus cookie sesi, lalu hancurkan sesi di server.
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

header("Location: " . BASE_URL . "/auth/login.php");
exit;
