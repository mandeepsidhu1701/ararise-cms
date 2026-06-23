<?php

namespace App\Models;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * A service the tenant offers (e.g. "Family Visa").
 */
class TenantService extends DataObject
{
    private static $table_name = 'TenantService';

    private static $db = [
        'Title'            => 'Varchar(255)',
        'Slug'             => 'Varchar(150)',
        'ShortDescription' => 'Varchar(500)',
        'FullDescription'  => 'HTMLText',
        'IconName'         => 'Varchar(100)',
        'DisplayOrder'     => 'Int',
        'IsActive'         => 'Boolean(1)',
        'CategoryLabel'    => 'Varchar(100)',
    ];

    private static $has_one = [
        'Tenant' => Tenant::class,
    ];

    private static $has_many = [
        'Features' => TenantServiceFeature::class,
    ];

    private static $owns = ['Features'];
    private static $cascade_deletes = ['Features'];

    private static $defaults = [
        'IsActive' => true,
    ];

    private static $default_sort = 'DisplayOrder ASC';

    private static $summary_fields = [
        'Title'          => 'Title',
        'CategoryLabel'  => 'Category',
        'DisplayOrder'   => 'Order',
        'IsActive.Nice'  => 'Active',
    ];

    private static $searchable_fields = ['Title', 'CategoryLabel'];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        if ($this->isInDB()) {
            $features = $fields->dataFieldByName('Features');
            if ($features instanceof GridField) {
                $features->setConfig(GridFieldConfig_RecordEditor::create());
            }
        } else {
            $fields->removeByName('Features');
        }

        return $fields;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->TenantID) {
            $member = Security::getCurrentUser();
            if ($member && $member->TenantID) {
                $this->TenantID = $member->TenantID;
            }
        }
        if (!$this->Slug && $this->Title) {
            $this->Slug = TenantPage::slugify($this->Title);
        }
    }

   public function canView($member = null)
{
    return $this->canEdit($member);
}

public function canEdit($member = null)
{
    $member = $member ?: Security::getCurrentUser();
    if (!$member) {
        return false;
    }
    if (Tenant::isSuperAdmin($member)) {
        return true;
    }

    $tenantID = (int) $member->TenantID;
    if ($tenantID <= 0) {
        return false;
    }

    // New / unsaved record — allow; TenantID is stamped in onBeforeWrite().
    if (!$this->isInDB() || !$this->TenantID) {
        return true;
    }

    // Existing record — must belong to the member's own tenant.
    return (int) $this->TenantID === $tenantID;
}

public function canDelete($member = null)
{
    return $this->canEdit($member);
}

public function canCreate($member = null, $context = [])
{
    $member = $member ?: Security::getCurrentUser();
    if (!$member) {
        return false;
    }
    if (Tenant::isSuperAdmin($member)) {
        return true;
    }

    // Any member belonging to a tenant may create content.
    return ((int) $member->TenantID) > 0;
}
}
