-- =============================================================================
-- FIX: Actualizar estado_pago + estado de incapacidades ya migradas
-- que tienen Pagado='si' en el legacy pero quedaron como 'pendiente' en BryNex
--
-- Ejecutar en el servidor SQL Server de BryNex (conexión principal).
-- Es IDEMPOTENTE: puede ejecutarse múltiples veces sin daño.
-- =============================================================================

-- ─── 1. DIAGNÓSTICO PREVIO ───────────────────────────────────────────────────
-- Ver cuántas incapacidades migradas tienen estado_pago='pendiente'
-- pero el legacy las marca como pagadas (cross-DB query)
-- Descomenta este bloque para diagnóstico antes de corregir:
/*
SELECT
    b.id,
    b.id_legacy,
    b.aliado_id,
    b.estado,
    b.estado_pago,
    b.cedula_usuario
FROM incapacidades b
WHERE b.id_legacy IS NOT NULL
  AND b.estado_pago = 'pendiente'
  AND b.deleted_at IS NULL;
*/

-- =============================================================================
-- ─── 2. CORRECCIÓN DIRECTA CROSS-DATABASE ────────────────────────────────────
-- Actualiza las incapacidades de BryNex consultando directamente el legacy.
-- Reemplaza [Brygar_BD] con el nombre real de tu BD legacy si es diferente.
-- =============================================================================

PRINT '── Inicio corrección incapacidades ──────────────────────────────────';

-- ─── 2a. Crear tabla temporal con datos de pago del legacy ───────────────────
IF OBJECT_ID('tempdb..#legacy_incap_pago') IS NOT NULL
    DROP TABLE #legacy_incap_pago;

CREATE TABLE #legacy_incap_pago (
    id_legacy      INT          NOT NULL,
    pagado_raw     NVARCHAR(50) NULL,
    valor_pago_raw DECIMAL(14,2) NULL,
    fecha_pago_raw DATE         NULL
);

-- Insertar desde CADA base de datos legacy (una por aliado)
-- Ajusta los nombres de BD según tus aliados registrados en dbs[]

-- ── Aliado 1: Brygar_BD ───────────────────────────────────────────────────────
INSERT INTO #legacy_incap_pago (id_legacy, pagado_raw, valor_pago_raw, fecha_pago_raw)
SELECT
    CAST(Id AS INT)                                                      AS id_legacy,
    LOWER(LTRIM(RTRIM(ISNULL(Pagado, ISNULL(Pagado_Si, '')))))          AS pagado_raw,
    CASE WHEN ISNUMERIC(ISNULL(Valor_Pago, ISNULL(Valor_Pagado, Valor))) = 1
         THEN CAST(ISNULL(Valor_Pago, ISNULL(Valor_Pagado, Valor)) AS DECIMAL(14,2))
         ELSE NULL END                                                    AS valor_pago_raw,
    CASE WHEN ISDATE(ISNULL(Fecha_Pago, Fecha_Pagado)) = 1
         THEN CAST(ISNULL(Fecha_Pago, Fecha_Pagado) AS DATE)
         ELSE NULL END                                                    AS fecha_pago_raw
FROM [Brygar_BD].dbo.Incapacidades
WHERE Id IS NOT NULL;

-- Si tienes más BDs legacy, agrega bloques similares aquí:
-- INSERT INTO #legacy_incap_pago ...
-- FROM [OtraDB].dbo.Incapacidades ...

PRINT CONCAT('  Legacy rows cargadas: ', @@ROWCOUNT);

-- ─── 2b. Corregir estado_pago + estado + valor_pago + fecha_pago ─────────────
UPDATE b
SET
    b.estado_pago = CASE
        WHEN l.pagado_raw IN ('si','1','true','pagado','pagado_afiliado','paid') THEN 'pagado_afiliado'
        WHEN l.pagado_raw IN ('autorizado','autorized')                          THEN 'autorizado'
        WHEN l.pagado_raw IN ('liquidado','liquidated')                          THEN 'liquidado'
        WHEN l.pagado_raw IN ('rechazado','rejected','negado')                   THEN 'rechazado'
        ELSE b.estado_pago  -- sin cambio si el legacy no tiene dato
    END,
    -- Sincronizar estado general si el pago indica que ya está resuelto
    b.estado = CASE
        WHEN l.pagado_raw IN ('si','1','true','pagado','pagado_afiliado','paid')
             AND b.estado NOT IN ('pagado_afiliado','cerrado')             THEN 'pagado_afiliado'
        WHEN l.pagado_raw IN ('rechazado','rejected','negado')
             AND b.estado = 'recibido'                                     THEN 'rechazado'
        ELSE b.estado  -- sin cambio en los demás casos
    END,
    b.valor_pago = CASE
        WHEN l.valor_pago_raw > 0 AND b.valor_pago IS NULL THEN l.valor_pago_raw
        ELSE b.valor_pago
    END,
    b.fecha_pago = CASE
        WHEN l.fecha_pago_raw IS NOT NULL AND b.fecha_pago IS NULL THEN l.fecha_pago_raw
        ELSE b.fecha_pago
    END,
    b.updated_at = GETDATE()
FROM incapacidades b
INNER JOIN #legacy_incap_pago l ON b.id_legacy = l.id_legacy
WHERE b.id_legacy IS NOT NULL
  AND b.deleted_at IS NULL
  -- Solo actualizar las que NECESITAN cambio
  AND (
      -- estado_pago todavía en pendiente pero legacy dice pagado/rechazado
      (b.estado_pago = 'pendiente' AND l.pagado_raw IN (
          'si','1','true','pagado','pagado_afiliado','paid',
          'autorizado','autorized','liquidado','liquidated',
          'rechazado','rejected','negado'
      ))
      -- valor_pago faltante y legacy lo tiene
      OR (b.valor_pago IS NULL AND l.valor_pago_raw > 0)
      -- fecha_pago faltante y legacy la tiene
      OR (b.fecha_pago IS NULL AND l.fecha_pago_raw IS NOT NULL)
  );

DECLARE @actualizadas INT = @@ROWCOUNT;
PRINT CONCAT('  Incapacidades actualizadas: ', @actualizadas);

-- ─── 3. RESUMEN POST-CORRECCIÓN ───────────────────────────────────────────────
PRINT '── Resumen por estado_pago después de la corrección ────────────────';
SELECT
    estado_pago,
    COUNT(*) AS total
FROM incapacidades
WHERE deleted_at IS NULL
  AND id_legacy IS NOT NULL
GROUP BY estado_pago
ORDER BY total DESC;

PRINT '── Fin corrección ───────────────────────────────────────────────────';

-- Limpiar temporal
DROP TABLE IF EXISTS #legacy_incap_pago;
