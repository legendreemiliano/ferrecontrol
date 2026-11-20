# Pruebas de FerreControl

> **Transparencia:** el código se escribió en un entorno **sin PHP ni MySQL**, por lo que **no se pudo ejecutar la aplicación**.
> Acá se separa lo que **sí se verificó** (chequeos estáticos, con resultados reales) de lo que **falta ejecutar en XAMPP**.
> No se marcó como aprobada ninguna prueba funcional que no se haya corrido.

## A. Verificaciones realizadas (estáticas)

| # | Verificación | Resultado |
|---|---|---|
| 1 | Balance de llaves, paréntesis y corchetes en los 63 archivos PHP; strings, comentarios y heredocs bien cerrados | Sin errores |
| 2 | Sintaxis alternativa (`if:`/`endif`, `foreach:`/`endforeach`…) pareja en todas las plantillas | Sin errores |
| 3 | Todos los `include`/`require`, `url()`, `redirect()`, `href` y scripts de página apuntan a archivos que existen | Sin errores |
| 4 | Toda función propia llamada está definida (108 funciones) | Sin llamadas huérfanas |
| 5 | El esquema `ferrecontrol.sql` se cargó completo en un motor SQL de prueba (SQLite, adaptando tipos): 25 tablas, claves foráneas y los datos iniciales (35 productos, 9 categorías, 5 proveedores, 5 empleados, 5 medios de pago, 14 categorías de gasto, 7 conceptos de sueldo, 14 parámetros) | Sin errores |
| 6 | 272 consultas SQL del código se compilaron contra ese esquema (detecta tablas/columnas inexistentes y errores de sintaxis comunes) | Sin errores reales (6 avisos eran texto que no es SQL) |
| 7 | Ninguna consulta repite un parámetro con nombre (los prepares reales de PDO lo rechazan) ni mezcla `?` con `:nombre` | Sin casos |
| 8 | Las consultas con `GROUP BY` se revisaron a mano para cumplir `ONLY_FULL_GROUP_BY` | Sin casos |
| 9 | Simulación en Python del stock de los datos de demostración (compras, 29 ventas, devoluciones, una anulación): el stock **nunca queda negativo** y quedan 10 productos bajo el mínimo (p. ej. tornillos de 5 cm: 12 cajas con mínimo 20) | Correcto |

Limitación: SQLite no es MySQL. Estas verificaciones **no reemplazan** la ejecución real.

## B. Pruebas funcionales a ejecutar en XAMPP

Preparación: seguir el README, crear el administrador y **cargar los datos de demostración**. Anotá el resultado en la última columna.

### Prueba 1 — Alta de producto
1. Entrar como administrador → *Productos → Nuevo producto*. Código `HM-100`, nombre **Martillo de acero**, categoría Herramientas manuales, mínimo 5. Guardar.
2. **Esperado:** aparece en el catálogo con stock 0; en phpMyAdmin, tabla `products`, existe la fila.

### Prueba 2 — Compra de mercadería
1. *Compras → Nueva compra*: proveedor Distribuidora Herramientas del Sur; 10 × Martillo de acero a $5.000; pagada en transferencia.
2. **Esperado:** compra `C-…` guardada; stock = 10; en *Movimientos* una entrada «Compra» (stock anterior 0 → 10); costo promedio $5.000; en *Egresos* aparece «Compra C-… » por $50.000 como pagado (categoría Compra de mercadería).
3. Segunda compra: 10 × a $6.000. **Esperado:** stock 20 y costo promedio **$5.500**.

### Prueba 3 — Venta
1. Asignar un precio al producto (*Precios de venta*, p. ej. recargo 40 % → $7.700).
2. *Nueva venta*: 2 × Martillo de acero, efectivo, recibido $20.000.
3. **Esperado:** venta `V-…` guardada; stock 18; movimiento de salida; vuelto $4.600; en *Ingresos* un único cobro de $15.400; en *Ventas* la venta figura «Pagada». Recargar la página del comprobante **no** duplica nada.

### Prueba 4 — Falta de stock
1. Intentar vender 100 martillos.
2. **Esperado:** mensaje «Stock insuficiente…»; no se crea la venta; el stock no cambia (verificar en *Movimientos*: sin filas nuevas).

### Prueba 5 — Costo unitario (promedio ponderado)
1. Con 18 unidades a $5.500, comprar 12 más a $6.500.
2. **Esperado:** costo promedio = (18×5.500 + 12×6.500) / 30 = **$5.900**. Las ventas ya hechas conservan su costo histórico.
3. Probar también una compra con descuento y flete: el «costo efectivo» de cada línea refleja el reparto.

### Prueba 6 — Permisos
Iniciar sesión con cada rol y comprobar, **también escribiendo la URL a mano**:
| Usuario | Debe poder | Debe recibir «Acceso denegado» en |
|---|---|---|
| `deposito` | productos, compras, ventas, stock, proveedores (solo ver) | `modules/administracion/*`, `modules/usuarios/index.php`, `modules/personal/sueldos/index.php`, `modules/ventas/ver.php` + anular venta (POST) |
| `personal` | empleados, asistencias, licencias, generar liquidaciones | `modules/stock/index.php`, `modules/ventas/pos.php`, `modules/administracion/precios.php`, aprobar/pagar liquidación |
| administrador | todo | — |
Además: el encargado de depósito no puede superar el descuento máximo; no puede cambiar el precio de un producto existente.

### Prueba 7 — Personal
1. Como `personal`: *Empleados → Nuevo empleado*; luego *Asistencias*: cargar una asistencia (con entrada 09:40 y estado «Presente»); luego *Sueldos → Nueva liquidación* del empleado, calcular y guardar.
2. **Esperado:** la asistencia queda como «Tarde» (si el horario habitual es 09:00 con 10 min de tolerancia); la liquidación queda en *borrador*; el administrador puede aprobarla y pagarla y entonces aparece el egreso «Sueldos» en *Egresos*.

### Prueba 8 — Integridad
1. Anular una venta con cobro: **esperado** stock restituido, reembolso registrado (egreso «Reembolso…»), la venta queda visible como *Anulada*.
2. Devolver parcialmente una venta (producto en buen estado): **esperado** stock sube, se genera el reembolso proporcional y el «Resultado» del período baja en consecuencia.
3. Anular una compra cuyo stock ya se vendió: **esperado** rechazo con mensaje claro.
4. Verificar que ningún registro se borró de las tablas (`sales`, `purchases`, `stock_movements`, `cash_movements`).
5. Verificar que la suma de `stock_movements` (entradas − salidas) de cada producto coincide con `products.stock`.

### Prueba 9 — Seguridad
1. En phpMyAdmin, tabla `users`: `password_hash` empieza con `$2y$` (nunca la contraseña en texto).
2. Abrir el código de un formulario (clic derecho → inspeccionar): tiene el campo oculto `csrf`. Borrar ese campo y enviar: **esperado** error 419.
3. En el POS, modificar con las herramientas del navegador el precio mostrado y confirmar: **esperado** el total se calcula con el precio de la base.
4. Probar caracteres como `<script>` en un nombre de producto: se muestra como texto, no se ejecuta.
5. 5 intentos fallidos de login: **esperado** bloqueo temporal.
6. Abrir `http://localhost/ferrecontrol/config/database.php` y `.../logs/error.log`: **esperado** 403.

## C. Registro de resultados (completar)

| Prueba | Fecha | Resultado (OK / falla) | Observaciones |
|---|---|---|---|
| 1 Alta de producto | | | |
| 2 Compra | | | |
| 3 Venta | | | |
| 4 Falta de stock | | | |
| 5 Costo promedio | | | |
| 6 Permisos | | | |
| 7 Personal | | | |
| 8 Integridad | | | |
| 9 Seguridad | | | |

## D. Qué no está implementado / limitaciones conocidas
- No hay facturación electrónica fiscal ni cálculo automático de impuestos (se aclara en pantalla).
- Las liquidaciones son estimaciones; los porcentajes laborales deben cargarse y validarse (no vienen precargados). Las cargas patronales no se calculan.
- Los recuentos de inventario aplican la **diferencia detectada** (contado − registrado al momento del recuento), no sobrescriben el stock.
- Los gastos se filtran por la fecha de la operación; el estado (pagado/pendiente) indica si salió el dinero.
- La anulación de una venta o compra mantiene el cobro/egreso original y registra el reembolso o lo marca como anulado (según el caso), para conservar la trazabilidad.
- Bootstrap, íconos y Chart.js se cargan por CDN (hace falta Internet la primera vez).
