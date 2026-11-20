<?php
define('PUBLIC_PAGE', true);
require_once __DIR__ . '/includes/bootstrap.php';
redirect(current_user() ? 'dashboard.php' : 'login.php');
