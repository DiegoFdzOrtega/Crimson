<?php
// Guardamos la salida en memoria antes de enviarla al navegador.
// Así los módulos pueden descargar un CSV o redirigir aunque ya se haya escrito HTML.
ob_start();
require_once 'config.php';
requerir_login(); // sin sesión iniciada, a la pantalla de entrada

$seccion = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['seccion'] ?? 'pedidos');
$accion  = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['accion'] ?? 'seleccionar');

// Menú lateral: grupos de secciones
$menu = [
    'Entidades clave' => ['clientes', 'pedidos', 'productos', 'almacen'],
    'Análisis y datos' => ['informes', 'extracciones', 'rendimiento'],
    'Sistema' => es_admin() ? ['incidencias', 'auditoria', 'usuarios', 'copias'] : ['incidencias', 'auditoria'],
    'Módulos de gestión' => ['activos', 'administracion', 'catalogo', 'compras', 'contabilidad', 'crm', 'documental', 'fabricacion', 'finanzas'],
];

// Iconos del menú (dibujos SVG sencillos de líneas, 24x24)
function icono($seccion) {
    $trazos = [
        'clientes'       => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.2-5.5 6.5-5.5s5.9 1.9 6.5 5.5"/><path d="M16 4.8a3.3 3.3 0 0 1 0 6.4M18 14.8c2 .7 3.2 2.4 3.5 5.2"/>',
        'pedidos'        => '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4M9 12h7M9 16h7"/>',
        'productos'      => '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>',
        'almacen'        => '<path d="M3 21V8l9-5 9 5v13"/><path d="M7 21v-8h10v8M7 17h10"/>',
        'informes'       => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>',
        'extracciones'   => '<path d="M12 3v11M7.5 9.5 12 14l4.5-4.5"/><path d="M4 15v5h16v-5"/>',
        'rendimiento'    => '<path d="M4 15a8 8 0 1 1 16 0"/><path d="M12 15l4-5"/><circle cx="12" cy="15" r="1.2"/>',
        'usuarios'       => '<circle cx="12" cy="8" r="4"/><path d="M4 21c.8-4.2 3.9-6.5 8-6.5s7.2 2.3 8 6.5"/>',
        'incidencias'    => '<path d="M12 3 2.5 20h19z"/><path d="M12 10v4.5M12 17.5v.01"/>',
        'copias'         => '<ellipse cx="12" cy="5.5" rx="7.5" ry="2.5"/><path d="M4.5 5.5v13c0 1.4 3.4 2.5 7.5 2.5s7.5-1.1 7.5-2.5v-13M4.5 12c0 1.4 3.4 2.5 7.5 2.5s7.5-1.1 7.5-2.5"/>',
        'ajustes'        => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'auditoria'      => '<path d="M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'activos'        => '<rect x="3" y="5" width="18" height="12" rx="1.5"/><path d="M8 21h8M12 17v4"/>',
        'administracion' => '<circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M5.3 18.7l2.1-2.1M16.6 7.4l2.1-2.1"/>',
        'catalogo'       => '<path d="M4 4.5h6a2 2 0 0 1 2 2V20a2 2 0 0 0-2-2H4zM20 4.5h-6a2 2 0 0 0-2 2V20a2 2 0 0 1 2-2h6z"/>',
        'compras'        => '<path d="M3 4h2.5l2.2 11h10.6L20.5 7H7"/><circle cx="9" cy="19.5" r="1.3"/><circle cx="17" cy="19.5" r="1.3"/>',
        'contabilidad'   => '<rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="M8 7h8M8 11h2M11 11h2M14 11h2M8 15h2M11 15h2M14 15h2"/>',
        'crm'            => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9.5h8M8 12.5h5"/>',
        'documental'     => '<path d="M3 6.5h7l2 2h9V19H3z"/>',
        'fabricacion'    => '<path d="M3 21V10l5 3V10l5 3V10l5 3V5h3v16z"/>',
        'finanzas'       => '<path d="M4 7h14a2 2 0 0 1 2 2v9H4z"/><path d="M4 7l11-3v3M16 12.5h4"/>',
    ];
    $trazo = $trazos[$seccion] ?? '<circle cx="12" cy="12" r="8"/>';
    return '<svg class="icono" viewBox="0 0 24 24" aria-hidden="true">' . $trazo . '</svg>';
}

// Datos para la franja de indicadores
$tabla_check = $seccion;
if ($tabla_check == 'auditoria' || $tabla_check == 'extracciones') { $tabla_check = 'auditoria_sistema'; } // guardan sus datos en la auditoría
if (in_array($tabla_check, ['informes', 'rendimiento'])) { $tabla_check = 'pedidos'; } // analizan los pedidos

$total_registros = 0;
if (tabla_existe($conn, $tabla_check)) {
    $total_registros = (int)$conn->query("SELECT COUNT(*) AS total FROM `$tabla_check`")->fetch_assoc()['total'];
}

$ultimo_movimiento = null;
if (tabla_existe($conn, 'auditoria_sistema')) {
    $ultimo_movimiento = $conn->query("SELECT usuario, modulo, accion_realizada, fecha FROM auditoria_sistema ORDER BY fecha DESC, id DESC LIMIT 1")->fetch_assoc();
}

$titulo = nombre_modulo($seccion);

// Incidencias sin resolver, para el contador del menú
$incidencias_abiertas = 0;
if (tabla_existe($conn, 'incidencias')) {
    $incidencias_abiertas = (int)$conn->query("SELECT COUNT(*) AS n FROM incidencias WHERE estado <> 'resuelta'")->fetch_assoc()['n'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?> · <?php echo APP_NAME; ?> <?php echo APP_MODULE; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="estilos.css?v=<?php echo time(); ?>">
</head>
<body>

<div class="app">

    <nav class="sidebar" aria-label="Menú principal">
        <a href="index.php" class="marca">
            <span class="marca-sello" aria-hidden="true"></span>
            <span><?php echo APP_NAME; ?> <strong><?php echo APP_MODULE; ?></strong></span>
        </a>
        <span class="empresa"><?php echo htmlspecialchars(ajuste($conn, 'empresa_nombre', 'Mi empresa')); ?></span>

        <?php foreach ($menu as $grupo => $secciones): ?>
            <div class="nav-group">
                <span class="nav-group-title"><?php echo $grupo; ?></span>
                <?php foreach ($secciones as $mod): ?>
                    <a href="index.php?seccion=<?php echo $mod; ?>" class="nav-item <?php echo $seccion == $mod ? 'active' : ''; ?>"
                       <?php echo $seccion == $mod ? 'aria-current="page"' : ''; ?>>
                        <?php echo icono($mod); ?>
                        <span><?php echo nombre_modulo($mod); ?></span>
                        <?php if ($mod == 'incidencias' && $incidencias_abiertas > 0): ?>
                            <span class="contador" title="Incidencias sin resolver"><?php echo $incidencias_abiertas; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="usuario">
            <span class="usuario-avatar" aria-hidden="true"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($_SESSION['nombre'], 0, 1))); ?></span>
            <span class="usuario-datos">
                <span><?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
                <span class="usuario-rol"><?php echo es_admin() ? 'Administrador' : 'Empleado'; ?></span>
            </span>
            <a href="index.php?seccion=ajustes" class="usuario-salir <?php echo $seccion == 'ajustes' ? 'activo' : ''; ?>" title="Ajustes" aria-label="Ajustes">
                <?php echo icono('ajustes'); ?>
            </a>
            <a href="logout.php" class="usuario-salir usuario-salir-2" title="Cerrar sesión" aria-label="Cerrar sesión">
                <svg class="icono" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/></svg>
            </a>
        </div>
    </nav>

    <main class="content">

        <header class="cabecera">
            <div>
                <h1 class="titulo-pagina"><?php echo $titulo; ?></h1>
                <?php if ($accion == 'insertar'): ?>
                    <p class="subtitulo">Nuevo registro</p>
                <?php elseif ($accion == 'actualizar'): ?>
                    <p class="subtitulo">Editando registro</p>
                <?php elseif ($accion == 'ver'): ?>
                    <p class="subtitulo">Detalle</p>
                <?php endif; ?>
            </div>

            <div class="acciones">
                <?php if ($accion != 'seleccionar'): ?>
                    <a href="index.php?seccion=<?php echo $seccion; ?>" class="btn-secundario">Volver al listado</a>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($accion == 'seleccionar' && !in_array($seccion, ['informes', 'rendimiento', 'extracciones', 'ajustes', 'copias', 'usuarios', 'incidencias'])): ?>
            <dl class="franja">
                <div>
                    <dt><?php echo $seccion == 'auditoria' ? 'Acciones registradas' : 'Registros'; ?></dt>
                    <dd class="cifra"><?php echo number_format($total_registros, 0, ',', '.'); ?></dd>
                </div>
                <div>
                    <dt>Base de datos</dt>
                    <dd><span class="punto-ok" aria-hidden="true"></span>Conectada a diegofo_crimson</dd>
                </div>
                <div>
                    <dt>Último movimiento</dt>
                    <dd>
                        <?php if ($ultimo_movimiento): ?>
                            <?php echo nombre_modulo($ultimo_movimiento['modulo']); ?>: <?php echo describir_accion($ultimo_movimiento['accion_realizada']); ?>
                            <span class="dd-sub"><?php echo date('d/m/Y H:i', strtotime($ultimo_movimiento['fecha'])); ?>, por <?php echo htmlspecialchars($ultimo_movimiento['usuario']); ?></span>
                        <?php else: ?>
                            Sin actividad todavía
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
        <?php endif; ?>

        <?php
        $vista_file = "modulos/" . $seccion . ".php";

        if (file_exists($vista_file)) {
            include $vista_file;
        } elseif (file_exists("modulos/generico.php")) {
            include "modulos/generico.php";
        } else {
            echo "<div class='card-container'><h2>No se encuentra el módulo</h2><p>Falta el archivo modulos/generico.php.</p></div>";
        }
        ?>
    </main>
</div>

<!-- Ventana de confirmación (sustituye al aviso del navegador al eliminar) -->
<dialog id="confirmar" class="dialogo" aria-labelledby="confirmar-titulo">
    <h2 id="confirmar-titulo" class="dialogo-titulo">¿Eliminar?</h2>
    <p id="confirmar-nombre" class="dialogo-nombre"></p>
    <p id="confirmar-texto" class="dialogo-texto"></p>
    <p class="dialogo-nota">Esta acción no se puede deshacer.</p>
    <div class="dialogo-botones">
        <button type="button" class="btn-secundario" id="confirmar-cancelar" autofocus>Cancelar</button>
        <button type="button" class="btn-peligro" id="confirmar-aceptar">Eliminar</button>
    </div>
</dialog>

<script>
// Cualquier enlace o formulario con el atributo data-titulo pide confirmación
// en esta ventana antes de continuar. Atributos que puede llevar:
//   data-titulo    -> pregunta ("¿Eliminar este cliente?")
//   data-nombre    -> qué se va a borrar ("Comercial Martínez S.L.")
//   data-confirmar -> qué más pasará (opcional)
//   data-boton     -> texto del botón rojo (por defecto "Eliminar")
const dialogo  = document.getElementById('confirmar');
const titulo   = document.getElementById('confirmar-titulo');
const nombre   = document.getElementById('confirmar-nombre');
const texto    = document.getElementById('confirmar-texto');
const aceptar  = document.getElementById('confirmar-aceptar');
let continuar = null; // qué hacer si el usuario confirma

function pedirConfirmacion(elemento, accion) {
    titulo.textContent  = elemento.dataset.titulo || '¿Eliminar?';
    nombre.textContent  = elemento.dataset.nombre || '';
    texto.textContent   = elemento.dataset.confirmar || '';
    nombre.hidden = !elemento.dataset.nombre;
    texto.hidden  = !elemento.dataset.confirmar;
    aceptar.textContent = elemento.dataset.boton || 'Eliminar';
    continuar = accion;
    dialogo.showModal();
}

// Enlaces de "Eliminar"
document.addEventListener('click', (e) => {
    const enlace = e.target.closest('a[data-titulo]');
    if (!enlace) return;
    e.preventDefault();
    pedirConfirmacion(enlace, () => { window.location.href = enlace.href; });
});

// Formularios (por ejemplo, "Borrar datos de prueba")
document.addEventListener('submit', (e) => {
    const form = e.target.closest('form[data-titulo]');
    if (!form) return;
    e.preventDefault();
    pedirConfirmacion(form, () => { form.submit(); });
});

aceptar.addEventListener('click', () => { dialogo.close(); if (continuar) continuar(); });
document.getElementById('confirmar-cancelar').addEventListener('click', () => dialogo.close());

// Clic fuera de la ventana = cancelar
dialogo.addEventListener('click', (e) => { if (e.target === dialogo) dialogo.close(); });
</script>

</body>
</html>
