<?php

declare(strict_types=1);

use Tests\Architecture\RawSqlAliases;

it('aliases no column to a Postgres reserved word in raw SQL, which SQLite accepts and Postgres refuses', function (): void {
    $root = dirname(__DIR__, 2);

    expect(RawSqlAliases::in([$root.'/app', $root.'/database']))->toBe([]);
});

it('reports a reserved word alias in a planted query and accepts a safe alias', function (): void {
    expect(RawSqlAliases::in([__DIR__.'/../Fixtures/Architecture/RawSql']))->toBe([
        'ReservedAliasQuery.php line 11 aliases a column to the Postgres reserved word "user"',
    ]);
});

it('ignores aliases outside SQL and in import statements', function (string $source): void {
    expect(RawSqlAliases::inSource($source))->toBe([]);
})->with([
    'class alias' => ["use App\\Foo as User;\n"],
    'plain text' => ["'Pay the vendor as user requested'"],
    'safe column alias' => ["->selectRaw('sum(amount_minor) as total_minor')"],
]);

it('reports a reserved alias in double quoted and backtick quoted SQL', function (): void {
    expect(RawSqlAliases::inSource('$sql = "select max(id) as `order` from invoices";'))
        ->toBe(['line 1 aliases a column to the Postgres reserved word "order"']);
});
