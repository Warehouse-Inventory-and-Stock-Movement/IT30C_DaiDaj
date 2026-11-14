<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Logout is POST-only with a CSRF token so other sites cannot sign users out.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(is_logged_in() ? dashboard_path(current_user()['role']) : 'login.php');
}
csrf_verify();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

session_start();
session_regenerate_id(true);
flash('success', 'You have been signed out.');
redirect('login.php');
