<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Model;

use SilverStripe\ORM\DataObject;

class SyncState extends DataObject
{
    private static string $table_name = 'ReapitSyncState';

    private static array $db = [
        'CustomerID' => 'Varchar(32)',
        'Channel' => 'Varchar(40)',
        'LastSuccessful' => 'Datetime',
    ];

    private static array $indexes = [
        'SyncKey' => ['type' => 'unique', 'columns' => ['CustomerID', 'Channel']],
    ];

    public function canView($member = null): bool
    {
        return false;
    }
}
