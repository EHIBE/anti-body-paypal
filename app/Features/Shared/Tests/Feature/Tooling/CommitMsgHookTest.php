<?php

declare(strict_types=1);

use Tests\Support\HookSandbox;

afterEach(function (): void {
    HookSandbox::cleanup();
});

it('accepts a valid commit message', function (string $message): void {
    $result = HookSandbox::runCommitMessageHook($message);

    expect($result->exitCode())->toBe(0)
        ->and($result->errorOutput())->toBe('');
})->with(function (): array {
    $messages = [];

    foreach (['feat', 'fix', 'refactor', 'perf', 'test', 'docs', 'chore'] as $type) {
        $messages["type {$type}"] = ["{$type}: [ABP-9] Add thing\n"];
    }

    return $messages + [
        'multi-digit ticket id' => ["feat: [ABP-1234] Add thing\n"],
        'body after a blank line' => ["fix: [ABP-9] Add thing\n\nLonger explanation of why.\nSecond line.\n"],
        'comment lines before the subject' => ["# Please enter the commit message\n# Lines starting with # are ignored\nfeat: [ABP-9] Add thing\n"],
        'comment line that mentions a trailer' => ["feat: [ABP-9] Add thing\n\n# Co-Authored-By: someone\n"],
        'merge commit' => ["Merge branch 'x'\n"],
        'revert commit' => ["Revert \"feat: [ABP-1] X\"\n"],
        'fixup commit' => ["fixup! feat: [ABP-1] X\n"],
        'squash commit' => ["squash! feat: [ABP-1] X\n"],
    ];
});

it('rejects a malformed subject and explains the expected format', function (string $message): void {
    $result = HookSandbox::runCommitMessageHook($message);

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('Commit blocked: invalid subject')
        ->and($result->errorOutput())->toContain('Expected: type: [ABP-123] Imperative action')
        ->and($result->errorOutput())->toContain('Types: feat fix refactor perf test docs chore');
})->with([
    'unknown type feature' => ["feature: [ABP-9] Add thing\n"],
    'unknown type style' => ["style: [ABP-9] Add thing\n"],
    'unknown type ci' => ["ci: [ABP-9] Add thing\n"],
    'uppercase type' => ["Feat: [ABP-9] Add thing\n"],
    'no type' => ["[ABP-9] Add thing\n"],
    'missing ticket id' => ["feat: Add thing\n"],
    'lowercase ticket prefix' => ["feat: [abp-9] Add thing\n"],
    'other ticket prefix' => ["feat: [SEMS-9] Add thing\n"],
    'ticket id without a number' => ["feat: [ABP-] Add thing\n"],
    'missing space after the colon' => ["feat:[ABP-9] Add thing\n"],
    'missing space after the ticket id' => ["feat: [ABP-9]Add thing\n"],
    'double space before the action' => ["feat: [ABP-9]  Add thing\n"],
    'empty action' => ["feat: [ABP-9] \n"],
    'no action at all' => ["feat: [ABP-9]\n"],
    'missing brackets' => ["feat: ABP-9 Add thing\n"],
    'plain sentence' => ["added a thing\n"],
    'empty message' => [''],
    'only comment lines' => ["# nothing here\n"],
    'valid subject on the second line' => ["\nfeat: [ABP-9] Add thing\n"],
    'merge without the space' => ["Merge: [ABP-9] Add thing\n"],
    'revert without the quote' => ["Revert feat: [ABP-1] X\n"],
]);

it('rejects a Co-Authored-By trailer even when the subject is valid', function (string $message): void {
    $result = HookSandbox::runCommitMessageHook($message);

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('Commit blocked: the message has a Co-Authored-By trailer.');
})->with([
    'canonical case' => ["feat: [ABP-9] Add thing\n\nCo-Authored-By: X <x@example.test>\n"],
    'lowercase' => ["feat: [ABP-9] Add thing\n\nco-authored-by: X <x@example.test>\n"],
    'uppercase' => ["feat: [ABP-9] Add thing\n\nCO-AUTHORED-BY: X <x@example.test>\n"],
    'on an exempt merge subject' => ["Merge branch 'x'\n\nCo-Authored-By: X <x@example.test>\n"],
    'on an exempt revert subject' => ["Revert \"feat: [ABP-1] X\"\n\nCo-Authored-By: X <x@example.test>\n"],
    'on an exempt fixup subject' => ["fixup! feat: [ABP-1] X\n\nCo-Authored-By: X <x@example.test>\n"],
    'in the middle of the body' => ["feat: [ABP-9] Add thing\n\nCo-Authored-By: X <x@example.test>\n\nMore text.\n"],
    'with an invalid subject' => ["nonsense\n\nCo-Authored-By: X <x@example.test>\n"],
]);

it('rejects a Generated with line even when the subject is valid', function (string $message): void {
    $result = HookSandbox::runCommitMessageHook($message);

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('Commit blocked: the message has a "Generated with" line.');
})->with([
    'plain line' => ["feat: [ABP-9] Add thing\n\nGenerated with a tool\n"],
    'bracketed link' => ["feat: [ABP-9] Add thing\n\nGenerated with [Tool](https://example.test)\n"],
    'on an exempt merge subject' => ["Merge branch 'x'\n\nGenerated with a tool\n"],
    'on an exempt squash subject' => ["squash! feat: [ABP-1] X\n\nGenerated with a tool\n"],
]);

it('fails when the commit message file does not exist', function (): void {
    $result = HookSandbox::runCommitMessageHook(null);

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('commit-msg: commit message file not found');
});

it('fails when no commit message file argument is given', function (): void {
    $result = HookSandbox::runCommitMessageHookWithoutArgument();

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('commit-msg: commit message file not found');
});
