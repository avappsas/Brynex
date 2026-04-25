-- =============================================================================
-- SCRIPT 01 (CORREGIDO): MIGRAR RAZONES SOCIALES
-- Legacy: [BD].dbo.Razon_Social  →  BryNex.dbo.razones_sociales
-- NOTA: Razon_Social.ID en legacy = NIT para empresas reales, negativo para especiales
-- =============================================================================
USE BryNex;
GO

DECLARE @id_brygar    BIGINT = (SELECT id FROM aliados WHERE nit = '901918923');
DECLARE @id_gimave    BIGINT = (SELECT id FROM aliados WHERE nombre = 'GiMave Integral');
DECLARE @id_fecop     BIGINT = (SELECT id FROM aliados WHERE nombre = 'Grupo Fecop');
DECLARE @id_luislopez BIGINT = (SELECT id FROM aliados WHERE nombre = 'Luis Lopez');
DECLARE @id_mave      BIGINT = (SELECT id FROM aliados WHERE nombre = 'Mave Anderson');
DECLARE @id_faga      BIGINT = (SELECT id FROM aliados WHERE nombre = 'SS Faga');

-- Deshabilitar FKs para insertar sin restricciones
ALTER TABLE razones_sociales NOCHECK CONSTRAINT ALL;

-- ── Macro de inserción (repetida por aliado) ──────────────────────────────────
-- BRYGAR
PRINT '🔵 Brygar_BD → razones_sociales...';
INSERT INTO razones_sociales (
    aliado_id, id_legacy, dv, razon_social, estado, plan,
    direccion, telefonos, correos, actividad_economica, objeto_social,
    observacion, salario_minimo, arl_nit, caja_nit,
    mes_pagos, anio_pagos, n_plano,
    fecha_constitucion, fecha_limite_pago, dia_habil,
    forma_presentacion, codigo_sucursal, nombre_sucursal,
    notas_factura1, notas_factura2,
    dir_formulario, tel_formulario, correo_formulario,
    cedula_rep, nombre_rep, encargado
)
SELECT
    @id_brygar, ID, DV,
    LTRIM(RTRIM(ISNULL(Razon_Social,''))),
    LTRIM(RTRIM(ISNULL(Estado,''))),
    LTRIM(RTRIM(ISNULL(Plan,''))),
    LTRIM(RTRIM(ISNULL(Direccion,''))),
    LTRIM(RTRIM(ISNULL(Telefonos,''))),
    LTRIM(RTRIM(ISNULL(Correos,''))),
    LTRIM(RTRIM(ISNULL(Actividad_Economica,''))),
    LTRIM(RTRIM(ISNULL(Objeto_Social,''))),
    LTRIM(RTRIM(ISNULL(Observacion,''))),       -- legacy también se llama Observacion
    ISNULL(Salario_Minimo, 0),
    TRY_CAST(ARL  AS BIGINT),                   -- NIT del ARL
    TRY_CAST(CAJA AS BIGINT),                   -- NIT de la caja
    MES_PAGOS,
    ISNULL([AÑO_PAGOS], NULL),
    N_PLANO,
    TRY_CAST(Fecha_Constitucion AS DATETIME),
    TRY_CAST(Fecha_Limite_pago  AS DATETIME),
    Dia_Habil,
    LTRIM(RTRIM(ISNULL(Forma_Presentacion,''))),
    LTRIM(RTRIM(ISNULL(Codigo_Sucursal_Aportante,''))),
    LTRIM(RTRIM(ISNULL(Nombre_Sucursal,''))),
    LTRIM(RTRIM(ISNULL(Notas_Factura1,''))),
    LTRIM(RTRIM(ISNULL(Notas_Factura2,''))),
    LTRIM(RTRIM(ISNULL(Dir_Formulario,''))),
    LTRIM(RTRIM(ISNULL(Tel_Formulario,''))),
    LTRIM(RTRIM(ISNULL(Correo_Formulario,''))),
    TRY_CAST(Cedula_Rep AS BIGINT),
    LTRIM(RTRIM(ISNULL(Nombre_Rep,''))),
    NULL
FROM Brygar_BD.dbo.Razon_Social;
PRINT '   ✅ Brygar: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' filas';

-- GIMAVE
PRINT '🔵 GiMave_Integral → razones_sociales...';
INSERT INTO razones_sociales (aliado_id, id_legacy, dv, razon_social, estado, plan, direccion, telefonos, correos, actividad_economica, objeto_social, observacion, salario_minimo, arl_nit, caja_nit, mes_pagos, anio_pagos, n_plano, fecha_constitucion, fecha_limite_pago, dia_habil, forma_presentacion, codigo_sucursal, nombre_sucursal, notas_factura1, notas_factura2, dir_formulario, tel_formulario, correo_formulario, cedula_rep, nombre_rep, encargado)
SELECT @id_gimave, ID, DV, LTRIM(RTRIM(ISNULL(Razon_Social,''))), LTRIM(RTRIM(ISNULL(Estado,''))), LTRIM(RTRIM(ISNULL(Plan,''))), LTRIM(RTRIM(ISNULL(Direccion,''))), LTRIM(RTRIM(ISNULL(Telefonos,''))), LTRIM(RTRIM(ISNULL(Correos,''))), LTRIM(RTRIM(ISNULL(Actividad_Economica,''))), LTRIM(RTRIM(ISNULL(Objeto_Social,''))), LTRIM(RTRIM(ISNULL(Observacion,''))), ISNULL(Salario_Minimo,0), TRY_CAST(ARL AS BIGINT), TRY_CAST(CAJA AS BIGINT), MES_PAGOS, ISNULL([AÑO_PAGOS],NULL), N_PLANO, TRY_CAST(Fecha_Constitucion AS DATETIME), TRY_CAST(Fecha_Limite_pago AS DATETIME), Dia_Habil, LTRIM(RTRIM(ISNULL(Forma_Presentacion,''))), LTRIM(RTRIM(ISNULL(Codigo_Sucursal_Aportante,''))), LTRIM(RTRIM(ISNULL(Nombre_Sucursal,''))), LTRIM(RTRIM(ISNULL(Notas_Factura1,''))), LTRIM(RTRIM(ISNULL(Notas_Factura2,''))), LTRIM(RTRIM(ISNULL(Dir_Formulario,''))), LTRIM(RTRIM(ISNULL(Tel_Formulario,''))), LTRIM(RTRIM(ISNULL(Correo_Formulario,''))), TRY_CAST(Cedula_Rep AS BIGINT), LTRIM(RTRIM(ISNULL(Nombre_Rep,''))), NULL
FROM GiMave_Integral.dbo.Razon_Social;
PRINT '   ✅ GiMave: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' filas';

-- FECOP
PRINT '🔵 Grupo_Fecop → razones_sociales...';
INSERT INTO razones_sociales (aliado_id, id_legacy, dv, razon_social, estado, plan, direccion, telefonos, correos, actividad_economica, objeto_social, observacion, salario_minimo, arl_nit, caja_nit, mes_pagos, anio_pagos, n_plano, fecha_constitucion, fecha_limite_pago, dia_habil, forma_presentacion, codigo_sucursal, nombre_sucursal, notas_factura1, notas_factura2, dir_formulario, tel_formulario, correo_formulario, cedula_rep, nombre_rep, encargado)
SELECT @id_fecop, ID, DV, LTRIM(RTRIM(ISNULL(Razon_Social,''))), LTRIM(RTRIM(ISNULL(Estado,''))), LTRIM(RTRIM(ISNULL(Plan,''))), LTRIM(RTRIM(ISNULL(Direccion,''))), LTRIM(RTRIM(ISNULL(Telefonos,''))), LTRIM(RTRIM(ISNULL(Correos,''))), LTRIM(RTRIM(ISNULL(Actividad_Economica,''))), LTRIM(RTRIM(ISNULL(Objeto_Social,''))), LTRIM(RTRIM(ISNULL(Observacion,''))), ISNULL(Salario_Minimo,0), TRY_CAST(ARL AS BIGINT), TRY_CAST(CAJA AS BIGINT), MES_PAGOS, ISNULL([AÑO_PAGOS],NULL), N_PLANO, TRY_CAST(Fecha_Constitucion AS DATETIME), TRY_CAST(Fecha_Limite_pago AS DATETIME), Dia_Habil, LTRIM(RTRIM(ISNULL(Forma_Presentacion,''))), LTRIM(RTRIM(ISNULL(Codigo_Sucursal_Aportante,''))), LTRIM(RTRIM(ISNULL(Nombre_Sucursal,''))), LTRIM(RTRIM(ISNULL(Notas_Factura1,''))), LTRIM(RTRIM(ISNULL(Notas_Factura2,''))), LTRIM(RTRIM(ISNULL(Dir_Formulario,''))), LTRIM(RTRIM(ISNULL(Tel_Formulario,''))), LTRIM(RTRIM(ISNULL(Correo_Formulario,''))), TRY_CAST(Cedula_Rep AS BIGINT), LTRIM(RTRIM(ISNULL(Nombre_Rep,''))), NULL
FROM Grupo_Fecop.dbo.Razon_Social;
PRINT '   ✅ Fecop: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' filas';

-- LUISLOPEZ
PRINT '🔵 LuisLopez → razones_sociales...';
INSERT INTO razones_sociales (aliado_id, id_legacy, dv, razon_social, estado, plan, direccion, telefonos, correos, actividad_economica, objeto_social, observacion, salario_minimo, arl_nit, caja_nit, mes_pagos, anio_pagos, n_plano, fecha_constitucion, fecha_limite_pago, dia_habil, forma_presentacion, codigo_sucursal, nombre_sucursal, notas_factura1, notas_factura2, dir_formulario, tel_formulario, correo_formulario, cedula_rep, nombre_rep, encargado)
SELECT @id_luislopez, ID, DV, LTRIM(RTRIM(ISNULL(Razon_Social,''))), LTRIM(RTRIM(ISNULL(Estado,''))), LTRIM(RTRIM(ISNULL(Plan,''))), LTRIM(RTRIM(ISNULL(Direccion,''))), LTRIM(RTRIM(ISNULL(Correos,''))), LTRIM(RTRIM(ISNULL(Correos,''))), LTRIM(RTRIM(ISNULL(Actividad_Economica,''))), LTRIM(RTRIM(ISNULL(Objeto_Social,''))), LTRIM(RTRIM(ISNULL(Observacion,''))), ISNULL(Salario_Minimo,0), TRY_CAST(ARL AS BIGINT), TRY_CAST(CAJA AS BIGINT), MES_PAGOS, ISNULL([AÑO_PAGOS],NULL), N_PLANO, TRY_CAST(Fecha_Constitucion AS DATETIME), TRY_CAST(Fecha_Limite_pago AS DATETIME), Dia_Habil, LTRIM(RTRIM(ISNULL(Forma_Presentacion,''))), LTRIM(RTRIM(ISNULL(Codigo_Sucursal_Aportante,''))), LTRIM(RTRIM(ISNULL(Nombre_Sucursal,''))), LTRIM(RTRIM(ISNULL(Notas_Factura1,''))), LTRIM(RTRIM(ISNULL(Notas_Factura2,''))), LTRIM(RTRIM(ISNULL(Dir_Formulario,''))), LTRIM(RTRIM(ISNULL(Tel_Formulario,''))), LTRIM(RTRIM(ISNULL(Correo_Formulario,''))), TRY_CAST(Cedula_Rep AS BIGINT), LTRIM(RTRIM(ISNULL(Nombre_Rep,''))), NULL
FROM LuisLopez.dbo.Razon_Social;
PRINT '   ✅ LuisLopez: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' filas';

-- MAVE
PRINT '🔵 Mave_Anderson → razones_sociales...';
INSERT INTO razones_sociales (aliado_id, id_legacy, dv, razon_social, estado, plan, direccion, telefonos, correos, actividad_economica, objeto_social, observacion, salario_minimo, arl_nit, caja_nit, mes_pagos, anio_pagos, n_plano, fecha_constitucion, fecha_limite_pago, dia_habil, forma_presentacion, codigo_sucursal, nombre_sucursal, notas_factura1, notas_factura2, dir_formulario, tel_formulario, correo_formulario, cedula_rep, nombre_rep, encargado)
SELECT @id_mave, ID, DV, LTRIM(RTRIM(ISNULL(Razon_Social,''))), LTRIM(RTRIM(ISNULL(Estado,''))), LTRIM(RTRIM(ISNULL(Plan,''))), LTRIM(RTRIM(ISNULL(Direccion,''))), LTRIM(RTRIM(ISNULL(Telefonos,''))), LTRIM(RTRIM(ISNULL(Correos,''))), LTRIM(RTRIM(ISNULL(Actividad_Economica,''))), LTRIM(RTRIM(ISNULL(Objeto_Social,''))), LTRIM(RTRIM(ISNULL(Observacion,''))), ISNULL(Salario_Minimo,0), TRY_CAST(ARL AS BIGINT), TRY_CAST(CAJA AS BIGINT), MES_PAGOS, ISNULL([AÑO_PAGOS],NULL), N_PLANO, TRY_CAST(Fecha_Constitucion AS DATETIME), TRY_CAST(Fecha_Limite_pago AS DATETIME), Dia_Habil, LTRIM(RTRIM(ISNULL(Forma_Presentacion,''))), LTRIM(RTRIM(ISNULL(Codigo_Sucursal_Aportante,''))), LTRIM(RTRIM(ISNULL(Nombre_Sucursal,''))), LTRIM(RTRIM(ISNULL(Notas_Factura1,''))), LTRIM(RTRIM(ISNULL(Notas_Factura2,''))), LTRIM(RTRIM(ISNULL(Dir_Formulario,''))), LTRIM(RTRIM(ISNULL(Tel_Formulario,''))), LTRIM(RTRIM(ISNULL(Correo_Formulario,''))), TRY_CAST(Cedula_Rep AS BIGINT), LTRIM(RTRIM(ISNULL(Nombre_Rep,''))), NULL
FROM Mave_Anderson.dbo.Razon_Social;
PRINT '   ✅ Mave: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' filas';

-- FAGA
PRINT '🔵 SS_Faga → razones_sociales...';
INSERT INTO razones_sociales (aliado_id, id_legacy, dv, razon_social, estado, plan, direccion, telefonos, correos, actividad_economica, objeto_social, observacion, salario_minimo, arl_nit, caja_nit, mes_pagos, anio_pagos, n_plano, fecha_constitucion, fecha_limite_pago, dia_habil, forma_presentacion, codigo_sucursal, nombre_sucursal, notas_factura1, notas_factura2, dir_formulario, tel_formulario, correo_formulario, cedula_rep, nombre_rep, encargado)
SELECT @id_faga, ID, DV, LTRIM(RTRIM(ISNULL(Razon_Social,''))), LTRIM(RTRIM(ISNULL(Estado,''))), LTRIM(RTRIM(ISNULL(Plan,''))), LTRIM(RTRIM(ISNULL(Direccion,''))), LTRIM(RTRIM(ISNULL(Telefonos,''))), LTRIM(RTRIM(ISNULL(Correos,''))), LTRIM(RTRIM(ISNULL(Actividad_Economica,''))), LTRIM(RTRIM(ISNULL(Objeto_Social,''))), LTRIM(RTRIM(ISNULL(Observacion,''))), ISNULL(Salario_Minimo,0), TRY_CAST(ARL AS BIGINT), TRY_CAST(CAJA AS BIGINT), MES_PAGOS, ISNULL([AÑO_PAGOS],NULL), N_PLANO, TRY_CAST(Fecha_Constitucion AS DATETIME), TRY_CAST(Fecha_Limite_pago AS DATETIME), Dia_Habil, LTRIM(RTRIM(ISNULL(Forma_Presentacion,''))), LTRIM(RTRIM(ISNULL(Codigo_Sucursal_Aportante,''))), LTRIM(RTRIM(ISNULL(Nombre_Sucursal,''))), LTRIM(RTRIM(ISNULL(Notas_Factura1,''))), LTRIM(RTRIM(ISNULL(Notas_Factura2,''))), LTRIM(RTRIM(ISNULL(Dir_Formulario,''))), LTRIM(RTRIM(ISNULL(Tel_Formulario,''))), LTRIM(RTRIM(ISNULL(Correo_Formulario,''))), TRY_CAST(Cedula_Rep AS BIGINT), LTRIM(RTRIM(ISNULL(Nombre_Rep,''))), NULL
FROM SS_Faga.dbo.Razon_Social;
PRINT '   ✅ Faga: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' filas';

-- Rehabilitar FKs
ALTER TABLE razones_sociales WITH CHECK CHECK CONSTRAINT ALL;

-- Resumen
PRINT '';
PRINT '📊 RESUMEN:';
SELECT a.nombre AS Aliado, COUNT(*) AS Total
FROM razones_sociales rs JOIN aliados a ON a.id = rs.aliado_id
GROUP BY a.nombre ORDER BY a.nombre;

PRINT '✅ Script 01 completo. Siguiente: 02_USUARIOS.sql';
