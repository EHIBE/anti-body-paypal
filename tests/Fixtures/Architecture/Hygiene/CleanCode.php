<?php

declare(strict_types=1);

namespace Tests\Fixtures\Architecture\Hygiene;

use Illuminate\Support\Facades\DB;

final class CleanCode
{
    public function exec(): string
    {
        return 'eval( dd( whereRaw( are only text here';
    }

    public function run(object $query): mixed
    {
        $query->where('status', 'open');
        DB::table('invoices')->where('id', 1);
        DB::connection('other')->table('invoices')->where('id', 1);
        $query->raw('not a connection');
        $query->statement('not a connection');
        $query->unprepared('not a connection');
        $getenvLikeName = 'getenv';

        return $this->exec();
    }
}
