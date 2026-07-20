
[![Full Pipeline CI/CD](https://github.com/PS-GLangosta/bookstack-testing-project/actions/workflows/full-pipeline.yml/badge.svg)](https://github.com/PS-GLangosta/bookstack-testing-project/actions/workflows/full-pipeline.yml)

![Tests](https://img.shields.io/badge/tests-+600-blue)
![Coverage](https://img.shields.io/badge/coverage-95%25-brightgreen)
![Assertions](https://img.shields.io/badge/assertions-2545-blue)
![UAT](https://img.shields.io/badge/UAT-100%25%20aprobado-brightgreen)

# BookStack Testing Project

**Asignatura:** Pruebas de Software — UNSA 2026
**Docente:** Ing. Robert Edison Arisaca Mamani
**Fase de Evaluación:** Hito 3 — Defensa Final

**Integrantes del Equipo:**

| Nombre | Rol |
|:-------|:----|
| Joe Daniel Flores Choquehuenca | Test Leader |
| Mauricio Eduardo Zegarra Puma | Test Architect |
| Cielo Cristal Meza Vizcarra | Test Analyst |
| Rommel Abraham Chambi Velasquez | Test Analyst |
| Jeremy Joshua Perez Huamani | Test Analyst |
| Ower Frank Lopez Arela | Test Designer |

Proyecto académico orientado al análisis, diseño, implementación y documentación de un proceso completo de pruebas de software sobre [**BookStack**](https://github.com/BookStackApp/BookStack), una plataforma de documentación de código abierto construida con PHP y Laravel.

---

## Métricas Finales 

| Métrica | Valor |
|:--------|:------|
| Tests totales (suite Langosta) | **+600** |
| Aserciones totales | **2,545** |
| Cobertura global de líneas (phpcov) | **95%** (12,718 / 13,419 líneas) |
| Cobertura módulo API | ~98% |
| Flujos de sistema evaluados | **6** (ST-01 a ST-06) |
| Casos de sistema | **60** (31 base + 29 complementarios) |
| Tasa de éxito pruebas de sistema | **98.3%** (59/60) |
| Defecto detectado (ST-D-01) | 1 — evidencia formal mantenida |
| Hallazgos técnicos documentados | **7** (HT-01 a HT-07) |
| Escenarios UAT aprobados | **16/16 (100%)** |
| Aserciones UAT | **124** |
| Atributos Myers evaluados | **2** (Seguridad · Usabilidad) |
| Jobs pipeline CI/CD | **5** (Unit · Integration · System · Feature · Coverage-merge) |
| Archivos nativos BookStack excluidos | 55 |
| Tests nativos BookStack (referencia) | 1,802 |

---

## Recursos y Plataformas del Proyecto

| Recurso | Enlace |
|:--------|:-------|
| Repositorio | [PS-GLangosta/bookstack-testing-project](https://github.com/PS-GLangosta/bookstack-testing-project) |
| Presentación del Producto | [GitHub Pages](https://ps-glangosta.github.io/bookstack-testing-project/) |
| Tablero de Gestión (Kanban) | [GitHub Projects](https://github.com/orgs/PS-GLangosta/projects/1) |
| Documentación Técnica | [GitHub Wiki](https://github.com/PS-GLangosta/bookstack-testing-project/wiki) |
| Pipeline CI/CD | [GitHub Actions](https://github.com/PS-GLangosta/bookstack-testing-project/actions) |
| Seguimiento de Incidencias | [GitHub Issues](https://github.com/PS-GLangosta/bookstack-testing-project/issues) |

---

La documentación técnica del proyecto se encuentra centralizada en la GitHub Wiki. En ella se recopilan los artefactos documentales generados durante los distintos hitos del proyecto, incluyendo requisitos, planes de prueba, informes de ejecución, evidencias del proceso de validación y documentación del pipeline de integración continua.

Los documentos disponibles son los siguientes:
## Documentación Técnica (Wiki) — Estado Final

| Documento | Descripción |
|:----------|:------------|
| [HOME](https://github.com/PS-GLangosta/bookstack-testing-project/wiki) | Índice general del proyecto con métricas finales y enlaces a todas las páginas |
| [REQUISITOS DEL SISTEMA](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/REQUISITOS-DEL-SISTEMA) | Requisitos funcionales (RF) y no funcionales (RNF) de BookStack con matriz de trazabilidad |
| [PLAN DE PRUEBAS UNITARIAS](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/PLAN-DE-PRUEBAS-UNITARIAS) | Estrategia, casos diseñados y criterios — Hito 1 |
| [PLAN DE PRUEBAS UNITARIAS (HITO 2)](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/PLAN-DE-PRUEBAS-UNITARIAS-%28HITO-2%29) | Plan extendido de pruebas unitarias para la fase de ejecución |
| [PRUEBAS UNITARIAS Y COBERTURA](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/PRUEBAS-UNITARIAS-Y-COBERTURA) | Resultados de ejecución, métricas de cobertura global (95%) y hallazgos HT-01 a HT-07 |
| [Diseño de Casos Funcionales (CPF)](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/Dise%C3%B1o-de-Casos-Funcionales) | 220 casos de prueba de caja negra — CPF-001 a CPF-220 |
| [INFORME DE CASOS DE PRUEBA FUNCIONALES](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/INFORME-DE-CASOS-DE-PRUEBA-FUNCIONALES) | Resultados de ejecución de los casos funcionales priorizados |
| [PLAN DE PRUEBAS DE INTEGRACIÓN](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/PLAN-DE-PRUEBAS-DE-INTEGRACI%C3%93N) | Puntos de integración IT-001–IT-008, estrategia y criterios |
| [PLAN DE PRUEBAS DE SISTEMA](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/PLAN-DE-PRUEBAS-DE-SISTEMA) | Diseño de flujos ST-01–ST-06 y selección de atributos Myers |
| [INFORME DE PRUEBAS DE SISTEMA](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/INFORME-DE-PRUEBAS-DE-SISTEMA) | Resultados ST-01–ST-06 · Seguridad y Usabilidad · Defecto ST-D-01 |
| [PLAN UAT](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/PLAN-UAT) | Estrategia y escenarios de pruebas de aceptación de usuario |
| [INFORME DE PRUEBAS DE ACEPTACIÓN (UAT)](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/INFORME-DE-PRUEBAS-DE-ACEPTACION-DE-USUARIO-%28UAT%29) | 16 tests · 124 aserciones · 100% aprobados · 32.53s |
| [EVIDENCIAS CI CD](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/EVIDENCIAS-CI-CD) | Pipeline 5 jobs en verde · cobertura 95% · badge GitHub Actions |

---

## Estructura del Repositorio

```
bookstack-testing-project/
├── .github/
│   └── workflows/
│       ├── full-pipeline.yml          # Pipeline completo: 5 jobs paralelos + coverage-merge
│       └── pages-build-deployment.yml # GitHub Pages
├── tests/
│   ├── Unit/                          # Pruebas unitarias del equipo (~20 archivos)
│   ├── Integration/                   # Pruebas de integración (4 archivos: IT-001 a IT-008)
│   ├── System/                        # Flujos E2E ST-01 a ST-06 (6 archivos)
│   └── UAT/                           # Escenarios UAT + complementos (1 archivo, 16 tests)
├── dev/
│   └── docker/                        # Dockerfile y entrypoint de producción
├── phpunit.xml                        # Testsuite "Langosta" — 55 archivos nativos excluidos
├── index.html                         # Presentación pública (GitHub Pages)
└── README.md                          # Este archivo
```

---

## Estrategia de Pruebas — Resumen por Nivel

### Hito 1 — Pruebas Unitarias (Sprint 1)
- Plan de pruebas unitarias elaborado
- Estructura del repositorio y tablero Kanban configurados

### Hito 2 — Pruebas Unitarias + Funcionales + Integración (Sprint 2)
- **Cobertura base alcanzada:** 91.44% (suite nativa BookStack)
- **Casos funcionales diseñados:** 220 CPF (caja negra)
- **Plan de integración:** IT-001 a IT-008 definidos

### Hito 3 — Sistema, Aceptación y CI/CD completo (Sprints 3 y 4)
- **Suite Langosta aislada:** +600 tests / 2,545 aserciones / 95% cobertura global
- **Pruebas de sistema:** 6 flujos E2E, atributos Myers (Seguridad + Usabilidad)
- **Pruebas de aceptación:** 16 escenarios UAT, 100% aprobados
- **Pipeline CI/CD:** 5 jobs en verde, consolidación de cobertura con phpcov
- **Defecto real detectado:** ST-D-01 — desincronización SoftDelete / índice de búsqueda
- **Principio de independencia:** todas las pruebas crean sus propios datos — cero dependencias globales

---

## Cronograma del Proyecto

### Hito 1 — Sprint 1 (Mayo 2026)
- Configuración de tablero de gestión (GitHub Projects)
- Presentación inicial del producto (GitHub Pages)
- Plan de pruebas unitarias
- Estructura inicial del repositorio

### Hito 2 — Sprint 2 (Junio 2026)
- Actualización del Plan de Pruebas Unitarias
- Implementación de pruebas unitarias (91.44% cobertura base)
- Diseño de 220 casos funcionales (CPF-001 a CPF-220)
- Plan de Pruebas de Integración (IT-001 a IT-008)
- Configuración inicial de CI/CD

### Hito 3 — Sprints 3 y 4 (Junio–Julio 2026)
- Ejecución de pruebas de integración (IT-001 a IT-008, ~98% cobertura API)
- Pruebas de sistema: 6 flujos E2E, ST-01 a ST-06
- Pruebas de aceptación: 16 escenarios UAT, 100% aprobados
- Pipeline CI/CD completo: 5 jobs en verde (PR #70)
- Suite Langosta aislada: 600 tests, 2,545 aserciones (PR #77)
- Consolidación de cobertura: 95% global (phpcov merge)
- Documentación técnica completa en Wiki (13 páginas)

### Defensa Final (Julio 2026)
- Artículo IEEE — borrador v3 completo
- Presentación de defensa
- Demostración integrada de herramientas

---

## Entorno Tecnológico

| Herramienta | Versión | Propósito |
|:------------|:--------|:----------|
| PHP | 8.2 | Runtime del sistema bajo prueba |
| Laravel | 12 | Framework del SUT |
| MariaDB | 11.4 | Base de datos (Docker) |
| PHPUnit | 11.x | Ejecución de pruebas unitarias, integración y sistema |
| Xdebug | 3.5 | Medición de cobertura de código |
| phpcov | 9.x | Consolidación de artefactos de cobertura entre jobs |
| Docker Compose | ≥2.0 | Contenerización del entorno local |
| GitHub Actions | — | Pipeline CI/CD automatizado |
| GitHub Wiki | — | Documentación técnica centralizada (13 páginas) |
| GitHub Projects | — | Gestión ágil del proyecto (Kanban) |
| GitHub Pages | — | Presentación pública del proyecto |

---

## Artefactos de Entrega del Hito 3

El presente README constituye el índice general de los entregables correspondientes al Hito 3 del proyecto **BookStack Testing Project**. En esta sección se describen los principales artefactos generados durante el desarrollo del proyecto, indicando su propósito y la ubicación donde pueden ser consultados.

| Artefacto | Descripción | Ubicación |
|:----------|:------------|:----------|
| README.md | Documento principal del proyecto. Describe la estructura general, métricas finales, recursos disponibles y sirve como índice de los artefactos entregados durante el Hito 3. | Este archivo |
| Repositorio GitHub | Contiene el código fuente, configuración del entorno, suites de pruebas, flujos de trabajo, documentación y control de versiones del proyecto. | https://github.com/PS-GLangosta/bookstack-testing-project |
| GitHub Pages | Sitio web público utilizado para presentar el proyecto, sus objetivos, métricas generales y acceso a los recursos principales. | https://ps-glangosta.github.io/bookstack-testing-project/ |
| GitHub Projects | Tablero Kanban empleado para la planificación, seguimiento y gestión de tareas durante los diferentes sprints del proyecto. | https://github.com/orgs/PS-GLangosta/projects/1 |
| GitHub Wiki | Repositorio central de la documentación técnica del proyecto, incluyendo requisitos, planes de prueba, informes, evidencias y documentación asociada al proceso de pruebas. | https://github.com/PS-GLangosta/bookstack-testing-project/wiki |
| GitHub Actions | Plataforma de Integración Continua y Despliegue Continuo (CI/CD) utilizada para ejecutar automáticamente las suites de pruebas, consolidar la cobertura y validar el proyecto. | https://github.com/PS-GLangosta/bookstack-testing-project/actions |
| GitHub Issues | Sistema de seguimiento de incidencias, mejoras y actividades registradas durante el desarrollo del proyecto. | https://github.com/PS-GLangosta/bookstack-testing-project/issues |
| Artículo IEEE | Documento técnico que sintetiza la metodología, resultados y conclusiones obtenidas durante el desarrollo del proyecto. Forma parte de los entregables académicos del Hito 3. | Carpeta **HITO-3** |
| Presentación Final | Presentación utilizada durante la sustentación del Hito 3, que resume los principales resultados técnicos y académicos del proyecto. | Carpeta **HITO-3** |

---

## Equipo de Trabajo

| Integrante | Rol Asignado |
| :---- | :---- |
| Joe Daniel Flores Choquehuenca | Líder de Pruebas |
| Mauricio Eduardo Zegarra Puma | Arquitecto de Pruebas |
| Cielo Cristal Meza Vizcarra | Analista de Pruebas |
| Rommel Abraham Chambi Velasquez | Analista de Pruebas |
| Jeremy Joshua Perez Huamani | Analista de Pruebas |
| Ower Frank Lopez Arela | Diseñador de Pruebas |
---

*Universidad Nacional de San Agustín de Arequipa — Facultad de Producción y Servicios — Escuela Profesional de Ingeniería de Sistemas — 2026*