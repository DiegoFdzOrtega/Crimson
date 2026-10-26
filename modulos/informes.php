<?php
// =====================================================================
// Módulo de Informes y Análisis (RA3 a, c, d, h)
// Usa las consultas definidas en consultas.php (GROUP BY, SUM, JOIN...)
// y permite filtrar por fechas y exportar cada informe a CSV.
// =====================================================================

// Rango de fechas: por defecto, todo el histórico hasta hoy
$desde = fecha_valida($_GET['desde'] ?? '', '2000-01-01');
$hasta = fecha_valida($_GET['hasta'] ?? '', date('Y-m-d'));
if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde]; // si vienen al revés, las intercambiamos
}

$informes = informes_definidos($desde, $hasta, ajuste($conn, 'stock_minimo', 20));

// Exportar un informe concreto a CSV
if (isset($_GET['exportar']) && isset($informes[$_GET['exportar']])) {
    $inf = $informes[$_GET['exportar']];
    exportar_csv($conn, 'informes', 'informe_' . $_GET['exportar'], $inf['columnas'], $inf['sql']);
}

$kpi = resumen_ventas($conn, $desde, $hasta);
$filtro_url = 'desde=' . $desde . '&hasta=' . $hasta;

// Comparación con el periodo anterior de la misma duración
// (por ejemplo: si miras octubre, se compara con septiembre). Solo si hay un periodo elegido.
$comparar = $desde != '2000-01-01';
if ($comparar) {
    $dias = (strtotime($hasta) - strtotime($desde)) / 86400 + 1;
    $ant_hasta = date('Y-m-d', strtotime($desde . ' -1 day'));
    $ant_desde = date('Y-m-d', strtotime($ant_hasta . ' -' . ($dias - 1) . ' days'));
    $kpi_ant = resumen_ventas($conn, $ant_desde, $ant_hasta);
}

// Atajos de periodo
$hoy = date('Y-m-d');
$periodos = [
    'Este mes'          => [date('Y-m-01'), $hoy],
    'Últimos 30 días'   => [date('Y-m-d', strtotime('-29 days')), $hoy],
    'Este año'          => [date('Y-01-01'), $hoy],
    'Todo'              => ['2000-01-01', $hoy],
];

// Variación en % frente al periodo anterior, con flecha y color
function variacion($actual, $anterior) {
    if ($anterior == 0) {
        return $actual > 0 ? '<span class="variacion sube">Nuevo frente al periodo anterior</span>' : '';
    }
    $pct = round(($actual - $anterior) / $anterior * 100);
    $clase = $pct > 0 ? 'sube' : ($pct < 0 ? 'baja' : 'igual');
    $signo = $pct > 0 ? '+' : '';
    return "<span class=\"variacion $clase\">$signo$pct % frente al periodo anterior</span>";
}

// Formato de dinero al estilo español: 1.234,56 €
function euros($n) {
    return number_format((float)$n, 2, ',', '.') . ' €';
}
?>

<div class="card-container">
    <div class="card-header">
        <h2 class="card-title">Análisis de ventas</h2>
        <button type="button" class="btn-exportar btn-pdf" onclick="window.print()">Guardar en PDF</button>
    </div>

    <!-- Cabecera que solo aparece al imprimir o guardar en PDF -->
    <div class="solo-impresion cabecera-impresion">
        <strong><?php echo htmlspecialchars(ajuste($conn, 'empresa_nombre', 'Mi empresa')); ?></strong>
        Informe de ventas del <?php echo date('d/m/Y', strtotime($desde)); ?> al <?php echo date('d/m/Y', strtotime($hasta)); ?>.
        Generado el <?php echo date('d/m/Y H:i'); ?> por <?php echo htmlspecialchars($_SESSION['nombre'] ?? usuario_actual()); ?>.
    </div>

    <!-- Atajos de periodo -->
    <div class="periodos">
        <?php foreach ($periodos as $texto => [$d, $h]): ?>
            <a href="index.php?seccion=informes&desde=<?php echo $d; ?>&hasta=<?php echo $h; ?>"
               class="periodo <?php echo ($d == $desde && $h == $hasta) ? 'activo' : ''; ?>"><?php echo $texto; ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Filtro por rango de fechas -->
    <form action="index.php" method="GET" class="barra-filtros">
        <input type="hidden" name="seccion" value="informes">
        <div class="form-group">
            <label>Desde</label>
            <input type="date" name="desde" value="<?php echo $desde; ?>" class="form-control">
        </div>
        <div class="form-group">
            <label>Hasta</label>
            <input type="date" name="hasta" value="<?php echo $hasta; ?>" class="form-control">
        </div>
        <button type="submit" class="btn-actualizar">Aplicar filtro</button>
        <a href="index.php?seccion=informes" class="btn-secundario">Quitar filtro</a>
    </form>

    <!-- Indicadores clave del periodo (RA3 h) -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <span class="kpi-title">Total facturado</span>
            <span class="kpi-value"><?php echo euros($kpi['facturado']); ?></span>
            <span class="kpi-sub"><?php echo $kpi['pedidos']; ?> pedidos, <?php echo $kpi['unidades']; ?> unidades</span>
            <?php if ($comparar) echo variacion($kpi['facturado'], $kpi_ant['facturado']); ?>
        </div>
        <div class="kpi-card">
            <span class="kpi-title">Ticket medio</span>
            <span class="kpi-value"><?php echo euros($kpi['ticket_medio']); ?></span>
            <span class="kpi-sub">Importe medio por pedido</span>
            <?php if ($comparar) echo variacion($kpi['ticket_medio'], $kpi_ant['ticket_medio']); ?>
        </div>
        <div class="kpi-card">
            <span class="kpi-title">Mejor cliente</span>
            <span class="kpi-value kpi-texto"><?php echo htmlspecialchars($kpi['mejor_cliente']['nombre'] ?? '—'); ?></span>
            <span class="kpi-sub"><?php echo $kpi['mejor_cliente'] ? euros($kpi['mejor_cliente']['importe']) . ' comprados' : 'Sin ventas en el periodo'; ?></span>
        </div>
        <div class="kpi-card">
            <span class="kpi-title">Producto estrella</span>
            <span class="kpi-value kpi-texto"><?php echo htmlspecialchars($kpi['producto_estrella']['nombre'] ?? '—'); ?></span>
            <span class="kpi-sub"><?php echo $kpi['producto_estrella'] ? $kpi['producto_estrella']['unidades'] . ' unidades vendidas' : 'Sin ventas en el periodo'; ?></span>
        </div>
    </div>

    <?php if ($comparar): ?>
        <p class="texto-ayuda nota-comparacion">
            Periodo anterior usado para comparar: del <?php echo date('d/m/Y', strtotime($ant_desde)); ?>
            al <?php echo date('d/m/Y', strtotime($ant_hasta)); ?>
            (<?php echo $kpi_ant['pedidos']; ?> pedidos, <?php echo euros($kpi_ant['facturado']); ?>).
        </p>
    <?php endif; ?>
</div>

<?php foreach ($informes as $clave => $inf): ?>
    <?php
    // Si el informe depende de una tabla que aún no existe, lo saltamos
    if (isset($inf['tabla']) && !tabla_existe($conn, $inf['tabla'])) {
        continue;
    }
    $resultado = $conn->query($inf['sql']);
    $filas = $resultado->fetch_all(MYSQLI_ASSOC);

    // Valor máximo, para dibujar cada barra en proporción a él
    $maximo = 0;
    if ($inf['valor']) {
        foreach ($filas as $f) {
            $maximo = max($maximo, (float)$f[$inf['valor']]);
        }
    }
    ?>
    <div class="card-container seccion-informe">
        <div class="card-header">
            <h2 class="card-title"><?php echo $inf['titulo']; ?></h2>
            <a href="index.php?seccion=informes&exportar=<?php echo $clave; ?>&<?php echo $filtro_url; ?>" class="btn-exportar">Exportar CSV</a>
        </div>

        <table class="table-crimson">
            <thead>
                <tr>
                    <?php foreach ($inf['columnas'] as $col): ?>
                        <th><?php echo $col; ?></th>
                    <?php endforeach; ?>
                    <?php if ($inf['valor']): ?><th>Peso</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (count($filas) > 0): ?>
                    <?php foreach ($filas as $f): ?>
                        <tr>
                            <?php foreach ($f as $campo => $valor): ?>
                                <td>
                                    <?php
                                    if ($campo == 'importe' || $campo == 'precio') {
                                        echo euros($valor);
                                    } else {
                                        echo htmlspecialchars((string)$valor);
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                            <?php if ($inf['valor']):
                                $porcentaje = $maximo > 0 ? round((float)$f[$inf['valor']] / $maximo * 100) : 0; ?>
                                <td>
                                    <div class="barra-fondo"><div class="barra-relleno" style="width: <?php echo $porcentaje; ?>%;"></div></div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo count($inf['columnas']) + 1; ?>">Sin datos para este periodo.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endforeach; ?>
