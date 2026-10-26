<?php
// 0. Exportar almacén a CSV (RA3 d)
if (isset($_GET['exportar']) && $_GET['exportar'] == 'csv') {
    exportar_csv($conn, 'almacen', 'almacen', ['ID', 'Concepto', 'Cantidad', 'Ubicación'], "SELECT id, concepto, cantidad, ubicacion FROM almacen ORDER BY id ASC");
}

// 1. Eliminar registro de almacén
if (isset($_GET['op']) && $_GET['op'] == 'eliminar_almacen' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM almacen WHERE id = $id");
    registrar_auditoria($conn, 'almacen', 'BORRAR', "Eliminado registro de almacén ID: $id");
    echo "<script>window.location.href='index.php?seccion=almacen';</script>";
    exit();
}

// 2. Guardar nuevo registro en almacén (Insertar)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_almacen'])) {
    $concepto = $conn->real_escape_string($_POST['concepto']);
    $cantidad = intval($_POST['cantidad']);
    $ubicacion = $conn->real_escape_string($_POST['ubicacion']);
    
    $conn->query("INSERT INTO almacen (concepto, cantidad, ubicacion) VALUES ('$concepto', $cantidad, '$ubicacion')");
    registrar_auditoria($conn, 'almacen', 'INSERTAR', "Nuevo stock: " . $_POST['concepto'] . " ($cantidad uds.)");
    echo "<script>window.location.href='index.php?seccion=almacen';</script>";
    exit();
}

// 3. Actualizar registro existente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_almacen'])) {
    $id = intval($_POST['id']);
    $concepto = $conn->real_escape_string($_POST['concepto']);
    $cantidad = intval($_POST['cantidad']);
    $ubicacion = $conn->real_escape_string($_POST['ubicacion']);

    $conn->query("UPDATE almacen SET concepto = '$concepto', cantidad = $cantidad, ubicacion = '$ubicacion' WHERE id = $id");
    registrar_auditoria($conn, 'almacen', 'ACTUALIZAR', "Actualizado registro de almacén ID: $id");
    echo "<script>window.location.href='index.php?seccion=almacen';</script>";
    exit();
}

$accion = $_GET['accion'] ?? 'seleccionar';
?>

<div class="card-container">
    <?php if ($accion == 'insertar'): ?>
        <h2 class="card-title">Añadir stock</h2>
        <form action="index.php?seccion=almacen&accion=insertar" method="POST">
            <input type="hidden" name="guardar_almacen" value="1">
            <div class="form-group">
                <label>Artículo</label>
                <input type="text" name="concepto" class="form-control" placeholder="Ej: Lote Componentes Alpha" required>
            </div>
            <div class="form-group">
                <label>Cantidad</label>
                <input type="number" name="cantidad" class="form-control" placeholder="Ej: 150" required>
            </div>
            <div class="form-group">
                <label>Ubicación</label>
                <input type="text" name="ubicacion" class="form-control" placeholder="Ej: Estantería A-1" required>
            </div>
            <button type="submit" class="btn-actualizar">Guardar stock</button>
        </form>

    <?php elseif ($accion == 'actualizar' && isset($_GET['id'])): 
        $id_edit = intval($_GET['id']);
        $res = $conn->query("SELECT * FROM almacen WHERE id = $id_edit");
        $alm_edit = $res->fetch_assoc();
    ?>
        <h2 class="card-title">Editar stock #<?php echo $id_edit; ?></h2>
        <form action="index.php?seccion=almacen" method="POST">
            <input type="hidden" name="actualizar_almacen" value="1">
            <input type="hidden" name="id" value="<?php echo $alm_edit['id']; ?>">
            <div class="form-group">
                <label>Artículo</label>
                <input type="text" name="concepto" class="form-control" value="<?php echo $alm_edit['concepto']; ?>" required>
            </div>
            <div class="form-group">
                <label>Cantidad</label>
                <input type="number" name="cantidad" class="form-control" value="<?php echo $alm_edit['cantidad']; ?>" required>
            </div>
            <div class="form-group">
                <label>Ubicación</label>
                <input type="text" name="ubicacion" class="form-control" value="<?php echo $alm_edit['ubicacion']; ?>" required>
            </div>
            <button type="submit" class="btn-actualizar">Guardar cambios</button>
        </form>

    <?php else: 
        // Asegurarnos de que la tabla existe en MySQL por si acaso
        $conn->query("CREATE TABLE IF NOT EXISTS almacen (
            id INT AUTO_INCREMENT PRIMARY KEY,
            concepto VARCHAR(100) NOT NULL,
            cantidad INT NOT NULL,
            ubicacion VARCHAR(50) NOT NULL
        ) ENGINE=InnoDB;");

        $result = $conn->query("SELECT * FROM almacen ORDER BY id ASC");
    ?>
        <div class="card-header">
            <h2 class="card-title">Existencias en almacén</h2>
            <div class="acciones">
                <a href="index.php?seccion=almacen&exportar=csv" class="btn-exportar">Exportar CSV</a>
                <a href="index.php?seccion=almacen&accion=insertar" class="btn-actualizar">Añadir stock</a>
            </div>
        </div>
        <table class="table-crimson">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Concepto</th>
                    <th>Cantidad</th>
                    <th>Ubicación</th>
                    <th>Operaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#ALM-0<?php echo $row['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['concepto']); ?></td>
                            <td><?php echo $row['cantidad']; ?> unidades</td>
                            <td><?php echo htmlspecialchars($row['ubicacion']); ?></td>
                            <td>
                                <a href="index.php?seccion=almacen&accion=actualizar&id=<?php echo $row['id']; ?>" class="btn-actualizar">Editar</a>
                                <a href="index.php?seccion=almacen&op=eliminar_almacen&id=<?php echo $row['id']; ?>" data-titulo="¿Eliminar este stock?" data-nombre="<?php echo htmlspecialchars($row['concepto'], ENT_QUOTES); ?>" data-confirmar="" class="btn-eliminar">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No hay registros en almacén.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>