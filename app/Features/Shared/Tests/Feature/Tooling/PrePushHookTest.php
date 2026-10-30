<?php

declare(strict_types=1);

use Tests\Support\HookSandbox;

afterEach(function (): void {
    HookSandbox::cleanup();
});

function toolingPushLine(string $localRef, string $remoteRef, ?string $localSha = null): string
{
    return $localRef.' '.($localSha ?? str_repeat('1', 40)).' '.$remoteRef.' '.str_repeat('2', 40)."\n";
}

it('refuses a push from a branch that is not a task branch', function (string $branch): void {
    $sandbox = HookSandbox::create($branch);

    $result = $sandbox->runHook('pre-push', toolingPushLine("refs/heads/{$branch}", "refs/heads/{$branch}"));

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('Push blocked:')
        ->and($result->errorOutput())->toContain('Push only task branches named task/ABP-123-short-slug.')
        ->and($sandbox->npmCalls())->toBe([]);
})->with([
    'main' => ['main'],
    'feature branch' => ['feature/x'],
    'lowercase ticket prefix' => ['task/abp-1-x'],
    'uppercase in the slug' => ['task/ABP-1-Upper'],
    'ticket without a slug' => ['task/ABP-1'],
    'ticket without a number' => ['task/ABP-x-slug'],
    'underscore in the slug' => ['task/ABP-1-with_underscore'],
]);

it('refuses to push a task branch to main', function (): void {
    $sandbox = HookSandbox::create('task/ABP-9-install-security-checks');

    $result = $sandbox->runHook('pre-push', toolingPushLine('refs/heads/task/ABP-9-install-security-checks', 'refs/heads/main'));

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('pushing to main is not allowed.')
        ->and($sandbox->npmCalls())->toBe([]);
});

it('refuses to push a task branch to a differently named remote branch', function (string $remoteRef): void {
    $sandbox = HookSandbox::create('task/ABP-9-install-security-checks');

    $result = $sandbox->runHook('pre-push', toolingPushLine('refs/heads/task/ABP-9-install-security-checks', $remoteRef));

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('Push blocked:')
        ->and($sandbox->npmCalls())->toBe([]);
})->with([
    'feature branch' => ['refs/heads/feature/x'],
    'lowercase task branch' => ['refs/heads/task/abp-1-x'],
    'master branch' => ['refs/heads/master'],
]);

it('refuses to push a tag', function (): void {
    $sandbox = HookSandbox::create('task/ABP-9-install-security-checks');

    $result = $sandbox->runHook('pre-push', toolingPushLine('refs/tags/v1', 'refs/tags/v1'));

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('only branches may be pushed, not refs/tags/v1.')
        ->and($sandbox->npmCalls())->toBe([]);
});

it('refuses to delete a remote branch', function (): void {
    $sandbox = HookSandbox::create('task/ABP-9-install-security-checks');

    $result = $sandbox->runHook('pre-push', toolingPushLine('(delete)', 'refs/heads/task/ABP-9-install-security-checks', HookSandbox::ZERO_SHA));

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('deleting the remote branch task/ABP-9-install-security-checks is not allowed.')
        ->and($sandbox->npmCalls())->toBe([]);
});

it('refuses a push from a detached HEAD', function (): void {
    $sandbox = HookSandbox::create('task/ABP-9-install-security-checks');
    $sandbox->detachHead();

    $result = $sandbox->runHook('pre-push', toolingPushLine('HEAD', 'refs/heads/task/ABP-9-install-security-checks'));

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('HEAD is detached.')
        ->and($sandbox->npmCalls())->toBe([]);
});

it('refuses the whole push when one of several refs is not allowed', function (): void {
    $sandbox = HookSandbox::create('task/ABP-9-install-security-checks');
    $stdin = toolingPushLine('refs/heads/task/ABP-9-install-security-checks', 'refs/heads/task/ABP-9-install-security-checks')
        .toolingPushLine('refs/heads/task/ABP-9-install-security-checks', 'refs/heads/main');

    $result = $sandbox->runHook('pre-push', $stdin);

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('pushing to main is not allowed.')
        ->and($sandbox->npmCalls())->toBe([]);
});

it('allows a task branch pushed to the same ref and runs the single CI gate', function (): void {
    $sandbox = HookSandbox::create('task/ABP-9-install-security-checks');

    $result = $sandbox->runHook('pre-push', toolingPushLine('refs/heads/task/ABP-9-install-security-checks', 'refs/heads/task/ABP-9-install-security-checks'));

    expect($result->exitCode())->toBe(0)
        ->and($result->errorOutput())->toBe('')
        ->and($sandbox->npmCalls())->toBe(['run ci:check']);
});

it('allows a task branch with a multi-digit ticket and digits in the slug', function (): void {
    $sandbox = HookSandbox::create('task/ABP-123-add-2fa-v2');

    $result = $sandbox->runHook('pre-push', toolingPushLine('refs/heads/task/ABP-123-add-2fa-v2', 'refs/heads/task/ABP-123-add-2fa-v2'));

    expect($result->exitCode())->toBe(0)
        ->and($sandbox->npmCalls())->toBe(['run ci:check']);
});

it('runs the CI gate when the push carries no refs', function (): void {
    $sandbox = HookSandbox::create('task/ABP-9-install-security-checks');

    $result = $sandbox->runHook('pre-push');

    expect($result->exitCode())->toBe(0)
        ->and($sandbox->npmCalls())->toBe(['run ci:check']);
});

it('fails the push when the CI gate fails', function (): void {
    $sandbox = HookSandbox::create('task/ABP-9-install-security-checks');

    $result = $sandbox->runHook('pre-push', toolingPushLine('refs/heads/task/ABP-9-install-security-checks', 'refs/heads/task/ABP-9-install-security-checks'), npmExit: 3);

    expect($result->exitCode())->toBe(3)
        ->and($sandbox->npmCalls())->toBe(['run ci:check']);
});
