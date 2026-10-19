# KaONTIME verification

## Required release evidence

| Claim | Evidence owner | Minimum evidence |
|---|---|---|
| Accounts are authenticated and roles cannot be self-assigned | Backend implementation | Laravel feature tests for registration, login, token revocation, and administrator authorization |
| Customer data stays private | Backend implementation + integration | Feature tests and HTTP checks with two independent customer accounts; cross-account order/address access must return 404 |
| Checkout stores authoritative, consistent data | Backend implementation + production integration | Tests for price tampering, insufficient stock, transactions, idempotent retry and key mismatch; a real hosted checkout |
| Cancellation does not inflate inventory | Backend implementation + integration | Cancel once, repeat cancellation, and compare stock before/after |
| Favorites and addresses survive sign-in | Integration | Save, log out, log in, then retrieve the same records |
| Administrators can operate the application | Backend implementation + browser integration | Unauthorized 403, successful authorized menu edits, order-status updates, and real reports |
| The frontend is runnable | Frontend implementation | Production build, TypeScript check, ESLint, then browser route/error inspection |
| The release works outside the local machine | Release integration | Hosted API and browser checks through the public frontend URL, against the Singapore Neon database |

## Repeatable commands

Backend:

```sh
cd backend
php artisan test
```

Frontend:

```sh
cd VueActivity
npm run typecheck
npm run lint
npm run build
```

HTTP integration, from the project root:

```sh
node scripts/verify-api.mjs
API_URL=https://vueactivity-kaontime.vercel.app/api node scripts/verify-api.mjs
```

The HTTP script creates uniquely named verification accounts and a clearly labeled test order, cancels the order to restore stock, and removes its saved address/favorite. It leaves verification accounts and the cancelled order as an audit trail. It never prints passwords or bearer tokens. Optional `ADMIN_EMAIL` and `ADMIN_PASSWORD` environment variables enable administrator read checks; do not put credentials in shell history or commit them.

Browser integration, from the project root, using an existing Playwright installation:

```sh
PLAYWRIGHT_MODULE=/absolute/path/to/playwright/index.mjs node scripts/verify-browser.mjs
FRONTEND_URL=https://vueactivity-kaontime.vercel.app PLAYWRIGHT_MODULE=/absolute/path/to/playwright/index.mjs node scripts/verify-browser.mjs
```

Set `ADMIN_EMAIL` and `ADMIN_PASSWORD`, or use the locally generated ignored `backend/.vercel/admin-credentials.env` file. The browser script places test orders, cancels one and advances another through administrator statuses to Delivered. It restores its temporary menu-name edit. Test orders and accounts remain as an audit trail; no money is processed. Screenshots are written to `submission/screenshots/current/`.

## Recorded release evidence — 20 October 2026

- Backend suite: **13 tests / 94 assertions passed**; Composer manifest validation passed.
- Local HTTP integration: **35 checks passed**.
- Final hosted HTTP integration through the frontend proxy: **48 checks passed**, including administrator product CRUD and customer-versus-admin cancellation permissions.
- Hosted real-browser integration passed registration, favorites, address CRUD/default/checkout selection, COD order persistence, receipt, cancellation, token restoration/logout/re-login, administrator menu editing, order progression and reports.
- Frontend build, TypeScript and ESLint passed before deployment.
- Visual review accepted the captured desktop home, mobile home, customer account, administrator reports and receipt, with minor nonblocking contrast/decorative-label issues.
- Hosted health returned HTTP 200 with `database: connected`; routing header contained **`sin1::sin1`**, confirming Singapore ingress/function routing. The dedicated Neon project region is **`aws-ap-southeast-1`**.
- The exact historical pooler failure was not established. Direct/native connections passed the integration paths that failed with pooled/native connections; emulated prepares were not retained.
- Final clean `npm ci`, build, TypeScript and ESLint checks passed. Runtime-only npm audit: **zero vulnerabilities**. Full audit: **four unresolved development-toolchain advisories (one low, three high)** in the Quasar CLI tree. The available fix requires a broader Quasar CLI / Vue Router major upgrade; no forced or incompatible install was retained. Keep the development server private and plan that migration separately.

## Limitations

- Passing tests does not establish suitability for real commerce. The application does not process money or dispatch deliveries.
- Local SQLite evidence does not establish PostgreSQL/serverless behavior; hosted checks are required independently.
- Screenshots establish visible interface state, not database persistence or authorization.
- Historical screenshots may show an earlier version; use the final submission's current screenshot list.
- No release is considered verified while known build failures, cross-account data leaks, or failing hosted checkout remain.
