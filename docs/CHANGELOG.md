# CHANGELOG

Todos los cambios notables de este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/), y este proyecto se adhiere a un versionamiento semántico.

---

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
