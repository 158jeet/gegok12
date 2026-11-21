[CmdletBinding()]
param([Parameter(Mandatory=$true)][string]$ArchivePath,[switch]$RestoreToDisposableMySql)
$ErrorActionPreference="Stop"; $ArchivePath=(Resolve-Path -LiteralPath $ArchivePath).Path
$checksumPath="$ArchivePath.sha256"; if(-not(Test-Path $checksumPath)){throw "Missing checksum sidecar."}
$expected=((Get-Content -LiteralPath $checksumPath -Raw).Trim() -split '\s+')[0]; $actual=(Get-FileHash -LiteralPath $ArchivePath -Algorithm SHA256).Hash
if($expected -ne $actual){throw "Archive SHA-256 mismatch."}
$work=Join-Path ([IO.Path]::GetTempPath()) ("tagore-restore-test-"+[guid]::NewGuid().ToString("N"))
$container="tagore-restore-test-"+[guid]::NewGuid().ToString("N").Substring(0,8)
$rng=[Security.Cryptography.RandomNumberGenerator]::Create();$randomBytes=New-Object byte[] 32;try{$rng.GetBytes($randomBytes)}finally{$rng.Dispose()};$password=[Convert]::ToBase64String($randomBytes)
try {
 Expand-Archive -LiteralPath $ArchivePath -DestinationPath $work
 $dump=Join-Path $work "database.sql"; $manifestPath=Join-Path $work "manifest.json"; $uploads=Join-Path $work "uploads"
 if(-not(Test-Path $dump) -or (Get-Item $dump).Length -lt 100){throw "Archive has no valid database.sql."}
 if(-not(Test-Path $manifestPath) -or -not(Test-Path $uploads)){throw "Archive is missing manifest.json or uploads/."}
 $manifest=Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
 if($manifest.database_sha256 -ne (Get-FileHash -LiteralPath $dump -Algorithm SHA256).Hash){throw "Database hash does not match manifest."}
 $uploadFiles=@(Get-ChildItem -LiteralPath $uploads -File -Recurse)
 if($uploadFiles.Count -ne [int]$manifest.upload_file_count){throw "Uploaded-file count does not match manifest."}
 foreach($entry in $manifest.upload_files){$path=Join-Path $uploads $entry.path;if(-not(Test-Path -LiteralPath $path)){throw "Missing uploaded file: $($entry.path)"};if((Get-Item -LiteralPath $path).Length -ne [long]$entry.bytes){throw "Upload size mismatch: $($entry.path)"};if((Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash -ne $entry.sha256){throw "Upload hash mismatch: $($entry.path)"}}
 $restoreStatus="NOT RUN"
 if($RestoreToDisposableMySql){
  if(-not(Get-Command docker -ErrorAction SilentlyContinue)){throw "Docker is required."}
  & docker run -d --rm --name $container -e "MYSQL_ROOT_PASSWORD=$password" mysql:8.4
  if($LASTEXITCODE -ne 0){throw "Could not start isolated MySQL."}
  $ready=$false
  for($i=0;$i -lt 60;$i++){& docker exec $container sh -lc 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqladmin ping -uroot --silent' *> $null;if($LASTEXITCODE -eq 0){$ready=$true;break};Start-Sleep -Seconds 2}
  if(-not $ready){throw "Disposable MySQL did not become healthy."}
  & docker cp $dump ($container+":/tmp/tagore-backup.sql");if($LASTEXITCODE -ne 0){throw "Could not copy SQL dump."}
  & docker exec $container sh -lc 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "CREATE DATABASE tagore_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"';if($LASTEXITCODE -ne 0){throw "Could not create isolated database."}
  & docker exec $container sh -lc 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot tagore_erp < /tmp/tagore-backup.sql';if($LASTEXITCODE -ne 0){throw "SQL restore failed."}
  $countOutput=& docker exec $container sh -lc 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -N -uroot tagore_erp -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 0x7461676f72655f657270"'
  $tableCount=($countOutput | Select-Object -Last 1).ToString().Trim()
  if($LASTEXITCODE -ne 0 -or [int]$tableCount -lt 1){throw "Restored database contains no tables."}
  $restoreStatus="PASS ($tableCount tables in disposable container)"
 }
 [pscustomobject]@{checksum="PASS";database_manifest_hash="PASS";uploaded_files="PASS ($($uploadFiles.Count) files)";disposable_mysql_restore=$restoreStatus} | Format-List
} finally {if(Get-Command docker -ErrorAction SilentlyContinue){& docker rm -f $container *> $null};if(Test-Path $work){Remove-Item -LiteralPath $work -Recurse -Force -ErrorAction SilentlyContinue}}
