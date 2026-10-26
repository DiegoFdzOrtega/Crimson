# diegofo | crimson — Sistema de Gestión Empresarial (SGE / ERP-CRM)

**Autor:** Diego Fernández Ortega
**Módulo profesional:** Sistemas de Gestión Empresarial (SGE) — DAM
**Entrega:** Resultado de Aprendizaje 3 (RA3)
**Tecnologías:** PHP 8.x + MySQL/MariaDB sobre XAMPP
**Ruta local:** `C:\xampp\htdocs\crimson\`

---

## 1. Resultado de aprendizaje

> **RA3.** Realiza operaciones de gestión, consulta y análisis de la información siguiendo las especificaciones de diseño y utilizando las herramientas proporcionadas por los sistemas ERP-CRM.

El proyecto continúa el ERP **diegofo | crimson** de las entregas RA1 (análisis de necesidades) y RA2 (implantación y configuración). En el RA3 se añaden las herramientas para **consultar, analizar, exportar y automatizar** la información que guarda el ERP, junto con la parte de **sistema** que necesita una aplicación real: usuarios, copias de seguridad, ajustes e incidencias.

---

## 2. Novedades del RA3

| Novedad | Archivos | Criterios |
|---|---|---|
| **Informes** con indicadores, ventas por cliente, producto y mes, y stock bajo | `modulos/informes.php`, `consultas.php` | a, c, h |
| **Comparación con el periodo anterior** y atajos de periodo (este mes, últimos 30 días, este año) | `modulos/informes.php` | h |
| **Exportación a CSV** en todos los módulos e informes, y **guardado en PDF** de los informes | `consultas.php`, `estilos.css` | d |
| **Extracción automática** diaria de informes y copia de seguridad con el Programador de tareas | `extraccion_automatica.php`, `extraccion_programada.bat`, `modulos/extracciones.php` | e |
| **Rendimiento:** prueba de carga, tiempos de consulta, `EXPLAIN`, índices y tamaño de tablas | `modulos/rendimiento.php` | f |
| **Usuarios y login** con perfiles administrador y empleado | `login.php`, `logout.php`, `modulos/usuarios.php` | a, b, g |
| **Copias de seguridad**: crear, descargar y restaurar la base de datos completa | `modulos/copias.php`, `consultas.php` | d, e |
| **Ajustes**: nombre de la empresa, stock mínimo y cambio de contraseña | `modulos/ajustes.php` | b |
| **Incidencias**: registro de problemas, prioridad, estado y solución | `modulos/incidencias.php` | g |
| **Auditoría** con el usuario que hizo cada acción | `config.php`, `modulos/auditoria.php` | e, g |
| Campo **cantidad** en los pedidos e índice por fecha | `schema.sql`, `modulos/pedidos.php` | b, f |
| Interfaz renovada, ventana propia para confirmar borrados y diseño adaptado al móvil | `index.php`, `estilos.css` | b |

---

## 3. Estructura del proyecto

```text
crimson/
├── config.php                 # Conexión, sesión, usuarios, auditoría y utilidades
├── consultas.php              # Consultas de informes, KPI, CSV, extracción, copias y ajustes
├── estilos.css                # Hoja de estilos (pantalla e impresión/PDF)
├── index.php                  # Enrutador principal, menú lateral e indicadores
├── install.php                # Instalador: requisitos, base de datos y primer administrador
├── login.php / logout.php     # Entrada y salida de la aplicación
├── schema.sql                 # Estructura de la base de datos, índices y datos iniciales
├── extraccion_automatica.php  # Proceso automático (se ejecuta desde la consola)
├── extraccion_programada.bat  # Lanzador para el Programador de tareas de Windows
├── exportaciones/             # CSV generados por el proceso automático (se crea sola)
├── copias/                    # Copias de seguridad .sql (se crea sola, bloqueada al navegador)
├── .gitignore                 # Evita subir a GitHub los CSV y las copias
├── readme.md                  # Esta memoria
└── modulos/
    ├── ajustes.php            # Ajustes de la empresa y cambio de contraseña
    ├── almacen.php            # Existencias, cantidad y ubicación
    ├── auditoria.php          # Registro de actividad (quién, qué y cuándo)
    ├── clientes.php           # Cartera de clientes
    ├── copias.php             # Copias de seguridad
    ├── extracciones.php       # Panel de extracciones automáticas
    ├── generico.php           # Motor genérico (Compras, CRM, Finanzas...)
    ├── incidencias.php        # Registro de incidencias
    ├── informes.php           # Informes y análisis de ventas
    ├── insertar_pedido.php    # Formulario antiguo de pedidos (sustituido por pedidos.php)
    ├── pedidos.php            # Pedidos con cantidad e importe
    ├── productos.php          # Catálogo de productos
    ├── rendimiento.php        # Verificación del rendimiento
    └── usuarios.php           # Gestión de usuarios (solo administrador)
```

### Modelo de datos

```text
clientes (id, nombre)
productos (id, nombre, precio)
pedidos (id, fecha, numero, cliente_id → clientes.id, producto_id → productos.id, cantidad)
almacen (id, concepto, cantidad, ubicacion)
usuarios (id, usuario, nombre, password_hash, rol, ultimo_acceso, creado)
ajustes (clave, valor)
incidencias (id, titulo, modulo, descripcion, prioridad, estado, solucion, creada_por, fecha_creacion, fecha_resolucion)
auditoria_sistema (id, usuario, modulo, accion_realizada, detalle, fecha)
```

El **importe** de cada pedido se calcula al consultar: `cantidad × precio del producto`.

---

## 4. Justificación de los criterios de evaluación

### a) Herramientas y lenguajes de consulta y manipulación de datos

Se usa **SQL** a través de la extensión **MySQLi** de PHP:

- **Manipulación de datos:** `INSERT`, `UPDATE` y `DELETE` en todos los módulos. Pedidos, usuarios, ajustes, incidencias y auditoría usan **consultas preparadas**: los datos viajan separados de la orden SQL, lo que evita la inyección de código (inyección SQL).
- **Consultas de análisis** (`consultas.php`):
  - `JOIN` para unir pedidos con clientes y productos.
  - `GROUP BY` para agrupar ventas por cliente, producto y mes.
  - Funciones de agregado: `COUNT`, `SUM`, `ROUND` y `COALESCE`.
  - `DATE_FORMAT` para agrupar por mes y `BETWEEN` para filtrar por fechas.
- **Búsquedas** con `LIKE` en los módulos generales y en la auditoría (por usuario, módulo o acción).
- **Herramientas del SGBD:** `EXPLAIN`, `SHOW INDEX`, `SHOW CREATE TABLE` e `information_schema`, usadas en Rendimiento y en las copias de seguridad.

Ejemplo (ventas por cliente):

```sql
SELECT c.nombre AS cliente, COUNT(p.id) AS pedidos, SUM(p.cantidad) AS unidades,
       ROUND(SUM(p.cantidad * pr.precio), 2) AS importe
FROM pedidos p
JOIN clientes c   ON c.id = p.cliente_id
JOIN productos pr ON pr.id = p.producto_id
WHERE p.fecha BETWEEN '2026-01-01' AND '2026-12-31'
GROUP BY c.id, c.nombre
ORDER BY importe DESC;
```

### b) Generación de formularios

- Formularios de **alta y edición** en Clientes, Productos, Pedidos, Almacén, Usuarios, Incidencias, Ajustes y en todos los módulos generales.
- El formulario de pedidos usa **desplegables** que se rellenan con los clientes y productos de la base de datos, así que no se puede elegir uno que no existe.
- **Validaciones:** campos obligatorios, cantidad mínima de 1, número de pedido propuesto automáticamente, fecha comprobada, nombre de usuario con formato válido y contraseñas de 8 caracteres como mínimo (escritas dos veces al crearlas).
- **Formularios de filtro:** rango de fechas y atajos de periodo en Informes, estado en Incidencias y buscador en módulos y auditoría.
- Antes de **eliminar o restaurar** se abre una ventana de confirmación propia que indica qué se va a borrar y qué más se borra con ello. Por ejemplo, al borrar un cliente avisa de que también se borran sus pedidos (por el `ON DELETE CASCADE`).

### c) Generación de informes

El módulo **Informes** genera cuatro informes a partir de los datos:

1. **Ventas por cliente:** pedidos, unidades e importe de cada cliente.
2. **Ventas por producto:** unidades vendidas e importe de cada producto.
3. **Evolución de ventas por mes.**
4. **Stock bajo en almacén:** artículos por debajo del stock mínimo configurado en Ajustes.

Cada informe se puede filtrar por fechas y muestra una **barra** con el peso de cada fila frente a la mayor.

### d) Exportación de datos e informes

- Todos los módulos, los informes, la auditoría y las incidencias tienen un botón **Exportar CSV**.
- El CSV lleva **BOM UTF-8** (una marca invisible al principio que indica a Excel que lea bien las tildes y la ñ) y usa **punto y coma** como separador, que es el que espera Excel en español.
- La función común `exportar_csv()` vacía la página antes de descargar, para que el archivo contenga solo los datos.
- **Guardar en PDF:** el botón de Informes abre la ventana de impresión del navegador con una hoja de estilo propia para papel (`@media print`). Oculta el menú y los botones y añade una cabecera con la empresa, el periodo, la fecha y el usuario que lo genera.
- **Copias de seguridad:** toda la base de datos se exporta a un archivo `.sql` que se puede descargar.
- Cada exportación queda anotada en la auditoría.

### e) Automatización de extracciones mediante procesos

- `extraccion_automatica.php` es un **proceso** que se ejecuta desde la consola, sin navegador. Genera todos los informes como CSV en `exportaciones/` y una **copia de seguridad** de la base de datos en `copias/`, con la fecha y la hora en el nombre.
- `extraccion_programada.bat` lo lanza y guarda su salida en `exportaciones/registro_extracciones.log`.
- La tarea se programa en el **Programador de tareas de Windows** para que se ejecute todos los días a las 08:00:

```bat
schtasks /create /tn "SGE diegofo - Extraccion" /tr "\"C:\xampp\htdocs\crimson\extraccion_programada.bat\"" /sc daily /st 08:00
schtasks /run /tn "SGE diegofo - Extraccion"
```

- El módulo **Extracciones** permite lanzarla a mano, ver el historial (programadas y manuales) y descargar los archivos.
- Otras automatizaciones: el sistema crea solo la base de datos y las tablas que faltan, la auditoría registra cada operación con su usuario, y el contador del menú avisa de las incidencias sin resolver.

### f) Verificación del rendimiento

El módulo **Rendimiento** permite:

- Hacer una **prueba de carga**: inserta miles de pedidos de prueba dentro de una **transacción** (todas las inserciones se confirman de golpe al final, lo que es mucho más rápido) y mide cuántos pedidos por segundo admite.
- **Cronometrar** 7 consultas reales (5 repeticiones de cada una, con tiempo medio y máximo), marcadas con un semáforo: verde < 10 ms, amarillo < 100 ms, rojo ≥ 100 ms.
- Ver el **plan de ejecución** (`EXPLAIN`), es decir, si MySQL lee la tabla entera (`ALL`) o usa un índice (`range`, `ref`).
- Ver el **tamaño** de cada tabla y los **índices** de la tabla pedidos.

**Optimización aplicada:** índice `idx_pedidos_fecha`. Funciona como el índice de un libro: MySQL salta directamente a las fechas que busca en vez de leer todos los pedidos. Lo usan los informes y la consulta "Pedidos del último mes". La tabla de incidencias tiene también un índice por estado.

**Resultados de las pruebas** _(a completar con las medidas reales tomadas en Rendimiento)_:

| Prueba | Datos | Resultado |
|---|---|---|
| Prueba de carga | 1.000 pedidos | _(tiempo y pedidos/segundo)_ |
| Prueba de carga | 10.000 pedidos | _(tiempo y pedidos/segundo)_ |
| Pedidos del último mes (con índice) | 10.000 pedidos | _(tiempo medio y tipo de acceso)_ |
| Informe: ventas por cliente | 10.000 pedidos | _(tiempo medio)_ |
| Listado de pedidos (JOIN) | 10.000 pedidos | _(tiempo medio)_ |

_Conclusión:_ _(a completar: si el sistema responde bien con muchos datos y qué consulta es la más lenta)_

### g) Documentación de operaciones e incidencias

**Registro dentro de la aplicación:**

- **Auditoría:** cada alta, cambio, borrado, exportación, extracción, copia, entrada, salida e intento de acceso fallido queda guardado con el usuario, el módulo, la acción y la fecha. Se puede buscar y exportar a CSV.
- **Incidencias:** cualquier usuario puede abrir una incidencia (título, módulo, prioridad y descripción). El administrador la pasa a "en curso" o "resuelta" y escribe la solución aplicada; la fecha de resolución se guarda sola.

**Operaciones realizadas para comprobar el RA3:**

1. Reinstalar desde `install.php` para aplicar los cambios (cantidad, índices y tablas nuevas) y crear el administrador.
2. Crear un usuario con perfil empleado y comprobar que no ve Usuarios ni Copias.
3. Dar de alta clientes, productos y pedidos con cantidad.
4. Lanzar pruebas de carga de 1.000 y 10.000 pedidos en Rendimiento y anotar los tiempos.
5. Consultar los informes con distintos periodos y comprobar la comparación con el periodo anterior.
6. Exportar a CSV y guardar en PDF; abrir los archivos en Excel y en el visor de PDF.
7. Lanzar la extracción a mano y con la tarea programada (`schtasks /run`).
8. Crear una copia de seguridad, modificar datos y restaurarla.
9. Abrir y resolver una incidencia.
10. Revisar en Auditoría que todas las acciones anteriores quedaron registradas con su usuario.
11. Borrar los datos de prueba (los pedidos se borran solos por `ON DELETE CASCADE`).

**Incidencias encontradas durante el desarrollo y solución:**

| # | Incidencia | Causa | Solución |
|---|---|---|---|
| 1 | El instalador fallaba al crear las tablas | `install.php` leía `schema.sql`, pero el archivo se llamaba `schema.php` | Renombrar el script a `schema.sql` |
| 2 | En un equipo nuevo ninguna página arrancaba ("Error de conexión") | `config.php` se conectaba a `diegofo_crimson` antes de que existiera | Conectar primero al servidor, crear la base de datos si no existe y después seleccionarla |
| 3 | Reinstalar daba error de clave duplicada | Los datos de ejemplo se insertaban siempre con los mismos `id` | `INSERT IGNORE`, que se salta los registros que ya existen |
| 4 | Los CSV descargados contenían el HTML de la página | `index.php` ya había enviado la cabecera y el menú antes de generar el CSV | `ob_start()` guarda la salida en memoria y `limpiar_salida()` la vacía antes de descargar |
| 5 | La sección Auditoría no mostraba el registro de acciones | Usaba una tabla genérica en lugar de `auditoria_sistema` | Módulo reescrito y función común `registrar_auditoria()` para todos los módulos |
| 6 | Excel en español juntaba todas las columnas del CSV en una | El separador era la coma | Separador `;` y BOM UTF-8 para las tildes |
| 7 | No se podían calcular importes reales | Los pedidos no tenían cantidad | Columna `cantidad` añadida con `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` |
| 8 | Riesgo de ejecutar código si un nombre contenía `<script>` | Los datos se mostraban sin escapar | `htmlspecialchars()` al mostrar datos guardados |
| 9 | Cualquiera podía usar el ERP y la auditoría no sabía quién hacía cada acción | No había usuarios | Login con `password_hash()` / `password_verify()`, perfiles y columna `usuario` en la auditoría |

_(Añadir aquí las incidencias propias encontradas al probar la aplicación, o exportar el CSV del módulo Incidencias.)_

### h) Obtención de información relevante

Para el periodo elegido, el módulo Informes calcula:

- **Total facturado**, número de pedidos y unidades vendidas, con la **variación frente al periodo anterior** de la misma duración (en verde si sube, en rojo si baja).
- **Ticket medio:** lo que se gasta de media en cada pedido, también comparado con el periodo anterior.
- **Mejor cliente:** el que más dinero ha gastado.
- **Producto estrella:** el que más unidades ha vendido.
- **Stock bajo**, según el mínimo definido en Ajustes, para saber qué reponer.

Con esta información la empresa puede decidir a qué clientes cuidar, qué productos promocionar, si las ventas van mejor o peor que el mes anterior y qué debe pedir al proveedor.

---

## 5. Seguridad

- Contraseñas guardadas con `password_hash()`: en la base de datos no está la contraseña, sino un código del que no se puede obtener.
- Al entrar se genera una sesión nueva (`session_regenerate_id`) y el mensaje de error es el mismo tanto si falla el usuario como la contraseña.
- Los intentos fallidos se registran en la auditoría.
- Usuarios y copias de seguridad solo están disponibles para el perfil administrador.
- La carpeta `copias/` tiene un `.htaccess` que impide descargarla escribiendo su dirección en el navegador.
- Consultas preparadas y `htmlspecialchars()` contra inyección SQL y código en los datos.

---

## 6. Capturas

_(Añadir capturas propias con datos reales: pantalla de entrada, Informes con comparación, informe en PDF, CSV abierto en Excel, Extracciones con historial, Rendimiento tras la prueba de carga, Copias de seguridad, Incidencias y Auditoría.)_

---

## 7. Valoración personal

_(A completar por el autor: qué ha resultado más difícil, qué se ha aprendido y qué se mejoraría.)_

---

## 8. Instalación y uso

1. Copiar la carpeta del proyecto en `C:\xampp\htdocs\crimson\`.
2. Iniciar **Apache** y **MySQL** desde el panel de control de XAMPP.
3. Abrir `http://localhost/crimson/install.php` y seguir los tres pasos: requisitos, base de datos y administrador. Si la base de datos ya existía del RA2, el instalador la **actualiza** sin borrar los datos.
4. Entrar en `http://localhost/crimson/` con el usuario creado.
5. (Opcional) Programar la extracción automática con la orden `schtasks` del apartado 4.e.

La tipografía (Archivo) se carga desde Google Fonts; sin conexión a internet se usa la letra del sistema.

**Requisitos:** PHP 8.x con MySQLi y MariaDB 10.3 o superior (la que trae XAMPP). Las órdenes `ADD COLUMN IF NOT EXISTS` y `ADD INDEX IF NOT EXISTS` son propias de MariaDB.
