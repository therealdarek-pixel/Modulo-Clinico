<?php
/**
 * config/db.php
 * -------------
 * Conexión única a MariaDB/MySQL usando PDO.
 * La función db() devuelve siempre la MISMA conexión (patrón singleton):
 *   - La primera vez la crea.
 *   - Las siguientes veces reutiliza la que ya existe.
 */

// === Credenciales (cámbialas si tu entorno es distinto) ===
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'bienestar_clinico');
define('DB_USER', 'root');
define('DB_PASS', '');

function db() {
    // Variable estática: conserva su valor entre llamadas a la función.
    static $conexionPdo = null;

    if ($conexionPdo === null) {
        // DSN = cadena de conexión que entiende PDO.
        $dsn = "mysql:host=" . DB_HOST
             . ";port=" . DB_PORT
             . ";dbname=" . DB_NAME
             . ";charset=utf8mb4";

        // Opciones recomendadas:
        //   ERRMODE_EXCEPTION → los errores lanzan excepciones (más fácil depurar).
        //   FETCH_ASSOC       → fetch() devuelve arrays asociativos.
        //   EMULATE_PREPARES  → false fuerza prepared statements reales (más seguros).
        $opcionesPdo = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $conexionPdo = new PDO($dsn, DB_USER, DB_PASS, $opcionesPdo);
        } catch (PDOException $errorConexion) {
            // Si MySQL no está encendido o las credenciales son malas, mostramos
            // un mensaje sencillo y cortamos la ejecución.
            http_response_code(500);
            echo '<h1>Error de conexión a la base de datos</h1>';
            echo '<pre>' . htmlspecialchars($errorConexion->getMessage()) . '</pre>';
            exit;
        }
    }
    return $conexionPdo;
}
