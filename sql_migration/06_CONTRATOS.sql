-- =============================================================================
-- SCRIPT 06: MIGRAR CONTRATOS
-- FKs resueltas inline:
--   COD_RAZON_SOC → razones_sociales via id_legacy
--   Asesor        → asesores via id_legacy
--   Encargado_Afiliacion → users via id_legacy
--   Eps_c/Pension_c → eps/pensiones via NIT (tablas globales)
--   ARL/Caja_Comp → arls/cajas via NIT (tablas globales)
-- =============================================================================
USE BryNex;
GO

DECLARE @id_brygar    BIGINT = (SELECT id FROM aliados WHERE nit = '901918923');
DECLARE @id_gimave    BIGINT = (SELECT id FROM aliados WHERE nombre = 'GiMave Integral');
DECLARE @id_fecop     BIGINT = (SELECT id FROM aliados WHERE nombre = 'Grupo Fecop');
DECLARE @id_luislopez BIGINT = (SELECT id FROM aliados WHERE nombre = 'Luis Lopez');
DECLARE @id_mave      BIGINT = (SELECT id FROM aliados WHERE nombre = 'Mave Anderson');
DECLARE @id_faga      BIGINT = (SELECT id FROM aliados WHERE nombre = 'SS Faga');

ALTER TABLE contratos NOCHECK CONSTRAINT ALL;

-- ── Brygar ───────────────────────────────────────────────────────────────────
PRINT '🔵 Brygar_BD → contratos...';
INSERT INTO contratos (
    aliado_id, id_legacy, cedula, estado,
    razon_social_id, asesor_id, encargado_id,
    eps_id, pension_id, arl_id, caja_id, n_arl,
    cargo, fecha_ingreso, fecha_retiro, fecha_arl, fecha_created,
    salario, administracion, admon_asesor, costo_afiliacion, seguro,
    np, envio_planilla, fecha_probable_pago, modo_probable_pago,
    observacion, observacion_afiliacion, observacion_llamada,
    motivo_afiliacion, motivo_retiro,
    tipo_modalidad_id, actividad_economica_id,
    radicado_eps, radicado_arl, radicado_caja, radicado_pension,
    created_at, updated_at
)
SELECT
    @id_brygar,
    l.Id,
    l.Cedula,
    LOWER(LTRIM(RTRIM(ISNULL(l.Estado,'')))),
    -- razon_social_id: match por id_legacy dentro del mismo aliado
    (SELECT TOP 1 id FROM razones_sociales WHERE aliado_id=@id_brygar AND id_legacy=l.COD_RAZON_SOC),
    -- asesor_id
    (SELECT TOP 1 id FROM asesores WHERE aliado_id=@id_brygar AND id_legacy=l.Asesor),
    -- encargado_id (usuario)
    (SELECT TOP 1 id FROM users WHERE aliado_id=@id_brygar AND id_legacy=l.Encargado_Afiliacion),
    -- eps/pension por NIT global
    (SELECT TOP 1 id FROM eps      WHERE nit=TRY_CAST(l.Eps_c     AS BIGINT)),
    (SELECT TOP 1 id FROM pensiones WHERE nit=TRY_CAST(l.Pension_c AS BIGINT)),
    (SELECT TOP 1 id FROM arls     WHERE nit=TRY_CAST(l.ARL       AS BIGINT)),
    (SELECT TOP 1 id FROM cajas    WHERE nit=TRY_CAST(l.Caja_Comp  AS BIGINT)),
    l.N_ARL,
    LTRIM(RTRIM(ISNULL(l.Cargo,''))),
    TRY_CAST(l.Fecha_Ingreso AS DATE),
    TRY_CAST(l.Fecha_Retiro  AS DATE),
    TRY_CAST(l.Fecha_ARL     AS DATE),
    TRY_CAST(l.Fecha_Created AS DATE),
    ISNULL(l.Salario_M,      0),
    ISNULL(l.Administracion, 0),
    ISNULL(l.admon_asesor,   0),
    ISNULL(l.costo_afiliacion,0),
    ISNULL(l.Seguro,          0),
    LTRIM(RTRIM(ISNULL(l.NP,''))),
    LTRIM(RTRIM(ISNULL(l.Envio_Planilla,''))),
    LTRIM(RTRIM(ISNULL(l.Fecha_problable_pago,''))),
    LTRIM(RTRIM(ISNULL(l.Modo_propable_pago,''))),
    LTRIM(RTRIM(ISNULL(l.Observacion,''))),
    LTRIM(RTRIM(ISNULL(l.Observacion_Afiliacion,''))),
    CAST(ISNULL(l.Observacion_llamada,'') AS NVARCHAR(MAX)),
    LTRIM(RTRIM(ISNULL(l.Motivo_Afiliacion,''))),
    LTRIM(RTRIM(ISNULL(l.Motivo_Retiro,''))),
    l.Tipo,                                                  -- tipo_modalidad_id directo
    l.Actividad_Economica,                                   -- actividad_economica_id directo
    LTRIM(RTRIM(ISNULL(l.Radicado_EPS,''))),
    LTRIM(RTRIM(ISNULL(l.Radicado_ARL,''))),
    LTRIM(RTRIM(ISNULL(l.Radicado_Caja,''))),
    LTRIM(RTRIM(ISNULL(l.Radicado_Pension,''))),
    GETDATE(), GETDATE()
FROM Brygar_BD.dbo.Contratos l;
PRINT '   ✅ Brygar: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' contratos';

-- ── GiMave ───────────────────────────────────────────────────────────────────
PRINT '🔵 GiMave_Integral → contratos...';
INSERT INTO contratos (aliado_id, id_legacy, cedula, estado, razon_social_id, asesor_id, encargado_id, eps_id, pension_id, arl_id, caja_id, n_arl, cargo, fecha_ingreso, fecha_retiro, fecha_arl, fecha_created, salario, administracion, admon_asesor, costo_afiliacion, seguro, np, envio_planilla, fecha_probable_pago, modo_probable_pago, observacion, observacion_afiliacion, observacion_llamada, motivo_afiliacion, motivo_retiro, tipo_modalidad_id, actividad_economica_id, radicado_eps, radicado_arl, radicado_caja, radicado_pension, created_at, updated_at)
SELECT @id_gimave, l.Id, l.Cedula, LOWER(LTRIM(RTRIM(ISNULL(l.Estado,'')))),
    (SELECT TOP 1 id FROM razones_sociales WHERE aliado_id=@id_gimave AND id_legacy=l.COD_RAZON_SOC),
    (SELECT TOP 1 id FROM asesores WHERE aliado_id=@id_gimave AND id_legacy=l.Asesor),
    (SELECT TOP 1 id FROM users WHERE aliado_id=@id_gimave AND id_legacy=l.Encargado_Afiliacion),
    (SELECT TOP 1 id FROM eps WHERE nit=TRY_CAST(l.Eps_c AS BIGINT)),
    (SELECT TOP 1 id FROM pensiones WHERE nit=TRY_CAST(l.Pension_c AS BIGINT)),
    (SELECT TOP 1 id FROM arls WHERE nit=TRY_CAST(l.ARL AS BIGINT)),
    (SELECT TOP 1 id FROM cajas WHERE nit=TRY_CAST(l.Caja_Comp AS BIGINT)),
    l.N_ARL, LTRIM(RTRIM(ISNULL(l.Cargo,''))), TRY_CAST(l.Fecha_Ingreso AS DATE), TRY_CAST(l.Fecha_Retiro AS DATE), TRY_CAST(l.Fecha_ARL AS DATE), TRY_CAST(l.Fecha_Created AS DATE),
    ISNULL(l.Salario_M,0), ISNULL(l.Administracion,0), ISNULL(l.admon_asesor,0), ISNULL(l.costo_afiliacion,0), ISNULL(l.Seguro,0),
    LTRIM(RTRIM(ISNULL(l.NP,''))), LTRIM(RTRIM(ISNULL(l.Envio_Planilla,''))), LTRIM(RTRIM(ISNULL(l.Fecha_problable_pago,''))), LTRIM(RTRIM(ISNULL(l.Modo_propable_pago,''))),
    LTRIM(RTRIM(ISNULL(l.Observacion,''))), LTRIM(RTRIM(ISNULL(l.Observacion_Afiliacion,''))), CAST(ISNULL(l.Observacion_llamada,'') AS NVARCHAR(MAX)),
    LTRIM(RTRIM(ISNULL(l.Motivo_Afiliacion,''))), LTRIM(RTRIM(ISNULL(l.Motivo_Retiro,''))),
    l.Tipo, l.Actividad_Economica,
    LTRIM(RTRIM(ISNULL(l.Radicado_EPS,''))), LTRIM(RTRIM(ISNULL(l.Radicado_ARL,''))), LTRIM(RTRIM(ISNULL(l.Radicado_Caja,''))), LTRIM(RTRIM(ISNULL(l.Radicado_Pension,''))),
    GETDATE(), GETDATE()
FROM GiMave_Integral.dbo.Contratos l;
PRINT '   ✅ GiMave: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' contratos';

-- ── Fecop ────────────────────────────────────────────────────────────────────
PRINT '🔵 Grupo_Fecop → contratos...';
INSERT INTO contratos (aliado_id, id_legacy, cedula, estado, razon_social_id, asesor_id, encargado_id, eps_id, pension_id, arl_id, caja_id, n_arl, cargo, fecha_ingreso, fecha_retiro, fecha_arl, fecha_created, salario, administracion, admon_asesor, costo_afiliacion, seguro, np, envio_planilla, fecha_probable_pago, modo_probable_pago, observacion, observacion_afiliacion, observacion_llamada, motivo_afiliacion, motivo_retiro, tipo_modalidad_id, actividad_economica_id, radicado_eps, radicado_arl, radicado_caja, radicado_pension, created_at, updated_at)
SELECT @id_fecop, l.Id, l.Cedula, LOWER(LTRIM(RTRIM(ISNULL(l.Estado,'')))),
    (SELECT TOP 1 id FROM razones_sociales WHERE aliado_id=@id_fecop AND id_legacy=l.COD_RAZON_SOC),
    (SELECT TOP 1 id FROM asesores WHERE aliado_id=@id_fecop AND id_legacy=l.Asesor),
    (SELECT TOP 1 id FROM users WHERE aliado_id=@id_fecop AND id_legacy=l.Encargado_Afiliacion),
    (SELECT TOP 1 id FROM eps WHERE nit=TRY_CAST(l.Eps_c AS BIGINT)),
    (SELECT TOP 1 id FROM pensiones WHERE nit=TRY_CAST(l.Pension_c AS BIGINT)),
    (SELECT TOP 1 id FROM arls WHERE nit=TRY_CAST(l.ARL AS BIGINT)),
    (SELECT TOP 1 id FROM cajas WHERE nit=TRY_CAST(l.Caja_Comp AS BIGINT)),
    l.N_ARL, LTRIM(RTRIM(ISNULL(l.Cargo,''))), TRY_CAST(l.Fecha_Ingreso AS DATE), TRY_CAST(l.Fecha_Retiro AS DATE), TRY_CAST(l.Fecha_ARL AS DATE), TRY_CAST(l.Fecha_Created AS DATE),
    ISNULL(l.Salario_M,0), ISNULL(l.Administracion,0), ISNULL(l.admon_asesor,0), ISNULL(l.costo_afiliacion,0), ISNULL(l.Seguro,0),
    LTRIM(RTRIM(ISNULL(l.NP,''))), LTRIM(RTRIM(ISNULL(l.Envio_Planilla,''))), LTRIM(RTRIM(ISNULL(l.Fecha_problable_pago,''))), LTRIM(RTRIM(ISNULL(l.Modo_propable_pago,''))),
    LTRIM(RTRIM(ISNULL(l.Observacion,''))), LTRIM(RTRIM(ISNULL(l.Observacion_Afiliacion,''))), CAST(ISNULL(l.Observacion_llamada,'') AS NVARCHAR(MAX)),
    LTRIM(RTRIM(ISNULL(l.Motivo_Afiliacion,''))), LTRIM(RTRIM(ISNULL(l.Motivo_Retiro,''))),
    l.Tipo, l.Actividad_Economica,
    LTRIM(RTRIM(ISNULL(l.Radicado_EPS,''))), LTRIM(RTRIM(ISNULL(l.Radicado_ARL,''))), LTRIM(RTRIM(ISNULL(l.Radicado_Caja,''))), LTRIM(RTRIM(ISNULL(l.Radicado_Pension,''))),
    GETDATE(), GETDATE()
FROM Grupo_Fecop.dbo.Contratos l;
PRINT '   ✅ Fecop: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' contratos';

-- ── LuisLopez ────────────────────────────────────────────────────────────────
PRINT '🔵 LuisLopez → contratos...';
INSERT INTO contratos (aliado_id, id_legacy, cedula, estado, razon_social_id, asesor_id, encargado_id, eps_id, pension_id, arl_id, caja_id, n_arl, cargo, fecha_ingreso, fecha_retiro, fecha_arl, fecha_created, salario, administracion, admon_asesor, costo_afiliacion, seguro, np, envio_planilla, fecha_probable_pago, modo_probable_pago, observacion, observacion_afiliacion, observacion_llamada, motivo_afiliacion, motivo_retiro, tipo_modalidad_id, actividad_economica_id, radicado_eps, radicado_arl, radicado_caja, radicado_pension, created_at, updated_at)
SELECT @id_luislopez, l.Id, l.Cedula, LOWER(LTRIM(RTRIM(ISNULL(l.Estado,'')))),
    (SELECT TOP 1 id FROM razones_sociales WHERE aliado_id=@id_luislopez AND id_legacy=l.COD_RAZON_SOC),
    (SELECT TOP 1 id FROM asesores WHERE aliado_id=@id_luislopez AND id_legacy=l.Asesor),
    (SELECT TOP 1 id FROM users WHERE aliado_id=@id_luislopez AND id_legacy=l.Encargado_Afiliacion),
    (SELECT TOP 1 id FROM eps WHERE nit=TRY_CAST(l.Eps_c AS BIGINT)),
    (SELECT TOP 1 id FROM pensiones WHERE nit=TRY_CAST(l.Pension_c AS BIGINT)),
    (SELECT TOP 1 id FROM arls WHERE nit=TRY_CAST(l.ARL AS BIGINT)),
    (SELECT TOP 1 id FROM cajas WHERE nit=TRY_CAST(l.Caja_Comp AS BIGINT)),
    l.N_ARL, LTRIM(RTRIM(ISNULL(l.Cargo,''))), TRY_CAST(l.Fecha_Ingreso AS DATE), TRY_CAST(l.Fecha_Retiro AS DATE), TRY_CAST(l.Fecha_ARL AS DATE), TRY_CAST(l.Fecha_Created AS DATE),
    ISNULL(l.Salario_M,0), ISNULL(l.Administracion,0), ISNULL(l.admon_asesor,0), ISNULL(l.costo_afiliacion,0), ISNULL(l.Seguro,0),
    LTRIM(RTRIM(ISNULL(l.NP,''))), LTRIM(RTRIM(ISNULL(l.Envio_Planilla,''))), LTRIM(RTRIM(ISNULL(l.Fecha_problable_pago,''))), LTRIM(RTRIM(ISNULL(l.Modo_propable_pago,''))),
    LTRIM(RTRIM(ISNULL(l.Observacion,''))), LTRIM(RTRIM(ISNULL(l.Observacion_Afiliacion,''))), CAST(ISNULL(l.Observacion_llamada,'') AS NVARCHAR(MAX)),
    LTRIM(RTRIM(ISNULL(l.Motivo_Afiliacion,''))), LTRIM(RTRIM(ISNULL(l.Motivo_Retiro,''))),
    l.Tipo, l.Actividad_Economica,
    LTRIM(RTRIM(ISNULL(l.Radicado_EPS,''))), LTRIM(RTRIM(ISNULL(l.Radicado_ARL,''))), LTRIM(RTRIM(ISNULL(l.Radicado_Caja,''))), LTRIM(RTRIM(ISNULL(l.Radicado_Pension,''))),
    GETDATE(), GETDATE()
FROM LuisLopez.dbo.Contratos l;
PRINT '   ✅ LuisLopez: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' contratos';

-- ── Mave ─────────────────────────────────────────────────────────────────────
PRINT '🔵 Mave_Anderson → contratos...';
INSERT INTO contratos (aliado_id, id_legacy, cedula, estado, razon_social_id, asesor_id, encargado_id, eps_id, pension_id, arl_id, caja_id, n_arl, cargo, fecha_ingreso, fecha_retiro, fecha_arl, fecha_created, salario, administracion, admon_asesor, costo_afiliacion, seguro, np, envio_planilla, fecha_probable_pago, modo_probable_pago, observacion, observacion_afiliacion, observacion_llamada, motivo_afiliacion, motivo_retiro, tipo_modalidad_id, actividad_economica_id, radicado_eps, radicado_arl, radicado_caja, radicado_pension, created_at, updated_at)
SELECT @id_mave, l.Id, l.Cedula, LOWER(LTRIM(RTRIM(ISNULL(l.Estado,'')))),
    (SELECT TOP 1 id FROM razones_sociales WHERE aliado_id=@id_mave AND id_legacy=l.COD_RAZON_SOC),
    (SELECT TOP 1 id FROM asesores WHERE aliado_id=@id_mave AND id_legacy=l.Asesor),
    (SELECT TOP 1 id FROM users WHERE aliado_id=@id_mave AND id_legacy=l.Encargado_Afiliacion),
    (SELECT TOP 1 id FROM eps WHERE nit=TRY_CAST(l.Eps_c AS BIGINT)),
    (SELECT TOP 1 id FROM pensiones WHERE nit=TRY_CAST(l.Pension_c AS BIGINT)),
    (SELECT TOP 1 id FROM arls WHERE nit=TRY_CAST(l.ARL AS BIGINT)),
    (SELECT TOP 1 id FROM cajas WHERE nit=TRY_CAST(l.Caja_Comp AS BIGINT)),
    l.N_ARL, LTRIM(RTRIM(ISNULL(l.Cargo,''))), TRY_CAST(l.Fecha_Ingreso AS DATE), TRY_CAST(l.Fecha_Retiro AS DATE), TRY_CAST(l.Fecha_ARL AS DATE), TRY_CAST(l.Fecha_Created AS DATE),
    ISNULL(l.Salario_M,0), ISNULL(l.Administracion,0), ISNULL(l.admon_asesor,0), ISNULL(l.costo_afiliacion,0), ISNULL(l.Seguro,0),
    LTRIM(RTRIM(ISNULL(l.NP,''))), LTRIM(RTRIM(ISNULL(l.Envio_Planilla,''))), LTRIM(RTRIM(ISNULL(l.Fecha_problable_pago,''))), LTRIM(RTRIM(ISNULL(l.Modo_propable_pago,''))),
    LTRIM(RTRIM(ISNULL(l.Observacion,''))), LTRIM(RTRIM(ISNULL(l.Observacion_Afiliacion,''))), CAST(ISNULL(l.Observacion_llamada,'') AS NVARCHAR(MAX)),
    LTRIM(RTRIM(ISNULL(l.Motivo_Afiliacion,''))), LTRIM(RTRIM(ISNULL(l.Motivo_Retiro,''))),
    l.Tipo, l.Actividad_Economica,
    LTRIM(RTRIM(ISNULL(l.Radicado_EPS,''))), LTRIM(RTRIM(ISNULL(l.Radicado_ARL,''))), LTRIM(RTRIM(ISNULL(l.Radicado_Caja,''))), LTRIM(RTRIM(ISNULL(l.Radicado_Pension,''))),
    GETDATE(), GETDATE()
FROM Mave_Anderson.dbo.Contratos l;
PRINT '   ✅ Mave: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' contratos';

-- ── Faga ─────────────────────────────────────────────────────────────────────
PRINT '🔵 SS_Faga → contratos...';
INSERT INTO contratos (aliado_id, id_legacy, cedula, estado, razon_social_id, asesor_id, encargado_id, eps_id, pension_id, arl_id, caja_id, n_arl, cargo, fecha_ingreso, fecha_retiro, fecha_arl, fecha_created, salario, administracion, admon_asesor, costo_afiliacion, seguro, np, envio_planilla, fecha_probable_pago, modo_probable_pago, observacion, observacion_afiliacion, observacion_llamada, motivo_afiliacion, motivo_retiro, tipo_modalidad_id, actividad_economica_id, radicado_eps, radicado_arl, radicado_caja, radicado_pension, created_at, updated_at)
SELECT @id_faga, l.Id, l.Cedula, LOWER(LTRIM(RTRIM(ISNULL(l.Estado,'')))),
    (SELECT TOP 1 id FROM razones_sociales WHERE aliado_id=@id_faga AND id_legacy=l.COD_RAZON_SOC),
    (SELECT TOP 1 id FROM asesores WHERE aliado_id=@id_faga AND id_legacy=l.Asesor),
    (SELECT TOP 1 id FROM users WHERE aliado_id=@id_faga AND id_legacy=l.Encargado_Afiliacion),
    (SELECT TOP 1 id FROM eps WHERE nit=TRY_CAST(l.Eps_c AS BIGINT)),
    (SELECT TOP 1 id FROM pensiones WHERE nit=TRY_CAST(l.Pension_c AS BIGINT)),
    (SELECT TOP 1 id FROM arls WHERE nit=TRY_CAST(l.ARL AS BIGINT)),
    (SELECT TOP 1 id FROM cajas WHERE nit=TRY_CAST(l.Caja_Comp AS BIGINT)),
    l.N_ARL, LTRIM(RTRIM(ISNULL(l.Cargo,''))), TRY_CAST(l.Fecha_Ingreso AS DATE), TRY_CAST(l.Fecha_Retiro AS DATE), TRY_CAST(l.Fecha_ARL AS DATE), TRY_CAST(l.Fecha_Created AS DATE),
    ISNULL(l.Salario_M,0), ISNULL(l.Administracion,0), ISNULL(l.admon_asesor,0), ISNULL(l.costo_afiliacion,0), ISNULL(l.Seguro,0),
    LTRIM(RTRIM(ISNULL(l.NP,''))), LTRIM(RTRIM(ISNULL(l.Envio_Planilla,''))), LTRIM(RTRIM(ISNULL(l.Fecha_problable_pago,''))), LTRIM(RTRIM(ISNULL(l.Modo_propable_pago,''))),
    LTRIM(RTRIM(ISNULL(l.Observacion,''))), LTRIM(RTRIM(ISNULL(l.Observacion_Afiliacion,''))), CAST(ISNULL(l.Observacion_llamada,'') AS NVARCHAR(MAX)),
    LTRIM(RTRIM(ISNULL(l.Motivo_Afiliacion,''))), LTRIM(RTRIM(ISNULL(l.Motivo_Retiro,''))),
    l.Tipo, l.Actividad_Economica,
    LTRIM(RTRIM(ISNULL(l.Radicado_EPS,''))), LTRIM(RTRIM(ISNULL(l.Radicado_ARL,''))), LTRIM(RTRIM(ISNULL(l.Radicado_Caja,''))), LTRIM(RTRIM(ISNULL(l.Radicado_Pension,''))),
    GETDATE(), GETDATE()
FROM SS_Faga.dbo.Contratos l;
PRINT '   ✅ Faga: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' contratos';

ALTER TABLE contratos WITH CHECK CHECK CONSTRAINT ALL;

-- ── Verificación de FKs nulas (contratos sin razon_social_id) ─────────────────
PRINT '';
PRINT '⚠️  Contratos sin razon_social_id (posible falta de match):';
SELECT aliado_id, COUNT(*) AS sin_razon_social
FROM contratos WHERE razon_social_id IS NULL
GROUP BY aliado_id;

PRINT '';
PRINT '📊 RESUMEN:';
SELECT a.nombre AS Aliado, COUNT(*) AS Total,
    SUM(CASE WHEN razon_social_id IS NULL THEN 1 ELSE 0 END) AS sin_RS,
    SUM(CASE WHEN asesor_id IS NULL THEN 1 ELSE 0 END) AS sin_asesor,
    SUM(CASE WHEN eps_id IS NULL THEN 1 ELSE 0 END) AS sin_eps
FROM contratos c JOIN aliados a ON a.id = c.aliado_id
GROUP BY a.nombre ORDER BY a.nombre;

PRINT '✅ Script 06 completo. Siguiente: 07_FACTURAS.sql';
