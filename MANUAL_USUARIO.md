# Manual de usuario — FerreControl

Guía paso a paso para quien usa el sistema por primera vez. Cada sección indica **qué rol** puede hacer la operación:
**A** = Administrador · **D** = Encargado de depósito y ventas · **P** = Encargado de personal.

## Permisos de un vistazo

| Operación | A | D | P |
|---|:-:|:-:|:-:|
| Ver el panel económico, ingresos, egresos, costos, ganancias, informes de Administración | ✔ | | |
| Cambiar precios de venta | ✔ | | |
| Crear/editar productos | ✔ | ✔ (crear según configuración; no cambia precios) | |
| Registrar compras y ventas | ✔ | ✔ | |
| Anular compras y ventas | ✔ | | |
| Devoluciones y cobros de ventas | ✔ | ✔ | |
| Proveedores: ver | ✔ | ✔ | |
| Proveedores: crear/editar | ✔ | | |
| Ajustes de stock y recuentos físicos (cargar) | ✔ | ✔ | |
| Autorizar recuentos físicos | ✔ | | |
| Empleados, asistencias, licencias | ✔ | | ✔ |
| Generar liquidaciones | ✔ | | ✔ |
| Aprobar y pagar liquidaciones, configurar conceptos | ✔ | | |
| Usuarios, configuración, auditoría, copia de seguridad | ✔ | | |

Si intentás algo que tu rol no permite, el sistema muestra **Acceso denegado** (aunque escribas la dirección a mano).

---

## 1. Cómo ingresar al sistema
1. Abrí http://localhost/ferrecontrol/ en el navegador.
2. Escribí tu **usuario** y **contraseña** y presioná **Ingresar**.
3. Después de 5 intentos fallidos el usuario se bloquea unos minutos. Si la sesión queda inactiva 2 horas, vuelve a pedir la contraseña.

## 2. Cómo cerrar sesión
Arriba a la derecha, botón **Cerrar sesión**. Hacelo siempre al terminar, sobre todo en computadoras compartidas.

## 3. Cómo navegar por el menú
A la izquierda (en el celular, el botón ☰) hay tres grupos: **Administración**, **Producción y Stock** y **Personal**. Solo ves lo que tu rol permite.
En las tablas podés **buscar**, **filtrar**, **ordenar** (clic en el título de la columna) y **paginar**.

## 4. Cómo registrar productos (A, D)
1. *Producción y Stock → Productos → Nuevo producto*.
2. Completá **código interno** (único, ej. `HM-020`), **nombre** y **categoría** (obligatorios). Opcional: marca, unidad, ubicación, proveedor habitual, código de barras.
3. Indicá el **stock mínimo** (se usa para las alertas).
4. El **precio de venta** lo puede cargar quien crea el producto; después solo el administrador lo modifica.
5. Si ya tenés mercadería, cargá **stock inicial** y su **costo unitario**; si no, dejá 0 y registrá una compra.
6. **Guardar**. *Ejemplo:* "Martillo de acero", categoría Herramientas manuales, mínimo 5.

## 5. Cómo editar productos (A, D)
En la lista de productos, botón ✏️. No se puede editar a mano el stock ni el costo promedio: cambian solos con compras, ventas y ajustes (así queda todo registrado).
Para **desactivar** un producto usá el botón 🚫: deja de ofrecerse para vender pero se conserva su historial. **Nunca se borran** productos.

## 6. Cómo consultar el inventario
- **Productos**: stock actual de cada producto, con marca **Bajo** o **Sin stock**.
- **Stock bajo**: lista de lo que hay que reponer (stock igual o menor al mínimo).
- **Movimientos**: historial de entradas y salidas, el más reciente arriba. Filtrá por fecha, producto o tipo.
- **Control diario**: elegí un producto y mirá, **día por día**, cuánto entró, cuánto salió y la existencia al cierre.
- **Reportes de stock**: stock actual, sin stock, más vendidos, rotación, valorización, etc. (con CSV e impresión).

## 7. Cómo registrar una compra (A, D)
1. *Compras → Nueva compra*.
2. Elegí **proveedor** y **fecha** (y, si querés, el número de remito/factura).
3. En **Productos**, elegí producto, **cantidad** (entero) y **costo unitario**. Usá *Agregar línea* para más productos.
4. Cargá **descuento** y **otros costos** (flete, por ejemplo) si corresponde: se reparten entre los productos.
5. Elegí **Pagada** (y el medio de pago) o **Pendiente de pago**.
6. **Confirmar compra**. El stock sube, el costo promedio se recalcula y el egreso aparece en Administración.
*Ejemplo:* 10 martillos a $5.000 y luego 10 a $6.000 → el costo promedio queda en **$5.500**.
Para corregir un error, el administrador puede **anular** la compra (solo si esas unidades siguen en stock). El registro no se borra.

## 8. Cómo registrar una venta (A, D)
1. *Nueva venta* (punto de venta).
2. Escribí en el buscador el nombre, código o código de barras y presioná **Enter** (o hacé clic) para agregar al carrito. Cambiá la cantidad si hace falta.
3. Opcional: cliente y **descuento** (el encargado de depósito tiene un tope configurable; por defecto 10 %).
4. Elegí el **medio de pago**. En efectivo, escribí el **efectivo recibido** y se muestra el **vuelto**.
   Si el cliente no paga ahora, elegí *Pendiente de cobro (a cuenta)*.
5. **Confirmar venta**. Se descuenta el stock y se muestra el **comprobante interno** (botón *Imprimir*).
Si pedís más unidades de las que hay, **la venta completa se rechaza** y no se modifica nada.
El comprobante es interno: **no es una factura**.

## 9. Cómo registrar un pago
- **Cobro de una venta con saldo** (A, D): *Ventas →* abrí la venta → *Registrar cobro*. Podés cobrar en varios pagos; el saldo se actualiza solo.
- **Pago de una compra pendiente** (A): abrí la compra → *Registrar pago*; o *Egresos →* botón ✔ del egreso pendiente.
- **Pago de un gasto pendiente** (A): *Egresos →* botón ✔.

## 10. Cómo consultar ingresos y egresos (A)
- *Administración → Ingresos*: cobros de ventas (origen **Venta**, se generan solos) e ingresos **manuales** (servicios, ajustes). Mostramos también las **ventas pendientes de cobro**, que todavía no son ingreso.
- *Administración → Egresos*: gastos, compras de mercadería (automáticas) y sueldos pagados. Para cargar un gasto: **Registrar gasto**. Las **compras no se cargan acá**: se hacen en Compras.
- Un registro equivocado se **anula** (queda visible, tachado); no se borra.

## 11. Cómo interpretar los costos y las ganancias (A)
- **Costo total del período = costos fijos + costos variables.**
  - *Fijos*: alquiler, sueldos, internet, etc. *Variables*: mercadería vendida, comisiones de medios de pago, transporte, etc. *Mixtos*: se reparten según el % fijo configurado.
  - Las **compras de mercadería no son un costo del período**: aumentan el inventario y se cuentan cuando se venden.
- **Ganancia bruta = ventas netas − costo de la mercadería vendida.**
- **Resultado operativo estimado = ganancia bruta − gastos operativos** (y − comisiones, + otros ingresos).
- Son **estimaciones de gestión**, no el balance contable ni impositivo.
- **Precios de venta**: *Administración → Precios de venta*. Elegí un producto, el método y el porcentaje:
  *Recargo sobre costo*: costo $10.000 con 30 % → **$13.000**. *Margen sobre ventas*: costo $10.000 con 30 % → **$14.285,71**.
  Los precios son **antes de impuestos**.

## 12. Cómo registrar proveedores (A; D solo consulta)
*Producción y Stock → Proveedores*: completá nombre, CUIT (opcional), teléfono, correo, dirección y contacto. **Guardar**. Para dejar de usar uno, desactivalo (no se borra).

## 13. Cómo registrar empleados (A, P)
*Personal → Empleados → Nuevo empleado*: nombre, apellido, DNI (solo números), puesto, fecha de ingreso, contratación y sueldo básico (si tu rol puede ver sueldos). **Guardar**.
Para dar de baja, usá el botón de baja: se conserva todo el historial.

## 14. Cómo cargar asistencias y licencias (A, P)
- **Asistencias**: *Personal → Asistencias*. Elegí la fecha y el turno; por cada empleado completá entrada, salida y estado (Presente, Ausente, Tarde, Licencia, Franco) y **Guardar**. Si ponés «Presente» con una entrada posterior al horario + tolerancia, queda como «Tarde». Hay un solo registro por empleado, día y turno.
- **Licencias**: *Personal → Licencias → Registrar licencia*: empleado, tipo, fechas y motivo. Queda **pendiente** hasta que se **apruebe** o **rechace** (queda registrado quién lo hizo).

## 15. Cómo consultar una liquidación (A, P)
1. *Personal → Sueldos → Nueva liquidación*: elegí empleado y período, marcá los conceptos a aplicar, cargá horas extra, bonificaciones o descuentos y presioná **Calcular vista previa**.
2. Revisá el detalle y **Guardar liquidación** (queda como *borrador*).
3. El administrador la **aprueba** y luego **registra el pago** (eso genera el egreso «Sueldos» en Administración).
4. Para ver o imprimir el detalle: *Sueldos →* botón 👁.
Es una **estimación**; no es un recibo de sueldo oficial. Los porcentajes se configuran en *Conceptos de sueldo* (A) y deben ser validados.

## 16. Cómo generar informes
- *Administración → Informes* (A), *Reportes de stock* (A, D), *Reportes de personal* (A, P) o el **Centro de informes**.
- Elegí el informe y el rango de fechas, **Generar**. Botones: **Exportar CSV** (se abre en Excel) e **Imprimir** (diseño listo para papel o PDF).

## 17. Cómo resolver los errores más habituales
| Mensaje | Qué significa / qué hacer |
|---|---|
| «Stock insuficiente de …» | Pediste más unidades que las disponibles. Bajá la cantidad o registrá primero una compra. |
| «Ya existe un producto con ese código» | Elegí otro código interno. |
| «No se puede anular: ya se vendieron unidades…» | La compra no se puede revertir porque el stock quedaría negativo. |
| «La sesión del formulario venció…» | Recargá la página (F5) y reenviá el formulario. |
| «Acceso denegado» | Tu rol no incluye esa operación. |
| «Ya existe una liquidación vigente…» | Ya hay una para ese empleado y período; anulala para rehacerla. |

## 18. Cómo realizar una copia de seguridad (A)
*Administración → Copia de seguridad → Descargar copia de seguridad*. Guardá el `.sql` en un lugar seguro (pendrive o nube).
Para restaurar: phpMyAdmin → base `ferrecontrol` → *Importar* → elegir el archivo. (Importar reemplaza los datos actuales.)
