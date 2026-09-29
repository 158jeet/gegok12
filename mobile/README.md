# TagoreK12 mobile clients

The parent and teacher Android clients remain pinned to the upstream GegoK12 implementations so their existing ERP functionality is retained while the Tagore project owns the build/configuration layer.

- `mobile/parent` — Parent Android client
- `mobile/teacher` — Teacher Android client
- Both are built by `.github/workflows/android-clients.yml`.
- `scripts/mobile/prepare-tagore-android.sh` converts the upstream demo configuration into Tagore package IDs/app names and injects the API base URL through `BuildConfig`.

## API configuration

Set the GitHub repository variable `TAGORE_API_BASE_URL` to the deployed Laravel API base URL, including the trailing `/api/` path, before distributing functional APKs. The workflow uses a non-routable placeholder when the variable is absent so source/build validation does not depend on private credentials.

Firebase and Google Maps credentials are deliberately not committed. Firebase/Maps can be enabled later by supplying the corresponding project configuration and secrets.

The Android apps currently retain the upstream feature surface; the ERP's new offline-first Windows client remains under `desktop/`. Mobile offline synchronization should be extended endpoint-by-endpoint once the parent/teacher authentication contract is finalized, rather than silently pretending the legacy mobile APIs support the new sync protocol.
