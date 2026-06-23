<?php

namespace App\Models;

use SilverStripe\ORM\DataObject;

/**
 * A single bullet-point feature belonging to a TenantService.
 */
class TenantServiceFeature extends DataObject
{
    private static $table_name = 'TenantServiceFeature';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Sort'  => 'Int',
    ];

    private static $has_one = [
        'Service' => TenantService::class,
    ];

    private static $default_sort = 'Sort ASC';

    private static $summary_fields = [
        'Title' => 'Feature',
    ];

    /**
     * Permission inherited from the parent service so isolation still applies.
     */
    public function canView($member = null)
    {
        return $this->Service()->canView($member);
    }

    public function canEdit($member = null)
    {
        return $this->Service()->canEdit($member);
    }

    public function canCreate($member = null, $context = [])
    {
        return true;
    }

    public function canDelete($member = null)
    {
        return $this->Service()->canEdit($member);
    }
}
