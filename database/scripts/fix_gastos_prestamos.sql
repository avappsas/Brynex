-- ═══════════════════════════════════════════════════════════════════
-- ANÁLISIS: gastos migrados que parecen préstamos
-- ═══════════════════════════════════════════════════════════════════

-- 1. Ver distribución de tipos actuales en gastos
SELECT tipo, COUNT(*) AS total, SUM(valor) AS valor_total
FROM gastos
GROUP BY tipo
ORDER BY total DESC;

-- 2. Ver gastos que parecen préstamos (por descripción)
SELECT id, aliado_id, fecha, tipo, descripcion, valor, observacion
FROM gastos
WHERE tipo LIKE '%prest%'
   OR descripcion LIKE '%prestamo%'
   OR descripcion LIKE '%préstamo%'
ORDER BY fecha DESC;

-- ═══════════════════════════════════════════════════════════════════
-- OPCIÓN A: Si los préstamos legacy ya fueron cobrados (histórico)
-- Dejarlos como otro_admin para que aparezcan en egresos históricos
-- ═══════════════════════════════════════════════════════════════════
-- UPDATE gastos
-- SET tipo = 'otro_admin'
-- WHERE tipo LIKE '%prest%';

-- ═══════════════════════════════════════════════════════════════════
-- OPCIÓN B: Eliminar de gastos los préstamos legacy (si ya están
-- como facturas estado='prestamo' en el nuevo sistema)
-- ═══════════════════════════════════════════════════════════════════
-- DELETE FROM gastos
-- WHERE (tipo LIKE '%prest%' OR descripcion LIKE '%prestamo%')
--   AND id_legacy IS NOT NULL;  -- Solo los migrados, no los nuevos

-- ═══════════════════════════════════════════════════════════════════
-- VERIFICAR facturas con estado='prestamo' (módulo BryNex)
-- ═══════════════════════════════════════════════════════════════════
SELECT aliado_id,
       COUNT(*) AS prestamos_activos,
       SUM(total) AS total_prestado
FROM facturas
WHERE estado = 'prestamo'
  AND deleted_at IS NULL
GROUP BY aliado_id;
