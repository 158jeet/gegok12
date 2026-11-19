$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $Root

function Invoke-Compose([string[]]$ComposeArgs) {
    docker compose --env-file .env.school -f deploy/windows/docker-compose.production.yml @ComposeArgs
    if ($LASTEXITCODE -ne 0) { throw "Docker Compose command failed." }
}

Write-Host "[Tagore] Checking IP certificate renewal..." -ForegroundColor Cyan
$LogDir = Join-Path $Root 'deploy\windows\letsencrypt\logs'
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
docker run --rm -v ($Root + '\deploy\windows\letsencrypt:/etc/letsencrypt') -v ($Root + '\deploy\windows\acme-challenge:/var/www/acme') -v ($LogDir + ':/var/log/letsencrypt') certbot/certbot:latest renew --authenticator webroot --preferred-profile shortlived --no-random-sleep-on-renew --quiet --webroot-path /var/www/acme
if ($LASTEXITCODE -ne 0) { throw "Certbot renewal failed. See deploy\windows\letsencrypt\logs\letsencrypt.log." }

# Docker Desktop on Windows exposes Certbot live symlinks as empty files in Linux bind mounts.
# Export the latest concrete archive PEM files to a separate directory mounted read-only by Nginx.
$ArchiveDir = Join-Path $Root 'deploy\windows\letsencrypt\archive\59.90.66.12'
$ExportDir = 'G:\TagoreSecrets\nginx-certs\59.90.66.12'
New-Item -ItemType Directory -Force -Path $ExportDir | Out-Null
$Cert = Get-ChildItem $ArchiveDir -File | Where-Object Name -match '^fullchain(\d+)\.pem$' | Sort-Object { [int]([regex]::Match($_.Name,'\d+').Value) } -Descending | Select-Object -First 1
$Key = Get-ChildItem $ArchiveDir -File | Where-Object Name -match '^privkey(\d+)\.pem$' | Sort-Object { [int]([regex]::Match($_.Name,'\d+').Value) } -Descending | Select-Object -First 1
if (-not $Cert -or -not $Key -or $Cert.Length -eq 0 -or $Key.Length -eq 0) { throw "Latest archived certificate/key missing." }
Copy-Item $Cert.FullName (Join-Path $ExportDir 'fullchain.pem') -Force
Copy-Item $Key.FullName (Join-Path $ExportDir 'privkey.pem') -Force

docker run --rm --entrypoint sh -v 'G:\TagoreSecrets\nginx-certs:/etc/nginx/certs:ro' certbot/certbot:latest -lc 'openssl x509 -checkend 0 -noout -in /etc/nginx/certs/59.90.66.12/fullchain.pem'
if ($LASTEXITCODE -ne 0) { throw "Exported TLS certificate expired or unreadable; Nginx was not reloaded." }
cmd.exe /d /c "docker exec windows-web-1 nginx -t 1>nul 2>nul"
if ($LASTEXITCODE -ne 0) { throw "Nginx configuration validation failed; Nginx was not reloaded." }
Invoke-Compose @('exec','-T','web','nginx','-s','reload')
Write-Host "[Tagore] Certificate check completed and nginx reloaded." -ForegroundColor Green
