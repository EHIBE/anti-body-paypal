<?php

declare(strict_types=1);

use Tests\Architecture\ForbiddenCalls;

arch('application code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

it('uses no debug helper, env, shell call, unserialize, eval, raw query or unguard in the application', function (): void {
    expect(ForbiddenCalls::in(dirname(__DIR__, 2).'/app'))->toBe([]);
});

it('keeps the raw SQL allowlist empty until an entry has a reason and a bound-parameter test', function (): void {
    expect(ForbiddenCalls::rawSqlAllowlist())->toBe([]);
});

it('reports every planted forbidden call with its line', function (): void {
    expect(ForbiddenCalls::in(__DIR__.'/../Fixtures/Architecture/Hygiene'))->toBe([
        'DirtyCode.php line 14 calls the debug helper dd()',
        'DirtyCode.php line 15 calls the debug helper dump()',
        'DirtyCode.php line 16 calls the debug helper var_dump()',
        'DirtyCode.php line 17 calls the debug helper ray()',
        'DirtyCode.php line 18 calls the debug method dump()',
        'DirtyCode.php line 19 calls the unsafe function env()',
        'DirtyCode.php line 20 calls the shell function shell_exec()',
        'DirtyCode.php line 21 calls the shell function exec()',
        'DirtyCode.php line 22 calls the shell function system()',
        'DirtyCode.php line 23 calls the shell function passthru()',
        'DirtyCode.php line 24 calls the shell function proc_open()',
        'DirtyCode.php line 25 calls the shell function popen()',
        'DirtyCode.php line 26 calls the unsafe function unserialize()',
        'DirtyCode.php line 27 uses eval',
        'DirtyCode.php line 28 uses the backtick shell operator',
        'DirtyCode.php line 28 uses the backtick shell operator',
        'DirtyCode.php line 29 calls the raw query method DB::raw()',
        'DirtyCode.php line 30 calls the raw query method DB::statement()',
        'DirtyCode.php line 31 calls the raw query method whereRaw()',
        'DirtyCode.php line 32 calls the raw query method selectRaw()',
        'DirtyCode.php line 33 calls the raw query method orderByRaw()',
        'DirtyCode.php line 34 calls the raw query method havingRaw()',
        'DirtyCode.php line 35 calls Model::unguard() to disable mass assignment protection',
        'DirtyCode.php line 36 calls Model::unguarded() to disable mass assignment protection',
    ]);
});

it('ignores lookalike method names, string contents and safe query builder calls', function (): void {
    $source = (string) file_get_contents(__DIR__.'/../Fixtures/Architecture/Hygiene/CleanCode.php');

    expect(ForbiddenCalls::inSource($source))->toBe([]);
});

it('returns no offences for a missing directory', function (): void {
    expect(ForbiddenCalls::in(__DIR__.'/../Fixtures/Architecture/Missing'))->toBe([]);
});
