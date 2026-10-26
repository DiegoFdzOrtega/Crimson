<?php
// =====================================================================
// Módulo de Extracciones automáticas (RA3 e)
// - Lanza la extracción a mano (botón) para probarla.
// - Lista los CSV que ha generado el proceso automático.
// - Muestra el historial de ejecuciones (desde la auditoría).
// - Explica cómo programarla en el Programador de tareas de Windows.
// =====================================================================

$mensaje = '';

// Ejecutar la extracción ahora mismo desde la web
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ejecutar_extraccion'])) {
    $r = ejecutar_extraccion($conn, 'manual');
    $mensaje = count($r['archivos']) . " informes generados en {$r['ms']} ms.";
}

// Archivos CSV generados, del más reciente al más antiguo
$carpeta = carpeta_exportaciones();
$archivos = is_dir($carpeta) ? glob($carpeta . DIRECTORY_SEPARATOR . '*.csv') : [];
usort($archivos, fn($a, $b) => filemtime($b) - filemtime($a));
$archivos = array_slice($archivos, 0, 40); // mostramos como máximo los 40 últimos

// Historial de ejecuciones guardado en la auditoría
$historial = $conn->query("SELECT fecha, accion_realizada, detalle
                           FROM auditoria_sistema
                           WHERE modulo = 'extracciones'
                           ORDER BY fecha DESC, id DESC
                           LIMIT 15");

// Rutas reales de este ordenador, para las instrucciones
$ruta_proyecto = dirname(__DIR__);
$ruta_bat = $ruta_proyecto . DIRECTORY_SEPARATOR . 'extraccion_programada.bat';
?>

<div class="card-container">
    <div class="card-header">
        <h2 class="card-title">Extracción automática de informes</h2>
        <form action="index.php?seccion=extracciones" method="POST">
            <input type="hidden" name="ejecutar_extraccion" value="1">
            <button type="submit" class="btn-actualizar">Extraer informes ahora</button>
        </form>
    </div>

    <?php if ($mensaje): ?>
        <div class="aviso aviso-ok"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <p class="texto-ayuda">
        El proceso <strong>extraccion_automatica.php</strong> genera todos los informes del módulo
        Informes como archivos CSV en la carpeta <strong>exportaciones/</strong>. Se ejecuta solo,
        cada día, mediante el Programador de tareas de Windows, y cada ejecución queda registrada en la auditoría.
    </p>
</div>

<div class="card-container seccion-informe">
    <h2 class="card-title">Cómo programarla en Windows</h2>
    <p class="texto-ayuda">
        Abre <strong>cmd</strong> (Símbolo del sistema) y ejecuta esta orden. Crea una tarea llamada
        "SGE diegofo - Extraccion" que lanza el proceso todos los días a las 08:00:
    </p>
    <div class="bloque-codigo">schtasks /create /tn "SGE diegofo - Extraccion" /tr "\"<?php echo htmlspecialchars($ruta_bat); ?>\"" /sc daily /st 08:00</div>
    <p class="texto-ayuda">Para probarla sin esperar a mañana:</p>
    <div class="bloque-codigo">schtasks /run /tn "SGE diegofo - Extraccion"</div>
    <p class="texto-ayuda">
        Importante: MySQL tiene que estar encendido en el panel de XAMPP a esa hora.
        Lo que escribe el proceso se guarda en <strong>exportaciones/registro_extracciones.log</strong>.
    </p>
</div>

<div class="card-container seccion-informe">
    <h2 class="card-title">Historial de ejecuciones</h2>
    <table class="table-crimson">
        <thead>
            <tr><th>Fecha</th><th>Origen</th><th>Detalle</th></tr>
        </thead>
        <tbody>
            <?php if ($historial && $historial->num_rows > 0): ?>
                <?php while ($h = $historial->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $h['fecha']; ?></td>
                        <td>
                            <?php if ($h['accion_realizada'] == 'EXTRACCION_PROGRAMADA'): ?>
                                <span class="badge badge-info">Programada</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Manual</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($h['detalle']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="3">Aún no se ha ejecutado ninguna extracción.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card-container seccion-informe">
    <h2 class="card-title">Archivos generados</h2>
    <table class="table-crimson">
        <thead>
            <tr><th>Archivo</th><th>Fecha</th><th>Tamaño</th><th>Descargar</th></tr>
        </thead>
        <tbody>
            <?php if (count($archivos) > 0): ?>
                <?php foreach ($archivos as $ruta):
                    $nombre = basename($ruta); ?>
                    <tr>
                        <td><?php echo htmlspecialchars($nombre); ?></td>
                        <td><?php echo date('Y-m-d H:i:s', filemtime($ruta)); ?></td>
                        <td><?php echo number_format(filesize($ruta) / 1024, 1, ',', '.'); ?> KB</td>
                        <td><a href="exportaciones/<?php echo rawurlencode($nombre); ?>" class="btn-exportar" download>Descargar</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4">Todavía no hay archivos. Pulsa "Extraer informes ahora".</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
