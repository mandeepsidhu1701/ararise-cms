<?php

namespace App\Models;

use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;

/**
 * Singleton-per-tenant settings (contact details, social links, footer).
 * Exactly one record should exist per Tenant.
 */
class TenantSetting extends DataObject
{
    private static $table_name = 'TenantSetting';

    private static $db = [
        'BusinessName'      => 'Varchar(255)',
        'Tagline'           => 'Varchar(255)',
        'PhoneNZ'           => 'Varchar(50)',
        'PhoneAU'           => 'Varchar(50)',
        'Email'             => 'Varchar(255)',
        'WhatsApp'          => 'Varchar(50)',
        'Facebook'          => 'Varchar(255)',
        'Instagram'         => 'Varchar(255)',
        'LinkedIn'          => 'Varchar(255)',
        'Address'           => 'Text',
        'IAA_LicenseNumber' => 'Varchar(100)',
        'MARA_AgentNumber'  => 'Varchar(100)',
        'GoogleMapsEmbed'   => 'Text',
        'FooterText'        => 'Text',
        // Analytics & tracking
        'GTMCode'            => 'Varchar(30)',   // e.g. GTM-XXXXXXX
        'GA4Code'            => 'Varchar(30)',   // e.g. G-XXXXXXXXXX
        'FacebookPixelCode'  => 'Varchar(30)',   // e.g. 1234567890
        'GoogleVerification' => 'Varchar(100)',  // meta tag content value
    ];

    private static $has_one = [
        'Tenant' => Tenant::class,
    ];

    private static $indexes = [
        'Tenant' => [
            'type'    => 'unique',
            'columns' => ['TenantID'],
        ],
    ];

    private static $summary_fields = [
        'BusinessName' => 'Business',
        'Email'        => 'Email',
        'PhoneNZ'      => 'Phone (NZ)',
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldsToTab('Root.Analytics', [
            HeaderField::create('TrackingHeader', 'Tracking Codes'),
            TextField::create('GTMCode', 'Google Tag Manager ID')
                ->setDescription('Format: GTM-XXXXXXX'),
            TextField::create('GA4Code', 'Google Analytics 4 ID')
                ->setDescription('Format: G-XXXXXXXXXX'),
            TextField::create('FacebookPixelCode', 'Facebook Pixel ID')
                ->setDescription('Numeric ID only'),
            TextField::create('GoogleVerification', 'Google Search Console Verification')
                ->setDescription('Content value from the meta tag only'),
        ]);

        // Give the tab a friendlier title than the field-name default.
        if ($tab = $fields->fieldByName('Root.Analytics')) {
            $tab->setTitle('Analytics & Tracking');
        }

        return $fields;
    }

    public function getTitle()
    {
        return $this->BusinessName ?: 'Settings';
    }
}
