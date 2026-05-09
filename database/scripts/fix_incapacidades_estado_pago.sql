-- =============================================================================
-- DIAGNÓSTICO: Distribución actual de estado_pago en incapacidades migradas
-- Ejecutar SOLO en el servidor BryNex (no requiere acceso al legacy)
-- =============================================================================

-- Ver distribución por estado_pago
SELECT
    estado_pago,
    COUNT(*) AS total
FROM incapacidades
WHERE deleted_at IS NULL
  AND id_legacy IS NOT NULL
GROUP BY estado_pago
ORDER BY total DESC;

-- Ver cuántas quedaron en 'pendiente' (candidatas a corrección)
SELECT COUNT(*) AS pendientes_migradas
FROM incapacidades
WHERE deleted_at IS NULL
  AND id_legacy IS NOT NULL
  AND estado_pago = 'pendiente';

-- Ver muestra de las pendientes (para validar antes de corregir)
SELECT TOP 20
    id,
    id_legacy,
    aliado_id,
    cedula_usuario,
    estado,
    estado_pago,
    valor_pago,
    fecha_pago,
    created_at
FROM incapacidades
WHERE deleted_at IS NULL
  AND id_legacy IS NOT NULL
  AND estado_pago = 'pendiente'
ORDER BY created_at DESC;
