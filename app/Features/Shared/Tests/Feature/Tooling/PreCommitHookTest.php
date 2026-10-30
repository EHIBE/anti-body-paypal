<?php

declare(strict_types=1);

use Tests\Support\HookSandbox;

afterEach(function (): void {
    HookSandbox::cleanup();
});

/**
 * @return list<string>
 */
function toolingSecretNames(): array
{
    return [
        'APP_KEY',
        'PAYPAL_CLIENT_SECRET',
        'ANTHROPIC_API_KEY',
        'TELEGRAM_BOT_TOKEN',
        'TELEGRAM_WEBHOOK_SECRET',
        'ANTIBODY_TOKEN_HMAC_KEY',
        'DEMO_OWNER_PASSWORD',
    ];
}

it('refuses a file that has both staged and unstaged changes before npm runs and lists it', function (): void {
    $sandbox = HookSandbox::create();
    $sandbox->stage('tracked.txt', "staged change\n");
    $sandbox->write('tracked.txt', "staged change\nunstaged change\n");

    $result = $sandbox->runHook('pre-commit');

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('Commit blocked: these files have both staged and unstaged changes.')
        ->and($result->errorOutput())->toContain('  tracked.txt')
        ->and($sandbox->npmCalls())->toBe([]);
});

it('refuses an environment file', function (string $path): void {
    $sandbox = HookSandbox::create();
    $sandbox->stage($path, "APP_NAME=demo\n");

    $result = $sandbox->runHook('pre-commit');

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('Commit blocked: environment files must never be committed.')
        ->and($result->errorOutput())->toContain("  {$path}")
        ->and($sandbox->npmCalls())->toBe([]);
})->with([
    'env' => ['.env'],
    'env local' => ['.env.local'],
    'nested env production' => ['config/.env.production'],
    'env backup' => ['.env.backup'],
]);

it('accepts the placeholder environment example', function (): void {
    $sandbox = HookSandbox::create();
    $sandbox->stage('.env.example', "APP_NAME=demo\n".'APP_'."KEY=\n");

    $result = $sandbox->runHook('pre-commit');

    expect($result->exitCode())->toBe(0)
        ->and($sandbox->npmCalls())->toBe(['run pre-commit']);
});

it('refuses a staged secret, names the file and the pattern and never prints the value', function (string $label, string $content, string $secret): void {
    $sandbox = HookSandbox::create();
    $sandbox->stage('notes/leak.txt', $content."\n");

    $result = $sandbox->runHook('pre-commit');

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('Commit blocked: the staged changes look like they contain secrets.')
        ->and($result->errorOutput())->toContain("  {$label} in notes/leak.txt")
        ->and($result->errorOutput())->not->toContain($secret)
        ->and($result->output())->not->toContain($secret)
        ->and($sandbox->npmCalls())->toBe([]);
})->with(function (): array {
    $privateKey = '-----BEGIN '.'RSA PRIVATE KEY-----';
    $anthropic = 'sk-'.'ant-'.str_repeat('a', 30);
    $telegram = '123456789:'.str_repeat('A', 35);
    $paypal = 'A21'.'AA'.str_repeat('b', 30);

    $cases = [
        'private key header' => ['private key', $privateKey, $privateKey],
        'ec private key header' => ['private key', '-----BEGIN '.'EC PRIVATE KEY-----', 'EC PRIVATE KEY-----'],
        'anthropic key' => ['Anthropic API key', 'key='.$anthropic, $anthropic],
        'anthropic key at the minimum length' => ['Anthropic API key', 'sk-'.'ant-'.str_repeat('c', 20), str_repeat('c', 20)],
        'telegram bot token' => ['Telegram bot token', 'token '.$telegram, $telegram],
        'telegram bot token with 8 digits' => ['Telegram bot token', '12345678:'.str_repeat('B', 35), str_repeat('B', 35)],
        'telegram bot token with 10 digits and dashes' => ['Telegram bot token', '1234567890:'.str_repeat('-', 35), str_repeat('-', 35)],
        'paypal access token' => ['PayPal access token', 'Bearer '.$paypal, $paypal],
        'paypal access token at the minimum length' => ['PayPal access token', 'A21'.'AA'.str_repeat('d', 20), str_repeat('d', 20)],
    ];

    foreach (toolingSecretNames() as $name) {
        $value = str_repeat('Z', 24);
        $cases["assignment of {$name}"] = ['secret value assignment', $name.'='.$value, $value];
    }

    $appKey = str_repeat('Q', 40);
    $password = str_repeat('x', 14);

    return $cases + [
        'base64 app key' => ['secret value assignment', 'APP_'.'KEY=base64:'.$appKey, $appKey],
        'quoted demo password' => ['secret value assignment', 'DEMO_OWNER_'.'PASSWORD="'.$password.'"', $password],
        'single quoted demo password' => ['secret value assignment', 'DEMO_OWNER_'."PASSWORD='".$password."'", $password],
        'yaml style assignment' => ['secret value assignment', 'PAYPAL_CLIENT_'.'SECRET: '.$password, $password],
        'assignment with spaces' => ['secret value assignment', 'TELEGRAM_BOT_'.'TOKEN = '.$password, $password],
    ];
});

it('accepts text that only looks like a secret but is outside the pattern bounds', function (string $content): void {
    $sandbox = HookSandbox::create();
    $sandbox->stage('notes/safe.txt', $content."\n");

    $result = $sandbox->runHook('pre-commit');

    expect($result->exitCode())->toBe(0)
        ->and($sandbox->npmCalls())->toBe(['run pre-commit']);
})->with(fn (): array => [
    'anthropic key one character short' => ['sk-'.'ant-'.str_repeat('a', 19)],
    'paypal token one character short' => ['A21'.'AA'.str_repeat('b', 19)],
    'telegram token with 34 characters' => ['123456789:'.str_repeat('A', 34)],
    'telegram token with 36 characters' => ['123456789:'.str_repeat('A', 36)],
    'telegram token with 7 digits' => ['1234567:'.str_repeat('A', 35)],
    'telegram token with 11 digits' => ['12345678901:'.str_repeat('A', 35)],
    'private key mentioned in prose' => ['rotate the private key every quarter'],
    'unrelated variable' => ['APP_NAME=anti-body-paypal'],
    'secret name as a plain word' => ['the '.'APP_KEY is generated by artisan'],
]);

it('accepts a documented placeholder as the value of a secret variable', function (string $value): void {
    $sandbox = HookSandbox::create();
    $sandbox->stage('notes/placeholders.txt', 'PAYPAL_CLIENT_'.'SECRET='.$value."\n");

    $result = $sandbox->runHook('pre-commit');

    expect($result->exitCode())->toBe(0)
        ->and($sandbox->npmCalls())->toBe(['run pre-commit']);
})->with([
    'empty value' => [''],
    'your prefix' => ['your-sandbox-secret'],
    'quoted your prefix' => ['"your-sandbox-secret"'],
    'change me' => ['change-me'],
    'generate prefix' => ['generate-with-artisan'],
    'angle bracket placeholder' => ['<paste-secret-here>'],
    'shell variable reference' => ['${SANDBOX_SECRET}'],
    'literal three dots' => ['...'],
]);

it('accepts a clean change, runs npm pre-commit and keeps untracked and unstaged files as they were', function (): void {
    $sandbox = HookSandbox::create();
    $sandbox->write('untracked.txt', "stays untracked\n");
    $sandbox->write('tracked.txt', "unstaged edit\n");
    $sandbox->stage('clean.txt', "a harmless change\n");

    $result = $sandbox->runHook('pre-commit');

    expect($result->exitCode())->toBe(0)
        ->and($sandbox->npmCalls())->toBe(['run pre-commit'])
        ->and($sandbox->status())->toBe([
            'A  clean.txt',
            ' M tracked.txt',
            '?? untracked.txt',
        ]);
});

it('stages the lines that npm pre-commit appended to an already staged file and nothing else', function (): void {
    $sandbox = HookSandbox::create();
    $sandbox->write('untracked.txt', "stays untracked\n");
    $sandbox->write('tracked.txt', "unstaged edit\n");
    $sandbox->stage('clean.txt', "first\n");

    $script = $sandbox->npmScriptThatAppends('clean.txt', 'appended by the formatter');

    $result = $sandbox->runHook('pre-commit', npmScript: $script);

    expect($result->exitCode())->toBe(0)
        ->and($sandbox->npmCalls())->toBe(['run pre-commit'])
        ->and($sandbox->git('show', ':clean.txt')->output())->toBe("first\nappended by the formatter\n")
        ->and($sandbox->git('diff', '--', 'clean.txt')->output())->toBe('')
        ->and($sandbox->status())->toBe(['A  clean.txt', ' M tracked.txt', '?? untracked.txt']);
});

it('names the right file when the first added line imitates a diff header', function (): void {
    $sandbox = HookSandbox::create();
    $secret = str_repeat('Z', 24);
    $sandbox->stage('notes/leak.txt', "++ b/decoy.txt\n".'APP_'.'KEY='.$secret."\n");

    $result = $sandbox->runHook('pre-commit');

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('  secret value assignment in notes/leak.txt')
        ->and($result->errorOutput())->not->toContain('decoy.txt')
        ->and($result->errorOutput())->not->toContain($secret)
        ->and($sandbox->npmCalls())->toBe([]);
});

it('refuses a secret in a file whose path is not ASCII', function (): void {
    $sandbox = HookSandbox::create();
    $secret = str_repeat('Z', 24);
    $sandbox->stage("notes/\u{e9}.txt", 'APP_'.'KEY='.$secret."\n");

    $result = $sandbox->runHook('pre-commit');

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain("  secret value assignment in notes/\u{e9}.txt")
        ->and($result->errorOutput())->not->toContain($secret)
        ->and($sandbox->npmCalls())->toBe([]);
});

it('refuses an environment file in a directory whose name is not ASCII', function (): void {
    $sandbox = HookSandbox::create();
    $sandbox->stage("\u{e9}/.env", "APP_NAME=demo\n");

    $result = $sandbox->runHook('pre-commit');

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain("  \u{e9}/.env")
        ->and($sandbox->npmCalls())->toBe([]);
});

it('still refuses a secret when the repository changes the diff prefixes', function (): void {
    $sandbox = HookSandbox::create();
    $sandbox->git('config', 'diff.noprefix', 'true')->throw();
    $sandbox->git('config', 'diff.mnemonicPrefix', 'true')->throw();
    $secret = str_repeat('Z', 24);
    $sandbox->stage('notes/leak.txt', 'APP_'.'KEY='.$secret."\n");

    $result = $sandbox->runHook('pre-commit');

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('  secret value assignment in notes/leak.txt')
        ->and($result->errorOutput())->not->toContain($secret)
        ->and($sandbox->npmCalls())->toBe([]);
});

it('refuses a secret value that starts with punctuation or looks like a placeholder with a real value', function (string $value): void {
    $sandbox = HookSandbox::create();
    $sandbox->stage('notes/leak.txt', 'PAYPAL_CLIENT_'.'SECRET='.$value."\n");

    $result = $sandbox->runHook('pre-commit');

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('  secret value assignment in notes/leak.txt')
        ->and($result->errorOutput())->not->toContain($value)
        ->and($sandbox->npmCalls())->toBe([]);
})->with([
    'exclamation mark' => ['!Xk9Qw3Lm'],
    'underscore' => ['_Xk9Qw3Lm'],
    'placeholder prefix with a mixed case value' => ['your-RealValue9'],
    'placeholder prefix with an underscore tail' => ['your-real_value'],
    'change me with a suffix' => ['change-me-now-Xk9'],
]);

it('refuses a secret assigned through a quoted array key', function (string $content, string $secret): void {
    $sandbox = HookSandbox::create();
    $sandbox->stage('notes/leak.php', $content."\n");

    $result = $sandbox->runHook('pre-commit');

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('  secret value assignment in notes/leak.php')
        ->and($result->errorOutput())->not->toContain($secret)
        ->and($sandbox->npmCalls())->toBe([]);
})->with(function (): array {
    $secret = str_repeat('x', 14);

    return [
        'single quoted key with a fat arrow' => ["'DEMO_OWNER_"."PASSWORD' => '".$secret."',", $secret],
        'double quoted key with a fat arrow' => ['"DEMO_OWNER_'.'PASSWORD" => "'.$secret.'",', $secret],
        'json style key' => ['"DEMO_OWNER_'.'PASSWORD": "'.$secret.'"', $secret],
    ];
});

it('accepts a placeholder wrapped in markdown code formatting', function (string $line): void {
    $sandbox = HookSandbox::create();
    $sandbox->stage('notes/guide.md', $line."\n");

    $result = $sandbox->runHook('pre-commit');

    expect($result->exitCode())->toBe(0)
        ->and($sandbox->npmCalls())->toBe(['run pre-commit']);
})->with([
    'inline code' => ['Set `PAYPAL_CLIENT_'.'SECRET=your-sandbox-secret` in the env file.'],
    'inline code with a trailing comma' => ['Use `PAYPAL_CLIENT_'.'SECRET=your-sandbox-secret`, then restart.'],
    'ellipsis' => ['PAYPAL_CLIENT_'.'SECRET=...'],
]);

it('fails the commit when npm pre-commit fails and does not stage anything extra', function (): void {
    $sandbox = HookSandbox::create();
    $sandbox->write('untracked.txt', "stays untracked\n");
    $sandbox->stage('clean.txt', "a harmless change\n");

    $result = $sandbox->runHook('pre-commit', npmExit: 7);

    expect($result->exitCode())->toBe(7)
        ->and($sandbox->npmCalls())->toBe(['run pre-commit'])
        ->and($sandbox->status())->toBe(['A  clean.txt', '?? untracked.txt']);
});
