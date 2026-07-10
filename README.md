# AlquiGest v3.0.1

Aplicación web de gestión de alquileres para administradores de fincas y propietarios particulares. Funciona en local con MAMP o XAMPP sobre Windows; no requiere conexión a internet para ninguna función principal.

---

## Tabla de contenidos

1. [Descripción del proyecto](#1-descripción-del-proyecto)
2. [Arquitectura](#2-arquitectura)
3. [Instalación](#3-instalación)
4. [Configuración inicial](#4-configuración-inicial)
5. [Estructura del proyecto](#5-estructura-del-proyecto)
6. [Funcionalidades](#6-funcionalidades)
7. [Generación de contratos con plantillas Word](#7-generación-de-contratos-con-plantillas-word)
8. [Variables de plantilla disponibles](#8-variables-de-plantilla-disponibles)
9. [Fotografías en contratos (FotosContrato)](#9-fotografías-en-contratos-fotoscontrato)
10. [Propietarios y fincas](#10-propietarios-y-fincas)
11. [Inmuebles](#11-inmuebles)
12. [Inquilinos](#12-inquilinos)
13. [Contratos](#13-contratos)
14. [Recibos y cobros](#14-recibos-y-cobros)
15. [Facturas legales](#15-facturas-legales)
16. [Revisiones de renta](#16-revisiones-de-renta)
17. [Informes y exportación Excel](#17-informes-y-exportación-excel)
18. [VERI*FACTU — Facturación electrónica AEAT](#18-verifactu--facturación-electrónica-aeat)
19. [Parámetros de configuración](#19-parámetros-de-configuración)
20. [Preguntas frecuentes](#20-preguntas-frecuentes)
21. [Limitaciones](#21-limitaciones)
22. [Futuras mejoras propuestas](#22-futuras-mejoras-propuestas)
23. [Autenticación, usuarios y permisos](#23-autenticación-usuarios-y-permisos)
24. [Corrección de generación individual de recibos de períodos anteriores](#corrección-de-generación-individual-de-recibos-de-períodos-anteriores)
25. [Auditoría de julio 2026 — hallazgos y estado](#25-auditoría-de-julio-2026--hallazgos-y-estado)
26. [Registro de cambios](#26-registro-de-cambios)

---

## 1. Descripción del proyecto

AlquiGest es un sistema de gestión de alquileres diseñado para administradores de fincas y propietarios que gestionan varios inmuebles. Permite llevar el control completo del ciclo de vida de un alquiler: desde el alta del propietario hasta el cobro mensual de los recibos y la emisión de facturas con cumplimiento legal.

**Características principales:**
- Gestión multi-propietario y multi-finca desde una única aplicación
- Generación automática de recibos mensuales individuales y masivos
- Motor de plantillas Word con más de 65 variables dinámicas (incl. bloques repetitivos de inquilinos secundarios)
- Inserción de fotografías en documentos Word (tablas OOXML embebidas)
- Facturas legales conforme al RD 1619/2012
- Integración opcional con VERI*FACTU / AEAT (RD 1007/2023)
- Informes Excel para gestión e IRPF (6 tipos + 3 informes fiscales)
- Envío de recibos y facturas por email (Gmail) y WhatsApp
- Dashboard con KPIs, gráficos, alertas IPC y morosidad
- Funciona 100% en local, sin dependencias de servicios externos

---

## 2. Arquitectura

### Stack técnico

| Capa | Tecnología |
|------|-----------|
| Frontend | HTML5, CSS3, JavaScript vanilla (ES2015+) |
| Backend | PHP 7.4 (compatible con MAMP/XAMPP Windows) |
| Base de datos | MySQL 5.7+ / MariaDB |
| Servidor | Apache (MAMP/XAMPP) |
| Generación DOCX | PHP ZipArchive + manipulación OOXML directa |
| PDF | html2canvas + jsPDF (en cliente, sin backend) |
| Gráficos | Chart.js (local, sin CDN) |
| Excel | ZipArchive + OpenXML (.xlsx, generado en PHP) |

### Patrón arquitectónico

```
login.php                ← Login (sin sesión previa) → AlquiGest.php
assets/php/auth.php      ← Núcleo de autenticación/sesión, incluido por todos los backends
  ↓
AlquiGest.php            ← Shell SPA (Single Page Application); exige sesión (auth.php)
  ↓ incluye
assets/js/*.js           ← Módulos JS por sección (propietarios, contratos, recibos, usuarios…)
  ↓ llama a
assets/php/api.php       ← API PHP unificada (JSON) para datos de negocio; exige sesión
assets/php/plantillas.php ← Motor de plantillas DOCX (multipart/form-data); exige sesión
assets/php/verifactu.php ← Integración AEAT VERI*FACTU; exige sesión
```

### Base de datos — Tablas principales

| Tabla | Descripción |
|-------|-------------|
| `propietarios` | Propietarios de fincas |
| `fincas` | Edificios / conjuntos de inmuebles |
| `inmuebles` | Inmuebles individuales (pisos, locales, garajes) |
| `inquilinos` | Inquilinos / arrendatarios |
| `contratos` | Contratos de arrendamiento |
| `contratos_inq_sec` | Inquilinos secundarios por contrato |
| `historial_rentas` | Histórico de revisiones de renta (IPC/IRAV/fija) por contrato |
| `recibos` | Recibos mensuales de alquiler. Los cobros se guardan como JSON en la columna `pagos` (sin tabla `cobros` separada) |
| `facturas` | Facturas legales emitidas, incluidas las rectificativas (serie `RET`). Los datos VERI*FACTU (hash, QR, estado AEAT) son columnas de esta misma tabla, no una tabla aparte |
| `empresa` | Datos de la empresa/administrador (registro único) |
| `configuracion` | Parámetros de configuración clave-valor |
| `plantillas` | Plantillas DOCX (motor de generación de documentos) |
| `doc_secuencias` | Contador atómico de numeración anual por tipo de documento (`REC`, `FAC`, `RET`, `RER`) |
| `log_actividad` | Log de auditoría (activable/desactivable en `config.php`), con usuario/IP de cada acción (ver §23) |
| `usuarios` | Cuentas de acceso a la aplicación: roles `admin`/`user`, contraseña con `password_hash()`, borrado lógico (ver §23) |

### Seguridad

- Toda la API exige `requireLocalhost()` — rechaza peticiones externas a `127.0.0.1`/`::1`
- **Autenticación de usuario real** (desde v3.0.1): login con sesión PHP segura, roles `admin`/`user`, CSRF, y `install.php` protegido según el rol (ver [§23](#23-autenticación-usuarios-y-permisos))
- Uploads validados: extensión + MIME ZIP real (DOCX), UUID en disco, anti-path-traversal
- Certificados VERI*FACTU en `certs/` protegidos con `.htaccess`

---

## 3. Instalación

### Requisitos previos

- **MAMP** (Windows/macOS) o **XAMPP** (Windows/Linux)
- Apache y MySQL activos
- PHP 7.4+ con extensiones: `pdo_mysql`, `zip`, `gd`, `openssl`, `mbstring`
- Navegador moderno (Chrome, Firefox, Edge, Safari)

### Pasos

1. Copia la carpeta `AlquiGest_v3/` en el directorio web:
   - MAMP: `C:\MAMP\htdocs\AlquiGest_v3\`
   - XAMPP: `C:\xampp\htdocs\AlquiGest_v3\`

2. Inicia Apache y MySQL desde el panel de MAMP/XAMPP.

3. Abre en el navegador: `http://localhost/AlquiGest_v3/assets/php/install.php`

4. Elige una opción:
   - **Instalación limpia** — BD vacía lista para producción
   - **Instalación con datos de ejemplo** — carga datos de prueba

5. Crea el primer administrador cuando `install.php` te lo pida (nombre, usuario, email, contraseña) — ver [§23](#23-autenticación-usuarios-y-permisos).

6. Accede a la aplicación: `http://localhost/AlquiGest_v3/AlquiGest.php` e inicia sesión con el administrador creado.

> `install.php` puede ejecutarse de nuevo para aplicar migraciones (nuevas columnas) sin destruir datos existentes. Solo destruye datos si eliges "instalación limpia" o "con datos de ejemplo" (ambas opciones recrean las tablas de negocio desde cero; las tablas `usuarios` y `log_actividad` nunca se destruyen en una reinstalación). Tras la primera instalación, `install.php` exige haber iniciado sesión como administrador para volver a acceder a él (salvo la sección de copia de seguridad, disponible también para el rol `user`).

### Qué incluye cada modo

Ambos modos siembran siempre lo **obligatorio para que la aplicación funcione** desde el primer arranque, sin necesitar configuración manual:
- Las 6 plantillas DOCX de contrato/inventario (normal y multi-inquilino) en la tabla `plantillas`.
- Los 76 parámetros de `configuracion` con sus valores por defecto (paginación, visibilidad de botones y menús, Dashboard, WhatsApp, VERI*FACTU, documentos).
- La estructura completa de tablas, incluida `doc_secuencias` para la numeración atómica.

La **instalación limpia** no crea ningún propietario, finca, inmueble, inquilino, contrato, recibo ni factura — la BD queda vacía y lista para datos reales.

La **instalación con datos de ejemplo** añade además un escenario completo de prueba: propietarios/fincas/inmuebles/inquilinos/contratos/recibos de varios meses (incluido un salto de año en la numeración), facturas normales y rectificativas (`RET`), recibos anulados con y sin factura (incluido un `RER`), un contrato finalizado, **4 contratos con los distintos casos de revisión de renta** (pendiente / próxima / ya aplicada / lejana — ver sección 16) y **3 contratos con inquilinos secundarios** (0, 1 y 2 secundarios, para poder comparar).

**Repetibilidad verificada:** ambos modos pueden ejecutarse tantas veces como haga falta. Cada ejecución hace `DROP TABLE` + `CREATE TABLE` de las tablas de negocio y vuelve a sembrar los mismos datos de ejemplo desde cero — no depende de si había datos previos, no dejan residuos ni duplicados, y las secuencias de numeración (`doc_secuencias`) se reinician limpias en cada pasada. Verificado el 2026-07-10 con dos reinstalaciones consecutivas: mismo recuento de filas (37 recibos, 16 contratos) y 0 `periodo_key`/`numero_recibo` duplicados en ambas.

---

## 4. Configuración inicial

Flujo recomendado en el primer uso:

1. **Iniciar sesión** con el administrador creado durante la instalación (ver [§23](#23-autenticación-usuarios-y-permisos)).
2. **Usuarios** (solo admin) → Crea cuentas adicionales con rol `user` si otras personas van a usar la aplicación sin necesitar acceso a `install.php`.
3. **Mi Empresa** → Rellena nombre, CIF, dirección, teléfono, email e IBAN.
4. **Propietarios** → Crea al menos un propietario.
5. **Fincas / Edificios** → Asigna la finca al propietario.
6. **Pisos / Locales** → Añade los inmuebles de cada finca.
7. **Inquilinos** → Registra los inquilinos con email.
8. **Contratos** → Vincula inquilino + inmueble + condiciones económicas.
9. **Generar Recibos** → Genera los recibos del mes en curso.

---

## 5. Estructura del proyecto

```
AlquiGest_v3/
├── AlquiGest.php           ← Punto de entrada principal (SPA shell, exige sesión)
├── login.php               ← Pantalla de login (restringida a localhost)
├── index.php                ← Pantalla de bienvenida / instalación
├── README.md               ← Este documento (única fuente de documentación propia del proyecto)
│
├── assets/
│   ├── css/
│   │   └── main.css        ← Hoja de estilos principal (modo claro + oscuro)
│   ├── js/
│   │   ├── config.js       ← Constantes de estado + objeto DB (fetch/caché de api.php)
│   │   ├── helpers.js      ← Utilidades globales, navegación SPA, período canónico (periodoLabel/periodoYYYYMM), nextNumeroDoc
│   │   ├── init.js         ← Arranque de la SPA (carga DB.init(), primera navegación)
│   │   ├── empresa.js      ← Módulo Mi Empresa
│   │   ├── dashboard.js    ← Dashboard y widgets
│   │   ├── propietarios.js ← Módulo propietarios
│   │   ├── fincas.js       ← Módulo fincas
│   │   ├── inmuebles.js    ← Módulo inmuebles
│   │   ├── inquilinos.js   ← Módulo inquilinos
│   │   ├── contratos.js    ← Módulo contratos (incl. fiador, inq. sec.)
│   │   ├── contratos-pdf.js← PDF de contratos y fianza
│   │   ├── recibos-lista.js← Listado y filtros de recibos
│   │   ├── recibos-cobro.js← Generación individual, cobro, anulación (RER) — módulo activo, ver §14
│   │   ├── recibos-pdf.js  ← PDF e impresión de recibos
│   │   ├── recibos.js      ← ⚠️ CÓDIGO MUERTO: versión anterior, ya no se carga desde AlquiGest.php (sustituida por recibos-lista/cobro/pdf.js). Se conserva en el repo pero no se ejecuta nunca; no editar pensando que es el módulo activo.
│   │   ├── generar.js      ← Generación masiva de recibos por mes/ámbito
│   │   ├── facturas.js     ← Módulo facturas (incl. RET, VERI*FACTU fire-and-forget)
│   │   ├── informes.js     ← Exportación Excel e informes IRPF
│   │   ├── email.js        ← Envío por correo (Gmail)
│   │   ├── configuracion.js← Parámetros y configuración
│   │   ├── plantillas.js   ← Motor de plantillas DOCX (UI + lógica)
│   │   ├── verifactu.js    ← VERI*FACTU (UI)
│   │   ├── actividad.js    ← Log de actividad (con columna/filtro de usuario)
│   │   ├── usuarios.js     ← Gestión de usuarios (solo admin)
│   │   ├── ux.js           ← Modales, toasts, temas, búsqueda global, menú de usuario/sesión
│   │   ├── extras.js       ← Funciones auxiliares (alertas IPC/IRAV, etc.)
│   │   ├── tabla.js        ← Componente de tabla reutilizable
│   │   ├── notificaciones.js← Campana de notificaciones
│   │   └── vendor/         ← Librerías locales (sin CDN)
│   │       ├── chart.umd.min.js
│   │       ├── html2canvas.min.js
│   │       └── jspdf.umd.min.js
│   ├── php/
│   │   ├── api.php         ← API REST principal (JSON); exige sesión y POST salvo login/check/ine_rate
│   │   ├── auth.php        ← Autenticación, sesión, CSRF, roles, log de actividad
│   │   ├── config.php      ← Configuración de conexión BD y claves (host/user/pass/encrypt_key)
│   │   ├── helpers.php     ← requireLocalhost(), CORS, respuestas JSON, cifrado AES
│   │   ├── install.php     ← Instalador y migrador de BD; acceso según rol (ver §23)
│   │   ├── plantillas.php  ← Motor DOCX backend (ZipArchive + OOXML)
│   │   ├── verifactu.php   ← Backend VERI*FACTU (SHA-256, SOAP, AEAT)
│   │   ├── email.php       ← Envío SMTP real (Gmail) por sockets PHP
│   │   ├── export.php      ← Generación de informes Excel (.xlsx)
│   │   ├── import.php      ← Importación CSV/Excel
│   │   └── pdf_download.php← Descarga de PDF generados en cliente
│   ├── docs/
│   │   ├── ayuda.php           ← Manual de usuario completo (versión dinámica desde config.php)
│   │   ├── ayuda_verifactu.php ← Guía técnica VERI*FACTU/AEAT (versión dinámica desde config.php)
│   │   ├── fixexcel.html       ← Solución error Excel en XAMPP
│   │   └── cors.html           ← Guía seguridad y CORS
│   └── img/                ← Capturas de pantalla para documentación
│       ├── dashboard/
│       ├── propietarios/
│       ├── fincas/
│       ├── inmuebles/
│       ├── inquilinos/
│       ├── contratos/
│       ├── recibos/
│       ├── facturas/
│       ├── generar_recibos/
│       ├── informes/
│       ├── configuracion/
│       └── plantillas/
│
└── uploads/
    └── plantillas/         ← Archivos DOCX subidos (UUID en disco)
```

Los certificados VERI*FACTU (`.p12`/`.pfx`) se guardan en `assets/php/certs/` (creada de forma perezosa por `verifactu.php` al subir el primer certificado, no existe hasta entonces), no en una carpeta `certs/` de primer nivel.

---

## 6. Funcionalidades

| Módulo | Descripción |
|--------|-------------|
| Dashboard | KPIs, gráficos Chart.js, alertas IPC, próximas renovaciones, previsión de cobros, morosidad |
| Propietarios | Alta, edición, eliminación, informe IRPF por propietario |
| Fincas | Alta, edición, eliminación; agrupan inmuebles por dirección |
| Inmuebles | Alta, edición; estado automático ocupado/libre |
| Inquilinos | Alta, edición, historial completo de contratos, recibos y facturación |
| Contratos | Alta, edición, baja, renovación; campos: renta, IVA, IRPF, fianza, revisión, motivo temporada, fiador solidario, inquilinos secundarios |
| Recibos | Listado filtrado (estado/propietario/inquilino/fecha), cobro parcial/total, anulación, PDF, email, WhatsApp, generación de factura |
| Facturas | Emisión, PDF A4/A5, email, anulación lógica, integración VERI*FACTU |
| Generar Recibos | Generación masiva por mes + ámbito (cartera/finca/piso), protección anti-duplicados |
| Informes Excel | 6 informes de gestión + 3 informes fiscales IRPF (Modelo 100, 115, 180) |
| Calendario Cobros | Vista mensual de recibos por día (pendiente/cobrado/parcial) |
| Morosidad | Recibos vencidos > 30 días, exportación PDF formal |
| Actividad | Log de auditoría de todas las acciones del sistema, con usuario/IP (ver §23) |
| Mi Empresa | Datos del administrador, SMTP Gmail, IBAN, plantillas de correo |
| Parámetros | 7 pestañas: Dashboard, Paginación, Botones, WhatsApp, VERI*FACTU, Documentos, Menú |
| VERI*FACTU | Facturación electrónica AEAT (opcional, desactivado por defecto) |
| Plantillas DOCX | Motor de plantillas Word: más de 65 variables, bloques repetitivos, tabla de fotos |
| Usuarios | Alta, edición y baja de cuentas de acceso; roles `admin`/`user` (solo visible para administradores, ver §23) |

**Modo oscuro:** alternable desde el icono de la cabecera; persiste en `localStorage`. Los gráficos del Dashboard (Chart.js) adaptan sus colores automáticamente al tema activo.

**Atajos de teclado:** `Alt+D` Dashboard · `Alt+R` Recibos · `Alt+C` Contratos · `Alt+F` Facturas · `Alt+I` Inquilinos · `Alt+G` Generar recibos.

---

## 7. Generación de contratos con plantillas Word

El motor de plantillas genera archivos .docx reales con los datos del contrato sustituidos en el lugar correcto.

### Flujo de uso

1. Crea la plantilla Word con las variables en dobles llaves: `{{NombreInquilino}}`.
2. Súbela desde **Plantillas → Subir plantilla** y asígnale un tipo de documento.
3. Desde **Contratos**, pulsa el botón **DOCX** en la fila del contrato.
4. Si hay una sola plantilla activa del tipo correcto, se usa directamente sin selector.
5. Si hay varias, aparece un selector; si una está marcada como "por defecto", se usa automáticamente.
6. Si la plantilla contiene `{{FotosContrato}}`, se abre el diálogo de fotografías.
7. Se descarga el .docx generado.

### Tipos de documento

`contrato_arrendamiento` · `fianza` · `renovacion` · `comunicacion` · `otro`

### Vista previa

El botón **Vista previa** en la sección Plantillas muestra el documento con todas las variables sustituidas (variables no resueltas se muestran en rojo para ayudar a detectar errores).

---

## 8. Variables de plantilla disponibles

Escribe las variables exactamente con su capitalización: `{{NombreInquilino}}` (no `{{nombreinquilino}}`).

**Empresa:** `{{NombreEmpresa}}` · `{{CIFEmpresa}}` · `{{DireccionEmpresa}}` · `{{TelefonoEmpresa}}` · `{{EmailEmpresa}}` · `{{IBANEmpresa}}`

**Propietario:** `{{NombrePropietario}}` · `{{NIFPropietario}}` · `{{DireccionPropietario}}`

**Inquilino principal:** `{{NombreInquilino}}` · `{{NIFInquilino}}` · `{{TelefonoInquilino}}` · `{{EmailInquilino}}` · `{{DireccionInquilino}}`

**Inmueble:** `{{DireccionInmueble}}` · `{{RefCatastral}}` · `{{TipoInmueble}}` · `{{MunicipioInmueble}}` · `{{ProvinciaInmueble}}`

**Contrato:** `{{FechaInicio}}` · `{{FechaFin}}` · `{{Duracion}}` · `{{MotivoTemporada}}` · `{{MetodoRevision}}` · `{{DiaPago}}`

**Facturación:** `{{Renta}}` · `{{RentaLetras}}` · `{{IVA}}` · `{{IRPF}}`

**Fianza:** `{{Fianza}}` · `{{FianzaLetras}}`

**Fiador solidario:** `{{NombreFiador}}` · `{{NIFFiador}}` · `{{DireccionFiador}}`

**Fotos:** `{{FotosContrato}}`

**Sistema:** `{{FechaActual}}` · `{{FechaHoy}}` · `{{AnioActual}}` · `{{MesActual}}`

**Extras:** `{{IBANInquilino}}` · `{{ListaMuebles}}`

> El catálogo completo tiene más de 65 entradas (confirmado contra `catalogoVariables()` en `assets/php/plantillas.php`); esta sección documenta los grupos principales. El botón **Vista previa** de Plantillas muestra siempre el catálogo íntegro y actualizado.

### Bloque de inquilinos secundarios

```
{{#INQUILINOS_SECUNDARIOS}}
{{InqNombre}}, con NIF {{InqNIF}}, domiciliado en {{InqDireccion}}.
{{/INQUILINOS_SECUNDARIOS}}
```

Variables dentro del bloque: `{{InqNombre}}` · `{{InqNIF}}` · `{{InqDireccion}}` · `{{InqTelefono}}` · `{{InqEmail}}`

Si el contrato no tiene inquilinos secundarios, el bloque completo desaparece del documento.

También existen variables posicionales fuera del bloque, para plantillas que necesitan citar a un inquilino secundario concreto sin repetir bloque: `{{Inquilinos_Secundarios_Nombre_1}}`, `{{Inquilinos_Secundarios_NIF_1}}`, `{{Inquilinos_Secundarios_Direccion_1}}`, `{{Inquilinos_Secundarios_Telefono_1}}`, `{{Inquilinos_Secundarios_Email_1}}` (y `_2`, `_3`... según el número de secundarios del contrato).

### Bloque multi-inquilino (firmantes en pie de página/firma)

```
{{InicioMultiinquilino}}
{{NombreInquilinomultiple}}, NIF {{NIFInquilinomultiple}}, {{DireccionInquilinomultiple}}
{{/InicioMultiinquilino}}
```

Pensado para bloques de firma con varios inquilinos, como alternativa al bloque `{{#INQUILINOS_SECUNDARIOS}}` cuando la plantilla necesita un formato de repetición distinto.

---

## 9. Fotografías en contratos (FotosContrato)

Permite incrustar una galería de fotos directamente en el DOCX como tabla OOXML nativa de Word.

### Uso

1. En la plantilla Word, escribe `{{FotosContrato}}` solo en su párrafo (sin otro texto).
2. Al pulsar DOCX en un contrato, se detecta automáticamente la variable.
3. Aparece el diálogo: sube imágenes, elige columnas (1/2/3) y ordénalas.
4. El DOCX incluye las fotos embebidas con proporciones preservadas.

### Detalles técnicos

- Formatos admitidos: JPG, JPEG, PNG (nativos); WebP (convertido a JPEG vía PHP GD)
- Las imágenes se almacenan en `word/media/foto_N.ext` dentro del DOCX (ZIP)
- Ancho por columna en A4 con márgenes 2,5 cm: 1 col = 22,5 cm · 2 col = 9,8 cm · 3 col = 6,4 cm
- Unidades internas: EMUs. Fórmula: `heightEmu = widthEmu × (imgH / imgW)` (aspecto exacto)

---

## 10. Propietarios y fincas

Jerarquía de datos:

```
Mi Empresa (1)
  └── Propietario (N)
        └── Finca / Edificio (N)
              └── Inmueble / Piso (N)
                    └── Contrato activo (0-1)
                          └── Inquilino principal + Inquilinos secundarios
```

No hay relación directa propietario-inquilino; siempre pasan a través del contrato. El informe IRPF por propietario resume todos sus ingresos de alquiler para la declaración anual.

---

## 11. Inmuebles

Tipos: Vivienda, Local comercial, Garaje, Trastero, Oficina, Nave industrial, Otro.

La tabla muestra automáticamente el estado: **Ocupado** (nombre del inquilino) o **Libre**. Solo puede haber un contrato activo por inmueble.

---

## 12. Inquilinos

El botón **Pagos** (acción principal de la fila) lleva directamente a los recibos del contrato activo del inquilino.

El botón **Historial** (dentro del menú **Más ▾**, junto con Editar y Eliminar) abre tres pestañas: contratos, recibos y resumen financiero (total facturado, cobrado, pendiente).

---

## 13. Contratos

### Campos del formulario

| Campo | Descripción |
|-------|-------------|
| Inquilino | Inquilino principal firmante |
| Inmueble | Solo se listan los que están libres |
| Fecha inicio / fin | Período del contrato |
| Renta base | Importe mensual sin impuestos |
| IVA / IRPF | Porcentajes (0% IVA para vivienda; 19% IRPF si aplica) |
| Fianza | Importe depositado |
| Día de pago | Habitualmente 1 o 5 del mes |
| Revisión anual | IPC, % fijo, IPC con límite, IRAV… |
| Motivo de temporada | Obligatorio para contratos < 1 año (`{{MotivoTemporada}}`) |
| Fiador solidario | Nombre, NIF, dirección (variables `{{NombreFiador}}` etc.) |
| Inquilinos secundarios | Tabla editable dentro del modal (bloque `{{#INQUILINOS_SECUNDARIOS}}`) |

### Acciones por contrato en la tabla

Desde v3.0.0 cada fila muestra una única acción principal — **Generar recibo** (contratos activos) o **Ver PDF** (finalizados/rescindidos) — más el aviso **⚠ IPC/IRAV** cuando procede (se mantiene siempre visible fuera del menú, por ser una alerta temporal y no una acción rutinaria). El resto de acciones vive en el menú **Más ▾**, agrupadas en:
- **Contrato:** Renovar · Historial de rentas · Dar de baja (solo contratos activos)
- **Documentos:** PDF del contrato · Justificante de fianza (si hay fianza) · Contrato en DOCX
- **Gestión:** Editar

Los contratos no se pueden eliminar físicamente: es un documento con trazabilidad legal/contractual (recibos, facturas, histórico de rentas). La única forma de cerrarlo es **Dar de baja**, que conserva el registro y todo su histórico. El backend rechaza cualquier intento de borrado físico de un contrato aunque se llame directamente a la API (`assets/php/api.php`, acción `delete`).

---

## 14. Recibos y cobros

### Período del recibo (mes/año) — fuente única

Tanto la generación **individual** (botón "Generar recibo" desde Contratos, o "Nuevo recibo" desde Recibos) como la generación **en lote** (Generar Recibos) usan un único selector de **Mes/Año** como fuente de verdad del período. Seleccionar un mes recalcula automáticamente y de forma consistente:
- `periodo_desde` / `periodo_hasta` (primer y último día del mes elegido),
- `concepto_periodo` (etiqueta legible, ej. `"Junio 2026"`, generada siempre por la misma función `periodoLabel()` en ambos flujos),
- `fecha_limite` (día de pago del contrato dentro de ese mes),
- el año usado para la numeración anual (`String(año)`, ver más abajo).

`fecha_emision` es un campo independiente (por defecto hoy) que representa cuándo se emite/imprime el documento — puede ser distinta del período que cubre el recibo (un recibo de junio puede emitirse en julio). **La numeración usa siempre el año del período elegido, nunca `fecha_emision` ni la fecha actual del servidor.**

### Numeración

`{PREFIJO}-{AAAA}-{NNNNN}` — Ejemplo: `REC-2026-00001`. El prefijo se configura en Mi Empresa. El secuencial es **anual**: continúa durante todos los meses del año y solo se reinicia a `00001` al empezar un año nuevo (desde julio de 2026 — antes era mensual, ver [changelog](#corrección-de-generación-individual-de-recibos-de-períodos-anteriores)). La reserva del número es atómica: la genera el servidor (`nextNumeroDoc`, tabla `doc_secuencias`, `SELECT ... FOR UPDATE` dentro de una transacción InnoDB), nunca se calcula solo en el navegador. El parámetro `periodo` (`AAAA`) es obligatorio en la petición; si falta o no tiene formato de año válido, el servidor responde `400` — nunca sustituye el período por la fecha actual.

### Control de duplicados

Un recibo se considera duplicado cuando coincide **contrato + año + mes** (columna `periodo_key`, calculada siempre en el servidor como `"<contrato_id>-<AAAAMM del periodo_desde>"`), y aplica solo a recibos **ordinarios** (no a los rectificativos `RER`, que representan la anulación de otro recibo, no un período propio). Un recibo **anulado** libera su período: anular y volver a emitir para el mismo mes está permitido. La protección es una `UNIQUE KEY` real en MySQL (no solo una comprobación previa en PHP/JS), por lo que dos peticiones simultáneas para el mismo contrato+mes (doble clic, doble pestaña, o dos llamadas directas al endpoint) nunca pueden crear dos recibos ordinarios: la segunda recibe `409 RECIBO_DUPLICADO`.

### Estados

| Estado | Descripción |
|--------|-------------|
| Pendiente | Emitido, sin cobro registrado |
| Parcial | Con uno o más cobros parciales |
| Cobrado | Pagado en su totalidad |
| Anulado | Cancelado lógicamente; el registro original se conserva íntegro para auditoría |
| Rectificativo | Documento de corrección generado automáticamente al anular un recibo sin factura (ver más abajo) |

### Acciones por recibo en la tabla

Desde v3.0.0 cada fila muestra una única acción principal según el estado, más un menú **Más ▾** agrupado en Comunicación (Email, WhatsApp) / Documentos (PDF, Factura) / Gestión (Ver detalle, Editar, Anular):

| Estado | Acción principal | Menú "Más" |
|--------|-------------------|------------|
| Pendiente | Cobrar | Email · WhatsApp · PDF · Generar factura · Ver detalle · Editar · Anular |
| Parcial | Completar cobro | (igual que Pendiente) |
| Cobrado | Ver cobro | (igual que Pendiente, con "Ver/Generar factura" según corresponda) |
| Anulado | Ver | Solo PDF (documento cerrado: sin Email/WhatsApp/Editar/Anular/Factura) |
| Rectificativo | Ver | Solo PDF (documento interno cerrado, sin efecto fiscal — ver sección 15) |

El botón "Ver" (Anulado/Rectificativo) y "Ver detalle" (resto de estados) abren un panel lateral con el resumen del recibo, historial de cobros y documentos asociados. La tabla admite selección múltiple (casilla por fila) con una barra de acciones masivas para descargar el PDF de varios recibos a la vez.

### Anulación de un recibo

La anulación de un recibo es siempre **lógica**: nunca se borra físicamente, el registro original permanece en la base de datos con estado `anulado` y una nota indicando qué documento lo rectifica (si aplica). El comportamiento exacto depende de si el recibo tiene cobros registrados y de si ya generó factura:

- **Recibo pendiente, sin factura:** se anula directamente y se genera un **recibo rectificativo** `RER-AAAA-NNNNN` con los importes en negativo (a 0€, ya que no había ningún cobro que compensar). El año de la numeración es el de `periodo_desde` del recibo **original** que se rectifica, nunca la fecha en que se ejecuta la anulación: un recibo de diciembre de 2025 anulado en julio de 2026 genera `RER-2025-NNNNN`, no `RER-2026-NNNNN`.
- **Recibo cobrado (total o parcialmente), sin factura:** antes de anular, el sistema pregunta explícitamente si se quiere devolver el cobro (*"El recibo ya está cobrado. Para anularlo es necesario devolver el cobro en el recibo rectificativo..."*). Si se cancela, no se modifica nada. Si se acepta, se genera el `RER-AAAA-NNNNN` (mismo criterio de año que el punto anterior) con el cobro reflejado en negativo (campos `importe_pagado` y `pagos`), dejando trazabilidad completa de la devolución.
- **Recibo con factura EMITIDA:** ya no basta con anular el recibo dejando la factura intacta. El sistema pregunta (*"Este recibo tiene una factura emitida asociada. Para anular el recibo es necesario anular también la factura y generar su factura rectificativa..."*). Si se cancela, no se modifica nada (ni recibo, ni factura, ni cobros). Si se acepta:
  1. Se anula primero la factura asociada, generando su factura rectificativa `RET-AAAA-NNNNN` (misma lógica que anular una factura desde el módulo Facturas — código reutilizado, no duplicado).
  2. Solo si eso termina con éxito, el recibo pasa a `anulado`.
  3. **No se genera ningún `RER`**: la corrección fiscal ya la aporta el `RET` de la factura; un recibo rectificativo adicional duplicaría la compensación.
  4. Si falla la rectificación de la factura, el recibo **no** se anula y se informa con un mensaje de error — nunca queda una anulación parcial.
- **Recibo con factura ya rectificada (o anulada) de antes:** la corrección fiscal ya existe, así que el recibo se anula directamente sin generar ningún documento adicional (evita duplicar el `RET`).

Un recibo ya anulado, o un recibo que es en sí mismo un rectificativo (`RER-...`), no se puede volver a anular. Estas reglas están protegidas también en el backend (`api.php`, función `validarDatos()`): un recibo con factura vinculada cuyo estado siga `'emitida'` no puede pasar a `anulado` por ninguna vía, ni siquiera llamando directamente al endpoint; y un recibo cobrado sin factura exige el flag `confirmar_devolucion` en la petición.

### Métodos de cobro

Transferencia · Domiciliación · Efectivo · Bizum · Cheque

### Filtros disponibles

Búsqueda de texto siempre visible + filtros avanzados (Estado incl. Rectificativos, Propietario, Inquilino, Fecha desde/hasta) plegados en un desplegable "Filtros avanzados" que se abre automáticamente si ya hay alguno activo. Paginación configurable en Parámetros.

---

## 15. Facturas legales

Conformes al **RD 1619/2012** (Reglamento de Facturación):
- Numeración correlativa única y **anual**: `FAC-AAAA-NNNNN`. El contador continúa durante todos los meses del año y solo se reinicia a `00001` al empezar un año nuevo. El año sale siempre de la **fecha de emisión de la factura**, nunca del período del recibo que factura (un recibo de junio facturado el 10 de julio pertenece a la serie anual del año en curso, `FAC-2026-NNNNN`, no a una serie `FAC-202607-NNNNN`) — ver detalle en [§14](#14-recibos-y-cobros) y en la sección dedicada más abajo.
- Inmutabilidad: no se editan tras emisión
- Anulación lógica: la factura original pasa a estado "rectificada" (se conserva íntegra) y se genera automáticamente una **factura rectificativa** con la nueva numeración `RET-AAAA-NNNNN` (ej. `RET-2026-00001`), también anual e independiente de la serie `FAC`, con todos los importes negados, conforme al art. 15 del RD 1619/2012
- Si VERI*FACTU está activo: la factura rectificativa también se registra ante la AEAT como cualquier otra factura

### Numeración documental — los 4 tipos son anuales (desde julio 2026)

Cada tipo de documento tiene su propio contador independiente en `doc_secuencias` (clave `tipo`+`periodo`, ver `BASE_DE_DATOS.md` §7). Los 4 tipos usan numeración **anual** (`AAAA`, 4 dígitos): el contador continúa durante todos los meses del año y solo se reinicia a `00001` al empezar un año nuevo. Lo que distingue a cada tipo es de **dónde sale el año**, nunca el formato:

| Tipo | Documento | Numeración | Fuente del año |
|------|-----------|-----------|-----------------|
| `REC` | Recibo | `REC-AAAA-NNNNN` | `periodo_desde` del propio recibo |
| `RER` | Recibo rectificativo | `RER-AAAA-NNNNN` | `periodo_desde` del recibo **original** que se rectifica |
| `FAC` | Factura | `FAC-AAAA-NNNNN` | `fecha_emision` de la propia factura |
| `RET` | Factura rectificativa | `RET-AAAA-NNNNN` | `fecha_emision` de la propia factura rectificativa |

En ningún caso se usa la fecha actual del servidor como sustituto si falta el dato necesario: el backend (`assets/php/api.php`, acción `nextNumeroDoc`) exige `periodo` explícito en la petición y rechaza con `400` cualquier formato que no sea `AAAA` (año 2000-2099) — incluido el formato mensual `AAAAMM` histórico, que ya no se genera para ningún tipo.

Ejemplos:
- Un recibo de diciembre de 2026 generado en enero de 2027 sigue siendo `REC-2026-NNNNN` (el año sale de `periodo_desde`, no de cuándo se genera).
- Un recibo de junio de 2026 anulado en julio de 2026 genera `RER-2026-NNNNN` (el año sale del `periodo_desde` del recibo original, no de la fecha de la anulación); si ese mismo recibo fuera de diciembre de 2025, la anulación en 2026 generaría `RER-2025-NNNNN`.
- Un recibo de diciembre de 2026 facturado el 5 de enero de 2027 genera `FAC-2027-00001` — la primera factura de la serie anual 2027 (el año sale de la fecha de emisión de la factura, nunca del período del recibo).

Las 4 series son completamente independientes entre sí: el número de una no afecta al contador de las otras, aunque compartan año.

> Los documentos emitidos **antes** de esta migración con el formato mensual antiguo (`REC-202601-00001`, `FAC-202601-00001`, etc.) se conservan tal cual, como documentos históricos — no se renumeran retroactivamente. Los dos formatos coexisten sin ambigüedad ni colisión (el segundo guión cae en una posición distinta del texto en cada formato) y siguen mostrándose, filtrándose, imprimiéndose y exportándose con normalidad.

### Acciones por factura en la tabla

Igual que en Recibos, cada fila muestra una acción principal — **Imprimir / PDF** (emitida) o **Ver** (rectificada/anulada, estados cerrados sin más acciones posibles) — y un menú **Más ▾** con Email (Comunicación), Ver recibo origen y envío/estado AEAT (Documentos y VERI*FACTU), y Anular factura (Gestión, solo si está emitida).

### Factura rectificativa vs. recibo rectificativo

| Documento | Cuándo se genera | Numeración | Efecto |
|-----------|-------------------|------------|--------|
| Factura rectificativa (`RET`) | Al anular una factura ya emitida — desde el módulo Facturas, o en cascada al anular un recibo con factura emitida asociada | `RET-AAAA-NNNNN` (anual — el año de su propia fecha de emisión) | Cancela fiscalmente la factura original (importes negados); la original queda "rectificada" |
| Recibo rectificativo (`RER`) | Al anular un recibo que **no** tiene factura emitida asociada | `RER-AAAA-NNNNN` (anual — el año de `periodo_desde` del recibo **original**, igual criterio que `REC`) | Compensa el recibo original en los totales internos (importes negados, incluido el cobro si lo había); no tiene efecto fiscal ante la AEAT |

Un recibo con factura emitida nunca genera un `RER`: su corrección la aporta el `RET` de la factura (ver sección anterior). Y una factura nunca se rectifica automáticamente si no existía ya — no hay forma de generar un `RET` sin una factura `F1` previa.

La función que genera la rectificativa de una factura (`anularFacturaConRectificativa()` en `facturas.js`) es una única pieza de código reutilizada tanto por el botón "Anular factura" del módulo Facturas como por la cascada de anulación de recibos, para no duplicar la lógica de numeración e importes negados en dos sitios distintos.

---

## 16. Revisiones de renta

### Cómo se calcula la "Revisión IPC/IRAV pendiente"

Un contrato aparece como revisión pendiente (aviso naranja del Dashboard + badge `IPC ⚠` y botón `⚠ IPC` en Contratos) cuando se cumplen **las 5 condiciones a la vez** (función `contratosIPCPendientes()` en `assets/js/extras.js`):

1. `contratos.estado = 'activo'`.
2. `contratos.revision` es `'IPC'` o `'IRAV'` (los valores `'Fija'` y `'Sin revision'` nunca generan este aviso).
3. El **mes** de `contratos.fecha_inicio` coincide con el mes actual (el día del mes es irrelevante).
4. El **año** de `contratos.fecha_inicio` es anterior al año actual (un contrato de alta este mismo año no genera aviso el mismo año).
5. `contratos.ipc_anio_aplicado` es distinto del año actual (`NULL` o un año anterior cuentan como pendiente; si ya es el año en curso, no vuelve a aparecer).

Al pulsar `⚠ IPC` se abre un modal (`modalAplicarIPC()`) que consulta el % oficial a la API del INE (`api.php?action=ine_rate`); si no hay conexión, el % se introduce a mano. Al aplicar (`aplicarSubidaIPC()`): se actualiza `contratos.renta_base` con el nuevo importe, se fija `contratos.ipc_anio_aplicado` al año actual (esto es lo que hace desaparecer el aviso) y se añade una fila en `historial_rentas` con el detalle de la subida, visible desde el botón **Historial** del contrato.

Este aviso es distinto de otros dos, que no filtran por tipo de revisión:
- la campana de notificaciones y la tarjeta "Revisiones anuales urgentes" del Dashboard muestran **cualquier** contrato activo cuyo aniversario caiga en los próximos 30 días, tenga o no revisión indexada;
- la tabla "Próximas revisiones anuales de renta" del Dashboard lista **todos** los contratos activos ordenados por días restantes, sin límite de ventana.

### Cómo forzar manualmente una revisión pendiente (para pruebas)

Usar siempre una base de datos de pruebas, nunca la real. El SQL usa `CURDATE()`/`DATE_SUB()`/`YEAR()` para funcionar cualquier día:

```sql
-- 1) Localizar contratos activos disponibles para la prueba
SELECT id, fecha_inicio, revision, ipc_anio_aplicado, renta_base, estado
FROM contratos WHERE estado = 'activo' ORDER BY id LIMIT 5;

-- 2) Forzar revisión IPC PENDIENTE (mismo mes que hoy, hace 2 años, sin aplicar nunca)
UPDATE contratos
SET revision = 'IPC', fecha_inicio = DATE_SUB(CURDATE(), INTERVAL 2 YEAR), ipc_anio_aplicado = NULL
WHERE id = <ID_CONTRATO>;

-- 3) Marcarla como YA APLICADA este año (no debe volver a aparecer como pendiente)
UPDATE contratos
SET ipc_anio_aplicado = YEAR(CURDATE())
WHERE id = <ID_CONTRATO>;

-- 4) Revertir / limpiar la prueba
UPDATE contratos SET revision = 'Sin revision', ipc_anio_aplicado = NULL WHERE id = <ID_CONTRATO>;
```

Después de forzar el paso 2: abrir el **Dashboard** (debe verse el aviso naranja) y **Contratos** (badge `IPC ⚠` y botón `⚠ IPC`, filtro "⚠ Revisión pendiente"). Tras aplicar la subida: comprobar `renta_base` actualizada, `ipc_anio_aplicado` = año actual, nueva fila en `historial_rentas`, y que el aviso desaparece.

---

## 17. Informes y exportación Excel

### Informes de gestión (por año)

| Informe | Contenido |
|---------|-----------|
| Todos los recibos del año | Listado completo con desglose IVA/IRPF |
| Ingresos por finca | Tabla mensual por edificio |
| Ingresos por piso | Detalle por inmueble con inquilino |
| Recibos pendientes | Solo Pendiente o Parcial |
| Histórico de cobros | Pagos con fecha, método y cuenta |
| Resumen por propietario | Facturado, cobrado, pendiente por propietario |

### Informes fiscales

| Informe | Normativa |
|---------|-----------|
| Rendimientos trimestrales IRPF | Art. 23.2 LIRPF (reducción 60% vivienda habitual) |
| Modelo 100 — Capital Inmobiliario | Declaración anual de la renta |
| Modelo 115/180 — Retenciones | Solo contratos con IRPF > 0 |

---

## 18. VERI*FACTU — Facturación electrónica AEAT

Integración completa con el SIF (Sistema de Información de Facturación) de la AEAT según el **RD 1007/2023**:

- Desactivado por defecto — sin envíos a la AEAT hasta activación explícita
- Hash SHA-256 encadenado por factura
- XML SOAP conforme al esquema oficial AEAT
- Código QR de verificación en el PDF de factura
- Entornos: pruebas (`prewww1.aeat.es`) y producción (`www1.aeat.es`)
- Certificado digital (.p12/.pfx) almacenado en `certs/`

Ver `assets/docs/ayuda_verifactu.php` para la guía técnica completa.

---

## 19. Parámetros de configuración

Menú lateral → **Parámetros**. Siete pestañas:

| Pestaña | Contenido |
|---------|-----------|
| Dashboard | Activa/desactiva cada widget del panel principal (KPIs, alerta IPC, revisiones, gráficos, previsión de cobros...) |
| Paginación | Filas por página en cada sección |
| Botones | Muestra/oculta botones de acción por módulo (Contratos, Recibos, Facturas, Inquilinos, Propietarios, Sistema) |
| WhatsApp | Envío por WhatsApp + generación automática de PDF |
| VERI*FACTU | Certificado, entorno, NIF del obligado de emisión |
| Documentos | Módulo de plantillas DOCX |
| Menú | Muestra/oculta cada opción del menú lateral (`menu_propietarios`, `menu_facturas`, etc.); un grupo entero desaparece si se ocultan todas sus opciones. Dashboard y Parámetros son siempre visibles. |

Cada opción incluye un botón `?` con descripción detallada y consejo de uso. Todos estos parámetros (76 en total) se siembran con sus valores por defecto tanto en la instalación limpia como en la instalación con datos de ejemplo — la aplicación funciona sin configuración manual desde el primer arranque.

---

## 20. Preguntas frecuentes

**¿Puedo instalar AlquiGest en un servidor real (no localhost)?**  
Desde v3.0.1 ya existe autenticación de usuario real (ver [§23](#23-autenticación-usuarios-y-permisos)), pero sigue habiendo que eliminar la restricción `requireLocalhost()` en los backends PHP para que sea accesible fuera de la máquina local, y revisar HTTPS (las cookies de sesión no fuerzan `secure` en HTTP plano).

**¿Cómo hago una copia de seguridad?**  
Mi Empresa → *Descargar backup JSON*. El archivo puede restaurarse desde `install.php`.

**¿Por qué las plantillas pierden el formato de las variables?**  
Al sustituir variables, el motor reconstruye el párrafo completo. Aplica los formatos al párrafo entero en lugar de solo a la variable para evitar que se pierdan.

**¿Puedo tener dos contratos activos en el mismo inmueble?**  
No. Dar de baja el contrato actual antes de crear otro.

**¿Cómo convierto el DOCX generado a PDF?**  
Abrir con Word o LibreOffice y exportar. AlquiGest no incluye conversión en servidor (requiere LibreOffice, no disponible en MAMP/Windows).

**¿WebP funciona en FotosContrato?**  
Sí si PHP GD tiene soporte WebP compilado. En MAMP suele estar disponible. Si no, convierte las imágenes a JPG/PNG antes de subirlas.

**¿Qué pasa si el inquilino no tiene email?**  
El botón de envío por email aparece deshabilitado. Añadir el email en la ficha del inquilino para habilitarlo.

---

## 21. Limitaciones

| Limitación | Alternativa |
|-----------|-------------|
| Sin conversión DOCX → PDF en servidor | Exportar desde Word/LibreOffice |
| Un solo contrato activo por inmueble | Dar de baja el anterior primero |
| Email solo vía Gmail (contraseña de aplicación) | Editar SMTP en `api.php` |
| WebP requiere GD con soporte WebP | Convertir imágenes a JPG/PNG |
| Solo funciona en localhost por defecto | Editar `requireLocalhost()` en backends |
| Sin límite de intentos de login (rate limiting) | Mitigado por `requireLocalhost()`; añadir si se expone fuera de localhost |
| Sin recuperación de contraseña por email | Restablecer `password_hash` directamente en la tabla `usuarios` |
| Sin app móvil nativa | Funciona en navegador móvil (optimizado escritorio) |
| Sin sincronización en la nube | Backup JSON manual periódico |
| Sin transacción SQL real en la cascada "anular recibo con factura" ni en "facturar recibo" (son 2-3 guardados secuenciales desde JS) | El orden está protegido (la factura se rectifica antes que el recibo; si falla, el recibo no se toca), pero un corte de red justo entre pasos podría dejar un documento a medias — caso raro, sin reversión automática. Ver §25 |
| La generación de recibos (individual y en lote) no consulta `historial_rentas`: si se genera hoy el recibo de un mes pasado tras una revisión de renta, usa la renta ACTUAL del contrato, no la vigente en ese período histórico. Tampoco prorratea contratos dados de alta/baja a mitad de mes | Revisar manualmente el importe si se genera un recibo de un período anterior a una revisión de renta |
| CSRF: `action=save`/`action=delete` de `api.php` ahora exigen método POST (cierra el vector de CSRF vía GET/enlace/`<img>`), pero no exigen aún token `_csrf` como sí hacen `saveUsuario`/`deleteUsuario` | Mitigado por `SameSite=Lax` (bloquea POST cross-site) + `requireLocalhost()`; añadir `_csrf` sistemático si se expone la app fuera de un único usuario de confianza en localhost |
| ~150 estilos con colores hexadecimales fijos (`style="...#xxxxxx..."`) en 16 archivos JS, fuera del sistema de variables CSS — pueden no adaptarse al modo oscuro | Revisar y migrar a `var(--color-*)` progresivamente; ver §25 |
| `getAll`/`getTable`/`export.php` cargan tablas completas en cada arranque/informe, sin paginar ni filtrar en SQL | Aceptable al volumen actual (decenas/cientos de recibos); revisar si crece a miles |
| `assets/js/recibos.js` es código muerto (no se carga desde `AlquiGest.php`) pero sigue en el repositorio con una versión antigua y menos completa de la lógica de recibos | No editar ese archivo pensando que es el módulo activo (es `recibos-lista.js` + `recibos-cobro.js` + `recibos-pdf.js`); pendiente de eliminar físicamente del repositorio |

### Bugs conocidos sin corregir

Ninguno relacionado con la generación de recibos tras la corrección de julio 2026 (ver [§24](#corrección-de-generación-individual-de-recibos-de-períodos-anteriores)). El bug de colores hardcodeados en la previsualización de Plantillas se corrigió el 2026-07-11 (ver §26). Quedan pendientes de corregir, por ser cambios más amplios que exceden el alcance de una corrección puntual, los ítems listados en la tabla de Limitaciones de arriba y en el detalle de auditoría del [§25](#25-auditoría-de-julio-2026--hallazgos-y-estado).

---

## 22. Futuras mejoras propuestas

### Alta prioridad
- **Portal del inquilino**: acceso web para descargar recibos sin necesitar al administrador
- **Notificaciones automáticas**: email/SMS al inquilino X días antes del vencimiento del recibo
- **Importación CSV**: carga masiva de propietarios, inquilinos y contratos

### Media prioridad
- **Multi-empresa**: varias empresas administradoras en una instalación
- **Renovación automática de contratos indefinidos**
- **Exportar/importar plantillas de email**
- **Informe de rentabilidad** por inmueble (incluyendo gastos manuales)
- **Formulario de Contratos en secciones colapsables** (datos, económico, revisión, personas adicionales) — hoy es un único modal largo
- **Resumen previo en Generar Recibos** ("Se van a generar N recibos por valor de X €") antes de confirmar el lote

### Baja prioridad
- **Widgets del dashboard reordenables** (drag-and-drop)
- **API REST pública documentada** para integración con herramientas externas
- **Tema de color personalizable**

---

## 23. Autenticación, usuarios y permisos

Desde la v3.0.1, AlquiGest exige iniciar sesión para usar la aplicación.

### Roles

| Rol | Puede |
|---|---|
| `admin` | Todo: la aplicación completa, `install.php` completo (instalar/restaurar/backups), gestión de usuarios, ver todos los logs |
| `user` | La aplicación completa igual que un admin, **excepto**: dentro de `install.php` solo ve la sección de copia de seguridad; no puede gestionar usuarios |

### Primera instalación (sin bloquear a nadie)

1. Si no existe base de datos ni tabla `usuarios`: `install.php` funciona exactamente igual que siempre (sin login), incluida la instalación limpia/con ejemplos.
2. En cuanto la tabla `usuarios` existe pero está vacía, `install.php` muestra **solo** el formulario "Crear el primer administrador" (con un desplegable para volver a reinstalar la BD si hiciera falta).
3. Tras crear el primer admin, `install.php` (y toda la aplicación) exige login a partir de ese momento.

### Login / Logout

- `login.php` (raíz del proyecto): formulario de usuario/contraseña, con mensaje claro si el usuario/contraseña es incorrecto o si la cuenta está desactivada.
- Sesión PHP con cookie `HttpOnly`/`SameSite=Lax`, id regenerado en cada login (protección contra fijación de sesión), cierre automático tras 60 minutos de inactividad.
- Botón "Cerrar sesión" en el menú de usuario de la cabecera (`assets/js/ux.js`); llama a `api.php?action=logout` y redirige a `login.php`.
- Un usuario desactivado por un admin mientras tiene la sesión abierta pierde el acceso en su siguiente petición (revalidación contra la BD en cada llamada, no solo al iniciar sesión).

### Gestión de usuarios (solo admin)

Menú lateral → **Usuarios** (oculto para el rol `user`). Permite crear, editar (nombre, email, usuario, rol, contraseña, activo/inactivo) y eliminar (lógicamente) cuentas. El sistema impide que el único administrador activo se quite a sí mismo el rol de admin, se desactive o se elimine, para no quedar nadie sin acceso de administración.

### Protección de `install.php` (backend, no solo visual)

`install.php` bloquea en el propio PHP —antes de ejecutar nada— cualquier modo (`clean`, `sample`, `restore`, `fixzip`) que no sea `backup`/`backup_data` si quien lo solicita no tiene rol `admin`, aunque la petición llegue directamente por POST sin pasar por la interfaz. Todos los formularios llevan además un token CSRF de sesión.

### Registro de actividad por usuario

La pantalla **Actividad** muestra ahora quién ha hecho cada acción (columna y filtro "Usuario"), incluyendo login/logout, intentos fallidos, y alta/edición/baja de usuarios. Los eventos sin usuario asociado (anteriores a este cambio, o intentos de login con un usuario inexistente) se muestran como "Sistema".

---

## Corrección de generación individual de recibos de períodos anteriores

**Fecha:** 2026-07-10. **Estado:** corregido y verificado (código + pruebas manuales y automatizadas contra la base de datos).

### Síntoma

Al generar un recibo **individual** (botón "Generar recibo" desde Contratos, o "Nuevo recibo" desde Recibos) para un período distinto al mes en curso — por ejemplo, crear el recibo de **junio** estando ya en julio — el resultado era incoherente:
- el modal no tenía ningún selector de mes/año: había que editar por separado 4 campos independientes (`fecha_emision`, `periodo_desde`, `periodo_hasta`, `concepto_periodo`), cada uno inicializado con el mes actual;
- si solo se cambiaban las fechas de período pero se dejaba `fecha_emision` sin tocar (el caso más probable, al no ser obvio que ese campo también había que cambiarlo), el recibo se numeraba con el período de **hoy** (julio), no con el de junio;
- el texto de `concepto_periodo` por defecto (`"Alquiler julio de 2026"`) no coincidía nunca con el formato usado por la generación masiva (`"Julio 2026"`), así que ningún flujo reconocía como "ya generado" un recibo creado por el otro;
- no existía ninguna comprobación de duplicados — ni en el cliente ni en el servidor — para la generación individual.

### Pasos para reproducirlo (antes de la corrección)

1. Instalación con datos de ejemplo, fecha del sistema en julio de 2026.
2. Seleccionar un contrato activo → **Generar recibo**.
3. Cambiar `periodo_desde`/`periodo_hasta` a junio, dejando `fecha_emision` en su valor por defecto (hoy, julio).
4. Guardar: el recibo se numeraba `REC-202607-NNNNN` (julio) en vez de `REC-202606-NNNNN` (junio), y `concepto_periodo` no coincidía con el formato `"Junio 2026"` esperado.
5. Generar Recibos en Lote para julio: al no reconocer el recibo anterior como "ya generado" para julio (el `concepto_periodo` no coincidía con ningún formato), se creaba un recibo de julio adicional para el mismo contrato — dos documentos para el mismo período, uno de ellos mal etiquetado.

### Causa raíz

No era un simple desfase de "+1/-1 mes". Eran **tres defectos independientes que se combinaban**:

1. **`assets/js/recibos-cobro.js`** (`modalGenerarRecibo()`, `actualizarFormNuevoRecibo()`, `saveRecibo()`): el período del recibo no tenía una única fuente de verdad. `saveRecibo()` calculaba el período de numeración a partir de `fecha_emision` (`periodo = fechaEm.replace(/-/g,'').slice(0,6)`), un campo pensado para "cuándo se emite el documento", no para "qué mes cubre". `concepto_periodo` era texto libre inicializado con `now.toLocaleDateString(...)`, un formato distinto al de la generación masiva.
2. **`assets/js/generar.js`** (`generarLote()`): un bug de *scoping* de JavaScript — dos declaraciones `const periodo` anidadas (línea de la función, con formato humano `"Junio 2026"`, y otra dentro del `for`, con formato numérico `"202606"` para `nextNumeroDoc`) — hacía que la variable interna **sombrease** a la externa. El recibo se guardaba con `concepto_periodo` en formato numérico (`"202606"`), rompiendo la detección de "ya generado" en la siguiente ejecución del propio lote, incluso sin tocar la generación individual.
3. **`assets/php/api.php`** (`nextNumeroDoc`): si el parámetro `periodo` no llegaba en la petición, el servidor lo sustituía silenciosamente por `date('Ym')` (la fecha actual), en vez de responder con un error — el mismo patrón de "fecha actual usada accidentalmente" que señala esta corrección. Además, no validaba que el mes estuviera en el rango 01-12. Y no existía **ninguna** validación de negocio en `validarDatos()` (contrato existente, período con fecha válida, contrato activo y vigente para ese período, ni detección de duplicados) para la generación de recibos: todo el cálculo (fechas, importes, período) se hacía en JavaScript y el backend se limitaba a un `INSERT`/`UPDATE` genérico confiando ciegamente en el payload del cliente.

### Archivos afectados

- `assets/js/helpers.js` — funciones nuevas: `periodoLabel()`, `periodoYYYYMM()`, `periodoPrimerDia()`, `periodoUltimoDia()` (fuente única de verdad del período, usada por ambos flujos); `nextNumeroDoc()` ahora exige `periodo` explícito y pasa a POST.
- `assets/js/recibos-cobro.js` — `modalGenerarRecibo()` y `actualizarFormNuevoRecibo()` añaden un selector único de Mes/Año (`actualizarPeriodoRecibo()`) que sincroniza `periodo_desde`/`periodo_hasta`/`concepto_periodo`/`fecha_limite`; `saveRecibo()` deriva la numeración del período elegido (no de `fecha_emision`), añade aviso de duplicado en cliente y deshabilita el botón "Crear recibo" durante el guardado (anti doble clic).
- `assets/js/generar.js` — corregido el *shadowing* de `periodo` (renombrada la variable interna a `periodoDoc`); ahora guarda también `periodo_desde`/`periodo_hasta` (antes no se guardaban en la generación masiva); el chequeo de "ya generado" excluye recibos anulados/rectificativos.
- `assets/php/api.php` — `nextNumeroDoc` exige `periodo` explícito y método POST, valida `AAAAMM` con mes 01-12 real (400 si no); acción `save` calcula `periodo_key` en servidor y exige POST; `validarDatos()` (caso `recibos`) valida en alta: contrato existente, período con fecha válida, período dentro de la vigencia del contrato (`fecha_inicio`), contrato con `estado='activo'`.
- `assets/php/install.php` — nueva columna `recibos.periodo_key` + `UNIQUE KEY uq_recibos_periodo_key` en el `CREATE TABLE` de instalaciones nuevas.

### Solución aplicada

- **Fuente única del período**: un selector de Mes/Año (igual patrón que la generación en lote) sustituye a la edición independiente de 4 campos; al cambiarlo se recalculan automáticamente `periodo_desde`, `periodo_hasta`, `concepto_periodo` y `fecha_limite` con las mismas funciones que usa la generación masiva, así que ambos flujos producen siempre el mismo formato.
- **Numeración por período, no por fecha de emisión ni por la fecha del servidor**: tanto la generación individual como la masiva calculan el `AAAAMM` de `nextNumeroDoc` a partir del período elegido. El backend deja de aceptar `nextNumeroDoc` sin `periodo` explícito.
- **Regla de duplicados**: `contrato_id + año + mes` (no el texto de `concepto_periodo`). Se materializa como una columna `periodo_key` (`"<contrato_id>-<AAAAMM>"`) calculada **siempre en el servidor**, `NULL` para recibos anulados o rectificativos, con una `UNIQUE KEY` real en MySQL — protección atómica también frente a condiciones de carrera (dos peticiones simultáneas), no solo una comprobación previa bypasseable.
- **Validaciones de backend**: contrato existente, período con fecha válida, período dentro de la vigencia del contrato, contrato activo — todo verificado también llamando directamente al endpoint (sin pasar por la interfaz).

### Pruebas realizadas (contra la base de datos de ejemplo, ver §25 para la lista completa)

| # | Prueba | Resultado |
|---|--------|-----------|
| 1 | Crear recibo individual de **junio** con la fecha del sistema en julio | `REC-202606-00007`, `concepto_periodo="Junio 2026"`, `periodo_desde/hasta` de junio, `fecha_emision` de hoy (julio) — numeración y período correctos |
| 2 | Generar Recibos en Lote de **julio** después del paso 1 | Genera julio para ese contrato con normalidad (`REC-202607-00013`); **no** modifica ni duplica el recibo de junio |
| 3 | Crear julio individual primero y junio (u otro contrato) después | Ambos períodos se conservan de forma independiente |
| 4 | Crear dos veces el mismo contrato+mes (secuencial) | La segunda petición: `409 RECIBO_DUPLICADO` |
| 5 | Dos peticiones **concurrentes** para el mismo contrato+mes | Una `200 OK`, la otra `409 RECIBO_DUPLICADO` — protegido por la `UNIQUE KEY`, no por una condición de carrera evitable solo "la mayoría de las veces" |
| 6 | Anular un recibo y volver a emitir el mismo período | `periodo_key` se libera al anular (pasa a `NULL`); el reemitido se acepta con normalidad |
| 7 | Contrato iniciado a mitad del mes del recibo | Se acepta (no se prorratea, comportamiento ya existente) |
| 8 | Contrato iniciado **después** del período del recibo | Rechazado: 422 "El período del recibo es anterior a la fecha de inicio del contrato" |
| 9 | Contrato no activo (`finalizado`/`rescindido`) | Rechazado: 422 "El contrato no está activo" |
| 10 | `nextNumeroDoc` con mes `00`, `13`, vacío o ausente | Rechazado: 400 en los cuatro casos (antes: el caso "ausente" se aceptaba silenciosamente con la fecha de hoy) |
| 11 | Salto de año (diciembre → enero) | Secuencias independientes, ambas reinician correctamente en `1` |
| 12 | Contrato inexistente / `contrato_id` 0 | Rechazado: 422 |
| 13 | Sin sesión / usuario desactivado | Rechazado: 401 en ambos casos |
| 14 | Reinstalación con datos de ejemplo (×2) tras aplicar la corrección | Sin errores, mismo recuento de filas en ambas pasadas, 0 duplicados |

Resultado: **junio se crea correctamente, julio se genera después sin conflicto, ningún duplicado se cuela ni en secuencia ni en concurrencia.**

---

## 25. Auditoría de julio 2026 — hallazgos y estado

Auditoría multiagente (documentación + frontend + backend + ciclo de recibos/facturas + seguridad + calidad general) realizada el 2026-07-10 sobre el estado real del código, además de la corrección descrita en la sección anterior. Resumen de lo encontrado y su estado:

### Corregido en esta sesión

- **`validarDatos('inmuebles', ...)` exigía un campo `nombre` inexistente en la tabla `inmuebles`** (copiado por error del caso `fincas` contiguo) — bloqueaba con `422` **todo** alta/edición de un piso/local desde la interfaz. Corregido para validar el campo real obligatorio (`planta`).
- **`login.php` no aplicaba `requireLocalhost()`**, a diferencia del resto de backends sensibles. Corregido (añadido `require helpers.php` + `requireLocalhost()`).
- **CSRF vía GET en `action=delete`/`action=save`**: ambas acciones aceptaban el método GET (un enlace, `<img>` o `meta refresh` en un sitio externo podía borrar/guardar datos si la víctima tenía sesión abierta, ya que `SameSite=Lax` sí permite cookies en navegación GET de nivel superior). Corregido: ambas exigen ahora método POST (405 en caso contrario) — el frontend ya usaba POST en todos los casos, cambio sin riesgo de regresión.
- **`nextNumeroDoc` sin índices de rendimiento** en `recibos.fecha_emision`/`recibos.periodo_desde` — añadidos (migración no destructiva, `CREATE INDEX` con captura de excepción para compatibilidad MySQL 5.7).

### Documentado como limitación conocida (no corregido en esta sesión)

Por ser cambios de mayor alcance que el de esta corrección puntual, o por requerir decisiones de producto (ver tabla de Limitaciones en §21):
- Falta de token `_csrf` en `save`/`delete` genéricos (mitigado por `SameSite=Lax` + `requireLocalhost()`).
- ~150 estilos con colores hexadecimales fijos en 16 archivos JS que no se adaptan al modo oscuro.
- Sin transacción SQL real en operaciones multi-tabla (facturar recibo, rectificar factura): son varios guardados secuenciales desde el cliente; un fallo de red a mitad de la cascada puede dejar un estado intermedio (caso raro, ya con guardas de reintento en el estado previo real de BD para evitar duplicar la corrección).
- Generación de recibos sin prorrateo ni consulta a `historial_rentas` para períodos pasados a una revisión de renta.
- `assets/js/recibos.js` es código muerto confirmado (no se carga desde `AlquiGest.php`); se ha dejado en el repositorio en vez de eliminarlo físicamente en esta sesión (acción irreversible fuera del alcance explícitamente autorizado).
- `getAll`/`export.php` cargan tablas completas sin paginar (aceptable al volumen actual).
- Otros hallazgos de severidad baja/informativa (duplicación de componentes de error en `config.js`/`helpers.js`, inconsistencia de parseo de fechas UTC/local en algunos módulos, funciones JS huérfanas) — no afectan a la corrección de esta sesión ni suponen riesgo funcional inmediato.

### Verificado como correcto (sin cambios)

- La reserva de numeración (`nextNumeroDoc`) usa `SELECT ... FOR UPDATE` en una transacción InnoDB — protección real de condiciones de carrera para el contador.
- La revalidación de sesión contra la base de datos en cada petición (usuario desactivado pierde el acceso de inmediato, no solo al iniciar sesión) — verificado empíricamente en esta sesión, no solo por lectura de código.
- Las salvaguardas de anulación de recibos con factura emitida (`validarDatos()`, caso `recibos`).

---

## 26. Registro de cambios

### 2026-07-10 — Numeración anual de facturas (FAC/RET)
- **Cambio:** las facturas (`FAC`) y facturas rectificativas (`RET`) pasan de numeración mensual a **anual** (`FAC-AAAA-NNNNN`, `RET-AAAA-NNNNN`) — el contador continúa durante todos los meses del año y solo reinicia en año nuevo. El año sale siempre de la fecha de emisión de la factura, nunca del período del recibo que factura. Ver la subsección "Numeración documental — mensual vs. anual" en [§15](#15-facturas-legales).
- **Sin cambios:** recibos (`REC`) y recibos rectificativos (`RER`) siguen siendo mensuales exactamente como antes — ningún archivo de `generar.js`/`recibos-cobro.js` fue modificado.
- **Archivos modificados:** `assets/js/facturas.js` (año en vez de AAAAMM en `generarNumeroFacturaDesdeRecibo()` y `anularFacturaConRectificativa()`), `assets/js/helpers.js` (`nextNumeroDoc()` valida el formato de período según el tipo), `assets/php/api.php` (misma validación tipo-dependiente en `nextNumeroDoc`, rechazo explícito de combinaciones incorrectas), `assets/php/install.php` (datos de ejemplo de facturas actualizados a formato anual con demostración de continuidad entre meses y salto de año; siembra de `doc_secuencias` ampliada para reconocer también períodos de 4 dígitos), `AlquiGest.php` (versión de caché).
- **Esquema:** `doc_secuencias.periodo` se mantiene `CHAR(6)` sin cambios — admite tanto `AAAAMM` (6 caracteres) como `AAAA` (4 caracteres) sin ambigüedad (MySQL retira el relleno de espacios de un `CHAR` al leerlo), verificado empíricamente contra MySQL 5.7 real. Sin migración de esquema.
- **Facturas históricas:** ninguna existía en la base al aplicar el cambio (base de pruebas reinstalada con datos de ejemplo); si en el futuro coexisten facturas antiguas en formato mensual, se conservan sin renumerar — los dos formatos nunca coinciden textualmente (longitudes distintas).
- **Pruebas:** 19 casos (continuidad anual multi-mes, independencia `FAC`/`RET`, año de expedición frente a período del recibo, salto de año, regresión `REC`/`RER`, validaciones de formato cruzado, concurrencia de hasta 5 peticiones simultáneas sin duplicados) ejecutados vía API directa y vía interfaz real (generación y anulación de una factura desde Recibos/Facturas). 0 duplicados en `numero_factura`/`numero_recibo`; año embebido en cada `numero_factura` verificado contra `YEAR(fecha_emision)`.

### 2026-07-10 (posterior) — Numeración anual de recibos (REC/RER) — supera la entrada anterior
- **Cambio:** extiende la numeración anual (ya aplicada a `FAC`/`RET` arriba) también a recibos (`REC`) y recibos rectificativos (`RER`): los 4 tipos documentales usan ahora exclusivamente `AAAA` en `doc_secuencias`/`nextNumeroDoc`. **Esta entrada sustituye el punto "Sin cambios" de la entrada anterior** (REC/RER dejan de ser mensuales).
- **Fuente del año:** `REC` → `periodo_desde` del propio recibo (no la fecha de emisión ni la fecha del servidor). `RER` → `periodo_desde` del recibo **original** que se rectifica, nunca la fecha de la anulación — verificado con un caso real cruzando año (recibo `REC-202512-00001`, período diciembre de 2025, anulado el 2026-07-10, generó `RER-2025-00001`, no `RER-2026-NNNNN`). `FAC`/`RET` no cambian (siguen usando su propia `fecha_emision`).
- **`periodo_key` (anti-duplicados) no cambia:** sigue siendo `"<contrato_id>-<AAAAMM del periodo_desde>"`, mensual, calculada en `api.php` acción `save` — es un concepto distinto de la numeración del documento y no se ha tocado. Verificado: tras el cambio, generar dos veces recibos para el mismo contrato+mes sigue devolviendo "0 nuevos, ya existentes" en el segundo intento.
- **Archivos modificados:** `assets/js/helpers.js` (`nextNumeroDoc()`: un único `TIPOS_DOC = ['REC','RER','FAC','RET']`, todos exigen `AAAA`), `assets/js/generar.js` (`periodoDoc = String(anyo)` en vez de `periodoYYYYMM()`), `assets/js/recibos-cobro.js` (`saveRecibo()`: `periodo = String(anyo)`; `anularRecibo()`: el año del `RER` sale de `r.periodo_desde` del original, con `r.fecha_emision` como respaldo documentado si falta, y error explícito — nunca fecha actual — si ninguno de los dos es válido), `assets/php/api.php` (`nextNumeroDoc`: mismo `$TIPOS_DOC` unificado y validación `/^20\d{2}$/` única; nueva auto-migración idempotente que inicializa el contador anual de instalaciones existentes para los 4 tipos, ver más abajo), `assets/php/install.php` (recibos de ejemplo con `periodo_desde`/`periodo_hasta` poblados; nueva tanda de recibos en formato anual con secuencia continua entre meses; recibos históricos de dic-2025/ene-2026/feb-2026 se mantienen deliberadamente en formato mensual antiguo como demostración de compatibilidad; ejemplo `RER` recalculado al nuevo formato anual; siembra de `doc_secuencias` ampliada con una tercera consulta para `REC`/`RER` anual).
- **Migración de instalaciones existentes (reales, no solo datos de ejemplo):** nueva auto-migración en `api.php`, idempotente (se ejecuta en cada arranque pero solo actúa sobre pares tipo+año que aún no tengan fila anual), que inicializa el `ultimo` de cada `(tipo, año)` nuevo como `GREATEST(A, B)` donde `A = SUM(doc_secuencias.ultimo)` de las filas mensuales de ese año (el ledger de reservas real) y `B = COUNT(*)` de documentos reales de ese tipo/año (`recibos`/`facturas`, vía `periodo_desde`/`fecha_emision`, nunca parseando `numero_recibo`/`numero_factura`). Se usa el mayor de los dos deliberadamente: nunca se asume que el ledger de `doc_secuencias` por sí solo es exacto (podría tener reservas "fantasma" de documentos que se reservaron y nunca se guardaron) ni que el recuento de documentos reales por sí solo lo es (podría haber una fila de `doc_secuencias` incompleta) — se cruzan ambas fuentes en vez de fiarse de una sola. Extendida también a `FAC`/`RET` por consistencia (mismo mecanismo, cierra una laguna que ya existía desde la migración anterior: instalaciones reales con facturas mensuales históricas tampoco tenían antes una migración automática de arranque).
- **No se renumeró ningún documento histórico:** los recibos con formato mensual antiguo (`REC-202512-00001`, etc.) conservan su número, enlaces, PDFs y cobros exactamente igual. Los dos formatos (`AAAAMM` de 6 dígitos y `AAAA` de 4) nunca colisionan textualmente (el segundo guión cae en una posición distinta) y ambos se filtran/listan/imprimen con normalidad — confirmado con una reinstalación de ejemplo que deja ambos formatos coexistiendo a propósito.
- **Pruebas:** ejecutadas vía API directa (`fetch` autenticado) y vía interfaz real. Confirmado: secuencia anual `REC` continua entre marzo-julio 2026 sin reinicio mensual; rechazo `400` de formato `AAAAMM` para `REC`/`RER`; independencia total entre las 4 series (llamadas a `REC`/`RER` no alteran los contadores de `FAC`/`RET` y viceversa); salto de año (`REC-2027-00001` tras `REC-2026-…`, contador independiente); anulación de un recibo de junio de 2026 generó `RER-2026-00003` (saltando un número ya reservado por una prueba directa anterior, nunca reutilizado); anulación de un recibo de diciembre de 2025 generó `RER-2025-00001`, confirmando el cruce de año; `periodo_key` siguió bloqueando la regeneración duplicada del mismo contrato+mes tras el cambio; 0 errores de consola inesperados durante toda la sesión de pruebas.
- **Sin comunicaciones externas:** ninguna prueba de esta sesión envió emails, WhatsApp ni llamadas reales a la AEAT/VERI*FACTU — solo datos de ejemplo y llamadas a `nextNumeroDoc`/anulación dentro de la propia base de datos local.

### 2026-07-11 — Corrección de previsualización de Plantillas en modo oscuro
- **Problema**: la vista previa del módulo Plantillas usaba colores hardcodeados (`<mark style="background:#d1fae5">` etc.) generados en `assets/php/plantillas.php`, ajenos al sistema de variables CSS — ilegible en modo oscuro.
- **Causa raíz adicional**: bug latente en el propio sistema de tokens (`--color-success`/`--color-error`/`--color-brand`/`--color-warn` se definían en `:root` como alias `var(--green)` etc. sin redeclararse en `body.dark`, por lo que se congelaban en su valor de modo claro para cualquier descendiente).
- **Archivos modificados**: `assets/php/plantillas.php`, `assets/js/plantillas.js`, `assets/css/main.css`, `AlquiGest.php` (versión de caché).
- **Clases CSS nuevas**: `.tpl-preview`, `.tpl-preview-mark-ok/-placeholder/-error`, `.tpl-preview-legend*`, `.tpl-preview-warning`, `.tpl-preview-error-msg`, `.tpl-foto-caption`.
- **Accesibilidad**: corregido un contraste insuficiente (4.00:1) en la marca de variable resuelta en modo oscuro, ahora 6.12:1.
- **Sin cambios en**: generación real del DOCX (verificado que sigue produciendo ficheros válidos), escapado de variables (sin XSS).
- Nota: el resto de colores hardcodeados fuera de Plantillas (~150 casos en 16 archivos JS) siguen pendientes — ver §21 y §25.

### 2026-07-10 — Corrección de generación individual de recibos + auditoría general
- Ver el detalle completo en la sección dedicada [«Corrección de generación individual de recibos de períodos anteriores»](#corrección-de-generación-individual-de-recibos-de-períodos-anteriores) y en [§25](#25-auditoría-de-julio-2026--hallazgos-y-estado).
- **Resumen:** unificado el período (mes/año) del recibo individual y masivo en una única fuente de verdad; corregido el *shadowing* de `periodo` en `generarLote()`; la numeración documental ya no puede caer silenciosamente en la fecha actual del servidor; nueva protección real de duplicados a nivel de base de datos (`recibos.periodo_key`, `UNIQUE KEY`, libre de condiciones de carrera); nuevas validaciones de backend (contrato existente, vigente y activo) para la generación de recibos; corregido un bug que bloqueaba el alta/edición de inmuebles; cerrado el vector de CSRF vía GET en `save`/`delete`; `login.php` restringido a localhost; nuevos índices de rendimiento.
- **Archivos modificados:** `assets/js/helpers.js`, `assets/js/recibos-cobro.js`, `assets/js/generar.js`, `assets/php/api.php`, `assets/php/install.php`, `login.php`, `AlquiGest.php` (versión de caché).
- **Migraciones:** nueva columna `recibos.periodo_key` + `UNIQUE KEY uq_recibos_periodo_key` (idempotente, no destructiva — los recibos existentes quedan con `periodo_key = NULL` y no participan retroactivamente en la protección); nuevos índices `idx_recibos_fecha_emision`/`idx_recibos_periodo_desde`. Revertir: `ALTER TABLE recibos DROP INDEX uq_recibos_periodo_key, DROP COLUMN periodo_key, DROP INDEX idx_recibos_fecha_emision, DROP INDEX idx_recibos_periodo_desde`.
- **Documentación:** consolidados `ANALISIS_BORRADO_LOGICO_E_INTEGRIDAD.md`, `ANALISIS_GLOBAL_MEJORAS_ALQUIGEST.md` y `ANALISIS_USUARIOS_SEGURIDAD_PERMISOS.md` en este README (y eliminados como ficheros aparte); corregidas referencias obsoletas a `AlquiGest_v2`, recuento de variables de plantilla y estructura de archivos.

### 2026-07-10 — Sistema de usuarios y permisos
- **Funcionalidad**: autenticación real (login/logout), roles `admin`/`user`, protección de `install.php` según rol, gestión de usuarios, atribución de usuario en el log de actividad.
- **Archivos añadidos**: `assets/php/auth.php`, `login.php`, `assets/js/usuarios.js`.
- **Archivos modificados**: `AlquiGest.php`, `assets/php/api.php`, `assets/php/install.php`, `assets/php/plantillas.php`, `assets/php/verifactu.php`, `assets/php/email.php`, `assets/php/export.php`, `assets/php/import.php`, `assets/php/pdf_download.php`, `assets/js/helpers.js`, `assets/js/ux.js`, `assets/js/actividad.js`, `assets/js/dashboard.js`, `assets/css/main.css`.
- **Tablas nuevas/modificadas**: `usuarios` (nueva); `log_actividad` (+`usuario_id`, `usuario_nombre`, `usuario_username`, `usuario_rol`, `ip`).
- **Migraciones**: ambas son `CREATE TABLE IF NOT EXISTS` / `ALTER TABLE ADD COLUMN` idempotentes, aplicadas automáticamente al primer acceso tras actualizar (`api.php`) y también sembradas en `install.php` para instalaciones nuevas. Ninguna es destructiva; `usuarios` y `log_actividad` quedan además excluidas del `DROP TABLE` de una reinstalación limpia/con ejemplos.
- **Riesgos conocidos**: sin límite de intentos de login, sin recuperación de contraseña por email (ver [§23](#23-autenticación-usuarios-y-permisos)).
- **Compatibilidad**: instalaciones existentes migran solas (sin usuarios) al estado "primera instalación" — el primer acceso tras actualizar pedirá crear el administrador antes de continuar.

### 2026-07-09 — Borrado lógico e integridad referencial
- Fin del borrado físico para propietarios/fincas/inmuebles/inquilinos/plantillas (ahora lógico, columna `eliminado`/`eliminado_en`) y bloqueo total de borrado físico para contratos/recibos/facturas (ya tenían su propio ciclo de vida por `estado` — ver [§13](#13-contratos) y [§14](#14-recibos-y-cobros)). El backend rechaza cualquier intento de borrado físico de estas tres entidades aunque se llame directamente a la API.

---

*Versión: 3.0.1 · Última actualización: 2026-07-10*  
*Documentación de usuario: `assets/docs/ayuda.php`*  
*Este README.md es la única documentación propia del proyecto (los análisis técnicos previos se consolidaron aquí — ver [§26](#26-registro-de-cambios)).*
