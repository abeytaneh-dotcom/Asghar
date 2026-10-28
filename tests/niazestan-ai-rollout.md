# Niazestan's dedicated assistant

The default engine is a Persian domain assistant implemented in PHP, requiring no AI key or model server. It combines a finite-state dialogue, deterministic entity and price extraction, controlled taxonomy validation and a TF-IDF nearest-prototype item classifier. It is designed for classifieds and does not offer unrestricted general conversation or train a large language model from scratch.

## User behavior

Creation asks one question at a time about the item, category-specific attributes, price, city, district, address and extra details. Suggested responses use the site's controlled values. A complete draft can be explicitly accepted and transferred to the existing wizard, where photos, map coordinates, contact consent and final submission are confirmed. The native engine never fabricates coordinates, grants contact visibility, bypasses validation or publishes an ad.

Search extracts item/category, city and budget from Persian input, including Persian/Arabic digits, million/billion units, explicit rial conversion and corrections across turns. It asks for missing location/budget rather than displaying broad unrelated results. Only actual published, non-demo, unexpired and unsold listings are returned by server-side filtering. Contacts require login, a returned listing ID, current availability, an unblocked relationship and the seller's visibility settings.

The parser has deliberately bounded language coverage. It asks for clarification when item classification is ambiguous, a requested city is outside the catalog, or a required controlled value is missing. It does not claim to understand every free-form Persian sentence. Numeric amounts and selectable options are the reliable supported input. Full mobile visual QA and production push delivery remain unverified.

## What learns

1. Personal memory is off by default. A signed-in user can explicitly opt in to retaining city and search budget for reuse for 30 days. New instructions override remembered values. No address, phone, coordinates or chat body is copied into personal memory. Turning the option off removes stored preferences. Expired memory is not used.
2. Users may separately submit a short item phrase and corrected category, with an unchecked consent box. The feedback is bound to an actual recent conversation turn and user account. Contact/address patterns are rejected, input is bounded and submissions are rate-limited. Raw conversations are not used as shared training data.
3. Samples begin as `pending`, which has no effect on inference. An admin with the existing SMS-elevated session can approve, reject or disable a sample from the admin page. Only approved examples are included in the classifier. Conflicting active mappings require resolving the previous example first. The next request immediately uses the changed prototype set; no GPU, batch training, external model or worker is required. The model admits up to 500 active examples.
4. Users can delete their own preferences and all submitted examples, including approved ones. Deleted/disabled examples no longer influence future predictions. Account export includes the user's AI data, and account deletion removes it in the same storage transaction. Data is kept in the existing protected SQLite/JSON storage, separate from public source code.

This is supervised incremental learning of item classification plus optional personal memory, not autonomous modification of prompts, security rules or model weights. Automatic publication and access-control rules never learn from user text.

## Optional cloud model

Set server `NZ_AI_ENGINE=cloud` to use the existing OpenAI Responses API adapter, which supports strict JSON-schema output and uses `store: false`. Set `NZ_AI_API_KEY` on the server, or add the GitHub Actions secret `NIAZESTAN_AI_API_KEY` before deployment. The cPanel workflow writes that secret only to protected `public_html/.marketplace-data/ai-config.php`; it is never committed or sent to browsers. `NZ_AI_MODEL` can override the default `gpt-4.1-mini` with a compatible Responses model. The interface discloses external processing when cloud mode is selected. Cloud failures are explicit. Native mode remains the default even if a provider secret exists.

No live provider request was made in this change. The dedicated native engine and its learning loop are tested without provider credentials.

## Install and chat notifications

The existing app installation, separate manual flow, unread polling and chat controls remain. The install action uses the browser's supported PWA prompt or platform guide; the browser/user must confirm installation. Signed-in users can opt into native Web Push; P-256 VAPID keys are generated and persisted on the server. Generic empty-payload notifications contain no message text or phone number. Subscription ownership, allowed push-provider hosts, redirect rejection and private-IP checks remain enforced. Push sends occur after successful storage commits and are best effort without a durable retry queue. Actual Android and installed iOS background-delivery tests are still required on the production origin.

## Verification and release

From the repository root, with PHP 8.1+, mbstring, OpenSSL, cURL and Node:

```
php tests/niazestan-ai-test.php
php tests/niazestan-native-test.php
node tests/niazestan-ai-http.mjs
```

HTTP tests copy the app to a temporary directory and use isolated data and sessions. Their fixture endpoints are never deployed. `PHP_BIN` and JSON-array `PHP_ARGS_JSON` can select an isolated PHP runtime. Tests cover provider-independent dialogue, all twelve top-level categories, numeric extraction, corrections, pending/approved/disabled learning, privacy, authorization, memory consent, cross-account isolation and deletion. HTTP integration tests run with SQLite and JSON fallback. Local DOM checks exercise suggested answers, incomplete-draft gating, manual handoff, unchanged consent/map fields and opt-in learning UI. Full visual browser/device testing has not been completed.

Before production sign-off, run complete creation and search journeys with representative real listings, verify admin review with the existing SMS flow, inspect the mobile layout, and test installation/background notifications on real devices. The existing hosting, OTP, moderation, data and SEO routes remain in use.

## Personal recommendations and avatar

The launcher and assistant heading use `WebSaza/nz-ai-avatar.webp`, a 256-pixel, ~7 KB optimized derivative of the built-in imagegen avatar: a centered friendly ivory ceramic robot face with smiling teal eyes, coral accent, deep teal backdrop, no lettering. The binary is committed and explicitly uploaded by the cPanel workflow. The versioned image is included in the service worker static cache.

Personalization uses separate, explicit consent; enabling the older city/budget memory option does not enable search-history collection. With personalization on, completed AI searches and basic manual item/category/city/budget searches are retained for reuse for 30 days. Manual searches with unsupported extra filters are skipped, rather than silently dropping constraints. Stored data contains controlled filters and bounded item terms, not raw conversations, contact numbers, precise address or coordinates. Supported item attributes are retained so recommendations cannot drop condition/model constraints. Unknown or unsafe filters are not recorded. There are at most 30 unique searches and 100 interaction records per account, with deduplication and expiry.

The assistant offers continuation chips for the three most recent distinct searches and up to four real eligible listing recommendations. A recent-search match supplies candidates; explicit likes and successful contact actions increase personal affinity, and dislikes suppress a listing. All candidates must still satisfy a saved search, remain published/unexpired/non-demo, and pass seller blocking checks. Suggestions never invent ads or reveal contact information. Reaction writes require CSRF, a recent account-bound recommendation set, and current listing eligibility. Dislikes can be undone. Historical searches can only be recalled within their owning account.

This is per-account adaptation from interactions, not unreviewed global model training. Shared item-classification improvements still require the existing explicitly consented, admin-reviewed examples. Turning personalization off removes its preference/history/interaction records; the existing forget/export/delete paths include the new data. Recommendation content appears in an expandable section to keep the chat input reachable. Optional history-write failures do not block search or contact results.

Run `php tests/niazestan-ai-personal-test.php` for ranking, constraints, consent, account isolation, history resumption, undo, expiry and erasure. The HTTP test also exercises the real search-history/reaction/recall endpoints with SQLite and JSON fallback.
