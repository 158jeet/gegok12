#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
BASE_URL="${TAGORE_API_BASE_URL:-https://example.invalid/api/}"

for APP in parent teacher; do
  DIR="$ROOT/mobile/$APP"
  PKG="com.tagoregroup.$APP"

  find "$DIR/app/src" -type f \( -name '*.java' -o -name '*.xml' -o -name '*.kt' \) -print0 |
    xargs -0 sed -i \
      -e "s/com\\.gegosoft\\.yourappname/$PKG/g" \
      -e 's/Parent Demo School/Tagore Group Parent/g' \
      -e 's/Teacher Demo School/Tagore Group Teacher/g'

  sed -i 's/[[:space:]]*package="com.tagoregroup.[^"]*"//' "$DIR/app/src/main/AndroidManifest.xml"

  # Firebase/Maps secrets are intentionally optional for the open build.
  sed -i "/apply plugin: 'com.google.gms.google-services'/d" "$DIR/app/build.gradle"
  sed -i "/apply plugin: 'com.google.android.libraries.mapsplatform.secrets-gradle-plugin'/d" "$DIR/app/build.gradle" || true

  sed -i "s/viewBinding = true/viewBinding = true\\n        buildConfig true/" "$DIR/app/build.gradle"

  python3 - "$DIR/app/build.gradle" "$BASE_URL" <<'PY'
import pathlib, sys
path = pathlib.Path(sys.argv[1])
url = sys.argv[2].replace('\\', '\\\\').replace('"', '\\"')
text = path.read_text()
needle = '        versionName "1.0"'
if "TAGORE_API_BASE_URL" not in text:
    text = text.replace(needle, needle + f'\n        buildConfigField "String", "TAGORE_API_BASE_URL", "{url}"')
path.write_text(text)
PY

  mkdir -p "$DIR/app/src/main/java/$PKG/Helper"
  cat > "$DIR/app/src/main/java/$PKG/Helper/ApiClient.java" <<JAVA
package $PKG.Helper;

import java.util.concurrent.TimeUnit;
import okhttp3.Interceptor;
import okhttp3.OkHttpClient;
import okhttp3.logging.HttpLoggingInterceptor;
import retrofit2.Retrofit;
import retrofit2.adapter.rxjava2.RxJava2CallAdapterFactory;
import retrofit2.converter.gson.GsonConverterFactory;

public class ApiClient {
    private static Retrofit retrofit;

    public static Retrofit getClient() {
        HttpLoggingInterceptor interceptor = new HttpLoggingInterceptor();
        interceptor.setLevel(HttpLoggingInterceptor.Level.BASIC);
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor((Interceptor) interceptor)
                .connectTimeout(30, TimeUnit.SECONDS)
                .readTimeout(30, TimeUnit.SECONDS)
                .build();

        String baseUrl = BuildConfig.TAGORE_API_BASE_URL;
        if (!baseUrl.endsWith("/")) baseUrl += "/";

        retrofit = new Retrofit.Builder()
                .baseUrl(baseUrl)
                .addCallAdapterFactory(RxJava2CallAdapterFactory.create())
                .addConverterFactory(GsonConverterFactory.create())
                .client(client)
                .build();
        return retrofit;
    }
}
JAVA
done
