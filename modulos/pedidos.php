<?php
// Consulta del listado: une (JOIN) pedidos con clientes y productos
// y calcula el importe de cada línea (cantidad x precio).
$sql_listado = "SELECT p.id, p.fecha, p.numero, c.nombre AS cliente, pr.nombre AS producto,
                       p.cantidad, pr.precio, ROUND(p.cantidad * pr.precio, 2) AS importe
                FROM pedidos p
                LEFT JOIN clientes c   ON p.cliente_id = c.id
                LEFT JOIN productos pr ON p.producto_id = pr.id
                ORDER BY p.fecha DESC, p.id DESC";

// 0. Exportar pedidos a CSV (RA3 d)
if (isset($_GET['exportar']) && $_GET['exportar'] == 'csv') {
    exportar_csv($conn, 'pedidos', 'pedidos',
        ['ID', 'Fecha', 'Número', 'Cliente', 'Producto', 'Cantidad', 'Precio (€)', 'Importe (€)'],
        $sql_listado);
}

// 1. Eliminar pedido
if (isset($_GET['op']) && $_GET['op'] == 'eliminar' && isset($_GET['id'])) {
    $id_eliminar = intval($_GET['id']);
    $conn->query("DELETE FROM pedidos WHERE id = $id_eliminar");
    registrar_auditoria($conn, 'pedidos', 'BORRAR', "Eliminado pedido ID: $id_eliminar");
    echo "<script>window.location.href='index.php?seccion=pedidos';</script>";
    exit();
}

// 2. Actualizar pedido existente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_pedido'])) {
    $id = intval($_POST['id']);
    $fecha = fecha_valida($_POST['fecha'], date('Y-m-d'));
    $numero = intval($_POST['numero']);
    $cliente_id = intval($_POST['cliente_id']);
    $producto_id = intval($_POST['producto_id']);
    $cantidad = max(1, intval($_POST['cantidad']));

    $stmt = $conn->prepare("UPDATE pedidos SET fecha = ?, numero = ?, cliente_id = ?, producto_id = ?, cantidad = ? WHERE id = ?");
    $stmt->bind_param("siiiii", $fecha, $numero, $cliente_id, $producto_id, $cantidad, $id);
    $stmt->execute();
    registrar_auditoria($conn, 'pedidos', 'ACTUALIZAR', "Actualizado pedido ID: $id");
    echo "<script>window.location.href='index.php?seccion=pedidos';</script>";
    exit();
}

// 3. Insertar nuevo pedido
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['insertar_pedido'])) {
    $fecha = fecha_valida($_POST['fecha'], date('Y-m-d'));
    $numero = intval($_POST['numero']);
    $cliente_id = intval($_POST['cliente_id']);
    $producto_id = intval($_POST['producto_id']);
    $cantidad = max(1, intval($_POST['cantidad']));

    $stmt = $conn->prepare("INSERT INTO pedidos (fecha, numero, cliente_id, producto_id, cantidad) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("siiii", $fecha, $numero, $cliente_id, $producto_id, $cantidad);
    $stmt->execute();
    registrar_auditoria($conn, 'pedidos', 'INSERTAR', "Nuevo pedido Nº $numero ($cantidad uds.)");
    echo "<script>window.location.href='index.php?seccion=pedidos';</script>";
    exit();
}

$accion = $_GET['accion'] ?? 'seleccionar';

if ($accion == 'insertar'):
    $clientes_list = $conn->query("SELECT * FROM clientes ORDER BY nombre ASC");
    $productos_list = $conn->query("SELECT * FROM productos ORDER BY nombre ASC");
    $siguiente = $conn->query("SELECT COALESCE(MAX(numero), 0) + 1 AS n FROM pedidos")->fetch_assoc()['n'];
?>
    <div class="card-container">
        <h2 class="card-title">Nuevo pedido</h2>
        <form action="index.php?seccion=pedidos&accion=insertar" method="POST">
            <input type="hidden" name="insertar_pedido" value="1">
            <div class="form-group">
                <label>Fecha del pedido</label>
                <input type="date" name="fecha" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label>Número de pedido</label>
                <input type="number" name="numero" class="form-control" value="<?php echo $siguiente; ?>" min="1" required>
            </div>
            <div class="form-group">
                <label>Cliente</label>
                <select name="cliente_id" class="form-control" required>
                    <option value="">Elige un cliente</option>
                    <?php while ($c = $clientes_list->fetch_assoc()): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?> (ID: <?php echo $c['id']; ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Producto</label>
                <select name="producto_id" class="form-control" required>
                    <option value="">Elige un producto</option>
                    <?php while ($p = $productos_list->fetch_assoc()): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre']); ?> - <?php echo number_format($p['precio'], 2, ',', '.'); ?> €</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Cantidad (unidades)</label>
                <input type="number" name="cantidad" class="form-control" value="1" min="1" required>
            </div>
            <button type="submit" class="btn-actualizar">Guardar pedido</button>
        </form>
    </div>

<?php
elseif ($accion == 'actualizar' && isset($_GET['id'])):
    $id_edit = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM pedidos WHERE id = $id_edit");
    $pedido_edit = $res->fetch_assoc();

    $clientes_list = $conn->query("SELECT * FROM clientes ORDER BY nombre ASC");
    $productos_list = $conn->query("SELECT * FROM productos ORDER BY nombre ASC");
?>
    <div class="card-container">
        <h2 class="card-title">Editar pedido #<?php echo $id_edit; ?></h2>
        <form action="index.php?seccion=pedidos" method="POST">
            <input type="hidden" name="actualizar_pedido" value="1">
            <input type="hidden" name="id" value="<?php echo $pedido_edit['id']; ?>">
            <div class="form-group">
                <label>Fecha</label>
                <input type="date" name="fecha" class="form-control" value="<?php echo $pedido_edit['fecha']; ?>" required>
            </div>
            <div class="form-group">
                <label>Número de pedido</label>
                <input type="number" name="numero" class="form-control" value="<?php echo $pedido_edit['numero']; ?>" min="1" required>
            </div>
            <div class="form-group">
                <label>Cliente</label>
                <select name="cliente_id" class="form-control" required>
                    <?php while ($c = $clientes_list->fetch_assoc()): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $pedido_edit['cliente_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['nombre']); ?> (ID: <?php echo $c['id']; ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Producto</label>
                <select name="producto_id" class="form-control" required>
                    <?php while ($p = $productos_list->fetch_assoc()): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($p['id'] == $pedido_edit['producto_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['nombre']); ?> - <?php echo number_format($p['precio'], 2, ',', '.'); ?> €
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Cantidad (unidades)</label>
                <input type="number" name="cantidad" class="form-control" value="<?php echo $pedido_edit['cantidad']; ?>" min="1" required>
            </div>
            <button type="submit" class="btn-actualizar">Guardar cambios</button>
        </form>
    </div>

<?php
else:
    $result = $conn->query($sql_listado);
?>
    <div class="card-container">
        <div class="card-header">
            <h2 class="card-title">Todos los pedidos</h2>
            <div class="acciones">
                <a href="index.php?seccion=pedidos&exportar=csv" class="btn-exportar">Exportar CSV</a>
                <a href="index.php?seccion=pedidos&accion=insertar" class="btn-actualizar">Nuevo pedido</a>
            </div>
        </div>

        <table class="table-crimson">
            <thead>
                <tr>
                    <th>Identificador</th>
                    <th>Fecha</th>
                    <th>Número</th>
                    <th>Cliente</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Importe</th>
                    <th>Operaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?php echo $row['id']; ?></strong></td>
                            <td><?php echo $row['fecha']; ?></td>
                            <td>Nº <?php echo $row['numero']; ?></td>
                            <td><?php echo htmlspecialchars($row['cliente'] ?? 'Cliente eliminado'); ?></td>
                            <td><?php echo htmlspecialchars($row['producto'] ?? 'Producto eliminado'); ?></td>
                            <td><?php echo $row['cantidad']; ?> uds.</td>
                            <td><strong><?php echo number_format((float)$row['importe'], 2, ',', '.'); ?> €</strong></td>
                            <td>
                                <a href="index.php?seccion=pedidos&accion=actualizar&id=<?php echo $row['id']; ?>" class="btn-actualizar">Actualizar</a>
                                <a href="index.php?seccion=pedidos&op=eliminar&id=<?php echo $row['id']; ?>" data-titulo="¿Eliminar este pedido?" data-nombre="Pedido Nº <?php echo $row['numero']; ?>, <?php echo htmlspecialchars($row['cliente'] ?? 'Cliente eliminado', ENT_QUOTES); ?>" data-confirmar="" class="btn-eliminar">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No hay pedidos registrados en la base de datos.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
