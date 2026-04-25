-- =============================================================================
-- INSPECCIÓN DE TABLAS LEGACY - BRYGAR_BD
-- Ejecutar en SSMS conectado a 200.29.120.228,1533
-- Copiar y pegar el resultado completo
-- =============================================================================

-- ─── Tablas a inspeccionar ───────────────────────────────────────────────────
DECLARE @tables TABLE (tbl NVARCHAR(100))
INSERT INTO @tables VALUES
    ('usuarios'),
    ('Razon_Social'),
    ('Empresas'),
    ('Asesores'),
    ('Bancos_cuentas'),
    ('Base_De_Datos'),
    ('Contratos'),
    ('beneficiarios'),
    ('facturacion'),
    ('Planos'),
    ('claves'),
    ('incapacidades'),
    ('Gestion_Incapacidades'),
    ('gastos'),
    ('movimientos_bancos'),
    ('tareas'),
    ('bitacora_afiliaciones')

-- ─── 1. CONTEO DE REGISTROS ───────────────────────────────────────────────────
PRINT '============================================================'
PRINT 'CONTEO DE REGISTROS EN BRYGAR_BD'
PRINT '============================================================'

DECLARE @tbl NVARCHAR(100), @sql NVARCHAR(500)
DECLARE cur CURSOR FOR SELECT tbl FROM @tables
OPEN cur
FETCH NEXT FROM cur INTO @tbl
WHILE @@FETCH_STATUS = 0
BEGIN
    IF OBJECT_ID('Brygar_BD.dbo.' + @tbl, 'U') IS NOT NULL
    BEGIN
        SET @sql = 'SELECT ''' + @tbl + ''' AS Tabla, COUNT(*) AS Registros FROM Brygar_BD.dbo.[' + @tbl + ']'
        EXEC(@sql)
    END
    ELSE
        SELECT @tbl AS Tabla, -1 AS Registros, '⚠️ NO EXISTE' AS Nota
    FETCH NEXT FROM cur INTO @tbl
END
CLOSE cur; DEALLOCATE cur

-- ─── 2. ESTRUCTURA DE CADA TABLA ─────────────────────────────────────────────
PRINT ''
PRINT '============================================================'
PRINT 'ESTRUCTURA DE COLUMNAS EN BRYGAR_BD'
PRINT '============================================================'

SELECT
    t.name                                          AS Tabla,
    c.column_id                                     AS Orden,
    c.name                                          AS Columna,
    tp.name                                         AS Tipo,
    CASE
        WHEN tp.name IN ('nvarchar','varchar','char','nchar')
            THEN CAST(c.max_length AS VARCHAR) + ' bytes'
        ELSE ''
    END                                             AS Longitud,
    CASE c.is_nullable WHEN 1 THEN 'SI' ELSE 'NO' END AS Nullable,
    CASE ic.is_identity WHEN 1 THEN '✓ IDENTITY' ELSE '' END AS Identidad,
    CASE WHEN pk.column_id IS NOT NULL THEN '✓ PK' ELSE '' END AS PK
FROM Brygar_BD.sys.tables t
JOIN Brygar_BD.sys.columns c ON c.object_id = t.object_id
JOIN Brygar_BD.sys.types tp  ON tp.user_type_id = c.user_type_id
LEFT JOIN Brygar_BD.sys.identity_columns ic ON ic.object_id = c.object_id AND ic.column_id = c.column_id
LEFT JOIN (
    SELECT ic2.object_id, ic2.column_id
    FROM Brygar_BD.sys.index_columns ic2
    JOIN Brygar_BD.sys.indexes i ON i.object_id = ic2.object_id AND i.index_id = ic2.index_id
    WHERE i.is_primary_key = 1
) pk ON pk.object_id = c.object_id AND pk.column_id = c.column_id
WHERE t.name IN (
    'usuarios','Razon_Social','Empresas','Asesores','Bancos_cuentas',
    'Base_De_Datos','Contratos','beneficiarios','facturacion','Planos',
    'claves','incapacidades','Gestion_Incapacidades','gastos',
    'movimientos_bancos','tareas','bitacora_afiliaciones'
)
ORDER BY t.name, c.column_id

-- ─── 3. MUESTRA DE DATOS (2 filas por tabla) ─────────────────────────────────
PRINT ''
PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - usuarios'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.usuarios

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - Razon_Social'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.Razon_Social

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - Empresas'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.Empresas

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - Asesores'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.Asesores

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - Bancos_cuentas'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.Bancos_cuentas

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - Base_De_Datos (clientes)'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.Base_De_Datos

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - Contratos'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.Contratos

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - beneficiarios'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.beneficiarios

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - facturacion'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.facturacion

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - Planos'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.Planos

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - claves'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.claves

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - incapacidades'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.incapacidades

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - Gestion_Incapacidades'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.Gestion_Incapacidades

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - gastos'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.gastos

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - movimientos_bancos'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.movimientos_bancos

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - tareas'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.tareas

PRINT '============================================================'
PRINT 'MUESTRA 2 FILAS - bitacora_afiliaciones'
PRINT '============================================================'
SELECT TOP 2 * FROM Brygar_BD.dbo.bitacora_afiliaciones
