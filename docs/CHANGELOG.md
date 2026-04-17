# CHANGELOG

Todos los cambios notables de este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/), y este proyecto se adhiere a un versionamiento semántico.

---

## [2.7.0] - 15-04-2026

### Añadido 🚀
- **Nueva Instalación: Pista de Fútbol 7** — Integración completa de la pista de fútbol 7 en el sistema de reservas existente, con exactamente las mismas capacidades que Tenis y Pádel.
  - **Página de reserva (`html/futbol7.php`)**: Usa el mismo motor de `horarios.js` con `data-sport="Fútbol 7"`. Incluye hero section con gradiente verde campo, selector de días, slots de 2h y botón de confirmación.
  - **Tarjeta en Home (`html/home.php`)**: Nueva tarjeta "Fútbol 7" con balón de fútbol y CTA verde que enlaza directamente a `futbol7.php`. El hero banner del home también se actualizó para mencionar las tres instalaciones.
  - **CSS (`css/tenis.css`)**: Nueva clase `.futbol7-hero` con gradiente verde oscuro (#1a5c28), color de texto suave (#b8e6c0) y responsive completo para 768px y 480px. Integrada en todos los selectores de media queries existentes.
  - **Admin — Gestión de Bloqueos**: `#pista-select` ahora incluye "Fútbol 7" → los admins pueden bloquear/desbloquear horarios de la pista de fútbol.
  - **Admin — Centro de Actividad**: `#act-filtro-pista` incluye "Fútbol 7" → filtrar reservas activas de la pista.
  - **Admin — Buscador e Historial**: `#s-pista` incluye "Fútbol 7" → buscar/cancelar reservas históricas.

### Técnico 🔧
- **Sin cambios en backend**: Las APIs `get-horarios.php`, `enviar-correo.php`, `bloquear-hora.php`, `admin-reservas.php`, `admin-stats.php` y el sistema de bonos son completamente agnósticos a las pistas — soportan automáticamente cualquier valor de `pista`.
- **Sin cambios en checkout.js ni checkout.php**: Leen el nombre del sport desde `localStorage`, soporte automático.
- **Perfil de usuario**: Las reservas de Fútbol 7 aparecen automáticamente en "Próximas Reservas" e "Historial" del perfil.

---

## [2.6.0] - 13-04-2026

### Añadido 🚀
- **Sistema de Bonos (Packs de Sesiones Prepagadas)**: Implementación completa de un sistema que permite a los usuarios registrados comprar packs de sesiones deportivas por adelantado y canjearlos durante el checkout para reservar pistas sin coste adicional.
  - **Página de Bonos (`html/bonos.php`)**: Interfaz pública (requiere login) con tarjetas premium que muestran los packs disponibles, precio por sesión, características y un FAQ. Los packs se cargan dinámicamente desde la API. Compatible con pago por Stripe y pago simulado.
  - **Página de Confirmación (`html/bono-exitoso.php`)**: Página post-pago que verifica la transacción (Stripe o simulada), crea el registro del bono en BBDD de forma idempotente, y envía email de confirmación. Muestra resumen del bono con instrucciones de uso.
  - **Widget en Checkout (`html/checkout.php` + `js/checkout.js`)**: Toggle visual "Usar bono" que aparece automáticamente si el usuario tiene bonos activos. Al activarlo, el precio cambia a "0,00 € (Bono)", el botón cambia a "🎟️ Reservar con Bono (Gratis)" y se salta el flujo de Stripe, creando la reserva directamente.
  - **Sección en Perfil (`html/perfil.php`)**: Nueva sección "🎟️ Mis Bonos" con tarjetas visuales que incluyen barras de progreso (verde/naranja/rojo según usos restantes), estado del bono, fecha de caducidad y precio pagado. Nueva stat card "Bonos Activos".
  - **Enlace en Header (`includes/header.php`)**: Icono 🎟️ en la barra de navegación (solo usuarios logueados) con acceso directo a la página de bonos.
  - **Tarjeta en Home (`html/home.php`)**: Nueva tarjeta promocional "Bonos de Sesiones" en la sección de deportes con CTA naranja que enlaza a `bonos.php`.
- **API de Bonos (`api/bonos.php`)**: Endpoint REST con tres acciones:
  - `get_config` — Devuelve los packs activos y sus precios/caducidad (público).
  - `get_user_bonos` — Lista bonos activos del usuario con auto-caducidad de expirados.
  - `use_bono` — Transacción atómica (BEGIN/COMMIT) que: verifica disponibilidad, selecciona el bono más antiguo (FIFO con `FOR UPDATE`), decrementa usos, crea la reserva con `precio_total = 0` y `bono_id`, y envía email de confirmación.
- **API de Checkout de Bonos (`api/bonos-checkout.php`)**: Crea sesiones de Stripe Checkout específicas para la compra de bonos. En entorno sin Stripe, crea el bono directamente (pago simulado).
- **Panel Admin — Gestión de Bonos**: Nueva pestaña "🎟️ Gestión de Bonos" en el panel de administración:
  - **Estadísticas**: Tarjetas con total vendidos, activos, agotados e ingresos totales por bonos.
  - **Configuración de Packs**: Edición en vivo de precio (€) y caducidad (meses) de cada pack, con opción de activar/desactivar packs.
  - **Tabla de Bonos Vendidos**: Listado con filtros por usuario y estado, mostrando usos restantes, fecha de compra/caducidad y opción de cancelación.
  - **API Admin (`api/admin-bonos.php`)**: Endpoints para `get_all`, `get_config`, `update_config` y `cancel_bono`.
- **Base de Datos**:
  - **Tabla `bonos`**: Almacena bonos comprados con campos para usuario, tipo, usos totales/restantes, precio, estado, Stripe session ID, y fechas de compra/caducidad/agotado.
  - **Tabla `bonos_config`**: Configuración de packs (tipo, nombre, usos, precio, caducidad en meses, activo/inactivo).
  - **Columna `bono_id` en `reservas`**: FK opcional que vincula una reserva con el bono utilizado.
  - **Script de migración `api/setup-bonos-db.php`**: Crea tablas e inserta packs por defecto (Bono 15 y Bono 20 a 50€, caducidad 6 meses).
- **Estilos (`css/bonos.css`)**: Hoja de estilos dedicada con diseño premium: tarjetas con gradientes, toggle switch animado, barras de progreso con indicadores de nivel, y diseño responsive.

### Cambiado 🔄
- **`js/checkout.js` reescrito (v2.6.0)**: Flujo triple de pago — Bono (nuevo), Stripe y Simulado. Carga asíncrona de bonos del usuario al entrar en checkout.
- **`js/admin.js` actualizado (v2.6.0)**: Nuevas funciones de gestión de bonos, vistas y tabs integrados en el sistema existente.
- Versión del proyecto bumpeada a `v2.6.0`.

### Técnico 🔧
- **Caducidad automática**: Todos los endpoints que consultan bonos ejecutan primero `UPDATE bonos SET estado = 'caducado' WHERE fecha_caducidad < CURDATE()` para mantener la coherencia sin cron jobs.
- **Concurrencia**: El canjeo de bono usa `SELECT ... FOR UPDATE` dentro de una transacción para prevenir condiciones de carrera.
- **FIFO**: Al canjear, se selecciona el bono activo más antiguo (por `fecha_compra ASC`), consumiendo primero los bonos que caducan antes.

---

## [2.5.2] - 09-04-2026 (Mejoras Internas)

### Añadido 🚀
- **Middleware de Seguridad (`SecurityHandler.php`)**: Implementación centralizada de protección CSRF, prevención de redirecciones abiertas y gestión de cabeceras de seguridad (HSTS, CSP, X-Frame-Options).
- **Mejoras de Accesibilidad (`js/horarios-accesibles.js`)**: Soporte para navegación por teclado en la cuadrícula de horarios y notificaciones ARIA Live para lectores de pantalla.

---

## [2.5.0] - 08-04-2026

### Añadido 🚀
- **Módulo de Estadísticas**: Nueva sección en el panel de administrador ("📊 Resumen y Estadísticas") construida mediante gráficos interactivos con la librería `Chart.js`.
  - Tarjetas indicadoras del mes actual con los Ingresos (€) y Cantidad de Reservas comparativos al mes pasado.
  - Gráfico Donut (Circular) ilustrando la capacidad explotada por parte de todas las pistas.
  - Diagrama de Líneas ilustrando la demanda de reservas por día de los últimos 7 días con un backend dinámico en PDO `api/admin-stats.php`.

### Cambiado 🔄
- **Correo de Confirmaciones más minimalista**: Se ha removido la imagen física del código de barras (QR devueltos desde generador de API externa) y ahora se ha optado directamente por enclarecer los datos de facturación personal y mostrar el Alfanumérico a gran escala, resultando en carga mucho más limpia e instantánea.
  - Modificado en `html/pago-exitoso.php` y en `api/enviar-correo.php`.

---

## [2.4.0] - 07-04-2026

### Añadido 🚀
- **Pasarela de Pago Stripe Checkout**: Integración completa con Stripe para pagos reales con tarjeta de crédito/débito. El sistema detecta automáticamente si Stripe está configurado (`STRIPE_SECRET_KEY` / `STRIPE_PUBLISHABLE_KEY`); si no lo está, mantiene el flujo de pago simulado original.
  - **`api/stripe-checkout.php`**: Endpoint que crea sesiones de Stripe Checkout via API REST (cURL puro, sin librerías externas). Guarda los datos de reserva en sesión PHP para recuperarlos tras el pago.
  - **`html/pago-exitoso.php`**: Página post-pago que verifica la sesión con Stripe, crea la reserva en BBDD (idempotente — soporta recargas de página), envía email de confirmación y muestra el código de acceso. Reutiliza los estilos existentes de checkout.
  - **Checkout adaptativo** (`checkout.php` + `checkout.js`): Cuando Stripe está activo, se ocultan los campos de tarjeta simulada y se muestra un mensaje indicando la redirección segura a Stripe. El botón cambia a "💳 Pagar con Stripe". Si el usuario cancela en Stripe, se le devuelve al checkout con un aviso.
  - Modo test de Stripe (claves `sk_test_` / `pk_test_`) — ideal para pruebas internas antes de activar pagos reales.
- **Servicio de correo Brevo (ex-Sendinblue)**: Sistema de emails transaccionales con 300 envíos/día gratuitos.
  - **`api/mailer.php`**: Helper centralizado que usa la API HTTP de Brevo via cURL. Fallback automático a `mail()` cuando no hay API key configurada.
  - **`api/enviar-correo.php`**: Actualizado para usar el nuevo mailer en lugar de `mail()` directo. Los emails de confirmación ahora se envían realmente en producción.
- **Modo Mantenimiento / Beta Interna**: Sistema de acceso restringido para pruebas internas del Ayuntamiento.
  - Cuando `MAINTENANCE_MODE = true` (automático en producción), los visitantes ven una página de mantenimiento profesional (`html/mantenimiento.html`) con diseño glassmorphism.
  - Los empleados del Ayuntamiento acceden añadiendo `?beta_key=TaranconTest2026` a cualquier URL, lo que crea una cookie de 30 días que permite navegar normalmente.
  - El panel admin (`/admin/`) y las APIs (`/api/`) siempre son accesibles — no se ven afectados por el modo mantenimiento.
  - La clave beta es configurable via variable de entorno `BETA_KEY`.

### Cambiado 🔄
- **`config.php` ampliado**: Nuevas secciones 5 (Mantenimiento), 6 (Brevo), 7 (Stripe) con detección automática de si los servicios están configurados (`USE_BREVO`, `USE_STRIPE`).
- **`checkout.js` reescrito (v2.3.0)**: Flujo dual de pago. Variable global `window.USE_STRIPE` inyectada desde PHP determina qué flujo usar. Gestión de pago cancelado via query param `?pago=cancelado`.
- **`includes/header.php`**: Ahora ejecuta `check_maintenance()` antes de renderizar HTML.

---

## [2.3.0] - 07-04-2026

### Añadido 🚀
- **Sistema de configuración centralizado (`config.php`)**: Nuevo archivo maestro que detecta automáticamente el entorno (local vs producción) mediante `APP_ENV` o el hostname. Controla error reporting, seguridad de sesiones y credenciales de BBDD desde un solo lugar.
  - **Local (XAMPP)**: errores visibles, cookies sin flag `Secure`, credenciales por defecto.
  - **Producción**: errores ocultos (solo en log del servidor), cookies `Secure + HttpOnly + SameSite=Lax + StrictMode`, credenciales leídas desde variables de entorno del hosting.
- **Cabeceras HTTP de seguridad** en todas las páginas (`send_security_headers()`):
  - `X-Content-Type-Options: nosniff` — Previene MIME sniffing.
  - `X-Frame-Options: SAMEORIGIN` — Bloquea clickjacking.
  - `X-XSS-Protection: 1; mode=block` — Filtro XSS del navegador.
  - `Referrer-Policy: strict-origin-when-cross-origin` — Limita la información del Referer.
  - `Strict-Transport-Security` (solo producción) — Fuerza HTTPS 1 año.
- **Protección anti brute-force en login admin** (`admin-auth.php`):
  - Máximo 5 intentos de login cada 15 minutos por sesión.
  - Delay artificial aleatorio (200-500ms) en cada intento fallido.
  - Mensaje de error dedicado "Demasiados intentos" en la interfaz.
  - `session_regenerate_id(true)` al hacer login correcto (anti-fixation).
- **Ficheros `.htaccess` de seguridad**:
  - **Raíz**: Bloquea acceso a `config.php`, scripts de setup/migración, `check*.php`, `.git`, archivos `.md/.log/.sql`. Desactiva listado de directorios. Gzip y caché de assets.
  - **`/api/`**: Bloquea acceso directo a `conexion.php` y `setup-precios-db.php`.
  - **`/includes/`**: Acceso denegado completamente (solo PHP `include`).
  - **`/docs/`**: Acceso denegado completamente.
- **`.gitignore` completo**: Excluye `.env`, `check*.php`, scripts de migración, `refactor.ps1`, logs y configuraciones de IDE.

### Cambiado 🔄
- **`api/conexion.php` endurecido**: Las credenciales de BBDD ahora se leen desde `config.php` (variables de entorno en producción, valores XAMPP en local). Los errores de conexión se registran en el log del servidor sin mostrar detalles al usuario en producción (responde HTTP 503 genérico).
- **`includes/header.php`**: Ahora carga `config.php`, aplica cabeceras de seguridad y regenera el ID de sesión en el primer acceso (anti-fixation).
- **`admin/admin-dashboard.php` y `admin-login.php`**: Integrados con el sistema de configuración y cabeceras de seguridad.
- **`api/enviar-correo.php`**: Los errores PDO ya no exponen el mensaje de la excepción al cliente; se registran en el log del servidor.

### Seguridad 🔒
- Eliminada la exposición de `$e->getMessage()` en respuestas JSON de producción.
- Todas las cookies de sesión marcadas como `HttpOnly` (inaccesibles desde JavaScript).
- Scripts de setup/migración bloqueados por `.htaccess` (no ejecutables desde navegador en producción).

---

## [2.2.0] - 07-04-2026

### Añadido 🚀
- **Centro de Actividad (`🔔`)**: Nuevo módulo exclusivo para conserjes y administradores que centraliza todos los avisos de reservas activas. Accesible desde el panel de administración bajo el grupo «Usuarios y Reservas».
  - **Tarjetas de aviso detalladas**: Cada reserva muestra nombre, DNI, email y teléfono del cliente, la pista reservada, el tramo horario y la fecha. Las tarjetas se colorean según su estado: azul (HOY), naranja (EN CURSO), gris (PASADA) y verde (pendiente de verificar).
  - **Código de acceso prominente**: El código de entidad se muestra en grande con fuente monoespaciada sobre fondo oscuro. Incluye botón «📋 Copiar» que copia al portapapeles con feedback inmediato («✔ Copiado!»), permitiendo al conserje verificar de un vistazo si el cliente presenta el código correcto.
  - **Verificación de identidad presencial**: Botón «✓ Verificar Identidad» en cada tarjeta para que el conserje marque al cliente como identificado. El estado se persiste en base de datos con fecha y hora exacta (`verificado_admin`, `fecha_verificacion`). Al volver a pulsar el botón, se retira la verificación.
  - **Estadísticas en tiempo real**: Cuatro tarjetas resumen muestran el total de reservas hoy, el número visible con los filtros activos, las verificadas y las pendientes.
  - **Filtros**: Permite filtrar por «Solo hoy» o «Próximas 72 horas» y por pista específica. Botón de actualización manual.
  - **Nueva API `api/admin-actividad.php`**: Endpoint RESTful que gestiona la obtención de reservas activas con soporte de filtros, el conteo del badge y las acciones `verificar` / `desverificar`. Incluye migración automática de columnas (`ADD COLUMN IF NOT EXISTS`) para instalaciones existentes.

### Cambiado 🔄
- **Sidebar del panel de administración rediseñado**: Los enlaces del menú lateral se han reorganizado en cuatro grupos visuales con etiquetas de categoría, separadores y un nuevo estilo más limpio (sin fondo opaco en reposo, hover suave, fuente ligeramente menor). Los grupos son:
  - 🏟️ **Pistas** — Calendario de Bloqueos / Editar Precios
  - 👤 **Usuarios y Reservas** — Centro de Actividad / Buscador e Historial
  - 💬 **Comunicación** — Buzón de Consultas / Anuncios y Novedades
  - 🛠️ **Sistema** — Gestión de Admins
- **Badge inteligente en «Centro de Actividad»**: El número rojo visible sobre el ítem de menú refleja exclusivamente las reservas de hoy **sin verificar**. Al marcar todas como verificadas, el contador desaparece automáticamente. El badge se actualiza en cada carga del panel y cada vez que se completa una verificación.
- **Refactorización de la lógica de tabs**: Las funciones `hideAllViews()` y `deactivateAllTabs()` centralizan la gestión de visibilidad, eliminando la repetición de código que existía en cada handler de pestaña.
- Versión de `admin.js` bumpeada a `v2.2.0`.

---

## [2.1.1] - 07-04-2026

### Cambiado 🔄
- **Eliminación del rol "Concejal"**: Se ha simplificado el sistema de roles de administrador. Ahora todos los administradores tienen el mismo nivel de acceso bajo el rol `admin` único. Se han eliminado todas las referencias al rol `concejal` del código (frontend, backend, formularios y estilos).
  - `setup_db.php` — La columna `rol` pasa de `ENUM('admin','concejal')` a `VARCHAR(20)`. El usuario `concejal_deportes` ya no se genera.
  - `api/admin-users.php` — El endpoint de creación siempre asigna `'admin'` como rol, ignorando cualquier valor externo.
  - `admin-dashboard.php` — Eliminados: campo selector de Rol del formulario y línea de Rol del perfil en el sidebar. Eliminada columna Rol de la tabla.
  - `js/admin.js` — Eliminado el badge de color diferenciado por rol y la referencia al selector.
- **Script de Migración `migrate_remove_rol.php`**: Script de un solo uso para actualizar las instalaciones existentes, convirtiendo la columna `ENUM` a `VARCHAR`, actualizando filas `concejal` a `admin` y eliminando el usuario `concejal_deportes` de la BBDD.

---

## [2.1.0] - 07-04-2026

### Añadido 🚀
- **Gestión de Administradores (CRUD completo)**: Nueva pestaña «👥 Gestión de Admins» en el panel de administración que permite listar, crear, resetear contraseñas y eliminar usuarios administradores directamente desde la interfaz web.
  - **Tabla de Administradores**: Muestra ID, nombre de usuario y último acceso, con contador total de admins registrados.
  - **Crear Nuevo Admin**: Formulario integrado para dar de alta nuevos administradores especificando nombre de usuario, rol y contraseña inicial. Requiere verificación con la contraseña del admin que realiza la operación.
  - **Cambiar Contraseña (Resetear)**: Modal de seguridad que permite establecer una nueva contraseña para cualquier administrador. Solicita la contraseña actual del admin operador como doble factor de confirmación.
  - **Eliminar Administrador**: Botón de eliminación permanente protegido por modal de seguridad con verificación de contraseña. Incluye protecciones contra auto-eliminación y contra dejar la tabla sin ningún administrador.
- **Nueva API `api/admin-users.php`**: Endpoint RESTful con cuatro acciones (`get`, `create`, `reset`, `delete`). Todas las operaciones de escritura exigen la contraseña del admin autenticado como capa de seguridad adicional. Las contraseñas se almacenan con hash bcrypt (`password_hash`), garantizando que ninguna contraseña en texto plano queda expuesta.

---

## [2.0.1] - 07-04-2026

### Añadido 🚀
- **Restricción Lógica de Cancelación**: En el panel interactivo del historiador administrativo, el botón "Anular" ha sido deshabilitado y ocultado para todas aquellas reservas cuya fecha y hora de inicio ya pertenezcan al pasado o estén actualmente transcurriendo. Se implementó una verificación estricta asíncrona tanto visual (JavaScript) como segura desde el servidor (`api/admin-reservas.php`) para blindar el historial y prevenir que cancelaciones de sesiones ya consumidas alteren la integridad.

### Arreglado 🐛
- **Error 404 al iniciar sesión desde reservas ("Not Found")**: Solucionado un problema en la redirección. Cuando un usuario no registrado intentaba reservar, la web le enviaba correctamente a iniciar sesión recordando la ruta destino (ej. `checkout.php`). Sin embargo, al mandar estos datos a los validadores `api/user-auth.php` o `api/user-register.php`, las APIs lo redirigían literalmente y de forma relativa hacia `api/checkout.php` en vez de enviarlo a `html/checkout.php`, lo que lanzaba un Error 404 (No Encontrado). Corregido inyectando `../html/` como prefijo base para resolver la redirección pertinentemente.

---

## [2.0.0] - 06-04-2026

### Añadido 🚀
- **Novedades en Home (Anuncios)**: Creación de un panel público y dinámico de "Anuncios y Novedades". Los administradores ahora pueden publicar `Avisos`, `Importantes` e `Informaciones` que aparecerán inmediatamente en forma de tarjetas vistosas (*banners* temáticos según color) en la cabecera. Es ideal para avisos meteorológicos, cierres inesperados o promociones de campeonatos.
- **Herramienta Admin de Anuncios**: Pestaña incorporada al panel del gobierno para crear, ocultar temporalmente (toggle) o destruir (delete) notificaciones de la base de datos sin sobrecargar la interfaz.

### Cambiado 🔄
- **Sentido común en el Tiempo del Admin**: Ahora se neutralizan los botones de edición de las reservas en el panel de administrador para registros temporales que corresponden al pasado (físicamente imposibles de recuperar o ya en curso en este preciso instante local). Reemplaza visualmente los datos para remarcar el icono "⏳ Pasado/En Curso" logrando que nadie edite mal las tarifas de los partidos ya caducados por simple inercia.

---

## [1.9.0] - 06-04-2026

### Añadido 🚀
- **Portal de Consultas y Contacto Dinámico**: El formulario de contacto (`contacto.php`) ahora está conectado de forma real a la base de datos para almacenar y gestionar de forma centralizada todas las consultas de los usuarios.
- **Auto-completado Inteligente en Contacto**: Si el ciudadano tiene iniciada sesión con su cuenta, los campos personales del formulario (Nombre, Apellidos, DNI, Email, Teléfono) se pre-rellenan automáticamente, reduciendo tiempo y garantizando la consistencia de datos (la sesión la auto-verifica PHP y la inyecta al cargar la vista).
- **Nuevo panel Admin `Buzón de Consultas`**: Se ha creado un nuevo bloque interactivo dentro del panel administrativo (`admin-dashboard.php`) exclusivo para leer las peticiones de soporte.
  - **Filtros Personalizados**: Búsquedas combinadas por DNI o Correo, "Tema/Motivo", y estado de resolución.
  - **Historial Instantáneo**: Las peticiones entran a estado "Pendiente" y pueden expandirse para leer el mensaje al completo en formato emergente (modal).
- **Control de Estados y Correos Automatizados**: Los administradores disponen de un botón rotulado "Marcar como Resuelta" en cada ticket detallado. Su pulsación manda silenciosamente una notificación por correo al ciudadano confirmándole que el problema expuesto detalladamente ha sido solventado con éxito (función sujeta a activación SMTP en produccion `enviar-correo-func.php`).
- **Nuevas APIs de Infraestructura**: 
  - `api/guardar-consulta.php` – Recibe y sanitiza campos POST, inyecta Honeypots anti-spam para envíos no autorizados y bloqueos, e inserta a BBDD de forma segura mediante bindings `PDO`.
  - `api/admin-consultas.php` – Validador unificado de sesión de administrador para obtener listados JSON de incidencias o marcar "resoluciones" seguras.
- **Protección Antispam Básica**: Añadido un campo honeypot invisible para humanos pero detectable por bots; previniendo el relleno y automatización masiva a la base de datos sin molestar nunca con CAPTCHAs a los usuarios reales de Tarancón.
- **Script de Generación en Caliente**: Creado un archivo instalador `setup_db_consultas.php` para actualizar los esquemas relacionales `MySQL` de manera programática en cualquier entorno.

---

## [1.8.0] - 31-03-2026
  - 📞 **Teléfono** — Editable sin cierre de sesión. Se actualiza en BBDD y refresca la página.
  - 📧 **Email** — Requiere confirmar el nuevo email dos veces. Actualiza BBDD y cierra sesión automáticamente, forzando re-login con el nuevo correo.
  - 🔑 **Contraseña** — Requiere introducir la contraseña actual para confirmar identidad. Genera nuevo hash bcrypt, actualiza BBDD y cierra sesión.
  - 🔒 Nombre, Apellidos y DNI se muestran como datos protegidos con explicación ("contacta con el Ayuntamiento").
- **Nueva API `api/user-update.php`**: Endpoint JSON seguro que gestiona las tres acciones de actualización con validaciones completas en servidor y destrucción de sesión cuando corresponde.
- **Stat card "Total Gastado €"**: Cuarta tarjeta de estadísticas que suma el precio total de todas las reservas activas del usuario.
- **"Miembro desde"** en cabecera: badge azul en la cabecera del perfil que muestra la fecha en que el usuario creó su cuenta (`fecha_registro` de la tabla `usuarios`).
- **Códigos de acceso copiables (sutil)**: Los códigos en el historial y en las próximas reservas se copian al portapapeles con un clic. El feedback es mínimo: un flash verde instantáneo (1.3 s) sin modificar el layout ni mostrar iconos adicionales.
- **Historial con dos columnas de fecha**:
  - **Fecha Pista**: día para el que se hizo la reserva de la instalación.
  - **Reservado el**: día en que el ciudadano realizó la reserva (`fecha_registro`). Muestra "—" si la reserva es anterior a la migración de base de datos.

### Cambiado 🔄
- Tabla de historial ampliada de 6 a 7 columnas para acomodar las dos fechas.
- Estadísticas de perfil ampliadas de 3 a 4 tarjetas.
- Cabecera del perfil reestructurada con zona de acciones agrupadas verticalmente.

---

## [1.7.1] - 31-03-2026

### Arreglado 🐛
- **Página en blanco al hacer login/registro**: El cierre `?>` de `conexion.php` generaba output invisible (bytes `\r\n`) antes de que `session_start()` y `header("Location: ...")` pudieran ejecutarse, haciendo que las redirecciones fallasen silenciosamente y el navegador mostrase una página en blanco. Eliminado el cierre PHP según las buenas prácticas del lenguaje.
- **`header()` sin fallback**: Si por alguna razón PHP ya había enviado cabeceras, ninguna redirección funcionaba. Se añadió `ob_start()` al inicio de `user-auth.php`, `user-register.php` y `user-logout.php`, junto con una función `safe_redirect()` que actúa como fallback mediante `<meta http-equiv="refresh">` y `window.location.href` en caso de que `header()` no esté disponible.
- **Redirect vacío tras login**: El operador `??` no captura cadenas vacías `""`. El campo oculto `redirect` enviaba `""` cuando el usuario accedía directamente al login, causando un `Location:` vacío. Corregido usando `!empty()` para que los strings vacíos caigan al destino por defecto (`home.php`).
- **Error "Error de conexión con el servidor" al reservar**: La tabla `reservas` original carecía de las columnas `dni`, `telefono`, `precio_total`, `usuario_id`, `fecha_registro` y del valor `cancelada` en el ENUM `estado`. El INSERT de `enviar-correo.php` fallaba con PDOException 500. Corregido mediante detección dinámica de columnas con `SHOW COLUMNS FROM reservas` para construir el INSERT adaptándose a cualquier estado de la tabla.
- **Reserva guardada pero pantalla sin confirmación**: Cuando `mail()` fallaba (normal en XAMPP sin SMTP), la API devolvía HTTP 500, impidiendo que el JS mostrase la pantalla de confirmación. Ahora tanto el envío exitoso como el fallo de SMTP devuelven HTTP 200 con `status: success` y el código de acceso generado, mostrando siempre la confirmación al usuario.
- **Output corrupto en `enviar-correo.php`**: Añadido `ob_start()` y eliminado el cierre `?>` del archivo para evitar contaminación de la respuesta JSON con whitespace.

### Añadido 🚀
- **Copiar código con un clic**: En la pantalla de confirmación de reserva, el bloque del código de acceso es ahora interactivo. Al hacer clic sobre él, el código se copia automáticamente al portapapeles usando la API `navigator.clipboard`. Se muestra un feedback visual inmediato (✅ "¡Copiado!") que revierte a los 2 segundos. La selección manual del texto sigue siendo posible para quienes prefieran copiar a mano.
- **Hint visual en el código de acceso**: Un indicador con 📋 "Haz clic para copiar" aparece debajo del código, con animación de hover y cambio de color al estado copiado.
- **Script de migración `update_reservas_table.php`**: Creado script de un solo uso para añadir las columnas faltantes y actualizar el ENUM de `estado` en instalaciones existentes sin resetear la base de datos.

### Cambiado 🔄
- Etiqueta del code-box renombrada de "Código de Acceso del Conserje" a "Código de Acceso" para mayor claridad.
- Versión de `checkout.js` bumpeada a `1.7.1` para forzar recarga en caché de navegadores.

---

## [1.7.0] - 30-03-2026

### Añadido 🚀
- **Sistema de Login y Registro de Usuarios**: Implementación completa de autenticación para ciudadanos con registro por email, validación de DNI/teléfono, indicador de fuerza de contraseña y auto-login tras registro.
- **Página de Perfil de Usuario (`perfil.php`)**: Panel personal donde el usuario ve sus datos (nombre, email, DNI, teléfono), estadísticas de reservas (total, activas, canceladas) y un historial completo de todas sus reservas con códigos de acceso.
- **Auto-rellenado del Checkout**: Los campos del formulario de pago (nombre, apellidos, DNI, teléfono, email) se rellenan automáticamente con los datos de la cuenta del usuario logueado, reduciendo fricción en el proceso de reserva.
- **Vinculación de Reservas a Cuentas**: Las reservas quedan enlazadas al `usuario_id` de la cuenta, permitiendo consultas futuras de historial y estadísticas por usuario.
- **Header Dinámico**: El encabezado de la web ahora muestra iconos condicionales: "Iniciar Sesión" si no está logueado, o "Mi Perfil" + "Cerrar Sesión" si lo está.
- **Protección de Rutas**: Solo el checkout requiere login obligatorio. Las páginas de información (home, contacto, legales, horarios) siguen siendo de acceso libre.
- **Script de Base de Datos (`setup_db_users.php`)**: Creación automática de la tabla `usuarios` y de la columna `usuario_id` en `reservas`.

## [1.6.0] - 30-03-2026

### Añadido 🚀
- **Buscador Avanzado y Panel de Historial**: Se ha incluido una nueva pestaña en el portal administrativo (`admin-dashboard.php`) que proporciona un buscador detallado de reservas.
- **Filtros Flexibles**: Permite la búsqueda de reservas mediante DNI, Nombre completo, Pista (Tenis/Pádel), Rango de Fechas y Estado (Reservada, Bloqueo Admin, Cancelada).
- **Gestión de Incidencias**: Los administradores ahora pueden ver todos los detalles personales y de reserva en tiempo real mediante un nuevo Modal emergente, incluyendo teléfono, DNI y precio total.
- **Anulación de Reservas**: Incorporada funcionalidad que capacita a los administradores a anular reservas (`cancelada`), previniendo overbooking o confusiones y solucionando rápida y eficazmente las incidencias del servicio al usuario.
- **Exportación en CSV**: Agregado un botón que compila instantáneamente los resultados de la búsqueda de reservas vigente en un documento CSV organizado, optimizando reportes y contabilidad.
- **Ampliación Base de Datos (`reservas`)**: Implementación del script auto-ejecutable `setup_db_update.php` que añade automáticamente soportes de almacenamiento robusto para: DNI, Teléfono y Precios Totales dentro de la base general de reservas.

## [1.5.0] - 26-03-2026

### Añadido 🚀
- **Edición Dinámica de Precios**: Se ha añadido una nueva pestaña "Editar Precios" en el panel de control del administrador con un calendario de 30 días. Los administradores pueden modificar el precio base o cambiar/eliminar la tarifa de luz para horas específicas. Los cambios se reflejarán instantáneamente en el área pública para los ciudadanos y en el proceso de checkout.

## [1.4.0] - 25-03-2026

### Añadido 🚀
- **Validación de Formularios en Checkout**: Se ha integrado comprobación estricta y en tiempo real en los datos personales de la reserva. El DNI convierte automáticamente las letras a mayúsculas y purga espacios, el teléfono fuerza formato numérico de 9 dígitos, y el correo es validado garantizando integridad antes del cobro.

## [1.3.0] - 25-03-2026

### Añadido 🚀
- **Reservas Reales y Códigos QR**: El pago simulado ahora crea reservas auténticas en la base de datos que bloquean la pista inmediatamente (evitando *overbooking*).
- **Notificaciones Dinámicas por Correo (`api/enviar-correo.php`)**: Al reservar, el servidor genera un código alfanumérico aleatorio y un **Código QR de acceso**, enviándolos incrustados en un correo HTML automático al ciudadano.

## [1.2.0] - 25-03-2026

### Añadido 🚀
- **Desbloqueo Administrativo (`api/desbloquear-hora.php`)**: Nueva utilidad que permite a los perfiles de Ayuntamiento desbloquear franjas horarias que previamente fueron bloqueadas administrativamente, volviéndolas a poner rojas a la vista del ciudadano.
- **Interfaz de Desbloqueo**: Actualizaciones en `admin-dashboard.php` y `admin.js` para permitir seleccionar horas bloqueadas y deshabilitar el bloqueo con un solo click.

## [1.1.1] - 25-03-2026

### Cambiado 🔄
- **Arquitectura de Componentes (PHP)**: Refactorizado el código base para extraer el encabezado (`header`) y pie de página (`footer`) a archivos independientes en la carpeta `/includes/`. Se convirtieron todos los archivos públicos de `.html` a `.php` para evitar la duplicación de código mediante el comando `include` de PHP.
- **Redes Sociales**: Actualizados los enlaces del encabezado para redirigir a los perfiles oficiales del Ayuntamiento de Tarancón en X (Twitter) y Facebook.

## [1.1.0] - 25-03-2026

### Añadido 🚀
- **Panel de Administración (`admin/`)**: Creación de login seguro (`admin-login.php`) y dashboard a 30 días (`admin-dashboard.php`) para la gestión administrativa de pistas.
- **Base de Datos (`ayuntamiento_reservas`)**: Implementación del esquema en MySQL usando PDO. Añadida la tabla `usuarios_admin` y reestructurada la tabla `reservas`.
- **API Backend (`api/`)**: Nuevos endpoints PHP (`get-horarios.php` y `bloquear-hora.php`) para comunicar el frontend con la BBDD de forma asíncrona.
- **Integración Pública**: Sincronización del archivo interactivo `horarios.js` con la API para reflejar bloqueos de administrador y mostrar horas como "No Disponible" evitando colisiones.
- **Documentación (`docs/`)**: Creación de los archivos manuales `CHANGELOG.md` e `INSTALL.md` para mantener un historial ordenado y facilitar la instalación de futuros desarrolladores.

### Cambiado 🔄
- **Estructura Arquitectónica**: Reorganización masiva del proyecto para cumplir con mejores prácticas de GitHub y despliegue:
  - Archivos HTML movidos a `/html`
  - Archivos CSS movidos a `/css`
  - Archivos JS movidos a `/js`
  - Lógica administrativa a `/admin` y APIs a `/api`
  - Modificación de todas las rutas (`src` y `href`) para adaptarse al nuevo árbol de directorios.

## [1.0.0] - Versión Inicial (Febrero/Marzo 2026)

### Añadido 🚀
- Diseño base estático y moderno usando HTML5, CSS3, y JS Vanilla.
- Páginas de reservas de Pádel y Tenis.
- Funcionalidad interactiva del calendario en el Frontend basada en `localStorage`.
- Páginas de Políticas y Textos Legales (Accesibilidad, Privacidad, Cookies).
- Formularios interactivos y vista de checkout (Pago).
- Archivo básico `enviar-correo.php` para la simulación del envío de confirmaciones con código automático.
