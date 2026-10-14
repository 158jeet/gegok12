# CCTV / AI safety integration status

## Connector implemented

The ERP Growth & Quality workspace now has a CCTV connector-status entry backed by a read-only Frigate NVR stats check. It is restricted to the existing authenticated Tagore route and principal/owner roles, rate-limited, uses a short connection/read timeout, and never returns the API token or proxies camera streams. The status response explicitly does not claim that individual streams or detection accuracy are verified.

## Required private configuration

Set these values in the protected server environment file (never commit secrets):

- `FRIGATE_URL`: private base URL reachable from the ERP app container, e.g. `http://frigate.internal:5000`.
- `FRIGATE_API_TOKEN`: optional bearer token if the Frigate deployment requires it.

After configuration, rebuild Laravel's config cache and verify the connector from the owner/principal account. Keep Frigate and camera RTSP endpoints on a private network/VPN; do not expose camera streams or the NVR directly to the public internet.

## Not yet complete

This is the ERP connector foundation, not a deployed CCTV system. No NVR endpoint, camera inventory, RTSP URLs, credentials, retention rules, privacy zones, or hardware acceleration were supplied/configured in this project. Therefore live camera viewing, per-camera health, AI detection events, alerting, visitor/person analytics, retention and privacy review have not been enabled or tested. Do not claim those features are live until a real private NVR is connected and the acceptance checklist passes.

## Acceptance checklist for a real installation

1. Identify NVR/camera make and models, camera count, network/VLAN, supported RTSP/ONVIF, and authorised stream credentials.
2. Deploy Frigate in an isolated Docker Compose project with pinned image version, persistent recordings/config, restricted ports, backups, and a documented retention period.
3. Verify each stream and detector locally before connecting ERP.
4. Configure `FRIGATE_URL` and token only in the protected runtime environment; clear/rebuild config cache.
5. Test unauthenticated access (must redirect/block), student/parent/teacher access (must return 403), and owner/principal status access.
6. Confirm no stream URL, token, recording URL, or personal data leaks into logs, public pages, or API responses.
7. Review school notice/consent, access audit, retention/deletion, and incident response policies before activating analytics.
8. Run staging network-loss, NVR-restart, storage-full, backup/restore and alert tests before rollout.

Reference: https://docs.frigate.video/ and https://github.com/blakeblackshear/frigate (MIT-licensed open-source NVR; verify upstream license and release notes before deployment).
