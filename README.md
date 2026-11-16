# Lightpull + BLF

This repository holds two things:

- **`lightpull/`** is a small PHP framework. It has a front controller, a router with route groups, a middleware pipeline, request and response objects, a DI container, a PDO wrapper, sessions, an HTTP client and file cache for external APIs, and a library of Bootstrap UI functions. It knows nothing about furniture.
- **`BLF/`** is the Betis Legacy Furniture CMS, built on Lightpull. Customers browse the catalog, request quotes, and track them. Staff manage products and categories and move each inquiry through to a finished order. Prices and quotes are also shown in six foreign currencies, using live rates from ExchangeRate-API.
- **`tests/`** holds a dependency-free unit test runner.

## Requirements

- PHP 8.1 or newer, with the `pdo_mysql`, `fileinfo` and `mbstring` extensions
- MySQL 8 or MariaDB 10.4+
- Composer, used only for autoloading. No packages are installed.
- Apache with `mod_rewrite`, or PHP's built-in server

## Install

```bash
composer install                       # generates vendor/autoload.php
mysql -u root -p < BLF/database/schema.sql
mysql -u root -p < BLF/database/seed.sql
cp .env.example .env                   # then fill in your database settings
```

`schema.sql` creates the `blf` database itself. Example `.env` for a local XAMPP install:

```ini
APP_DEBUG=true
APP_URL=
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=blf
DB_USER=root
DB_PASS=
EXCHANGE_RATE_API_KEY=
```

Set `APP_DEBUG=false` anywhere real people can reach the site. With debug off, visitors only see a styled error page. The details go to PHP's error log.

`APP_URL` is the site's base URL. Leave it blank to detect it from where `index.php` lives. `EXCHANGE_RATE_API_KEY` is optional. Leave it blank to use ExchangeRate-API's free open-access endpoint.

`BLF/public/uploads/` and `BLF/storage/` must be writable by the web server. The storage folder holds the request log and cached API responses. The PHP `curl` extension is needed for the API call.

## Tests

```bash
php tests/run.php        # or: composer test
```

The tests cover the router, route groups and middleware order, the container, CSRF and logging middleware, helpers, UI escaping, SQL identifier checks, the cache, and the exchange-rate service. They need no database and no network, because API calls go to a fake HTTP client.

## Run

With PHP's built-in server:

```bash
php -S localhost:8000 -t BLF/public
```

Then open <http://localhost:8000>.

With Apache, point the virtual host's document root at `BLF/public/`. `BLF/public/.htaccess` sends every request that is not a real file to `index.php`. A sub-folder install also works, for example `http://localhost/IPT10/BLF/public/`, because every link and redirect is built with the base path. The `.htaccess` at the project root denies access to everything outside `BLF/public`, so `.env` and `vendor/` stay private.

## Seeded accounts

| Email | Password | Role |
| --- | --- | --- |
| `admin@betislegacy.test` | `password123` | admin |
| `staff@betislegacy.test` | `password123` | staff |

**Change both passwords, or delete these accounts, before any real deployment.**

Staff sign in at `/admin/login`. The public navbar has no admin link on purpose.

The seed adds 6 categories, 18 products, and 8 inquiries. The inquiries are `BLF-2026-0001` to `BLF-2026-0008`, one in each status. Try tracking `BLF-2026-0003` with phone `0927 555 0303`.

Products are seeded without photos. The site draws a placeholder with the product name until staff upload real photos from **Admin → Products → Edit**.

## How Lightpull works

### Request flow

```
BLF/public/index.php
  → loads .env, pages.php, admin_pages.php, config.php
  → new App($config)          registers Request, Session, Database, Cache and HttpClient (all lazy) in the Container
  → routes.php                $app->router->get('/path/{param}', [Controller::class, 'method'])
  → App::run()
      Session::start()        httponly, SameSite=Lax, strict mode, secure on HTTPS
      global middleware       config['middleware']: LogRequests, then VerifyCsrfToken (POST without token = 419)
      Router::dispatch()      matches the path
        route middleware      e.g. Authenticate and RequireAdmin on /admin routes
        controller method     built through the Container, returns a Response (or a string, wrapped as HTML)
  → Response::send()
```

### Middleware

A middleware class implements `Lightpull\Middleware` and has one method, `handle(Request $request, Closure $next): Response`. It can stop the request, change it, or call `$next($request)` and inspect the response on the way out. `Pipeline` wraps the layers around the destination, and the first class listed is the outermost.

| Middleware | Where | Job |
| --- | --- | --- |
| `Lightpull\Middleware\LogRequests` | global | Logging: one line per request in `BLF/storage/logs/requests.log` with method, path, status, time and IP |
| `Lightpull\Middleware\VerifyCsrfToken` | global | Request filtering: rejects any POST without the session's CSRF token |
| `App\Middleware\Authenticate` | `/admin` group | Authentication: re-reads the signed-in user from the database, or redirects to the login page |
| `App\Middleware\RequireAdmin` | nested `/admin/users` group | Authorization: 403 unless the user's role is `admin` |

Route middleware is attached with groups or per route:

```php
$router->group([Authenticate::class], function (Router $router): void {
    $router->get('/admin', [DashboardController::class, 'index']);
    $router->group([RequireAdmin::class], function (Router $router): void {
        $router->get('/admin/users', [UserController::class, 'index']);
    });
});
$router->get('/report', [ReportController::class, 'show'])->middleware(RequireAdmin::class);
```

Errors are caught in `App::run()`. A `HttpException` renders that status's page, and a 3xx `HttpException` becomes a redirect. Any other exception is logged and renders a 500. The page builder is named by `config['error_view']`.

### Pieces

| File | Job |
| --- | --- |
| `App.php` | Config with dot access, the `.env` reader, error handling, and `App::instance()` for helper functions |
| `Router.php`, `Route.php` | `get()`, `post()` and `group()`. `{name}` segments become arguments, and a parameter typed `int` must be digits or the route 404s |
| `Middleware.php`, `Pipeline.php`, `Middleware/` | The middleware contract, the onion pipeline, and the built-in logging and CSRF middleware |
| `HttpClient.php` | Outgoing JSON GET requests over cURL with timeouts. Every failure becomes one `HttpClientException` |
| `Cache.php` | A file cache with a maximum age, used to keep API responses |
| `Container.php` | `bind()`, `singleton()`, `get()`, and constructor autowiring through reflection |
| `Request.php` | The only reader of `$_GET`, `$_POST`, `$_FILES` and `$_SERVER` |
| `Response.php` | Body, status and headers, plus `html()`, `json()` and `redirect()` |
| `Database.php` | Prepared statements only. `insert()` and `update()` accept column names matching `[a-z_]+` and nothing else |
| `Session.php` | The only toucher of `$_SESSION`. Handles flash messages, old input, the CSRF token, and login state |
| `helpers.php` | `e()`, `url()`, `redirect()`, `money()`, `status_label()`, `paginate()` and similar |
| `ui.php`, `ui_admin.php` | UI components |

### The UI layer

There are no template files. Each component is a function that takes an options array and returns escaped Bootstrap HTML:

```php
page('Home', [
    site_navbar('Home'),
    hero(['title' => 'Solid wood furniture', 'button' => ['Browse', '/catalog']]),
    container([
        section_title(['title' => 'Featured pieces']),
        product_grid($products),
    ]),
    site_footer(),
]);
```

Components escape every value themselves. Options such as `color` and `size` are looked up in fixed maps and never pasted into class names. URLs pass through `ui_href()`, which adds the base path to app paths and turns anything like `javascript:` into `#`. `raw()` is the only way to output unescaped HTML, and it must never receive user input.

## Adding a page in three steps

Here is how to add a "Care guide" page at `/care`.

**1. Route** in `BLF/routes.php`:

```php
$router->get('/care', [PageController::class, 'care']);
```

**2. Controller method** in `BLF/Controllers/PageController.php`:

```php
public function care(): Response
{
    return page_care(['woods' => ['Narra', 'Mahogany', 'Acacia']]);
}
```

**3. Page builder** in `BLF/pages.php`:

```php
function page_care(array $d): Response
{
    return site_page('Caring for solid wood', '', [
        section_title(['title' => 'Caring for solid wood', 'size' => 'lg']),
        text_block(['text' => 'Dust with a dry cloth. Keep pieces out of direct sun.']),
        definition_list(['items' => array_fill_keys($d['woods'], 'Re-oil once a year.')]),
        button(['label' => 'Ask us a question', 'url' => '/inquire']),
    ]);
}
```

If the controller needs the database, add `private readonly Database $db` to its constructor. The container injects it.

## Inquiry lifecycle

`new → contacted → quoted → confirmed → in_production → ready → completed`, and `cancelled` can be set from any status. Staff may jump between any two statuses. Saving `quoted` without an amount still saves, with a warning. The list lives once, in `config['statuses']` in `BLF/config.php`.

Reference numbers look like `BLF-2026-0001`. They are generated in a transaction from the count of the year's inquiries. If two requests collide on the unique index, the insert retries once.

## External API: ExchangeRate-API

Customers abroad, for example Filipinos working overseas who buy furniture for family at home, see each product's price and their quoted amount in USD, AED, SAR, SGD, CAD and AUD. The conversion is approximate. Quotes and payments stay in pesos.

| | |
| --- | --- |
| Service | [ExchangeRate-API](https://www.exchangerate-api.com) |
| Endpoint without a key | `GET https://open.er-api.com/v6/latest/PHP` |
| Endpoint with a key | `GET https://v6.exchangerate-api.com/v6/{EXCHANGE_RATE_API_KEY}/latest/PHP` |
| Format | JSON: `result`, `time_last_update_unix`, and `rates` (open) or `conversion_rates` (keyed) |
| Code | `BLF/Services/ExchangeRates.php`, using `Lightpull\HttpClient` and `Lightpull\Cache` |

Rates change once a day, so they are cached in `BLF/storage/cache` for 12 hours. The API is called at most about twice a day, well inside the free plan's limits. If a call fails because of a timeout, an HTTP error, an HTTP 429 rate limit, a bad key, or bad JSON, these things happen:

- The page still loads.
- The last saved rates are shown for up to a week, with a note that they could not be refreshed.
- With no saved rates, the section is hidden.
- The API is not called again for 10 minutes.

## Security summary

- Every SQL value is bound, including `LIMIT` and `OFFSET`. Identifiers passed to `insert()` and `update()` are validated.
- Every rendered value goes through `e()`, inside the components.
- Every POST is checked for a CSRF token before routing, including the public forms.
- Passwords use `password_hash()` and `password_verify()`. A failed login shows one generic message and takes the same time whether or not the email exists. Logging in regenerates the session ID.
- Every `/admin` route sits behind the `Authenticate` middleware. It re-reads the user from the database on every request and redirects to the login page if the user is gone. The `/admin/users` routes add `RequireAdmin`, which returns 403 to anyone who is not an admin. `AdminController` also refuses to run without a user, in case a route is ever registered outside the group.
- The exchange-rate API key, when used, lives only in `.env`. Error messages and logs name the API host, never the full URL that contains the key.
- Uploads must be JPG, PNG, or WebP, detected from the file bytes. They are limited to 2 MB and renamed by the server. PHP execution is disabled in `uploads/`.
- The tracking page needs both the reference and the phone number, compared as digits only. It never selects internal notes.
- The inquiry form has a honeypot field.

## Known limitations

- **No rate limiting.** The honeypot stops simple bots only. Login and tracking attempts are not throttled.
- **The first product view after the cache expires waits for the API.** This takes about a second, or up to the 4-second timeout if the service is slow. Every later view uses the cache.
- **Reference numbers use a yearly count.** They are only correct as long as inquiries are never deleted. The admin has no inquiry delete, so this holds.
- **Contact details are placeholders.** The workshop addresses and phone numbers in `page_contact()` and the About text need to be replaced with real ones.
- **Large uploads.** If an upload is bigger than PHP's `post_max_size`, PHP drops the whole form. The CSRF token goes with it, so the user sees "Session expired" instead of a size error.
- **`ui.php` is longer than the 400-line guideline.** The admin components are already split into `ui_admin.php`. The rest are the public and form components that pages need.
- **Sub-folder Apache installs are untested.** They are supported by the base-path logic, but only `php -S` with `BLF/public` as the document root was tested end to end.
