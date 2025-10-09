<?php
// auth.php
if (session_status() === PHP_SESSION_NONE) session_start();

function current_user_id(): ?int {
  return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
}
function current_user_name(): ?string {
  return $_SESSION['name'] ?? null;
}
function require_login() {
  if (!current_user_id()) {
    header("Location: login.php?next=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
  }
}
function login_user(int $id, string $name) {
  session_regenerate_id(true); // Prevent session fixation
  $_SESSION['uid']  = $id;
  $_SESSION['name'] = $name;
}

function logout_user() {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
  }
  session_destroy();
}
