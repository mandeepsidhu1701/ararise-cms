<?php

namespace App\Models;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * A tenant video — either an embedded YouTube clip or a directly uploaded file.
 */
class TenantVideo extends DataObject
{
    private static $table_name = 'TenantVideo';

    private static $db = [
        'Title'        => 'Varchar(255)',
        'Description'  => 'Text',
        'VideoType'    => 'Enum("youtube,upload", "youtube")',
        'YouTubeURL'   => 'Varchar(500)',
        'YouTubeID'    => 'Varchar(20)',   // auto-extracted from the URL
        'DisplayOrder' => 'Int',
        'IsActive'     => 'Boolean(1)',
        'Category'     => 'Varchar(100)',  // e.g. "Client Stories"
    ];

    private static $has_one = [
        'Tenant'    => Tenant::class,
        'VideoFile' => File::class,   // for direct upload
        'Thumbnail' => Image::class,
    ];

    private static $owns = ['VideoFile', 'Thumbnail'];

    private static $defaults = [
        'IsActive'  => true,
        'VideoType' => 'youtube',
    ];

    private static $default_sort = 'DisplayOrder ASC';

    private static $summary_fields = [
        'Title'         => 'Title',
        'VideoType'     => 'Type',
        'Category'      => 'Category',
        'DisplayOrder'  => 'Order',
        'IsActive.Nice' => 'Active',
    ];

    private static $searchable_fields = ['Title', 'Category'];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['VideoType', 'YouTubeURL', 'YouTubeID', 'VideoFile', 'Thumbnail']);

        $fields->addFieldsToTab('Root.Main', [
            DropdownField::create('VideoType', 'Video type', [
                'youtube' => 'YouTube embed',
                'upload'  => 'Uploaded file',
            ]),
            (TextField::create('YouTubeURL', 'YouTube URL')
                ->setDescription('Paste full YouTube URL e.g. https://www.youtube.com/watch?v=...'))
                ->displayIf('VideoType')->isEqualTo('youtube')->end(),
            (ReadonlyField::create('YouTubeID', 'YouTube video ID (auto-filled)')
                ->setDescription('Extracted automatically from the URL above.'))
                ->displayIf('VideoType')->isEqualTo('youtube')->end(),
            (UploadField::create('VideoFile', 'Video file')
                ->setFolderName('tenant-videos')
                ->setAllowedExtensions(['mp4', 'mov', 'webm', 'ogg', 'm4v']))
                ->displayIf('VideoType')->isEqualTo('upload')->end(),
            UploadField::create('Thumbnail', 'Thumbnail image')
                ->setFolderName('tenant-videos')
                ->setAllowedExtensions(['jpg', 'jpeg', 'png', 'webp', 'gif']),
        ]);

        return $fields;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if ($this->YouTubeURL) {
            preg_match(
                '/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/',
                $this->YouTubeURL,
                $matches
            );
            if (!empty($matches[1])) {
                $this->YouTubeID = $matches[1];
            }
        }

        // Auto-set TenantID from the logged-in member.
        if (!$this->TenantID) {
            $member = Security::getCurrentUser();
            if ($member && $member->TenantID) {
                $this->TenantID = $member->TenantID;
            }
        }
    }

    /**
     * The canonical embed URL for a YouTube video.
     */
    public function getEmbedURL(): string
    {
        return $this->YouTubeID
            ? 'https://www.youtube.com/embed/' . $this->YouTubeID
            : '';
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
