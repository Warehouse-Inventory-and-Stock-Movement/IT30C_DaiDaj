<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_path(current_user()['role']));
}
redirect('login.php');
