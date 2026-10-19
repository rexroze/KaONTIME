# Jameboy Escartin API (Laravel)

Laravel API backend using a local SQLite database by default. The original Express/Prisma source is archived in `../legacy/express-backend`; never publish or commit that archive because it contains the original secret-bearing `.env`.

## Setup and run

Requires PHP 8.2+ (8.3+ for the current Laravel skeleton) with PDO SQLite, and Composer.

```sh
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
```

Set `API_HELLO_MESSAGE` in `.env` to configure `GET /api/hello`; the default message is **“Hello World”**, as requested by the project owner. `ORDER_DELIVERY_FEE` defaults to 50. The default DB is the app's own `database/database.sqlite`; no cloud database is contacted. Optional PostgreSQL/Neon is supported by setting `DB_CONNECTION=pgsql` and `DB_URL` to the PostgreSQL connection URL. The app does not migrate or connect to the archived backend's database unless explicitly configured to do so.

## API

- `GET /api/hello` -> `{ "message": "..." }`
- `GET /api/health` -> database connectivity only (no credentials or exception details)
- `GET /api/menu?category=Burgers&search=angus&sort=price-asc` -> `{success,data}`; sort may be `rating`, `price-asc`, `price-desc`, or `popular`.
- `POST /api/promo/validate` JSON `{ "code": "WELCOME10", "subtotal": 250 }` -> `{success,data:{code,discountPercent}}`.
- `POST /api/orders` JSON `{ "customerName":"...", "customerPhone":"...", "deliveryAddress":"...", "paymentMethod":"COD", "orderNotes":"...", "items":[{"productId":1,"quantity":2}], "promoCode":"KAONTIME" }`. Prices, discount and delivery fee are computed by the server. Stock checks/decrements and order writes are transactional. Totals supplied by clients are ignored. Only COD is accepted.

There are no public order-list/detail routes or unauthenticated menu/admin write routes.

## Tests

```sh
php artisan test --testsuite=Feature
```

Database seed upserts the original 12 menu products and 3 promotions idempotently. Menu image values (e.g. `images/burger.jpg`) match `BiteBoxAxtivity/images`.
