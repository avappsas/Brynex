<?php
// Inspeccionar tabla Empresas en Brygar_BD
$pdo = new PDO('sqlsrv:Server=200.29.120.228,1533;Database=Brygar_BD;Encrypt=false;TrustServerCertificate=true','Brygar','Brygar.3000');

// Columnas
echo "=== COLUMNAS Empresas ===\n";
$r = $pdo->query("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
                  FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_NAME='Empresas'
                  ORDER BY ORDINAL_POSITION");
foreach($r->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo $c['COLUMN_NAME'].' | '.$c['DATA_TYPE'].'('.$c['CHARACTER_MAXIMUM_LENGTH'].') | NULL:'.$c['IS_NULLABLE']."\n";
}

// Muestra 5 registros
echo "\n=== MUESTRA (5 rows) ===\n";
$rows = $pdo->query("SELECT TOP 5 * FROM Empresas")->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $row) {
    foreach($row as $k => $v) echo "$k: $v | ";
    echo "\n";
}

// Count
$cnt = $pdo->query("SELECT COUNT(*) FROM Empresas")->fetchColumn();
echo "\nTotal Empresas: $cnt\n";

// Ver qué cod_empresa tienen los clientes (top distinctos)
echo "\n=== cod_empresa en Base_De_Datos (top 10) ===\n";
$d = $pdo->query("SELECT TOP 10 cod_empresa, COUNT(*) as total FROM Base_De_Datos GROUP BY cod_empresa ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach($d as $row) echo "cod_empresa: {$row['cod_empresa']} -> {$row['total']} clientes\n";
