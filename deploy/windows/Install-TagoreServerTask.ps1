$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$CertificateScript = Join-Path $Root 'deploy\windows\Renew-TagoreCertificate.ps1'
$SchedulerScript = Join-Path $Root 'deploy\windows\Run-TagoreScheduler.ps1'
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

$certificateAction = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -ExecutionPolicy Bypass -File "' + $CertificateScript + '"')
$certificateTrigger = New-ScheduledTaskTrigger -Daily -At 1:00AM
Register-ScheduledTask -TaskName "Tagore ERP - Renew IP TLS Certificate" -Action $certificateAction -Trigger $certificateTrigger -Principal $principal -Force | Out-Null
Write-Host "Scheduled daily Tagore ERP IP certificate renewal check at 01:00." -ForegroundColor Green

$schedulerAction = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -ExecutionPolicy Bypass -File "' + $SchedulerScript + '"')
$schedulerTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 3650)
$schedulerSettings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 5)
Register-ScheduledTask -TaskName "Tagore ERP - Laravel Scheduler" -Action $schedulerAction -Trigger $schedulerTrigger -Principal $principal -Settings $schedulerSettings -Force | Out-Null
Write-Host "Scheduled Laravel command runner every minute. Logs: deploy\windows\logs\tagore-scheduler.log" -ForegroundColor Green
