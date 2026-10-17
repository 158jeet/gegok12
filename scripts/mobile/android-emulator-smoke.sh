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
gradle -p "$PROJECT" connectedDebugAndroidTest --stacktrace --no-daemon
adb install -r "$APK"
PACKAGE="com.tagoregroup.$APP"
adb shell monkey -p "$PACKAGE" -c android.intent.category.LAUNCHER 1
sleep 8
if ! adb shell pidof "$PACKAGE" >/dev/null; then
  echo "App process did not remain running: $PACKAGE"
  adb logcat -d -t 2500 > "$ROOT/android-$APP-logcat.txt" || true
  exit 1
fi
echo "Emulator launch smoke test passed: $PACKAGE"
