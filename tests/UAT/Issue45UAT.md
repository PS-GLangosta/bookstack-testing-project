@'
# Issue #45 - Pruebas de Aceptación UAT

## Objetivo

Registrar la ejecución de los escenarios de pruebas de aceptación de usuario definidos para la issue #45.

Estas pruebas validan los flujos principales del sistema desde la perspectiva del usuario final, considerando creación de contenido, permisos, roles, búsqueda, exportación e instalación limpia.

## Ubicación de archivos

| Archivo | Propósito |
|---|---|
| `tests/UAT/Issue45UatTest.php` | Ejecuta los escenarios UAT automatizables mediante PHPUnit/Laravel. |
| `tests/UAT/ISSUE_45_UAT.md` | Documenta los escenarios, resultados, observaciones y evidencias. |
| `tests/UAT/evidences/` | Carpeta para capturas, logs o evidencias por escenario. |

## Comando de ejecución

```powershell
php artisan test .\tests\UAT\Issue45UatTest.php