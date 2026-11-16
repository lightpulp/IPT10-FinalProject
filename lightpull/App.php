<?php

declare(strict_types=1);

namespace Lightpull;

use LogicException;
use Throwable;

final class App
{
    private const PUBLIC_MESSAGES = [
        403 => 'You do not have permission to view this page.',
        404 => 'The page you were looking for could not be found.',
        419 => 'Your session expired before the form was sent. Go back, refresh the page, and try again.',
        500 => 'Something went wrong on our side. Please try again in a moment.',
    ];

    private static ?self $instance = null;
    private static array $env = [];

    public readonly Container $container;
    public readonly Router $router;

    /** @var list<class-string<Middleware>> Global middleware, run on every request in this order. */
    private array $middleware;

    public function __construct(private readonly array $config)
    {
        self::$instance = $this;
        date_default_timezone_set((string) $this->config('timezone', 'UTC'));

        $this->container = new Container();
        $this->router = new Router();
        $this->middleware = array_values((array) $this->config('middleware', []));
        $this->container->singleton(self::class, fn () => $this);
        $this->container->singleton(Container::class, fn () => $this->container);
        $this->container->singleton(Request::class, fn () => Request::capture());
        $this->container->singleton(Session::class, fn () => new Session());
        $this->container->singleton(Database::class, fn () => new Database((array) $this->config('db', [])));
        $this->container->singleton(Cache::class, fn () => new Cache((string) $this->config('cache.dir', sys_get_temp_dir())));
        $this->container->singleton(HttpClient::class, fn () => new HttpClient((int) $this->config('http.timeout', 5)));
    }

    /** Adds global middleware after the ones listed in config('middleware'). */
    public function middleware(string ...$classes): void
    {
        array_push($this->middleware, ...$classes);
    }

    public static function instance(): self
    {
        return self::$instance ?? throw new LogicException('Lightpull\App has not been constructed yet.');
    }

    /** Reads KEY=value lines into $_ENV. Blank lines and # comments are skipped. */
    public static function loadEnv(string $file): void
    {
        if (!is_readable($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (preg_match('/^(["\'])(.*)\1$/', $value, $quoted)) {
                $value = $quoted[2];
            }
            self::$env[$key] = $_ENV[$key] = $value;
        }
    }

    public static function env(string $key, ?string $default = null): ?string
    {
        if (isset(self::$env[$key]) && self::$env[$key] !== '') {
            return self::$env[$key];
        }
        $value = getenv($key);
        return $value === false || $value === '' ? $default : $value;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $value = $this->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public function get(string $key): mixed
    {
        return $this->container->get($key);
    }

    /**
     * The URL prefix for links. Taken from the path of config('app.url') (APP_URL)
     * when set, otherwise detected from where index.php lives.
     */
    public function basePath(): string
    {
        $url = (string) $this->config('app.url', '');
        if ($url !== '') {
            return rtrim((string) parse_url($url, PHP_URL_PATH), '/');
        }
        return $this->get(Request::class)->basePath();
    }

    public function run(): void
    {
        try {
            $request = $this->get(Request::class);
            $this->get(Session::class)->start($request->isSecure());
            $response = (new Pipeline($this->container))->run(
                $this->middleware,
                $request,
                fn (Request $request) => $this->router->dispatch($request, $this->container),
            );
        } catch (HttpException $e) {
            $response = $e->status() >= 300 && $e->status() < 400
                ? new Response('', $e->status(), $e->headers())
                : $this->renderError($e->status(), $e);
        } catch (Throwable $e) {
            error_log((string) $e);
            $response = $this->renderError(500, $e);
        }
        $response->send();
    }

    private function renderError(int $status, Throwable $e): Response
    {
        $message = $e instanceof HttpException && $e->getMessage() !== ''
            ? $e->getMessage()
            : (self::PUBLIC_MESSAGES[$status] ?? self::PUBLIC_MESSAGES[500]);
        $detail = $this->config('debug') === true && !$e instanceof HttpException
            ? sprintf("%s: %s\n%s:%d\n\n%s", $e::class, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString())
            : null;

        try {
            $view = $this->config('error_view', 'error_page');
            $response = $view($status, $message, $detail);
            return $response instanceof Response ? $response : Response::html((string) $response, $status);
        } catch (Throwable $inner) {
            error_log((string) $inner);
            return Response::html('<h1>' . $status . '</h1><p>' . e($message) . '</p>', $status);
        }
    }
}
