<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\AccessMatrix\AccessMatrix;
use Tests\AccessMatrix\MatrixRow;
use Tests\AccessMatrix\ProtectedSurface;

it('lists every ability required by a RequiresAbility class in the access matrix', function (): void {
    $required = [
        ...ProtectedSurface::abilities(base_path('app/Features'), 'App\\Features'),
        ...ProtectedSurface::abilities(base_path('app/Filament'), 'App\\Filament'),
    ];

    expect(ProtectedSurface::missing($required, AccessMatrix::abilities()))->toBe([]);
});

it('treats a missing Filament directory as having no required abilities', function (): void {
    expect(ProtectedSurface::abilities(base_path('app/Filament/Missing'), 'App\\Filament\\Missing'))->toBe([]);
});

it('answers every registered route as authenticated, panel infrastructure, or explicitly open', function (): void {
    expect(ProtectedSurface::unprotectedRouteKeys())->toBe([]);
});

it('reports a planted route that has no authentication and is not listed as open', function (): void {
    Route::middleware('web')->get('probe/exposed', fn (): string => 'ok')->name('probe.exposed');
    Route::get('probe/unnamed-exposed', fn (): string => 'ok');

    expect(ProtectedSurface::unprotectedRouteKeys())->toBe(['probe.exposed', 'unnamed: GET probe/unnamed-exposed']);
});

it('does not report an authenticated planted route as exposed', function (): void {
    Route::middleware(['web', 'auth'])->get('probe/guarded', fn (): string => 'ok')->name('probe.guarded');

    expect(ProtectedSurface::unprotectedRouteKeys())->not->toContain('probe.guarded');
});

it('lists no open route that also requires authentication', function (): void {
    $authenticated = array_map(ProtectedSurface::keyFor(...), ProtectedSurface::authenticatedRoutes());

    expect(array_values(array_intersect(AccessMatrix::openRoutes(), $authenticated)))->toBe([]);
});

it('finds an authenticated route that reaches auth only through a custom middleware group', function (): void {
    Route::middlewareGroup('probe-group', ['web', 'auth']);
    Route::middleware('probe-group')->get('probe/grouped', fn (): string => 'ok')->name('probe.grouped');

    expect(ProtectedSurface::authenticatedRouteKeys())->toContain('probe.grouped');
});

it('finds an authenticated route whose middleware carries parameters', function (): void {
    Route::middleware(['web', 'auth:sanctum'])->get('probe/guard-parameter', fn (): string => 'ok')->name('probe.guard-parameter');
    Route::middleware(['web', 'can:update,post'])->get('probe/can-parameter', fn (): string => 'ok')->name('probe.can-parameter');

    expect(ProtectedSurface::authenticatedRouteKeys())->toContain('probe.guard-parameter', 'probe.can-parameter');
});

it('does not treat the Livewire hashed asset routes as exposed', function (): void {
    $exposed = array_filter(ProtectedSurface::unprotectedRouteKeys(), static fn (string $key): bool => str_contains($key, 'livewire'));

    expect(array_values($exposed))->toBe([]);
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

it('flags an agent grant of every forbidden ability stem', function (string $ability): void {
    $rows = [MatrixRow::ability($ability, [AccessMatrix::AGENT], static fn (?User $actor): bool => true)];

    expect(AccessMatrix::forbiddenAgentGrants($rows))->toBe([$ability]);
})->with([
    'approve' => ['invoice.approve'],
    'approval' => ['invoice.approval.grant'],
    'reject' => ['invoice.reject'],
    'rejection' => ['invoice.rejection'],
    'override' => ['invoice.override'],
    'overridden' => ['guard.overridden'],
    'payee' => ['payment.payee.change'],
    'amount' => ['invoice.amount.set'],
    'vendor create' => ['vendor.create'],
    'vendor update' => ['vendor.update'],
    'vendor delete' => ['vendor.delete'],
    'vendor edit' => ['vendor.edit'],
    'uppercase' => ['Invoice.APPROVE'],
]);

it('lets an agent hold read and propose abilities and a vendor read', function (string $ability): void {
    $rows = [MatrixRow::ability($ability, [AccessMatrix::AGENT], static fn (?User $actor): bool => true)];

    expect(AccessMatrix::forbiddenAgentGrants($rows))->toBe([]);
})->with([
    'propose' => ['invoice.propose'],
    'status' => ['invoice.status'],
    'vendor list' => ['vendor.list'],
    'vendor view' => ['vendor.view'],
]);

it('does not flag a forbidden ability that no agent holds', function (): void {
    $rows = [MatrixRow::ability('invoice.approve', [AccessMatrix::OWNER], static fn (?User $actor): bool => $actor instanceof User)];

    expect(AccessMatrix::forbiddenAgentGrants($rows))->toBe([]);
});
