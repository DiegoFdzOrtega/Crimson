<?php
// Limpiar el nombre de la sección
$tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $seccion);

// Asegurar tabla creada
@$conn->query("CREATE TABLE IF NOT EXISTS `$tabla` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    concepto VARCHAR(150) NOT NULL,
    referencia VARCHAR(50) NOT NULL,
    estado VARCHAR(50) DEFAULT 'Activo',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;");

// Acción de exportación a CSV (Criterio d del RA3)
if (isset($_GET['exportar']) && $_GET['exportar'] == 'csv') {
    exportar_csv($conn, $tabla, 'informe_' . $tabla,
        ['ID', 'Concepto', 'Referencia', 'Estado', 'Fecha Registro'],
        "SELECT id, concepto, referencia, estado, fecha_registro FROM `$tabla` ORDER BY id ASC");
}

// Inserción de datos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nuevo_registro'])) {
    $concepto = $conn->real_escape_string($_POST['concepto']);
    $referencia = $conn->real_escape_string($_POST['referencia']);
    @$conn->query("INSERT INTO `$tabla` (concepto, referencia) VALUES ('$concepto', '$referencia')");
    registrar_auditoria($conn, $tabla, 'INSERTAR', "Nuevo registro: {$_POST['concepto']} ({$_POST['referencia']})");
    limpiar_salida();
    header("Location: index.php?seccion=$tabla");
    exit();
}

// Borrado de registros
if (isset($_GET['op']) && $_GET['op'] == 'borrar' && isset($_GET['id'])) {
    $id_del = intval($_GET['id']);
    @$conn->query("DELETE FROM `$tabla` WHERE id = $id_del");
    registrar_auditoria($conn, $tabla, 'BORRAR', "Eliminado registro ID: $id_del");
    limpiar_salida();
    header("Location: index.php?seccion=$tabla");
    exit();
}

$accion = $_GET['accion'] ?? 'seleccionar';
$filtro = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
?>

<div class="card-container">
    <?php if ($accion == 'insertar'): ?>
        <h2 class="card-title">Nuevo registro en <?php echo nombre_modulo($seccion); ?></h2>
        <form action="index.php?seccion=<?php echo $seccion; ?>&accion=insertar" method="POST">
            <input type="hidden" name="nuevo_registro" value="1">
            <div class="form-group">
                <label>Concepto</label>
                <input type="text" name="concepto" class="form-control" placeholder="Ej: Operación principal de <?php echo $seccion; ?>" required>
            </div>
            <div class="form-group">
                <label>Referencia</label>
                <input type="text" name="referencia" class="form-control" placeholder="Ej: REF-9988" required>
            </div>
            <button type="submit" class="btn-actualizar">Guardar registro</button>
        </form>
    <?php else: 
        // Consulta con soporte de búsqueda y filtrado (Criterio a del RA3)
        if ($filtro != '') {
            $result = $conn->query("SELECT * FROM `$tabla` WHERE concepto LIKE '%$filtro%' OR referencia LIKE '%$filtro%' ORDER BY id ASC");
        } else {
            $result = $conn->query("SELECT * FROM `$tabla` ORDER BY id ASC");
        }
    ?>
        <div class="card-header">
            <h2 class="card-title">Registros de <?php echo nombre_modulo($seccion); ?></h2>
            
            <!-- Barra de búsqueda y herramientas de análisis/exportación -->
            <div class="acciones">
                <form action="index.php" method="GET" class="buscador">
                    <input type="hidden" name="seccion" value="<?php echo $seccion; ?>">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($filtro); ?>" placeholder="Buscar registro..." class="form-control">
                    <button type="submit" class="btn-secundario">Buscar</button>
                </form>
                <a href="index.php?seccion=<?php echo $seccion; ?>&exportar=csv" class="btn-exportar">Exportar CSV</a>
                <a href="index.php?seccion=<?php echo $seccion; ?>&accion=insertar" class="btn-actualizar">Nuevo registro</a>
            </div>
        </div>

        <table class="table-crimson">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Concepto</th>
                    <th>Referencia</th>
                    <th>Estado</th>
                    <th>Operaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?php echo strtoupper(substr($seccion, 0, 3)); ?>-0<?php echo $row['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['concepto']); ?></td>
                            <td><?php echo htmlspecialchars($row['referencia']); ?></td>
                            <td><span class="badge badge-success"><?php echo $row['estado']; ?></span></td>
                            <td>
                                <a href="index.php?seccion=<?php echo $seccion; ?>&op=borrar&id=<?php echo $row['id']; ?>" data-titulo="¿Eliminar este registro?" data-nombre="<?php echo htmlspecialchars($row['concepto'], ENT_QUOTES); ?>" data-confirmar="" class="btn-eliminar">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No se encontraron registros en <?php echo $seccion; ?>.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>