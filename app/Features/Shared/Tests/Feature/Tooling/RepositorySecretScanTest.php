<?php

declare(strict_types=1);

use Tests\Support\HookSandbox;
use Tests\Support\Repo;
use Tests\Support\SecretScan;

afterEach(function (): void {
    HookSandbox::cleanup();
});

/**
 * @return array<string, string>
 */
function toolingSecretSamples(): array
{
    return [
        'antibody-private-key' => '-----BEGIN '.'RSA PRIVATE KEY-----',
        'antibody-anthropic-api-key' => 'sk-'.'ant-'.str_repeat('a', 30),
        'antibody-telegram-bot-token' => 'bot 123456789:'.str_repeat('A', 35).' end',
        'antibody-paypal-access-token' => 'A21'.'AA'.str_repeat('b', 30),
        'antibody-secret-assignment' => 'APP_'.'KEY=base64:'.str_repeat('Q', 40),
    ];
}

it('reads the five project rules and scopes the placeholder and path allowlists to the assignment rule', function (): void {
    $config = SecretScan::projectConfig();

    expect(array_column($config['rules'], 'id'))->toBe(array_keys(toolingSecretSamples()))
        ->and($config['paths'])->toBe([])
        ->and(SecretScan::rule('antibody-secret-assignment')['paths'])->toBe(['^\.env\.example$', '^tests/Fixtures/'])
        ->and(SecretScan::rule('antibody-anthropic-api-key')['paths'])->toBe([])
        ->and(SecretScan::rule('antibody-secret-assignment')['allowed'])->toBe(['^(?:your-[a-z0-9-]+|change-me|generate-[a-z0-9-]+)$'])
        ->and(SecretScan::rule('antibody-telegram-bot-token')['secretGroup'])->toBe(1)
        ->and(SecretScan::rule('antibody-secret-assignment')['secretGroup'])->toBe(1);
});

it('finds no secret in the committed source, docs, workflows and example environment', function (): void {
    expect(SecretScan::scanRepository(Repo::root(), SecretScan::projectConfig()))->toBe([]);
});

it('detects a runtime-built sample of each project rule', function (string $id, string $sample): void {
    expect(SecretScan::scanText('sample.txt', $sample, SecretScan::projectConfig()))->toBe(["sample.txt:1 {$id}"]);
})->with(function (): array {
    $datasets = [];

    foreach (toolingSecretSamples() as $id => $sample) {
        $datasets[$id] = [$id, $sample];
    }

    return $datasets;
});

it('reports the line number of the secret and never the secret itself', function (): void {
    $secret = 'sk-'.'ant-'.str_repeat('z', 30);

    $findings = SecretScan::scanText('a/b.txt', "first\nsecond\nkey {$secret}\n", SecretScan::projectConfig());

    expect($findings)->toBe(['a/b.txt:3 antibody-anthropic-api-key'])
        ->and(implode('', $findings))->not->toContain($secret);
});

it('lets a documented placeholder value through the assignment rule', function (string $value): void {
    expect(SecretScan::scanText('sample.txt', 'PAYPAL_CLIENT_'.'SECRET='.$value, SecretScan::projectConfig()))->toBe([]);
})->with([
    'empty' => [''],
    'your prefix' => ['your-sandbox-secret'],
    'quoted your prefix' => ['"your-sandbox-secret"'],
    'change me' => ['change-me'],
    'generate prefix' => ['generate-with-artisan'],
    'angle bracket' => ['<paste-secret-here>'],
    'shell variable' => ['${SANDBOX_SECRET}'],
]);

it('does not let a value that merely contains a placeholder word through the assignment rule', function (string $value): void {
    expect(SecretScan::scanText('sample.txt', 'PAYPAL_CLIENT_'.'SECRET='.$value, SecretScan::projectConfig()))->toBe(['sample.txt:1 antibody-secret-assignment']);
})->with([
    'placeholder word in the middle' => ['real-your-secret-value'],
    'uppercase placeholder' => ['YOUR-SECRET'],
    'real looking value' => ['Zx9Qw3Lm5Tn7'],
    'value starting with punctuation' => ['!Xk9Qw3Lm'],
    'value starting with an underscore' => ['_Xk9Qw3Lm'],
    'placeholder prefix followed by a real value' => ['your-RealValue9'],
]);

it('detects a secret assigned in a quoted PHP array entry', function (): void {
    expect(SecretScan::scanText('sample.php', "'DEMO_OWNER_"."PASSWORD' => 'Xk9Qw3Lm'", SecretScan::projectConfig()))->toBe(['sample.php:1 antibody-secret-assignment']);
});

it('keeps each rule at its length boundary', function (string $id, string $sample, bool $detected): void {
    expect(SecretScan::scanText('sample.txt', $sample, SecretScan::projectConfig()))->toBe($detected ? ["sample.txt:1 {$id}"] : []);
})->with([
    'anthropic key at 20 characters' => ['antibody-anthropic-api-key', 'sk-'.'ant-'.str_repeat('a', 20), true],
    'anthropic key at 19 characters' => ['antibody-anthropic-api-key', 'sk-'.'ant-'.str_repeat('a', 19), false],
    'paypal token at 20 characters' => ['antibody-paypal-access-token', 'A21'.'AA'.str_repeat('b', 20), true],
    'paypal token at 19 characters' => ['antibody-paypal-access-token', 'A21'.'AA'.str_repeat('b', 19), false],
    'telegram token at 35 characters' => ['antibody-telegram-bot-token', '12345678:'.str_repeat('A', 35), true],
    'telegram token at 34 characters' => ['antibody-telegram-bot-token', '12345678:'.str_repeat('A', 34), false],
    'telegram token at 36 characters' => ['antibody-telegram-bot-token', '12345678:'.str_repeat('A', 36), false],
    'telegram token with 7 digits' => ['antibody-telegram-bot-token', '1234567:'.str_repeat('A', 35), false],
    'telegram token with 11 digits' => ['antibody-telegram-bot-token', '12345678901:'.str_repeat('A', 35), false],
]);

it('skips an allowlisted fixture path and the example environment file but scans everything else', function (): void {
    $config = SecretScan::projectConfig();
    $sample = toolingSecretSamples()['antibody-anthropic-api-key'];
    $root = HookSandbox::scratch().'/scan';

    $assignment = toolingSecretSamples()['antibody-secret-assignment'];

    foreach (['tests/Fixtures/deep/leak.txt', 'app/leak.txt', 'config/leak.txt', 'tests/leak.txt', '.github/leak.txt'] as $file) {
        if (! is_dir(dirname($root.'/'.$file))) {
            mkdir(dirname($root.'/'.$file), 0777, true);
        }

        file_put_contents($root.'/'.$file, $sample."\n");
    }

    file_put_contents($root.'/tests/Fixtures/assignment.txt', $assignment."\n");
    file_put_contents($root.'/.env.example', $assignment."\n".$sample."\n");
    file_put_contents($root.'/app/binary.bin', "\0".$sample);
    file_put_contents($root.'/vendor-leak.txt', $sample."\n");

    expect(SecretScan::scanRepository($root, $config))->toBe([
        '.env.example:2 antibody-anthropic-api-key',
        '.github/leak.txt:1 antibody-anthropic-api-key',
        'app/leak.txt:1 antibody-anthropic-api-key',
        'config/leak.txt:1 antibody-anthropic-api-key',
        'tests/Fixtures/deep/leak.txt:1 antibody-anthropic-api-key',
        'tests/leak.txt:1 antibody-anthropic-api-key',
    ]);
});

it('matches the path allowlist only from the start of the relative path', function (): void {
    $paths = SecretScan::rule('antibody-secret-assignment')['paths'];

    expect(SecretScan::isAllowedPath('tests/Fixtures/x.txt', $paths))->toBeTrue()
        ->and(SecretScan::isAllowedPath('.env.example', $paths))->toBeTrue()
        ->and(SecretScan::isAllowedPath('app/tests/Fixtures/x.txt', $paths))->toBeFalse()
        ->and(SecretScan::isAllowedPath('config/.env.example', $paths))->toBeFalse()
        ->and(SecretScan::isAllowedPath('.env.example.bak', $paths))->toBeFalse()
        ->and(SecretScan::isAllowedPath('tests/FixturesX/x.txt', $paths))->toBeFalse();
});

it('parses a rule, a rule allowlist and a path allowlist from toml text', function (): void {
    $toml = implode("\n", [
        'title = "demo"',
        '[extend]',
        'useDefault = true',
        '[[rules]]',
        'id = "demo-rule"',
        "regex = '''tok-([a-z]{4})'''",
        'secretGroup = 1',
        '[[rules.allowlists]]',
        "regexes = ['''^skip''', '''^also''']",
        '[[rules.allowlists]]',
        "paths = ['''^fixtures/''']",
        '[[allowlists]]',
        "paths = ['''^a/''']",
    ]);

    expect(SecretScan::parse($toml))->toBe([
        'rules' => [['id' => 'demo-rule', 'regex' => 'tok-([a-z]{4})', 'secretGroup' => 1, 'allowed' => ['^skip', '^also'], 'paths' => ['^fixtures/']]],
        'paths' => ['^a/'],
    ]);
});
