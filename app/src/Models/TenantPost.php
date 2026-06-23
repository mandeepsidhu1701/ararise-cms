<?php

namespace App\Models;

use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * A blog / news post for a tenant.
 */
class TenantPost extends DataObject
{
    private static $table_name = 'TenantPost';

    private static $db = [
        'Title'       => 'Varchar(255)',
        'Slug'        => 'Varchar(150)',
        'Excerpt'     => 'Varchar(500)',
        'Content'     => 'HTMLText',
        'Category'    => 'Varchar(100)',
        'PublishedAt' => 'Date',
        'IsPublished' => 'Boolean(1)',
        'Author'      => 'Varchar(150)',
    ];

    private static $has_one = [
        'Tenant'        => Tenant::class,
        'FeaturedImage' => Image::class,
    ];

    private static $owns = ['FeaturedImage'];

    private static $defaults = [
        'IsPublished' => true,
    ];

    private static $default_sort = 'PublishedAt DESC';

    private static $summary_fields = [
        'Title'           => 'Title',
        'Category'        => 'Category',
        'PublishedAt.Nice' => 'Published at',
        'IsPublished.Nice' => 'Published',
    ];

    private static $searchable_fields = ['Title', 'Category', 'Author'];

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
        if (!$this->PublishedAt) {
            $this->PublishedAt = date('Y-m-d');
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
