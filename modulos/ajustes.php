<?php
// Ajustes: cualquier usuario puede cambiar su contraseña;
// el administrador además configura los datos de la empresa y del almacén.

$mensaje = '';
$error = '';

// 1. Cambiar mi contraseña
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_password'])) {
    $actual = $_POST['actual'] ?? '';
    $nueva  = $_POST['nueva'] ?? '';
    $usuario = usuario_actual();

    $stmt = $conn->prepare("SELECT password_hash FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();

    if (!$fila || !password_verify($actual, $fila['password_hash'])) {
        $error = 'La contraseña actual no es correcta.';
    } elseif (mb_strlen($nueva) < 8) {
        $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
    } elseif ($nueva !== ($_POST['nueva2'] ?? '')) {
        $error = 'Las dos contraseñas nuevas no coinciden.';
    } else {
        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET password_hash = ? WHERE usuario = ?");
        $stmt->bind_param("ss", $hash, $usuario);
        $stmt->execute();
        registrar_auditoria($conn, 'ajustes', 'CAMBIAR_PASSWORD', 'Contraseña cambiada');
        $mensaje = 'Contraseña cambiada.';
    }
}

// 2. Ajustes de la empresa (solo administrador)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_ajustes']) && es_admin()) {
    $empresa = trim($_POST['empresa_nombre'] ?? '');
    $stock   = max(1, intval($_POST['stock_minimo'] ?? 20));
    guardar_ajuste($conn, 'empresa_nombre', $empresa !== '' ? mb_substr($empresa, 0, 100) : 'Mi empresa');
    guardar_ajuste($conn, 'stock_minimo', (string)$stock);
    registrar_auditoria($conn, 'ajustes', 'GUARDAR_AJUSTES', "Empresa: $empresa, stock mínimo: $stock");
    echo "<script>window.location.href='index.php?seccion=ajustes&ok=1';</script>";
    exit();
}
if (isset($_GET['ok'])) {
    $mensaje = 'Ajustes guardados.';
}
?>

<?php if ($mensaje): ?>
    <div class="aviso aviso-ok" role="status"><?php echo $mensaje; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="aviso aviso-error" role="alert"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (es_admin()): ?>
<div class="card-container">
    <h2 class="card-title">Empresa</h2>
    <form action="index.php?seccion=ajustes" method="POST">
        <input type="hidden" name="guardar_ajustes" value="1">
        <div class="form-group">
            <label for="empresa_nombre">Nombre de la empresa</label>
            <input type="text" id="empresa_nombre" name="empresa_nombre" class="form-control" maxlength="100"
                   value="<?php echo htmlspecialchars(ajuste($conn, 'empresa_nombre', 'Mi empresa')); ?>" required>
        </div>
        <div class="form-group">
            <label for="stock_minimo">Stock mínimo (avisa en el informe de stock bajo)</label>
            <input type="number" id="stock_minimo" name="stock_minimo" class="form-control" min="1"
                   value="<?php echo (int)ajuste($conn, 'stock_minimo', 20); ?>" required>
        </div>
        <button type="submit" class="btn-actualizar">Guardar ajustes</button>
    </form>
</div>
<?php endif; ?>

<div class="card-container">
    <h2 class="card-title">Cambiar mi contraseña</h2>
    <form action="index.php?seccion=ajustes" method="POST">
        <input type="hidden" name="cambiar_password" value="1">
        <input type="text" name="usuario" value="<?php echo htmlspecialchars(usuario_actual()); ?>" autocomplete="username" hidden>
        <div class="form-group">
            <label for="actual">Contraseña actual</label>
            <input type="password" id="actual" name="actual" class="form-control" autocomplete="current-password" required>
        </div>
        <div class="form-group">
            <label for="nueva">Nueva contraseña (mínimo 8 caracteres)</label>
            <input type="password" id="nueva" name="nueva" class="form-control" minlength="8" autocomplete="new-password" required>
        </div>
        <div class="form-group">
            <label for="nueva2">Repite la nueva contraseña</label>
            <input type="password" id="nueva2" name="nueva2" class="form-control" minlength="8" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn-actualizar">Cambiar contraseña</button>
    </form>
</div>
