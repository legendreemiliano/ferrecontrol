<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (is_post()) {
    csrf_check();
    auth_logout();
}
redirect('login.php');
