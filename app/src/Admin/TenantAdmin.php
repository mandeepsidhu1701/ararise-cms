<?php

namespace App\Admin;

use App\Models\Tenant;
use App\Models\TenantPage;
use App\Models\TenantPost;
use App\Models\TenantService;
use App\Models\TenantSetting;
use App\Models\TenantTestimonial;
use App\Models\TenantVideo;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * The single CMS section that drives the whole multi-tenant CMS.
 *
 * - Super admins see a Tenants tab plus every content model and a per-tenant
 *   record-count dashboard.
 * - Tenant admins see only their own content (Pages, Services, Posts,
 *   Testimonials, Settings) thanks to TenantDataExtension.
 * - Tenant editors see only Posts and Testimonials.
 */
class TenantAdmin extends ModelAdmin
{
    private static $url_segment = 'tenants';

    private static $menu_title = 'Tenant CMS';

    private static $menu_icon_class = 'font-icon-multi-file';

    private static $managed_models = [
        Tenant::class,
        TenantPage::class,
        TenantService::class,
        TenantPost::class,
        TenantTestimonial::class,
        TenantVideo::class,
        TenantSetting::class,
    ];

    /**
     * Hide models a given member is not allowed to manage.
     */
    public function getManagedModels()
    {
        $models = parent::getManagedModels();
        $member = Security::getCurrentUser();

        $isSuper = Tenant::isSuperAdmin($member);
        $isEditor = !$isSuper
            && Permission::checkMember($member, 'TENANT_EDITOR')
            && !Permission::checkMember($member, 'TENANT_ADMIN');

        foreach ($models as $key => $config) {
            $class = is_array($config) ? ($config['dataClass'] ?? $key) : $key;

            // Only super admins manage Tenant records.
            if ($class === Tenant::class && !$isSuper) {
                unset($models[$key]);
                continue;
            }

            // Editors are limited to Posts and Testimonials.
            if ($isEditor && !in_array($class, [TenantPost::class, TenantTestimonial::class], true)) {
                unset($models[$key]);
            }
        }

        return $models;
    }

    /**
     * Scope the grid list to the member's own tenant (super admins see all).
     * This complements the ORM-level filter in TenantDataExtension.
     */
    public function getList()
    {
        $list = parent::getList();
        $member = Security::getCurrentUser();

        if ($member
            && $this->modelClass !== Tenant::class
            && !Permission::checkMember($member, 'ADMIN')
            && !Permission::checkMember($member, 'TENANT_SUPER_ADMIN')
        ) {
            $list = $list->filter('TenantID', (int) $member->TenantID);
        }

        return $list;
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);
        $member = Security::getCurrentUser();
        $isSuper = Tenant::isSuperAdmin($member);

        $gridFieldName = $this->sanitiseClassName($this->modelClass);
        $gridField = $form->Fields()->dataFieldByName($gridFieldName);

        if ($gridField) {
            // Make sure tenant admins/editors keep the Add + Edit buttons.
            // (ModelAdmin's default config includes them; this is a safety net
            // so nothing strips them for non-super-admin users.)
            $config = $gridField->getConfig();
            if (!$config->getComponentByType(GridFieldAddNewButton::class)) {
                $config->addComponent(GridFieldAddNewButton::class);
            }
            if (!$config->getComponentByType(GridFieldEditButton::class)) {
                $config->addComponent(GridFieldEditButton::class);
            }

            // Super admins get a Tenant column on every tenant-aware grid.
            if ($isSuper && $this->modelClass !== Tenant::class) {
                $columns = $gridField->getConfig()->getComponentByType(GridFieldDataColumns::class);
                if ($columns) {
                    $display = $columns->getDisplayFields($gridField);
                    $display['Tenant.Name'] = 'Tenant';
                    $columns->setDisplayFields($display);
                }
            }
        }

        // Prepend the dashboard to the first managed model's form.
        $form->Fields()->unshift(LiteralField::create(
            'TenantDashboard',
            $this->renderDashboard($member, $isSuper)
        ));

        return $form;
    }

    /**
     * Simple HTML dashboard of record counts (all tenants for super admins,
     * just the member's tenant otherwise).
     */
    private function renderDashboard(?Member $member, bool $isSuper): string
    {
        $counts = [
            'Pages'        => TenantPage::get()->count(),
            'Services'     => TenantService::get()->count(),
            'Posts'        => TenantPost::get()->count(),
            'Testimonials' => TenantTestimonial::get()->count(),
        ];

        $heading = $isSuper
            ? 'Super admin dashboard — totals across all tenants'
            : 'Your site at a glance';

        if ($isSuper) {
            $counts = ['Tenants' => Tenant::get()->count()] + $counts;
        } elseif ($member && $member->TenantID) {
            $heading = htmlspecialchars($member->Tenant()->Name) . ' — your site at a glance';
        }

        $cards = '';
        foreach ($counts as $label => $value) {
            $cards .= sprintf(
                '<div style="flex:1;min-width:120px;background:#fff;border:1px solid #e6eaed;'
                . 'border-radius:6px;padding:12px 16px;">'
                . '<div style="font-size:28px;font-weight:700;color:#0071c4;">%d</div>'
                . '<div style="color:#43536d;text-transform:uppercase;font-size:11px;'
                . 'letter-spacing:.5px;">%s</div></div>',
                $value,
                htmlspecialchars($label)
            );
        }

        return sprintf(
            '<div class="tenant-dashboard" style="margin:0 0 20px;">'
            . '<h3 style="margin:0 0 10px;">%s</h3>'
            . '<div style="display:flex;gap:12px;flex-wrap:wrap;">%s</div></div>',
            htmlspecialchars($heading),
            $cards
        );
    }
}
