<?php
// Módulo de Incidencias: registro de los problemas detectados al usar el ERP
// y de cómo se resolvieron (RA3 g).
// - Cualquier usuario puede abrir una incidencia.
// - El administrador cambia su estado y escribe la solución.

$estados = ['abierta' => 'Abierta', 'en_curso' => 'En curso', 'resuelta' => 'Resuelta'];
$prioridades = ['alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
$modulos_posibles = ['clientes', 'pedidos', 'productos', 'almacen', 'informes', 'extracciones', 'rendimiento',
                     'auditoria', 'usuarios', 'copias', 'ajustes', 'instalador', 'otro'];

$sql_listado = "SELECT id, titulo, modulo, prioridad, estado, creada_por, fecha_creacion, fecha_resolucion, descripcion, solucion
                FROM incidencias";

// Filtro por estado
$filtro = $_GET['estado'] ?? '';
if (isset($estados[$filtro])) {
    $sql_listado .= " WHERE estado = '$filtro'";
}
$sql_listado .= " ORDER BY FIELD(estado, 'abierta', 'en_curso', 'resuelta'), FIELD(prioridad, 'alta', 'media', 'baja'), fecha_creacion DESC";

// Exportar a CSV
if (isset($_GET['exportar']) && $_GET['exportar'] == 'csv') {
    exportar_csv($conn, 'incidencias', 'incidencias',
        ['ID', 'Título', 'Módulo', 'Prioridad', 'Estado', 'Abierta por', 'Fecha', 'Resuelta el', 'Descripción', 'Solución'],
        $sql_listado);
}

// 1. Abrir una incidencia nueva
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nueva_incidencia'])) {
    $titulo      = mb_substr(trim($_POST['titulo'] ?? ''), 0, 150);
    $modulo      = in_array($_POST['modulo'] ?? '', $modulos_posibles) ? $_POST['modulo'] : 'otro';
    $descripcion = trim($_POST['descripcion'] ?? '');
    $prioridad   = isset($prioridades[$_POST['prioridad'] ?? '']) ? $_POST['prioridad'] : 'media';
    $usuario     = usuario_actual();

    $stmt = $conn->prepare("INSERT INTO incidencias (titulo, modulo, descripcion, prioridad, creada_por) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $titulo, $modulo, $descripcion, $prioridad, $usuario);
    $stmt->execute();
    registrar_auditoria($conn, 'incidencias', 'NUEVA_INCIDENCIA', "#{$conn->insert_id} $titulo (prioridad $prioridad)");
    echo "<script>window.location.href='index.php?seccion=incidencias';</script>";
    exit();
}

// 2. Cambiar estado y solución (solo administrador)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_incidencia']) && es_admin()) {
    $id       = intval($_POST['id']);
    $estado   = isset($estados[$_POST['estado'] ?? '']) ? $_POST['estado'] : 'abierta';
    $solucion = trim($_POST['solucion'] ?? '');
    // Al resolverla se guarda la fecha; si se reabre, se borra
    $fecha_sql = $estado == 'resuelta' ? 'COALESCE(fecha_resolucion, NOW())' : 'NULL';

    $stmt = $conn->prepare("UPDATE incidencias SET estado = ?, solucion = ?, fecha_resolucion = $fecha_sql WHERE id = ?");
    $stmt->bind_param("ssi", $estado, $solucion, $id);
    $stmt->execute();
    registrar_auditoria($conn, 'incidencias', 'ACTUALIZAR_INCIDENCIA', "#$id pasa a {$estados[$estado]}");
    echo "<script>window.location.href='index.php?seccion=incidencias';</script>";
    exit();
}

$accion = $_GET['accion'] ?? 'seleccionar';

// Etiqueta de color para prioridad y estado
function etiqueta_prioridad($p) {
    $clases = ['alta' => 'badge-danger', 'media' => 'badge-warning', 'baja' => 'badge-info'];
    return '<span class="badge ' . ($clases[$p] ?? 'badge-info') . '">' . ucfirst($p) . '</span>';
}
function etiqueta_estado($e, $estados) {
    $clases = ['abierta' => 'badge-danger', 'en_curso' => 'badge-warning', 'resuelta' => 'badge-success'];
    return '<span class="badge ' . ($clases[$e] ?? 'badge-info') . '">' . ($estados[$e] ?? $e) . '</span>';
}
?>

<?php if ($accion == 'insertar'): ?>
    <div class="card-container">
        <h2 class="card-title">Nueva incidencia</h2>
        <form action="index.php?seccion=incidencias&accion=insertar" method="POST">
            <input type="hidden" name="nueva_incidencia" value="1">
            <div class="form-group">
                <label for="titulo">Qué ha pasado (en una frase)</label>
                <input type="text" id="titulo" name="titulo" class="form-control" maxlength="150" placeholder="Ej: El CSV de pedidos sale con las tildes mal" required>
            </div>
            <div class="form-group">
                <label for="modulo">Dónde</label>
                <select id="modulo" name="modulo" class="form-control">
                    <?php foreach ($modulos_posibles as $m): ?>
                        <option value="<?php echo $m; ?>"><?php echo $m == 'otro' ? 'Otro' : nombre_modulo($m); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="prioridad">Prioridad</label>
                <select id="prioridad" name="prioridad" class="form-control">
                    <option value="media">Media: molesta, pero se puede seguir trabajando</option>
                    <option value="alta">Alta: impide trabajar</option>
                    <option value="baja">Baja: detalle o mejora</option>
                </select>
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción (qué hacías, qué esperabas y qué pasó)</label>
                <textarea id="descripcion" name="descripcion" class="form-control" rows="5" required></textarea>
            </div>
            <button type="submit" class="btn-actualizar">Abrir incidencia</button>
        </form>
    </div>

<?php elseif ($accion == 'ver' && isset($_GET['id'])):
    $id = intval($_GET['id']);
    $inc = $conn->query("SELECT * FROM incidencias WHERE id = $id")->fetch_assoc();
    if (!$inc): ?>
        <div class="card-container"><p class="texto-ayuda">No existe esa incidencia.</p></div>
    <?php else: ?>
    <div class="card-container">
        <div class="card-header">
            <h2 class="card-title">#<?php echo $inc['id']; ?> <?php echo htmlspecialchars($inc['titulo']); ?></h2>
            <div class="acciones"><?php echo etiqueta_prioridad($inc['prioridad']); ?> <?php echo etiqueta_estado($inc['estado'], $estados); ?></div>
        </div>
        <dl class="ficha">
            <div><dt>Módulo</dt><dd><?php echo $inc['modulo'] == 'otro' ? 'Otro' : nombre_modulo($inc['modulo']); ?></dd></div>
            <div><dt>Abierta por</dt><dd><?php echo htmlspecialchars($inc['creada_por']); ?>, el <?php echo date('d/m/Y H:i', strtotime($inc['fecha_creacion'])); ?></dd></div>
            <?php if ($inc['fecha_resolucion']): ?>
                <div><dt>Resuelta el</dt><dd><?php echo date('d/m/Y H:i', strtotime($inc['fecha_resolucion'])); ?></dd></div>
            <?php endif; ?>
        </dl>
        <h3 class="subtitulo-ficha">Descripción</h3>
        <p class="texto-largo"><?php echo nl2br(htmlspecialchars($inc['descripcion'])); ?></p>

        <?php if (es_admin()): ?>
            <form action="index.php?seccion=incidencias" method="POST" class="form-incidencia">
                <input type="hidden" name="actualizar_incidencia" value="1">
                <input type="hidden" name="id" value="<?php echo $inc['id']; ?>">
                <div class="form-group">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado" class="form-control">
                        <?php foreach ($estados as $clave => $texto): ?>
                            <option value="<?php echo $clave; ?>" <?php echo $inc['estado'] == $clave ? 'selected' : ''; ?>><?php echo $texto; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="solucion">Solución aplicada</label>
                    <textarea id="solucion" name="solucion" class="form-control" rows="4"><?php echo htmlspecialchars($inc['solucion'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn-actualizar">Guardar</button>
            </form>
        <?php elseif ($inc['solucion']): ?>
            <h3 class="subtitulo-ficha">Solución</h3>
            <p class="texto-largo"><?php echo nl2br(htmlspecialchars($inc['solucion'])); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php else:
    $result = $conn->query($sql_listado);
    $cuenta = [];
    $r = $conn->query("SELECT estado, COUNT(*) AS n FROM incidencias GROUP BY estado");
    while ($f = $r->fetch_assoc()) { $cuenta[$f['estado']] = (int)$f['n']; }
?>
    <div class="card-container">
        <div class="card-header">
            <h2 class="card-title">Incidencias</h2>
            <div class="acciones">
                <a href="index.php?seccion=incidencias&exportar=csv&estado=<?php echo urlencode($filtro); ?>" class="btn-exportar">Exportar CSV</a>
                <a href="index.php?seccion=incidencias&accion=insertar" class="btn-actualizar">Nueva incidencia</a>
            </div>
        </div>

        <div class="periodos">
            <a href="index.php?seccion=incidencias" class="periodo <?php echo $filtro == '' ? 'activo' : ''; ?>">Todas (<?php echo array_sum($cuenta); ?>)</a>
            <?php foreach ($estados as $clave => $texto): ?>
                <a href="index.php?seccion=incidencias&estado=<?php echo $clave; ?>" class="periodo <?php echo $filtro == $clave ? 'activo' : ''; ?>"><?php echo $texto; ?> (<?php echo $cuenta[$clave] ?? 0; ?>)</a>
            <?php endforeach; ?>
        </div>

        <table class="table-crimson">
            <thead>
                <tr><th>ID</th><th>Incidencia</th><th>Módulo</th><th>Prioridad</th><th>Estado</th><th>Abierta por</th><th>Fecha</th><th></th></tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?php echo $row['id']; ?></strong></td>
                            <td class="celda-larga"><?php echo htmlspecialchars($row['titulo']); ?></td>
                            <td><?php echo $row['modulo'] == 'otro' ? 'Otro' : nombre_modulo($row['modulo']); ?></td>
                            <td><?php echo etiqueta_prioridad($row['prioridad']); ?></td>
                            <td><?php echo etiqueta_estado($row['estado'], $estados); ?></td>
                            <td><?php echo htmlspecialchars($row['creada_por']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['fecha_creacion'])); ?></td>
                            <td><a href="index.php?seccion=incidencias&accion=ver&id=<?php echo $row['id']; ?>" class="btn-actualizar">Ver</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8">No hay incidencias<?php echo $filtro ? ' en este estado' : ''; ?>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
