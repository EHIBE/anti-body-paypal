<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('redirects a guest on the admin dashboard to the admin login page', function (): void {
    get('/admin')->assertRedirect('/admin/login');
});

it('lets a guest open the admin login page', function (): void {
    get('/admin/login')->assertOk();
});

it('redirects a guest to the login page from every parameterless admin panel page', function (): void {
    $checkedPages = 0;

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = $route->getName();

        if ($name === null || ! str_starts_with($name, 'filament.admin.') || str_contains($name, '.auth.')) {
            continue;
        }

        if (! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
            continue;
        }

        get('/'.$route->uri())->assertRedirect(route('filament.admin.auth.login'));
        $checkedPages++;
    }

    expect($checkedPages)->toBeGreaterThanOrEqual(1);
});

it('answers a guest on a protected api route with 401 JSON without an Accept header', function (): void {
    Route::middleware(['api', 'auth'])->get('api/probe/protected', fn (): JsonResponse => response()->json(['ok' => true]));

    get('/api/probe/protected')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('answers a guest form post to a protected api route with 401 JSON instead of a redirect', function (): void {
    Route::middleware(['api', 'auth'])->post('api/probe/protected', fn (): JsonResponse => response()->json(['ok' => true]));

    post('/api/probe/protected', ['field' => 'value'])
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('redirects a guest on a protected web route to the admin login page', function (): void {
    Route::middleware(['web', 'auth'])->get('probe/protected-page', fn (): Response => response('classified-content'));

    get('/probe/protected-page')
        ->assertRedirect('/admin/login')
        ->assertDontSee('classified-content');
});
