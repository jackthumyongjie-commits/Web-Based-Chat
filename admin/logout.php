<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
start_admin_session();
if (current_admin_id()) {
    logout_admin();
}
start_admin_session();
redirect('admin/login.php');
