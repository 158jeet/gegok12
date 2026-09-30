param(
    [string]$PublicIp = "59.90.66.12",
    [string]$CertificateEmail = ""
)

$ErrorActionPreference = "Stop"

function Invoke-Compose([string[]]$Args) {
    docker compose --env-file .env.school -f deploy/windows/docker-compose.production.yml @Args
    if ($LASTEXITCODE -ne 0) { throw "Docker Compose command failed." }
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker Desktop is required. Install Docker Desktop with WSL2 support first."
}
docker compose version | Out-Null
if ($LASTEXITCODE -ne 0) { throw "Docker Compose is unavailable." }

$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $Root

if (-not (Test-Path '.env.school')) {
    Copy-Item 'deploy\windows\.env.production.example' '.env.school'
    Write-Host "Created .env.school. Fill DB_PASSWORD and TAGORE_DB_ROOT_PASSWORD, then run this script again." -ForegroundColor Yellow
    exit 2
}

$envText = Get-Content '.env.school' -Raw
if ($envText -match 'DB_PASSWORD=\s*$' -or $envText -match 'TAGORE_DB_ROOT_PASSWORD=\s*$') {
    throw "Set DB_PASSWORD and TAGORE_DB_ROOT_PASSWORD in .env.school before deployment."
}

New-Item -ItemType Directory -Force -Path 'deploy\windows\letsencrypt' | Out-Null
New-Item -ItemType Directory -Force -Path 'deploy\windows\acme-challenge' | Out-Null

$lanIp = Get-NetIPConfiguration |
    Where-Object { $_.IPv4DefaultGateway -and $_.IPv4Address } |
    ForEach-Object { $_.IPv4Address.IPAddress } |
    Select-Object -First 1

if (-not $lanIp) { throw "Could not detect the server LAN IPv4 address." }

Write-Host "Tagore server LAN IP: $lanIp" -ForegroundColor Cyan
Write-Host "Tagore public IP:      $PublicIp" -ForegroundColor Cyan

Get-NetFirewallRule -DisplayName "Tagore ERP HTTPS" -ErrorAction SilentlyContinue | Remove-NetFirewallRule
Get-NetFirewallRule -DisplayName "Tagore ERP HTTP" -ErrorAction SilentlyContinue | Remove-NetFirewallRule
New-NetFirewallRule -DisplayName "Tagore ERP HTTPS" -Direction Inbound -Protocol TCP -LocalPort 443 -Action Allow -Profile Any | Out-Null
New-NetFirewallRule -DisplayName "Tagore ERP HTTP" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Any | Out-Null

$envText = $envText -replace '(?m)^APP_ENV=.*$', 'APP_ENV=production'
$envText = $envText -replace '(?m)^APP_DEBUG=.*$', 'APP_DEBUG=false'
$envText = $envText -replace '(?m)^APP_URL=.*$', "APP_URL=https://$PublicIp"
$envText = $envText -replace '(?m)^SESSION_SECURE_COOKIE=.*$', 'SESSION_SECURE_COOKIE=true'
Set-Content '.env.school' $envText -NoNewline

Write-Host "[Tagore] Pulling infrastructure images..." -ForegroundColor Cyan
Invoke-Compose @('pull','db','redis','web')

Write-Host "[Tagore] Starting application, database and worker..." -ForegroundColor Cyan
Invoke-Compose @('up','-d','db','redis','app','worker')
Start-Sleep -Seconds 10

Write-Host "[Tagore] Initializing Laravel..." -ForegroundColor Cyan
if (-not ($envText -match '(?m)^APP_KEY=.+')) {
    Invoke-Compose @('exec','-T','app','php','artisan','key:generate','--force')
}
Invoke-Compose @('exec','-T','app','php','artisan','migrate','--force')
Invoke-Compose @('exec','-T','app','php','artisan','optimize:clear')

$certPath = Join-Path $Root ("deploy\windows\letsencrypt\live\" + $PublicIp + "\fullchain.pem")
if (-not (Test-Path $certPath)) {
    if (-not $CertificateEmail) {
        $CertificateEmail = Read-Host "Email for Let's Encrypt certificate notices (optional; press Enter to continue without one)"
    }

    Write-Host "[Tagore] Temporarily stopping web container so Certbot can use port 80..." -ForegroundColor Cyan
    Invoke-Compose @('stop','web')

    $certbotArgs = @(
        'run','--rm','-p','80:80',
        '-v', ($Root + '\deploy\windows\letsencrypt:/etc/letsencrypt'),
        'certbot/certbot:latest',
        'certonly','--standalone',
        '--preferred-profile','shortlived',
        '--ip-address',$PublicIp,
        '--cert-name',$PublicIp,
        '--agree-tos',
        '--non-interactive'
    )
    if ($CertificateEmail) {
        $certbotArgs += @('--email',$CertificateEmail)
    } else {
        $certbotArgs += '--register-unsafely-without-email'
    }

    docker @certbotArgs
    if ($LASTEXITCODE -ne 0) {
        Invoke-Compose @('start','web')
        throw "Let's Encrypt certificate issuance failed. Check that TCP 80 is forwarded to this server and that $PublicIp is the router's actual WAN IP."
    }
}

Write-Host "[Tagore] Starting HTTPS reverse proxy..." -ForegroundColor Cyan
Invoke-Compose @('up','-d','web')

# Persist webroot renewal settings so future renewals do not need to stop nginx.
docker run --rm -v ($Root + '\deploy\windows\letsencrypt:/etc/letsencrypt') -v ($Root + '\deploy\windows\acme-challenge:/var/www/acme') certbot/certbot:latest reconfigure --cert-name $PublicIp --webroot-path /var/www/acme --preferred-profile shortlived --non-interactive
if ($LASTEXITCODE -ne 0) { throw "Could not configure automatic webroot renewal." }

Write-Host "[Tagore] Final Laravel optimization..." -ForegroundColor Cyan
Invoke-Compose @('exec','-T','app','php','artisan','config:cache')
Invoke-Compose @('exec','-T','app','php','artisan','route:cache')
Invoke-Compose @('exec','-T','app','php','artisan','view:cache')

Write-Host ""
Write-Host "TAGORE ERP SERVER READY" -ForegroundColor Green
Write-Host "LAN:    https://$lanIp"
Write-Host "Public: https://$PublicIp"
Write-Host ""
Write-Host "Do NOT forward or expose 3306, 6379, 8080, 9000 or Docker ports." -ForegroundColor Yellow
Write-Host "The router must forward TCP 80 and 443 to $lanIp."
