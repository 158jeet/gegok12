$ErrorActionPreference = 'Stop'
$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$ComposeFile = Join-Path $Root 'deploy\windows\docker-compose.yml'
$EnvFile = Join-Path $Root '.env.school'
$Timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$BackupDir = Join-Path $Root "backups\$Timestamp"
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { throw 'Docker Desktop is required.' }
if (-not (Test-Path $EnvFile)) { throw 'Missing .env.school. Run Install-TagorePilot.ps1 first.' }
New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null
Copy-Item $EnvFile (Join-Path $BackupDir '.env.school') -Force
& docker compose --env-file $EnvFile -f $ComposeFile exec -T db sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --events tagore_erp' |
    Out-File (Join-Path $BackupDir 'tagore_erp.sql') -Encoding utf8
if ($LASTEXITCODE -ne 0) { throw 'Database backup failed.' }
Write-Host "Backup complete: $BackupDir" -ForegroundColor Green
