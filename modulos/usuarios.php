<?php
// Módulo de Usuarios: alta y baja de las personas que pueden entrar al ERP.
// Solo lo pueden usar los administradores.

if (!es_admin()) {
    echo '<div class="card-container"><h2 class="card-title">Sin permiso</h2><p class="texto-ayuda">Solo un administrador puede gestionar usuarios.</p></div>';
    return;
}

$error = '';

// 1. Eliminar usuario (no puedes borrarte a ti mismo)
if (isset($_GET['op']) && $_GET['op'] == 'eliminar' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $fila = $conn->query("SELECT usuario FROM usuarios WHERE id = $id")->fetch_assoc();
    if ($fila && $fila['usuario'] !== usuario_actual()) {
        $conn->query("DELETE FROM usuarios WHERE id = $id");
        registrar_auditoria($conn, 'usuarios', 'BORRAR_USUARIO', "Eliminado el usuario {$fila['usuario']}");
    }
    echo "<script>window.location.href='index.php?seccion=usuarios';</script>";
    exit();
}

// 2. Crear usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_usuario'])) {
    $usuario  = trim($_POST['usuario'] ?? '');
    $nombre   = trim($_POST['nombre'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol      = ($_POST['rol'] ?? '') === 'admin' ? 'admin' : 'empleado';

    $existe = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
    $existe->bind_param("s", $usuario);
    $existe->execute();

    if (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $usuario)) {
        $error = 'El usuario debe tener entre 3 y 50 caracteres: letras, números, punto o guion bajo.';
    } elseif ($existe->get_result()->num_rows > 0) {
        $error = "Ya existe un usuario llamado \"$usuario\".";
    } elseif (mb_strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT); // nunca guardamos la contraseña tal cual
        $stmt = $conn->prepare("INSERT INTO usuarios (usuario, nombre, password_hash, rol) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $usuario, $nombre, $hash, $rol);
        $stmt->execute();
        registrar_auditoria($conn, 'usuarios', 'CREAR_USUARIO', "Nuevo usuario: $usuario ($rol)");
        echo "<script>window.location.href='index.php?seccion=usuarios';</script>";
        exit();
    }
}

$accion = $_GET['accion'] ?? 'seleccionar';
?>

<div class="card-container">
    <?php if ($accion == 'insertar' || $error): ?>
        <h2 class="card-title">Nuevo usuario</h2>

        <?php if ($error): ?>
            <div class="aviso aviso-error" role="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="index.php?seccion=usuarios&accion=insertar" method="POST">
            <input type="hidden" name="crear_usuario" value="1">
            <div class="form-group">
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" class="form-control" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" class="form-control" value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>" autocomplete="off" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña (mínimo 8 caracteres)</label>
                <input type="password" id="password" name="password" class="form-control" minlength="8" autocomplete="new-password" required>
            </div>
            <div class="form-group">
                <label for="rol">Perfil</label>
                <select id="rol" name="rol" class="form-control">
                    <option value="empleado">Empleado: usa el ERP</option>
                    <option value="admin">Administrador: además gestiona usuarios</option>
                </select>
            </div>
            <button type="submit" class="btn-actualizar">Crear usuario</button>
        </form>

    <?php else:
        $result = $conn->query("SELECT id, usuario, nombre, rol, ultimo_acceso, creado FROM usuarios ORDER BY rol ASC, nombre ASC");
    ?>
        <div class="card-header">
            <h2 class="card-title">Personas con acceso</h2>
            <a href="index.php?seccion=usuarios&accion=insertar" class="btn-actualizar">Nuevo usuario</a>
        </div>
        <table class="table-crimson">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Perfil</th>
                    <th>Último acceso</th>
                    <th>Operaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['nombre']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['usuario']); ?></td>
                        <td>
                            <?php if ($row['rol'] == 'admin'): ?>
                                <span class="badge badge-danger">Administrador</span>
                            <?php else: ?>
                                <span class="badge badge-info">Empleado</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $row['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($row['ultimo_acceso'])) : 'Nunca'; ?></td>
                        <td>
                            <?php if ($row['usuario'] === usuario_actual()): ?>
                                <span class="texto-tenue">Tu usuario</span>
                            <?php else: ?>
                                <a href="index.php?seccion=usuarios&op=eliminar&id=<?php echo $row['id']; ?>" data-titulo="¿Eliminar este usuario?" data-nombre="<?php echo htmlspecialchars($row['nombre'] . ' (' . $row['usuario'] . ')', ENT_QUOTES); ?>" data-confirmar="Ya no podrá entrar en la aplicación. Sus acciones seguirán en la auditoría." class="btn-eliminar">Eliminar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
