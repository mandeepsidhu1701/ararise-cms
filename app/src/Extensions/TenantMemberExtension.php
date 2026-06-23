<?php

namespace App\Extensions;

use App\Models\Tenant;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

/**
 * Adds a Tenant association to Member so a CMS login can be tied to exactly
 * one tenant. Used by TenantDataExtension to scope every query.
 */
class TenantMemberExtension extends DataExtension
{
    private static $has_one = [
        'Tenant' => Tenant::class,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        // Only super admins may (re)assign a member to a tenant.
        if (!Tenant::isSuperAdmin()) {
            $fields->removeByName('TenantID');
            return;
        }

        $tenants = Tenant::get()->map('ID', 'Name')->toArray();
        $fields->addFieldToTab(
            'Root.Main',
            DropdownField::create('TenantID', 'Tenant', $tenants)
                ->setEmptyString('(super admin — no single tenant)')
                ->setDescription('Restricts this user to a single tenant\'s data.')
        );
    }
}
