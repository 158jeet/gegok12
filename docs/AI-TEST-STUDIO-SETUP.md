# AI Test-Plan Studio setup

The ERP includes a role-restricted AI test-plan form on the Growth & Quality workspace. It sends only the user-authored test scenario, selected module, role, and test type to an administrator-configured Ollama-compatible endpoint. The response is validated as JSON and displayed as text; generated plans are suggestions and are never executed automatically.

## Configuration

Set these variables in the server's protected environment file (never commit them):

- `OLLAMA_URL`: private base URL reachable from the ERP app container, for example `http://ollama.internal:11434`.
- `OLLAMA_MODEL`: model name available on that endpoint, for example `qwen2.5:1.5b`.

The app calls `POST {OLLAMA_URL}/api/generate` with JSON mode enabled. After changing configuration, clear/rebuild Laravel config cache and verify the provider status in the ERP UI.

## Security and resource requirements

- Do not expose Ollama directly to the public internet. Use a private LAN/VPN and firewall allowlisting so only the ERP app can reach the endpoint.
- Do not enter real student/parent personal data in test scenarios.
- Generated steps must be reviewed and executed only in staging.
- The current ERP server has approximately 9.9 GB RAM. A model was deliberately not installed locally because it could compete with the database, queue worker, and ERP app. Use a separate workstation or dedicated private host.
- If the endpoint is absent or unavailable, the UI displays a safe status message and does not change ERP data.
