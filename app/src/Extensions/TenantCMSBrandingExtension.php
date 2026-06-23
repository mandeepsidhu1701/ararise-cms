<?php

namespace App\Extensions;

use App\Models\Tenant;
use SilverStripe\Admin\LeftAndMainExtension;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;

/**
 * Brands the CMS for a logged-in tenant admin: tints the nav with the
 * tenant's primary colour and shows the tenant logo + name in the left panel.
 * Super admins see the default (un-branded) CMS.
 */
class TenantCMSBrandingExtension extends LeftAndMainExtension
{
    public function init()
    {
        $member = Security::getCurrentUser();
        if (!$member || !$member->TenantID || Tenant::isSuperAdmin($member)) {
            return;
        }

        /** @var Tenant $tenant */
        $tenant = $member->Tenant();
        if (!$tenant || !$tenant->exists()) {
            return;
        }

        $color = preg_match('/^#[0-9a-fA-F]{3,6}$/', (string) $tenant->PrimaryColor)
            ? $tenant->PrimaryColor
            : '#cc1f1f';

        $logoUrl = '';
        if ($tenant->LogoID && ($logo = $tenant->Logo()) && $logo->exists()) {
            $logoUrl = $logo->Fill(160, 48)->getAbsoluteURL();
        }

        Requirements::customCSS(<<<CSS
            .cms-menu__list { border-top: 4px solid {$color}; }
            #tenant-branding {
                display:flex; align-items:center; gap:8px;
                padding:10px 12px; border-bottom:1px solid #43536d;
                color:#fff; font-weight:600; font-size:13px;
            }
            #tenant-branding img { max-height:32px; width:auto; }
CSS
        );

        $name = json_encode($tenant->Name);
        $logo = json_encode($logoUrl);
        Requirements::customScript(<<<JS
            (function(){
                function brand(){
                    try {
                        var menu = document.querySelector('.cms-menu__list, .cms-menu');
                        if (!menu || document.getElementById('tenant-branding')) return;
                        var el = document.createElement('div');
                        el.id = 'tenant-branding';
                        var img = {$logo} ? '<img src="'+{$logo}+'" alt="logo"/>' : '';
                        el.innerHTML = img + '<span>'+{$name}+'</span>';
                        menu.parentNode.insertBefore(el, menu);
                    } catch(e) {}
                }
                if (document.readyState !== 'loading') { brand(); }
                else { document.addEventListener('DOMContentLoaded', brand); }
                setTimeout(brand, 800);
            })();
JS
        );
    }
}
