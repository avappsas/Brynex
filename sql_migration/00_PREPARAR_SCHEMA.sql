-- =============================================================================
-- SCRIPT 00: PREPARACIÓN DEL ESQUEMA EN BRYNEX
-- Ejecutar PRIMERO antes de cualquier migración
-- Servidor: 200.29.120.228,1533  |  BD: BryNex
-- =============================================================================
-- ⚠️  ADVERTENCIA: Este script BORRA los datos existentes de las tablas de aliados.
--     Solo usar en la BD de PRUEBAS.
-- =============================================================================

USE BryNex;
GO

PRINT '🔵 Iniciando preparación del esquema...'
PRINT ''

-- =============================================================================
-- PASO 1: LIMPIAR DATOS EXISTENTES (orden inverso de FKs)
-- =============================================================================
PRINT '1. Limpiando datos existentes...'

-- Desactivar FK checks temporalmente
EXEC sp_MSforeachtable 'ALTER TABLE ? NOCHECK CONSTRAINT ALL'

DELETE FROM BryNex.dbo.gestiones_incapacidad;
DELETE FROM BryNex.dbo.incapacidades;
DELETE FROM BryNex.dbo.radicado_movimientos;
DELETE FROM BryNex.dbo.radicados;
DELETE FROM BryNex.dbo.tarea_gestiones;
DELETE FROM BryNex.dbo.tarea_documentos;
DELETE FROM BryNex.dbo.tareas;
DELETE FROM BryNex.dbo.planos;
DELETE FROM BryNex.dbo.abonos;
DELETE FROM BryNex.dbo.consignacion_factura;
DELETE FROM BryNex.dbo.consignaciones;
DELETE FROM BryNex.dbo.facturas;
DELETE FROM BryNex.dbo.factura_secuencias;
DELETE FROM BryNex.dbo.bitacora_cobros;
DELETE FROM BryNex.dbo.bitacora;
DELETE FROM BryNex.dbo.documentos_cliente;
DELETE FROM BryNex.dbo.beneficiarios;
DELETE FROM BryNex.dbo.clave_accesos;
DELETE FROM BryNex.dbo.comisiones_asesores;
DELETE FROM BryNex.dbo.contratos;
DELETE FROM BryNex.dbo.clientes;
DELETE FROM BryNex.dbo.empresas;
DELETE FROM BryNex.dbo.asesores;
DELETE FROM BryNex.dbo.razones_sociales;
DELETE FROM BryNex.dbo.banco_cuentas;
DELETE FROM BryNex.dbo.gastos;
DELETE FROM BryNex.dbo.saldos_banco;
DELETE FROM BryNex.dbo.cuadres;

-- Reactivar FK
EXEC sp_MSforeachtable 'ALTER TABLE ? WITH CHECK CHECK CONSTRAINT ALL'

PRINT '   ✅ Datos limpiados.'
PRINT ''

-- =============================================================================
-- PASO 2: VERIFICAR QUE LOS ALIADOS EXISTEN
-- (Se insertan manualmente o ya existen)
-- =============================================================================
PRINT '2. Verificando aliados...'

-- Insertar aliados si no existen
-- ⚠️  Ajustar NITs y nombres reales si son diferentes
IF NOT EXISTS (SELECT 1 FROM BryNex.dbo.aliados WHERE nit = '901918923')
    INSERT INTO BryNex.dbo.aliados (nombre, nit, razon_social, activo, created_at, updated_at)
    VALUES ('Brygar', '901918923', 'Brygar SAS', 1, GETDATE(), GETDATE());

IF NOT EXISTS (SELECT 1 FROM BryNex.dbo.aliados WHERE nombre = 'GiMave Integral')
    INSERT INTO BryNex.dbo.aliados (nombre, nit, razon_social, activo, created_at, updated_at)
    VALUES ('GiMave Integral', NULL, 'GiMave Integral', 1, GETDATE(), GETDATE());

IF NOT EXISTS (SELECT 1 FROM BryNex.dbo.aliados WHERE nombre = 'Grupo Fecop')
    INSERT INTO BryNex.dbo.aliados (nombre, nit, razon_social, activo, created_at, updated_at)
    VALUES ('Grupo Fecop', NULL, 'Grupo Fecop', 1, GETDATE(), GETDATE());

IF NOT EXISTS (SELECT 1 FROM BryNex.dbo.aliados WHERE nombre = 'Luis Lopez')
    INSERT INTO BryNex.dbo.aliados (nombre, nit, razon_social, activo, created_at, updated_at)
    VALUES ('Luis Lopez', NULL, 'Luis Lopez', 1, GETDATE(), GETDATE());

IF NOT EXISTS (SELECT 1 FROM BryNex.dbo.aliados WHERE nombre = 'Mave Anderson')
    INSERT INTO BryNex.dbo.aliados (nombre, nit, razon_social, activo, created_at, updated_at)
    VALUES ('Mave Anderson', NULL, 'Mave Anderson', 1, GETDATE(), GETDATE());

IF NOT EXISTS (SELECT 1 FROM BryNex.dbo.aliados WHERE nombre = 'SS Faga')
    INSERT INTO BryNex.dbo.aliados (nombre, nit, razon_social, activo, created_at, updated_at)
    VALUES ('SS Faga', NULL, 'SS Faga', 1, GETDATE(), GETDATE());

SELECT id, nombre, nit FROM BryNex.dbo.aliados ORDER BY id;
PRINT '   ✅ Aliados verificados. ANOTA los IDs de arriba, los necesitarás.'
PRINT ''

-- =============================================================================
-- PASO 3: AGREGAR COLUMNA id_legacy A TABLAS QUE LO NECESITAN
-- Solo se agrega si no existe ya
-- =============================================================================
PRINT '3. Agregando columnas id_legacy...'

-- razones_sociales
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('razones_sociales') AND name = 'id_legacy')
    ALTER TABLE BryNex.dbo.razones_sociales ADD id_legacy INT NULL;

-- empresas
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('empresas') AND name = 'id_legacy')
    ALTER TABLE BryNex.dbo.empresas ADD id_legacy INT NULL;

-- asesores
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('asesores') AND name = 'id_legacy')
    ALTER TABLE BryNex.dbo.asesores ADD id_legacy INT NULL;

-- banco_cuentas
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('banco_cuentas') AND name = 'id_legacy')
    ALTER TABLE BryNex.dbo.banco_cuentas ADD id_legacy INT NULL;

-- users (para mapear usuarios legacy)
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'id_legacy')
    ALTER TABLE BryNex.dbo.users ADD id_legacy INT NULL;

-- clientes
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('clientes') AND name = 'id_legacy')
    ALTER TABLE BryNex.dbo.clientes ADD id_legacy INT NULL;

-- contratos
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('contratos') AND name = 'id_legacy')
    ALTER TABLE BryNex.dbo.contratos ADD id_legacy INT NULL;

-- facturas
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('facturas') AND name = 'id_legacy')
    ALTER TABLE BryNex.dbo.facturas ADD id_legacy INT NULL;

-- incapacidades
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('incapacidades') AND name = 'id_legacy')
    ALTER TABLE BryNex.dbo.incapacidades ADD id_legacy INT NULL;

PRINT '   ✅ Columnas id_legacy agregadas.'
PRINT ''

-- =============================================================================
-- PASO 4: VERIFICAR ESTRUCTURA (confirmar que todo quedó bien)
-- =============================================================================
PRINT '4. Verificando columnas id_legacy...'

SELECT
    t.name AS Tabla,
    c.name AS Columna,
    tp.name AS Tipo
FROM sys.tables t
JOIN sys.columns c ON c.object_id = t.object_id
JOIN sys.types tp ON tp.user_type_id = c.user_type_id
WHERE c.name = 'id_legacy'
ORDER BY t.name;

PRINT ''
PRINT '✅ PREPARACIÓN COMPLETA. Ya puedes ejecutar los scripts de migración.'
PRINT ''
PRINT 'IMPORTANTE: Antes del siguiente script, anota los IDs de los aliados'
PRINT 'que aparecieron en el paso 2.'
