# Contributing

Use PHP 8.1+ and a private MySQL/MariaDB test database. Never commit config.php, database dumps, private backups, credentials or real kitchen records.

Run `php tests/domain.php` and `php tests/updater.php`. Lint every PHP file. Test a fresh installer and an upgrade in a separate installation, plus anonymous frontend entry, date history, backend role restrictions, cooling time/temperature boundaries, report exports and PWA paths under a subdirectory.

The frontend follows the Recipes design family. Keep controls large enough for tablets and distinguish self-reported names from authenticated manager actions. Never silently overwrite safety records, treat a draft as submitted, or infer a safe time exception from an unknown duration.

For releases, update version.php and CHANGELOG.md, run `python3 scripts/build-release.py --output dist`, test the resulting ZIP, then tag `vX.Y.Z`. The release workflow publishes the installable ZIP and checksum. Future database migrations must follow docs/UPDATES.md and include tested recovery steps.

Open a focused issue or pull request describing the trigger, expected behaviour, actual behaviour and validation. Use the security reporting route for vulnerabilities rather than publishing operational data or exploit details in a public issue.
