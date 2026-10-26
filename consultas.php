<?php
// =====================================================================
// consultas.php — Consultas de análisis y utilidades comunes (RA3)
// Lo usan: la web (index.php y módulos), el proceso automático
// (extraccion_automatica.php) y la página de rendimiento.
// =====================================================================

// Nombre bonito de cada sección (con tildes), para menús y títulos
function nombre_modulo($clave) {
    $nombres = [
        'activos' => 'Activos', 'administracion' => 'Administración', 'almacen' => 'Almacén',
        'auditoria' => 'Auditoría', 'catalogo' => 'Catálogo', 'compras' => 'Compras',
        'contabilidad' => 'Contabilidad', 'crm' => 'CRM', 'documental' => 'Documental',
        'fabricacion' => 'Fabricación', 'finanzas' => 'Finanzas', 'clientes' => 'Clientes',
        'pedidos' => 'Pedidos', 'productos' => 'Productos', 'informes' => 'Informes',
        'extracciones' => 'Extracciones', 'rendimiento' => 'Rendimiento', 'instalador' => 'Instalador', 'usuarios' => 'Usuarios', 'ajustes' => 'Ajustes', 'copias' => 'Copias de seguridad', 'incidencias' => 'Incidencias',
    ];
    return $nombres[$clave] ?? ucfirst($clave);
}

// Traduce el código de una acción de auditoría a lenguaje normal
function describir_accion($codigo) {
    $textos = [
        'INSERTAR' => 'nuevo registro', 'ACTUALIZAR' => 'registro editado', 'BORRAR' => 'registro eliminado',
        'EXPORTAR_CSV' => 'exportación a CSV', 'EXTRACCION_MANUAL' => 'extracción manual',
        'EXTRACCION_PROGRAMADA' => 'extracción programada', 'PRUEBA_CARGA' => 'prueba de carga',
        'BORRAR_CARGA' => 'datos de prueba borrados', 'INSTALAR' => 'instalación',
        'LOGIN' => 'inicio de sesión', 'LOGOUT' => 'cierre de sesión', 'LOGIN_FALLIDO' => 'intento de acceso fallido',
        'CREAR_USUARIO' => 'usuario creado', 'GUARDAR_AJUSTES' => 'ajustes guardados', 'CREAR_COPIA' => 'copia de seguridad', 'RESTAURAR_COPIA' => 'copia restaurada', 'BORRAR_COPIA' => 'copia eliminada', 'NUEVA_INCIDENCIA' => 'incidencia abierta', 'ACTUALIZAR_INCIDENCIA' => 'incidencia actualizada', 'BORRAR_USUARIO' => 'usuario eliminado', 'CAMBIAR_PASSWORD' => 'contraseña cambiada',
    ];
    return $textos[$codigo] ?? strtolower(str_replace('_', ' ', $codigo));
}

// ---------------------------------------------------------------------
// Ajustes de la aplicación (tabla ajustes: clave -> valor)
// ---------------------------------------------------------------------
function ajuste($conn, $clave, $por_defecto = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        if (tabla_existe($conn, 'ajustes')) {
            $res = $conn->query("SELECT clave, valor FROM ajustes");
            while ($f = $res->fetch_assoc()) {
                $cache[$f['clave']] = $f['valor'];
            }
        }
    }
    return $cache[$clave] ?? $por_defecto;
}

function guardar_ajuste($conn, $clave, $valor) {
    $stmt = $conn->prepare("INSERT INTO ajustes (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    $stmt->bind_param("ss", $clave, $valor);
    $stmt->execute();
}

// Comprueba si una tabla existe en la base de datos
function tabla_existe($conn, $tabla) {
    $tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $tabla);
    $res = $conn->query("SHOW TABLES LIKE '$tabla'");
    return $res && $res->num_rows > 0;
}

// Devuelve la fecha si tiene formato AAAA-MM-DD válido; si no, la fecha por defecto
function fecha_valida($fecha, $por_defecto) {
    if (is_string($fecha) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        [$a, $m, $d] = explode('-', $fecha);
        if (checkdate((int)$m, (int)$d, (int)$a)) {
            return $fecha;
        }
    }
    return $por_defecto;
}

// ---------------------------------------------------------------------
// Definición de los informes. Cada informe tiene:
//   titulo    -> nombre que se muestra
//   sql       -> consulta SQL (con agrupaciones GROUP BY, sumas SUM, uniones JOIN)
//   columnas  -> cabeceras de la tabla y del CSV
//   valor     -> columna numérica que se dibuja como barra (o null)
// Las fechas ya llegan validadas por fecha_valida(), por eso se pueden poner en el SQL.
// ---------------------------------------------------------------------
function informes_definidos($desde, $hasta, $stock_minimo = 20) {
    $stock_minimo = (int)$stock_minimo;
    $rango = "p.fecha BETWEEN '$desde' AND '$hasta'";

    return [
        'ventas_cliente' => [
            'titulo'   => 'Ventas por cliente',
            'sql'      => "SELECT c.nombre AS cliente,
                                  COUNT(p.id) AS pedidos,
                                  SUM(p.cantidad) AS unidades,
                                  ROUND(SUM(p.cantidad * pr.precio), 2) AS importe
                           FROM pedidos p
                           JOIN clientes c   ON c.id = p.cliente_id
                           JOIN productos pr ON pr.id = p.producto_id
                           WHERE $rango
                           GROUP BY c.id, c.nombre
                           ORDER BY importe DESC",
            'columnas' => ['Cliente', 'Pedidos', 'Unidades', 'Importe (€)'],
            'valor'    => 'importe',
        ],
        'ventas_producto' => [
            'titulo'   => 'Ventas por producto',
            'sql'      => "SELECT pr.nombre AS producto,
                                  SUM(p.cantidad) AS unidades,
                                  pr.precio AS precio,
                                  ROUND(SUM(p.cantidad * pr.precio), 2) AS importe
                           FROM pedidos p
                           JOIN productos pr ON pr.id = p.producto_id
                           WHERE $rango
                           GROUP BY pr.id, pr.nombre, pr.precio
                           ORDER BY importe DESC",
            'columnas' => ['Producto', 'Unidades', 'Precio (€)', 'Importe (€)'],
            'valor'    => 'importe',
        ],
        'ventas_mes' => [
            'titulo'   => 'Evolución de ventas por mes',
            'sql'      => "SELECT DATE_FORMAT(p.fecha, '%Y-%m') AS mes,
                                  COUNT(p.id) AS pedidos,
                                  ROUND(SUM(p.cantidad * pr.precio), 2) AS importe
                           FROM pedidos p
                           JOIN productos pr ON pr.id = p.producto_id
                           WHERE $rango
                           GROUP BY mes
                           ORDER BY mes ASC",
            'columnas' => ['Mes', 'Pedidos', 'Importe (€)'],
            'valor'    => 'importe',
        ],
        'stock_bajo' => [
            'titulo'   => "Stock bajo en almacén (menos de $stock_minimo unidades)",
            'sql'      => "SELECT concepto, cantidad, ubicacion
                           FROM almacen
                           WHERE cantidad < $stock_minimo
                           ORDER BY cantidad ASC",
            'columnas' => ['Artículo', 'Cantidad', 'Ubicación'],
            'valor'    => null,
            'tabla'    => 'almacen', // solo se ejecuta si esta tabla existe
        ],
    ];
}

// Indicadores clave (KPI) del periodo: facturación, pedidos, ticket medio...
function resumen_ventas($conn, $desde, $hasta) {
    $rango = "p.fecha BETWEEN '$desde' AND '$hasta'";

    $total = $conn->query("SELECT COUNT(p.id) AS pedidos,
                                  COALESCE(SUM(p.cantidad), 0) AS unidades,
                                  COALESCE(ROUND(SUM(p.cantidad * pr.precio), 2), 0) AS facturado
                           FROM pedidos p
                           JOIN productos pr ON pr.id = p.producto_id
                           WHERE $rango")->fetch_assoc();

    $mejor_cliente = $conn->query("SELECT c.nombre, ROUND(SUM(p.cantidad * pr.precio), 2) AS importe
                                   FROM pedidos p
                                   JOIN clientes c   ON c.id = p.cliente_id
                                   JOIN productos pr ON pr.id = p.producto_id
                                   WHERE $rango
                                   GROUP BY c.id, c.nombre
                                   ORDER BY importe DESC LIMIT 1")->fetch_assoc();

    $producto_estrella = $conn->query("SELECT pr.nombre, SUM(p.cantidad) AS unidades
                                       FROM pedidos p
                                       JOIN productos pr ON pr.id = p.producto_id
                                       WHERE $rango
                                       GROUP BY pr.id, pr.nombre
                                       ORDER BY unidades DESC LIMIT 1")->fetch_assoc();

    $pedidos = (int)$total['pedidos'];
    return [
        'pedidos'           => $pedidos,
        'unidades'          => (int)$total['unidades'],
        'facturado'         => (float)$total['facturado'],
        'ticket_medio'      => $pedidos > 0 ? round($total['facturado'] / $pedidos, 2) : 0,
        'mejor_cliente'     => $mejor_cliente,
        'producto_estrella' => $producto_estrella,
    ];
}

// Escribe un CSV (con BOM para que Excel lea bien las tildes) en un "recurso":
// puede ser la descarga del navegador (php://output) o un archivo en disco.
function escribir_csv($recurso, $cabeceras, $resultado) {
    fprintf($recurso, chr(0xEF) . chr(0xBB) . chr(0xBF));
    // Excel en español usa ";" como separador de columnas
    fputcsv($recurso, $cabeceras, ';');
    $filas = 0;
    while ($fila = $resultado->fetch_row()) {
        fputcsv($recurso, $fila, ';');
        $filas++;
    }
    return $filas;
}

// Descarga en el navegador el resultado de una consulta como CSV
// y deja constancia en la auditoría. Termina la ejecución.
function exportar_csv($conn, $modulo, $nombre_archivo, $cabeceras, $sql) {
    $resultado = $conn->query($sql);
    registrar_auditoria($conn, $modulo, 'EXPORTAR_CSV', "Descarga del informe $nombre_archivo");
    limpiar_salida(); // quitamos el HTML ya preparado para que el CSV salga limpio
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $nombre_archivo . '_' . date('Y-m-d') . '.csv');
    $salida = fopen('php://output', 'w');
    escribir_csv($salida, $cabeceras, $resultado);
    fclose($salida);
    exit();
}

// ---------------------------------------------------------------------
// Extracción automática (RA3 e): genera todos los informes como CSV
// dentro de la carpeta exportaciones/, sin que nadie tenga que pulsar nada.
// $origen indica quién la lanzó: 'programada' (Programador de tareas) o 'manual' (botón web).
// ---------------------------------------------------------------------
function carpeta_exportaciones() {
    return __DIR__ . DIRECTORY_SEPARATOR . 'exportaciones';
}

function ejecutar_extraccion($conn, $origen) {
    $carpeta = carpeta_exportaciones();
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0777, true);
    }

    $inicio  = microtime(true);
    $marca   = date('Y-m-d_His');
    $creados = [];

    // La extracción cubre todo el histórico de pedidos
    foreach (informes_definidos('2000-01-01', date('Y-m-d'), ajuste($conn, 'stock_minimo', 20)) as $clave => $inf) {
        if (isset($inf['tabla']) && !tabla_existe($conn, $inf['tabla'])) {
            continue;
        }
        $archivo = "{$clave}_{$marca}.csv";
        $recurso = fopen($carpeta . DIRECTORY_SEPARATOR . $archivo, 'w');
        $filas   = escribir_csv($recurso, $inf['columnas'], $conn->query($inf['sql']));
        fclose($recurso);
        $creados[] = ['archivo' => $archivo, 'filas' => $filas];
    }

    $ms = round((microtime(true) - $inicio) * 1000, 1);
    registrar_auditoria($conn, 'extracciones', 'EXTRACCION_' . strtoupper($origen),
        count($creados) . " informes generados en $ms ms ($marca)");

    return ['archivos' => $creados, 'ms' => $ms];
}

// ---------------------------------------------------------------------
// Copias de seguridad: guarda toda la base de datos en un archivo .sql
// (estructura de cada tabla + todos sus datos) dentro de la carpeta copias/.
// ---------------------------------------------------------------------
function carpeta_copias() {
    $carpeta = __DIR__ . DIRECTORY_SEPARATOR . 'copias';
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0777, true);
    }
    // Bloquea el acceso directo desde el navegador: solo se descargan desde la aplicación
    if (!file_exists($carpeta . DIRECTORY_SEPARATOR . '.htaccess')) {
        file_put_contents($carpeta . DIRECTORY_SEPARATOR . '.htaccess', "Require all denied\n");
    }
    return $carpeta;
}

// Comprueba que un nombre de archivo es una copia nuestra (evita que pidan otros archivos del disco)
function nombre_copia_valido($nombre) {
    return (bool)preg_match('/^copia_\d{4}-\d{2}-\d{2}_\d{6}\.sql$/', $nombre);
}

function crear_copia($conn, $origen = 'manual') {
    $inicio  = microtime(true);
    $archivo = 'copia_' . date('Y-m-d_His') . '.sql';
    $ruta    = carpeta_copias() . DIRECTORY_SEPARATOR . $archivo;
    $f = fopen($ruta, 'w');

    fwrite($f, "-- Copia de seguridad de diegofo_crimson\n-- Fecha: " . date('Y-m-d H:i:s') . "\n\n");
    fwrite($f, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

    $tablas = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    $total_filas = 0;
    $num_tablas = 0;
    while ($t = $tablas->fetch_row()) {
        $tabla = $t[0];
        $num_tablas++;
        $crear = $conn->query("SHOW CREATE TABLE `$tabla`")->fetch_row()[1];
        fwrite($f, "-- Tabla $tabla\nDROP TABLE IF EXISTS `$tabla`;\n$crear;\n\n");

        // Nombres de las columnas, para los INSERT
        $cols = [];
        $res = $conn->query("SHOW COLUMNS FROM `$tabla`");
        while ($c = $res->fetch_assoc()) { $cols[] = $c['Field']; }
        $insert = "INSERT INTO `$tabla` (`" . implode('`, `', $cols) . "`) VALUES\n";

        // Los datos se escriben en bloques de 200 filas por INSERT
        $filas = $conn->query("SELECT * FROM `$tabla`");
        $bloque = [];
        while ($fila = $filas->fetch_row()) {
            $valores = array_map(fn($v) => $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'", $fila);
            $bloque[] = '(' . implode(', ', $valores) . ')';
            $total_filas++;
            if (count($bloque) == 200) {
                fwrite($f, $insert . implode(",\n", $bloque) . ";\n");
                $bloque = [];
            }
        }
        if ($bloque) {
            fwrite($f, $insert . implode(",\n", $bloque) . ";\n");
        }
        fwrite($f, "\n");
    }

    fwrite($f, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fclose($f);

    $ms = round((microtime(true) - $inicio) * 1000, 1);
    registrar_auditoria($conn, 'copias', 'CREAR_COPIA', "$archivo ($num_tablas tablas, $total_filas filas, $ms ms, $origen)");
    return ['archivo' => $archivo, 'tablas' => $num_tablas, 'filas' => $total_filas, 'ms' => $ms];
}

// Vuelve a cargar una copia: borra las tablas actuales y las recrea con los datos de la copia
function restaurar_copia($conn, $archivo) {
    $sql = file_get_contents(carpeta_copias() . DIRECTORY_SEPARATOR . $archivo);
    $conn->multi_query($sql);
    do {
        if ($r = $conn->store_result()) { $r->free(); }
    } while ($conn->more_results() && $conn->next_result());
    registrar_auditoria($conn, 'copias', 'RESTAURAR_COPIA', "Restaurada la copia $archivo");
}
?>
