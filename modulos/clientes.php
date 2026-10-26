<?php
// 0. Exportar clientes a CSV (RA3 d)
if (isset($_GET['exportar']) && $_GET['exportar'] == 'csv') {
    exportar_csv($conn, 'clientes', 'clientes', ['ID', 'Nombre'], "SELECT id, nombre FROM clientes ORDER BY id ASC");
}

// 1. Eliminar cliente
if (isset($_GET['op']) && $_GET['op'] == 'eliminar_cliente' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM clientes WHERE id = $id");
    registrar_auditoria($conn, 'clientes', 'BORRAR', "Eliminado cliente ID: $id");
    echo "<script>window.location.href='index.php?seccion=clientes';</script>";
    exit();
}

// 2. Guardar nuevo cliente (Insertar)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_cliente'])) {
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $conn->query("INSERT INTO clientes (nombre) VALUES ('$nombre')");
    registrar_auditoria($conn, 'clientes', 'INSERTAR', "Nuevo cliente: " . $_POST['nombre']);
    echo "<script>window.location.href='index.php?seccion=clientes';</script>";
    exit();
}

$accion = $_GET['accion'] ?? 'seleccionar';
?>

<div class="card-container">
    <?php if ($accion == 'insertar'): ?>
        <h2 class="card-title">Nuevo cliente</h2>
        <form action="index.php?seccion=clientes&accion=insertar" method="POST">
            <input type="hidden" name="guardar_cliente" value="1">
            <div class="form-group">
                <label>Nombre del cliente o empresa</label>
                <input type="text" name="nombre" class="form-control" placeholder="Ej: Comercial Martínez S.L." required>
            </div>
            <button type="submit" class="btn-actualizar">Guardar cliente</button>
        </form>
    <?php else: 
        $result = $conn->query("SELECT * FROM clientes ORDER BY id ASC");
    ?>
        <div class="card-header">
            <h2 class="card-title">Cartera de clientes</h2>
            <div class="acciones">
                <a href="index.php?seccion=clientes&exportar=csv" class="btn-exportar">Exportar CSV</a>
                <a href="index.php?seccion=clientes&accion=insertar" class="btn-actualizar">Nuevo cliente</a>
            </div>
        </div>
        <table class="table-crimson">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre o empresa</th>
                    <th>Estado</th>
                    <th>Operaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#CLI-00<?php echo $row['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                            <td><span class="badge badge-success">Activo</span></td>
                            <td>
                                <a href="index.php?seccion=clientes&op=eliminar_cliente&id=<?php echo $row['id']; ?>" data-titulo="¿Eliminar este cliente?" data-nombre="<?php echo htmlspecialchars($row['nombre'], ENT_QUOTES); ?>" data-confirmar="También se borrarán todos sus pedidos." class="btn-eliminar">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">No hay clientes registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>