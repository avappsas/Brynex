-- =========================================================
-- FIX: Actualizar tipo_doc en planos históricos
-- Todos los planos tienen 'CC' hardcodeado, pero el tipo
-- real del cliente puede ser CE, PT, PA, TI, etc.
--
-- Lógica:
--   JOIN planos → clientes por (cedula = no_identifi AND aliado_id)
--   Solo actualiza cuando el tipo_doc del cliente es diferente a 'CC'
--   y no está vacío.
-- =========================================================

-- 1) PREVIEW: ver cuántos registros serían afectados y cuáles
SELECT
    p.id          AS plano_id,
    p.aliado_id,
    p.no_identifi AS cedula,
    p.primer_nombre,
    p.primer_ape,
    p.tipo_doc    AS tipo_doc_actual,
    cl.tipo_doc   AS tipo_doc_correcto,
    p.mes_plano,
    p.anio_plano
FROM planos p
INNER JOIN clientes cl
    ON  CAST(cl.cedula AS VARCHAR(20)) = CAST(p.no_identifi AS VARCHAR(20))
    AND cl.aliado_id = p.aliado_id
WHERE p.deleted_at IS NULL
  AND p.tipo_doc = 'CC'
  AND cl.tipo_doc IS NOT NULL
  AND LTRIM(RTRIM(cl.tipo_doc)) <> ''
  AND UPPER(LTRIM(RTRIM(cl.tipo_doc))) <> 'CC'
ORDER BY p.aliado_id, p.no_identifi;


-- =========================================================
-- 2) UPDATE: aplicar la corrección
--    EJECUTAR SOLO DESPUÉS DE REVISAR EL PREVIEW ANTERIOR
-- =========================================================
UPDATE p
SET
    p.tipo_doc   = UPPER(LTRIM(RTRIM(cl.tipo_doc))),
    p.updated_at = GETDATE()
FROM planos p
INNER JOIN clientes cl
    ON  CAST(cl.cedula AS VARCHAR(20)) = CAST(p.no_identifi AS VARCHAR(20))
    AND cl.aliado_id = p.aliado_id
WHERE p.deleted_at IS NULL
  AND p.tipo_doc = 'CC'
  AND cl.tipo_doc IS NOT NULL
  AND LTRIM(RTRIM(cl.tipo_doc)) <> ''
  AND UPPER(LTRIM(RTRIM(cl.tipo_doc))) <> 'CC';

-- Ver cuántos registros fueron actualizados:
-- SQL Server devuelve automáticamente el conteo de filas afectadas.
