# Updates and recovery

## From version 1.0.0

1. Back up the database and application, including config.php.
2. Extract the 1.1.0 built ZIP privately. Copy its files over the old application in cPanel. Preserve config.php and the existing database. Do not reinstall.
3. Visit the frontend or admin panel. Migration 2 adds a disabled system recorder identity and settings, preserving old accounts and every existing record. The frontend now opens without a login; manager accounts sign in at admin/.
4. Delete install.php after the file upload if it is present. New accounts are not needed for staff temperature entry.

This first manual upload is necessary because version 1.0.0 did not contain an updater.

## Later versions: release ZIP

In Admin > Updates, select a trusted built release ZIP (maximum 32 MiB, also subject to the host's PHP upload limits). Stage it, review the version/file count, then confirm installation. Packages must be newer than the installed version and include release.json with the matching application ID and full file hashes. Automatic GitHub source-code archives are not built release packages.

The updater rejects invalid paths, protected configuration/storage, archive symlinks, duplicate paths, unlisted/missing files, invalid hashes and extraction bombs. Configuration is never replaced by the ZIP. Database records are not replaced by application files.

## Directly from GitHub

Check latest version queries the public repository's latest stable published release. Download and stage verifies the ZIP against GitHub's SHA-256 asset digest. The final Back up and install action is the same as for uploads. The release must contain `mustrhq-kitchen-safety-X.Y.Z.zip`; a missing release or rate limit is reported without changing the installed application. GitHub API calls require outbound HTTPS and PHP curl. No user token is stored.

GitHub version checks are manual, not scheduled. Checksums establish that bytes match the selected release asset; they do not protect against a compromised publisher account. Only install releases you trust.

## Backup and recovery

Before replacement, a private backup ZIP stores a consistent database snapshot, affected existing files and recovery metadata. Admins can download successful-update backups from Update history. They include operational data and should be stored privately. Configuration remains in place and should also be included in your own hosting backups.

The frontend pauses with HTTP 503 during replacement. A file replacement failure attempts to restore affected original files and remove newly created files. SQL DDL cannot always be rolled back transactionally: if a database migration fails or file recovery fails, maintenance remains enabled. The admin Updates screen remains available for recovery information.

For host-assisted recovery:

1. Keep the site in maintenance and take a copy of its current state.
2. Extract the relevant private backup outside the web root. Restore original code from `files/` and remove newly introduced paths identified in `recovery.json` as previously absent.
3. Restore `database.sql` into the correct database using phpMyAdmin or a private database client. It contains DROP/CREATE/INSERT statements and replaces that snapshot's tables; never run it against another installation.
4. Restore the matching configuration from your independent host backup if required.
5. Verify versions, records, account access and controls, then remove `private/maintenance.json` to reopen the frontend.

Backups and staged packages may contain sensitive data or executable code; never expose the private directory over HTTP.

## Release maintainer contract

Use scripts/build-release.py to build a package from version.php. Update the semantic APP_VERSION and keep APP_ID unchanged. Database schema increases must include hashed `migrations/NNN.json` files with integer `schema` and an ordered `statements` array of SQL strings. Write additive, reviewed migrations and test the upgrade with a real backup/restore. The updater keeps failed-migration maintenance enabled rather than claiming a transactional rollback of SQL DDL. Publish the built ZIP as a stable GitHub Release asset. The release workflow builds it on a version tag or manual workflow run.
