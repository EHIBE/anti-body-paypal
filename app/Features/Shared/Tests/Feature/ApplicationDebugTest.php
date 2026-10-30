<?php

declare(strict_types=1);

use Illuminate\Support\Env;

use function Pest\Laravel\get;

/**
 * @param  callable(): void  $assertion
 */
function withAppDebugEnvironment(?string $value, callable $assertion): void
{
    $repository = Env::getRepository();
    $original = $repository->get('APP_DEBUG');

    try {
        if ($value === null) {
            $repository->clear('APP_DEBUG');
        } else {
            $repository->set('APP_DEBUG', $value);
        }

        $assertion();
    } finally {
        if (is_string($original)) {
            $repository->set('APP_DEBUG', $original);
        } else {
            $repository->clear('APP_DEBUG');
        }
    }
}

function resolveAppDebugConfig(): mixed
{
    $config = require base_path('config/app.php');

    return is_array($config) ? ($config['debug'] ?? null) : null;
}

it('resolves debug to false when APP_DEBUG is not set', function (): void {
    withAppDebugEnvironment(null, function (): void {
        expect(resolveAppDebugConfig())->toBeFalse();
    });
});

it('resolves debug to false when APP_DEBUG is the string false', function (): void {
    withAppDebugEnvironment('false', function (): void {
        expect(resolveAppDebugConfig())->toBeFalse();
    });
});

it('resolves debug to true when APP_DEBUG is the string true', function (): void {
    withAppDebugEnvironment('true', function (): void {
        expect(resolveAppDebugConfig())->toBeTrue();
    });
});

it('does not show a debug page for an unknown route when debug is off', function (): void {
    config(['app.debug' => false]);

    get('/does-not-exist')->assertNotFound()->assertDontSee(base_path(), false);
});
