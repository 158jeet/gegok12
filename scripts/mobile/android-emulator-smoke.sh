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
for i in $(seq 1 90); do
  BOOTED="$(adb shell getprop sys.boot_completed 2>/dev/null | tr -d '\r' || true)"
  [[ "$BOOTED" == "1" ]] && break
  sleep 2
done
test "$(adb shell getprop sys.boot_completed | tr -d '\r')" = "1"
# Some hosted API-30 images report boot complete before PackageManager and
# Settings are available. Do not start instrumentation until both services respond.
SERVICES_READY=0
for i in $(seq 1 150); do
  PACKAGE_READY="$(adb shell service check package 2>/dev/null || true)"
  SETTINGS_READY="$(adb shell service check settings 2>/dev/null || true)"
  if [[ "$PACKAGE_READY" == *"found"* && "$SETTINGS_READY" == *"found"* ]]; then
    SERVICES_READY=1
    break
  fi
  sleep 2
done
if [[ "$SERVICES_READY" != "1" ]]; then
  echo "Android package/settings services did not become ready within 5 minutes."
  adb shell getprop sys.boot_completed || true
  adb shell service check package || true
  adb shell service check settings || true
  adb logcat -d -t 2500 > "$ROOT/android-$APP-logcat.txt" || true
  exit 1
fi
gradle -p "$PROJECT" connectedDebugAndroidTest --stacktrace --no-daemon
# Instrumentation can restart Android system services. Recheck readiness before install.
for i in $(seq 1 30); do
  BOOTED="$(adb shell getprop sys.boot_completed 2>/dev/null | tr -d '\r' || true)"
  PACKAGE_READY="$(adb shell service check package 2>/dev/null || true)"
  if [[ "$BOOTED" == "1" && "$PACKAGE_READY" == *"found"* ]]; then break; fi
  sleep 2
done
if ! adb install -r "$APK"; then
  echo "Initial APK install failed; collecting diagnostics and retrying once after package service settles."
  adb shell getprop sys.boot_completed || true
  adb shell service check package || true
  adb logcat -d -t 2500 > "$ROOT/android-$APP-logcat.txt" || true
  sleep 10
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
echo "Emulator launch smoke test passed: $PACKAGE"
