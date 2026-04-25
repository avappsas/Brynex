-- =============================================================================
-- SCRIPT 05: MIGRAR CLIENTES (Base_De_Datos)
-- EPS/Pension se resuelven por NIT contra tablas globales
-- cod_empresa se guarda como legacy, se remap al final con id_legacy de empresas
-- =============================================================================
USE BryNex;
GO

DECLARE @id_brygar    BIGINT = (SELECT id FROM aliados WHERE nit = '901918923');
DECLARE @id_gimave    BIGINT = (SELECT id FROM aliados WHERE nombre = 'GiMave Integral');
DECLARE @id_fecop     BIGINT = (SELECT id FROM aliados WHERE nombre = 'Grupo Fecop');
DECLARE @id_luislopez BIGINT = (SELECT id FROM aliados WHERE nombre = 'Luis Lopez');
DECLARE @id_mave      BIGINT = (SELECT id FROM aliados WHERE nombre = 'Mave Anderson');
DECLARE @id_faga      BIGINT = (SELECT id FROM aliados WHERE nombre = 'SS Faga');

ALTER TABLE clientes NOCHECK CONSTRAINT ALL;

-- ── Macro INSERT (mismo para todos los aliados, solo cambia la BD y aliado_id) ─

PRINT '🔵 Brygar_BD → clientes...';
INSERT INTO clientes (
    aliado_id, id_legacy, cod_empresa,
    tipo_doc, cedula, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido,
    genero, sisben, fecha_nacimiento, fecha_expedicion, rh,
    telefono, celular, correo,
    departamento_id, municipio_id, direccion_vivienda, direccion_cobro, barrio,
    eps_id, pension_id, ips, urgencias, iva,
    ocupacion, referido, observacion, observacion_llamada,
    claves, datos, deuda, fecha_probable_pago, modo_probable_pago,
    created_at, updated_at
)
SELECT
    @id_brygar,
    l.Id,
    l.COD_EMPRESA,                                          -- remap al final
    LTRIM(RTRIM(ISNULL(l.TIPO_DOC,''))),
    l.Cedula,
    LTRIM(RTRIM(ISNULL(l.[1_NOMBRE],''))),
    LTRIM(RTRIM(ISNULL(l.[2_NOMBRE],''))),
    LTRIM(RTRIM(ISNULL(l.[1_APELLIDO],''))),
    LTRIM(RTRIM(ISNULL(l.[2_APELLIDO],''))),
    LTRIM(RTRIM(ISNULL(l.Genero,''))),
    LTRIM(RTRIM(ISNULL(l.Sisben,''))),
    TRY_CAST(l.Fecha_Nacimiento AS DATE),
    TRY_CAST(l.Fecha_Expedicion AS DATE),
    LTRIM(RTRIM(ISNULL(l.RH,''))),
    LTRIM(RTRIM(ISNULL(l.Telefono,''))),
    TRY_CAST(l.Celular AS BIGINT),
    LTRIM(RTRIM(ISNULL(l.Correo,''))),
    l.Departamento,                                         -- mismo ID global
    TRY_CAST(l.Municipio AS INT),                           -- mismo ID global
    LTRIM(RTRIM(ISNULL(l.Direccion_Vivienda,''))),
    LTRIM(RTRIM(ISNULL(l.Direccion_Cobro,''))),
    LTRIM(RTRIM(ISNULL(l.Barrio,''))),
    (SELECT TOP 1 id FROM eps WHERE nit = TRY_CAST(l.Eps AS BIGINT)),
    (SELECT TOP 1 id FROM pensiones WHERE nit = TRY_CAST(l.Pension AS BIGINT)),
    LTRIM(RTRIM(ISNULL(l.IPS,''))),
    LTRIM(RTRIM(ISNULL(l.URGENCIAS,''))),
    LTRIM(RTRIM(ISNULL(l.IVA,''))),
    LTRIM(RTRIM(ISNULL(l.Ocupacion,''))),
    LTRIM(RTRIM(ISNULL(l.Referido,''))),
    LTRIM(RTRIM(ISNULL(l.Observacion,''))),
    CAST(ISNULL(l.Observacion_llamada,'') AS NVARCHAR(MAX)),
    LTRIM(RTRIM(ISNULL(l.Claves,''))),
    LTRIM(RTRIM(ISNULL(l.Datos,''))),
    l.DEUDA,
    LTRIM(RTRIM(ISNULL(l.Fecha_problable_pago,''))),
    LTRIM(RTRIM(ISNULL(l.Modo_propable_pago,''))),
    GETDATE(), GETDATE()
FROM Brygar_BD.dbo.Base_De_Datos l;
PRINT '   ✅ Brygar: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' clientes';

PRINT '🔵 GiMave_Integral → clientes...';
INSERT INTO clientes (aliado_id, id_legacy, cod_empresa, tipo_doc, cedula, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, genero, sisben, fecha_nacimiento, fecha_expedicion, rh, telefono, celular, correo, departamento_id, municipio_id, direccion_vivienda, direccion_cobro, barrio, eps_id, pension_id, ips, urgencias, iva, ocupacion, referido, observacion, observacion_llamada, claves, datos, deuda, fecha_probable_pago, modo_probable_pago, created_at, updated_at)
SELECT @id_gimave, l.Id, l.COD_EMPRESA, LTRIM(RTRIM(ISNULL(l.TIPO_DOC,''))), l.Cedula, LTRIM(RTRIM(ISNULL(l.[1_NOMBRE],''))), LTRIM(RTRIM(ISNULL(l.[2_NOMBRE],''))), LTRIM(RTRIM(ISNULL(l.[1_APELLIDO],''))), LTRIM(RTRIM(ISNULL(l.[2_APELLIDO],''))), LTRIM(RTRIM(ISNULL(l.Genero,''))), LTRIM(RTRIM(ISNULL(l.Sisben,''))), TRY_CAST(l.Fecha_Nacimiento AS DATE), TRY_CAST(l.Fecha_Expedicion AS DATE), LTRIM(RTRIM(ISNULL(l.RH,''))), LTRIM(RTRIM(ISNULL(l.Telefono,''))), TRY_CAST(l.Celular AS BIGINT), LTRIM(RTRIM(ISNULL(l.Correo,''))), l.Departamento, TRY_CAST(l.Municipio AS INT), LTRIM(RTRIM(ISNULL(l.Direccion_Vivienda,''))), LTRIM(RTRIM(ISNULL(l.Direccion_Cobro,''))), LTRIM(RTRIM(ISNULL(l.Barrio,''))), (SELECT TOP 1 id FROM eps WHERE nit=TRY_CAST(l.Eps AS BIGINT)), (SELECT TOP 1 id FROM pensiones WHERE nit=TRY_CAST(l.Pension AS BIGINT)), LTRIM(RTRIM(ISNULL(l.IPS,''))), LTRIM(RTRIM(ISNULL(l.URGENCIAS,''))), LTRIM(RTRIM(ISNULL(l.IVA,''))), LTRIM(RTRIM(ISNULL(l.Ocupacion,''))), LTRIM(RTRIM(ISNULL(l.Referido,''))), LTRIM(RTRIM(ISNULL(l.Observacion,''))), CAST(ISNULL(l.Observacion_llamada,'') AS NVARCHAR(MAX)), LTRIM(RTRIM(ISNULL(l.Claves,''))), LTRIM(RTRIM(ISNULL(l.Datos,''))), l.DEUDA, LTRIM(RTRIM(ISNULL(l.Fecha_problable_pago,''))), LTRIM(RTRIM(ISNULL(l.Modo_propable_pago,''))), GETDATE(), GETDATE()
FROM GiMave_Integral.dbo.Base_De_Datos l;
PRINT '   ✅ GiMave: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' clientes';

PRINT '🔵 Grupo_Fecop → clientes...';
INSERT INTO clientes (aliado_id, id_legacy, cod_empresa, tipo_doc, cedula, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, genero, sisben, fecha_nacimiento, fecha_expedicion, rh, telefono, celular, correo, departamento_id, municipio_id, direccion_vivienda, direccion_cobro, barrio, eps_id, pension_id, ips, urgencias, iva, ocupacion, referido, observacion, observacion_llamada, claves, datos, deuda, fecha_probable_pago, modo_probable_pago, created_at, updated_at)
SELECT @id_fecop, l.Id, l.COD_EMPRESA, LTRIM(RTRIM(ISNULL(l.TIPO_DOC,''))), l.Cedula, LTRIM(RTRIM(ISNULL(l.[1_NOMBRE],''))), LTRIM(RTRIM(ISNULL(l.[2_NOMBRE],''))), LTRIM(RTRIM(ISNULL(l.[1_APELLIDO],''))), LTRIM(RTRIM(ISNULL(l.[2_APELLIDO],''))), LTRIM(RTRIM(ISNULL(l.Genero,''))), LTRIM(RTRIM(ISNULL(l.Sisben,''))), TRY_CAST(l.Fecha_Nacimiento AS DATE), TRY_CAST(l.Fecha_Expedicion AS DATE), LTRIM(RTRIM(ISNULL(l.RH,''))), LTRIM(RTRIM(ISNULL(l.Telefono,''))), TRY_CAST(l.Celular AS BIGINT), LTRIM(RTRIM(ISNULL(l.Correo,''))), l.Departamento, TRY_CAST(l.Municipio AS INT), LTRIM(RTRIM(ISNULL(l.Direccion_Vivienda,''))), LTRIM(RTRIM(ISNULL(l.Direccion_Cobro,''))), LTRIM(RTRIM(ISNULL(l.Barrio,''))), (SELECT TOP 1 id FROM eps WHERE nit=TRY_CAST(l.Eps AS BIGINT)), (SELECT TOP 1 id FROM pensiones WHERE nit=TRY_CAST(l.Pension AS BIGINT)), LTRIM(RTRIM(ISNULL(l.IPS,''))), LTRIM(RTRIM(ISNULL(l.URGENCIAS,''))), LTRIM(RTRIM(ISNULL(l.IVA,''))), LTRIM(RTRIM(ISNULL(l.Ocupacion,''))), LTRIM(RTRIM(ISNULL(l.Referido,''))), LTRIM(RTRIM(ISNULL(l.Observacion,''))), CAST(ISNULL(l.Observacion_llamada,'') AS NVARCHAR(MAX)), LTRIM(RTRIM(ISNULL(l.Claves,''))), LTRIM(RTRIM(ISNULL(l.Datos,''))), l.DEUDA, LTRIM(RTRIM(ISNULL(l.Fecha_problable_pago,''))), LTRIM(RTRIM(ISNULL(l.Modo_propable_pago,''))), GETDATE(), GETDATE()
FROM Grupo_Fecop.dbo.Base_De_Datos l;
PRINT '   ✅ Fecop: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' clientes';

PRINT '🔵 LuisLopez → clientes...';
INSERT INTO clientes (aliado_id, id_legacy, cod_empresa, tipo_doc, cedula, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, genero, sisben, fecha_nacimiento, fecha_expedicion, rh, telefono, celular, correo, departamento_id, municipio_id, direccion_vivienda, direccion_cobro, barrio, eps_id, pension_id, ips, urgencias, iva, ocupacion, referido, observacion, observacion_llamada, claves, datos, deuda, fecha_probable_pago, modo_probable_pago, created_at, updated_at)
SELECT @id_luislopez, l.Id, l.COD_EMPRESA, LTRIM(RTRIM(ISNULL(l.TIPO_DOC,''))), l.Cedula, LTRIM(RTRIM(ISNULL(l.[1_NOMBRE],''))), LTRIM(RTRIM(ISNULL(l.[2_NOMBRE],''))), LTRIM(RTRIM(ISNULL(l.[1_APELLIDO],''))), LTRIM(RTRIM(ISNULL(l.[2_APELLIDO],''))), LTRIM(RTRIM(ISNULL(l.Genero,''))), LTRIM(RTRIM(ISNULL(l.Sisben,''))), TRY_CAST(l.Fecha_Nacimiento AS DATE), TRY_CAST(l.Fecha_Expedicion AS DATE), LTRIM(RTRIM(ISNULL(l.RH,''))), LTRIM(RTRIM(ISNULL(l.Telefono,''))), TRY_CAST(l.Celular AS BIGINT), LTRIM(RTRIM(ISNULL(l.Correo,''))), l.Departamento, TRY_CAST(l.Municipio AS INT), LTRIM(RTRIM(ISNULL(l.Direccion_Vivienda,''))), LTRIM(RTRIM(ISNULL(l.Direccion_Cobro,''))), LTRIM(RTRIM(ISNULL(l.Barrio,''))), (SELECT TOP 1 id FROM eps WHERE nit=TRY_CAST(l.Eps AS BIGINT)), (SELECT TOP 1 id FROM pensiones WHERE nit=TRY_CAST(l.Pension AS BIGINT)), LTRIM(RTRIM(ISNULL(l.IPS,''))), LTRIM(RTRIM(ISNULL(l.URGENCIAS,''))), LTRIM(RTRIM(ISNULL(l.IVA,''))), LTRIM(RTRIM(ISNULL(l.Ocupacion,''))), LTRIM(RTRIM(ISNULL(l.Referido,''))), LTRIM(RTRIM(ISNULL(l.Observacion,''))), CAST(ISNULL(l.Observacion_llamada,'') AS NVARCHAR(MAX)), LTRIM(RTRIM(ISNULL(l.Claves,''))), LTRIM(RTRIM(ISNULL(l.Datos,''))), l.DEUDA, LTRIM(RTRIM(ISNULL(l.Fecha_problable_pago,''))), LTRIM(RTRIM(ISNULL(l.Modo_propable_pago,''))), GETDATE(), GETDATE()
FROM LuisLopez.dbo.Base_De_Datos l;
PRINT '   ✅ LuisLopez: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' clientes';

PRINT '🔵 Mave_Anderson → clientes...';
INSERT INTO clientes (aliado_id, id_legacy, cod_empresa, tipo_doc, cedula, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, genero, sisben, fecha_nacimiento, fecha_expedicion, rh, telefono, celular, correo, departamento_id, municipio_id, direccion_vivienda, direccion_cobro, barrio, eps_id, pension_id, ips, urgencias, iva, ocupacion, referido, observacion, observacion_llamada, claves, datos, deuda, fecha_probable_pago, modo_probable_pago, created_at, updated_at)
SELECT @id_mave, l.Id, l.COD_EMPRESA, LTRIM(RTRIM(ISNULL(l.TIPO_DOC,''))), l.Cedula, LTRIM(RTRIM(ISNULL(l.[1_NOMBRE],''))), LTRIM(RTRIM(ISNULL(l.[2_NOMBRE],''))), LTRIM(RTRIM(ISNULL(l.[1_APELLIDO],''))), LTRIM(RTRIM(ISNULL(l.[2_APELLIDO],''))), LTRIM(RTRIM(ISNULL(l.Genero,''))), LTRIM(RTRIM(ISNULL(l.Sisben,''))), TRY_CAST(l.Fecha_Nacimiento AS DATE), TRY_CAST(l.Fecha_Expedicion AS DATE), LTRIM(RTRIM(ISNULL(l.RH,''))), LTRIM(RTRIM(ISNULL(l.Telefono,''))), TRY_CAST(l.Celular AS BIGINT), LTRIM(RTRIM(ISNULL(l.Correo,''))), l.Departamento, TRY_CAST(l.Municipio AS INT), LTRIM(RTRIM(ISNULL(l.Direccion_Vivienda,''))), LTRIM(RTRIM(ISNULL(l.Direccion_Cobro,''))), LTRIM(RTRIM(ISNULL(l.Barrio,''))), (SELECT TOP 1 id FROM eps WHERE nit=TRY_CAST(l.Eps AS BIGINT)), (SELECT TOP 1 id FROM pensiones WHERE nit=TRY_CAST(l.Pension AS BIGINT)), LTRIM(RTRIM(ISNULL(l.IPS,''))), LTRIM(RTRIM(ISNULL(l.URGENCIAS,''))), LTRIM(RTRIM(ISNULL(l.IVA,''))), LTRIM(RTRIM(ISNULL(l.Ocupacion,''))), LTRIM(RTRIM(ISNULL(l.Referido,''))), LTRIM(RTRIM(ISNULL(l.Observacion,''))), CAST(ISNULL(l.Observacion_llamada,'') AS NVARCHAR(MAX)), LTRIM(RTRIM(ISNULL(l.Claves,''))), LTRIM(RTRIM(ISNULL(l.Datos,''))), l.DEUDA, LTRIM(RTRIM(ISNULL(l.Fecha_problable_pago,''))), LTRIM(RTRIM(ISNULL(l.Modo_propable_pago,''))), GETDATE(), GETDATE()
FROM Mave_Anderson.dbo.Base_De_Datos l;
PRINT '   ✅ Mave: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' clientes';

PRINT '🔵 SS_Faga → clientes...';
INSERT INTO clientes (aliado_id, id_legacy, cod_empresa, tipo_doc, cedula, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, genero, sisben, fecha_nacimiento, fecha_expedicion, rh, telefono, celular, correo, departamento_id, municipio_id, direccion_vivienda, direccion_cobro, barrio, eps_id, pension_id, ips, urgencias, iva, ocupacion, referido, observacion, observacion_llamada, claves, datos, deuda, fecha_probable_pago, modo_probable_pago, created_at, updated_at)
SELECT @id_faga, l.Id, l.COD_EMPRESA, LTRIM(RTRIM(ISNULL(l.TIPO_DOC,''))), l.Cedula, LTRIM(RTRIM(ISNULL(l.[1_NOMBRE],''))), LTRIM(RTRIM(ISNULL(l.[2_NOMBRE],''))), LTRIM(RTRIM(ISNULL(l.[1_APELLIDO],''))), LTRIM(RTRIM(ISNULL(l.[2_APELLIDO],''))), LTRIM(RTRIM(ISNULL(l.Genero,''))), LTRIM(RTRIM(ISNULL(l.Sisben,''))), TRY_CAST(l.Fecha_Nacimiento AS DATE), TRY_CAST(l.Fecha_Expedicion AS DATE), LTRIM(RTRIM(ISNULL(l.RH,''))), LTRIM(RTRIM(ISNULL(l.Telefono,''))), TRY_CAST(l.Celular AS BIGINT), LTRIM(RTRIM(ISNULL(l.Correo,''))), l.Departamento, TRY_CAST(l.Municipio AS INT), LTRIM(RTRIM(ISNULL(l.Direccion_Vivienda,''))), LTRIM(RTRIM(ISNULL(l.Direccion_Cobro,''))), LTRIM(RTRIM(ISNULL(l.Barrio,''))), (SELECT TOP 1 id FROM eps WHERE nit=TRY_CAST(l.Eps AS BIGINT)), (SELECT TOP 1 id FROM pensiones WHERE nit=TRY_CAST(l.Pension AS BIGINT)), LTRIM(RTRIM(ISNULL(l.IPS,''))), LTRIM(RTRIM(ISNULL(l.URGENCIAS,''))), LTRIM(RTRIM(ISNULL(l.IVA,''))), LTRIM(RTRIM(ISNULL(l.Ocupacion,''))), LTRIM(RTRIM(ISNULL(l.Referido,''))), LTRIM(RTRIM(ISNULL(l.Observacion,''))), CAST(ISNULL(l.Observacion_llamada,'') AS NVARCHAR(MAX)), LTRIM(RTRIM(ISNULL(l.Claves,''))), LTRIM(RTRIM(ISNULL(l.Datos,''))), l.DEUDA, LTRIM(RTRIM(ISNULL(l.Fecha_problable_pago,''))), LTRIM(RTRIM(ISNULL(l.Modo_propable_pago,''))), GETDATE(), GETDATE()
FROM SS_Faga.dbo.Base_De_Datos l;
PRINT '   ✅ Faga: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' clientes';

-- ── Remap cod_empresa → nuevo ID de empresas usando id_legacy ─────────────────
PRINT '';
PRINT '🔄 Remapeando cod_empresa al nuevo ID de empresas...';
UPDATE c
SET c.cod_empresa = e.id
FROM clientes c
JOIN empresas e ON e.id_legacy = c.cod_empresa AND e.aliado_id = c.aliado_id
WHERE c.cod_empresa IS NOT NULL;
PRINT '   ✅ cod_empresa remapeado: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' clientes actualizados';

ALTER TABLE clientes WITH CHECK CHECK CONSTRAINT ALL;

PRINT '';
PRINT '📊 RESUMEN:';
SELECT a.nombre AS Aliado, COUNT(*) AS Total
FROM clientes c JOIN aliados a ON a.id = c.aliado_id
GROUP BY a.nombre ORDER BY a.nombre;

PRINT '✅ Script 05 completo. Siguiente: 06_CONTRATOS.sql';
