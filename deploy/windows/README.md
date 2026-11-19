# Tagore ERP Windows Pilot

Run Install-TagorePilot.ps1 from Administrator PowerShell after installing Docker Desktop. It builds the ERP, starts the services, migrates and seeds demo data, then prints the LAN URL.

Temporary demo password: Tagore@2026!

Owner: owner@tagore-demo.local
Principal: principal@tagore-demo.local
Coordinator: coordinator@tagore-demo.local
Teacher: teacher@tagore-demo.local
Accounts: accounts@tagore-demo.local
Fee Editor: fees@tagore-demo.local
Parent: parent@tagore-demo.local
Student: student@tagore-demo.local

Testing only; replace/remove before production.

## Production scheduled tasks

The production setup installs two Windows Scheduled Tasks:
- Tagore ERP - Renew IP TLS Certificate: daily certificate renewal check.
- Tagore ERP - Laravel Scheduler: runs Laravel's schedule:run through the running Docker app every minute so jobs such as closed-fee-year archiving can execute.

After updating an existing server to a release that includes this installer, run deploy/windows/Install-TagoreServerTask.ps1 from elevated PowerShell in the repository root. Verify both tasks in Task Scheduler and inspect the Laravel scheduler task's last-run result and deploy/windows/logs/tagore-scheduler.log. A registered task is not proof of a successful archive; verify archive rows and files too.
