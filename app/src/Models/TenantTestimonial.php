<?php

namespace App\Models;

use SilverStripe\Assets\Image;
use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * A client testimonial for a tenant.
 */
class TenantTestimonial extends DataObject
{
    private static $table_name = 'TenantTestimonial';

    private static $db = [
        'ClientName'      => 'Varchar(255)',
        'Location'        => 'Varchar(255)',
        'Rating'          => 'Int',
        'TestimonialText' => 'Text',
        'IsActive'        => 'Boolean(1)',
        'IsFeatured'      => 'Boolean(0)',
        'DisplayOrder'    => 'Int',
    ];

    private static $has_one = [
        'Tenant' => Tenant::class,
        'Photo'  => Image::class,
    ];

    private static $owns = ['Photo'];

    private static $defaults = [
        'IsActive' => true,
        'Rating'   => 5,
    ];

    private static $default_sort = 'DisplayOrder ASC';

    private static $summary_fields = [
        'ClientName'    => 'Client',
        'Location'      => 'Location',
        'Rating'        => 'Rating',
        'IsActive.Nice' => 'Active',
        'IsFeatured.Nice' => 'Featured',
    ];

    private static $searchable_fields = ['ClientName', 'Location'];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->replaceField('Rating', DropdownField::create(
            'Rating',
            'Rating',
            [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5]
        ));
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
        // Clamp rating to 1-5.
        $this->Rating = max(1, min(5, (int) $this->Rating));
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
