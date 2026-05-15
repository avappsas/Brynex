-- ══════════════════════════════════════════════════════════════════════════
-- DIAGNÓSTICO: facturas de retiro con v_caja=0 (sin-CCF) del año 2026
-- ══════════════════════════════════════════════════════════════════════════

SELECT
    f.id            AS factura_id,
    f.cedula,
    f.anio,
    f.mes,
    f.v_eps,
    f.v_arl,
    f.v_afp,
    f.v_caja,
    f.total_ss,
    f.dias_cotizados,
    c.tipo_modalidad_id,
    c.plan_id,
    pl.incluye_caja
FROM facturas f
INNER JOIN contratos c ON c.id = f.contrato_id
LEFT  JOIN planes_contrato pl ON pl.id = c.plan_id
WHERE f.deleted_at IS NULL
  AND f.numero_factura = 0              -- retiro
  AND f.tipo = 'planilla'
  AND f.anio = 2026                     -- solo 2026
  AND f.v_caja = 0                      -- bug: debería ser 100
  AND f.dias_cotizados > 0             -- retiro real (no informativo)
  AND c.tipo_modalidad_id IN (0, 12)   -- Dependiente E / Ingreso-Retiro
  AND (
      pl.incluye_caja = 0
      OR (c.plan_id IS NULL AND c.caja_id IS NULL)
  )
ORDER BY f.mes, f.id;


-- ══════════════════════════════════════════════════════════════════════════
-- CORRECCIÓN: v_caja = 100 y total_ss += 100 — solo retiros 2026
-- ⚠️  Correr primero el SELECT de arriba para revisar los registros.
-- ══════════════════════════════════════════════════════════════════════════

BEGIN TRANSACTION;

    UPDATE f
    SET
        f.v_caja   = 100,
        f.total_ss = f.total_ss + 100
        -- f.total NO se toca: en retiro siempre es $0
    FROM facturas f
    INNER JOIN contratos c ON c.id = f.contrato_id
    LEFT  JOIN planes_contrato pl ON pl.id = c.plan_id
    WHERE f.deleted_at IS NULL
      AND f.numero_factura = 0
      AND f.tipo = 'planilla'
      AND f.anio = 2026
      AND f.v_caja = 0
      AND f.dias_cotizados > 0
      AND c.tipo_modalidad_id IN (0, 12)
      AND (
          pl.incluye_caja = 0
          OR (c.plan_id IS NULL AND c.caja_id IS NULL)
      );

    SELECT @@ROWCOUNT AS filas_actualizadas;

COMMIT;  -- cambiar a ROLLBACK si algo se ve mal


-- ══════════════════════════════════════════════════════════════════════════
-- VERIFICACIÓN: confirmar que quedaron bien
-- ══════════════════════════════════════════════════════════════════════════

SELECT
    f.id AS factura_id,
    f.cedula,
    f.anio,
    f.mes,
    f.v_eps, f.v_afp, f.v_arl,
    f.v_caja,       -- debe ser 100
    f.total_ss,
    f.dias_cotizados
FROM facturas f
INNER JOIN contratos c ON c.id = f.contrato_id
LEFT  JOIN planes_contrato pl ON pl.id = c.plan_id
WHERE f.deleted_at IS NULL
  AND f.numero_factura = 0
  AND f.tipo = 'planilla'
  AND f.anio = 2026
  AND f.v_caja = 100
  AND c.tipo_modalidad_id IN (0, 12)
ORDER BY f.mes, f.id;
