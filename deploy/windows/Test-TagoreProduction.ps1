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
function A([string[]]$CommandArgs) {
    $out = & docker compose --env-file .env.school -f deploy/windows/docker-compose.production.yml exec -T app php artisan @CommandArgs 2>&1
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

# Database health: use Docker's healthcheck result instead of mysqladmin CLI password flags.
try {
    $out = (& docker inspect -f '{{.State.Health.Status}}' windows-db-1 2>$null) -join $NL
    if ($LASTEXITCODE -eq 0 -and $out.Trim() -eq 'healthy') { Add-Result 'MySQL health' 'PASS' 'Docker healthcheck reports healthy' }
    else { Add-Result 'MySQL health' 'FAIL' $out }
} catch { Add-Result 'MySQL health' 'FAIL' $_.Exception.Message }

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

# Nginx uses concrete PEM files stored outside the repository and Docker build context.
$exportCert = "G:\TagoreSecrets\nginx-certs\59.90.66.12\fullchain.pem"
$exportKey = "G:\TagoreSecrets\nginx-certs\59.90.66.12\privkey.pem"
if ((Test-Path $exportCert) -and (Test-Path $exportKey) -and (Get-Item $exportCert).Length -gt 0 -and (Get-Item $exportKey).Length -gt 0) {
    Add-Result "TLS export files" "PASS" "Concrete Nginx certificate and key exist and are non-empty"
} else { Add-Result "TLS export files" "FAIL" "Nginx certificate export missing or empty" }

# Test a real TLS handshake and Laravel login response, not merely the existence of certificate paths.
try {
    $tlsOutput = & curl.exe -k -sS -o NUL -w "%{http_code}" --connect-timeout 5 --max-time 10 "https://127.0.0.1/login" 2>&1
    $tlsExit = $LASTEXITCODE
    $tlsCode = ($tlsOutput | Select-Object -Last 1).ToString().Trim()
    if ($tlsExit -eq 0 -and $tlsCode -eq "200") { Add-Result "Local HTTPS handshake" "PASS" "TLS connected and /login returned HTTP 200" }
    else { Add-Result "Local HTTPS handshake" "FAIL" "curl exit=$tlsExit, response=$tlsCode; $($tlsOutput -join ' ')" }
} catch { Add-Result "Local HTTPS handshake" "FAIL" $_.Exception.Message }

# Check actual certificate expiry inside the web container, where the mounted PEM symlink resolves.
try {
    docker run --rm --entrypoint sh -v ($Root + '\deploy\windows\letsencrypt:/etc/letsencrypt') certbot/certbot:latest -lc 'openssl x509 -checkend 0 -noout -in /etc/letsencrypt/live/59.90.66.12/fullchain.pem'
    $certExit = $LASTEXITCODE
    if ($certExit -eq 0) { Add-Result 'TLS certificate validity' 'PASS' 'Certificate is not expired' }
    else { Add-Result 'TLS certificate validity' 'FAIL' 'Certificate expired or unreadable; public HTTP challenge routing must be fixed' }
} catch { Add-Result 'TLS certificate validity' 'FAIL' $_.Exception.Message }
# Nginx configuration: suppress expected diagnostic text on stderr and judge by exit code.
try {
    cmd.exe /d /c "docker exec windows-web-1 nginx -t 1>nul 2>nul"
    $nginxExit = $LASTEXITCODE
    if ($nginxExit -eq 0) { Add-Result 'Nginx configuration' 'PASS' 'nginx -t exited successfully' }
    else { Add-Result 'Nginx configuration' 'FAIL' "nginx -t exit code $nginxExit" }
} catch { Add-Result 'Nginx configuration' 'FAIL' $_.Exception.Message }

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
    $r=A @("tinker","--execute=dump(DB::select('SELECT 1 AS ok'))")
    if ($r.Code -eq 0 -and $r.Output -match 'ok') { Add-Result "Laravel → MySQL query" "PASS" "Application successfully queried database" }
    else { Add-Result "Laravel → MySQL query" "FAIL" $r.Output }
} catch { Add-Result "Laravel → MySQL query" "FAIL" $_.Exception.Message }

# Required ERP routes
try {
    $r=A @("route:list","--path=tagore","--json")
    if ($r.Code -ne 0) { throw $r.Output }
    $cleanOutput = [regex]::Replace($r.Output, '\x1B\[[0-9;]*[A-Za-z]', '').Trim()
    if (-not $cleanOutput.StartsWith('[')) { throw "Laravel did not return a JSON route array. Output: $($cleanOutput.Substring(0, [Math]::Min(160, $cleanOutput.Length)))" }
    try { $routes = $cleanOutput | ConvertFrom-Json -ErrorAction Stop }
    catch { throw "Laravel returned invalid route JSON: $($_.Exception.Message)" }
    $registered = @($routes | ForEach-Object { $_.uri })
    $required=@("tagore/dashboard","tagore/admissions","tagore/accounts/fees","tagore/payroll","tagore/inventory","tagore/transport","tagore/communication","tagore/reports","tagore/security","tagore/learning","tagore/documents","tagore/platform","tagore/performance")
    $missing=@($required | Where-Object { $_ -notin $registered })
    if ($missing.Count -eq 0) { Add-Result "Core ERP routes" "PASS" "All required module routes registered" }
    else { Add-Result "Core ERP routes" "FAIL" ("Missing: "+($missing -join ", ")) }
} catch { Add-Result "Core ERP routes" "FAIL" $_.Exception.Message }

# Public route hygiene: destructive/test-only routes must not be exposed in production.
try {
    $r=A @("route:list","--json")
    if ($r.Code -ne 0) { throw $r.Output }
    $cleanOutput = [regex]::Replace($r.Output, '\x1B\[[0-9;]*[A-Za-z]', '').Trim()
    if (-not $cleanOutput.StartsWith('[')) { throw "Laravel did not return a JSON route array." }
    $allRoutes = $cleanOutput | ConvertFrom-Json -ErrorAction Stop
    $allUris = @($allRoutes | ForEach-Object { $_.uri })
    $unsafe = @($allUris | Where-Object { $_ -in @("cache-clear","checksms") -or $_ -like "__e2e/*" })
    if ($unsafe.Count -eq 0) { Add-Result "Public route hygiene" "PASS" "No public cache-clear, SMS-test, or E2E bypass routes" }
    else { Add-Result "Public route hygiene" "FAIL" ("Unexpected routes: "+($unsafe -join ", ")) }
    if ("olympiad/register" -in $allUris) { Add-Result "Olympiad intake route" "PASS" "Registration endpoint is registered; feature flag controls availability" }
    else { Add-Result "Olympiad intake route" "FAIL" "Registration endpoint is missing" }
} catch { Add-Result "Public route hygiene" "FAIL" $_.Exception.Message }

# Parent/offline API routes
try {
    $r=A @("route:list","--path=api","--json")
    if ($r.Code -ne 0) { throw $r.Output }
    $cleanOutput = [regex]::Replace($r.Output, '\x1B\[[0-9;]*[A-Za-z]', '').Trim()
    if (-not $cleanOutput.StartsWith('[')) { throw "Laravel did not return a JSON route array. Output: $($cleanOutput.Substring(0, [Math]::Min(160, $cleanOutput.Length)))" }
    try { $routes = $cleanOutput | ConvertFrom-Json -ErrorAction Stop }
    catch { throw "Laravel returned invalid route JSON: $($_.Exception.Message)" }
    $registered = @($routes | ForEach-Object { $_.uri })
    $required=@("api/parent/login","api/v2/tagore/sync/login","api/v2/tagore/sync/bootstrap","api/v2/tagore/sync/push")
    $missing=@($required | Where-Object { $_ -notin $registered })
    if ($missing.Count -eq 0) { Add-Result "Parent/offline API routes" "PASS" "Required API routes registered" }
    else { Add-Result "Parent/offline API routes" "FAIL" ("Missing: "+($missing -join ", ")) }
} catch { Add-Result "Parent/offline API routes" "FAIL" $_.Exception.Message }

# Scheduled renewal
try {
    $task=Get-ScheduledTask -TaskName "Tagore ERP - Renew IP TLS Certificate" -ErrorAction Stop
    $actionText=($task.Actions | ForEach-Object { $_.Arguments }) -join " "
    if ($task.State -eq "Ready" -and $actionText -match 'C:\\server\\gegok12-main-new\\deploy\\windows\\Renew-TagoreCertificate\.ps1') {
        Add-Result "Certificate renewal task" "PASS" "Scheduled task is Ready and points to the current repository script"
    } else { Add-Result "Certificate renewal task" "FAIL" "State: $($task.State); Action: $actionText" }
} catch { Add-Result "Certificate renewal task" "FAIL" $_.Exception.Message }

# PHPUnit must run in CI/staging with an isolated SQLite database, never against production config.
Add-Result "Automated PHPUnit suite" "INFO" "Skipped in production; run in isolated CI/staging database"

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
