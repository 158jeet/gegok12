$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $Root
function Invoke-Compose([string[]]$Args) {
    docker compose --env-file .env.school -f deploy/windows/docker-compose.production.yml @Args
    if ($LASTEXITCODE -ne 0) { throw "Docker Compose command failed." }
}
Write-Host "[Tagore] Checking IP certificate renewal..." -ForegroundColor Cyan
Invoke-Compose @('stop','web')
try {
    docker run --rm -v ($Root + '\deploy\windows\letsencrypt:/etc/letsencrypt') certbot/certbot:latest renew --preferred-profile shortlived --quiet
    if ($LASTEXITCODE -ne 0) { throw "Certbot renewal failed." }
}
finally {
    Invoke-Compose @('start','web')
}
Write-Host "[Tagore] Certificate check completed." -ForegroundColor Green
