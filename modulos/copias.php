<?php
// Copias de seguridad de la base de datos (solo administrador):
// crear, descargar, restaurar y eliminar copias en formato .sql.

if (!es_admin()) {
    echo '<div class="card-container"><h2 class="card-title">Sin permiso</h2><p class="texto-ayuda">Solo un administrador puede gestionar las copias de seguridad.</p></div>';
    return;
}

$carpeta = carpeta_copias();
$mensaje = '';
$error = '';

// Descargar una copia
if (isset($_GET['descargar']) && nombre_copia_valido($_GET['descargar']) && file_exists("$carpeta/{$_GET['descargar']}")) {
    limpiar_salida();
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $_GET['descargar']);
    readfile("$carpeta/{$_GET['descargar']}");
    exit();
}

// Crear una copia ahora
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_copia'])) {
    $r = crear_copia($conn, 'manual');
    $mensaje = "Copia creada: {$r['archivo']} ({$r['tablas']} tablas, {$r['filas']} filas, {$r['ms']} ms).";
}

// Restaurar una copia
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restaurar']) && nombre_copia_valido($_POST['restaurar'])) {
    try {
        restaurar_copia($conn, $_POST['restaurar']);
        $mensaje = "Base de datos restaurada desde {$_POST['restaurar']}.";
    } catch (mysqli_sql_exception $e) {
        $error = 'No se pudo restaurar la copia: ' . $e->getMessage();
    }
}

// Eliminar una copia
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar']) && nombre_copia_valido($_POST['eliminar'])) {
    @unlink("$carpeta/{$_POST['eliminar']}");
    registrar_auditoria($conn, 'copias', 'BORRAR_COPIA', "Eliminada la copia {$_POST['eliminar']}");
    $mensaje = "Copia {$_POST['eliminar']} eliminada.";
}

// Lista de copias, de la más reciente a la más antigua
$copias = glob($carpeta . DIRECTORY_SEPARATOR . 'copia_*.sql');
usort($copias, fn($a, $b) => filemtime($b) - filemtime($a));
?>

<?php if ($mensaje): ?>
    <div class="aviso aviso-ok" role="status"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="aviso aviso-error" role="alert"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card-container">
    <div class="card-header">
        <h2 class="card-title">Copias de seguridad</h2>
        <form action="index.php?seccion=copias" method="POST">
            <input type="hidden" name="crear_copia" value="1">
            <button type="submit" class="btn-actualizar">Crear copia ahora</button>
        </form>
    </div>

    <p class="texto-ayuda">
        Cada copia guarda la estructura y todos los datos de la base de datos en un archivo <strong>.sql</strong>.
        El proceso automático (<strong>extraccion_automatica.php</strong>) también crea una copia cada día.
        Restaurar una copia sustituye los datos actuales por los de esa fecha.
    </p>

    <table class="table-crimson">
        <thead>
            <tr><th>Copia</th><th>Fecha</th><th>Tamaño</th><th>Operaciones</th></tr>
        </thead>
        <tbody>
            <?php if (count($copias) > 0): ?>
                <?php foreach ($copias as $ruta):
                    $nombre = basename($ruta); ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($nombre); ?></strong></td>
                        <td><?php echo date('d/m/Y H:i', filemtime($ruta)); ?></td>
                        <td><?php echo number_format(filesize($ruta) / 1024, 1, ',', '.'); ?> KB</td>
                        <td>
                            <div class="acciones">
                                <a href="index.php?seccion=copias&descargar=<?php echo urlencode($nombre); ?>" class="btn-exportar">Descargar</a>
                                <form action="index.php?seccion=copias" method="POST"
                                      data-titulo="¿Restaurar esta copia?" data-nombre="<?php echo htmlspecialchars($nombre, ENT_QUOTES); ?>"
                                      data-confirmar="Los datos actuales se sustituirán por los de esta copia. Lo que se haya hecho después se perderá." data-boton="Restaurar">
                                    <input type="hidden" name="restaurar" value="<?php echo htmlspecialchars($nombre); ?>">
                                    <button type="submit" class="btn-secundario">Restaurar</button>
                                </form>
                                <form action="index.php?seccion=copias" method="POST"
                                      data-titulo="¿Eliminar esta copia?" data-nombre="<?php echo htmlspecialchars($nombre, ENT_QUOTES); ?>" data-confirmar="">
                                    <input type="hidden" name="eliminar" value="<?php echo htmlspecialchars($nombre); ?>">
                                    <button type="submit" class="btn-eliminar">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4">Todavía no hay copias. Pulsa "Crear copia ahora".</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
