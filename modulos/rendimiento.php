<?php
// =====================================================================
// Módulo de Rendimiento (RA3 f)
// - Prueba de carga: genera muchos pedidos de prueba para ver cómo responde el sistema.
// - Cronometra las consultas principales (media de varias repeticiones).
// - EXPLAIN: muestra cómo busca MySQL los datos y si usa índices.
// - Muestra el tamaño de cada tabla y los índices de la tabla pedidos.
// =====================================================================

$mensaje = '';
$CLIENTES_CARGA  = 15;
$PRODUCTOS_CARGA = 10;

// --- Prueba de carga: crear datos de prueba ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generar_carga'])) {
    $n = max(1, min(20000, intval($_POST['num_pedidos']))); // entre 1 y 20.000
    $inicio = microtime(true);

    // Transacción: todas las inserciones se guardan de golpe al final (mucho más rápido)
    $conn->begin_transaction();

    $ids_clientes = [];
    $stmt = $conn->prepare("INSERT INTO clientes (nombre) VALUES (?)");
    for ($i = 1; $i <= $CLIENTES_CARGA; $i++) {
        $nombre = "Cliente Carga " . str_pad($i, 2, '0', STR_PAD_LEFT);
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $ids_clientes[] = $conn->insert_id;
    }

    $ids_productos = [];
    $stmt = $conn->prepare("INSERT INTO productos (nombre, precio) VALUES (?, ?)");
    for ($i = 1; $i <= $PRODUCTOS_CARGA; $i++) {
        $nombre = "Producto Carga " . str_pad($i, 2, '0', STR_PAD_LEFT);
        $precio = mt_rand(500, 25000) / 100; // entre 5,00 y 250,00 €
        $stmt->bind_param("sd", $nombre, $precio);
        $stmt->execute();
        $ids_productos[] = $conn->insert_id;
    }

    $numero = (int)$conn->query("SELECT COALESCE(MAX(numero), 0) AS n FROM pedidos")->fetch_assoc()['n'];
    $stmt = $conn->prepare("INSERT INTO pedidos (fecha, numero, cliente_id, producto_id, cantidad) VALUES (?, ?, ?, ?, ?)");
    for ($i = 0; $i < $n; $i++) {
        $fecha    = date('Y-m-d', strtotime('-' . mt_rand(0, 364) . ' days')); // último año
        $numero++;
        $cliente  = $ids_clientes[array_rand($ids_clientes)];
        $producto = $ids_productos[array_rand($ids_productos)];
        $cantidad = mt_rand(1, 5);
        $stmt->bind_param("siiii", $fecha, $numero, $cliente, $producto, $cantidad);
        $stmt->execute();
    }

    $conn->commit();
    $ms = round((microtime(true) - $inicio) * 1000, 1);
    registrar_auditoria($conn, 'rendimiento', 'PRUEBA_CARGA', "Insertados $n pedidos de prueba en $ms ms");
    $mensaje = "Prueba de carga: $n pedidos insertados en $ms ms (" . round($n / max($ms / 1000, 0.001)) . " pedidos/segundo).";
}

// --- Borrar los datos de prueba ---
// Al borrar los clientes y productos de carga, sus pedidos se borran solos
// gracias a ON DELETE CASCADE de las claves foráneas.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['borrar_carga'])) {
    $conn->query("DELETE FROM clientes WHERE nombre LIKE 'Cliente Carga %'");
    $conn->query("DELETE FROM productos WHERE nombre LIKE 'Producto Carga %'");
    registrar_auditoria($conn, 'rendimiento', 'BORRAR_CARGA', 'Eliminados los datos de la prueba de carga');
    $mensaje = "Datos de prueba eliminados.";
}

// --- Consultas que vamos a cronometrar ---
$informes = informes_definidos('2000-01-01', date('Y-m-d'), ajuste($conn, 'stock_minimo', 20));
$hace_un_mes = date('Y-m-d', strtotime('-30 days'));
$pruebas = [
    'Contar pedidos (COUNT)'          => "SELECT COUNT(*) FROM pedidos",
    'Pedidos del último mes (índice)' => "SELECT * FROM pedidos WHERE fecha >= '$hace_un_mes'",
    'Buscar cliente (LIKE)'           => "SELECT * FROM clientes WHERE nombre LIKE '%Carga%'",
    'Listado de pedidos (JOIN)'       => "SELECT p.*, c.nombre, pr.nombre FROM pedidos p
                                          LEFT JOIN clientes c ON c.id = p.cliente_id
                                          LEFT JOIN productos pr ON pr.id = p.producto_id",
    'Informe: ventas por cliente'     => $informes['ventas_cliente']['sql'],
    'Informe: ventas por producto'    => $informes['ventas_producto']['sql'],
    'Informe: ventas por mes'         => $informes['ventas_mes']['sql'],
];

$REPETICIONES = 5;
$resultados = [];
foreach ($pruebas as $nombre => $sql) {
    $tiempos = [];
    $filas = 0;
    for ($i = 0; $i < $REPETICIONES; $i++) {
        $t = microtime(true);
        $res = $conn->query($sql);
        $tiempos[] = (microtime(true) - $t) * 1000;
        $filas = $res->num_rows;
        $res->free();
    }
    // EXPLAIN: MySQL explica cómo va a ejecutar la consulta (primera tabla del plan)
    $plan = $conn->query("EXPLAIN " . $sql)->fetch_assoc();

    $media = array_sum($tiempos) / count($tiempos);
    $resultados[] = [
        'nombre' => $nombre,
        'media'  => $media,
        'max'    => max($tiempos),
        'filas'  => $filas,
        'tipo'   => $plan['type'] ?? '—',
        'indice' => $plan['key'] ?? 'ninguno',
        'leidas' => $plan['rows'] ?? '—',
    ];
}

// --- Tamaño de las tablas ---
$tablas = $conn->query("SELECT TABLE_NAME AS tabla, TABLE_ROWS AS filas,
                               ROUND(DATA_LENGTH / 1024, 1) AS datos_kb,
                               ROUND(INDEX_LENGTH / 1024, 1) AS indices_kb
                        FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA = DATABASE()
                        ORDER BY DATA_LENGTH DESC");

// --- Índices de la tabla pedidos ---
$indices = $conn->query("SHOW INDEX FROM pedidos");

// Semáforo según el tiempo medio
function color_tiempo($ms) {
    if ($ms < 10)  return 'badge-success'; // rápido
    if ($ms < 100) return 'badge-warning'; // aceptable
    return 'badge-danger';                 // lento
}
?>

<div class="card-container">
    <div class="card-header">
        <h2 class="card-title">Rendimiento de las consultas</h2>
    </div>

    <?php if ($mensaje): ?>
        <div class="aviso aviso-ok"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <p class="texto-ayuda">
        Servidor: <strong>MySQL/MariaDB <?php echo $conn->server_info; ?></strong> &middot;
        PHP <strong><?php echo phpversion(); ?></strong> &middot;
        Cada consulta se ejecuta <strong><?php echo $REPETICIONES; ?> veces</strong> y se muestra el tiempo medio.
    </p>

    <!-- Prueba de carga -->
    <div class="barra-filtros">
        <form action="index.php?seccion=rendimiento" method="POST">
            <input type="hidden" name="generar_carga" value="1">
            <div class="form-group">
                <label>Pedidos de prueba</label>
                <input type="number" name="num_pedidos" value="1000" min="1" max="20000" class="form-control">
            </div>
            <button type="submit" class="btn-actualizar">Lanzar prueba de carga</button>
        </form>
        <form action="index.php?seccion=rendimiento" method="POST"
              data-titulo="¿Borrar los datos de prueba?" data-confirmar="Se borrarán todos los clientes, productos y pedidos creados por la prueba de carga." data-boton="Borrar datos de prueba">
            <input type="hidden" name="borrar_carga" value="1">
            <button type="submit" class="btn-eliminar">Borrar datos de prueba</button>
        </form>
    </div>

    <table class="table-crimson">
        <thead>
            <tr>
                <th>Consulta</th>
                <th>Tiempo medio</th>
                <th>Tiempo máximo</th>
                <th>Filas devueltas</th>
                <th>Tipo de acceso</th>
                <th>Índice usado</th>
                <th>Filas examinadas</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($resultados as $r): ?>
                <tr>
                    <td><?php echo $r['nombre']; ?></td>
                    <td><span class="badge <?php echo color_tiempo($r['media']); ?>"><?php echo number_format($r['media'], 2, ',', '.'); ?> ms</span></td>
                    <td><?php echo number_format($r['max'], 2, ',', '.'); ?> ms</td>
                    <td><?php echo $r['filas']; ?></td>
                    <td><?php echo htmlspecialchars((string)$r['tipo']); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['indice']); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['leidas']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="texto-ayuda">
        <strong>Cómo leer el tipo de acceso:</strong> <em>ALL</em> = MySQL lee la tabla entera;
        <em>range</em> o <em>ref</em> = usa un índice y solo lee las filas necesarias (más rápido);
        <em>index</em> = recorre un índice completo.
        Verde: menos de 10 ms &middot; Amarillo: menos de 100 ms &middot; Rojo: 100 ms o más.
    </p>
</div>

<div class="card-container seccion-informe">
    <h2 class="card-title">Tamaño de las tablas</h2>
    <table class="table-crimson">
        <thead>
            <tr><th>Tabla</th><th>Filas (aprox.)</th><th>Datos</th><th>Índices</th></tr>
        </thead>
        <tbody>
            <?php while ($t = $tablas->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($t['tabla']); ?></td>
                    <td><?php echo $t['filas']; ?></td>
                    <td><?php echo number_format((float)$t['datos_kb'], 1, ',', '.'); ?> KB</td>
                    <td><?php echo number_format((float)$t['indices_kb'], 1, ',', '.'); ?> KB</td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<div class="card-container seccion-informe">
    <h2 class="card-title">Índices de la tabla pedidos</h2>
    <table class="table-crimson">
        <thead>
            <tr><th>Nombre del índice</th><th>Columna</th><th>¿Único?</th></tr>
        </thead>
        <tbody>
            <?php while ($i = $indices->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($i['Key_name']); ?></td>
                    <td><?php echo htmlspecialchars($i['Column_name']); ?></td>
                    <td><?php echo $i['Non_unique'] == 0 ? 'Sí' : 'No'; ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
