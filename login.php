<?php
// Pantalla de entrada: comprueba usuario y contraseña contra la tabla usuarios.
require_once 'config.php';

// Si todavía no hay ningún usuario, hay que crear el administrador en el instalador
if (!tabla_existe($conn, 'usuarios') || (int)$conn->query("SELECT COUNT(*) AS n FROM usuarios")->fetch_assoc()['n'] === 0) {
    header('Location: install.php?paso=3');
    exit();
}

// Si ya ha entrado, directo a la aplicación
if (isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$usuario_escrito = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario_escrito = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, usuario, nombre, password_hash, rol FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario_escrito);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();

    // password_verify compara la contraseña escrita con el código cifrado guardado
    if ($fila && password_verify($password, $fila['password_hash'])) {
        session_regenerate_id(true); // nueva sesión al entrar (evita que se reutilice una antigua)
        $_SESSION['usuario'] = $fila['usuario'];
        $_SESSION['nombre']  = $fila['nombre'];
        $_SESSION['rol']     = $fila['rol'];

        $conn->query("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = " . (int)$fila['id']);
        registrar_auditoria($conn, 'usuarios', 'LOGIN', 'Inicio de sesión');
        header('Location: index.php');
        exit();
    }

    // Mismo mensaje tanto si falla el usuario como la contraseña: no damos pistas
    $error = 'Usuario o contraseña incorrectos.';
    registrar_auditoria($conn, 'usuarios', 'LOGIN_FALLIDO', 'Intento fallido con el usuario "' . mb_substr($usuario_escrito, 0, 50) . '"', 'anónimo');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar · <?php echo APP_NAME; ?> <?php echo APP_MODULE; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="estilos.css?v=<?php echo time(); ?>">
</head>
<body class="pagina-login">
    <div class="login">
        <div class="login-marca">
            <span class="marca"><?php echo APP_NAME; ?> <strong><?php echo APP_MODULE; ?></strong></span>
            <p>Sistema de gestión empresarial</p>
        </div>

        <form method="POST" action="login.php" class="login-form">
            <h1 class="titulo-pagina">Entrar</h1>

            <?php if ($error): ?>
                <div class="aviso aviso-error" role="alert"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" class="form-control" value="<?php echo htmlspecialchars($usuario_escrito); ?>" autocomplete="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn-principal">Entrar</button>
        </form>
    </div>
</body>
</html>
