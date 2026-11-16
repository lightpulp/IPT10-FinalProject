<?php

declare(strict_types=1);

/*
 * Lightpull + BLF unit tests. No test framework is installed on purpose; this
 * file is a tiny runner. Run it with:
 *
 *     php tests/run.php        (or: composer test)
 *
 * The tests need no database and no network: HTTP calls go to a fake client.
 */

use App\Services\ExchangeRates;
use Lightpull\App;
use Lightpull\Cache;
use Lightpull\Database;
use Lightpull\HttpClient;
use Lightpull\HttpClientException;
use Lightpull\HttpException;
use Lightpull\Middleware;
use Lightpull\Middleware\LogRequests;
use Lightpull\Middleware\VerifyCsrfToken;
use Lightpull\Request;
use Lightpull\Response;
use Lightpull\Router;
use Lightpull\Session;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/BLF/pages.php';

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lightpull-tests-' . getmypid();
@mkdir($tmp, 0775, true);
ini_set('error_log', $tmp . DIRECTORY_SEPARATOR . 'php-errors.log');

// ---------------------------------------------------------------- tiny harness

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new AssertionError($message);
    }
}

function same(mixed $expected, mixed $actual, string $what): void
{
    check($expected === $actual, $what . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}

function throws(string $class, callable $fn): Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        check($e instanceof $class, 'expected ' . $class . ', got ' . $e::class . ': ' . $e->getMessage());
        return $e;
    }
    throw new AssertionError('expected ' . $class . ' to be thrown');
}

/** A fresh App with its own cache folder; $overrides are merged into the base config. */
function make_app(array $overrides = []): App
{
    global $tmp;
    return new App(array_replace_recursive([
        'app' => ['name' => 'Test', 'url' => 'http://localhost'],
        'cache' => ['dir' => $tmp . DIRECTORY_SEPARATOR . uniqid('cache-', true)],
        'money' => ['symbol' => '₱', 'null_text' => 'Price upon request'],
        'statuses' => ['new' => ['label' => 'New', 'color' => 'secondary']],
        'exchange_rates' => ['currencies' => ['USD', 'AED'], 'max_age' => 3600, 'stale_max_age' => 86400, 'retry_after' => 600],
    ], $overrides));
}

function request(string $method, string $path, array $post = []): Request
{
    return new Request($method, $path, '', [], $post, [], ['REMOTE_ADDR' => '127.0.0.1']);
}

// ---------------------------------------------------------------- test doubles

final class TraceA implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->setAttribute('trace', $request->attribute('trace', '') . 'A');
        return $next($request);
    }
}

final class TraceB implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->setAttribute('trace', $request->attribute('trace', '') . 'B');
        return $next($request);
    }
}

final class Block implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return new Response('blocked', 403);
    }
}

final class Engine
{
}

final class Car
{
    public function __construct(public readonly Engine $engine)
    {
    }
}

final class NeedsScalar
{
    public function __construct(public readonly int $speed)
    {
    }
}

/** Stands in for the real HttpClient: records URLs and returns a canned reply or throws. */
final class FakeHttpClient extends HttpClient
{
    public array $calls = [];

    public function __construct(public array|Throwable $reply)
    {
        parent::__construct(1);
    }

    public function getJson(string $url, array $headers = [], ?int $timeout = null): array
    {
        $this->calls[] = $url;
        if ($this->reply instanceof Throwable) {
            throw $this->reply;
        }
        return $this->reply;
    }
}

const OPEN_REPLY = [
    'result' => 'success',
    'time_last_update_unix' => 1794787351,
    'base_code' => 'PHP',
    'rates' => ['PHP' => 1, 'USD' => 0.017, 'AED' => 0.0625, 'EUR' => 0.0155],
];

// ---------------------------------------------------------------- tests

$tests = [
    'Router passes {params} to the handler' => function (): void {
        $app = make_app();
        $app->router->get('/hello/{name}', fn (string $name) => 'Hi ' . $name);
        same('Hi Ana', $app->router->dispatch(request('GET', '/hello/Ana'), $app->container)->body(), 'body');
    },

    'Router casts int params and 404s on non-digits' => function (): void {
        $app = make_app();
        $app->router->get('/items/{id}', fn (int $id) => (string) ($id * 2));
        same('42', $app->router->dispatch(request('GET', '/items/21'), $app->container)->body(), 'body');
        $e = throws(HttpException::class, fn () => $app->router->dispatch(request('GET', '/items/abc'), $app->container));
        same(404, $e->status(), 'status');
    },

    'Router 404s unknown paths and wrong methods' => function (): void {
        $app = make_app();
        $app->router->post('/form', fn () => 'ok');
        throws(HttpException::class, fn () => $app->router->dispatch(request('GET', '/nope'), $app->container));
        throws(HttpException::class, fn () => $app->router->dispatch(request('GET', '/form'), $app->container));
    },

    'Group middleware runs before route middleware' => function (): void {
        $app = make_app();
        $req = request('GET', '/traced');
        $app->router->group([TraceA::class], function (Router $router) use ($req): void {
            $router->get('/traced', fn () => (string) $req->attribute('trace'))->middleware(TraceB::class);
        });
        same('AB', $app->router->dispatch($req, $app->container)->body(), 'order');
    },

    'Middleware can stop a request before the handler' => function (): void {
        $app = make_app();
        $ran = false;
        $app->router->group([Block::class], function (Router $router) use (&$ran): void {
            $router->get('/secret', function () use (&$ran) {
                $ran = true;
                return 'secret';
            });
        });
        $response = $app->router->dispatch(request('GET', '/secret'), $app->container);
        same(403, $response->status(), 'status');
        same(false, $ran, 'handler ran');
    },

    'Container autowires constructor dependencies' => function (): void {
        $app = make_app();
        $car = $app->container->get(Car::class);
        check($car instanceof Car && $car->engine instanceof Engine, 'Car was not built with an Engine');
    },

    'Container singletons are shared, binds are not' => function (): void {
        $app = make_app();
        $app->container->singleton('one', fn () => new Engine());
        $app->container->bind('many', fn () => new Engine());
        check($app->container->get('one') === $app->container->get('one'), 'singleton returned two objects');
        check($app->container->get('many') !== $app->container->get('many'), 'bind returned the same object');
    },

    'Container explains an unresolvable scalar' => function (): void {
        $app = make_app();
        $e = throws(RuntimeException::class, fn () => $app->container->get(NeedsScalar::class));
        check(str_contains($e->getMessage(), '$speed'), 'message does not name the parameter');
    },

    'VerifyCsrfToken rejects a POST without the session token' => function (): void {
        $_SESSION = ['_csrf' => str_repeat('a', 64)];
        $middleware = new VerifyCsrfToken(new Session());
        $next = fn (Request $r) => new Response('ok');
        $e = throws(HttpException::class, fn () => $middleware->handle(request('POST', '/x', ['_token' => 'wrong']), $next));
        same(419, $e->status(), 'status');
        same('ok', $middleware->handle(request('POST', '/x', ['_token' => str_repeat('a', 64)]), $next)->body(), 'valid token');
        same('ok', $middleware->handle(request('GET', '/x'), $next)->body(), 'GET without token');
    },

    'LogRequests writes method, path and status' => function (): void {
        global $tmp;
        $file = $tmp . DIRECTORY_SEPARATOR . 'requests.log';
        $app = make_app(['log' => ['file' => $file]]);
        (new LogRequests($app))->handle(request('GET', '/catalog'), fn () => new Response('', 201));
        check(str_contains((string) file_get_contents($file), 'GET /catalog 201'), 'log line missing');
    },

    'e() escapes HTML' => function (): void {
        same('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'), 'escaped');
        same('', e(['array']), 'array');
    },

    'slugify, money and paginate' => function (): void {
        make_app();
        same('cabinets-storage', slugify('Cabinets & Storage'), 'slug');
        same('Price upon request', money(null), 'null price');
        same('₱1,500.00', money('1500'), 'price');
        same(['page' => 3, 'pages' => 3, 'offset' => 40, 'limit' => 20], paginate(45, 20, '3'), 'page 3');
        same(3, paginate(45, 20, '99')['page'], 'clamped page');
        same(1, paginate(0, 20, 'abc')['page'], 'bad page');
    },

    'UI components escape values and whitelist options' => function (): void {
        make_app();
        $html = input(['name' => 'q', 'value' => '"><script>x</script>']);
        check(!str_contains($html, '<script>'), 'input let a script tag through');
        same('#', ui_href('javascript:alert(1)'), 'javascript: URL');
        same('/catalog', ui_href('/catalog'), 'app URL');
        $badge = badge(['text' => 'x', 'color' => 'red" onclick="evil()']);
        check(str_contains($badge, 'text-bg-secondary') && !str_contains($badge, 'onclick'), 'badge colour was not whitelisted');
        same('New', status_label('new'), 'status label');
    },

    'Database only accepts [a-z_] identifiers' => function (): void {
        $db = (new ReflectionClass(Database::class))->newInstanceWithoutConstructor();
        $identifier = Closure::bind(fn (string $name) => $this->identifier($name), $db, Database::class);
        same('`quoted_amount`', $identifier('quoted_amount'), 'valid');
        throws(InvalidArgumentException::class, fn () => $identifier('name; DROP TABLE users'));
        same('%50\%\_off%', Database::like('50%_off'), 'LIKE escaping');
    },

    'Cache returns values until they expire' => function (): void {
        global $tmp;
        $cache = new Cache($tmp . DIRECTORY_SEPARATOR . 'cache-test');
        $cache->put('k', ['a' => 1]);
        same(['a' => 1], $cache->get('k', 60), 'fresh');
        same(null, $cache->get('k', -1), 'expired');
        $cache->forget('k');
        same(null, $cache->get('k'), 'forgotten');
    },

    'ExchangeRates reads the open endpoint and keeps configured currencies' => function (): void {
        $app = make_app();
        $http = new FakeHttpClient(OPEN_REPLY);
        $latest = (new ExchangeRates($http, $app->get(Cache::class), $app))->latest();
        same(['USD' => 0.017, 'AED' => 0.0625], $latest['rates'], 'rates');
        same(1794787351, $latest['updated_at'], 'updated_at');
        same(false, $latest['stale'], 'stale');
        same('https://open.er-api.com/v6/latest/PHP', $http->calls[0], 'URL');
    },

    'ExchangeRates uses the keyed endpoint when an API key is set' => function (): void {
        $app = make_app(['exchange_rates' => ['api_key' => 'TESTKEY']]);
        $reply = OPEN_REPLY;
        $reply['conversion_rates'] = $reply['rates'];
        unset($reply['rates']);
        $http = new FakeHttpClient($reply);
        $latest = (new ExchangeRates($http, $app->get(Cache::class), $app))->latest();
        same('https://v6.exchangerate-api.com/v6/TESTKEY/latest/PHP', $http->calls[0], 'URL');
        same(0.017, $latest['rates']['USD'], 'USD');
    },

    'ExchangeRates calls the API once, then serves from cache' => function (): void {
        $app = make_app();
        $http = new FakeHttpClient(OPEN_REPLY);
        $rates = new ExchangeRates($http, $app->get(Cache::class), $app);
        $rates->latest();
        $rates->latest();
        same(1, count($http->calls), 'API calls');
    },

    'ExchangeRates falls back to stale rates when the API fails' => function (): void {
        $app = make_app(['exchange_rates' => ['max_age' => -1]]); // every cached copy counts as old
        $http = new FakeHttpClient(OPEN_REPLY);
        $rates = new ExchangeRates($http, $app->get(Cache::class), $app);
        $rates->latest();
        $http->reply = new HttpClientException('open.er-api.com is rate limiting us (HTTP 429).', 429);
        $latest = $rates->latest();
        same(true, $latest['stale'], 'stale');
        same(0.017, $latest['rates']['USD'], 'USD');
    },

    'ExchangeRates returns null on an API error and backs off' => function (): void {
        $app = make_app();
        $http = new FakeHttpClient(['result' => 'error', 'error-type' => 'invalid-key']);
        $rates = new ExchangeRates($http, $app->get(Cache::class), $app);
        same(null, $rates->latest(), 'first call');
        same(null, $rates->latest(), 'second call');
        same(1, count($http->calls), 'API calls during back-off');
    },

    'foreign_prices converts pesos and hides itself without rates' => function (): void {
        make_app();
        same(['USD' => 17.0], ExchangeRates::convert(1000, ['USD' => 0.017]), 'convert');
        $html = foreign_prices('1000', ['rates' => ['USD' => 0.017], 'updated_at' => 1794787351, 'stale' => false], 'Abroad');
        check(str_contains($html, '≈ 17.00 USD') && str_contains($html, 'US dollar'), 'converted amount missing');
        same('', foreign_prices(null, ['rates' => ['USD' => 0.017], 'updated_at' => 0, 'stale' => false], 'Abroad'), 'no price');
        same('', foreign_prices('1000', null, 'Abroad'), 'no rates');
    },
];

// ---------------------------------------------------------------- runner

$failed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "  PASS  {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  FAIL  {$name}\n        {$e->getMessage()}\n";
    }
}

$passed = count($tests) - $failed;
echo "\n{$passed} passed, {$failed} failed\n";

$cleanup = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($cleanup as $file) {
    $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
}
@rmdir($tmp);

exit($failed === 0 ? 0 : 1);
