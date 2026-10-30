<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\withSession;

beforeEach(function (): void {
    app()->instance('env', 'local');

    Route::middleware('web')->post('probe/csrf', fn (): Response => response('accepted'));
});

it('rejects a web post without a token with 419', function (): void {
    post('/probe/csrf', ['field' => 'value'])->assertStatus(419);
});

it('rejects a web post whose token does not match the session with 419', function (): void {
    withSession(['_token' => 'session-token'])
        ->post('/probe/csrf', ['_token' => 'another-token'])
        ->assertStatus(419);
});

it('accepts a web post carrying the session token', function (): void {
    withSession(['_token' => 'session-token'])
        ->post('/probe/csrf', ['_token' => 'session-token'])
        ->assertOk()
        ->assertSee('accepted');
});

it('rejects an authenticated admin panel logout post without a token with 419', function (): void {
    config(['app.env' => 'local']);
    actingAs(User::factory()->create());

    post('/admin/logout')->assertStatus(419);
});
