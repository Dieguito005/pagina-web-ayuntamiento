# CHANGELOG

Todos los cambios notables de este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/), y este proyecto se adhiere a un versionamiento semántico.

---

## [1.8.0] - 31-03-2026

### Añadido 🚀
- **Sección "Próximas Reservas"**: Nueva sección visual destacada en el perfil que muestra únicamente las reservas con fecha futura y estado activo. Cada tarjeta incluye instalación, fecha, horario, código de acceso y una **cuenta atrás dinámica** ("¡Hoy!", "Mañana", "En N días") calculada automáticamente.
- **Edición de credenciales (modal tabbed)**: Nuevo botón "✏️ Editar datos" en la cabecera del perfil que abre un modal con tres pestañas:
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
