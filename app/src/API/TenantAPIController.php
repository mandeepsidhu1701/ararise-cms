<?php

namespace App\API;

use App\Models\Tenant;
use App\Models\TenantPage;
use App\Models\TenantPost;
use App\Models\TenantService;
use App\Models\TenantSetting;
use App\Models\TenantTestimonial;
use App\Models\TenantVideo;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Public read API: GET /api/v1/{slug}/{resource}[/{item}]
 *
 *   GET /api/v1/{slug}/settings
 *   GET /api/v1/{slug}/pages            GET /api/v1/{slug}/pages/{type|slug}
 *   GET /api/v1/{slug}/services
 *   GET /api/v1/{slug}/posts            GET /api/v1/{slug}/posts/{slug}
 *   GET /api/v1/{slug}/testimonials
 *   GET /api/v1/{slug}/videos
 *
 * Every request must present a matching X-API-Key header OR originate from the
 * tenant's registered Domain. Responses are JSON with tenant-scoped CORS.
 *
 * NOTE: there is no logged-in Member in this context, so TenantDataExtension's
 * auto-filter does not fire — every query below is scoped explicitly by
 * TenantID, which is the only safe thing to rely on for a public endpoint.
 */
class TenantAPIController extends Controller
{
    private static $allowed_actions = ['index'];

    public function index(HTTPRequest $request)
    {
        $tenant = $this->resolveTenant($request);

        if ($request->httpMethod() === 'OPTIONS') {
            return $this->withCors(HTTPResponse::create('', 204), $tenant, $request);
        }
        if (!$tenant) {
            return $this->error('Tenant not found', 404, null, $request);
        }
        if (!$this->authorise($tenant, $request)) {
            return $this->error('Unauthorized', 401, $tenant, $request);
        }

        $resource = $request->param('Resource');
        $item = $request->param('Item');

        switch ($resource) {
            case 'settings':
                return $this->respond($this->settingsData($tenant), 200, $tenant, $request);
            case 'pages':
                return $item
                    ? $this->respondOrMissing($this->pageData($tenant, $item), 'Page', $tenant, $request)
                    : $this->respond($this->pagesData($tenant), 200, $tenant, $request);
            case 'services':
                return $this->respond($this->servicesData($tenant), 200, $tenant, $request);
            case 'posts':
                return $item
                    ? $this->respondOrMissing($this->postData($tenant, $item), 'Post', $tenant, $request)
                    : $this->respond($this->postsData($tenant), 200, $tenant, $request);
            case 'testimonials':
                return $this->respond($this->testimonialsData($tenant), 200, $tenant, $request);
            case 'videos':
                return $this->respond($this->videosData($tenant), 200, $tenant, $request);
        }

        return $this->error('Unknown resource', 404, $tenant, $request);
    }

    // --- Tenant resolution & auth -----------------------------------------

    protected function resolveTenant(HTTPRequest $request): ?Tenant
    {
        $slug = $request->param('Slug');
        if (!$slug) {
            return null;
        }
        return Tenant::get()->filter(['Slug' => $slug, 'IsActive' => true])->first();
    }

    protected function authorise(Tenant $tenant, HTTPRequest $request): bool
    {
        // 1) Matching API key.
        $key = $request->getHeader('X-API-Key');
        if ($key && $tenant->APIKey && hash_equals($tenant->APIKey, $key)) {
            return true;
        }

        // 2) Request origin matches the tenant's registered domain.
        $origin = $request->getHeader('Origin') ?: $request->getHeader('Referer');
        if ($origin) {
            $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
            if ($host && $tenant->Domain) {
                $domain = strtolower(preg_replace('/^www\./', '', $tenant->Domain));
                $host = preg_replace('/^www\./', '', $host);
                if ($host === $domain) {
                    return true;
                }
            }
        }

        // 3) Local development convenience.
        if (Director::isDev()) {
            $host = strtolower((string) $request->getHeader('Host'));
            if (str_contains($host, 'localhost') || str_ends_with($host, '.test') || str_starts_with($host, '127.0.0.1')) {
                return true;
            }
        }

        return false;
    }

    // --- Serialisers -------------------------------------------------------

    protected function settingsData(Tenant $tenant): array
    {
        /** @var TenantSetting $s */
        $s = TenantSetting::get()->filter('TenantID', $tenant->ID)->first();
        if (!$s) {
            return [];
        }
        return [
            'businessName'    => $s->BusinessName,
            'tagline'         => $s->Tagline,
            'phoneNZ'         => $s->PhoneNZ,
            'phoneAU'         => $s->PhoneAU,
            'email'           => $s->Email,
            'whatsApp'        => $s->WhatsApp,
            'facebook'        => $s->Facebook,
            'instagram'       => $s->Instagram,
            'linkedIn'        => $s->LinkedIn,
            'address'         => $s->Address,
            'iaaLicenseNumber' => $s->IAA_LicenseNumber,
            'maraAgentNumber' => $s->MARA_AgentNumber,
            'googleMapsEmbed' => $s->GoogleMapsEmbed,
            'footerText'      => $s->FooterText,
            // Analytics & tracking — the React frontend injects these into <head>.
            'gtmCode'            => $s->GTMCode,
            'ga4Code'            => $s->GA4Code,
            'facebookPixelCode'  => $s->FacebookPixelCode,
            'googleVerification' => $s->GoogleVerification,
        ];
    }

    protected function pagesData(Tenant $tenant): array
    {
        $pages = TenantPage::get()->filter(['TenantID' => $tenant->ID, 'IsPublished' => true]);
        return array_map([$this, 'pageToArray'], $pages->toArray());
    }

    protected function pageData(Tenant $tenant, string $key): ?array
    {
        $page = TenantPage::get()->filter([
            'TenantID'    => $tenant->ID,
            'IsPublished' => true,
        ])->filterAny(['PageType' => $key, 'Slug' => $key])->first();
        return $page ? $this->pageToArray($page) : null;
    }

    protected function pageToArray(TenantPage $p): array
    {
        return [
            'title'           => $p->Title,
            'slug'            => $p->Slug,
            'pageType'        => $p->PageType,
            'heroTitle'       => $p->HeroTitle,
            'heroSubtitle'    => $p->HeroSubtitle,
            'heroButtonText'  => $p->HeroButtonText,
            'heroButtonURL'   => $p->HeroButtonURL,
            'metaTitle'       => $p->MetaTitle,
            'metaDescription' => $p->MetaDescription,
            'content'         => $p->Content,
        ];
    }

    protected function servicesData(Tenant $tenant): array
    {
        $services = TenantService::get()
            ->filter(['TenantID' => $tenant->ID, 'IsActive' => true])
            ->sort('DisplayOrder', 'ASC');

        $out = [];
        foreach ($services as $s) {
            $features = [];
            foreach ($s->Features() as $f) {
                $features[] = $f->Title;
            }
            $out[] = [
                'title'            => $s->Title,
                'slug'             => $s->Slug,
                'shortDescription' => $s->ShortDescription,
                'fullDescription'  => $s->FullDescription,
                'iconName'         => $s->IconName,
                'categoryLabel'    => $s->CategoryLabel,
                'displayOrder'     => (int) $s->DisplayOrder,
                'features'         => $features,
            ];
        }
        return $out;
    }

    protected function postsData(Tenant $tenant): array
    {
        $posts = TenantPost::get()
            ->filter(['TenantID' => $tenant->ID, 'IsPublished' => true])
            ->sort('PublishedAt', 'DESC');
        return array_map([$this, 'postToArray'], $posts->toArray());
    }

    protected function postData(Tenant $tenant, string $slug): ?array
    {
        $post = TenantPost::get()->filter([
            'TenantID'    => $tenant->ID,
            'IsPublished' => true,
            'Slug'        => $slug,
        ])->first();
        return $post ? $this->postToArray($post) : null;
    }

    protected function postToArray(TenantPost $p): array
    {
        $image = '';
        if ($p->FeaturedImageID && ($img = $p->FeaturedImage()) && $img->exists()) {
            $image = $img->getAbsoluteURL();
        }
        return [
            'id'            => $p->ID,
            'title'         => $p->Title,
            'slug'          => $p->Slug,
            'excerpt'       => $p->Excerpt,
            'content'       => $p->Content,
            'category'      => $p->Category,
            'author'        => $p->Author,
            'publishedAt'   => $p->PublishedAt,
            'featuredImage' => $image,
        ];
    }

    protected function testimonialsData(Tenant $tenant): array
    {
        $items = TenantTestimonial::get()
            ->filter(['TenantID' => $tenant->ID, 'IsActive' => true])
            ->sort(['IsFeatured' => 'DESC', 'DisplayOrder' => 'ASC']);

        $out = [];
        foreach ($items as $t) {
            $photo = '';
            if ($t->PhotoID && ($img = $t->Photo()) && $img->exists()) {
                $photo = $img->getAbsoluteURL();
            }
            $out[] = [
                'id'              => (int)$t->ID,
                'clientName'      => $t->ClientName,
                'location'        => $t->Location,
                'rating'          => (int) $t->Rating,
                'testimonialText' => $t->TestimonialText,
                'photo'           => $photo,
                'isFeatured'      => (bool) $t->IsFeatured,
            ];
        }
        return $out;
    }

    protected function videosData(Tenant $tenant): array
    {
        $videos = TenantVideo::get()
            ->filter(['TenantID' => $tenant->ID, 'IsActive' => true])
            ->sort('DisplayOrder', 'ASC');

        $out = [];
        foreach ($videos as $v) {
            $thumb = '';
            if ($v->ThumbnailID && ($img = $v->Thumbnail()) && $img->exists()) {
                $thumb = $img->getAbsoluteURL();
            }

            $row = [
                'title'        => $v->Title,
                'description'  => $v->Description,
                'videoType'    => $v->VideoType,
                'category'     => $v->Category,
                'displayOrder' => (int) $v->DisplayOrder,
                'thumbnail'    => $thumb,
            ];

            if ($v->VideoType === 'youtube') {
                $row['youTubeID'] = $v->YouTubeID;
                $row['youTubeURL'] = $v->YouTubeURL;
                $row['embedURL'] = $v->getEmbedURL();
            } else {
                $fileUrl = '';
                if ($v->VideoFileID && ($file = $v->VideoFile()) && $file->exists()) {
                    $fileUrl = $file->getAbsoluteURL();
                }
                $row['fileURL'] = $fileUrl;
            }

            $out[] = $row;
        }
        return $out;
    }

    // --- Response helpers --------------------------------------------------

    protected function respond($data, int $status, ?Tenant $tenant, HTTPRequest $request): HTTPResponse
    {
        $response = HTTPResponse::create(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $status);
        $response->addHeader('Content-Type', 'application/json; charset=utf-8');
        return $this->withCors($response, $tenant, $request);
    }

    protected function respondOrMissing(?array $data, string $label, Tenant $tenant, HTTPRequest $request): HTTPResponse
    {
        if ($data === null) {
            return $this->error($label . ' not found', 404, $tenant, $request);
        }
        return $this->respond($data, 200, $tenant, $request);
    }

    protected function error(string $message, int $status, ?Tenant $tenant, HTTPRequest $request): HTTPResponse
    {
        return $this->respond(['error' => $message], $status, $tenant, $request);
    }

    protected function withCors(HTTPResponse $response, ?Tenant $tenant, HTTPRequest $request): HTTPResponse
    {
        // Allow the tenant's registered domain; fall back to the request origin
        // in dev so local frontends (credo.test, localhost:5173) work.
        $allowed = '';
        if ($tenant && $tenant->Domain) {
            $allowed = 'https://' . preg_replace('#^https?://#', '', $tenant->Domain);
        }
        $origin = $request->getHeader('Origin');
        if ($origin && (Director::isDev() || !$allowed)) {
            $allowed = $origin;
        }
        if (!$allowed) {
            $allowed = $origin ?: '*';
        }

        $response->addHeader('Access-Control-Allow-Origin', $allowed);
        $response->addHeader('Vary', 'Origin');
        $response->addHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->addHeader('Access-Control-Allow-Headers', 'Content-Type, X-API-Key');
        $response->addHeader('Access-Control-Max-Age', '86400');
        return $response;
    }
}
