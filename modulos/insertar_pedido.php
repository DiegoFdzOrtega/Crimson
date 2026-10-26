<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fecha = $_POST['fecha'];
    $numero = intval($_POST['numero']);
    $cliente_id = intval($_POST['cliente_id']);
    $producto_id = intval($_POST['producto_id']);

    $stmt = $conn->prepare("INSERT INTO pedidos (fecha, numero, cliente_id, producto_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siii", $fecha, $numero, $cliente_id, $producto_id);
    
    if ($stmt->execute()) {
        echo "<script>window.location.href='index.php?seccion=pedidos';</script>";
    } else {
        echo "<p style='color:red;'>Error al insertar el pedido.</p>";
    }
}
?>

<div>
    <h2>Nuevo pedido</h2>
    <form action="index.php?seccion=pedidos&accion=insertar" method="POST">
        <div>
            <label>Fecha</label>
            <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div>
            <label>Número de pedido</label>
            <input type="number" name="numero" required>
        </div>
        <div>
            <label>ID:</label>
            <input type="number" name="cliente_id" required>
        </div>
        <div>
            <label>ID:</label>
            <input type="number" name="producto_id" required>
        </div>
        <button type="submit" class="btn-actualizar">Guardar pedido</button>
    </form>
</div>