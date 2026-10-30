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

        return $this->exec();
    }
}
