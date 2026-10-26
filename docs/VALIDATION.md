# Release validation - 1.1.0

Local validation on 26 October 2026 used PHP 8.3 and MariaDB 10.11 with real InnoDB tables.

- 42 HTTP/database checks passed: fresh installation, disabled non-login recorder migration, anonymous entry and attribution, idempotency, corrective actions, manager-only resolution, date history, public/admin boundaries, CSRF, cooking dwell time, timed rice cooling, PWA subdirectory paths/assets, malformed ZIP rejection, version/application/file-hash checks, traversal/config/symlink/duplicate/bomb rejection, staged installation, private database/file backups, protected downloads, cancellation, migration-failure rollback/maintenance, multiple kitchens, and upgrading an independently installed original 1.0.0 portal while preserving its records, configuration and administrator account.
- 17 domain assertions and 20 updater assertions passed. The tests are available in tests/ and run in CI.
- Real WebKit browser tests passed for anonymous tablet submission, date history, local draft restoration, tablet installation instructions, the separate admin updater, and desktop/tablet/phone layouts. Representative views were visually inspected, including the corrected tablet form layout.
- PHP files were syntax-checked. All generated PNG icons were fully decoded and dimension-checked. Built ZIPs were checked by CRC and manifest hashes.

GitHub metadata/digest parsing is covered by deterministic fixtures; the application's live GitHub download still depends on the hosting server's curl extension and outbound HTTPS. Local browser testing does not certify an iPad/Android home-screen installation or a particular cPanel host. Check HTTPS, Apache/Nginx private-directory rules, real device installation, backups/restoration and kitchen-specific controls during deployment acceptance.

There is no claim of food-safety certification or an already deployed production kitchen service. Names entered on the frontend are intentionally self-reported; manager actions require authentication.
