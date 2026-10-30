<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

use function Pest\Laravel\get;

function resolveAppDebugInFreshProcess(string|false $value): string
{
    $script = 'require "vendor/autoload.php"; $config = require "config/app.php"; echo var_export($config["debug"], true);';

    $result = Process::path(base_path())
        ->env(['APP_DEBUG' => $value])
        ->run([PHP_BINARY, '-r', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());

    return trim($result->output());
}

it('resolves debug to false when APP_DEBUG is not set', function (): void {
    expect(resolveAppDebugInFreshProcess(false))->toBe('false');
});

it('resolves debug to false when APP_DEBUG is the string false', function (): void {
    expect(resolveAppDebugInFreshProcess('false'))->toBe('false');
});

it('resolves debug to true when APP_DEBUG is the string true', function (): void {
    expect(resolveAppDebugInFreshProcess('true'))->toBe('true');
});

it('does not show a debug page for an unknown route when debug is off', function (): void {
    config(['app.debug' => false]);

    get('/does-not-exist')->assertNotFound()->assertDontSee(base_path(), false);
});
