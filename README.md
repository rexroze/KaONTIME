# KaONTIME — Food Ordering & Kitchen Management

Continuation of Activity 1 for IT Elective 4: a food-ordering project migrated to a **Laravel API + Vue 3 + Quasar** application.

## Project layout

```text
Escartin_Jameboy_VueActivity/
├── backend/               Laravel API
├── VueActivity/           Vue 3 + Quasar CLI frontend (requested frontend name)
├── BiteBoxAxtivity/       Original Activity 1 frontend, preserved
├── legacy/                Original Express backend, local-only / ignored
└── submission/            Progress report and screenshots
```

The application and private GitHub repository are named **KaONTIME**. The frontend folder remains `VueActivity`, rather than `frontend`, as originally requested.

## Live application

- Storefront: https://vueactivity-kaontime.vercel.app
- Account and order history: https://vueactivity-kaontime.vercel.app/orders
- Administrator workspace: https://vueactivity-kaontime.vercel.app/admin
- Activity / API check: https://vueactivity-kaontime.vercel.app/activity
- Laravel API: https://vueactivity-kaontime-api.vercel.app/api/hello
- Private repository: https://github.com/rexroze/KaONTIME

The Laravel function runs in Vercel **Singapore (`sin1`)**. Its dedicated Neon PostgreSQL database is in **AWS Singapore (`aws-ap-southeast-1`)**. Production uses the Neon-provided **direct** connection URL: the pooled/native-prepare path produced transaction errors with the deployed community PHP runtime, while the direct/native configuration passed hosted integration checks. Do not switch back to a pooled endpoint without rechecking transactional flows. Connection pooling and load testing would need further work before a high-traffic commercial deployment.

## Features

- Searchable, filterable food menu, cart persistence, validated promos and cash-on-delivery order records.
- Registration, sign-in, sign-out and seven-day Sanctum bearer tokens. Browser tokens are held in session storage, not public source.
- Customer-private order history, receipts, status visibility and cancellation of pending orders.
- Database-backed favorites and saved delivery addresses with a default address.
- Administrator-only menu create/edit/remove, stock and availability management.
- Validated order progression and cancellation, preserving historical receipts when menu items are removed.
- Administrator reports with recorded order value, status counts, popular dishes and low-stock items.
- Server-calculated totals, transactional stock checks, idempotent checkout retries and exactly-once cancellation stock restoration.

Sign-in is required to place an order. Registering a customer never grants administrator permissions. API authorization is enforced in Laravel, not just by hiding navigation.

## Local setup

Requirements: PHP with SQLite support, Composer, and Node.js/npm. PostgreSQL is optional; this activity uses a local SQLite database by default, so no cloud account or MySQL service is needed.

Terminal 1:

```sh
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2:

```sh
cd VueActivity
npm ci
npm run dev
```

Use the frontend URL printed by Quasar. Requests to `/api` are proxied to Laravel on port 8000. The frontend and backend are separate running applications; opening the frontend alone does not start Laravel.

After the initial setup, you can also run **`npm run dev` from the project root** to start both applications together. Press Ctrl+C to stop them.

## Activity 2 demonstration

1. Open `http://127.0.0.1:8000/api/hello`.
2. Open the frontend's `/activity` page and click **Test Backend**.
3. Verify that the displayed message comes from the API: `Hello World`.
4. The greeting is `Hello World` at the project owner's request. The original Activity 2 rubric asks for a personalized name; if your instructor requires that exact format, set `API_HELLO_MESSAGE` accordingly in the backend `.env` and run `php artisan config:clear`.

The assignment's malformed proxy URL ending in `&#39;` is corrected to `http://127.0.0.1:8000`. Use `npm run dev`; `quasar run dev` is not the Quasar development command.

## Security and scope

- No `.env`, database files, dependencies, or legacy backend credentials belong in Git.
- The old Express backend is kept locally for reference and is **not** the required Laravel backend.
- Cash on delivery is a demonstration checkout; no money is processed and no real delivery service is connected.
- Authentication and administrator workflows are implemented. Real payment/delivery integrations, password recovery email, image uploads and commercial load testing remain outside this release.
- Existing cloud database credentials were not reused or rotated automatically. If they have been shared elsewhere, their owner should rotate them.
- Only Jameboy Escartin's name was found in the source. Additional group members must be supplied by the group; they are not invented here.

## Administrator access

An administrator account is provisioned explicitly; there is no predictable seeded password. On the development machine, the generated credentials are stored in **`backend/.vercel/admin-credentials.env`**, which is ignored by Git and excluded from deployment. Do not submit that file with coursework or share its contents publicly.

To create your own administrator interactively:

```sh
cd backend
php artisan app:create-admin admin@example.com --name="Your Name"
```

The command asks for a password without displaying it. To provision the existing generated local credentials against the configured production database, the owner can run `php scripts/provision-admin.php --production` from the project root. This helper runs locally only; it is not a public API endpoint.

## Verification and submission

See [VERIFICATION.md](VERIFICATION.md) for repeatable checks and their limitations, and `submission/` for the progress report and current screenshots. The source has no embedded database passwords or administrator credentials.
