# Niazestan AI concierge and chat notifications

The existing `/nz` application gains separate AI/manual tabs for creation and search. AI collects Persian conversational input, maps it to the site's controlled taxonomy and locations, and drafts a title, description, price and category attributes. Accepting a draft fills the existing manual wizard. The user adds photos, confirms the map point, reviews contact permissions, and explicitly submits through the unchanged server validation and moderation path. No model can publish, set coordinates, or bypass approval.

Search runs over actual published, unexpired, unsold, non-demo listings. Category, city, district, budget, keywords and supplied attributes are applied by PHP. Unknown prices are excluded when a numeric budget is set. Contacts require login, a returned result ID, a still-available listing, an unblocked relationship and the seller's phone visibility permission. Rendering uses text nodes; model strings never become HTML.

## Enable the provider

Add `NIAZESTAN_AI_API_KEY` to the repository's GitHub Actions secrets. After approval/merge into `websaza-hosting`, the existing cPanel deployment writes the key only to `public_html/.marketplace-data/ai-config.php`. The protected file is PHP with no direct output and is not committed, cached or sent to browsers. If the secret is absent, deployment leaves an existing server configuration alone. The model defaults to `gpt-4.1-mini`; a server `NZ_AI_MODEL` environment variable can select another Responses API model supporting strict JSON schema. Alternatively configure server `NZ_AI_API_KEY` directly.

The provider uses the OpenAI Responses API with `store: false`. Recent conversation and the current draft are held in the user's PHP session, with a one-hour conversation expiry and a bounded history. Users can reset the conversation. The interface discloses external AI processing. IP limits and CSRF validation protect provider calls. A missing key or provider error is shown explicitly; manual operation remains available.

## Install and notifications

The install button opens the browser's PWA installation prompt when supported and otherwise opens the existing platform guide. Installation always requires the user's browser confirmation. On iOS, users add the app to their home screen.

Signed-in users can enable/disable background notifications for their device. Native Web Push uses a persisted P-256 VAPID key pair generated on the server (requires PHP OpenSSL and cURL), with a maximum of six devices per account. Push subscriptions are stored in the existing database. Sends happen after successful notification commits. Empty push payloads carry no message text or phone number; the worker displays a generic notification linked to the authenticated notification page. FCM, Mozilla and Apple push hosts are allowed; redirects, nonstandard ports, credentials, private IPs and arbitrary URLs are rejected. Delivery is best effort; transient push errors are logged without blocking database commits. There is no durable retry queue in this version.

While a page is visible, a 15-second poll updates unread counts and shows an in-app toast. Chat messages continue using the existing live polling. Real background delivery depends on device/browser permission, browser support, HTTPS and hosting access to push providers. A real Android and installed iPhone delivery check remains required before production sign-off. Device subscription transfer follows the active login when the app is reopened; notifications contain generic text only.

## Verification

Run `php tests/niazestan-ai-test.php` and `node tests/niazestan-ai-http.mjs` from the repository root, with PHP 8.1+ and extensions mbstring, cURL and OpenSSL. HTTP tests copy the application to a temporary directory and use isolated sessions/data. Their fixture endpoint is never deployed. Optional `PHP_BIN` and JSON-array `PHP_ARGS_JSON` environment variables support an isolated PHP runtime.

Local verification also exercised AI/manual mode switching, accepted draft transfer, preservation of values, blank/unconfirmed map coordinates, unchanged contact checkboxes, and HTML injection resistance in a DOM environment with a mocked provider response. No live provider call was made. Full visual mobile-browser testing and actual push delivery have not been verified locally.

Before release: configure the provider key, run a real multi-turn creation/search conversation, inspect phone privacy with two accounts, install on Android/iOS, and send a real chat message while the recipient's app is closed. Existing accounts, ads, OTP, moderation and SEO routes remain on the current hosting and storage system.
