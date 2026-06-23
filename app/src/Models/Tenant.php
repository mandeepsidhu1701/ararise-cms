<?php

namespace App\Models;

use SilverStripe\Assets\Image;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;

/**
 * A client account. Everything in the CMS hangs off a Tenant so that one
 * SilverStripe installation (cms.ararise.com) can serve many client sites.
 */
class Tenant extends DataObject implements PermissionProvider
{
    private static $table_name = 'Tenant';

    private static $db = [
        'Name'         => 'Varchar(255)',
        'Slug'         => 'Varchar(100)',
        'Domain'       => 'Varchar(255)',
        'APIKey'       => 'Varchar(64)',
        'IsActive'     => 'Boolean(1)',
        'PrimaryColor' => 'Varchar(7)',
    ];

    private static $has_one = [
        'Logo'      => Image::class,
        'CreatedBy' => Member::class,
    ];

    private static $has_many = [
        'Pages'        => TenantPage::class,
        'Services'     => TenantService::class,
        'Posts'        => TenantPost::class,
        'Testimonials' => TenantTestimonial::class,
        'Members'      => Member::class,
    ];

    private static $owns = ['Logo'];

    private static $indexes = [
        'Slug' => [
            'type'    => 'unique',
            'columns' => ['Slug'],
        ],
        'APIKey' => ['columns' => ['APIKey']],
    ];

    private static $defaults = [
        'IsActive'     => true,
        'PrimaryColor' => '#cc1f1f',
    ];

    private static $summary_fields = [
        'Name'         => 'Name',
        'Slug'         => 'Slug',
        'Domain'       => 'Domain',
        'IsActive.Nice' => 'Active',
    ];

    private static $searchable_fields = ['Name', 'Slug', 'Domain'];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['APIKey', 'CreatedByID', 'Pages', 'Services', 'Posts', 'Testimonials', 'Members']);

        $fields->dataFieldByName('PrimaryColor')
            ->setDescription('Hex colour, e.g. #cc1f1f');

        // The API key is generated automatically and shown read-only.
        if ($this->APIKey) {
            $fields->addFieldToTab('Root.Main', ReadonlyField::create(
                'APIKeyReadonly',
                'API Key',
                $this->APIKey
            )->setDescription('Send as the X-API-Key request header. Auto-generated.'));
        }

        return $fields;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->APIKey) {
            $this->APIKey = $this->generateAPIKey();
        }

        if (!$this->Slug && $this->Name) {
            $this->Slug = $this->generateSlug($this->Name);
        }

        if (!$this->CreatedByID && ($member = Security::getCurrentUser())) {
            $this->CreatedByID = $member->ID;
        }
    }

    /**
     * Generate a random 64-character API token.
     */
    public function generateAPIKey(): string
    {
        return bin2hex(random_bytes(32)); // 32 bytes => 64 hex chars
    }

    private function generateSlug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }

    // --- Permissions -------------------------------------------------------

    public function providePermissions()
    {
        return [
            'TENANT_SUPER_ADMIN' => [
                'name'     => 'Manage all tenants (super admin)',
                'category' => 'Tenant CMS',
                'help'     => 'Create/edit/delete tenants and see all data across every tenant.',
                'sort'     => 100,
            ],
            'TENANT_ADMIN' => [
                'name'     => 'Administer own tenant',
                'category' => 'Tenant CMS',
                'help'     => 'Manage all content and staff for the assigned tenant only.',
                'sort'     => 90,
            ],
            'TENANT_EDITOR' => [
                'name'     => 'Edit own tenant content',
                'category' => 'Tenant CMS',
                'help'     => 'Edit posts and testimonials for the assigned tenant only.',
                'sort'     => 80,
            ],
        ];
    }

    /**
     * Only super admins may manage Tenant records.
     */
    public function canView($member = null)
    {
        return $this->isSuperAdmin($member);
    }

    public function canEdit($member = null)
    {
        return $this->isSuperAdmin($member);
    }

    public function canCreate($member = null, $context = [])
    {
        return $this->isSuperAdmin($member);
    }

    public function canDelete($member = null)
    {
        return $this->isSuperAdmin($member);
    }

    public static function isSuperAdmin($member = null): bool
    {
        $member = $member ?: Security::getCurrentUser();
        if (!$member) {
            return false;
        }
        return Permission::checkMember($member, 'ADMIN')
            || Permission::checkMember($member, 'TENANT_SUPER_ADMIN');
    }

    /**
     * Ensure the three permission groups exist and carry the right permission
     * codes after every dev/build. Idempotent and self-healing — re-grants any
     * missing codes (e.g. after a permission was renamed).
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        $groups = [
            'tenant-superadmin' => ['Tenant Super Admins', ['TENANT_SUPER_ADMIN', 'ADMIN']],
            'tenant-admin'      => ['Tenant Admins', ['TENANT_ADMIN', 'CMS_ACCESS_App\\Admin\\TenantAdmin', 'CMS_ACCESS_LeftAndMain']],
            'tenant-editor'     => ['Tenant Editors', ['TENANT_EDITOR', 'CMS_ACCESS_App\\Admin\\TenantAdmin', 'CMS_ACCESS_LeftAndMain']],
        ];

        foreach ($groups as $code => [$title, $perms]) {
            $group = Group::get()->filter('Code', $code)->first();
            if (!$group) {
                $group = Group::create();
                $group->Code = $code;
                $group->Title = $title;
                $group->write();
                \SilverStripe\ORM\DB::alteration_message("Tenant group '$title' created", 'created');
            }

            foreach ($perms as $p) {
                $hasPerm = Permission::get()
                    ->filter(['GroupID' => $group->ID, 'Code' => $p])
                    ->exists();
                if (!$hasPerm) {
                    Permission::grant($group->ID, $p);
                    \SilverStripe\ORM\DB::alteration_message("Granted '$p' to '$title'", 'created');
                }
            }
        }
    }
}
