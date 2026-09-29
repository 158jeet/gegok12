$ErrorActionPreference = 'Stop'
$RepoOwner = '158jeet'
$RepoName = 'gegok12'
$Branch = 'main'
$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$ComposeFile = Join-Path $Root 'deploy\windows\docker-compose.yml'
$EnvFile = Join-Path $Root '.env.school'
$Timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$BackupRoot = Join-Path $Root 'backups'
$BackupDir = Join-Path $BackupRoot $Timestamp
$Archive = Join-Path $env:TEMP "tagore-erp-$Timestamp.zip"
$ExtractDir = Join-Path $env:TEMP "tagore-erp-$Timestamp"
function Invoke-Compose([string[]]$Arguments) {
    & docker compose --env-file $EnvFile -f $ComposeFile @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Docker Compose command failed: docker compose $($Arguments -join ' ')" }
}
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { throw 'Docker Desktop is required.' }
docker compose version | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Docker Compose is unavailable.' }
if (-not (Test-Path $EnvFile)) { throw 'Missing .env.school. Run Install-TagorePilot.ps1 first.' }
New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null
Write-Host "[Tagore] Creating pre-update backup: $BackupDir" -ForegroundColor Cyan
Copy-Item $EnvFile (Join-Path $BackupDir '.env.school') -Force
Invoke-Compose @('exec','-T','db','sh','-c','mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --events tagore_erp') |
    Out-File (Join-Path $BackupDir 'tagore_erp.sql') -Encoding utf8
if ($LASTEXITCODE -ne 0) { throw 'Database backup failed.' }
Write-Host '[Tagore] Downloading latest tested source from GitHub...' -ForegroundColor Cyan
$DownloadUrl = "https://github.com/$RepoOwner/$RepoName/archive/refs/heads/$Branch.zip"
Invoke-WebRequest -Uri $DownloadUrl -OutFile $Archive
New-Item -ItemType Directory -Force -Path $ExtractDir | Out-Null
Expand-Archive -Path $Archive -DestinationPath $ExtractDir -Force
$SourceRoot = Get-ChildItem $ExtractDir -Directory | Select-Object -First 1
if (-not $SourceRoot) { throw 'Downloaded source archive could not be located.' }
Write-Host '[Tagore] Stopping application containers...' -ForegroundColor Cyan
Invoke-Compose @('down')
Write-Host '[Tagore] Updating application files...' -ForegroundColor Cyan
$Preserve = @('.env.school','backups')
Get-ChildItem $SourceRoot.FullName -Force | Where-Object { $Preserve -notcontains $_.Name } |
    ForEach-Object { Copy-Item $_.FullName (Join-Path $Root $_.Name) -Recurse -Force }
Write-Host '[Tagore] Rebuilding and starting updated services...' -ForegroundColor Cyan
Invoke-Compose @('up','-d','--build')
Write-Host '[Tagore] Running database migrations...' -ForegroundColor Cyan
Invoke-Compose @('exec','-T','app','php','artisan','migrate','--force')
Invoke-Compose @('exec','-T','app','php','artisan','optimize:clear')
Write-Host '[Tagore] Checking application health...' -ForegroundColor Cyan
$Healthy = $false
for ($i = 1; $i -le 30; $i++) {
    try {
        $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8080' -UseBasicParsing -TimeoutSec 5
        if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 500) { $Healthy = $true; break }
    } catch {}
    Start-Sleep -Seconds 2
}
if (-not $Healthy) {
    Write-Warning "[Tagore] Health check failed. Application logs:"
    & docker compose --env-file $EnvFile -f $ComposeFile logs --tail=100 app web
    throw "Updated application did not become healthy. Backup is available at $BackupDir"
}
Remove-Item $Archive -Force -ErrorAction SilentlyContinue
Remove-Item $ExtractDir -Recurse -Force -ErrorAction SilentlyContinue
Write-Host ''
Write-Host 'TAGORE ERP UPDATE COMPLETE' -ForegroundColor Green
Write-Host "Backup: $BackupDir"
Write-Host 'Database, uploaded files and school configuration were preserved.'
Write-Host 'Local: http://127.0.0.1:8080'
