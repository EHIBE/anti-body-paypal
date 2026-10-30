<?php

declare(strict_types=1);

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\call;
use function Pest\Laravel\get;

it('sends the baseline security headers on a page response', function (): void {
    get('/')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

it('does not send Strict-Transport-Security over plain http', function (): void {
    get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
});

it('sends Strict-Transport-Security with the configured max age over https', function (): void {
    config(['antibody.security.hsts_max_age' => 31536000]);

    get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('falls back to the default Strict-Transport-Security max age when the configured value is invalid', function (mixed $invalidMaxAge): void {
    config(['antibody.security.hsts_max_age' => $invalidMaxAge]);

    get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=15552000; includeSubDomains');
})->with([
    'negative' => [-10],
    'string' => ['forever'],
    'null' => [null],
]);

it('sends Strict-Transport-Security with max-age zero when the configured max age is zero', function (): void {
    config(['antibody.security.hsts_max_age' => 0]);

    get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=0; includeSubDomains');
});

it('sends a page policy without unsafe-eval and with frame-ancestors none on the home page', function (): void {
    $policy = (string) get('/')->headers->get('Content-Security-Policy-Report-Only');

    expect($policy)->toContain("script-src 'self'")->toContain("frame-ancestors 'none'");
    expect($policy)->not->toContain('unsafe-eval');
    expect($policy)->not->toContain("script-src 'self' 'unsafe-inline'");
});

it('sends a panel policy with unsafe-eval on the admin login page', function (): void {
    $policy = (string) get('/admin/login')->headers->get('Content-Security-Policy-Report-Only');

    expect($policy)
        ->toContain("script-src 'self' 'unsafe-inline' 'unsafe-eval'")
        ->toContain("frame-ancestors 'none'");
});

it('sends the panel policy on a livewire path with the hashed prefix', function (string $prefix): void {
    Route::middleware('web')->get("{$prefix}/ping", fn (): Response => response('ok'));

    $policy = (string) get("/{$prefix}/ping")->assertOk()->headers->get('Content-Security-Policy-Report-Only');

    expect($policy)->toContain("'unsafe-eval'");
})->with([
    'eight hex characters' => ['livewire-0a1b2c3d'],
    'one hex character' => ['livewire-f'],
    'long hex string' => ['livewire-0123456789abcdef'],
]);

it('sends the page policy on a path that only looks like the livewire prefix', function (string $prefix): void {
    Route::middleware('web')->get("{$prefix}/page", fn (): Response => response('ok'));

    $policy = (string) get("/{$prefix}/page")->assertOk()->headers->get('Content-Security-Policy-Report-Only');

    expect($policy)->toContain("script-src 'self'")
        ->and($policy)->not->toContain('unsafe-eval')
        ->and($policy)->not->toContain("script-src 'self' 'unsafe-inline'");
})->with([
    'word suffix' => ['livewire-demo'],
    'uppercase hex' => ['livewire-0A1B2C3D'],
    'bare livewire segment' => ['livewire'],
    'hex then letters' => ['livewire-0a1bxyz'],
    'empty suffix' => ['livewire-'],
    'prefix inside a longer segment' => ['xlivewire-0a1b2c3d'],
]);

it('sends the panel policy on a filament path', function (): void {
    Route::middleware('web')->get('filament/probe', fn (): Response => response('ok'));

    expect((string) get('/filament/probe')->assertOk()->headers->get('Content-Security-Policy-Report-Only'))->toContain("'unsafe-eval'");
});

it('sends the page policy on a path that merely starts with admin', function (): void {
    Route::middleware('web')->get('administrator/page', fn (): Response => response('ok'));

    expect((string) get('/administrator/page')->assertOk()->headers->get('Content-Security-Policy-Report-Only'))->not->toContain('unsafe-eval');
});

it('sends the security headers on a response that a global middleware rejects before routing', function (): void {
    $response = call('POST', '/', server: ['CONTENT_LENGTH' => '999999999999']);

    $response->assertStatus(413)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');

    expect($response->headers->has('Content-Security-Policy-Report-Only'))->toBeTrue();
});

it('sends the security headers on a not found response', function (): void {
    get('/no-such-page')->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('sends the machine policy on an api path', function (): void {
    Route::middleware('api')->get('api/probe/headers', fn (): Response => response('ok'));

    $policy = (string) get('/api/probe/headers')->assertOk()->headers->get('Content-Security-Policy-Report-Only');

    expect($policy)->toContain("default-src 'none'")->toContain("frame-ancestors 'none'");
    expect($policy)->not->toContain('unsafe-eval');
});

it('sends the machine policy on a webhooks path', function (): void {
    Route::middleware('web')->get('webhooks/probe', fn (): Response => response('ok'));

    $policy = (string) get('/webhooks/probe')->assertOk()->headers->get('Content-Security-Policy-Report-Only');

    expect($policy)->toContain("default-src 'none'");
    expect($policy)->not->toContain('unsafe-eval');
});

it('sends the report-only policy header and not the enforced header by default', function (): void {
    config(['antibody.security.csp_report_only' => true]);

    get('/')
        ->assertHeader('Content-Security-Policy-Report-Only')
        ->assertHeaderMissing('Content-Security-Policy');
});

it('sends the enforced policy header and not the report-only header when report-only is disabled', function (mixed $disabled): void {
    config(['antibody.security.csp_report_only' => $disabled]);

    get('/')
        ->assertHeader('Content-Security-Policy')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only');
})->with([
    'boolean false' => [false],
    'string false' => ['false'],
    'string no' => ['no'],
    'string off' => ['off'],
    'string zero' => ['0'],
    'integer zero' => [0],
]);

it('sends the report-only policy header when report-only is enabled or the value is not a recognised boolean', function (mixed $enabled): void {
    config(['antibody.security.csp_report_only' => $enabled]);

    get('/')
        ->assertHeader('Content-Security-Policy-Report-Only')
        ->assertHeaderMissing('Content-Security-Policy');
})->with([
    'boolean true' => [true],
    'string true' => ['true'],
    'string yes' => ['yes'],
    'string on' => ['on'],
    'string one' => ['1'],
    'unrecognised text' => ['maybe'],
    'missing key' => [null],
]);

it('does not replace a header the route already set', function (): void {
    Route::middleware('web')->get('probe/framed', fn (): Response => response('ok')->header('X-Frame-Options', 'SAMEORIGIN'));

    get('/probe/framed')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});
