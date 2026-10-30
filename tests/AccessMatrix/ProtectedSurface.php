<?php

declare(strict_types=1);

namespace Tests\AccessMatrix;

use App\Features\Shared\Authorization\RequiresAbility;
use FilesystemIterator;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

final class ProtectedSurface
{
    private const array AUTH_MIDDLEWARE_PREFIXES = ['auth', 'can:', 'abilities:', 'ability:'];

    private const array PANEL_ROUTE_PREFIXES = ['filament.', 'livewire.', 'default-livewire.'];

    /**
     * @return list<string>
     */
    public static function abilities(string $directory, string $namespace): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $abilities = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $class = self::classFor($directory, $namespace, $file);

            if ($class === null) {
                continue;
            }

            foreach (new ReflectionClass($class)->getAttributes(RequiresAbility::class) as $attribute) {
                $abilities[] = $attribute->newInstance()->ability;
            }
        }

        sort($abilities);

        return array_values(array_unique($abilities));
    }

    /**
     * @return list<Route>
     */
    public static function authenticatedRoutes(): array
    {
        return array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            self::requiresAuthentication(...),
        ));
    }

    /**
     * @return list<string>
     */
    public static function authenticatedRouteKeys(): array
    {
        $keys = [];

        foreach (self::authenticatedRoutes() as $route) {
            $key = self::keyFor($route);

            if (self::isPanelRoute($key) || in_array($key, AccessMatrix::openRoutes(), true)) {
                continue;
            }

            $keys[] = $key;
        }

        sort($keys);

        return array_values(array_unique($keys));
    }

    /**
     * @param  list<string>  $required
     * @param  list<string>  $covered
     * @return list<string>
     */
    public static function missing(array $required, array $covered): array
    {
        return array_values(array_diff($required, $covered));
    }

    public static function keyFor(Route $route): string
    {
        return $route->getName() ?? 'unnamed: '.$route->methods()[0].' '.$route->uri();
    }

    private static function requiresAuthentication(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if (is_a($middleware, Authenticate::class, true)) {
                return true;
            }

            foreach (self::AUTH_MIDDLEWARE_PREFIXES as $prefix) {
                if (str_starts_with($middleware, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function isPanelRoute(string $key): bool
    {
        return array_any(self::PANEL_ROUTE_PREFIXES, fn (string $prefix): bool => str_starts_with($key, $prefix));
    }

    /**
     * @return class-string|null
     */
    private static function classFor(string $directory, string $namespace, SplFileInfo $file): ?string
    {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));

        if (str_contains('/'.$relative, '/Tests/') || preg_match('/^[A-Z][A-Za-z0-9]*\.php$/', basename($relative)) !== 1) {
            return null;
        }

        $class = $namespace.'\\'.str_replace('/', '\\', substr($relative, 0, -4));

        return class_exists($class) ? $class : null;
    }
}
