<?php

declare(strict_types=1);

namespace Tests\Fixtures\Architecture\RawSql;

final class ReservedAliasQuery
{
    public function reserved(): string
    {
        return 'select count(*) as user, max(id) as latest_id from invoices';
    }

    public function safe(): string
    {
        return 'select count(*) as user_count from invoices';
    }
}
