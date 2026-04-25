-- =============================================================================
-- SCRIPT 04: MIGRAR ASESORES + BANCO_CUENTAS
-- Legacy Asesores: ID = cédula del asesor (no es auto-increment)
-- Legacy Bancos_cuentas: ID manual (no es auto-increment)
-- =============================================================================
USE BryNex;
GO

DECLARE @id_brygar    BIGINT = (SELECT id FROM aliados WHERE nit = '901918923');
DECLARE @id_gimave    BIGINT = (SELECT id FROM aliados WHERE nombre = 'GiMave Integral');
DECLARE @id_fecop     BIGINT = (SELECT id FROM aliados WHERE nombre = 'Grupo Fecop');
DECLARE @id_luislopez BIGINT = (SELECT id FROM aliados WHERE nombre = 'Luis Lopez');
DECLARE @id_mave      BIGINT = (SELECT id FROM aliados WHERE nombre = 'Mave Anderson');
DECLARE @id_faga      BIGINT = (SELECT id FROM aliados WHERE nombre = 'SS Faga');

-- ─────────────────────────────────────────────────────────────────────────────
-- PARTE A: ASESORES
-- NOTA: legacy.Asesores.ID parece ser la cédula del asesor.
--       BryNex.asesores tiene (aliado_id, cedula, nombre, ..., id_legacy, id_original_access)
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE asesores NOCHECK CONSTRAINT ALL;

PRINT '🔵 Asesores: Brygar_BD...';
INSERT INTO asesores (aliado_id, id_legacy, cedula, nombre, telefono, direccion,
    ciudad, departamento, cuenta_bancaria, fecha_ingreso, activo,
    id_original_access, created_at, updated_at)
SELECT
    @id_brygar,
    ID,                                         -- id_legacy = ID original (es la cédula)
    CAST(ID AS VARCHAR(20)),                    -- cedula = mismo ID
    LTRIM(RTRIM(ISNULL(Nombre,'Sin nombre'))),
    LTRIM(RTRIM(ISNULL(Telefono,''))),
    LTRIM(RTRIM(ISNULL(DIreccion,''))),         -- DIreccion (typo en legacy)
    LTRIM(RTRIM(ISNULL(Ciudad,''))),
    LTRIM(RTRIM(ISNULL(Departamento,''))),
    LTRIM(RTRIM(ISNULL(Cuenta_Bancaria,''))),
    TRY_CAST(Fecha_Ingreso AS DATE),
    CASE WHEN LTRIM(RTRIM(ISNULL(Activo,''))) = 'Activo' THEN 1 ELSE 0 END,
    ID,                                         -- id_original_access = mismo ID
    GETDATE(), GETDATE()
FROM Brygar_BD.dbo.Asesores
WHERE ID IS NOT NULL;
PRINT '   ✅ Brygar: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' asesores';

PRINT '🔵 Asesores: GiMave_Integral...';
INSERT INTO asesores (aliado_id, id_legacy, cedula, nombre, telefono, direccion, ciudad, departamento, cuenta_bancaria, fecha_ingreso, activo, id_original_access, created_at, updated_at)
SELECT @id_gimave, ID, CAST(ID AS VARCHAR(20)), LTRIM(RTRIM(ISNULL(Nombre,'Sin nombre'))), LTRIM(RTRIM(ISNULL(Telefono,''))), LTRIM(RTRIM(ISNULL(DIreccion,''))), LTRIM(RTRIM(ISNULL(Ciudad,''))), LTRIM(RTRIM(ISNULL(Departamento,''))), LTRIM(RTRIM(ISNULL(Cuenta_Bancaria,''))), TRY_CAST(Fecha_Ingreso AS DATE), CASE WHEN LTRIM(RTRIM(ISNULL(Activo,'')))='Activo' THEN 1 ELSE 0 END, ID, GETDATE(), GETDATE()
FROM GiMave_Integral.dbo.Asesores WHERE ID IS NOT NULL;
PRINT '   ✅ GiMave: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' asesores';

PRINT '🔵 Asesores: Grupo_Fecop...';
INSERT INTO asesores (aliado_id, id_legacy, cedula, nombre, telefono, direccion, ciudad, departamento, cuenta_bancaria, fecha_ingreso, activo, id_original_access, created_at, updated_at)
SELECT @id_fecop, ID, CAST(ID AS VARCHAR(20)), LTRIM(RTRIM(ISNULL(Nombre,'Sin nombre'))), LTRIM(RTRIM(ISNULL(Telefono,''))), LTRIM(RTRIM(ISNULL(DIreccion,''))), LTRIM(RTRIM(ISNULL(Ciudad,''))), LTRIM(RTRIM(ISNULL(Departamento,''))), LTRIM(RTRIM(ISNULL(Cuenta_Bancaria,''))), TRY_CAST(Fecha_Ingreso AS DATE), CASE WHEN LTRIM(RTRIM(ISNULL(Activo,'')))='Activo' THEN 1 ELSE 0 END, ID, GETDATE(), GETDATE()
FROM Grupo_Fecop.dbo.Asesores WHERE ID IS NOT NULL;
PRINT '   ✅ Fecop: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' asesores';

PRINT '🔵 Asesores: LuisLopez...';
INSERT INTO asesores (aliado_id, id_legacy, cedula, nombre, telefono, direccion, ciudad, departamento, cuenta_bancaria, fecha_ingreso, activo, id_original_access, created_at, updated_at)
SELECT @id_luislopez, ID, CAST(ID AS VARCHAR(20)), LTRIM(RTRIM(ISNULL(Nombre,'Sin nombre'))), LTRIM(RTRIM(ISNULL(Telefono,''))), LTRIM(RTRIM(ISNULL(DIreccion,''))), LTRIM(RTRIM(ISNULL(Ciudad,''))), LTRIM(RTRIM(ISNULL(Departamento,''))), LTRIM(RTRIM(ISNULL(Cuenta_Bancaria,''))), TRY_CAST(Fecha_Ingreso AS DATE), CASE WHEN LTRIM(RTRIM(ISNULL(Activo,'')))='Activo' THEN 1 ELSE 0 END, ID, GETDATE(), GETDATE()
FROM LuisLopez.dbo.Asesores WHERE ID IS NOT NULL;
PRINT '   ✅ LuisLopez: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' asesores';

PRINT '🔵 Asesores: Mave_Anderson...';
INSERT INTO asesores (aliado_id, id_legacy, cedula, nombre, telefono, direccion, ciudad, departamento, cuenta_bancaria, fecha_ingreso, activo, id_original_access, created_at, updated_at)
SELECT @id_mave, ID, CAST(ID AS VARCHAR(20)), LTRIM(RTRIM(ISNULL(Nombre,'Sin nombre'))), LTRIM(RTRIM(ISNULL(Telefono,''))), LTRIM(RTRIM(ISNULL(DIreccion,''))), LTRIM(RTRIM(ISNULL(Ciudad,''))), LTRIM(RTRIM(ISNULL(Departamento,''))), LTRIM(RTRIM(ISNULL(Cuenta_Bancaria,''))), TRY_CAST(Fecha_Ingreso AS DATE), CASE WHEN LTRIM(RTRIM(ISNULL(Activo,'')))='Activo' THEN 1 ELSE 0 END, ID, GETDATE(), GETDATE()
FROM Mave_Anderson.dbo.Asesores WHERE ID IS NOT NULL;
PRINT '   ✅ Mave: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' asesores';

PRINT '🔵 Asesores: SS_Faga...';
INSERT INTO asesores (aliado_id, id_legacy, cedula, nombre, telefono, direccion, ciudad, departamento, cuenta_bancaria, fecha_ingreso, activo, id_original_access, created_at, updated_at)
SELECT @id_faga, ID, CAST(ID AS VARCHAR(20)), LTRIM(RTRIM(ISNULL(Nombre,'Sin nombre'))), LTRIM(RTRIM(ISNULL(Telefono,''))), LTRIM(RTRIM(ISNULL(DIreccion,''))), LTRIM(RTRIM(ISNULL(Ciudad,''))), LTRIM(RTRIM(ISNULL(Departamento,''))), LTRIM(RTRIM(ISNULL(Cuenta_Bancaria,''))), TRY_CAST(Fecha_Ingreso AS DATE), CASE WHEN LTRIM(RTRIM(ISNULL(Activo,'')))='Activo' THEN 1 ELSE 0 END, ID, GETDATE(), GETDATE()
FROM SS_Faga.dbo.Asesores WHERE ID IS NOT NULL;
PRINT '   ✅ Faga: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' asesores';

ALTER TABLE asesores WITH CHECK CHECK CONSTRAINT ALL;

-- ─────────────────────────────────────────────────────────────────────────────
-- PARTE B: BANCO_CUENTAS
-- Legacy: NOMBRE, NIT, TIPO, NUMERO, BANCO, ID
-- BryNex: nombre, nit, tipo_cuenta, numero_cuenta, banco, aliado_id, id_legacy
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE banco_cuentas NOCHECK CONSTRAINT ALL;

PRINT '';
PRINT '🔵 Bancos_cuentas: Brygar_BD...';
INSERT INTO banco_cuentas (aliado_id, id_legacy, nombre, nit, banco, tipo_cuenta, numero_cuenta, activo, created_at, updated_at)
SELECT @id_brygar, ID, LTRIM(RTRIM(ISNULL(NOMBRE,''))), LTRIM(RTRIM(ISNULL(NIT,''))), LTRIM(RTRIM(ISNULL(BANCO,''))), LTRIM(RTRIM(ISNULL(TIPO,''))), LTRIM(RTRIM(ISNULL(NUMERO,''))), 1, GETDATE(), GETDATE()
FROM Brygar_BD.dbo.Bancos_cuentas WHERE ID IS NOT NULL;
PRINT '   ✅ Brygar: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' cuentas';

PRINT '🔵 Bancos_cuentas: GiMave_Integral...';
INSERT INTO banco_cuentas (aliado_id, id_legacy, nombre, nit, banco, tipo_cuenta, numero_cuenta, activo, created_at, updated_at)
SELECT @id_gimave, ID, LTRIM(RTRIM(ISNULL(NOMBRE,''))), LTRIM(RTRIM(ISNULL(NIT,''))), LTRIM(RTRIM(ISNULL(BANCO,''))), LTRIM(RTRIM(ISNULL(TIPO,''))), LTRIM(RTRIM(ISNULL(NUMERO,''))), 1, GETDATE(), GETDATE()
FROM GiMave_Integral.dbo.Bancos_cuentas WHERE ID IS NOT NULL;
PRINT '   ✅ GiMave: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' cuentas';

PRINT '🔵 Bancos_cuentas: Grupo_Fecop...';
INSERT INTO banco_cuentas (aliado_id, id_legacy, nombre, nit, banco, tipo_cuenta, numero_cuenta, activo, created_at, updated_at)
SELECT @id_fecop, ID, LTRIM(RTRIM(ISNULL(NOMBRE,''))), LTRIM(RTRIM(ISNULL(NIT,''))), LTRIM(RTRIM(ISNULL(BANCO,''))), LTRIM(RTRIM(ISNULL(TIPO,''))), LTRIM(RTRIM(ISNULL(NUMERO,''))), 1, GETDATE(), GETDATE()
FROM Grupo_Fecop.dbo.Bancos_cuentas WHERE ID IS NOT NULL;
PRINT '   ✅ Fecop: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' cuentas';

PRINT '🔵 Bancos_cuentas: LuisLopez...';
INSERT INTO banco_cuentas (aliado_id, id_legacy, nombre, nit, banco, tipo_cuenta, numero_cuenta, activo, created_at, updated_at)
SELECT @id_luislopez, ID, LTRIM(RTRIM(ISNULL(NOMBRE,''))), LTRIM(RTRIM(ISNULL(NIT,''))), LTRIM(RTRIM(ISNULL(BANCO,''))), LTRIM(RTRIM(ISNULL(TIPO,''))), LTRIM(RTRIM(ISNULL(NUMERO,''))), 1, GETDATE(), GETDATE()
FROM LuisLopez.dbo.Bancos_cuentas WHERE ID IS NOT NULL;
PRINT '   ✅ LuisLopez: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' cuentas';

PRINT '🔵 Bancos_cuentas: Mave_Anderson...';
INSERT INTO banco_cuentas (aliado_id, id_legacy, nombre, nit, banco, tipo_cuenta, numero_cuenta, activo, created_at, updated_at)
SELECT @id_mave, ID, LTRIM(RTRIM(ISNULL(NOMBRE,''))), LTRIM(RTRIM(ISNULL(NIT,''))), LTRIM(RTRIM(ISNULL(BANCO,''))), LTRIM(RTRIM(ISNULL(TIPO,''))), LTRIM(RTRIM(ISNULL(NUMERO,''))), 1, GETDATE(), GETDATE()
FROM Mave_Anderson.dbo.Bancos_cuentas WHERE ID IS NOT NULL;
PRINT '   ✅ Mave: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' cuentas';

PRINT '🔵 Bancos_cuentas: SS_Faga...';
INSERT INTO banco_cuentas (aliado_id, id_legacy, nombre, nit, banco, tipo_cuenta, numero_cuenta, activo, created_at, updated_at)
SELECT @id_faga, ID, LTRIM(RTRIM(ISNULL(NOMBRE,''))), LTRIM(RTRIM(ISNULL(NIT,''))), LTRIM(RTRIM(ISNULL(BANCO,''))), LTRIM(RTRIM(ISNULL(TIPO,''))), LTRIM(RTRIM(ISNULL(NUMERO,''))), 1, GETDATE(), GETDATE()
FROM SS_Faga.dbo.Bancos_cuentas WHERE ID IS NOT NULL;
PRINT '   ✅ Faga: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' cuentas';

ALTER TABLE banco_cuentas WITH CHECK CHECK CONSTRAINT ALL;

PRINT '';
PRINT '📊 RESUMEN ASESORES:';
SELECT a.nombre AS Aliado, COUNT(*) AS Asesores FROM asesores s JOIN aliados a ON a.id=s.aliado_id GROUP BY a.nombre;
PRINT '📊 RESUMEN BANCO_CUENTAS:';
SELECT a.nombre AS Aliado, COUNT(*) AS Cuentas FROM banco_cuentas b JOIN aliados a ON a.id=b.aliado_id GROUP BY a.nombre;

PRINT '✅ Script 04 completo. Siguiente: 05_CLIENTES.sql';
