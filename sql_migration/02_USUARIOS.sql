-- =============================================================================
-- SCRIPT 02: MIGRAR USUARIOS
-- Legacy: [BD].dbo.usuarios  →  BryNex.dbo.users
-- NOTA: Passwords en legacy son texto plano. Se migran hasheados con bcrypt.
--       En SQL Server no hay bcrypt nativo → usamos SHA2_256 como placeholder.
--       ⚠️  Después de migrar, desde Laravel ejecutar:
--           php artisan tinker => User::where('id_legacy','!=',null)->each(fn($u)=>$u->update(['password'=>bcrypt($u->password)]))
--       O simplemente asignar una contraseña temporal conocida.
-- =============================================================================
-- DECISIÓN: Los usuarios se crean con email = Login + '@legacy.local' si no tienen email real.
--           El aliado_id se asigna al primer aliado de cada BD (el owner).
-- =============================================================================
USE BryNex;
GO

DECLARE @id_brygar    BIGINT = (SELECT id FROM aliados WHERE nit = '901918923');
DECLARE @id_gimave    BIGINT = (SELECT id FROM aliados WHERE nombre = 'GiMave Integral');
DECLARE @id_fecop     BIGINT = (SELECT id FROM aliados WHERE nombre = 'Grupo Fecop');
DECLARE @id_luislopez BIGINT = (SELECT id FROM aliados WHERE nombre = 'Luis Lopez');
DECLARE @id_mave      BIGINT = (SELECT id FROM aliados WHERE nombre = 'Mave Anderson');
DECLARE @id_faga      BIGINT = (SELECT id FROM aliados WHERE nombre = 'SS Faga');

ALTER TABLE users NOCHECK CONSTRAINT ALL;

-- BRYGAR
PRINT '🔵 Brygar_BD → users...';
INSERT INTO users (aliado_id, id_legacy, name, email, password, cedula, created_at, updated_at)
SELECT
    @id_brygar,
    Id_usuario,
    LTRIM(RTRIM(ISNULL(Login, 'Usuario' + CAST(Id_usuario AS VARCHAR)))),
    -- Email: si Login parece un email lo usamos, si no generamos uno único
    LOWER(REPLACE(LTRIM(RTRIM(ISNULL(Login,'user'))), ' ', '.'))
        + '_brygar_' + CAST(Id_usuario AS VARCHAR) + '@legacy.local',
    -- Password hasheado con SHA2 como placeholder (cambiar después con bcrypt desde Laravel)
    CONVERT(VARCHAR(255), HASHBYTES('SHA2_256', ISNULL(Password,'changeme')), 2),
    TRY_CAST(Cedula AS BIGINT),
    GETDATE(), GETDATE()
FROM Brygar_BD.dbo.usuarios
WHERE Id_usuario IS NOT NULL;
PRINT '   ✅ Brygar: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' usuarios';

-- GIMAVE
PRINT '🔵 GiMave_Integral → users...';
INSERT INTO users (aliado_id, id_legacy, name, email, password, cedula, created_at, updated_at)
SELECT @id_gimave, Id_usuario,
    LTRIM(RTRIM(ISNULL(Login,'Usuario' + CAST(Id_usuario AS VARCHAR)))),
    LOWER(REPLACE(LTRIM(RTRIM(ISNULL(Login,'user'))), ' ', '.')) + '_gimave_' + CAST(Id_usuario AS VARCHAR) + '@legacy.local',
    CONVERT(VARCHAR(255), HASHBYTES('SHA2_256', ISNULL(Password,'changeme')), 2),
    TRY_CAST(Cedula AS BIGINT), GETDATE(), GETDATE()
FROM GiMave_Integral.dbo.usuarios WHERE Id_usuario IS NOT NULL;
PRINT '   ✅ GiMave: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' usuarios';

-- FECOP
PRINT '🔵 Grupo_Fecop → users...';
INSERT INTO users (aliado_id, id_legacy, name, email, password, cedula, created_at, updated_at)
SELECT @id_fecop, Id_usuario,
    LTRIM(RTRIM(ISNULL(Login,'Usuario' + CAST(Id_usuario AS VARCHAR)))),
    LOWER(REPLACE(LTRIM(RTRIM(ISNULL(Login,'user'))), ' ', '.')) + '_fecop_' + CAST(Id_usuario AS VARCHAR) + '@legacy.local',
    CONVERT(VARCHAR(255), HASHBYTES('SHA2_256', ISNULL(Password,'changeme')), 2),
    TRY_CAST(Cedula AS BIGINT), GETDATE(), GETDATE()
FROM Grupo_Fecop.dbo.usuarios WHERE Id_usuario IS NOT NULL;
PRINT '   ✅ Fecop: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' usuarios';

-- LUISLOPEZ
PRINT '🔵 LuisLopez → users...';
INSERT INTO users (aliado_id, id_legacy, name, email, password, cedula, created_at, updated_at)
SELECT @id_luislopez, Id_usuario,
    LTRIM(RTRIM(ISNULL(Login,'Usuario' + CAST(Id_usuario AS VARCHAR)))),
    LOWER(REPLACE(LTRIM(RTRIM(ISNULL(Login,'user'))), ' ', '.')) + '_luislopez_' + CAST(Id_usuario AS VARCHAR) + '@legacy.local',
    CONVERT(VARCHAR(255), HASHBYTES('SHA2_256', ISNULL(Password,'changeme')), 2),
    TRY_CAST(Cedula AS BIGINT), GETDATE(), GETDATE()
FROM LuisLopez.dbo.usuarios WHERE Id_usuario IS NOT NULL;
PRINT '   ✅ LuisLopez: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' usuarios';

-- MAVE
PRINT '🔵 Mave_Anderson → users...';
INSERT INTO users (aliado_id, id_legacy, name, email, password, cedula, created_at, updated_at)
SELECT @id_mave, Id_usuario,
    LTRIM(RTRIM(ISNULL(Login,'Usuario' + CAST(Id_usuario AS VARCHAR)))),
    LOWER(REPLACE(LTRIM(RTRIM(ISNULL(Login,'user'))), ' ', '.')) + '_mave_' + CAST(Id_usuario AS VARCHAR) + '@legacy.local',
    CONVERT(VARCHAR(255), HASHBYTES('SHA2_256', ISNULL(Password,'changeme')), 2),
    TRY_CAST(Cedula AS BIGINT), GETDATE(), GETDATE()
FROM Mave_Anderson.dbo.usuarios WHERE Id_usuario IS NOT NULL;
PRINT '   ✅ Mave: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' usuarios';

-- FAGA
PRINT '🔵 SS_Faga → users...';
INSERT INTO users (aliado_id, id_legacy, name, email, password, cedula, created_at, updated_at)
SELECT @id_faga, Id_usuario,
    LTRIM(RTRIM(ISNULL(Login,'Usuario' + CAST(Id_usuario AS VARCHAR)))),
    LOWER(REPLACE(LTRIM(RTRIM(ISNULL(Login,'user'))), ' ', '.')) + '_faga_' + CAST(Id_usuario AS VARCHAR) + '@legacy.local',
    CONVERT(VARCHAR(255), HASHBYTES('SHA2_256', ISNULL(Password,'changeme')), 2),
    TRY_CAST(Cedula AS BIGINT), GETDATE(), GETDATE()
FROM SS_Faga.dbo.usuarios WHERE Id_usuario IS NOT NULL;
PRINT '   ✅ Faga: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' usuarios';

ALTER TABLE users WITH CHECK CHECK CONSTRAINT ALL;

PRINT '';
PRINT '📊 RESUMEN:';
SELECT a.nombre AS Aliado, COUNT(*) AS Total
FROM users u JOIN aliados a ON a.id = u.aliado_id
WHERE u.id_legacy IS NOT NULL
GROUP BY a.nombre ORDER BY a.nombre;

PRINT '';
PRINT '⚠️  IMPORTANTE: Los passwords están hasheados con SHA2_256 (no bcrypt).';
PRINT '   Ejecutar desde Laravel para hashear correctamente:';
PRINT '   php artisan tinker';
PRINT '   > \App\Models\User::whereNotNull(''id_legacy'')->get()->each(fn($u) => $u->forceFill([''password'' => bcrypt(''nueva_clave_temporal'')])->save())';
PRINT '';
PRINT '✅ Script 02 completo. Siguiente: 03_EMPRESAS.sql';
