<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

beforeEach(function (): void {
    config(['app.debug' => false, 'logging.default' => 'null']);
});

it('returns a generic 500 JSON body without exception details on an api route', function (): void {
    $secretMessage = 'db password is hunter'.'2';
    Route::middleware('api')->get('api/probe/boom', function () use ($secretMessage): never {
        throw new RuntimeException($secretMessage);
    });

    get('/api/probe/boom')
        ->assertStatus(500)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message'])
        ->assertJsonPath('message', 'Server Error')
        ->assertJsonMissingPath('exception')
        ->assertJsonMissingPath('trace')
        ->assertJsonMissingPath('file')
        ->assertJsonMissingPath('line')
        ->assertDontSee($secretMessage, false)
        ->assertDontSee('RuntimeException', false)
        ->assertDontSee(base_path(), false)
        ->assertDontSee(str_replace('\\', '\\\\', base_path()), false)
        ->assertDontSee(str_replace('\\', '/', base_path()), false);
});

it('returns a 500 HTML page without exception details on a web route', function (): void {
    $secretMessage = 'api token is hunter'.'2';
    Route::middleware('web')->get('probe/boom', function () use ($secretMessage): never {
        throw new RuntimeException($secretMessage);
    });

    get('/probe/boom')
        ->assertStatus(500)
        ->assertDontSee($secretMessage, false)
        ->assertDontSee('RuntimeException', false)
        ->assertDontSee(base_path(), false)
        ->assertDontSee(str_replace('\\', '/', base_path()), false);
});

it('returns 404 JSON for an unknown api route without an Accept header', function (): void {
    get('/api/does-not-exist')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message']);
});

it('returns 404 HTML for an unknown web route without an Accept header', function (): void {
    get('/does-not-exist')->assertNotFound()->assertHeader('Content-Type', 'text/html; charset=utf-8');
});
