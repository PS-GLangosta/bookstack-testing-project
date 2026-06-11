
# Configurar entorno local de BookStack para ejecución de pruebas

Aquí se describen los pasos necesarios para instalar y configurar BookStack en un entorno Windows, incluyendo la base de datos, dependencias y la preparación específica para poder ejecutar el conjunto de pruebas unitarias.

## Requisitos previos

- Windows 11 (o superior)
- Git Bash y PowerShell (ejecutar como administrador cuando se indique)
- MySQL Server 8.4 instalado y en ejecución
- PHP 8.4.x (Thread Safe) con extensiones: `pdo_mysql`, `mysqli`, `gd`, `zip`, `openssl`
- Composer
- Node.js y npm

## Pasos de configuración

### 1. Clonar el repositorio

```bash
cd C:\laragon\www   # o la carpeta de proyectos
git clone https://github.com/BookStackApp/BookStack.git
cd BookStack
```

### 2. Configurar archivo `.env`

```bash
copy .env.example .env
```

Editar `.env` con los siguientes valores mínimos:

```ini
APP_URL=http://bookstack.test
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bookstack_db
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Instalar dependencias

```bash
composer install
npm install
npm run build
```

### 4. Preparar la base de datos

Iniciar el servicio MySQL (PowerShell como administrador):

```powershell
net start MySQL   # o MySQL84 según instalación
```

Crear las bases de datos y usuario para pruebas (en cliente MySQL):

```sql
CREATE DATABASE bookstack_db;
CREATE DATABASE `bookstack-test`;
CREATE USER 'bookstack-test'@'localhost' IDENTIFIED BY 'bookstack-test';
GRANT ALL PRIVILEGES ON `bookstack-test`.* TO 'bookstack-test'@'localhost';
FLUSH PRIVILEGES;
```

### 5. Ejecutar migraciones y clave de aplicación

```bash
php artisan key:generate
php artisan migrate
```

### 6. Preparar la base de datos específica para pruebas

```bash
composer refresh-test-database -- --force
```

### 7. Configurar PHPUnit

Asegurar que en `phpunit.xml` existan las variables de entorno para la base de datos de pruebas:

```xml
<env name="DB_TESTING_HOST" value="127.0.0.1"/>
<env name="DB_TESTING_PORT" value="3306"/>
<env name="DB_TESTING_DATABASE" value="bookstack-test"/>
<env name="DB_TESTING_USERNAME" value="bookstack-test"/>
<env name="DB_TESTING_PASSWORD" value="bookstack-test"/>
```

### 8. Verificar que el entorno esté listo

Ejecutar una prueba simple sin cobertura:

```bash
vendor/bin/phpunit --filter test_basic
```

Si no hay errores de conexión a BD, el entorno está correcto.

## Resumen de herramientas utilizadas

| Herramienta | Propósito |
|-------------|-----------|
| Git Bash / PowerShell | Ejecución de comandos |
| MySQL Server 8.4 | Base de datos |
| PHP 8.4.22 | Intérprete PHP |
| Composer | Dependencias PHP |
| Node.js / npm | Assets frontend |
| Laravel Artisan | Migraciones y configuración |
| PHPUnit | Framework de pruebas (sin cobertura aún) |

Con estos pasos, el entorno está listo para ejecutar pruebas unitarias.
```

---

