<?php
// =====================================================================
// extraccion_automatica.php — Proceso de extracción automática (RA3 e)
//
// Genera todos los informes del sistema como archivos CSV dentro de la
// carpeta "exportaciones/" y una copia de seguridad en "copias/",
// sin abrir el navegador ni pulsar nada.
// Está pensado para ejecutarse desde la consola (línea de comandos),
// lanzado cada día por el Programador de tareas de Windows mediante
// el archivo extraccion_programada.bat.
//
// Ejecución manual desde la consola de Windows (cmd):
//   C:\xampp\php\php.exe C:\xampp\htdocs\crimson\extraccion_automatica.php
// =====================================================================

// Por seguridad, este archivo solo se puede ejecutar desde la consola.
// (Desde la web se usa el botón del módulo "Extracciones").
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Este proceso solo se ejecuta desde la línea de comandos.');
}

require_once __DIR__ . '/config.php';

echo "[" . date('Y-m-d H:i:s') . "] Inicio de la extracción automática\n";

$resultado = ejecutar_extraccion($conn, 'programada');

foreach ($resultado['archivos'] as $a) {
    echo "  - {$a['archivo']} ({$a['filas']} filas)\n";
}
echo "[" . date('Y-m-d H:i:s') . "] Informes: " . count($resultado['archivos'])
   . " generados en {$resultado['ms']} ms\n";

// Copia de seguridad diaria de toda la base de datos
$copia = crear_copia($conn, 'programada');
echo "[" . date('Y-m-d H:i:s') . "] Copia de seguridad: {$copia['archivo']} ({$copia['filas']} filas)\n\n";
?>
