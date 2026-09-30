$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $Root

function Invoke-Compose([string[]]$Args) {
    docker compose --env-file .env.school -f deploy/windows/docker-compose.production.yml @Args
    if ($LASTEXITCODE -ne 0) { throw "Docker Compose command failed." }
}

Write-Host "[Tagore] Checking IP certificate renewal..." -ForegroundColor Cyan

docker run --rm -v ($Root + '\deploy\windows\letsencrypt:/etc/letsencrypt') -v ($Root + '\deploy\windows\acme-challenge:/var/www/acme') certbot/certbot:latest renew --preferred-profile shortlived --quiet --webroot-path /var/www/acme
if ($LASTEXITCODE -ne 0) { throw "Certbot renewal failed." }

Invoke-Compose @('exec','-T','web','nginx','-s','reload')
Write-Host "[Tagore] Certificate check completed and nginx reloaded." -ForegroundColor Green
