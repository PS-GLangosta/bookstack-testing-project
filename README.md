# **BookStack Testing Project**

**Asignatura:** Pruebas de Software \- UNSA 2026

**Docente:** Ing. Robert Edison Arisaca Mamani

**Fase de Evaluación:** Hito 2 – Sprint 2 (Fecha límite: 10 de junio de 2026\)

**Integrantes del Equipo:**

* Chambi Velasquez Rommel Abraham  
* Flores Choquehuanca Joe Daniel  
* Lopez Arela Ower Frank  
* Meza Vizcarra Cielo Cristal  
* Perez Huamani Jeremy Joshua  
* Zegarra Puma Mauricio Eduardo

Proyecto académico orientado al análisis, diseño, implementación y documentación de un proceso completo de pruebas de software sobre [**BookStack**](https://github.com/BookStackApp/BookStack), una plataforma de documentación de código abierto construida con PHP y Laravel.

## **Autoevaluación del Equipo \- Hito 2**

**Nota:** Los siguientes porcentajes son de carácter preliminar y se encuentran sujetos a validación final por parte del equipo previo a la entrega oficial de la documentación.

| Integrante | Porcentaje de esfuerzo (Hito 2\) |
| :---- | :---- |
| Joe Daniel Flores Choquehuenca | 20% |
| Mauricio Eduardo Zegarra Puma | 18% |
| Cielo Cristal Meza Vizcarra | 17% |
| Rommel Abraham Chambi Velasquez | 15% |
| Jeremy Joshua Perez Huamani | 15% |
| Ower Frank Lopez Arela | 15% |

## **Recursos del Proyecto**

| Recurso | Enlace de Acceso | Estado de Verificación |
| :---- | :---- | :---- |
| Repositorio | [PS-GLangosta/bookstack-testing-project](https://github.com/PS-GLangosta/bookstack-testing-project) | Público y accesible |
| Presentación del Producto | [GitHub Pages](https://ps-glangosta.github.io/bookstack-testing-project/) | Activo (En proceso de actualización para el Hito 2\) |
| Tablero de Gestión (Kanban) | [GitHub Projects](https://github.com/orgs/PS-GLangosta/projects/1) | Activo |
| Documentación Técnica | [GitHub Wiki](https://github.com/PS-GLangosta/bookstack-testing-project/wiki) | Activo (8 documentos registrados) |
| Integración Continua (CI/CD) | [GitHub Actions](https://github.com/PS-GLangosta/bookstack-testing-project/actions) | Parcialmente activo (Despliegue operativo; CI en configuración) |
| Seguimiento de Incidencias | [GitHub Issues](https://github.com/PS-GLangosta/bookstack-testing-project/issues) | Activo |

## **Documentación Técnica (Wiki) \- Hito 2**

| Documento | Descripción del Contenido |
| :---- | :---- |
| [REQUISITOS DEL SISTEMA](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/REQUISITOS-DEL-SISTEMA) | Especificación de requisitos funcionales (RF-01 al RF-08) y no funcionales (RNF-01 al RNF-06) de BookStack, incluyendo matriz de trazabilidad. |
| [PLAN DE PRUEBAS UNITARIAS (HITO 2\)](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/PLAN-DE-PRUEBAS-UNITARIAS-%28HITO-2%29) | Planificación actualizada de pruebas unitarias: componentes identificados, técnicas de diseño a aplicar y criterios de finalización. |
| [PRUEBAS UNITARIAS Y COBERTURA](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/PRUEBAS-UNITARIAS-Y-COBERTURA) | Resultados de la ejecución, métricas de cobertura (91.44% en líneas), aportes individuales y registro de hallazgos técnicos (HT-01 al HT-05). |
| [DISEÑO DE CASOS DE PRUEBAS FUNCIONALES](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/DISE%C3%91O-DE-CASOS-DE-PRUEBAS-FUNCIONALES) | Diseño de 30 casos de prueba de caja negra (CPF-001 al CPF-030) basados en técnicas de Partición de Equivalencia, Valores Límite, Tabla de Decisión y Transición de Estados. |
| [PLAN DE PRUEBAS DE INTEGRACIÓN](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/PLAN-DE-PRUEBAS-DE-INTEGRACI%C3%93N) | Definición de puntos de integración (PI-001 al PI-008) y estrategia de implementación. Ejecución programada para el Hito 3\. |
| [EVIDENCIAS CI CD](https://github.com/PS-GLangosta/bookstack-testing-project/wiki/EVIDENCIAS-CI-CD) | Configuración del flujo de trabajo de GitHub Actions para validación automática de pruebas unitarias. |

## **Estructura del Repositorio**

bookstack-testing-project/  
├── .github/  
│   └── workflows/                       \# Archivos de configuración de GitHub Actions  
│       └── pages-build-deployment.yml   \# Activo: Despliegue de GitHub Pages  
│       └── unit-tests.yml               \# En configuración: Integración Continua para PHPUnit  
├── tests/  
│   ├── Unit/                            \# Pruebas unitarias desarrolladas por el equipo (Hito 2\)  
│   ├── Integration/                     \# Pruebas de integración (Planificadas para el Hito 3\)  
│   └── System/                          \# Pruebas de sistema (Planificadas para el Hito 3\)  
├── index.html                           \# Código fuente de GitHub Pages  
└── README.md                            \# Documento principal de información

## **Cronograma del Proyecto**

### **Hito 1 – Sprint 1 (Mayo 2026\) \- Estado: Finalizado**

* \[x\] Configuración de tablero de gestión (GitHub Projects)  
* \[x\] Despliegue de la presentación del producto (GitHub Pages)  
* \[x\] Elaboración del plan de pruebas unitarias (GitHub Wiki)  
* \[x\] Estructuración del repositorio de código y documentación

### **Hito 2 – Sprint 2 (Junio 2026\) \- Estado: En Fase de Cierre**

* \[x\] Actualización del Plan de Pruebas Unitarias  
* \[x\] Implementación de pruebas unitarias (Cobertura alcanzada: 91.44% en líneas)  
* \[x\] Diseño de casos de pruebas funcionales (30 casos de caja negra)  
* \[ \] Ejecución manual de casos funcionales en entorno de calidad (En curso)  
* \[x\] Elaboración del Plan de Pruebas de Integración  
* \[ \] Configuración de flujo automatizado CI/CD para pruebas unitarias (En curso)

### **Hito 3 – Sprints 3 y 4 \- Estado: Pendiente**

* \[ \] Ejecución de pruebas de integración (Casos IT-001 al IT-006)  
* \[ \] Elaboración del plan e informe de pruebas del sistema  
* \[ \] Automatización completa del despliegue (CI/CD) para la suite completa  
* \[ \] Consolidación de la documentación técnica del proceso

### **Defensa Final \- Estado: Pendiente**

* \[ \] Presentación del informe final de calidad  
* \[ \] Redacción del artículo de investigación en formato IEEE  
* \[ \] Demostración del uso integrado de las herramientas aplicadas

## **Entorno Tecnológico**

| Herramienta | Propósito en el Proyecto |
| :---- | :---- |
| **PHPUnit 11.5** | Ejecución de pruebas unitarias (Backend PHP/Laravel) |
| **MariaDB 11.4 (Docker)** | Gestión de base de datos para el entorno de pruebas |
| **Xdebug 3.5** | Análisis y medición de cobertura de código |
| **GitHub Actions** | Automatización de flujos de trabajo (CI/CD) |
| **GitHub Projects** | Gestión ágil de sprints y control de actividades |
| **GitHub Wiki** | Almacenamiento centralizado de documentación técnica |
| **GitHub Pages** | Alojamiento de la presentación pública del proyecto |
| **GitHub Issues** | Trazabilidad y seguimiento de defectos |

## **Métricas de Rendimiento \- Hito 2**

| Indicador | Resultado Obtenido | Objetivo | Estado Actual |
| :---- | :---- | :---- | :---- |
| Cobertura de líneas | 91.44% (12,270 / 13,419) | Superior al 85% | Objetivo superado |
| Cobertura de métodos | 81.56% (1,698 / 2,082) | N/A | Informativo |
| Pruebas ejecutadas | 1,802 (7,578 aserciones) | N/A | Informativo |
| Pruebas desarrolladas por el equipo | 45 | N/A | Informativo |
| Casos funcionales diseñados | 30 | Mínimo 30 | Objetivo alcanzado |
| Tiempo de ejecución general | 31 min 37 seg | Menor a 10 min | Requerimiento de optimización (Sprint 3\) |

## **Equipo de Trabajo**

| Integrante | Rol Asignado |
| :---- | :---- |
| Joe Daniel Flores Choquehuenca | Líder de Pruebas |
| Mauricio Eduardo Zegarra Puma | Arquitecto de Pruebas |
| Cielo Cristal Meza Vizcarra | Analista de Pruebas |
| Rommel Abraham Chambi Velasquez | Analista de Pruebas |
| Jeremy Joshua Perez Huamani | Analista de Pruebas |
| Ower Frank Lopez Arela | Diseñador de Pruebas |

*Universidad Nacional de San Agustín de Arequipa \- Facultad de Producción y Servicios \- Escuela Profesional de Ingeniería de Sistemas \- 2026*