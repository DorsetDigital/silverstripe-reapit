<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Model;

use SilverStripe\ORM\DataObject;

/** Tracks monitoring state independently from remote API sync checkpoints. */
class JobHealth extends DataObject
{
    private static string $table_name = 'ReapitJobHealth';

    private static array $db = [
        'JobClass' => 'Varchar(255)',
        'FirstSeen' => 'Datetime',
        'LastSuccessful' => 'Datetime',
        'AlertActive' => 'Boolean',
    ];

    private static array $indexes = [
        'JobClassUnique' => ['type' => 'unique', 'columns' => ['JobClass']],
    ];

    public function canView($member = null): bool
    {
        return false;
    }
}
