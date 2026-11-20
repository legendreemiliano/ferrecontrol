# FerreControl — Sistema web integral para una ferretería

Aplicación web (PHP + MySQL + Bootstrap 5) para administrar una ferretería: productos, stock, compras,
ventas, costos, ingresos, egresos, empleados, sueldos e informes. Funciona en una PC local con **XAMPP**.

Tiene tres módulos conectados entre sí:

| Módulo | Qué hace |
|---|---|
| **Administración** | Ingresos, egresos, costos fijos/variables, precios de venta, ganancias, informes, usuarios, configuración, auditoría y copia de seguridad. |
| **Producción y Stock** | Productos, categorías, proveedores, compras, ventas (punto de venta), devoluciones, movimientos, control diario, ajustes, inventario físico, alertas y reportes. |
| **Personal** | Empleados, asistencias, licencias, liquidación estimada de sueldos, productividad y reportes. |

Cuando se **registra una venta**, el stock se descuenta solo y el cobro aparece como ingreso en Administración.
Cuando se **registra una compra**, el stock aumenta, se recalcula el costo promedio y el egreso aparece en Administración.

> Relación con el TP7 "Control de Stock": la pantalla **Producción y Stock → Control diario** muestra, día por día,
> **Entrada / Salida / Existencia** de un producto (con el último día arriba), y el sistema **no deja registrar una salida
> mayor que el stock disponible** (si quedan 50 no se pueden sacar 51).

---

## Requisitos

- Windows con **XAMPP** (PHP 7.4 o superior —probado el diseño para PHP 8.x— y MySQL/MariaDB).
- Un navegador moderno.
- **Conexión a Internet la primera vez**: Bootstrap, los íconos y Chart.js se cargan desde la red (CDN).
  Si querés trabajar sin Internet, descargá esos archivos a `assets/vendor/` y cambiá las URLs en `includes/header.php`,
  `includes/footer.php`, `login.php` y `setup.php`.

## Instalación paso a paso

1. **Descargar e instalar XAMPP** desde https://www.apachefriends.org (instalación por defecto).
2. Abrir el **XAMPP Control Panel** y presionar **Start** en **Apache** y en **MySQL**.
3. **Copiar la carpeta del proyecto** a `C:\xampp\htdocs\ferrecontrol` (debe quedar `C:\xampp\htdocs\ferrecontrol\index.php`).
4. Abrir **phpMyAdmin**: http://localhost/phpmyadmin
5. **Importar la base de datos**: pestaña *Importar* → *Seleccionar archivo* → `database/ferrecontrol.sql` → *Importar*.
   (El archivo crea la base `ferrecontrol` y todas las tablas con datos iniciales de ejemplo: categorías, productos,
   proveedores, empleados y medios de pago. **No** trae usuarios.)
6. **Revisar la conexión** en `config/database.php`. Con XAMPP por defecto no hay que cambiar nada
   (usuario `root`, contraseña vacía). Si le pusiste contraseña a MySQL, escribila ahí.
7. **Crear el administrador inicial** abriendo http://localhost/ferrecontrol/setup.php
   - Elegí nombre, usuario y contraseña (mínimo 8 caracteres, letras y números). Se guarda con `password_hash()`.
   - Dejá marcadas las opciones de **usuarios y datos de demostración** si querés probar todo enseguida
     (crea los usuarios `deposito` y `personal`, y compras, ventas, gastos, asistencias y liquidaciones de ejemplo).
   - Esta pantalla solo funciona desde la propia PC y solo mientras no exista ningún usuario.
8. Abrir http://localhost/ferrecontrol/ e **iniciar sesión**.
9. **Probar los módulos** con la lista de `PRUEBAS.md`.

### Usuarios de demostración (si los creaste en el paso 7)

| Usuario | Rol | Contraseña |
|---|---|---|
| el que elegiste | Administrador | la que elegiste |
| `deposito` | Encargado de depósito y ventas | la "contraseña de usuarios de demostración" |
| `personal` | Encargado de personal | la misma de demostración |

### Cómo se crea el administrador de forma segura

El archivo `ferrecontrol.sql` **no contiene ninguna contraseña**. `setup.php` recibe la contraseña por formulario,
la convierte con `password_hash()` (bcrypt) y guarda solo el hash. Después de crear el primer usuario, `setup.php`
deja de funcionar. Si te olvidás la contraseña, otro administrador puede cambiarla en *Administración → Usuarios*;
si no hay otro, volvé a importar el SQL (se borran los datos) y repetí el paso 7.

## Estructura de archivos

```
ferrecontrol/
├── index.php  login.php  logout.php  dashboard.php  setup.php
├── README.md  MANUAL_USUARIO.md  PRUEBAS.md
├── config/database.php          Conexión y opciones (zona horaria, modo debug)
├── database/ferrecontrol.sql    Estructura + datos iniciales (18+ tablas)
├── includes/
│   ├── bootstrap.php            Arranque común (errores, sesión, login obligatorio)
│   ├── db.php  functions.php    PDO, transacciones, formato, CSRF, paginación, CSV
│   ├── auth.php  permissions.php  Login/sesiones y permisos por rol
│   ├── header.php  sidebar.php  footer.php  Plantilla
│   ├── demo_seed.php  demo_data.json   Carga de datos de demostración
│   └── services/                Lógica de negocio (usa transacciones)
│       ├── stock_service.php    Único punto que modifica el stock
│       ├── purchase_service.php Compras, costo promedio ponderado, anulación
│       ├── sale_service.php     Ventas, cobros, devoluciones, anulación
│       ├── accounting_service.php  Caja, resumen económico, costos, precios
│       └── payroll_service.php  Asistencia y liquidación estimada
├── modules/
│   ├── administracion/   usuarios/   stock/   compras/   ventas/   proveedores/
│   └── personal/ (empleados, asistencias, licencias, sueldos)
├── reports/                     Definición de los informes (admin, stock, personal) + centro de informes
├── assets/ (css, js, img)
└── logs/                        error.log (no accesible desde el navegador)
```

## Cómo funcionan las reglas más importantes

- **Stock**: toda modificación pasa por `stock_apply()`, dentro de una transacción, con bloqueo de la fila del producto
  y registro en `stock_movements` (stock anterior y resultante, motivo, usuario). El stock **nunca queda negativo**
  (lo controla el código y además una restricción `CHECK` en la base).
- **Costo promedio ponderado**: `(valor del stock anterior + valor de la compra) / (cantidad anterior + cantidad comprada)`.
  Ejemplo: 10 martillos a $5.000 + 10 a $6.000 → $5.500. Las ventas guardan el costo vigente al venderse, así que
  un cambio posterior **no modifica el costo histórico** de ventas ya hechas.
- **Compras vs. costo**: comprar mercadería aumenta el inventario; el costo se reconoce cuando se **vende**
  (costo de mercadería vendida). Por eso las compras no se suman al costo del período (se mostrarían dos veces).
- **Ingresos sin duplicar**: cada cobro genera un único ingreso (`cash_movements.payment_id` es único).
  Corregir una venta se hace con devolución o anulación, que dejan reembolsos registrados, nunca borrando.
- **Precios**: siempre se calculan en el servidor a partir de la base de datos; lo que envíe el navegador se ignora.
  El precio sugerido admite *recargo sobre costo* (`costo × (1+%)`) o *margen sobre ventas* (`costo ÷ (1−%)`), antes de impuestos.
- **Sueldos**: son **estimaciones de gestión**. No hay porcentajes legales precargados; los conceptos se configuran y
  validan en *Personal → Conceptos de sueldo*.

## Seguridad implementada

Consultas preparadas con PDO (sin emulación) · validación en el servidor · escape de salida HTML · token CSRF en todos los
formularios que modifican datos · contraseñas con `password_hash`/`password_verify` · sesiones con cookie `HttpOnly` y
`SameSite`, regeneración del ID al ingresar y cierre por inactividad (2 h) · bloqueo temporal tras 5 intentos fallidos ·
permisos por rol verificados en cada página y operación · transacciones en ventas, compras y devoluciones ·
registro de auditoría · mensajes de error genéricos (el detalle queda en `logs/error.log`) · carpetas `config/`,
`includes/`, `database/` y `logs/` bloqueadas con `.htaccess`.

## Solución de errores frecuentes

| Problema | Solución |
|---|---|
| "No se pudo conectar a la base de datos" | Iniciá **MySQL** en XAMPP; revisá usuario/contraseña en `config/database.php`; confirmá que importaste el SQL. |
| `setup.php` dice que faltan las tablas | Importá `database/ferrecontrol.sql` en phpMyAdmin y recargá. |
| Página sin estilos / íconos | Falta Internet para cargar Bootstrap (CDN). Conectate o descargá los archivos (ver *Requisitos*). |
| Los links llevan a una URL rara | Poné la carpeta en `htdocs/ferrecontrol` o fijá `APP_BASE_URL` en `config/database.php`. |
| "La sesión del formulario venció" | Recargá la página (F5) y volvé a enviar el formulario. |
| Error 500 / pantalla genérica de error | Mirá `logs/error.log`. Para ver el detalle en pantalla poné `APP_DEBUG` en `true` (solo en tu PC). |
| Importar el SQL falla por tamaño/tiempo | En `php.ini` subí `upload_max_filesize` y `max_execution_time`, y reiniciá Apache. |
| "Acceso denegado" | Tu rol no tiene permiso para esa operación; pedíselo al administrador. |

## Copia de seguridad

*Administración → Copia de seguridad → Descargar* genera un `.sql` completo (estructura y datos) usando solo PHP.
Para restaurar: phpMyAdmin → base `ferrecontrol` → *Importar*. También podés usar *Exportar* de phpMyAdmin.

## Alcance y limitaciones (leer antes de entregar)

- Pensado para **funcionar en la PC local**. Publicarlo en Internet requiere hosting con HTTPS, cambiar las credenciales
  de MySQL, desactivar `setup.php`, restringir el acceso a carpetas internas y una revisión de seguridad adicional.
- Los comprobantes de venta son **internos**: no son facturas fiscales. No hay facturación electrónica.
- Las liquidaciones son estimaciones y **no son recibos de sueldo oficiales**. Las cargas patronales no se calculan:
  se cargan como egresos (categoría "Cargas y contribuciones laborales").
- Impuestos (IVA, etc.) no se aplican automáticamente.
- Las cantidades de stock son números enteros.
- Los cálculos de dinero usan columnas `DECIMAL` en MySQL y redondeo controlado a 2 decimales en PHP (sin acumular error
  de punto flotante); el costo promedio se guarda con 4 decimales.
- El código fue revisado con chequeos estáticos, pero **no pudo ejecutarse en el entorno donde se escribió**
  (no tenía PHP ni MySQL). Ver `PRUEBAS.md`: las pruebas funcionales deben correrse en XAMPP antes de la entrega.
