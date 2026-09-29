$ErrorActionPreference = 'Stop'
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { throw 'Docker Desktop is required.' }
docker compose version | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Docker Compose is unavailable.' }
$Root=(Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $Root
if (-not (Test-Path '.env.school')) { Copy-Item 'deploy\windows\.env.school.example' '.env.school' }
Write-Host '[Tagore] Building and starting pilot...' -ForegroundColor Cyan
docker compose --env-file .env.school -f deploy/windows/docker-compose.yml up -d --build
if ($LASTEXITCODE -ne 0) { throw 'Docker build/start failed.' }
Write-Host '[Tagore] Generating application key...' -ForegroundColor Cyan
docker compose --env-file .env.school -f deploy/windows/docker-compose.yml exec -T app php artisan key:generate --force
if ($LASTEXITCODE -ne 0) { throw 'Application key generation failed.' }
Write-Host '[Tagore] Migrating and seeding demo data...' -ForegroundColor Cyan
docker compose --env-file .env.school -f deploy/windows/docker-compose.yml exec -T app php artisan migrate --seed --force
if ($LASTEXITCODE -ne 0) { throw 'Migration/seed failed.' }
docker compose --env-file .env.school -f deploy/windows/docker-compose.yml exec -T app php artisan optimize:clear
$ip=Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*'} | Select-Object -First 1 -ExpandProperty IPAddress
Write-Host 'TAGORE ERP PILOT READY' -ForegroundColor Green
Write-Host 'Local: http://127.0.0.1:8080'
if ($ip) { Write-Host ('LAN: http://' + $ip + ':8080') }
Write-Host 'Demo password: Tagore@2026!'