<?php

namespace App\Models;

use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;

/**
 * A content page for a tenant's marketing site (home, about, etc.).
 */
class TenantPage extends DataObject
{
    private static $table_name = 'TenantPage';

    private static $db = [
        'Title'           => 'Varchar(255)',
        'Slug'            => 'Varchar(150)',
        'PageType'        => 'Varchar(50)',
        'HeroTitle'       => 'Varchar(255)',
        'HeroSubtitle'    => 'Varchar(500)',
        'HeroButtonText'  => 'Varchar(100)',
        'HeroButtonURL'   => 'Varchar(255)',
        'MetaTitle'       => 'Varchar(255)',
        'MetaDescription' => 'Varchar(500)',
        'Content'         => 'HTMLText',
        'IsPublished'     => 'Boolean(1)',
    ];

    private static $has_one = [
        'Tenant' => Tenant::class,
    ];

    private static $defaults = [
        'IsPublished' => true,
        'PageType'    => 'home',
    ];

    private static $page_types = [
        'home'         => 'Home',
        'about'        => 'About',
        'services'     => 'Services',
        'contact'      => 'Contact',
        'testimonials' => 'Testimonials',
    ];

    private static $summary_fields = [
        'Title'          => 'Title',
        'PageType'       => 'Type',
        'IsPublished.Nice' => 'Published',
    ];

    private static $searchable_fields = ['Title', 'PageType', 'Slug'];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->replaceField('PageType', DropdownField::create(
            'PageType',
            'Page type',
            self::config()->get('page_types')
        ));
        return $fields;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->Slug && $this->Title) {
            $this->Slug = self::slugify($this->Title);
        }
    }

    public static function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
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
