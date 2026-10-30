<?php

declare(strict_types=1);

namespace Tests\Fixtures\Architecture\Hygiene;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class DirtyCode
{
    public function run(object $query, object $collection, string $payload): mixed
    {
        dd($payload);
        dump($payload);
        var_dump($payload);
        ray($payload);
        $collection->dump();
        $mode = env('APP_ENV');
        $output = shell_exec('whoami');
        exec('whoami');
        system('whoami');
        passthru('whoami');
        $process = proc_open('whoami', [], $pipes);
        $pipe = popen('whoami', 'r');
        $value = unserialize($payload);
        eval('return 1;');
        $listing = `ls`;
        DB::raw('1');
        DB::statement('select 1');
        $query->whereRaw('1 = 1');
        $query->selectRaw('count(*)');
        $query->orderByRaw('1');
        $query->havingRaw('1');
        Model::unguard();
        Model::unguarded(fn (): bool => true);

        return [$mode, $output, $process, $pipe, $value, $listing];
    }
}
