[CmdletBinding()]
param([string]$DestinationPath="G:\TagoreBackups",[string]$RcloneRemote="",[int]$LocalRetentionDays=30,[switch]$LocalOnly)
$ErrorActionPreference="Stop"
$Root=(Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $Root
if(-not(Test-Path ".env.school")){throw "Missing .env.school."}
if(-not(Get-Command docker -ErrorAction SilentlyContinue)){throw "Docker CLI is required."}
if($LocalRetentionDays -lt 1 -or $LocalRetentionDays -gt 3650){throw "LocalRetentionDays must be between 1 and 3650."}
if(-not $LocalOnly -and [string]::IsNullOrWhiteSpace($RcloneRemote)){throw "An encrypted rclone crypt remote is required; use -LocalOnly only for a controlled test."}
$compose=@("--env-file",".env.school","-f","deploy/windows/docker-compose.production.yml")
$stamp=Get-Date -Format "yyyyMMdd-HHmmss"
$work=Join-Path ([IO.Path]::GetTempPath()) ("tagore-backup-"+[guid]::NewGuid().ToString("N"))
$archiveName="tagore-backup-$stamp.zip"; $archivePath=Join-Path $DestinationPath $archiveName; $writer=$null
function Invoke-Compose { param([string[]]$Arguments) & docker compose @compose @Arguments; if($LASTEXITCODE -ne 0){throw "Docker Compose failed: $($Arguments -join ' ')"} }
try {
 New-Item -ItemType Directory -Force -Path $DestinationPath,$work | Out-Null
 New-Item -ItemType Directory -Force -Path (Join-Path $work "uploads") | Out-Null
 $dumpPath=Join-Path $work "database.sql"
 $dumpCommand='MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump --single-transaction --quick --routines --triggers --events --hex-blob --default-character-set=utf8mb4 -u root tagore_erp'
 $writer=[IO.StreamWriter]::new($dumpPath,$false,[Text.UTF8Encoding]::new($false))
 & docker compose @compose exec -T db sh -lc $dumpCommand | ForEach-Object {$writer.WriteLine([string]$_)}
 $dumpExit=$LASTEXITCODE; $writer.Flush(); $writer.Dispose(); $writer=$null
 if($dumpExit -ne 0 -or -not(Test-Path $dumpPath) -or (Get-Item $dumpPath).Length -lt 100){throw "MySQL dump failed or is unexpectedly small (exit $dumpExit)."}
 Invoke-Compose @("cp","app:/var/www/html/storage/app",(Join-Path $work "uploads"))
 if(-not(Test-Path (Join-Path $work "uploads"))){throw "Uploaded-file snapshot was not created."}
 $uploadFiles=@(Get-ChildItem -LiteralPath (Join-Path $work "uploads") -File -Recurse | ForEach-Object {
  [pscustomobject]@{path=$_.FullName.Substring((Join-Path $work "uploads").Length).TrimStart('\','/');bytes=$_.Length;sha256=(Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash}
 })
 $manifest=[ordered]@{product="Tagore ERP";created_at_utc=[DateTime]::UtcNow.ToString("o");database="database.sql";database_sha256=(Get-FileHash -LiteralPath $dumpPath -Algorithm SHA256).Hash;uploads_directory="uploads";upload_file_count=$uploadFiles.Count;upload_files=$uploadFiles;note="MySQL transactional snapshot plus storage/app copy. Restore rehearsal required before release evidence."}
 $manifest | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $work "manifest.json") -Encoding UTF8
 if(Test-Path $archivePath){throw "Refusing to overwrite existing archive: $archivePath"}
 Compress-Archive -Path (Join-Path $work "*") -DestinationPath $archivePath -CompressionLevel Optimal
 if(-not(Test-Path $archivePath) -or (Get-Item $archivePath).Length -lt 100){throw "Backup archive is missing or unexpectedly small."}
 $archiveHash=(Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash
 Set-Content -LiteralPath "$archivePath.sha256" -Value "$archiveHash  $archiveName" -Encoding ASCII
 if(-not $LocalOnly){
  if(-not(Get-Command rclone -ErrorAction SilentlyContinue)){throw "rclone is required for off-machine backup."}
  $remoteConfig=(& rclone config show $RcloneRemote 2>$null) -join [Environment]::NewLine
  if($LASTEXITCODE -ne 0 -or $remoteConfig -notmatch '(?im)^\s*type\s*=\s*crypt\s*$'){throw "Remote '$RcloneRemote' is not a readable rclone crypt remote. Refusing plaintext upload."}
  & rclone copyto $archivePath "$RcloneRemote/$archiveName"; if($LASTEXITCODE -ne 0){throw "Encrypted archive upload failed."}
  & rclone copyto "$archivePath.sha256" "$RcloneRemote/$archiveName.sha256"; if($LASTEXITCODE -ne 0){throw "Offsite checksum upload failed."}
 }
 Get-ChildItem -LiteralPath $DestinationPath -File -Filter "tagore-backup-*.zip" | Where-Object {$_.LastWriteTimeUtc -lt [DateTime]::UtcNow.AddDays(-$LocalRetentionDays)} | ForEach-Object {Remove-Item -LiteralPath $_.FullName -Force; if(Test-Path "$($_.FullName).sha256"){Remove-Item -LiteralPath "$($_.FullName).sha256" -Force}}
 [pscustomobject]@{status="created";archive=$archivePath;sha256=$archiveHash;database_bytes=(Get-Item $dumpPath).Length;uploaded_file_count=$uploadFiles.Count;offsite_encrypted_copy=(-not $LocalOnly);offsite_remote=$(if($LocalOnly){$null}else{$RcloneRemote})} | Format-List
} finally {if($writer){$writer.Dispose()};if(Test-Path $work){Remove-Item -LiteralPath $work -Recurse -Force -ErrorAction SilentlyContinue}}
