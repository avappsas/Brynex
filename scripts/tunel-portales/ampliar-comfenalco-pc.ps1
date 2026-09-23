#Requires -RunAsAdministrator
<#
  Amplía el túnel de la oficina para que también llegue a Comfenalco Valle.

  El PC ya mantiene el túnel de Nueva EPS (C:\ProgramData\BrynexTunel\tunel.ps1).
  Esto le añade dos reenvíos más, uno por dominio, y reinicia la tarea:

    18444 → virtual.comfenalcovalle.com.co:443   (la Sucursal Virtual)
    18445 → authcomfeempresasprod.web.app:443    (el login de AuthComfe)

  Siguen siendo destinos FIJOS: desde el servidor no se alcanza nada más de esta
  red —ni el SQL Server de este PC—, y el TLS sigue siendo de punta a punta con
  cada portal. Antes hay que haber corrido en el servidor
  scripts/tunel-portales/ampliar-comfenalco.sh, que es lo que permite esos dos
  puertos.

  Uso: PowerShell como administrador →
     powershell -ExecutionPolicy Bypass -File .\ampliar-comfenalco-pc.ps1
#>
$ErrorActionPreference = 'Stop'

$dir     = 'C:\ProgramData\BrynexTunel'
$archivo = Join-Path $dir 'tunel.ps1'
$tarea   = 'BryNex tunel Nueva EPS'
$viejo   = '-R 127.0.0.1:18443:portal.nuevaeps.com.co:443 `'
$nuevo   = @'
-R 127.0.0.1:18443:portal.nuevaeps.com.co:443 `
        -R 127.0.0.1:18444:virtual.comfenalcovalle.com.co:443 `
        -R 127.0.0.1:18445:authcomfeempresasprod.web.app:443 `
'@

if (-not (Test-Path $archivo)) {
    throw "No está $archivo. Corre antes el instalador de scripts/tunel-nueva-eps/."
}

$texto = Get-Content $archivo -Raw

if ($texto -match 'comfenalcovalle') {
    Write-Host 'El túnel ya incluía Comfenalco. Nada que cambiar.'
    exit 0
}

if ($texto -notmatch [regex]::Escape($viejo)) {
    throw 'El tunel.ps1 no tiene la línea esperada del reenvío; revísalo a mano.'
}

Copy-Item $archivo "$archivo.antes-comfenalco" -Force
Set-Content -Path $archivo -Value $texto.Replace($viejo, $nuevo.TrimEnd("`r", "`n")) -Encoding UTF8

# Reiniciar la tarea para que el túnel se vuelva a abrir con los tres reenvíos.
Stop-ScheduledTask -TaskName $tarea -ErrorAction SilentlyContinue
Get-Process ssh -ErrorAction SilentlyContinue |
    Where-Object { $_.Path -like '*OpenSSH*' } |
    Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 3
Start-ScheduledTask -TaskName $tarea

Write-Host 'Listo. Túnel ampliado a Comfenalco; el log está en' (Join-Path $dir 'tunel.log')
