<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';

start_app_session();
logout();

header('Location: login.php');
exit;
