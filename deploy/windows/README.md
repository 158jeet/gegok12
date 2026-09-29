# Tagore ERP Windows Pilot

## First installation

Run `Install-TagorePilot.ps1` from Administrator PowerShell after installing Docker Desktop. It builds the ERP, starts the services, migrates and seeds demo data, then prints the LAN URL.

Temporary demo password: `Tagore@2026!`

Owner: owner@tagore-demo.local  
Principal: principal@tagore-demo.local  
Coordinator: coordinator@tagore-demo.local  
Teacher: teacher@tagore-demo.local  
Accounts: accounts@tagore-demo.local  
Fee Editor: fees@tagore-demo.local  
Parent: parent@tagore-demo.local  
Student: student@tagore-demo.local  

## Updating during testing

**Do not delete the installation folder or database to install a new version.**

From the same `C:\TagoreERP` folder, run:

`.\deploy\windows\Update-TagorePilot.ps1`

The updater creates a timestamped database/configuration backup, downloads the latest `main` source, preserves `.env.school`, rebuilds the containers, runs Laravel migrations, clears caches, and performs a local health check.

The MySQL database and Docker storage volume are **not deleted**. Uploaded documents and school data therefore remain available across normal application updates.

For an on-demand database backup without updating, run:

`.\deploy\windows\Backup-TagorePilot.ps1`

Backups are stored under `C:\TagoreERP\backups\`.

If an update fails, **do not delete the installation or Docker volumes**. Keep the backup folder and failure output so the application/data state can be diagnosed safely.

## Testing accounts

All demo accounts use password `Tagore@2026!`.

Testing only; replace/remove demo accounts before production.
