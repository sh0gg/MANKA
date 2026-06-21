# MANKA — Especificación para desenvolvemento

Web app de xestión de incidencias de mantemento industrial.  
Desenvolvida por **Galicloud S. Coop. Galega**. Cliente piloto: **KiwiAtlántico S.A.**

---

## 1. Contexto e obxectivo

MANKA nace da experiencia de Galicloud dando soporte IT en KiwiAtlántico, empresa de transformación e comercialización de kiwi en Lois, Ribadumia (Pontevedra). Detectouse que as PEMEs industriais non teñen ferramentas axeitadas para xestionar o mantemento da súa maquinaria: os CMMS profesionais son demasiado caros e complexos, e as alternativas xenéricas (GLPI, Jira) están pensadas para incidencias de TI, non industriais.

MANKA é un produto SaaS propio de Galicloud, lixeiro e adaptable, pensado para equipos de mantemento de entre 3 e 15 operarios. KiwiAtlántico actúa como cliente piloto.

---

## 2. Stack técnico

| Compoñente | Tecnoloxía |
|---|---|
| Linguaxe backend | PHP 8.4 |
| Framework | Symfony 7.2 |
| Autenticación e roles | Symfony Security Component |
| ORM | Doctrine ORM 3.6 |
| Base de datos | PostgreSQL 16 |
| Templates | Twig 3.x |
| CSS | Tailwind CSS v3 (via SymfonyCasts bundle) |
| Probas | PHPUnit 13.0 |
| Control de versións | Git / GitLab (repositorio propio de Galicloud) |
| Servidor | Linux + PHP 8.4 + Nginx (VPS ou servidor interno do cliente) |

---

## 3. Arquitectura

Patrón **MVC** de Symfony:

- **Modelo**: cinco entidades Doctrine (ver sección 4)
- **Vista**: templates Twig con Tailwind CSS. Layout de dúas columnas: sidebar fixo en escritorio, menú hamburguesa en móbil
- **Controlador**: un controlador CRUD por entidade + un controlador de API REST + un controlador de autenticación

---

## 4. Modelo de datos

### Entidades

| Entidade | Campos | Relacións |
|---|---|---|
| `Issue` | id, startAt, endAt, type (preventivo/correctivo), status (open/closed) | ManyToOne → Device, ManyToOne → IssueCategory, ManyToMany → User (técnicos), ManyToOne → User (creador) |
| `Device` | id, name | OneToMany → Issue |
| `User` | id, email, password, roles, name, surname, active | ManyToMany → Issue (como técnico), OneToMany → Issue (como creador), OneToMany → Observation |
| `IssueCategory` | id, name | OneToMany → Issue |
| `Observation` | id, content, createdAt | ManyToOne → Issue, ManyToOne → User |

---

## 5. Sistema de roles e permisos

Xestionados co compoñente Security de Symfony (RBAC).

### `ROLE_USER` — Traballador de planta
- Pode abrir novas incidencias
- Pode consultar o estado das incidencias que el abriu
- Pode engadir observacións a calquera incidencia aberta
- Pode pechar as incidencias que el mesmo abriu
- **Non pode** editar campos técnicos (tipo, equipo, categoría, técnicos asignados)
- **Non pode** ver o historial completo de todas as incidencias
- **Non pode** eliminar rexistros
- **Non ten** acceso ao panel de administración

### `ROLE_TECHNICIAN` — Técnico de mantemento
- Herda todos os permisos de `ROLE_USER`
- Pode ver **todas** as incidencias (historial completo)
- Pode crear, editar e pechar calquera incidencia
- Pode asignarse a si mesmo e a outros técnicos a unha incidencia
- Pode eliminar incidencias (acción irreversible)
- Pode engadir observacións
- **Non ten** acceso ao panel de administración

### `ROLE_ADMIN` — Administrador
- Herda todos os permisos de `ROLE_TECHNICIAN`
- Acceso completo ao panel de administración:
  - Xestión de usuarios (crear, editar, activar/desactivar)
  - Xestión do catálogo de equipos
  - Xestión de categorías de incidencia
  - Xestión de API Tokens (xerar, revogar)
- Visibilidade total sobre todas as incidencias e o historial completo

**Nota:** Non existe rexistro público. Todos os usuarios créanse exclusivamente dende o panel de administración.

---

## 6. Ciclo de vida dunha incidencia

1. **Apertura**: calquera usuario autenticado (calquera rol) pode rexistrar unha nova incidencia indicando equipo, categoría e tipo. Créase en estado `open`.
2. **Seguimento**: calquera usuario pode engadir observacións (mensaxes cronolóxicos con autor e data) mentres a incidencia está aberta.
3. **Resolución**: os técnicos e administradores pechan a incidencia indicando a data de fin. Os traballadores só poden pechar as que eles mesmos abriron.
4. **Eliminación**: só `ROLE_TECHNICIAN` e `ROLE_ADMIN` poden eliminar incidencias. Acción irreversible.

---

## 7. API REST

Endpoints CRUD completos sobre a entidade `Issue`. Autenticación mediante **Bearer Token** na cabeceira HTTP:

```
Authorization: Bearer {token}
```

O token xérase e xestiónase dende o panel de administración. Ten caducidade configurable. Se expira, o administrador debe xerar un novo.

| Método | Endpoint | Descrición |
|---|---|---|
| GET | `/api/issues` | Listado de incidencias. Filtros: `?from=`, `?to=`, `?device=`, `?category=`, `?status=` |
| GET | `/api/issues/{id}` | Detalle dunha incidencia concreta |
| POST | `/api/issues` | Crear unha nova incidencia |
| PATCH | `/api/issues/{id}` | Actualizar campos dunha incidencia |
| DELETE | `/api/issues/{id}` | Eliminar unha incidencia |

**Non hai** endpoint `/api/issues/export` nin `/api/issues/stats`. A exportación a Excel realízase desde a interface web mediante PhpSpreadsheet, non como endpoint da API.

---

## 8. Seguridade

- Autenticación con login (email + contrasinal). Contrasinais cifradas con `bcrypt`.
- Control de acceso por roles (RBAC) en cada acción do controlador.
- Protección CSRF en todos os formularios web.
- Validación de datos en servidor con Symfony Validator.
- Prevención de inxección SQL a través de Doctrine ORM (consultas preparadas).
- Bearer Token con caducidade para a API REST.
- Despregue en servidor illado (sen exposición á rede pública por defecto).

### RGPD
- Datos almacenados exclusivamente no servidor do cliente, sen transferencia a terceiros.
- Desactivación lóxica de usuarios (campo `active`) para preservar trazabilidade sen eliminar datos.
- Relación Galicloud–cliente regulada polo artigo 28 do RGPD.

---

## 9. Interface e UX

### Deseño xeral
- Layout de dúas columnas: **sidebar fixo** (240px) + **área de contido** principal
- Tipografía: **DM Sans** (Google Fonts)
- Cor principal: `#1e3a5f` (azul Galicloud)
- Responsive: en móbil o sidebar oculta con botón hamburguesa

### Paleta de cores
```css
--brand: #1e3a5f;
--brand-light: #2e5fa3;
--brand-hover: #162d4a;
--accent: #e8f0f8;
```

### Sidebar
Contén dúas seccións de navegación:

**Principal** (todos os roles):
- Incidencias

**Administración** (só `ROLE_ADMIN`):
- Usuarios
- Equipos
- Categorías
- API Tokens

Footer do sidebar: avatar con iniciais + nome + rol do usuario autenticado.

### Pantallas principais

#### Login
- Pantalla dividida: lado esquerdo con logo e tagline sobre fondo `#1e3a5f`, lado dereito con formulario
- Campos: email e contrasinal
- Tagline: *"Sistema de xestión de incidencias de mantemento industrial"*
- Footer: *"Desenvolvido por Galicloud S. Coop. Galega"*

#### Listado de incidencias (`/issues`)
- Tarxetas de estatísticas no topo: Total incidencias, Abertas, Pechadas, Tempo medio de resolución
- Táboa con columnas: Estado, Inicio, Equipo, Categoría, Tipo, Técnicos, Accións
- Badge animado para incidencias abertas (punto pulsante en vermello)
- Botón "Nova incidencia" (só visible para `ROLE_TECHNICIAN` e `ROLE_ADMIN`)
- `ROLE_USER` só ve as súas propias incidencias

#### Detalle de incidencia (`/issues/{id}`)
- Layout dúas columnas: datos da incidencia + observacións
- Datos: equipo, categoría, tipo, data inicio, data fin, técnicos asignados, reportado por
- Observacións: lista cronolóxica con avatar, nome, rol e data de cada mensaxe; área de texto para engadir nova observación
- Botóns de acción segundo rol:
  - `ROLE_USER`: só "Pechar incidencia" (se é o creador)
  - `ROLE_TECHNICIAN`: "Editar" + "Pechar incidencia"
  - `ROLE_ADMIN`: "Editar" + "Eliminar" + "Pechar incidencia"

#### Nova / Editar incidencia (`/issues/new`, `/issues/{id}/edit`)
- Formulario con: equipo, categoría, tipo, data/hora de inicio
- Sección "Persoal técnico" con checkboxes de técnicos dispoñibles (oculta para `ROLE_USER`)
- Campo de observación inicial (opcional)

#### Panel de administración (só `ROLE_ADMIN`)

Cada sección ten a súa propia URL e entrada no sidebar:

**Usuarios** (`/admin/users`):
- Táboa: avatar con iniciais, nome, email, rol (badge de cor), estado activo/inactivo, último acceso, botón editar
- Botón "Novo usuario"

**Equipos** (`/admin/devices`):
- Táboa: nome, incidencias abertas, total incidencias, última incidencia, botón editar
- Botón "Novo equipo"

**Categorías** (`/admin/categories`):
- Táboa: nome, incidencias totais, última vez usada, botón editar
- Botón "Nova categoría"

**API Tokens** (`/admin/tokens`):
- Tarxetas por token: nome, estado (activo/inactivo), valor do token (truncado) con botón copiar, metadatos (creado, expira, último uso)
- Botón "Novo token"

---

## 10. Datos de demo (KiwiAtlántico)

### Usuarios
| Nome | Email | Rol |
|---|---|---|
| Sergi Orrantia | sorrantia@kiwiatlantico.com | Administrador |
| Martín Torres | mtorres@kiwiatlantico.com | Técnico |
| Iván Rodríguez | irodriguez@kiwiatlantico.com | Técnico |
| José Cancelo | jcancelo@kiwiatlantico.com | Técnico |
| Sergio Pardo | spardo@kiwiatlantico.com | Técnico |
| Ana López | alopez@kiwiatlantico.com | Traballadora |
| Carlos Pérez | cperez@kiwiatlantico.com | Traballador (inactivo) |

### Equipos
Arranque, Básculas L2, Calibradora, Cámara frixorífica 1, Cámara frixorífica 2, Carretilla 1, Carretilla 2, Carretilla 3, Colector L1-L5, Compresor C-02, Dosificador Cloro

### Categorías
Avería mecánica, Fallo eléctrico, Temperatura, Calibración, Limpeza, Outro

---

## 11. Modelo de negocio

MANKA comercialízase baixo **licenza anual por empresa**, con dúas vías:

1. **Vía Kit Digital / Kit Consulting**: Galicloud actúa como Axente Dixitalizador Adherido. O cliente accede a MANKA de forma subvencionada con fondos NextGenerationEU. Galicloud asegura ingresos recorrentes co contrato de mantemento posterior.
2. **Vía comercial directa**: licenza anual sen necesidade de programas de subvencións.

### Liñas de ingreso
| Concepto | Importe |
|---|---|
| Licenza anual | 300 € – 600 € / empresa |
| Implantación e configuración inicial | 200 € – 400 € (único) |
| Mantemento e soporte anual | 150 € – 300 € / empresa |
| Servidor de produción | 0 € – 20 € / mes (infraestrutura do cliente) |
| Licencias de software | 0 € (todo open source) |

Con 5 clientes a 400 €/ano de media → **2.000 € anuais recorrentes**, amortizando o investimento de desenvolvemento (2.525 €) no primeiro ano.

---

## 12. Estimación de custos de desenvolvemento

| Concepto | Horas | Custo (25 €/h) |
|---|---|---|
| Análise de requisitos e reunións co cliente | 8 h | 200 € |
| Deseño do modelo de datos e interfaces | 6 h | 150 € |
| Desenvolvemento do backend (entidades, controladores, formularios) | 40 h | 1.000 € |
| Desenvolvemento do frontend (Twig, Tailwind CSS) | 15 h | 375 € |
| Sistema de autenticación, roles e observacións | 12 h | 300 € |
| API REST e interface de consulta para xestión de calidade | 10 h | 250 € |
| Probas, corrección e documentación | 10 h | 250 € |
| **Total** | **101 h** | **2.525 €** |

---

## 13. Planificación de implantación no cliente

| Fase | Duración | Descrición |
|---|---|---|
| 1. Configuración do servidor | 1 día | PHP 8.4, PostgreSQL, Nginx, despregue dende GitLab |
| 2. Carga de datos inicial | 1–7 días | Equipos, categorías e usuarios. Script de migración ou manual |
| 3. Validación en paralelo | 1 semana | Uso simultáneo coa ferramenta anterior |
| 4. Formación | < 1 semana | Sesión presencial con operarios e técnicos |
| 5. Lanzamento oficial | — | MANKA convértese na ferramenta principal |
| 6. Traspaso a mantemento rutineiro | — | Integración no contrato de soporte IT de Galicloud |

### Recursos humanos necesarios
- David Besada (Galicloud): desenvolvedor e líder técnico
- Administrador de sistemas de Galicloud: despregue e configuración do servidor (Virtualmin)

---

## 14. Forma xurídica

Galicloud S. Coop. Galega, constituída ao amparo da **Lei 5/1998, do 18 de decembro, de Cooperativas de Galicia**. Cooperativa de traballo asociado con modelo de xestión horizontal.

Obrigacións fiscais: Imposto de Sociedades, IVE en facturas de servizos, retencións IRPF. Socios traballadores: cotización á Seguridade Social segundo normativa vixente. PRL: modalidade simplificada para empresas de menos de 10 traballadores (actividade remota, riscos ergonómicos e psicosociais).

---

## 15. Proposta de melloras futuras

1. Integración con GLPI via API REST (plugins ou tipos de obxecto personalizados para distinguir incidencias industriais dos tickets de TI)
2. Sistema de notificacións por email ao abrir/pechar incidencias (Symfony Mailer)
3. Sistema de adxuntos en incidencias (fotografía do equipo averiado dende móbil)
4. Aplicación móbil nativa que consuma a API REST de MANKA
5. Panel de estatísticas visual (gráficas por equipo, categoría e período) para técnicos e administradores

---

## 16. Notas para Claude Code

- O idioma da aplicación é o **galego**
- Usar `ROLE_USER`, `ROLE_TECHNICIAN`, `ROLE_ADMIN` como identificadores exactos dos roles en Symfony Security
- A entidade principal é `Issue` (non `Ticket`, non `Incidence`)
- A entidade de comentarios chámase `Observation` (non `Comment`)
- A entidade de equipos chámase `Device` (non `Machine`, non `Equipment`)
- Non implementar os endpoints `/api/issues/export` nin `/api/issues/stats`
- PhpSpreadsheet úsase só para exportación desde a interface web, non como endpoint da API
- Os usuarios **non se rexistran**: créanse exclusivamente dende o panel de administración
- O campo `active` en `User` permite desactivación lóxica sen eliminar datos históricos
- Symfony 7.2 (non 8.x, non existe esa versión)
