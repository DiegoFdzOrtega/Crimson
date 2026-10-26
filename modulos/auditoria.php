<?php
// Módulo de Auditoría: muestra el registro de todas las acciones del sistema
// (inserciones, borrados y exportaciones), guardadas en la tabla auditoria_sistema.

// Nos aseguramos de que la tabla existe (la función la crea si hace falta)
$conn->query("CREATE TABLE IF NOT EXISTS auditoria_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL DEFAULT 'sistema',
    modulo VARCHAR(50) NOT NULL,
    accion_realizada VARCHAR(100) NOT NULL,
    detalle VARCHAR(255) NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Texto que el usuario escribe en el buscador (vacío si no busca nada)
$filtro = $_GET['q'] ?? '';
$patron = $conn->real_escape_string('%' . $filtro . '%');

$sql = "SELECT id, fecha, usuario, modulo, accion_realizada, detalle FROM auditoria_sistema
        WHERE usuario LIKE '$patron' OR modulo LIKE '$patron' OR accion_realizada LIKE '$patron' OR detalle LIKE '$patron'
        ORDER BY fecha DESC, id DESC";

// Exportar el registro de auditoría a CSV
if (isset($_GET['exportar']) && $_GET['exportar'] == 'csv') {
    exportar_csv($conn, 'auditoria', 'auditoria', ['ID', 'Fecha', 'Usuario', 'Módulo', 'Acción', 'Detalle'], $sql);
}

$result = $conn->query($sql);
?>

<div class="card-container">
    <div class="card-header">
        <h2 class="card-title">Registro de actividad</h2>

        <div class="acciones">
            <form action="index.php" method="GET" class="buscador">
                <input type="hidden" name="seccion" value="auditoria">
                <input type="text" name="q" value="<?php echo htmlspecialchars($filtro); ?>" placeholder="Buscar usuario, módulo o acción..." class="form-control">
                <button type="submit" class="btn-secundario">Buscar</button>
            </form>
            <a href="index.php?seccion=auditoria&exportar=csv&q=<?php echo urlencode($filtro); ?>" class="btn-exportar">Exportar CSV</a>
        </div>
    </div>

    <table class="table-crimson">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Usuario</th>
                <th>Módulo</th>
                <th>Acción</th>
                <th>Detalle</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#AUD-0<?php echo $row['id']; ?></strong></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['fecha'])); ?></td>
                        <td><?php echo htmlspecialchars($row['usuario']); ?></td>
                        <td><?php echo htmlspecialchars(nombre_modulo($row['modulo'])); ?></td>
                        <td><span class="badge badge-info"><?php echo htmlspecialchars(ucfirst(describir_accion($row['accion_realizada']))); ?></span></td>
                        <td><?php echo htmlspecialchars($row['detalle']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">Todavía no hay acciones registradas.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
