<?php

declare(strict_types=1);

namespace App\Features\Shared\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SecurityHeaders
{
    public const string ENFORCED_POLICY_HEADER = 'Content-Security-Policy';

    public const string REPORT_ONLY_POLICY_HEADER = 'Content-Security-Policy-Report-Only';

    private const int DEFAULT_HSTS_MAX_AGE = 15552000;

    private const array BASELINE_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    private const array MACHINE_POLICY = [
        "default-src 'none'",
        "frame-ancestors 'none'",
        "base-uri 'none'",
        "form-action 'none'",
    ];

    private const array PANEL_POLICY = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob:",
        "font-src 'self' data:",
        "connect-src 'self'",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'",
    ];

    private const array PAGE_POLICY = [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data:",
        "font-src 'self'",
        "connect-src 'self'",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'",
    ];

    private const array MACHINE_PATHS = ['api', 'api/*', 'webhooks', 'webhooks/*'];

    private const array PANEL_SEGMENTS = ['admin', 'filament'];

    private const string LIVEWIRE_SEGMENT_PATTERN = '/^livewire-[0-9a-f]+$/';

    public function __construct(private Repository $config) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::BASELINE_HEADERS as $name => $value) {
            $this->setUnlessPresent($response, $name, $value);
        }

        $this->setUnlessPresent($response, $this->policyHeaderName(), implode('; ', $this->policyFor($request)));

        if ($request->isSecure()) {
            $this->setUnlessPresent($response, 'Strict-Transport-Security', 'max-age='.$this->hstsMaxAge().'; includeSubDomains');
        }

        return $response;
    }

    private function setUnlessPresent(Response $response, string $name, string $value): void
    {
        if ($response->headers->has($name)) {
            return;
        }

        $response->headers->set($name, $value);
    }

    private function policyHeaderName(): string
    {
        return filter_var($this->config->get('antibody.security.csp_report_only') ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === false
            ? self::ENFORCED_POLICY_HEADER
            : self::REPORT_ONLY_POLICY_HEADER;
    }

    /**
     * @return list<string>
     */
    private function policyFor(Request $request): array
    {
        if ($request->is(...self::MACHINE_PATHS)) {
            return self::MACHINE_POLICY;
        }

        if ($this->isPanelRequest($request)) {
            return self::PANEL_POLICY;
        }

        return self::PAGE_POLICY;
    }

    private function isPanelRequest(Request $request): bool
    {
        $segment = $request->segment(1);

        if (! is_string($segment)) {
            return false;
        }

        return in_array($segment, self::PANEL_SEGMENTS, true) || preg_match(self::LIVEWIRE_SEGMENT_PATTERN, $segment) === 1;
    }

    private function hstsMaxAge(): int
    {
        $maxAge = $this->config->get('antibody.security.hsts_max_age');

        return is_int($maxAge) && $maxAge >= 0 ? $maxAge : self::DEFAULT_HSTS_MAX_AGE;
    }
}
