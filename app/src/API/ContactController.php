<?php

namespace App\API;

use App\Models\Tenant;
use App\Models\TenantSetting;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;

/**
 * Contact form endpoint: POST /api/v1/{slug}/contact
 *
 * - Rate limited to 5 submissions per hour per IP.
 * - Emails the submission to the tenant's configured Settings.Email.
 * - Returns { "success": true } or { "error": "..." }.
 */
class ContactController extends TenantAPIController
{
    private static $allowed_actions = ['index'];

    private const MAX_PER_HOUR = 5;

    public function index(HTTPRequest $request)
    {
        $tenant = $this->resolveTenant($request);

        if ($request->httpMethod() === 'OPTIONS') {
            return $this->withCors(HTTPResponse::create('', 204), $tenant, $request);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->error('Method not allowed', 405, $tenant, $request);
        }
        if (!$tenant) {
            return $this->error('Tenant not found', 404, null, $request);
        }
        if (!$this->authorise($tenant, $request)) {
            return $this->error('Unauthorized', 401, $tenant, $request);
        }

        // --- Rate limit ---------------------------------------------------
        $ip = $request->getIP() ?: 'unknown';
        if ($this->isRateLimited($tenant, $ip)) {
            return $this->error('Too many requests. Please try again later.', 429, $tenant, $request);
        }

        // --- Parse input --------------------------------------------------
        $data = $this->readPayload($request);
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? 'Website enquiry'));

        if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Please provide a valid name, email and message.', 422, $tenant, $request);
        }

        /** @var TenantSetting $setting */
        $setting = TenantSetting::get()->filter('TenantID', $tenant->ID)->first();
        $to = $setting && $setting->Email ? $setting->Email : null;
        if (!$to) {
            return $this->error('This site is not configured to receive enquiries.', 503, $tenant, $request);
        }

        // --- Send ---------------------------------------------------------
        try {
            $this->sendEmail($tenant, $to, compact('name', 'email', 'phone', 'subject', 'message'));
        } catch (\Throwable $e) {
            return $this->error('Could not send your message. Please try again later.', 500, $tenant, $request);
        }

        $this->recordHit($tenant, $ip);

        return $this->respond(['success' => true], 200, $tenant, $request);
    }

    private function readPayload(HTTPRequest $request): array
    {
        $body = (string) $request->getBody();
        if ($body !== '') {
            $json = json_decode($body, true);
            if (is_array($json)) {
                return $json;
            }
        }
        // Fall back to form-encoded post vars.
        return $request->postVars();
    }

    private function sendEmail(Tenant $tenant, string $to, array $fields): void
    {
        $lines = [
            'New enquiry via ' . $tenant->Name,
            '',
            'Name:    ' . $fields['name'],
            'Email:   ' . $fields['email'],
            'Phone:   ' . ($fields['phone'] ?: '-'),
            'Subject: ' . $fields['subject'],
            '',
            'Message:',
            $fields['message'],
        ];

        $email = Email::create()
            ->setTo($to)
            ->setReplyTo($fields['email'])
            ->setSubject('[' . $tenant->Name . '] ' . $fields['subject'])
            ->setBody(nl2br(htmlspecialchars(implode("\n", $lines))));

        // From: prefer a configured admin address, else a no-reply on the CMS host.
        $from = Email::config()->get('admin_email');
        if (!$from) {
            $from = 'no-reply@' . ($tenant->Domain ?: 'ararise.com');
        }
        $email->setFrom($from);

        $email->send();
    }

    // --- Rate limiting (per tenant + IP, 1 hour window) -------------------

    private function cache(): CacheInterface
    {
        return Injector::inst()->get(CacheInterface::class . '.tenantContactRateLimit');
    }

    private function rateKey(Tenant $tenant, string $ip): string
    {
        return 'contact_' . $tenant->ID . '_' . md5($ip);
    }

    private function isRateLimited(Tenant $tenant, string $ip): bool
    {
        $count = (int) $this->cache()->get($this->rateKey($tenant, $ip), 0);
        return $count >= self::MAX_PER_HOUR;
    }

    private function recordHit(Tenant $tenant, string $ip): void
    {
        $key = $this->rateKey($tenant, $ip);
        $count = (int) $this->cache()->get($key, 0);
        // 1 hour TTL; window resets an hour after the first hit.
        $this->cache()->set($key, $count + 1, 3600);
    }
}
