$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$Script = Join-Path $Root 'deploy\windows\Renew-TagoreCertificate.ps1'
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -ExecutionPolicy Bypass -File "' + $Script + '"')
$trigger = New-ScheduledTaskTrigger -Daily -At 1:00AM
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskName "Tagore ERP - Renew IP TLS Certificate" -Action $action -Trigger $trigger -Principal $principal -Force | Out-Null
Write-Host "Scheduled daily Tagore ERP IP certificate renewal check at 01:00." -ForegroundColor Green
