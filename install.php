<?php
require_once 'config.php';

$paso = (int)($_GET['paso'] ?? 1);

// ¿Hay ya algún usuario? (solo se puede crear el administrador si no hay ninguno)
function hay_usuarios($conn) {
    return tabla_existe($conn, 'usuarios')
        && (int)$conn->query("SELECT COUNT(*) AS n FROM usuarios")->fetch_assoc()['n'] > 0;
}

// Paso 3: crear el primer administrador
$error_admin = '';
if ($paso == 3) {
    if (!tabla_existe($conn, 'usuarios')) {
        header('Location: install.php?paso=2'); // primero hay que crear las tablas
        exit();
    }
    if (hay_usuarios($conn)) {
        header('Location: login.php'); // ya existe un administrador
        exit();
    }
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $usuario  = trim($_POST['usuario'] ?? '');
        $nombre   = trim($_POST['nombre'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $usuario)) {
            $error_admin = 'El usuario debe tener entre 3 y 50 caracteres: letras, números, punto o guion bajo.';
        } elseif (mb_strlen($password) < 8) {
            $error_admin = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($password !== ($_POST['password2'] ?? '')) {
            $error_admin = 'Las dos contraseñas no coinciden.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $rol = 'admin';
            $stmt = $conn->prepare("INSERT INTO usuarios (usuario, nombre, password_hash, rol) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $usuario, $nombre, $hash, $rol);
            $stmt->execute();
            registrar_auditoria($conn, 'usuarios', 'CREAR_USUARIO', "Administrador inicial: $usuario", 'instalador');
            header('Location: login.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación · diegofo crimson</title>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="estilos.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="pagina-instalador">
        <div class="card-container">
            <h1 class="titulo-pagina instalador-titulo">Instalación</h1>

            <?php if ($paso == 1): ?>
                <h3>Paso 1 de 3: requisitos</h3>
                <table class="table-crimson">
                    <tr>
                        <th>Requisito</th>
                        <th>Estado</th>
                    </tr>
                    <tr>
                        <td>Versión PHP (>= 8.0)</td>
                        <td><span class="badge badge-success">PHP <?php echo phpversion(); ?></span></td>
                    </tr>
                    <tr>
                        <td>Extensión MySQLi</td>
                        <td><span class="badge badge-success"><?php echo extension_loaded('mysqli') ? 'Instalada' : 'Falta'; ?></span></td>
                    </tr>
                    <tr>
                        <td>Conexión SGBD (MySQL)</td>
                        <td>
                            <?php if (!$conn->connect_error): ?>
                                <span class="badge badge-success">Conectado a MySQL</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Error de conexión</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <a href="install.php?paso=2" class="btn-actualizar">Instalar la base de datos</a>

            <?php elseif ($paso == 2): ?>
                <h3>Paso 2 de 3: base de datos</h3>
                <?php
                $sql = file_get_contents(__DIR__ . '/schema.sql');
                $instalado = false;
                // try/catch: si una orden SQL falla, mostramos el error en vez de romper la página
                try {
                    $conn->multi_query($sql);
                    do {
                        if ($result = $conn->store_result()) { $result->free(); }
                    } while ($conn->more_results() && $conn->next_result());

                    registrar_auditoria($conn, 'instalador', 'INSTALAR', 'Esquema de base de datos instalado/actualizado', 'instalador');
                    $instalado = true;
                    echo "<p class='resultado-ok'>Base de datos instalada y actualizada.</p>";
                } catch (mysqli_sql_exception $e) {
                    echo "<p class='resultado-error'>No se pudo instalar. MySQL respondió: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
                ?>
                <?php if ($instalado && !hay_usuarios($conn)): ?>
                    <a href="install.php?paso=3" class="btn-actualizar">Crear el administrador</a>
                <?php else: ?>
                    <a href="login.php" class="btn-actualizar">Entrar en la aplicación</a>
                <?php endif; ?>

            <?php elseif ($paso == 3): ?>
                <h3>Paso 3 de 3: administrador</h3>
                <p class="texto-ayuda">Crea el primer usuario. Tendrá el perfil de administrador y podrá dar de alta al resto.</p>

                <?php if ($error_admin): ?>
                    <div class="aviso aviso-error" role="alert"><?php echo $error_admin; ?></div>
                <?php endif; ?>

                <form method="POST" action="install.php?paso=3">
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="usuario">Usuario</label>
                        <input type="text" id="usuario" name="usuario" class="form-control" value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>" autocomplete="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña (mínimo 8 caracteres)</label>
                        <input type="password" id="password" name="password" class="form-control" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="form-group">
                        <label for="password2">Repite la contraseña</label>
                        <input type="password" id="password2" name="password2" class="form-control" minlength="8" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn-actualizar">Crear administrador</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
