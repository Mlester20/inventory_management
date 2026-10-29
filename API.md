# SAIMS read-only API

For other systems that need to read SAIMS data. It only reads: every route is a GET, so nothing here can change data.

## Get a token

Run this on the server, once for each system that will connect:

```
php artisan api:token "Inertia app"
```

It prints the token one time. Copy it into that system's `.env` (never into frontend code). Each name gets its own
non-login account ("API: Inertia app"), so tokens never act as a real user.

To cut a system off: `php artisan api:token "Inertia app" --revoke`.

## Calling it

Send the token on every request:

```
Authorization: Bearer <token>
Accept: application/json
```

Base URL: `https://<your-saims-domain>/api/v1`. Limit: 60 calls a minute per token.

| Route | What it returns | Filters |
| --- | --- | --- |
| `GET /customers`, `/customers/{id}` | customers | `search` |
| `GET /products`, `/products/{id}` | items with prices and stock per location (cost price is left out) | `search` (name, code, barcode) |
| `GET /sales-orders`, `/sales-orders/{id}` | orders with their lines (qty, delivered, remaining) | `customer_id`, `status`, `from`, `to` |
| `GET /delivery-receipts`, `/delivery-receipts/{id}` | receipts with their lines | `customer_id`, `status`, `from`, `to` |
| `GET /invoices`, `/invoices/{id}` | invoices with their lines and totals | `customer_id`, `from`, `to` |

Every list also takes `per_page` (default 25, most 100), `page`, and `updated_since=YYYY-MM-DD` (only what changed on or
after that date, handy for keeping another system in step). Drafts and archived records are never returned.

## From a Laravel app (e.g. an Inertia backend)

```php
// config/services.php:  'saims' => ['url' => env('SAIMS_URL'), 'token' => env('SAIMS_TOKEN')],
$customers = Http::withToken(config('services.saims.token'))
    ->baseUrl(config('services.saims.url') . '/api/v1')
    ->acceptJson()
    ->get('customers', ['search' => 'clinic', 'per_page' => 50])
    ->throw()
    ->json('data');
```

Call it from the backend and pass the result to the page as props, so the token never reaches the browser.

## Deploying it

This adds a package and a table, so unlike most updates:

```
git pull origin main
composer install --no-dev
php artisan migrate
php artisan optimize:clear
```
