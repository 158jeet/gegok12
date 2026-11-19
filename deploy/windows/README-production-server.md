Production deployment for the school server.

Public IP: 59.90.66.12

Prerequisites: Docker Desktop with WSL2, Administrator PowerShell, a fixed LAN IP for the ERP server, and Syrotech router access.

1. Copy deploy/windows/.env.production.example to .env.school.
2. Set strong TAGORE_DB_PASSWORD and TAGORE_DB_ROOT_PASSWORD values in .env.school. Never commit .env.school.
3. In the Syrotech router forward TCP 80 to the ERP server LAN IP:80 and TCP 443 to the ERP server LAN IP:443. Do not forward 3306, 6379, 8080, 9000 or Docker ports.
4. From the repository root run: .\deploy\windows\Server-Setup.ps1
5. The script detects the LAN IP, configures Windows Firewall, starts Laravel/MySQL/Redis/queue, migrates the database, requests the short-lived Let's Encrypt IP certificate for 59.90.66.12 and starts HTTPS.
6. Run: .\deploy\windows\Install-TagoreServerTask.ps1 to install the daily certificate-renewal check.
7. Verify https://59.90.66.12 from an external network.

Let's Encrypt now supports publicly trusted IP-address certificates, but they are short-lived, so renewal automation is required. The first certificate request will fail if TCP 80 is not reachable from the Internet or if the router WAN address is not actually 59.90.66.12.

After HTTPS is confirmed, set the GitHub repository variable TAGORE_API_BASE_URL to https://59.90.66.12/api/ and rebuild the Parent and Teacher Android clients.

For production, replace/remove demo accounts, configure real mail/SMS/WhatsApp/payment providers, and establish daily off-machine backups for the MySQL database and uploaded files.