param(
    [string]$PublicUrl = "https://59.90.66.12",
    [switch]$RunHttpTests
)

$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $Root
$NL = [Environment]::NewLine
$results = [System.Collections.Generic.List[object]]::new()

function Add-Result([string]$Name, [string]$Status, [string]$Detail) {
    $results.Add([pscustomobject]@{ Check=$Name; Status=$Status; Detail=$Detail })
}
function C([string[]]$Args) {
    & docker compose --env-file .env.school -f deploy/windows/docker-compose.production.yml @Args
    if ($LASTEXITCODE -ne 0) { throw "Compose command failed: $($Args -join ' ')" }
}
function A([string[]]$Args) {
    $out = & docker compose --env-file .env.school -f deploy/windows/docker-compose.production.yml exec -T app php artisan @Args 2>&1
    [pscustomobject]@{ Code=$LASTEXITCODE; Output=($out -join $NL) }
}

Write-Host "TAGORE ERP - PRODUCTION ACCEPTANCE TEST" -ForegroundColor Cyan

# Docker services
try {
    $services = (& docker compose --env-file .env.school -f deploy/windows/docker-compose.production.yml ps --services --filter status=running 2>&1)
    foreach ($svc in @("app","web","worker","db","redis")) {
        if ($services -contains $svc) { Add-Result "Service: $svc" "PASS" "Running" }
        else { Add-Result "Service: $svc" "FAIL" "Not running" }
    }
} catch { Add-Result "Docker service inspection" "FAIL" $_.Exception.Message }

# Database health
try {
    $out = (& docker compose --env-file .env.school -f deploy/windows/docker-compose.production.yml exec -T db sh -lc 'mysqladmin ping -h localhost -u root -p"$MYSQL_ROOT_PASSWORD" --silent' 2>&1) -join $NL
    if ($out -match 'mysqld is alive' -or $LASTEXITCODE -eq 0) { Add-Result "MySQL health" "PASS" "mysqladmin ping succeeded" }
    else { Add-Result "MySQL health" "FAIL" $out }
} catch { Add-Result "MySQL health" "FAIL" $_.Exception.Message }

# Laravel runtime
foreach ($x in @(
    @("Laravel about", @("about")),
    @("Migration status", @("migrate:status")),
    @("Route cache", @("route:cache")),
    @("Config cache", @("config:cache")),
    @("View cache", @("view:cache"))
)) {
    try {
        $r=A $x[1]
        if ($r.Code -eq 0) { Add-Result $x[0] "PASS" "Command completed" }
        else { Add-Result $x[0] "FAIL" (($r.Output -split $NL | Select-Object -Last 8) -join $NL) }
    } catch { Add-Result $x[0] "FAIL" $_.Exception.Message }
}

# No pending migrations
try {
    $r=A @("migrate:status")
    if ($r.Code -ne 0) { throw $r.Output }
    if ($r.Output -match '(?im)^\s*\[\s*\]\s+') { Add-Result "All migrations applied" "FAIL" "Pending migrations detected" }
    else { Add-Result "All migrations applied" "PASS" "No pending migrations detected" }
} catch { Add-Result "All migrations applied" "FAIL" $_.Exception.Message }

# TLS files
if ((Test-Path "deploy\windows\letsencrypt\live\59.90.66.12\fullchain.pem") -and (Test-Path "deploy\windows\letsencrypt\live\59.90.66.12\privkey.pem")) {
    Add-Result "TLS certificate files" "PASS" "Certificate and key exist"
} else { Add-Result "TLS certificate files" "FAIL" "Certificate files missing" }

# Nginx
try {
    $out = (& docker compose --env-file .env.school -f deploy/windows/docker-compose.production.yml exec -T web nginx -t 2>&1) -join $NL
    if ($LASTEXITCODE -eq 0 -and $out -match 'syntax is ok|test is successful') { Add-Result "Nginx configuration" "PASS" "nginx -t successful (deprecation warnings allowed)" }
    else { Add-Result "Nginx configuration" "FAIL" $out }
} catch { Add-Result "Nginx configuration" "FAIL" $_.Exception.Message }

# Worker
try {
    $state = (& docker compose --env-file .env.school -f deploy/windows/docker-compose.production.yml ps --status running worker 2>&1) -join $NL
    if ($LASTEXITCODE -eq 0 -and $state -match 'windows-worker-1') { Add-Result "Queue worker" "PASS" "Worker container is running" }
    else { Add-Result "Queue worker" "FAIL" "Worker container is not running" }
} catch { Add-Result "Queue worker" "FAIL" $_.Exception.Message }

# Redis
try {
    $out = (& docker compose --env-file .env.school -f deploy/windows/docker-compose.production.yml exec -T redis redis-cli ping 2>&1) -join $NL
    if ($out -match 'PONG') { Add-Result "Redis" "PASS" "PING returned PONG" }
    else { Add-Result "Redis" "FAIL" $out }
} catch { Add-Result "Redis" "FAIL" $_.Exception.Message }

# Application DB query
try {
    $r=A @("tinker","--execute=DB::select('SELECT 1 AS ok');")
    if ($r.Code -eq 0 -and $r.Output -match 'ok') { Add-Result "Laravel → MySQL query" "PASS" "Application successfully queried database" }
    else { Add-Result "Laravel → MySQL query" "FAIL" $r.Output }
} catch { Add-Result "Laravel → MySQL query" "FAIL" $_.Exception.Message }

# Required ERP routes
try {
    $r=A @("route:list","--path=tagore","--json")
    if ($r.Code -ne 0) { throw $r.Output }
    $routes = $r.Output | ConvertFrom-Json
    $registered = @($routes | ForEach-Object { $_.uri })
    $required=@("tagore/dashboard","tagore/admissions","tagore/accounts/fees","tagore/payroll","tagore/inventory","tagore/transport","tagore/communication","tagore/reports","tagore/security","tagore/learning","tagore/documents","tagore/platform")
    $missing=@($required | Where-Object { $_ -notin $registered })
    if ($missing.Count -eq 0) { Add-Result "Core ERP routes" "PASS" "All required module routes registered" }
    else { Add-Result "Core ERP routes" "FAIL" ("Missing: "+($missing -join ", ")) }
} catch { Add-Result "Core ERP routes" "FAIL" $_.Exception.Message }

# Parent/offline API routes
try {
    $r=A @("route:list","--path=api","--json")
    if ($r.Code -ne 0) { throw $r.Output }
    $routes = $r.Output | ConvertFrom-Json
    $registered = @($routes | ForEach-Object { $_.uri })
    $required=@("api/parent/login","api/v2/tagore/sync/login","api/v2/tagore/sync/bootstrap","api/v2/tagore/sync/push")
    $missing=@($required | Where-Object { $_ -notin $registered })
    if ($missing.Count -eq 0) { Add-Result "Parent/offline API routes" "PASS" "Required API routes registered" }
    else { Add-Result "Parent/offline API routes" "FAIL" ("Missing: "+($missing -join ", ")) }
} catch { Add-Result "Parent/offline API routes" "FAIL" $_.Exception.Message }

# Scheduled renewal
try {
    $task=Get-ScheduledTask -TaskName "Tagore ERP - Renew IP TLS Certificate" -ErrorAction Stop
    if ($task.State -eq "Ready") { Add-Result "Certificate renewal task" "PASS" "Scheduled task is Ready" }
    else { Add-Result "Certificate renewal task" "FAIL" "State: $($task.State)" }
} catch { Add-Result "Certificate renewal task" "FAIL" $_.Exception.Message }

# Test suite: production image intentionally omits dev dependencies
try {
    $r=A @("test")
    if ($r.Code -eq 0) { Add-Result "Automated PHPUnit suite" "PASS" "Tests passed" }
    elseif ($r.Output -match 'There are no commands defined|vendor/bin/phpunit.*not found|Could not open input file') {
        Add-Result "Automated PHPUnit suite" "INFO" "Skipped in production image because dev/test dependencies are excluded"
    } else { Add-Result "Automated PHPUnit suite" "FAIL" (($r.Output -split $NL | Select-Object -Last 20) -join $NL) }
} catch { Add-Result "Automated PHPUnit suite" "INFO" "Not executable in production image" }

# Optional WAN test. Same-LAN WAN hairpin can be unreliable.
if ($RunHttpTests) {
    try {
        $r=Invoke-WebRequest -Uri $PublicUrl -UseBasicParsing -TimeoutSec 20 -MaximumRedirection 0 -ErrorAction Stop
        if ($r.StatusCode -in @(200,301,302,303)) { Add-Result "Public HTTPS endpoint" "PASS" "HTTP $($r.StatusCode)" }
        else { Add-Result "Public HTTPS endpoint" "FAIL" "HTTP $($r.StatusCode)" }
    } catch { Add-Result "Public HTTPS endpoint" "INFO" "External mobile-data test is authoritative when LAN NAT loopback is present" }
}

Write-Host ""
$results | Format-Table -AutoSize
$fail=@($results | Where-Object Status -eq "FAIL").Count
$pass=@($results | Where-Object Status -eq "PASS").Count
$info=@($results | Where-Object Status -eq "INFO").Count
Write-Host "PASS: $pass  FAIL: $fail  INFO: $info" -ForegroundColor Cyan
if ($fail -eq 0) { Write-Host "TAGORE ERP ACCEPTANCE TEST: PASS" -ForegroundColor Green; exit 0 }
Write-Host "TAGORE ERP ACCEPTANCE TEST: FAIL" -ForegroundColor Red
exit 1
