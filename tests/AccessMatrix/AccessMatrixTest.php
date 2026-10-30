<?php

declare(strict_types=1);

use App\Models\User;
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
    expect(AccessMatrix::mismatches(AccessMatrix::rows(), matrixActor(...)))->toBe([]);
});

it('probes every matrix row once for each active actor', function (): void {
    $probed = 0;

    AccessMatrix::mismatches(AccessMatrix::rows(), static function (string $actor) use (&$probed): ?User {
        $probed++;

        return matrixActor($actor);
    });

    expect($probed)->toBe(count(AccessMatrix::rows()) * count(AccessMatrix::ACTIVE_ACTORS));
});

it('reports a row whose probe grants a guest or denies the owner against the matrix', function (): void {
    $rows = [
        MatrixRow::ability('demo.open', [AccessMatrix::OWNER], static fn (?User $actor): bool => true),
        MatrixRow::ability('demo.locked', [AccessMatrix::OWNER], static fn (?User $actor): bool => false),
        MatrixRow::route('demo.correct', [AccessMatrix::OWNER], static fn (?User $actor): bool => $actor instanceof User),
    ];

    expect(AccessMatrix::mismatches($rows, matrixActor(...)))->toBe([
        'ability:demo.open for guest: expected deny, got allow',
        'ability:demo.locked for owner: expected allow, got deny',
    ]);
});

it('finds no mismatch when every probe agrees with the matrix', function (): void {
    $rows = [MatrixRow::route('demo.correct', [AccessMatrix::OWNER], static fn (?User $actor): bool => $actor instanceof User)];

    expect(AccessMatrix::mismatches($rows, matrixActor(...)))->toBe([]);
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
