#Requires -RunAsAdministrator
<#
  Túnel de BryNex hacia Nueva EPS — instalación en el PC de la oficina.

  Nueva EPS rechaza la IP del servidor (netcup) y las de datacenter; este PC
  mantiene un túnel SSH inverso para que el servidor llegue al portal con la IP
  del ISP de la oficina. El destino es FIJO: desde el servidor solo se alcanza
  portal.nuevaeps.com.co:443, nunca la red de la oficina ni los servicios de
  este equipo. El tráfico sigue cifrado de punta a punta (TLS con Nueva EPS).

  Qué hace (idempotente, se puede volver a correr):
   1. Crea C:\ProgramData\BrynexTunel, accesible solo por SYSTEM y Administradores.
   2. Genera la llave SSH del túnel (si no existe) y fija la huella del servidor.
   3. Escribe tunel.ps1, que reconecta solo si se cae.
   4. Registra la tarea programada "BryNex tunel Nueva EPS" (SYSTEM, al arrancar
      y cada 5 minutos si no está corriendo). NO la inicia.
   5. Muestra la llave pública, que se instala en el servidor.

  Uso: PowerShell como administrador →  powershell -ExecutionPolicy Bypass -File .\instalar-pc-windows.ps1
#>
$ErrorActionPreference = 'Stop'

$dir    = 'C:\ProgramData\BrynexTunel'
$ssh    = "$env:WINDIR\System32\OpenSSH\ssh.exe"
$keygen = "$env:WINDIR\System32\OpenSSH\ssh-keygen.exe"
$tarea  = 'BryNex tunel Nueva EPS'

if (-not (Test-Path $ssh)) {
    throw 'Falta el cliente OpenSSH de Windows: Configuración > Aplicaciones > Características opcionales > Agregar "Cliente OpenSSH".'
}

# 1. Carpeta privada. SIDs y no nombres: en Windows en español el grupo se llama "Administradores".
New-Item -ItemType Directory -Force -Path $dir | Out-Null
icacls $dir /inheritance:r /grant:r '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' | Out-Null

# 2. Llave del túnel. OpenSSH de Windows rechaza llaves con permisos abiertos,
#    y la tarea corre como SYSTEM: dueño SYSTEM, acceso solo SYSTEM y Administradores.
$llave = Join-Path $dir 'id_ed25519'
if (-not (Test-Path $llave)) {
    # Start-Process con la línea completa: PowerShell 5.1 descarta los argumentos vacíos (-N "").
    Start-Process -FilePath $keygen -Wait -NoNewWindow `
        -ArgumentList "-q -t ed25519 -N `"`" -C tunel-nueva-eps@$env:COMPUTERNAME -f `"$llave`""
}
foreach ($f in @($llave, "$llave.pub")) {
    icacls $f /setowner '*S-1-5-18' | Out-Null
    icacls $f /inheritance:r /grant:r '*S-1-5-18:F' '*S-1-5-32-544:F' | Out-Null
}

# Huella fija del servidor: si algún día cambia, el túnel no conecta (en vez de hablarle a otro).
Set-Content -Path (Join-Path $dir 'known_hosts') -Encoding ascii -Value `
    '159.195.233.132 ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAILI9SX/CNPHG/vGvc7tKDfMlVCEeWpc4QjD3rAXDnOiz'

# 3. El bucle que mantiene el túnel.
$script = @'
$dir = 'C:\ProgramData\BrynexTunel'
$log = Join-Path $dir 'tunel.log'

function Anotar([string]$m) {
    if ((Test-Path $log) -and (Get-Item $log).Length -gt 1MB) { Move-Item $log "$log.1" -Force }
    Add-Content -Path $log -Value ('{0:yyyy-MM-dd HH:mm:ss} {1}' -f (Get-Date), $m)
}

while ($true) {
    Anotar 'Conectando al servidor de BryNex...'
    # -R con destino fijo: el servidor escucha en su 127.0.0.1:18443 y este PC
    # lo reenvía solo a portal.nuevaeps.com.co:443. ExitOnForwardFailure hace que
    # ssh salga (y se reintente) si el puerto quedó ocupado por una conexión muerta.
    $salida = & "$env:WINDIR\System32\OpenSSH\ssh.exe" -N -T `
        -i "$dir\id_ed25519" -o IdentitiesOnly=yes -o BatchMode=yes `
        -o "UserKnownHostsFile=$dir\known_hosts" -o StrictHostKeyChecking=yes `
        -o ServerAliveInterval=30 -o ServerAliveCountMax=3 `
        -o ExitOnForwardFailure=yes -o ConnectTimeout=20 `
        -R 127.0.0.1:18443:portal.nuevaeps.com.co:443 `
        tunelnep@159.195.233.132 2>&1
    Anotar ("El túnel se cerró (código $LASTEXITCODE). " + (($salida | Out-String).Trim()))
    Start-Sleep -Seconds 20
}
'@
Set-Content -Path (Join-Path $dir 'tunel.ps1') -Value $script -Encoding UTF8

# 4. Tarea programada como SYSTEM: corre sin sesión iniciada.
$accion = New-ScheduledTaskAction -Execute 'powershell.exe' `
    -Argument "-NoProfile -NonInteractive -ExecutionPolicy Bypass -File `"$dir\tunel.ps1`""
$disparadores = @(
    (New-ScheduledTaskTrigger -AtStartup),
    # Red de seguridad: si el proceso murió, lo relanza; si ya corre, no hace nada (IgnoreNew).
    (New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 5))
)
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
$ajustes = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable `
    -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew `
    -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1)
Register-ScheduledTask -TaskName $tarea -Action $accion -Trigger $disparadores `
    -Principal $principal -Settings $ajustes -Force | Out-Null
# Registrada pero detenida hasta que la llave esté en el servidor.
Disable-ScheduledTask -TaskName $tarea | Out-Null

# 5. Resultado.
Write-Host ''
Write-Host "Listo. Tarea '$tarea' registrada (deshabilitada hasta instalar la llave en el servidor)." -ForegroundColor Green
Write-Host 'Llave pública (esto SÍ se puede compartir; la privada nunca sale de este PC):' -ForegroundColor Yellow
Get-Content "$llave.pub"
Write-Host ''
Write-Host 'Recuerda: este PC no debe suspenderse. Configuración > Sistema > Inicio/apagado y suspensión > "Nunca" con corriente.'
