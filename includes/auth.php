<?php
/**
 * includes/auth.php
 * -----------------
 * Manejo de sesión (login / logout / usuario actual) usando $_SESSION.
 * Cada página protegida llama a require_login() al inicio.
 */

require_once __DIR__ . '/../config/db.php';

// Arrancamos la sesión solo si no estaba ya iniciada.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------- 1. Lectura del usuario logueado ----------

/** Devuelve el id del usuario logueado, o null si no hay sesión. */
function current_user_id() {
    return isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
}

/** Si no hay sesión, manda al login. Se usa al inicio de cada página privada. */
function require_login() {
    if (!current_user_id()) {
        header('Location: login.php');
        exit;
    }
}

// ---------- 2. Login / logout ----------

/** Marca al usuario como logueado y regenera el id de sesión (anti session-fixation). */
function login_user($idUsuario) {
    $_SESSION['usuario_id'] = (int)$idUsuario;
    session_regenerate_id(true);
}

/** Cierra sesión completamente: vacía $_SESSION, borra la cookie y destruye la sesión. */
function logout_user() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

// ---------- 3. Helpers ----------

/**
 * Devuelve el array del usuario logueado (sin password_hash).
 * Cachea el resultado en una static para no hacer dos veces la misma query.
 */
function get_user() {
    $id = current_user_id();
    if (!$id) return null;
    static $cache = null;
    if ($cache !== null) return $cache;

    $stmt = db()->prepare(
        "SELECT id, nombre, email, edad, sexo, peso, altura, creado_en
           FROM usuarios WHERE id = ?"
    );
    $stmt->execute([$id]);
    $cache = $stmt->fetch();
    return $cache ?: null;
}

/** Escape rápido para HTML — atajo de htmlspecialchars con configuración segura. */
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
