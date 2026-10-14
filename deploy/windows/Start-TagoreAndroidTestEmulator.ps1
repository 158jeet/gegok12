param([int]$Port=5554,[string]$Avd='TagoreERP_Acceptance_API34',[int]$BootTimeoutSeconds=240)
$ErrorActionPreference='Stop'
$sdk='G:\Android\Sdk'
$env:ANDROID_SDK_ROOT=$sdk
$env:ANDROID_HOME=$sdk
$env:ANDROID_AVD_HOME=Join-Path $env:USERPROFILE '.android\avd'
$adb=Join-Path $sdk 'platform-tools\adb.exe'
$pinnedEmu='G:\Android\emulator-35.5.10\emulator\emulator.exe'
$emu=if(Test-Path $pinnedEmu){$pinnedEmu}else{Join-Path $sdk 'emulator\emulator.exe'}
$cfg=Join-Path $env:USERPROFILE ('.android\avd\'+$Avd+'.avd\config.ini')
$logDir='G:\TestArtifacts\android-acceptance'
New-Item -ItemType Directory -Force -Path $logDir | Out-Null
if(Test-Path $cfg){
  $backup=Join-Path $logDir ($Avd+'.config.original.bak')
  if(!(Test-Path $backup)){Copy-Item $cfg $backup}
  $lines=Get-Content $cfg
  $keys=[ordered]@{
    'hw.ramSize'='2048'; 'vm.heapSize'='512'; 'hw.cpu.ncore'='2'
    'hw.lcd.width'='720'; 'hw.lcd.height'='1280'; 'hw.lcd.density'='320'
    'showDeviceFrame'='no'; 'fastboot.forceColdBoot'='yes'; 'fastboot.forceFastBoot'='no'
    'firstboot.bootFromDownloadableSnapshot'='no'; 'firstboot.bootFromLocalSnapshot'='no'; 'firstboot.saveToLocalSnapshot'='no'
  }
  $pattern='^('+ (($keys.Keys | ForEach-Object {[regex]::Escape($_)}) -join '|') +')\s*='
  $lines=@($lines | Where-Object {$_ -notmatch $pattern})
  foreach($key in $keys.Keys){$lines+=($key+'='+$keys[$key])}
  [System.IO.File]::WriteAllLines($cfg,$lines,(New-Object System.Text.UTF8Encoding($false)))
}
& $adb start-server | Out-Null
$serial="emulator-$Port"
$state=& $adb devices | Select-String ("^"+[regex]::Escape($serial)+"\s+device$")
if(-not $state){
  $proc=Get-CimInstance Win32_Process -Filter "name='emulator.exe'" | Where-Object {$_.CommandLine -match [regex]::Escape("-avd $Avd") -or $_.CommandLine -match [regex]::Escape("-port $Port")}
  if(-not $proc){
    $log=Join-Path $logDir ("emulator-"+$Port+".log")
    $err=Join-Path $logDir ("emulator-"+$Port+".err")
    Start-Process -FilePath $emu -ArgumentList @('-avd',$Avd,'-no-window','-gpu','off','-feature','-Vulkan','-feature','-Wifi','-feature','-BluetoothEmulation','-no-snapshot','-no-snapshot-load','-no-snapshot-save','-no-audio','-no-boot-anim','-no-metrics','-memory','2048','-cores','2','-port',"$Port") -WindowStyle Hidden -RedirectStandardOutput $log -RedirectStandardError $err
  }
  $deadline=(Get-Date).AddSeconds($BootTimeoutSeconds)
  do { Start-Sleep -Seconds 4; $state=& $adb devices | Select-String ("^"+[regex]::Escape($serial)+"\s+device$") } while(-not $state -and (Get-Date)-lt $deadline)
}
if(-not $state){throw "Android emulator $serial did not become ADB-online within $BootTimeoutSeconds seconds. Check G:\TestArtifacts\android-acceptance logs; no duplicate emulator was launched."}
$boot=''
$deadline=(Get-Date).AddSeconds($BootTimeoutSeconds)
do{$boot=(& $adb -s $serial shell getprop sys.boot_completed 2>$null | Out-String).Trim();if($boot -eq '1'){break};Start-Sleep -Seconds 3}while((Get-Date)-lt $deadline)
if($boot -ne '1'){throw "Android emulator connected but Android OS did not finish booting within $BootTimeoutSeconds seconds."}
& $adb -s $serial shell settings put global animator_duration_scale 0
& $adb -s $serial shell settings put global transition_animation_scale 0
& $adb -s $serial shell settings put global window_animation_scale 0
Write-Output "READY: $serial | AVD=$Avd | Android API 34 | RAM=2048 MB | cores=2 | boot_completed=$boot"
& $adb -s $serial shell wm size
& $adb -s $serial shell wm density
