param([string]$Serial='emulator-5554',[int]$BootTimeoutSeconds=240)
$ErrorActionPreference='Stop'
$sdk='G:\Android\Sdk'
$adb=Join-Path $sdk 'platform-tools\adb.exe'
$apk='G:\TestArtifacts\TagoreParent-debug-emulator-smoke.apk'
$out='G:\TestArtifacts\android-acceptance'
New-Item -ItemType Directory -Force -Path $out | Out-Null
if(!(Test-Path $apk)){throw "Debug smoke APK not found: $apk"}
$deadline=(Get-Date).AddSeconds($BootTimeoutSeconds)
$online=$false
while((Get-Date)-lt $deadline){
  $state=& $adb devices | Select-String ("^"+[regex]::Escape($Serial)+"\s+device$")
  if($state){$online=$true;break}
  Start-Sleep -Seconds 3
}
if(-not $online){throw "Android emulator $Serial is not ADB-online; no app was installed. See android-emulator logs."}
$boot=''
$deadline=(Get-Date).AddSeconds($BootTimeoutSeconds)
do{$boot=(& $adb -s $Serial shell getprop sys.boot_completed 2>$null | Out-String).Trim();if($boot -eq '1'){break};Start-Sleep -Seconds 3}while((Get-Date)-lt $deadline)
if($boot -ne '1'){throw 'Android OS did not complete boot; UI acceptance not run.'}
& $adb -s $Serial logcat -c
$install=& $adb -s $Serial install -r $apk 2>&1
$install | Set-Content (Join-Path $out 'install.txt')
if($LASTEXITCODE -ne 0){throw "APK install failed: $install"}
& $adb -s $Serial shell am force-stop com.gegosoft.yourappname
& $adb -s $Serial shell monkey -p com.gegosoft.yourappname -c android.intent.category.LAUNCHER 1 | Out-File (Join-Path $out 'launch.txt')
Start-Sleep -Seconds 12
& $adb -s $Serial shell screencap -p /sdcard/tagore-parent-smoke.png
& $adb -s $Serial pull /sdcard/tagore-parent-smoke.png (Join-Path $out 'parent-app.png') | Out-Null
& $adb -s $Serial shell uiautomator dump /sdcard/tagore-parent-ui.xml | Out-File (Join-Path $out 'uiautomator.txt')
& $adb -s $Serial pull /sdcard/tagore-parent-ui.xml (Join-Path $out 'parent-app-ui.xml') | Out-Null
& $adb -s $Serial logcat -d -t 5000 | Set-Content (Join-Path $out 'logcat.txt')
$log=Get-Content (Join-Path $out 'logcat.txt') -Raw
$fatal=$log -match 'FATAL EXCEPTION|Process: com\.gegosoft\.yourappname.*died'
$result=[pscustomobject]@{timestamp=(Get-Date).ToString('o');serial=$Serial;package='com.gegosoft.yourappname';installSucceeded=$true;bootCompleted=$true;fatalCrash=$fatal;screenshot=(Join-Path $out 'parent-app.png');uiDump=(Join-Path $out 'parent-app-ui.xml');logcat=(Join-Path $out 'logcat.txt');testOnlyBuild=$true;note='Debug APK was built with a temporary fake Firebase smoke configuration; Retrofit baseUrl in source is blank, so backend workflows are not accepted.'}
$result | ConvertTo-Json -Depth 5 | Set-Content (Join-Path $out 'result.json') -Encoding UTF8
$result | Format-List
if($fatal){throw 'App process crashed; inspect logcat.txt and parent-app-ui.xml.'}
