<?php
/**
 * public/index.php
 * ----------------
 * Punto de entrada. Si hay sesión → dashboard. Si no → login.
 */
require_once __DIR__ . '/../includes/auth.php';

header('Location: ' . (current_user_id() ? 'dashboard.php' : 'login.php'));
exit;
