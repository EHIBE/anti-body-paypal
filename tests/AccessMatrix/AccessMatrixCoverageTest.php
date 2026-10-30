<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\AccessMatrix\AccessMatrix;
use Tests\AccessMatrix\MatrixRow;
use Tests\AccessMatrix\ProtectedSurface;

it('lists every ability required by a RequiresAbility class in the access matrix', function (): void {
    $required = ProtectedSurface::abilities(base_path('app/Features'), 'App\\Features');

    expect(ProtectedSurface::missing($required, AccessMatrix::abilities()))->toBe([]);
});

it('lists every authenticated route outside the panel in the access matrix', function (): void {
    expect(ProtectedSurface::missing(ProtectedSurface::authenticatedRouteKeys(), AccessMatrix::routes()))->toBe([]);
});

it('allows only the owner, agent and guest actors', function (): void {
    expect(AccessMatrix::unknownActors(AccessMatrix::rows()))->toBe([]);
});

it('keeps every matrix key unique', function (): void {
    $keys = array_map(static fn (MatrixRow $row): string => $row->kind.':'.$row->key, AccessMatrix::rows());

    expect($keys)->toBe(array_values(array_unique($keys)));
});

it('never grants an agent approve, reject, override, vendor edit, amount or payee', function (): void {
    expect(AccessMatrix::forbiddenAgentGrants(AccessMatrix::rows()))->toBe([]);
});

it('finds a planted RequiresAbility class that is missing from the matrix', function (): void {
    $found = ProtectedSurface::abilities(base_path('tests/Fixtures/AccessMatrix'), 'Tests\\Fixtures\\AccessMatrix');

    expect($found)->toBe(['fixture.approve'])
        ->and(ProtectedSurface::missing($found, AccessMatrix::abilities()))->toBe(['fixture.approve']);
});

it('finds a named and an unnamed authenticated route that are missing from the matrix', function (): void {
    Route::middleware(['api', 'auth'])->get('api/probe/protected', fn (): string => 'ok')->name('probe.protected');
    Route::middleware(['web', 'auth'])->post('probe/unnamed', fn (): string => 'ok');

    $missing = ProtectedSurface::missing(ProtectedSurface::authenticatedRouteKeys(), AccessMatrix::routes());

    expect($missing)->toContain('probe.protected', 'unnamed: POST probe/unnamed');
});

it('leaves panel routes and public routes to their own tests', function (): void {
    Route::middleware('api')->get('api/probe/public', fn (): string => 'ok')->name('probe.public');

    $keys = ProtectedSurface::authenticatedRouteKeys();

    expect(array_values(array_filter($keys, static fn (string $key): bool => str_starts_with($key, 'filament.'))))->toBe([])
        ->and($keys)->not->toContain('probe.public');
});

it('reports an unknown actor and an agent granted a forbidden ability', function (): void {
    $rows = [
        MatrixRow::ability('invoice.approve', [AccessMatrix::OWNER, AccessMatrix::AGENT], static fn (?User $actor): bool => $actor instanceof User),
        MatrixRow::ability('invoice.propose', [AccessMatrix::AGENT, 'auditor'], static fn (?User $actor): bool => $actor instanceof User),
    ];

    expect(AccessMatrix::forbiddenAgentGrants($rows))->toBe(['invoice.approve'])
        ->and(AccessMatrix::unknownActors($rows))->toBe(['auditor']);
});
