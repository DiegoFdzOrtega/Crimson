<?php
// Cerrar sesión: lo apunta en la auditoría, borra la sesión y vuelve a la entrada.
require_once 'config.php';

if (isset($_SESSION['usuario'])) {
    registrar_auditoria($conn, 'usuarios', 'LOGOUT', 'Cierre de sesión');
}

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit();
