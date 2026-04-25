-- =============================================================================
-- SCRIPT 03: MIGRAR EMPRESAS
-- Legacy: [BD].dbo.Empresas  →  BryNex.dbo.empresas
-- NOTA: Legacy tiene IDENTITY en Id. BryNex también tiene IDENTITY.
--       Guardamos id_legacy para remap en clientes (COD_EMPRESA → empresa_id)
-- =============================================================================
USE BryNex;
GO

DECLARE @id_brygar    BIGINT = (SELECT id FROM aliados WHERE nit = '901918923');
DECLARE @id_gimave    BIGINT = (SELECT id FROM aliados WHERE nombre = 'GiMave Integral');
DECLARE @id_fecop     BIGINT = (SELECT id FROM aliados WHERE nombre = 'Grupo Fecop');
DECLARE @id_luislopez BIGINT = (SELECT id FROM aliados WHERE nombre = 'Luis Lopez');
DECLARE @id_mave      BIGINT = (SELECT id FROM aliados WHERE nombre = 'Mave Anderson');
DECLARE @id_faga      BIGINT = (SELECT id FROM aliados WHERE nombre = 'SS Faga');

ALTER TABLE empresas NOCHECK CONSTRAINT ALL;

-- BRYGAR
PRINT '🔵 Brygar_BD → empresas...';
INSERT INTO empresas (aliado_id, id_legacy, nit, empresa, contacto, telefono, celular,
    direccion, observacion, cliente_de, tipo_facturacion, iva, correo,
    actividad_economica, created_at, updated_at)
SELECT
    @id_brygar,
    Id,                                             -- id_legacy = Id original
    TRY_CAST(NIT AS BIGINT),
    LTRIM(RTRIM(ISNULL(Empresa,''))),
    LTRIM(RTRIM(ISNULL(Contacto,''))),
    LTRIM(RTRIM(ISNULL(Telefono,''))),
    LTRIM(RTRIM(ISNULL(Celular,''))),
    LTRIM(RTRIM(ISNULL(Direccion,''))),
    LTRIM(RTRIM(ISNULL(Observacion,''))),
    LTRIM(RTRIM(ISNULL(Cliente_De,''))),
    LTRIM(RTRIM(ISNULL(Tipo_Facturacion,''))),
    LTRIM(RTRIM(ISNULL(IVA,''))),
    LTRIM(RTRIM(ISNULL(Correo,''))),
    -- Actividad_economica es ntext en legacy → convertir a varchar
    CAST(ISNULL(CAST(Actividad_economica AS NVARCHAR(MAX)),'') AS NVARCHAR(1000)),
    GETDATE(), GETDATE()
FROM Brygar_BD.dbo.Empresas;
PRINT '   ✅ Brygar: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' empresas';

-- GIMAVE
PRINT '🔵 GiMave_Integral → empresas...';
INSERT INTO empresas (aliado_id, id_legacy, nit, empresa, contacto, telefono, celular, direccion, observacion, cliente_de, tipo_facturacion, iva, correo, actividad_economica, created_at, updated_at)
SELECT @id_gimave, Id, TRY_CAST(NIT AS BIGINT), LTRIM(RTRIM(ISNULL(Empresa,''))), LTRIM(RTRIM(ISNULL(Contacto,''))), LTRIM(RTRIM(ISNULL(Telefono,''))), LTRIM(RTRIM(ISNULL(Celular,''))), LTRIM(RTRIM(ISNULL(Direccion,''))), LTRIM(RTRIM(ISNULL(Observacion,''))), LTRIM(RTRIM(ISNULL(Cliente_De,''))), LTRIM(RTRIM(ISNULL(Tipo_Facturacion,''))), LTRIM(RTRIM(ISNULL(IVA,''))), LTRIM(RTRIM(ISNULL(Correo,''))), CAST(ISNULL(CAST(Actividad_economica AS NVARCHAR(MAX)),'') AS NVARCHAR(1000)), GETDATE(), GETDATE()
FROM GiMave_Integral.dbo.Empresas;
PRINT '   ✅ GiMave: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' empresas';

-- FECOP
PRINT '🔵 Grupo_Fecop → empresas...';
INSERT INTO empresas (aliado_id, id_legacy, nit, empresa, contacto, telefono, celular, direccion, observacion, cliente_de, tipo_facturacion, iva, correo, actividad_economica, created_at, updated_at)
SELECT @id_fecop, Id, TRY_CAST(NIT AS BIGINT), LTRIM(RTRIM(ISNULL(Empresa,''))), LTRIM(RTRIM(ISNULL(Contacto,''))), LTRIM(RTRIM(ISNULL(Telefono,''))), LTRIM(RTRIM(ISNULL(Celular,''))), LTRIM(RTRIM(ISNULL(Direccion,''))), LTRIM(RTRIM(ISNULL(Observacion,''))), LTRIM(RTRIM(ISNULL(Cliente_De,''))), LTRIM(RTRIM(ISNULL(Tipo_Facturacion,''))), LTRIM(RTRIM(ISNULL(IVA,''))), LTRIM(RTRIM(ISNULL(Correo,''))), CAST(ISNULL(CAST(Actividad_economica AS NVARCHAR(MAX)),'') AS NVARCHAR(1000)), GETDATE(), GETDATE()
FROM Grupo_Fecop.dbo.Empresas;
PRINT '   ✅ Fecop: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' empresas';

-- LUISLOPEZ
PRINT '🔵 LuisLopez → empresas...';
INSERT INTO empresas (aliado_id, id_legacy, nit, empresa, contacto, telefono, celular, direccion, observacion, cliente_de, tipo_facturacion, iva, correo, actividad_economica, created_at, updated_at)
SELECT @id_luislopez, Id, TRY_CAST(NIT AS BIGINT), LTRIM(RTRIM(ISNULL(Empresa,''))), LTRIM(RTRIM(ISNULL(Contacto,''))), LTRIM(RTRIM(ISNULL(Telefono,''))), LTRIM(RTRIM(ISNULL(Celular,''))), LTRIM(RTRIM(ISNULL(Direccion,''))), LTRIM(RTRIM(ISNULL(Observacion,''))), LTRIM(RTRIM(ISNULL(Cliente_De,''))), LTRIM(RTRIM(ISNULL(Tipo_Facturacion,''))), LTRIM(RTRIM(ISNULL(IVA,''))), LTRIM(RTRIM(ISNULL(Correo,''))), CAST(ISNULL(CAST(Actividad_economica AS NVARCHAR(MAX)),'') AS NVARCHAR(1000)), GETDATE(), GETDATE()
FROM LuisLopez.dbo.Empresas;
PRINT '   ✅ LuisLopez: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' empresas';

-- MAVE
PRINT '🔵 Mave_Anderson → empresas...';
INSERT INTO empresas (aliado_id, id_legacy, nit, empresa, contacto, telefono, celular, direccion, observacion, cliente_de, tipo_facturacion, iva, correo, actividad_economica, created_at, updated_at)
SELECT @id_mave, Id, TRY_CAST(NIT AS BIGINT), LTRIM(RTRIM(ISNULL(Empresa,''))), LTRIM(RTRIM(ISNULL(Contacto,''))), LTRIM(RTRIM(ISNULL(Telefono,''))), LTRIM(RTRIM(ISNULL(Celular,''))), LTRIM(RTRIM(ISNULL(Direccion,''))), LTRIM(RTRIM(ISNULL(Observacion,''))), LTRIM(RTRIM(ISNULL(Cliente_De,''))), LTRIM(RTRIM(ISNULL(Tipo_Facturacion,''))), LTRIM(RTRIM(ISNULL(IVA,''))), LTRIM(RTRIM(ISNULL(Correo,''))), CAST(ISNULL(CAST(Actividad_economica AS NVARCHAR(MAX)),'') AS NVARCHAR(1000)), GETDATE(), GETDATE()
FROM Mave_Anderson.dbo.Empresas;
PRINT '   ✅ Mave: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' empresas';

-- FAGA
PRINT '🔵 SS_Faga → empresas...';
INSERT INTO empresas (aliado_id, id_legacy, nit, empresa, contacto, telefono, celular, direccion, observacion, cliente_de, tipo_facturacion, iva, correo, actividad_economica, created_at, updated_at)
SELECT @id_faga, Id, TRY_CAST(NIT AS BIGINT), LTRIM(RTRIM(ISNULL(Empresa,''))), LTRIM(RTRIM(ISNULL(Contacto,''))), LTRIM(RTRIM(ISNULL(Telefono,''))), LTRIM(RTRIM(ISNULL(Celular,''))), LTRIM(RTRIM(ISNULL(Direccion,''))), LTRIM(RTRIM(ISNULL(Observacion,''))), LTRIM(RTRIM(ISNULL(Cliente_De,''))), LTRIM(RTRIM(ISNULL(Tipo_Facturacion,''))), LTRIM(RTRIM(ISNULL(IVA,''))), LTRIM(RTRIM(ISNULL(Correo,''))), CAST(ISNULL(CAST(Actividad_economica AS NVARCHAR(MAX)),'') AS NVARCHAR(1000)), GETDATE(), GETDATE()
FROM SS_Faga.dbo.Empresas;
PRINT '   ✅ Faga: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' empresas';

ALTER TABLE empresas WITH CHECK CHECK CONSTRAINT ALL;

PRINT '';
PRINT '📊 RESUMEN:';
SELECT a.nombre AS Aliado, COUNT(*) AS Total
FROM empresas e JOIN aliados a ON a.id = e.aliado_id
GROUP BY a.nombre ORDER BY a.nombre;

PRINT '✅ Script 03 completo. Siguiente: 04_ASESORES.sql';
