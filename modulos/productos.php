<?php
// 0. Exportar productos a CSV (RA3 d)
if (isset($_GET['exportar']) && $_GET['exportar'] == 'csv') {
    exportar_csv($conn, 'productos', 'productos', ['ID', 'Nombre', 'Precio (€)'], "SELECT id, nombre, precio FROM productos ORDER BY id ASC");
}

// 1. Eliminar producto
if (isset($_GET['op']) && $_GET['op'] == 'eliminar_producto' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM productos WHERE id = $id");
    registrar_auditoria($conn, 'productos', 'BORRAR', "Eliminado producto ID: $id");
    echo "<script>window.location.href='index.php?seccion=productos';</script>";
    exit();
}

// 2. Guardar nuevo producto (Insertar)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_producto'])) {
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $precio = floatval($_POST['precio']);
    $conn->query("INSERT INTO productos (nombre, precio) VALUES ('$nombre', $precio)");
    registrar_auditoria($conn, 'productos', 'INSERTAR', "Nuevo producto: " . $_POST['nombre']);
    echo "<script>window.location.href='index.php?seccion=productos';</script>";
    exit();
}

// 3. Actualizar producto existente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_producto'])) {
    $id = intval($_POST['id']);
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $precio = floatval($_POST['precio']);
    $conn->query("UPDATE productos SET nombre = '$nombre', precio = $precio WHERE id = $id");
    registrar_auditoria($conn, 'productos', 'ACTUALIZAR', "Actualizado producto ID: $id");
    echo "<script>window.location.href='index.php?seccion=productos';</script>";
    exit();
}

$accion = $_GET['accion'] ?? 'seleccionar';
?>

<div class="card-container">
    <?php if ($accion == 'insertar'): ?>
        <h2 class="card-title">Nuevo producto</h2>
        <form action="index.php?seccion=productos&accion=insertar" method="POST">
            <input type="hidden" name="guardar_producto" value="1">
            <div class="form-group">
                <label>Nombre</label>
                <input type="text" name="nombre" class="form-control" placeholder="Ej: Teclado Mecánico RGB" required>
            </div>
            <div class="form-group">
                <label>Precio (€)</label>
                <input type="number" step="0.01" name="precio" class="form-control" placeholder="Ej: 49.99" required>
            </div>
            <button type="submit" class="btn-actualizar">Guardar producto</button>
        </form>

    <?php elseif ($accion == 'actualizar' && isset($_GET['id'])): 
        $id_edit = intval($_GET['id']);
        $res = $conn->query("SELECT * FROM productos WHERE id = $id_edit");
        $prod_edit = $res->fetch_assoc();
    ?>
        <h2 class="card-title">Editar producto #<?php echo $id_edit; ?></h2>
        <form action="index.php?seccion=productos" method="POST">
            <input type="hidden" name="actualizar_producto" value="1">
            <input type="hidden" name="id" value="<?php echo $prod_edit['id']; ?>">
            <div class="form-group">
                <label>Nombre</label>
                <input type="text" name="nombre" class="form-control" value="<?php echo $prod_edit['nombre']; ?>" required>
            </div>
            <div class="form-group">
                <label>Precio (€)</label>
                <input type="number" step="0.01" name="precio" class="form-control" value="<?php echo $prod_edit['precio']; ?>" required>
            </div>
            <button type="submit" class="btn-actualizar">Guardar cambios</button>
        </form>

    <?php else: 
        $result = $conn->query("SELECT * FROM productos ORDER BY id ASC");
    ?>
        <div class="card-header">
            <h2 class="card-title">Catálogo de productos</h2>
            <div class="acciones">
                <a href="index.php?seccion=productos&exportar=csv" class="btn-exportar">Exportar CSV</a>
                <a href="index.php?seccion=productos&accion=insertar" class="btn-actualizar">Nuevo producto</a>
            </div>
        </div>
        <table class="table-crimson">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Precio</th>
                    <th>Estado</th>
                    <th>Operaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#PROD-00<?php echo $row['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                            <td><?php echo number_format($row['precio'], 2); ?> €</td>
                            <td><span class="badge badge-success">En Catálogo</span></td>
                            <td>
                                <a href="index.php?seccion=productos&accion=actualizar&id=<?php echo $row['id']; ?>" class="btn-actualizar">Editar</a>
                                <a href="index.php?seccion=productos&op=eliminar_producto&id=<?php echo $row['id']; ?>" data-titulo="¿Eliminar este producto?" data-nombre="<?php echo htmlspecialchars($row['nombre'], ENT_QUOTES); ?>" data-confirmar="También se borrarán los pedidos que lo incluyen." class="btn-eliminar">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No hay productos registrados en el catálogo.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>