<?php

namespace App\Extensions;

use App\Models\Tenant;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * Enforces tenant data isolation at the ORM level.
 *
 * Applied to every tenant-aware DataObject (those with a `Tenant` has_one).
 * When a non-super-admin member is logged in, *every* query on that class is
 * silently constrained to their own TenantID — so a TenantAdmin physically
 * cannot read or write another tenant's rows, regardless of the UI.
 *
 * Super admins (ADMIN / TENANT_SUPER_ADMIN) bypass the filter entirely.
 *
 * No member logged in (CLI dev/build, public REST API) => no auto-filter is
 * applied; the API controller scopes every query explicitly by tenant.
 */
class TenantDataExtension extends DataExtension
{
    /**
     * Constrain SELECTs to the current member's tenant.
     */
    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        $member = Security::getCurrentUser();
        if (!$member) {
            // CLI / API context — nothing to scope automatically.
            return;
        }

        if (Tenant::isSuperAdmin($member)) {
            return;
        }

        $baseTable = DataObject::getSchema()->baseDataTable($this->owner);
        $tenantID = (int) $member->TenantID;

        if ($tenantID > 0) {
            $query->addWhere([
                "\"{$baseTable}\".\"TenantID\" = ?" => $tenantID,
            ]);
        } else {
            // A CMS user with no tenant and no super-admin rights sees nothing.
            $query->addWhere('1 = 0');
        }
    }

    /**
     * Stamp new/edited records with the current member's tenant so a
     * TenantAdmin can never create rows under someone else's tenant.
     */
    public function onBeforeWrite()
    {
        $member = Security::getCurrentUser();
        if (!$member || Tenant::isSuperAdmin($member)) {
            return;
        }

        $tenantID = (int) $member->TenantID;
        if ($tenantID > 0) {
            $this->owner->TenantID = $tenantID;
        }
    }

    // --- Per-record permission checks --------------------------------------
    //
    // These return an *affirmative* true for same-tenant members. That matters:
    // SilverStripe's default canEdit/canCreate require ADMIN, so returning null
    // (no opinion) would let a TenantAdmin fall through to "denied". Returning
    // true here is what actually grants tenant admins access to their own data
    // (Pages and Settings rely on this; Posts/Services/Testimonials/Videos also
    // declare their own equivalent methods).

    public function canView($member = null)
    {
        return $this->checkTenantAccess($member);
    }

    public function canEdit($member = null)
    {
        return $this->checkTenantAccess($member);
    }

    public function canDelete($member = null)
    {
        return $this->checkTenantAccess($member);
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

        $tenantID = (int) $member->TenantID;
        if ($tenantID > 0 && (
            Permission::checkMember($member, 'TENANT_ADMIN')
            || Permission::checkMember($member, 'TENANT_EDITOR')
        )) {
            return true;
        }

        return false;
    }

    /**
     * true  = this tenant member may act on the record (their own tenant)
     * false = record belongs to another tenant (hard deny)
     * null  = no opinion (super admin / public — let the default rules decide)
     */
    private function checkTenantAccess($member = null)
    {
        $member = $member ?: Security::getCurrentUser();
        if (!$member) {
            return false;
        }
        if (Tenant::isSuperAdmin($member)) {
            return true;
        }

        $tenantID = (int) $member->TenantID;
        if (!$tenantID) {
            return false;
        }

        // Unsaved record will be stamped with this tenant on write — allow.
        if (!$this->owner->TenantID) {
            return true;
        }

        return (int) $this->owner->TenantID === $tenantID;
    }
}
