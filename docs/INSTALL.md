# Manual de Instalación y Uso 📖

Bienvenido al repositorio oficial del **Proyecto Alquileres Tarancón**. 
Este manual explica cómo instalar el sistema en un entorno local y sus funcionalidades principales.

## ⚙️ Requisitos Previos

Para ejecutar la página web con todas sus funcionalidades (incluyendo el sistema de Panel de Administración), necesitas un servidor local con soporte para PHP y MySQL.

1. Descarga e instala [XAMPP](https://www.apachefriends.org/es/index.html).
2. Clona o mueve esta carpeta del proyecto dentro del directorio `htdocs` de XAMPP (Ej: `C:\xampp\htdocs\pagina-web-ayuntamiento`).

---

## 🛠️ Instalación Paso a Paso

### 1. Iniciar el Servidor Local
Abre el panel de control de XAMPP y haz clic en **Start** en los módulos de **Apache** y **MySQL**. Deben ponerse en color verde.

### 2. Creación de la Base de Datos
He automatizado este paso para que no tengas que escribir código SQL.
1. Abre tu navegador web favorito (Chrome, Firefox, Edge).
2. Ve a la siguiente URL (asegúrate de que coincida con el nombre de tu carpeta):
   > `http://localhost/pagina-web-ayuntamiento/setup_db.php`
3. Verás un mensaje de éxito indicando que la base de datos `ayuntamiento_reservas` se ha creado y se han inyectado 5 credenciales de Administrador.

> **Importante:** Por normativas de seguridad en entornos de producción, se recomienda borrar el archivo `setup_db.php` tras ejecutarlo con éxito.

### 3. ¡Todo Listo!
Abre la página web yendo a la raíz del proyecto en tu navegador:
> `http://localhost/pagina-web-ayuntamiento/`

Esta URL redirigirá automáticamente a `html/home.html`.

---

## 👥 Credenciales de Administración Pre-establecidas

Para probar el panel oculto de administración, ve al pie de página (footer) de la web y haz clic en **🔒 Acceso Empleados**. Puedes usar cualquiera de estas cuentas:

| Usuario | Contraseña | Rol |
|---------|------------|-----|
| `admin_juan` | `Tarancon2026!` | Admin |
| `admin_maria` | `Tarancon2026!` | Admin |
| `admin_carlos` | `Tarancon2026!` | Admin |
| `admin_laura` | `Tarancon2026!` | Admin |
| `concejal_deportes` | `Deportes2026!` | Concejal |

---

## 📂 Estructura del Proyecto

Tras las últimas mejoras, el proyecto tiene un orden modular estricto:

- **`/html/`**: Contiene exclusivamente las vistas públicas de la web (home, tenis, aviso legal, etc).
- **`/css/`**: Contiene las hojas de estilo modulares usadas en la web.
- **`/js/`**: Contiene la lógica interactiva de interfaz y de peticiones a la API.
- **`/admin/`**: Contiene la aplicación restringida para validación y Dashboard de administradores.
- **`/api/`**: Contiene los endpoints PHP que el JS y el panel consumen de forma asíncrona.
- **`/assets/`**: Archivos estáticos como imágenes urbanas, iconos y logos del ayuntamiento.
- **`/docs/`**: Documentación técnica, changelog y guías.

---

## 📱 Uso del Sistema

### Modo Ciudadano
1. El usuario entra a la web y selecciona el deporte (Tenis o Pádel).
2. Se selecciona el número de días vista y se escoge una franja horaria verde (Disponible).
3. Se accede a la página de Pago simulado (`checkout.html`) donde al terminar se simulará el cobro y se preparará un correo de confirmación.

### Modo Administrador (Concejalía de Deportes)
1. Entra al Panel de Control.
2. Escoge un Deporte y un Día en los próximos 30 días de calendario real.
3. Se verán las franjas horarias ocupadas por los ciudadanos (en verde y bloqueadas). Si hay una franja libre, se puede hacer clic en "Bloquear Horario Seleccionado".
4. Esto marcará internamente la hora como inhabilitada (`bloqueada_admin`), e impedirá que los ciudadanos públicos paguen por usarla en ese tramo de tiempo sin necesidad de afectar el flujo económico del sistema.
