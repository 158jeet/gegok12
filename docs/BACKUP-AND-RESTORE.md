# Tagore ERP backup and restore rehearsal

The Backup-Tagore.ps1 script backs up MySQL and Laravel storage/app without changing application records, running migrations, stopping services, or restoring over production.

- MySQL uses mysqldump --single-transaction with routines, triggers, events and binary-safe output.
- Uploaded files are copied from the persistent application volume.
- A manifest records the database hash and per-file size and SHA-256.
- The archive receives a separate SHA-256 sidecar.
- Off-machine upload is refused unless the named rclone remote is a crypt remote.
- Local retention only prunes tagore-backup-*.zip files in the selected local folder. Remote objects are never deleted by this script.

A backup is not release evidence until its checksum, archive contents and disposable restore test pass.

## Configure encrypted off-machine storage

1. Install rclone on the ERP server.
2. Configure a remote using rclone's crypt backend over the approved off-machine provider and a dedicated bucket/folder.
3. Protect the rclone config with Windows ACLs. Keep crypt passwords and a recovery copy outside this repository. Losing the crypt credentials makes backups unrecoverable.
4. Pass the crypt remote name, not its underlying plaintext remote.

## Create backup

Run from the repository root in a PowerShell session:

powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\deploy\windows\Backup-Tagore.ps1 -DestinationPath G:\TagoreBackups -RcloneRemote tagore-crypt

For a controlled local-only test, explicitly pass -LocalOnly. Local-only is not offsite protection and does not satisfy the release gate.

Schedule daily only after a successful manual run and after confirming the task identity can read .env.school, access Docker and read the rclone config. Do not place secrets in task arguments.

## Verify and restore rehearsal

Verify the checksum, database manifest and every uploaded file:

powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\deploy\windows\Test-TagoreBackup.ps1 -ArchivePath G:\TagoreBackups\tagore-backup-YYYYMMDD-HHMMSS.zip

Restore SQL into an isolated disposable MySQL 8.4 container (no host port is published and production is not targeted):

powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\deploy\windows\Test-TagoreBackup.ps1 -ArchivePath G:\TagoreBackups\tagore-backup-YYYYMMDD-HHMMSS.zip -RestoreToDisposableMySql

This verifies SQL importability and table creation, not full application recovery. A separate staging restore must validate the app, uploaded-file access, queue recovery and read-only workflows.

## Remaining operational controls

- Owner must select provider, region, retention, RPO and RTO.
- Schedule and monitor daily runs; alert on failure, missing archive, checksum mismatch or failed restore rehearsal.
- Test recovery from the offsite crypt remote, not only local storage.
- Periodically restore into isolated staging and record Laravel health/read-only workflow evidence.
- Protect local backup storage with restrictive NTFS ACLs and adequate free space.
- Never commit .env.school, rclone config, dumps, uploaded files, archives or encryption secrets.

## Release checklist

- [ ] Encrypted off-machine copy exists and checksum matches.
- [ ] Manifest and all upload hashes validate.
- [ ] Disposable MySQL restore passes.
- [ ] Staging application restore and read-only smoke pass.
- [ ] Retention, monitoring, credential recovery and restore ownership are documented.
