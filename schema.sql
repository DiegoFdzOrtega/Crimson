-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS diegofo_crimson DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE diegofo_crimson;

-- Tabla de Clientes
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- Tabla de Productos
CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    precio DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB;

-- Tabla de Pedidos (con claves foráneas)
CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    numero INT NOT NULL,
    cliente_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de Almacén
CREATE TABLE IF NOT EXISTS almacen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    concepto VARCHAR(100) NOT NULL,
    cantidad INT NOT NULL,
    ubicacion VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

-- Tabla de Usuarios. La contraseña se guarda cifrada con password_hash()
-- rol: 'admin' (gestiona usuarios) o 'empleado' (usa el ERP)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(20) NOT NULL DEFAULT 'empleado',
    ultimo_acceso DATETIME NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de Ajustes (configuración de la aplicación: clave -> valor)
CREATE TABLE IF NOT EXISTS ajustes (
    clave VARCHAR(50) PRIMARY KEY,
    valor VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- Tabla de Incidencias (problemas detectados al usar el ERP y su solución)
CREATE TABLE IF NOT EXISTS incidencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    descripcion TEXT NOT NULL,
    prioridad VARCHAR(10) NOT NULL DEFAULT 'media',   -- baja, media, alta
    estado VARCHAR(15) NOT NULL DEFAULT 'abierta',    -- abierta, en_curso, resuelta
    solucion TEXT NULL,
    creada_por VARCHAR(50) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion DATETIME NULL,
    INDEX idx_incidencias_estado (estado)
) ENGINE=InnoDB;

-- Tabla de Auditoría (registro de cada acción del sistema y de quién la hizo)
CREATE TABLE IF NOT EXISTS auditoria_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL DEFAULT 'sistema',
    modulo VARCHAR(50) NOT NULL,
    accion_realizada VARCHAR(100) NOT NULL,
    detalle VARCHAR(255) NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Actualización para el RA3 (si la base de datos ya venía instalada del RA2)
-- "IF NOT EXISTS" hace que no dé error si la columna o el índice ya existen.
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS cantidad INT NOT NULL DEFAULT 1;
-- Índice por fecha: acelera los informes que filtran por un rango de fechas (RA3 f)
ALTER TABLE pedidos ADD INDEX IF NOT EXISTS idx_pedidos_fecha (fecha);
-- La auditoría guarda ahora qué usuario hizo cada acción
ALTER TABLE auditoria_sistema ADD COLUMN IF NOT EXISTS usuario VARCHAR(50) NOT NULL DEFAULT 'sistema' AFTER id;

-- Registros iniciales de prueba
-- INSERT IGNORE: si el registro ya existe (por ejemplo, al instalar dos veces), lo salta sin dar error
INSERT IGNORE INTO clientes (id, nombre) VALUES
(1, 'Cliente Ejemplo 1'),
(2, 'Cliente Ejemplo 2');

INSERT IGNORE INTO productos (id, nombre, precio) VALUES
(1, 'Producto Ejemplo 1', 99.99),
(2, 'Producto Ejemplo 2', 149.50);

INSERT IGNORE INTO pedidos (id, fecha, numero, cliente_id, producto_id, cantidad) VALUES
(1, '2026-09-17', 1, 1, 1, 2),
(2, '2026-10-19', 2, 2, 2, 1);

INSERT IGNORE INTO almacen (id, concepto, cantidad, ubicacion) VALUES
(1, 'Lote Producto Ejemplo 1', 150, 'Estantería A-1'),
(2, 'Lote Producto Ejemplo 2', 12, 'Estantería B-3');

INSERT IGNORE INTO ajustes (clave, valor) VALUES
('empresa_nombre', 'Mi empresa'),
('stock_minimo', '20');
