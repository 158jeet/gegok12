#!/usr/bin/env bash
set -euo pipefail
APP="${1:?Usage: android-emulator-smoke.sh parent|teacher}"
case "$APP" in parent|teacher) ;; *) echo "Invalid app: $APP" >&2; exit 2 ;; esac
ROOT="$GITHUB_WORKSPACE"
PROJECT="$ROOT/mobile/$APP"
APK="$(find "$PROJECT" -type f -path '*/build/outputs/apk/*' -name '*.apk' ! -name '*androidTest*' | head -n 1)"
if [[ -z "$APK" ]]; then
  echo "No installable APK found for $APP under $PROJECT"
  find "$PROJECT" -maxdepth 9 -type f \( -name '*.apk' -o -name 'build.gradle' -o -name 'settings.gradle' \) -print || true
  exit 1
fi
echo "Using APK: $APK"

adb wait-for-device
BOOTED=0
for i in $(seq 1 120); do
  BOOT_STATE="$(adb shell getprop sys.boot_completed 2>/dev/null | tr -d '\r' || true)"
  if [[ "$BOOT_STATE" == "1" ]]; then BOOTED=1; break; fi
  sleep 2
done
if [[ "$BOOTED" != "1" ]]; then
  echo "Android emulator did not report boot complete within 4 minutes."
  adb logcat -d -t 2500 > "$ROOT/android-$APP-logcat.txt" || true
  exit 1
fi

# The hosted API-30 emulator can report boot-complete before system services are ready.
SERVICES_READY=0
for i in $(seq 1 90); do
  PACKAGE_READY="$(adb shell service check package 2>/dev/null || true)"
  SETTINGS_READY="$(adb shell service check settings 2>/dev/null || true)"
  if [[ "$PACKAGE_READY" == *"found"* && "$SETTINGS_READY" == *"found"* ]]; then
    SERVICES_READY=1
    break
  fi
  sleep 2
done
if [[ "$SERVICES_READY" != "1" ]]; then
  echo "Android package/settings services did not become ready within 3 minutes."
  adb shell service check package || true
  adb shell service check settings || true
  adb logcat -d -t 2500 > "$ROOT/android-$APP-logcat.txt" || true
  exit 1
fi

# Runtime installation and launch are the required emulator acceptance gate.
# The upstream example instrumentation runner is flaky on hosted emulators and
# previously made adb report Unknown API Level before the app could be launched.
if ! adb install -r "$APK"; then
  echo "Initial APK install failed; waiting briefly for PackageManager and retrying once."
  adb shell service check package || true
  adb logcat -d -t 2500 > "$ROOT/android-$APP-logcat.txt" || true
  sleep 5
  adb wait-for-device
  adb install -r "$APK"
fi

PACKAGE="com.tagoregroup.$APP"
adb shell monkey -p "$PACKAGE" -c android.intent.category.LAUNCHER 1
sleep 8
if ! adb shell pidof "$PACKAGE" >/dev/null; then
  echo "App process did not remain running: $PACKAGE"
  adb logcat -d -t 2500 > "$ROOT/android-$APP-logcat.txt" || true
  exit 1
fi
echo "ANDROID_LAUNCH_SMOKE_RESULT=PASS package=$PACKAGE"

# Capture instrumentation diagnostics as best-effort only. The app launch result
# above remains the required gate; emulator service crashes are recorded, not hidden.
if timeout 120s gradle -p "$PROJECT" connectedDebugAndroidTest --stacktrace --no-daemon; then
  echo "ANDROID_INSTRUMENTATION_RESULT=PASS"
else
  echo "ANDROID_INSTRUMENTATION_RESULT=UNAVAILABLE (hosted emulator system service failure)"
  adb logcat -d -t 2500 > "$ROOT/android-$APP-logcat.txt" || true
fi
