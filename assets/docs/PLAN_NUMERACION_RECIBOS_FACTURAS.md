# Plan de Numeración de Recibos y Facturas con Reinicio Mensual
> Versión: 1.0 | Fecha: 2026-06-30 | Estado: ANÁLISIS COMPLETO — pendiente de aprobación

---

## 1. Objetivo

Cambiar el comportamiento del contador secuencial de recibos y facturas para que se **reinicie a 1 al comenzar cada mes**, manteniendo la estructura de formato `REC-AAAAMM-NNNNN` y `FAC-AAAAMM-NNNNN`.

### Comportamiento actual
```
REC-202604-00031
REC-202605-00032   ← el contador CONTINÚA entre meses
REC-202606-00033
```

### Comportamiento deseado
```
REC-202604-00031   ← recibos existentes NO se tocan
REC-202605-00001   ← primer recibo de mayo → reinicia a 1
REC-202605-00002
REC-202606-00001   ← primer recibo de junio → vuelve a 1
```

**Documentos existentes: NO se modifican.** Solo cambia la generación de nuevos documentos.

---

## 2. Hallazgos del análisis

### 2.1 Arquitectura actual

La app es una **SPA con BD vía API REST genérica** (`api.php?action=save`). El servidor no genera numeración: recibe los datos ya construidos desde el frontend y ejecuta un INSERT genérico. La numeración se calcula **íntegramente en JavaScript** con los datos ya cargados en memoria (`DB.get('recibos')`).

### 2.2 Dónde se genera el número de recibo — 4 puntos distintos

| Fichero | Función/Contexto | Método actual | Problema |
|---------|-----------------|---------------|----------|
| `helpers.js:201` | `getNumRecibo(n)` | Recibe `n` externo, aplica formato YYYYMM de **hoy** | Base de la generación; `n` viene de MAX global |
| `recibos.js:62` | Modal desde contrato | `Math.max(...recibos.map(r => r.numero_seq))` global | No filtra por mes |
| `recibos.js:248` | `modalNuevoReciboLibre()` | Ídem | No filtra por mes |
| `recibos.js:300` | `actualizarFormNuevoRecibo()` | Ídem | No filtra por mes |
| `generar.js:188` | Generación masiva | **`DB.get('recibos').length + 1`** | BUG ADICIONAL: usa longitud, no MAX |

### 2.3 Dónde se genera el número de factura — 3 puntos

| Fichero | Función/Contexto | Método actual | Estado |
|---------|-----------------|---------------|--------|
| `facturas.js:19` | `generarNumeroFacturaDesdeRecibo()` | Filtra por prefijo del mes → `MAX` mensual | **Ya correcto** ✅ |
| `facturas.js:237` | Facturas rectificativas (RECT) | Filtra por prefijo del mes → `MAX` mensual | **Ya correcto** ✅ |
| `install.php:619` | Datos de ejemplo | Bucle incremental `$seq++` | Solo para demo |

### 2.4 Hallazgo importante sobre facturas

**Las facturas YA implementan reinicio mensual correctamente.** `generarNumeroFacturaDesdeRecibo()` filtra `DB.get('facturas')` por el prefijo `FAC-YYYYMM-` del mes actual y calcula el MAX de ese subconjunto. El cambio solicitado **solo afecta a los recibos** en cuanto a comportamiento, aunque la implementación debe unificarse.

### 2.5 El bug de `generar.js`

```javascript
// Código actual — INCORRECTO
const seq = (DB.get('recibos').length + 1).toString().padStart(5,'0');
```

Este código usa el **número de registros** en lugar del **MAX de `numero_seq`**. Si hay recibos eliminados, o si se generan recibos de meses pasados, el número calculado es incorrecto. Este bug se corrige en la solución propuesta.

### 2.6 Estado actual de la BD

```
recibos.numero_recibo   VARCHAR(50)           — sin índice UNIQUE
recibos.numero_seq      INT DEFAULT 0         — contador global
facturas.numero_factura VARCHAR(50) NOT NULL  — tiene UNIQUE KEY
facturas.numero_seq     INT DEFAULT NULL      — contador mensual (ya correcto)
```

**No existe tabla de secuencias** en la BD. La numeración es completamente calculada en JS.

### 2.7 Concurrencia actual

Aunque la app es localhost-only (un solo usuario), la solución actual tiene una **ventana de race condition teórica**:

1. JS lee todos los recibos y calcula `MAX + 1`
2. Envía INSERT al servidor
3. Servidor ejecuta INSERT sin comprobación de unicidad (no hay UNIQUE en `recibos.numero_recibo`)

Si dos sesiones del navegador generan un recibo simultáneamente (p.ej., dos pestañas), ambas calculan el mismo MAX+1 y se insertan dos recibos con el mismo número. Para facturas esto falla con error de BD (tiene UNIQUE), para recibos pasa silenciosamente.

---

## 3. Alternativas analizadas

### Opción A — Frontend puro: filtrar por prefijo de mes (como facturas)

**Descripción:** Replicar el patrón de `generarNumeroFacturaDesdeRecibo()` para recibos. En lugar de `Math.max(numero_seq global)`, filtrar por `numero_recibo.startsWith('REC-YYYYMM-')` y calcular el MAX de ese subconjunto.

```javascript
// Ejemplo de implementación
const prefijo = `${prefix}-${yyyymm}-`;
const recibosDelMes = DB.get('recibos').filter(r => r.numero_recibo?.startsWith(prefijo));
const maxSeq = recibosDelMes.reduce((max, r) => {
  const seq = parseInt(r.numero_recibo.slice(prefijo.length), 10) || 0;
  return Math.max(max, seq);
}, 0);
const nextN = maxSeq + 1;
```

**Ventajas:**
- Mínimos cambios de código
- Sin modificaciones en BD ni en el servidor
- Consistente con lo que ya hace `facturas.js`

**Inconvenientes:**
- Sigue siendo `MAX()` calculado en JS → race condition teórica persiste
- Si se elimina el último recibo del mes, el número se "reutilizaría" (no hay UNIQUE en recibos)
- No hay trazabilidad de la secuencia en BD

### Opción B — Tabla de secuencias en BD con bloqueo atómico

**Descripción:** Crear una tabla `doc_secuencias` y un endpoint PHP que devuelva el siguiente número de forma atómica mediante `INSERT … ON DUPLICATE KEY UPDATE … LAST_INSERT_ID()`.

```sql
CREATE TABLE doc_secuencias (
    tipo    VARCHAR(20) NOT NULL,
    periodo CHAR(6)     NOT NULL,
    ultimo  INT         NOT NULL DEFAULT 0,
    PRIMARY KEY (tipo, periodo)
) ENGINE=InnoDB;
```

```php
INSERT INTO doc_secuencias (tipo, periodo, ultimo) VALUES (?, ?, 1)
ON DUPLICATE KEY UPDATE ultimo = LAST_INSERT_ID(ultimo + 1);
SELECT LAST_INSERT_ID();  -- devuelve el número reservado
```

**Ventajas:**
- Atómico: imposible duplicar aunque 100 usuarios generen simultáneamente
- Centralizado: una sola función PHP para todos los tipos de documento
- Extensible: funciona sin cambios para Presupuestos, Abonos, Contratos, etc.
- La secuencia queda registrada en BD (auditoría)
- Un número consumido nunca se reutiliza, aunque el documento sea cancelado

**Inconvenientes:**
- Requiere nueva tabla en BD y migración
- Requiere nuevo endpoint PHP
- Requiere que JS llame al servidor antes de mostrar el número final
- Un número consumido si el usuario cancela el modal crea un "hueco" en la secuencia (comportamiento correcto y esperado en sistemas contables)

### Opción C — Generación del número en el servidor al momento del INSERT

**Descripción:** El servidor genera el `numero_recibo` en el momento del INSERT, sin que el JS tenga que calcularlo ni pedirlo previamente.

**Inconvenientes:**
- Requiere romper el API genérico de `save` (actualmente recibe el número como dato)
- El usuario no vería el número en el formulario antes de guardar
- Mayor impacto en el código existente

---

## 4. Solución propuesta: Opción B

### Justificación

La **Opción B** (tabla de secuencias con bloqueo atómico) es la elección correcta por las siguientes razones:

1. **Robustez real:** `LAST_INSERT_ID()` con `ON DUPLICATE KEY UPDATE` es la técnica estándar de MySQL para secuencias atómicas. Es imposible generar duplicados.

2. **Centralización:** Un único servicio (`nextNumeroDoc`) sirve para recibos, facturas, rectificativas y cualquier tipo futuro. La lógica deja de estar duplicada en 5 sitios del código.

3. **Trazabilidad:** La tabla `doc_secuencias` registra el último número emitido por tipo y período. Permite auditorías y diagnósticos.

4. **Huecos aceptables:** En sistemas contables serios (SAGE, ContaPlus, Holded), los números consumidos por operaciones canceladas crean huecos en la secuencia. Esto es correcto y esperado. La Ley no exige secuencia continua, exige que no haya duplicados.

5. **Compatibilidad total:** El endpoint es asíncrono (Promise), lo que se integra sin fricción con la arquitectura JS existente que ya usa `async/await` y `DB.save()`.

6. **Extensibilidad:** Para añadir "Presupuestos" en el futuro basta con llamar a `nextNumeroDoc('PRE', periodo, 'PRE')`.

---

## 5. Diseño técnico detallado

### 5.1 Nueva tabla `doc_secuencias`

```sql
CREATE TABLE IF NOT EXISTS `doc_secuencias` (
  `tipo`    VARCHAR(20) NOT NULL COMMENT 'REC, FAC, RECT, PRE...',
  `periodo` CHAR(6)     NOT NULL COMMENT 'YYYYMM del periodo de emision',
  `ultimo`  INT         NOT NULL DEFAULT 0 COMMENT 'ultimo seq emitido en este periodo',
  PRIMARY KEY (`tipo`, `periodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Secuencias por tipo de documento y periodo mensual';
```

**Por qué InnoDB:** soporta transacciones y bloqueos a nivel de fila, necesarios para la atomicidad.

**Inicialización de datos existentes:** Al crear la tabla, se debe poblar con los máximos actuales para no colisionar con numeración existente:

```sql
-- Poblar con los máximos actuales de recibos
INSERT INTO doc_secuencias (tipo, periodo, ultimo)
SELECT
  SUBSTRING_INDEX(numero_recibo, '-', 1) AS tipo,
  SUBSTRING(numero_recibo, LOCATE('-', numero_recibo) + 1, 6) AS periodo,
  MAX(numero_seq) AS ultimo
FROM recibos
WHERE numero_recibo REGEXP '^[A-Z]+-[0-9]{6}-[0-9]+$'
GROUP BY tipo, periodo
ON DUPLICATE KEY UPDATE ultimo = GREATEST(ultimo, VALUES(ultimo));

-- Poblar con los máximos actuales de facturas
INSERT INTO doc_secuencias (tipo, periodo, ultimo)
SELECT
  SUBSTRING_INDEX(numero_factura, '-', 1) AS tipo,
  SUBSTRING(numero_factura, LOCATE('-', numero_factura) + 1, 6) AS periodo,
  MAX(numero_seq) AS ultimo
FROM facturas
WHERE numero_factura REGEXP '^[A-Z]+-[0-9]{6}-[0-9]+$'
GROUP BY tipo, periodo
ON DUPLICATE KEY UPDATE ultimo = GREATEST(ultimo, VALUES(ultimo));
```

### 5.2 Nuevo índice UNIQUE en recibos

```sql
ALTER TABLE recibos
  ADD UNIQUE KEY `uq_recibos_numero_recibo` (`numero_recibo`);
```

Actualmente las facturas ya tienen este índice, los recibos no. Esto garantiza que ningún duplicado entre en BD incluso si el JS tuviera un bug.

### 5.3 Nueva función PHP en `api.php` — acción `nextNumeroDoc`

```php
if ($action === 'nextNumeroDoc') {
    // Solo carácteres seguros
    $tipo    = preg_replace('/[^A-Z]/', '', strtoupper($_GET['tipo']    ?? ''));
    $periodo = preg_replace('/[^0-9]/', '',              $_GET['periodo'] ?? date('Ym'));
    $prefijo = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_GET['prefijo'] ?? $tipo));

    if (!$tipo || strlen($periodo) !== 6) {
        json_out(['error' => 'Parámetros inválidos'], 400);
    }

    // Operación atómica: reservar el siguiente número
    // Si no existe la fila para este tipo+periodo, la crea con ultimo=1
    // Si existe, incrementa ultimo y retorna el valor incrementado via LAST_INSERT_ID
    $pdo->prepare(
        "INSERT INTO doc_secuencias (tipo, periodo, ultimo) VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE ultimo = LAST_INSERT_ID(ultimo + 1)"
    )->execute([$tipo, $periodo]);

    $seq = (int)$pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();

    // Para la inserción nueva (LAST_INSERT_ID=0 en MySQL 5.x con INSERT sin ON DUPLICATE)
    // obtenemos el valor directamente si es necesario
    if ($seq === 0) {
        $seq = (int)$pdo->prepare(
            "SELECT ultimo FROM doc_secuencias WHERE tipo = ? AND periodo = ?"
        )->execute([$tipo, $periodo]) ? (int)$pdo->query(
            "SELECT ultimo FROM doc_secuencias WHERE tipo = '$tipo' AND periodo = '$periodo'"
        )->fetchColumn() : 1;
    }

    $numero = $prefijo . '-' . $periodo . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
    json_out(['seq' => $seq, 'numero' => $numero, 'tipo' => $tipo, 'periodo' => $periodo]);
}
```

> **Nota técnica MySQL:** Con `INSERT … ON DUPLICATE KEY UPDATE ultimo = LAST_INSERT_ID(ultimo + 1)`, cuando hay colisión (UPDATE), `LAST_INSERT_ID()` devuelve el nuevo valor de `ultimo`. Cuando es INSERT nuevo, `LAST_INSERT_ID()` devuelve el AUTO_INCREMENT de la última inserción (que en tablas sin AUTO_INCREMENT es 0 o 1 según versión). Por eso se añade el fallback con SELECT.

**Versión limpia y compatible con MySQL 5.7 / 8.0 / MariaDB:**

```php
if ($action === 'nextNumeroDoc') {
    $tipo    = preg_replace('/[^A-Z]/', '', strtoupper($_GET['tipo']    ?? ''));
    $periodo = preg_replace('/[^0-9]/', '',              $_GET['periodo'] ?? date('Ym'));
    $prefijo = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_GET['prefijo'] ?? $tipo));

    if (!$tipo || strlen($periodo) !== 6) {
        json_out(['error' => 'Parámetros inválidos'], 400);
    }

    // Transacción + SELECT FOR UPDATE: garantiza atomicidad sin depender de LAST_INSERT_ID
    $pdo->beginTransaction();
    try {
        // Bloquea la fila exclusivamente durante esta transacción
        $stmt = $pdo->prepare(
            "SELECT ultimo FROM doc_secuencias WHERE tipo = ? AND periodo = ? FOR UPDATE"
        );
        $stmt->execute([$tipo, $periodo]);
        $fila = $stmt->fetch();

        if ($fila === false) {
            // Primera secuencia para este tipo+periodo
            $pdo->prepare(
                "INSERT INTO doc_secuencias (tipo, periodo, ultimo) VALUES (?, ?, 1)"
            )->execute([$tipo, $periodo]);
            $seq = 1;
        } else {
            $seq = (int)$fila['ultimo'] + 1;
            $pdo->prepare(
                "UPDATE doc_secuencias SET ultimo = ? WHERE tipo = ? AND periodo = ?"
            )->execute([$seq, $tipo, $periodo]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        json_out(['error' => 'Error generando secuencia: ' . $e->getMessage()], 500);
    }

    $numero = $prefijo . '-' . $periodo . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
    json_out(['seq' => $seq, 'numero' => $numero, 'tipo' => $tipo, 'periodo' => $periodo]);
}
```

**Por qué `SELECT FOR UPDATE` en lugar de `ON DUPLICATE KEY UPDATE + LAST_INSERT_ID`:**
- `SELECT FOR UPDATE` bloquea la fila durante la transacción → ninguna otra conexión puede leer ni modificar esa fila hasta que se hace COMMIT
- Es más explícito, más portable, más fácil de entender y depurar
- Funciona igual en MySQL 5.7, 8.0 y MariaDB
- No hay ambigüedad con `LAST_INSERT_ID()` en tablas sin AUTO_INCREMENT

### 5.4 Nueva función JS en `helpers.js`

```javascript
// Obtiene el siguiente número de documento del servidor (atómico, sin duplicados).
// tipo: 'REC', 'FAC', 'RECT', etc.
// periodo: 'YYYYMM' — si se omite, usa el mes actual
// prefijo: prefijo del número (si se omite, usa el tipo)
// Retorna: { seq: 42, numero: 'REC-202606-00042' }
async function nextNumeroDoc(tipo, periodo, prefijo) {
  const p = periodo || (new Date().getFullYear() + String(new Date().getMonth()+1).padStart(2,'0'));
  const pref = prefijo || tipo;
  const url = `assets/php/api.php?action=nextNumeroDoc&tipo=${encodeURIComponent(tipo)}&periodo=${encodeURIComponent(p)}&prefijo=${encodeURIComponent(pref)}`;
  const res = await fetch(url);
  if (!res.ok) throw new Error('Error al generar número de documento');
  return await res.json();  // { seq, numero, tipo, periodo }
}
```

`getNumRecibo(n)` en `helpers.js` se mantiene como función de **display** (para mostrar el número estimado mientras el usuario abre el modal), pero el número definitivo se obtiene de `nextNumeroDoc` en el momento de guardar.

### 5.5 Cambios en `recibos.js`

**Función `saveRecibo()` (el único punto que realmente persiste el recibo):**

```javascript
async function saveRecibo() {
  // ...validaciones existentes...

  // Obtener número definitivo del servidor (atómico)
  const empresa = DB.getEmpresa();
  const prefix  = empresa?.prefijo_recibos || 'REC';
  const fechaEmision = data.fecha_emision || new Date().toISOString().slice(0, 10);
  const periodo = fechaEmision.replace(/-/g, '').slice(0, 6);

  let seqInfo;
  try {
    seqInfo = await nextNumeroDoc('REC', periodo, prefix);
  } catch (e) {
    toast('Error al generar número de recibo. Inténtalo de nuevo.', 'error');
    return;
  }

  data.numero_recibo = seqInfo.numero;
  data.numero_seq    = seqInfo.seq;

  // ...resto del INSERT existente...
}
```

**Número estimado en el modal (UX):** El modal puede seguir mostrando un número provisional calculado en JS (como "REC-202606-00060 (provisional)"), que se reemplaza por el definitivo en el momento de guardar. Esto mantiene la buena experiencia de usuario.

**Alternativa más limpia:** Mostrar solo el campo "Se asignará automáticamente" y actualizar el campo tras guardar. Menos riesgo de confusión.

### 5.6 Cambios en `generar.js` (generación masiva)

```javascript
// Por cada recibo en el bucle:
const empresa = DB.getEmpresa();
const prefix  = empresa?.prefijo_recibos || 'REC';
const periodo = String(anyo) + String(mes).padStart(2, '0');

let seqInfo;
try {
  seqInfo = await nextNumeroDoc('REC', periodo, prefix);
} catch (e) {
  toast(`Error al generar número para recibo ${n}/${total}`, 'error');
  continue;  // o break, según estrategia de error
}

// seqInfo.numero = 'REC-202606-00042'
// seqInfo.seq    = 42
```

Esto también corrige el **bug actual** de `DB.get('recibos').length + 1`.

### 5.7 Cambios en `facturas.js`

`generarNumeroFacturaDesdeRecibo()` pasa de ser síncrona a asíncrona:

```javascript
async function generarNumeroFacturaDesdeRecibo() {
  const hoy     = new Date().toISOString().split('T')[0];
  const periodo = hoy.replace(/-/g, '').slice(0, 6);
  return await nextNumeroDoc('FAC', periodo, 'FAC');
  // retorna { seq, numero } igual que antes
}
```

Las facturas rectificativas (RECT):

```javascript
const hoy     = new Date().toISOString().split('T')[0];
const periodo = hoy.replace(/-/g, '').slice(0, 6);
const seqInfo = await nextNumeroDoc('RECT', periodo, 'RECT');
const numRect = seqInfo.numero;
const sigSeq  = seqInfo.seq;
```

### 5.8 Ordenación por `numero_seq`

Actualmente varios sitios ordenan recibos por `numero_seq` (que es un contador global). Con el cambio, `numero_seq` pasa a ser **mensual** (1..N dentro de cada mes). La ordenación correcta sería por `numero_recibo` (lexicográfico) o por `fecha_emision + numero_seq`.

La cadena `REC-202606-00042` ordena lexicográficamente de forma correcta ya que tiene YYYYMM delante. Por tanto:

```javascript
// Antes
.sort((a, b) => (a.numero_seq || 0) - (b.numero_seq || 0))

// Después
.sort((a, b) => (a.numero_recibo || '').localeCompare(b.numero_recibo || ''))
```

---

## 6. Archivos afectados

| Archivo | Tipo de cambio | Impacto |
|---------|---------------|---------|
| `assets/php/api.php` | Añadir acción `nextNumeroDoc` + lógica con transacción | Medio |
| `assets/php/install.php` | Crear tabla `doc_secuencias` en schema + poblar con datos existentes | Medio |
| `assets/js/helpers.js` | Añadir `async nextNumeroDoc()` | Bajo |
| `assets/js/recibos.js` | `saveRecibo()` → obtener número de servidor | Medio |
| `assets/js/generar.js` | Loop de generación masiva → obtener número de servidor (corrige bug) | Medio |
| `assets/js/facturas.js` | `generarNumeroFacturaDesdeRecibo()` → async | Bajo |
| `assets/js/facturas.js` | Rectificativas → `nextNumeroDoc('RECT', ...)` | Bajo |
| `assets/sql/` | Nueva migración: `003_doc_secuencias.sql` | Nueva |
| `AlquiGest.php` | Cache buster del JS que se modifique | Bajo |

---

## 7. Cambios en Base de Datos

### 7.1 Nueva migración: `assets/sql/migrations/003_doc_secuencias.sql`

```sql
-- Migración 003: secuencias de numeración por tipo y periodo
-- Ejecutar SOLO UNA VEZ. Idempotente (IF NOT EXISTS).

-- 1. Crear tabla de secuencias
CREATE TABLE IF NOT EXISTS `doc_secuencias` (
  `tipo`    VARCHAR(20) NOT NULL COMMENT 'REC=Recibos, FAC=Facturas, RECT=Rectificativas...',
  `periodo` CHAR(6)     NOT NULL COMMENT 'Periodo YYYYMM',
  `ultimo`  INT         NOT NULL DEFAULT 0 COMMENT 'Ultimo numero de secuencia emitido',
  PRIMARY KEY (`tipo`, `periodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Secuencias de numeracion mensual por tipo de documento';

-- 2. Poblar con los maximos actuales de recibos
-- (para no colisionar con numeracion existente)
INSERT INTO doc_secuencias (tipo, periodo, ultimo)
SELECT
  SUBSTRING_INDEX(numero_recibo, '-', 1)                       AS tipo,
  SUBSTRING(numero_recibo, LOCATE('-', numero_recibo) + 1, 6)  AS periodo,
  MAX(COALESCE(numero_seq, 0))                                  AS ultimo
FROM recibos
WHERE numero_recibo REGEXP '^[A-Z]+-[0-9]{6}-'
GROUP BY
  SUBSTRING_INDEX(numero_recibo, '-', 1),
  SUBSTRING(numero_recibo, LOCATE('-', numero_recibo) + 1, 6)
ON DUPLICATE KEY UPDATE ultimo = GREATEST(ultimo, VALUES(ultimo));

-- 3. Poblar con los maximos actuales de facturas
INSERT INTO doc_secuencias (tipo, periodo, ultimo)
SELECT
  SUBSTRING_INDEX(numero_factura, '-', 1)                        AS tipo,
  SUBSTRING(numero_factura, LOCATE('-', numero_factura) + 1, 6)  AS periodo,
  MAX(COALESCE(numero_seq, 0))                                    AS ultimo
FROM facturas
WHERE numero_factura REGEXP '^[A-Z]+-[0-9]{6}-'
GROUP BY
  SUBSTRING_INDEX(numero_factura, '-', 1),
  SUBSTRING(numero_factura, LOCATE('-', numero_factura) + 1, 6)
ON DUPLICATE KEY UPDATE ultimo = GREATEST(ultimo, VALUES(ultimo));

-- 4. Indice UNIQUE en recibos.numero_recibo
-- (facturas ya lo tiene: uq_facturas_numero_factura)
ALTER TABLE recibos
  ADD UNIQUE KEY `uq_recibos_numero_recibo` (`numero_recibo`);
```

---

## 8. Riesgos y mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|-----------|
| Rollback de transacción: el seq queda consumido | Baja | Bajo | Hueco en secuencia = correcto en contabilidad. No se reutiliza. |
| Restauración de backup: la tabla `doc_secuencias` queda desfasada | Baja | Medio | Tras restaurar, ejecutar query de repoblación del punto 7.1 |
| Cambio de año (ej: 202512 → 202601) | Nula | Ninguno | El periodo es YYYYMM → el sistema crea automáticamente una nueva fila para 202601 |
| Cambio de mes concurrente (dos recibos al mismo tiempo) | Muy baja (localhost) | Ninguno | `SELECT FOR UPDATE` garantiza atomicidad |
| Generación masiva de cientos de recibos | Posible | Ninguno | El loop hace llamadas secuenciales → cada una atómica. Ligero overhead de red (localhost ≈ <1ms) |
| Eliminación de recibo: el número no se reutiliza | — | Ninguno | El contador de BD no retrocede. Correcto. |
| Pérdida de conexión entre `nextNumeroDoc` y el INSERT | Muy baja | Bajo | El seq queda consumido, el recibo no se crea. El usuario reintenta → número siguiente. |
| `install.php` datos de ejemplo: secuencias desfasadas | Baja | Bajo | El install.php también pobla `doc_secuencias` en la instalación demo |
| Prefijo configurable (`prefijo_recibos` en empresa) | Existente | Bajo | `nextNumeroDoc` recibe el prefijo como parámetro — completamente flexible |

---

## 9. Casos de prueba

### 9.1 Cambio de mes
- [ ] Crear el último recibo de mayo (ej: `REC-202605-00125`)
- [ ] Crear el primer recibo de junio → debe ser `REC-202606-00001`
- [ ] Verificar que `doc_secuencias` tiene fila `(REC, 202605, 125)` y `(REC, 202606, 1)`

### 9.2 Cambio de año
- [ ] Crear recibo en diciembre (`REC-202512-00043`)
- [ ] Crear recibo en enero siguiente → debe ser `REC-202601-00001`
- [ ] Verificar fila `(REC, 202601, 1)` en `doc_secuencias`

### 9.3 Concurrencia (dos pestañas)
- [ ] Abrir dos pestañas en el modal de nuevo recibo
- [ ] Guardar desde ambas simultáneamente
- [ ] Verificar que los números son distintos (ej: 00001 y 00002)
- [ ] Verificar que NO hay recibos duplicados en BD

### 9.4 Generación masiva
- [ ] Generar 5 contratos activos → generar recibos en lote para junio
- [ ] Verificar que se crean 5 recibos con números consecutivos `00001`–`00005`
- [ ] Volver a generar para julio → comienzan desde `00001`

### 9.5 Recibo manual + lote en el mismo mes
- [ ] Crear manualmente `REC-202606-00001`
- [ ] Ejecutar generación masiva → deben comenzar desde `00002`

### 9.6 Factura desde recibo
- [ ] Generar factura desde un recibo de junio
- [ ] Verificar que el número es `FAC-202606-0000X` (reinicio correcto)

### 9.7 Factura rectificativa
- [ ] Anular factura → se crea `RECT-YYYYMM-00001` (primera del mes)
- [ ] Anular otra factura el mismo mes → `RECT-YYYYMM-00002`

### 9.8 Eliminación de recibo
- [ ] Crear `REC-202606-00001` y `REC-202606-00002`
- [ ] Eliminar `REC-202606-00001`
- [ ] Crear nuevo recibo → debe ser `REC-202606-00003` (no reutiliza el 00001)

### 9.9 Rollback / cancelación
- [ ] Abrir modal de nuevo recibo
- [ ] Cancelar (no guardar)
- [ ] El siguiente recibo creado debe tener el número correcto (sin hueco si no se llegó a llamar a `nextNumeroDoc`, con hueco si sí se llamó)

### 9.10 Restauración de backup
- [ ] Restaurar un backup antiguo
- [ ] Ejecutar el query de repoblación de `doc_secuencias`
- [ ] Crear nuevo recibo → número correcto (sin colisión con los existentes)

### 9.11 Documentos históricos intactos
- [ ] Verificar que `REC-202604-00031` (ejemplo existente) no ha cambiado
- [ ] Verificar que el número de recibo anterior no es modificable desde el formulario de edición

### 9.12 Prefijo configurable
- [ ] Cambiar `prefijo_recibos` en Mi Empresa de "REC" a "ALQ"
- [ ] Crear recibo → debe ser `ALQ-202606-00001`
- [ ] Los recibos anteriores con "REC-" siguen intactos

---

## 10. Checklist de implementación

### Análisis
- [x] Analizar generación de numeración actual de recibos
- [x] Analizar generación de numeración actual de facturas
- [x] Identificar todos los puntos del código que generan número
- [x] Revisar esquema de BD (tablas, índices, constraints)
- [x] Evaluar opciones de solución (A, B, C)
- [x] Diseñar arquitectura de la solución elegida
- [x] Identificar todos los archivos afectados
- [x] Documentar riesgos y mitigaciones
- [x] Definir casos de prueba

### Decisiones de diseño validadas
- [x] **Modal:** mostrar "Se asignará automáticamente" — sin número provisional
- [x] **Ordenación:** cambiar sorts de `numero_seq` a `numero_recibo` (lexicográfico)

### Implementación
- [x] Crear migración SQL `003_doc_secuencias.sql`
- [x] Ejecutar migración en la BD de desarrollo
- [x] Añadir acción `nextNumeroDoc` en `api.php`
- [x] Añadir función `nextNumeroDoc()` en `helpers.js`
- [x] Actualizar `saveRecibo()` en `recibos-cobro.js` (módulo activo)
- [x] Actualizar `modalGenerarRecibo()` y `modalNuevoReciboLibre()` en `recibos-cobro.js`
- [x] Actualizar `actualizarFormNuevoRecibo()` en `recibos-cobro.js` (sin número provisional)
- [x] Corregir bug de generación masiva en `generar.js`
- [x] Hacer `generarNumeroFacturaDesdeRecibo()` async en `facturas.js`
- [x] Actualizar rectificativas (`anularFactura`) en `facturas.js`
- [x] Actualizar `install.php` para crear `doc_secuencias` y poblarla
- [x] Actualizar ordenaciones de `numero_seq` por `numero_recibo` (recibos-pdf.js, email.js)
- [x] Actualizar cache buster en `AlquiGest.php` (v=20260630a)

### Verificación
- [ ] Ejecutar todos los casos de prueba (sección 9)
- [ ] Verificar que los documentos históricos no han cambiado
- [ ] Verificar concurrencia (dos pestañas simultáneas)
- [ ] Verificar generación masiva
- [ ] Verificar cambio de mes
- [ ] Verificar cambio de año
- [ ] Verificar que no hay duplicados en BD

### Documentación
- [ ] Actualizar `AI_PROJECT_CONTEXT.md` con los cambios
- [x] Actualizar este documento marcando el progreso

---

## 11. Pregunta al usuario antes de implementar

Existen dos decisiones de diseño UX que requieren tu validación:

### Decisión 1: Número provisional en el modal

**Opción A:** El modal muestra el número calculado en JS como estimación `"REC-202606-00060 (provisional)"`. El número definitivo se confirma al guardar y puede diferir en 1-2 unidades si hay actividad concurrente.

**Opción B:** El modal llama al servidor al abrirse y muestra el número definitivo reservado. Si el usuario cancela, ese número queda consumido (hueco en la secuencia).

**Opción C:** El modal muestra `"Se asignará automáticamente"`. El número aparece solo en el recibo ya guardado.

**Recomendación:** Opción A — es la de menor impacto, la más usable y consistente con el comportamiento actual.

### Decisión 2: Semántica de `numero_seq` para recibos

Con el cambio, `numero_seq` pasará a ser mensual (1..N por mes). Esto afecta a los ordenamientos que actualmente usan `numero_seq` para ordenar todos los recibos de forma cronológica.

**Opción A:** Cambiar los sorts de `numero_seq` a `numero_recibo` (lexicográfico). Funciona perfectamente porque `REC-YYYYMM-NNNNN` ordena bien.

**Opción B:** Mantener `numero_seq` como contador global para ordenar, y usar un campo separado `seq_mensual` para el nuevo contador. Más complejo, no recomendado.

**Recomendación:** Opción A — `numero_recibo` ordena igual de bien y es más semántico.

---

*Pendiente de validación por el usuario antes de comenzar la implementación.*
