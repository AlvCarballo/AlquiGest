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
24. [Registro de cambios](#24-registro-de-cambios)

---

## 1. Descripción del proyecto

AlquiGest es un sistema de gestión de alquileres diseñado para administradores de fincas y propietarios que gestionan varios inmuebles. Permite llevar el control completo del ciclo de vida de un alquiler: desde el alta del propietario hasta el cobro mensual de los recibos y la emisión de facturas con cumplimiento legal.

**Características principales:**
- Gestión multi-propietario y multi-finca desde una única aplicación
- Generación automática de recibos mensuales individuales y masivos
- Motor de plantillas Word con 42 variables dinámicas
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
| `doc_secuencias` | Contador atómico de numeración mensual por tipo de documento (`REC`, `FAC`, `RET`, `RER`) |
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

1. Copia la carpeta `AlquiGest_v2/` en el directorio web:
   - MAMP: `C:\MAMP\htdocs\AlquiGest_v2\`
   - XAMPP: `C:\xampp\htdocs\AlquiGest_v2\`

2. Inicia Apache y MySQL desde el panel de MAMP/XAMPP.

3. Abre en el navegador: `http://localhost/AlquiGest_v2/assets/php/install.php`

4. Elige una opción:
   - **Instalación limpia** — BD vacía lista para producción
   - **Instalación con datos de ejemplo** — carga datos de prueba

5. Crea el primer administrador cuando `install.php` te lo pida (nombre, usuario, email, contraseña) — ver [§23](#23-autenticación-usuarios-y-permisos).

6. Accede a la aplicación: `http://localhost/AlquiGest_v2/AlquiGest.php` e inicia sesión con el administrador creado.

> `install.php` puede ejecutarse de nuevo para aplicar migraciones (nuevas columnas) sin destruir datos existentes. Solo destruye datos si eliges "instalación limpia" o "con datos de ejemplo" (ambas opciones recrean las tablas de negocio desde cero; las tablas `usuarios` y `log_actividad` nunca se destruyen en una reinstalación). Tras la primera instalación, `install.php` exige haber iniciado sesión como administrador para volver a acceder a él (salvo la sección de copia de seguridad, disponible también para el rol `user`).

### Qué incluye cada modo

Ambos modos siembran siempre lo **obligatorio para que la aplicación funcione** desde el primer arranque, sin necesitar configuración manual:
- Las 6 plantillas DOCX de contrato/inventario (normal y multi-inquilino) en la tabla `plantillas`.
- Los 76 parámetros de `configuracion` con sus valores por defecto (paginación, visibilidad de botones y menús, Dashboard, WhatsApp, VERI*FACTU, documentos).
- La estructura completa de tablas, incluida `doc_secuencias` para la numeración atómica.

La **instalación limpia** no crea ningún propietario, finca, inmueble, inquilino, contrato, recibo ni factura — la BD queda vacía y lista para datos reales.

La **instalación con datos de ejemplo** añade además un escenario completo de prueba: propietarios/fincas/inmuebles/inquilinos/contratos/recibos de varios meses (incluido un salto de año en la numeración), facturas normales y rectificativas (`RET`), recibos anulados con y sin factura (incluido un `RER`), un contrato finalizado, **4 contratos con los distintos casos de revisión de renta** (pendiente / próxima / ya aplicada / lejana — ver sección 16) y **3 contratos con inquilinos secundarios** (0, 1 y 2 secundarios, para poder comparar).

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
AlquiGest_v2/
├── AlquiGest.php           ← Punto de entrada principal (SPA shell, exige sesión)
├── login.php               ← Pantalla de login
├── index.php               ← Pantalla de bienvenida / instalación
├── README.md               ← Este documento
│
├── assets/
│   ├── css/
│   │   └── main.css        ← Hoja de estilos principal (modo claro + oscuro)
│   ├── js/
│   │   ├── config.js       ← Constantes y configuración JS
│   │   ├── helpers.js      ← Utilidades globales y navegación SPA
│   │   ├── dashboard.js    ← Dashboard y widgets
│   │   ├── propietarios.js ← Módulo propietarios
│   │   ├── fincas.js       ← Módulo fincas
│   │   ├── inmuebles.js    ← Módulo inmuebles
│   │   ├── inquilinos.js   ← Módulo inquilinos
│   │   ├── contratos.js    ← Módulo contratos (incl. fiador, inq. sec.)
│   │   ├── contratos-pdf.js← PDF de contratos y fianza
│   │   ├── recibos-lista.js← Listado y filtros de recibos
│   │   ├── recibos-cobro.js← Modal de cobro y gestión de pagos
│   │   ├── recibos-pdf.js  ← PDF e impresión de recibos
│   │   ├── generar.js      ← Generación masiva de recibos
│   │   ├── facturas.js     ← Módulo facturas
│   │   ├── informes.js     ← Exportación Excel e informes IRPF
│   │   ├── email.js        ← Envío por correo (Gmail)
│   │   ├── configuracion.js← Parámetros y configuración
│   │   ├── plantillas.js   ← Motor de plantillas DOCX (UI + lógica)
│   │   ├── verifactu.js    ← VERI*FACTU (UI)
│   │   ├── actividad.js    ← Log de actividad (con columna/filtro de usuario)
│   │   ├── usuarios.js     ← Gestión de usuarios (solo admin)
│   │   ├── ux.js           ← Modales, toasts, temas, búsqueda global, menú de usuario/sesión
│   │   ├── extras.js       ← Funciones auxiliares
│   │   ├── tabla.js        ← Componente de tabla reutilizable
│   │   ├── notificaciones.js← Campana de notificaciones
│   │   └── vendor/         ← Librerías locales (sin CDN)
│   │       ├── chart.umd.min.js
│   │       ├── html2canvas.min.js
│   │       └── jspdf.umd.min.js
│   ├── php/
│   │   ├── api.php         ← API REST principal (JSON); exige sesión salvo login/check
│   │   ├── auth.php        ← Autenticación, sesión, CSRF, roles, log de actividad
│   │   ├── install.php     ← Instalador y migrador de BD; acceso según rol (ver §23)
│   │   ├── plantillas.php  ← Motor DOCX backend (ZipArchive + OOXML)
│   │   └── verifactu.php   ← Backend VERI*FACTU (SHA-256, SOAP, AEAT)
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
├── uploads/
│   └── plantillas/         ← Archivos DOCX subidos (UUID en disco)
│
└── certs/                  ← Certificados digitales VERI*FACTU (.p12/.pfx)
```

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
| Plantillas DOCX | Motor de plantillas Word: 42 variables, bloques repetitivos, tabla de fotos |
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

**Sistema:** `{{FechaActual}}` · `{{AnioActual}}` · `{{MesActual}}`

### Bloque de inquilinos secundarios

```
{{#INQUILINOS_SECUNDARIOS}}
{{InqNombre}}, con NIF {{InqNIF}}, domiciliado en {{InqDireccion}}.
{{/INQUILINOS_SECUNDARIOS}}
```

Variables dentro del bloque: `{{InqNombre}}` · `{{InqNIF}}` · `{{InqDireccion}}` · `{{InqTelefono}}` · `{{InqEmail}}`

Si el contrato no tiene inquilinos secundarios, el bloque completo desaparece del documento.

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

### Numeración

`{PREFIJO}-{AAAAMM}-{NNNNN}` — Ejemplo: `REC-202601-00001`. El prefijo se configura en Mi Empresa. El secuencial se reinicia cada mes (y por tanto también al cambiar de año). La reserva del número es atómica: la genera el servidor (`nextNumeroDoc`, tabla `doc_secuencias`), nunca se calcula solo en el navegador.

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

- **Recibo pendiente, sin factura:** se anula directamente y se genera un **recibo rectificativo** `RER-AAAAMM-NNNNN` con los importes en negativo (a 0€, ya que no había ningún cobro que compensar).
- **Recibo cobrado (total o parcialmente), sin factura:** antes de anular, el sistema pregunta explícitamente si se quiere devolver el cobro (*"El recibo ya está cobrado. Para anularlo es necesario devolver el cobro en el recibo rectificativo..."*). Si se cancela, no se modifica nada. Si se acepta, se genera el `RER-AAAAMM-NNNNN` con el cobro reflejado en negativo (campos `importe_pagado` y `pagos`), dejando trazabilidad completa de la devolución.
- **Recibo con factura EMITIDA:** ya no basta con anular el recibo dejando la factura intacta. El sistema pregunta (*"Este recibo tiene una factura emitida asociada. Para anular el recibo es necesario anular también la factura y generar su factura rectificativa..."*). Si se cancela, no se modifica nada (ni recibo, ni factura, ni cobros). Si se acepta:
  1. Se anula primero la factura asociada, generando su factura rectificativa `RET-AAAAMM-NNNNN` (misma lógica que anular una factura desde el módulo Facturas — código reutilizado, no duplicado).
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
- Numeración correlativa única: `FAC-AAAAMM-NNNNN`
- Inmutabilidad: no se editan tras emisión
- Anulación lógica: la factura original pasa a estado "rectificada" (se conserva íntegra) y se genera automáticamente una **factura rectificativa** con la nueva numeración `RET-AAAAMM-NNNNN` (ej. `RET-202607-00001`), con todos los importes negados, conforme al art. 15 del RD 1619/2012
- Si VERI*FACTU está activo: la factura rectificativa también se registra ante la AEAT como cualquier otra factura

### Acciones por factura en la tabla

Igual que en Recibos, cada fila muestra una acción principal — **Imprimir / PDF** (emitida) o **Ver** (rectificada/anulada, estados cerrados sin más acciones posibles) — y un menú **Más ▾** con Email (Comunicación), Ver recibo origen y envío/estado AEAT (Documentos y VERI*FACTU), y Anular factura (Gestión, solo si está emitida).

### Factura rectificativa vs. recibo rectificativo

| Documento | Cuándo se genera | Numeración | Efecto |
|-----------|-------------------|------------|--------|
| Factura rectificativa (`RET`) | Al anular una factura ya emitida — desde el módulo Facturas, o en cascada al anular un recibo con factura emitida asociada | `RET-AAAAMM-NNNNN` | Cancela fiscalmente la factura original (importes negados); la original queda "rectificada" |
| Recibo rectificativo (`RER`) | Al anular un recibo que **no** tiene factura emitida asociada | `RER-AAAAMM-NNNNN` | Compensa el recibo original en los totales internos (importes negados, incluido el cobro si lo había); no tiene efecto fiscal ante la AEAT |

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
| Sin transacción SQL real en la cascada "anular recibo con factura" (son 2-3 guardados secuenciales desde JS, igual que el resto del proyecto) | El orden está protegido (la factura se rectifica antes que el recibo; si falla, el recibo no se toca), pero un corte de red justo entre pasos podría dejar el `RET` creado sin completar el resto — caso raro, sin reversión automática |

### Bugs conocidos sin corregir

Ninguno pendiente por ahora. El bug de colores hardcodeados en la previsualización de Plantillas (`assets/php/plantillas.php` generaba `<mark style="background:#d1fae5">` fuera del sistema de variables CSS, sin adaptarse al modo oscuro) se corrigió el 2026-07-11 — ver `UX_UI_MODO_OSCURO_COLORES.md` § Corrección de previsualización de Plantillas en modo oscuro.

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

Desde la v3.0.1, AlquiGest exige iniciar sesión para usar la aplicación. Análisis completo, matriz de permisos y pruebas realizadas en `ANALISIS_USUARIOS_SEGURIDAD_PERMISOS.md`; resumen operativo aquí.

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

## 24. Registro de cambios

### 2026-07-11 — Corrección de previsualización de Plantillas en modo oscuro
- **Problema**: la vista previa del módulo Plantillas usaba colores hardcodeados (`<mark style="background:#d1fae5">` etc.) generados en `assets/php/plantillas.php`, ajenos al sistema de variables CSS — ilegible en modo oscuro.
- **Causa raíz adicional**: bug latente en el propio sistema de tokens (`--color-success`/`--color-error`/`--color-brand`/`--color-warn` se definían en `:root` como alias `var(--green)` etc. sin redeclararse en `body.dark`, por lo que se congelaban en su valor de modo claro para cualquier descendiente).
- **Archivos modificados**: `assets/php/plantillas.php`, `assets/js/plantillas.js`, `assets/css/main.css`, `AlquiGest.php` (versión de caché).
- **Clases CSS nuevas**: `.tpl-preview`, `.tpl-preview-mark-ok/-placeholder/-error`, `.tpl-preview-legend*`, `.tpl-preview-warning`, `.tpl-preview-error-msg`, `.tpl-foto-caption`.
- **Accesibilidad**: corregido un contraste insuficiente (4.00:1) en la marca de variable resuelta en modo oscuro, ahora 6.12:1.
- **Sin cambios en**: generación real del DOCX (verificado que sigue produciendo ficheros válidos), escapado de variables (sin XSS).
- Detalle completo en `UX_UI_MODO_OSCURO_COLORES.md`.

### 2026-07-10 — Sistema de usuarios y permisos
- **Funcionalidad**: autenticación real (login/logout), roles `admin`/`user`, protección de `install.php` según rol, gestión de usuarios, atribución de usuario en el log de actividad.
- **Archivos añadidos**: `assets/php/auth.php`, `login.php`, `assets/js/usuarios.js`.
- **Archivos modificados**: `AlquiGest.php`, `assets/php/api.php`, `assets/php/install.php`, `assets/php/plantillas.php`, `assets/php/verifactu.php`, `assets/php/email.php`, `assets/php/export.php`, `assets/php/import.php`, `assets/php/pdf_download.php`, `assets/js/helpers.js`, `assets/js/ux.js`, `assets/js/actividad.js`, `assets/js/dashboard.js`, `assets/css/main.css`.
- **Tablas nuevas/modificadas**: `usuarios` (nueva); `log_actividad` (+`usuario_id`, `usuario_nombre`, `usuario_username`, `usuario_rol`, `ip`).
- **Migraciones**: ambas son `CREATE TABLE IF NOT EXISTS` / `ALTER TABLE ADD COLUMN` idempotentes, aplicadas automáticamente al primer acceso tras actualizar (`api.php`) y también sembradas en `install.php` para instalaciones nuevas. Ninguna es destructiva; `usuarios` y `log_actividad` quedan además excluidas del `DROP TABLE` de una reinstalación limpia/con ejemplos.
- **Riesgos conocidos**: sin límite de intentos de login, sin recuperación de contraseña por email (ver detalle en `ANALISIS_USUARIOS_SEGURIDAD_PERMISOS.md` §12).
- **Compatibilidad**: instalaciones existentes migran solas (sin usuarios) al estado "primera instalación" — el primer acceso tras actualizar pedirá crear el administrador antes de continuar.

### 2026-07-09 — Borrado lógico e integridad referencial
- Ver detalle completo en `ANALISIS_BORRADO_LOGICO_E_INTEGRIDAD.md`. Resumen: fin del borrado físico para propietarios/fincas/inmuebles/inquilinos/plantillas (ahora lógico, columna `eliminado`) y bloqueo total de borrado físico para contratos/recibos/facturas (ya tenían su propio ciclo de vida por `estado`).

---

*Versión: 3.0.1 · Última actualización: julio 2026*  
*Documentación de usuario: `assets/docs/ayuda.php`*
*Análisis técnicos: `ANALISIS_BORRADO_LOGICO_E_INTEGRIDAD.md` · `ANALISIS_USUARIOS_SEGURIDAD_PERMISOS.md` · `UX_UI_MODO_OSCURO_COLORES.md`*
