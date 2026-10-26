# Tablet app (PWA)

The kitchen frontend is the app. Staff do not sign in. The separate admin panel remains password-protected.

- iPad: open the HTTPS frontend in Safari, tap Share, then Add to Home Screen. If offered, enable Open as Web App.
- Android: open the HTTPS frontend in Chrome or another supporting browser, then choose Install app or Add to Home screen.
- The in-page Install on tablet button opens an install prompt where supported, or displays platform instructions. Installation availability depends on the browser/OS. No App Store/Play Store listing or native app binary is included.

The app opens standalone with its own Kitchen Safety icon and adapts to tablet portrait/landscape and phones. The service worker stores static assets and an offline explanation. It never stores admin pages or server temperature histories in its cache, and POST submissions are not cached.

## Connection and drafts

Records require a working connection to the server. The online indicator reflects browser connectivity; only a successful server response confirms a saved check. If there is a network failure, check History before entering another record. The same form token prevents duplicate saves when the same form is retried.

The record form can keep a local unsaved draft on that browser. Restore or discard it on the next visit. Drafts expire from the restore UI after seven days. A draft is not an official record and is not queued for automatic submission. Reconnect and submit it with the actual measured time and any required late-entry explanation. Successful saving clears the matching pending draft.

Drafts are device/browser-local and may contain a recorder name or batch details. Use an appropriately managed shared tablet and discard unwanted drafts. A private-browsing session, storage cleanup or another browser will not preserve/share them. Do not rely on this as the only copy of a required safety record during an outage; use the kitchen's approved contingency procedure.
