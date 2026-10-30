<?php

declare(strict_types=1);

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

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
    'zero' => [0],
    'negative' => [-10],
    'string' => ['forever'],
    'null' => [null],
]);

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

it('sends the panel policy on a livewire-prefixed path', function (): void {
    Route::middleware('web')->get('livewire-probe/ping', fn (): Response => response('ok'));

    $policy = (string) get('/livewire-probe/ping')->assertOk()->headers->get('Content-Security-Policy-Report-Only');

    expect($policy)->toContain("'unsafe-eval'");
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

it('sends the enforced policy header and not the report-only header when report-only is disabled', function (): void {
    config(['antibody.security.csp_report_only' => false]);

    get('/')
        ->assertHeader('Content-Security-Policy')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only');
});

it('does not replace a header the route already set', function (): void {
    Route::middleware('web')->get('probe/framed', fn (): Response => response('ok')->header('X-Frame-Options', 'SAMEORIGIN'));

    get('/probe/framed')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});
