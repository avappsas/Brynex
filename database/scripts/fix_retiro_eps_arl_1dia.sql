-- ============================================================
--  FIX: Retiros 1 día — EPS 2300 → 2400 / ARL 400 → 300
--  SQL Server
--  Fecha: 2026-05-27
--
--  CONTEXTO:
--    Las facturas de retiro (numero_factura = 0) con 1 día cotizado
--    tenían redondeo incorrecto ("doble-ceil") que provocaba:
--      • EPS old-code (round): 2,300  → correcto es 2,400
--      • ARL new-code (ceil):    400  → correcto es   300
--
--  La tabla planos NO tiene columnas v_eps/v_arl propias;
--  las lee vía JOIN de facturas, por eso solo se actualiza facturas.
--
--  INSTRUCCIONES:
--    1. Ejecutar primero el bloque PASO 1 (solo SELECTs) para revisar.
--    2. Si los conteos y datos son correctos, ejecutar PASO 2.
--    3. El PASO 2 está envuelto en transacción con ROLLBACK de seguridad.
-- ============================================================


-- ============================================================
--  PASO 1 — DIAGNÓSTICO (solo lectura, sin cambios)
-- ============================================================

-- 1A. Facturas con EPS=2300 en retiros de 1 día
SELECT
    f.id,
    f.aliado_id,
    f.cedula,
    f.dias_cotizados,
    f.v_eps,
    f.v_arl,
    f.total_ss,
    f.total,
    f.fecha_pago,
    f.anio,
    f.mes
FROM facturas f
WHERE f.numero_factura = 0          -- factura de retiro (no es ingreso)
  AND f.dias_cotizados = 1          -- retiro de exactamente 1 día
  AND f.v_eps = 2300                -- valor incorrecto (debería ser 2400)
  AND f.deleted_at IS NULL
ORDER BY f.fecha_pago DESC;

-- 1B. Facturas con ARL=400 en retiros de 1 día
SELECT
    f.id,
    f.aliado_id,
    f.cedula,
    f.dias_cotizados,
    f.v_eps,
    f.v_arl,
    f.total_ss,
    f.total,
    f.fecha_pago,
    f.anio,
    f.mes
FROM facturas f
WHERE f.numero_factura = 0
  AND f.dias_cotizados = 1
  AND f.v_arl = 400                 -- valor incorrecto (debería ser 300)
  AND f.deleted_at IS NULL
ORDER BY f.fecha_pago DESC;

-- 1C. Resumen de cuántos registros se van a modificar
SELECT
    SUM(CASE WHEN v_eps = 2300 THEN 1 ELSE 0 END) AS facturas_eps_2300_a_corregir,
    SUM(CASE WHEN v_arl = 400  THEN 1 ELSE 0 END) AS facturas_arl_400_a_corregir
FROM facturas
WHERE numero_factura = 0
  AND dias_cotizados = 1
  AND (v_eps = 2300 OR v_arl = 400)
  AND deleted_at IS NULL;


-- ============================================================
--  PASO 2 — CORRECCIÓN (ejecutar solo si el PASO 1 se ve bien)
-- ============================================================

BEGIN TRANSACTION;

BEGIN TRY

    -- ── 2A. Corregir EPS: 2300 → 2400  (+100 en total_ss) ──────────
    UPDATE facturas
    SET
        v_eps    = 2400,
        total_ss = total_ss + 100
        -- NOTA: total = 0 en retiros (cliente no paga), no se toca.
    WHERE numero_factura = 0
      AND dias_cotizados = 1
      AND v_eps = 2300
      AND deleted_at IS NULL;

    PRINT CAST(@@ROWCOUNT AS VARCHAR) + ' facturas corregidas: EPS 2300 → 2400 (+100 en total_ss)';

    -- ── 2B. Corregir ARL: 400 → 300  (-100 en total_ss) ────────────
    UPDATE facturas
    SET
        v_arl    = 300,
        total_ss = total_ss - 100
    WHERE numero_factura = 0
      AND dias_cotizados = 1
      AND v_arl = 400
      AND deleted_at IS NULL;

    PRINT CAST(@@ROWCOUNT AS VARCHAR) + ' facturas corregidas: ARL 400 → 300 (-100 en total_ss)';

    -- ── 2C. Verificación post-update ────────────────────────────────
    SELECT
        SUM(CASE WHEN v_eps = 2300 THEN 1 ELSE 0 END) AS eps_2300_restantes,   -- debe ser 0
        SUM(CASE WHEN v_arl = 400  THEN 1 ELSE 0 END) AS arl_400_restantes,    -- debe ser 0
        SUM(CASE WHEN v_eps = 2400 THEN 1 ELSE 0 END) AS eps_2400_ok,
        SUM(CASE WHEN v_arl = 300  THEN 1 ELSE 0 END) AS arl_300_ok
    FROM facturas
    WHERE numero_factura = 0
      AND dias_cotizados = 1
      AND deleted_at IS NULL;

    COMMIT TRANSACTION;
    PRINT 'Transacción completada exitosamente.';

END TRY
BEGIN CATCH
    ROLLBACK TRANSACTION;
    PRINT 'ERROR — se hizo ROLLBACK. Mensaje: ' + ERROR_MESSAGE();
    THROW;
END CATCH;
