<?php
/**
 * public/logout.php
 * -----------------
 * Cierra la sesión y vuelve al login.
 */
require_once __DIR__ . '/../includes/auth.php';

logout_user();
header('Location: login.php');
exit;
