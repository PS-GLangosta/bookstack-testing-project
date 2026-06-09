# BookStack Testing Project

> **Asignatura:** Pruebas de Software · UNSA 2026  
> **Docente:** Ing. Robert Edison Arisaca Mamani  
> **Integrantes:**  
> - Chambi Velasquez Rommel Abraham  
> - Flores Choquehuanca Joe Daniel  
> - Lopez Arela Ower Frank  
> - Meza Vizcarra Cielo Cristal  
> - Perez Huamani Jeremy Joshua  
> - Zegarra Puma Mauricio Eduardo

Proyecto académico orientado al análisis, diseño, implementación y documentación de un proceso completo de pruebas de software sobre [**BookStack**](https://github.com/BookStackApp/BookStack), una plataforma de documentación de código abierto construida con PHP y Laravel.

---

## 🌐 Recursos del proyecto

| Recurso | Enlace |
|---------|--------|
| Presentación del producto | [GitHub Pages](../../pages/) |
| Tablero Kanban / Scrum | [GitHub Projects](../../projects/) |
| Plan de Pruebas Unitarias | [GitHub Wiki](../../wiki/Plan-de-Pruebas-Unitarias) |
| Issues y seguimiento | [GitHub Issues](../../issues/) |

---

## Estructura del repositorio (planificada)

```
bookstack-testing-project/
├── .github/
│   ├── workflows/         # GitHub Actions (CI/CD – Hito 2)
│   └── ISSUE_TEMPLATE/    # Plantillas de issues
├── docs/
│   ├── requisitos/        # Documento de Requisitos de Software
│   ├── arquitectura/      # Documento de Arquitectura de Software
│   ├── despliegue/        # Documento de Despliegue (localhost)
│   └── plan-pruebas/      # Documento de Plan de Pruebas
├── tests/
│   ├── Unit/              # Pruebas unitarias (Sprint 1–2)
│   ├── Integration/       # Pruebas de integración (Sprint 2)
│   └── System/            # Pruebas de sistema (Sprint 3–4)
└── README.md
```

---

## Hitos del proyecto

### Hito 1 – Sprint 1 (28–29 Mayo 2026)
- [x] Tablero Kanban/Scrum → GitHub Projects
- [x] Presentación del producto → GitHub Pages
- [x] Plan de pruebas unitarias → GitHub Wiki
- [x] Repositorio de código y documentación

### Hito 2 – Sprint 2
- [ ] Informe de pruebas unitarias
- [ ] Plan e informe de pruebas de integración con CI/CD
- [ ] Implementación completa del proceso de pruebas
- [ ] GitHub Actions, Issues, Projects y Wiki actualizados

### Hito 3 – Sprints 3–4
- [ ] Plan e informe de pruebas de sistema y aceptación
- [ ] Despliegue CI/CD automatizado
- [ ] Documentación técnica completa del proceso

### Defensa Final
- [ ] Presentación del informe final
- [ ] Artículo en formato IEEE
- [ ] Uso integrado de todas las herramientas propuestas

---

## Stack de pruebas (preliminar)

| Herramienta | Uso |
|-------------|-----|
| **PHPUnit** | Pruebas unitarias (backend PHP/Laravel) |
| **Mockery** | Mocking de dependencias |
| **Xdebug / PCOV** | Cobertura de código |
| **GitHub Actions** | CI/CD automático (Hito 2) |
| **GitHub Projects** | Gestión del sprint y tablero Kanban |
| **GitHub Wiki** | Documentación técnica de pruebas |
| **GitHub Pages** | Presentación del producto |
| **GitHub Issues** | Seguimiento de defectos y tareas |

---

## Equipo

| Nombre | Rol |
|--------|-----|
| Joe Daniel Flores Choquehuenca | Test Leader |
| Mauricio Eduardo Zegarra Puma | Test Architect |
| Cielo Cristal Meza Vizcarra | Test Analyst |
| Rommel Abraham Chambi Velasquez | Test Analyst |
| Jeremy Joshua Perez Huamani | Test Analyst |
| Ower Frank Lopez Arela | Test Designer |

---

## Documentación técnica

Los documentos de referencia del proyecto se encuentran en la carpeta [`docs/`](./docs/):
- Documento de Requisitos de Software
- Documento de Arquitectura de Software 
- Documento de Despliegue (localhost)
- Documento de Plan de Pruebas

---

*Universidad Nacional de San Agustín de Arequipa · Facultad de Producción y Servicios · Escuela Profesional de Ingeniería de Sistemas · 2026*
