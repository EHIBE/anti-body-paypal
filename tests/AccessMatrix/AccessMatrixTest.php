<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Routing\Route;
use Tests\AccessMatrix\AccessMatrix;
use Tests\AccessMatrix\MatrixRow;
use Tests\AccessMatrix\ProtectedSurface;

use function Pest\Laravel\call;

function matrixActor(string $actor): ?User
{
    return $actor === AccessMatrix::OWNER ? User::factory()->create() : null;
}

function guestRequestUrl(Route $route): string
{
    $uri = '/'.ltrim($route->uri(), '/');

    foreach ($route->parameterNames() as $name) {
        if (! is_string($name)) {
            continue;
        }

        $uri = (string) preg_replace('/\{'.preg_quote($name, '/').'\??\}/', '1', $uri);
    }

    return $uri;
}

it('grants or denies every matrix row exactly as the matrix says for each active actor', function (): void {
    $checked = 0;

    foreach (AccessMatrix::rows() as $row) {
        foreach (AccessMatrix::ACTIVE_ACTORS as $actor) {
            resolve(AuthManager::class)->forgetGuards();

            expect(($row->probe)(matrixActor($actor)))->toBe($row->allows($actor), "{$row->key} for {$actor}");

            $checked++;
        }
    }

    expect($checked)->toBe(count(AccessMatrix::rows()) * count(AccessMatrix::ACTIVE_ACTORS));
});

it('never lets a guest through any matrix row', function (): void {
    $openToGuests = array_values(array_filter(
        AccessMatrix::rows(),
        static fn (MatrixRow $row): bool => $row->allows(AccessMatrix::GUEST),
    ));

    expect($openToGuests)->toBe([]);
});

it('answers a guest with 401 or the panel login redirect on every authenticated route', function (): void {
    $checked = [];

    foreach (ProtectedSurface::authenticatedRoutes() as $route) {
        $method = $route->methods()[0];
        $response = call($method, guestRequestUrl($route))->baseResponse;
        $key = ProtectedSurface::keyFor($route);

        if ($response->getStatusCode() === 401) {
            $checked[] = $key;

            continue;
        }

        expect($response->getStatusCode())->toBe(302, "{$method} {$route->uri()} must refuse a guest")
            ->and($response->headers->get('Location'))->toEndWith('/admin/login');

        $checked[] = $key;
    }

    expect($checked)->toContain('filament.admin.pages.dashboard');
});
