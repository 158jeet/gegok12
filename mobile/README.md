# TagoreK12 mobile clients

The parent and teacher Android clients are tracked as pinned public GegoK12 source repositories so the ERP keeps the upstream mobile implementation instead of duplicating it.

- `mobile/parent` — GegoK12 Parent App
- `mobile/teacher` — GegoK12 Teacher App

Both clients consume the ERP's API-first backend. The Tagore offline staff client is separate under `desktop/` because its Windows workflow requires durable local attendance/outbox storage.
