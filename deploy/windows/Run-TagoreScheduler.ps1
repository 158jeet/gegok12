$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $Root

$LogDir = Join-Path $Root 'deploy\windows\logs'
$LogFile = Join-Path $LogDir 'tagore-scheduler.log'
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

# Keep the scheduler log bounded; retain timestamped rotations for diagnosis.
if ((Test-Path $LogFile) -and (Get-Item $LogFile).Length -gt 5MB) {
    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    Move-Item -Path $LogFile -Destination (Join-Path $LogDir "tagore-scheduler-$stamp.log") -Force
}
Get-ChildItem $LogDir -Filter 'tagore-scheduler-*.log' -File -ErrorAction SilentlyContinue |
    Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-30) } |
    Remove-Item -Force -ErrorAction SilentlyContinue

try {
    if (-not (Test-Path '.env.school')) { throw "Missing .env.school; Laravel scheduler was not run." }
    $output = (& docker compose --env-file .env.school -f deploy/windows/docker-compose.production.yml exec -T app php artisan schedule:run --no-interaction 2>&1 | Out-String).Trim()
    $exitCode = $LASTEXITCODE
    if ($exitCode -ne 0) {
        Add-Content -Path $LogFile -Encoding UTF8 -Value ("[{0}] ERROR (exit {1}) | {2}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $exitCode, $output)
        throw "Laravel schedule:run failed with exit code $exitCode."
    }
    if ($output -and $output -notmatch 'No scheduled commands are ready to run') {
        Add-Content -Path $LogFile -Encoding UTF8 -Value ("[{0}] {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $output)
    }
} catch {
    Add-Content -Path $LogFile -Encoding UTF8 -Value ("[{0}] ERROR {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $_.Exception.Message)
    exit 1
}
