<?php
define('APP_NAME', 'diegofo');
define('APP_MODULE', 'crimson');

$host = "localhost";
$user = "root";
$pass = "";
$db   = "diegofo_crimson";

// 0. Sesión: recuerda qué usuario ha entrado mientras navega por las páginas.
//    En la consola (proceso automático) no hay sesión ni usuario.
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Conectamos al servidor MySQL SIN elegir base de datos todavía.
//    Así funciona aunque la base de datos aún no exista (primera instalación).
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// 2. Creamos la base de datos si no existe y la seleccionamos.
$conn->query("CREATE DATABASE IF NOT EXISTS `$db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($db);
$conn->set_charset("utf8mb4");

// 3. Usuarios y sesión
// Usuario que está usando la aplicación ('sistema' si es el proceso automático)
function usuario_actual() {
    return $_SESSION['usuario'] ?? 'sistema';
}

function es_admin() {
    return ($_SESSION['rol'] ?? '') === 'admin';
}

// Si nadie ha iniciado sesión, manda a la pantalla de entrada
function requerir_login() {
    if (!isset($_SESSION['usuario'])) {
        limpiar_salida();
        header('Location: login.php');
        exit();
    }
}

// 4. Guarda cada acción en la tabla de auditoría, junto con quién la hizo.
//    Usa una consulta preparada (los datos van aparte de la orden SQL),
//    así nadie puede colar código SQL dentro del texto.
function registrar_auditoria($conn, $modulo, $accion, $detalle, $usuario = null) {
    $conn->query("CREATE TABLE IF NOT EXISTS auditoria_sistema (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(50) NOT NULL DEFAULT 'sistema',
        modulo VARCHAR(50) NOT NULL,
        accion_realizada VARCHAR(100) NOT NULL,
        detalle VARCHAR(255) NOT NULL,
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $usuario = $usuario ?? usuario_actual();
    $stmt = $conn->prepare("INSERT INTO auditoria_sistema (usuario, modulo, accion_realizada, detalle) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $usuario, $modulo, $accion, $detalle);
    $stmt->execute();
}

// 5. Vacía lo que la página ya tenía preparado para enviar al navegador.
//    Se usa antes de descargar un CSV o de redirigir, para que no se mezcle HTML.
function limpiar_salida() {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

// 6. Consultas de informes, exportación CSV y extracción automática (RA3)
require_once __DIR__ . '/consultas.php';
?>
