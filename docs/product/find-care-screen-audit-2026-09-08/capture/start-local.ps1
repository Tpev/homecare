$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '../../../..')).Path
$runtimePath = Join-Path $projectRoot '.tmp/find-care-screen-audit-2026-09-08'
$nodePath = 'C:\Program Files\nodejs\node.exe'
New-Item -ItemType Directory -Path $runtimePath -Force | Out-Null
$launched = @()
foreach ($service in @(@{Name='app';Port=8028;Script='serve.mjs'}, @{Name='gallery';Port=8031;Script='serve-gallery.mjs'})) {
    $connection = Get-NetTCPConnection -State Listen -LocalPort $service.Port -ErrorAction SilentlyContinue
    if ($connection) { Write-Output "$($service.Name) already has a listener on $($service.Port)."; continue }
    $scriptPath = Join-Path $PSScriptRoot $service.Script
    $arguments = @('--preserve-symlinks', '--preserve-symlinks-main', ('"' + $scriptPath + '"'))
    $process = Start-Process -FilePath $nodePath -ArgumentList $arguments -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimePath ($service.Name + '.stdout.log')) -RedirectStandardError (Join-Path $runtimePath ($service.Name + '.stderr.log'))
    $launched += @{Name=$service.Name;Pid=$process.Id;Port=$service.Port;Script=$scriptPath}
}
$launched | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runtimePath 'local-processes.json')
Write-Output 'Application: http://127.0.0.1:8028/login'
Write-Output 'Post-implementation gallery: http://127.0.0.1:8031/'
