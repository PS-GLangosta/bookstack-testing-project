# Generar y analizar reporte de cobertura de pruebas unitarias en BookStack

Una vez que el entorno local está configurado, en este comentario se explica cómo instalar un driver de cobertura (Xdebug), ejecutar las pruebas con PHPUnit y generar un informe HTML con el análisis de cobertura.

## 1. Instalar Xdebug (driver de cobertura)

### 1.1. Identificar la versión correcta

Ejecutar:

```bash
php -i
```

Copiar toda la salida y pegarla en [Xdebug Wizard](https://xdebug.org/wizard). El wizard indicará el archivo DLL específico a descargar (por ejemplo, `php_xdebug-3.5.3-8.4-ts-vs17-x86_64.dll`).

### 1.2. Instalar la extensión

- Descargar el archivo DLL indicado.
- Copiarlo a la carpeta `ext` de PHP (ej. `C:\Users\Usuario\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\ext\`).
- Renombrar el archivo a `php_xdebug.dll`.

### 1.3. Activar Xdebug en `php.ini`

Editar el archivo `php.ini` (en la misma carpeta raíz de PHP) y agregar al final:

```ini
[XDebug]
zend_extension = xdebug
xdebug.mode = coverage
```

### 1.4. Verificar la instalación

Reiniciar la terminal y ejecutar:

```bash
php -m | findstr xdebug
```

Debe aparecer `xdebug` en la lista de módulos.

## 2. Ejecutar las pruebas con cobertura

Aumentar el límite de memoria para evitar errores por falta de recursos:

```bash
cd ~/Documents/ARISACA/BookStack   # ruta del proyecto
php -d memory_limit=1G vendor/bin/phpunit --coverage-html coverage
```

El proceso ejecutará las 1566 pruebas y generará una carpeta `coverage/` con el informe HTML.

## 3. Analizar el reporte de cobertura

### 3.1. Abrir el informe

```bash
explorer coverage\index.html
```

Se abrirá en el navegador una vista general con porcentajes de cobertura por directorio.

### 3.2. Interpretación de los datos

El informe muestra tres métricas principales:

- **Lines**: cobertura por línea de código ejecutada.
- **Functions and Methods**: cobertura de funciones y métodos.
- **Classes and Traits**: cobertura de clases y traits.

Colores indicativos:
- Verde (success): 90% – 100%
- Amarillo (warning): 50% – 90%
- Rojo (danger): 0% – 50%


### 3.3. Navegación detallada

Desde el informe principal se puede hacer clic en cada directorio y luego en cada archivo para ver:
- Las líneas coloreadas (verde = cubiertas, rojo = no cubiertas).
- El número de veces que se ejecutó cada línea.

Esto permite identificar partes del código que carecen de pruebas y priorizar la escritura de nuevas pruebas.

## 4. Herramientas involucradas

| Herramienta | Versión / Detalle |
|-------------|-------------------|
| Xdebug 3.5.3 | Driver de cobertura |
| PHPUnit 11.5.55 | Ejecución de pruebas y generación del informe |
| PHP 8.4.22 | Intérprete base |
| Navegador web | Visualización del informe HTML |
