<?php

namespace App\Tasks;

use App\Models\Tenant;
use App\Models\TenantPost;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

/**
 * Seeds the initial Credo news posts into the database via the ORM.
 *
 * Safe to re-run: posts already present (matched by Slug within the tenant)
 * are skipped, so it never creates duplicates.
 *
 * Run from CLI:   vendor/bin/sake dev/tasks/SeedPostsTask
 * Run in browser: http://credo-api.test/dev/tasks/SeedPostsTask
 */
class SeedPostsTask extends BuildTask
{
    private static $segment = 'SeedPostsTask';

    protected $title = 'Seed Credo News Posts';

    protected $description =
        'Creates the initial news posts for the Credo tenant. '
        . 'Idempotent — existing posts (matched by slug) are skipped.';

    /** The tenant these posts belong to. */
    private const TENANT_SLUG = 'credo';

    /** Author stamped on every seeded post (the source data has no author). */
    private const DEFAULT_AUTHOR = 'Credo Migration';

    public function run($request)
    {
        $tenant = Tenant::get()->filter('Slug', self::TENANT_SLUG)->first();

        if (!$tenant || !$tenant->exists()) {
            DB::alteration_message(
                "ERROR: no Tenant found with slug '" . self::TENANT_SLUG . "'. Aborting.",
                'error'
            );
            return;
        }

        DB::alteration_message(
            "Seeding posts for tenant '{$tenant->Name}' (ID {$tenant->ID})",
            'info'
        );

        $created = 0;
        $skipped = 0;

        foreach ($this->postData() as $data) {
            $alreadyExists = TenantPost::get()->filter([
                'TenantID' => $tenant->ID,
                'Slug'     => $data['Slug'],
            ])->exists();

            if ($alreadyExists) {
                $skipped++;
                DB::alteration_message("Skipped (exists): {$data['Slug']}", 'notice');
                continue;
            }

            $post = TenantPost::create();
            $post->TenantID    = $tenant->ID;
            $post->Title       = $data['Title'];
            $post->Slug        = $data['Slug'];
            $post->Excerpt     = $data['Excerpt'];
            $post->Content     = trim($data['Content']);
            $post->Category    = $data['Category'];
            $post->PublishedAt = $data['PublishedAt'];
            $post->Author      = self::DEFAULT_AUTHOR;
            $post->IsPublished = true;
            $post->write();

            $created++;
            DB::alteration_message("Created: {$data['Title']}", 'created');
        }

        DB::alteration_message(
            "Finished. Created {$created}, skipped {$skipped}.",
            'created'
        );
    }

    /**
     * The source posts, mapped to TenantPost fields.
     */
    private function postData(): array
    {
        return [
            [
                'Title'       => 'New Zealand Introduces Short-term Graduate Work Visa',
                'Slug'        => 'new-zealand-short-term-graduate-work-visa-2026',
                'Excerpt'     => 'Immigration New Zealand has announced a new Short-term Graduate Work Visa, giving eligible graduates 6 months of open work rights.',
                'Category'    => 'Policy Updates',
                'PublishedAt' => '2026-05-29',
                'Content'     => <<<'HTML'
<h2>New Short-term Graduate Work Visa</h2>
<p>Immigration New Zealand has announced a new Short-term Graduate Work Visa for eligible international graduates who have completed study in New Zealand but may not qualify for a Post Study Work Visa.</p>

<h2>Key Details</h2>
<ul>
  <li>The visa is expected to open from 16 November 2026.</li>
  <li>It provides 6 months of open work rights.</li>
  <li>Applicants must apply within 3 months of their New Zealand student visa expiring.</li>
  <li>The visa may help graduates look for work and transition to an Accredited Employer Work Visa where eligible.</li>
</ul>

<h2>Who May Benefit?</h2>
<p>This update may be useful for students completing eligible Level 5 to Level 7 qualifications who are not otherwise eligible for a Post Study Work Visa.</p>

<p>If you are planning your study-to-work pathway in New Zealand, professional advice can help you understand your options before your current visa expires.</p>
HTML,
            ],
            [
                'Title'       => 'Post Study Work Visa Eligibility Extended for Graduate Diplomas',
                'Slug'        => 'post-study-work-visa-eligibility-extended-graduate-diplomas-2026',
                'Excerpt'     => 'From November 2026, some graduate diploma holders may become eligible for a Post Study Work Visa if they meet specific conditions.',
                'Category'    => 'Student Visas',
                'PublishedAt' => '2026-05-29',
                'Content'     => <<<'HTML'
<h2>Post Study Work Visa Update</h2>
<p>Immigration New Zealand has confirmed changes to Post Study Work Visa eligibility for some graduate diploma holders.</p>

<h2>What Is Changing?</h2>
<ul>
  <li>From 16 November 2026, eligibility will extend to certain NZQCF Level 7 graduate diploma holders.</li>
  <li>Applicants must have studied full-time in New Zealand for the full duration of the qualification.</li>
  <li>Applicants must also hold a bachelor's degree completed in New Zealand or overseas.</li>
  <li>The Post Study Work Visa may be granted for the duration of study, up to a maximum of 1 year.</li>
</ul>

<h2>Important Note</h2>
<p>People who have already held a Post Study Work Visa are generally not eligible for a second Post Study Work Visa.</p>

<p>Students should review their study plans carefully before changing programmes or education providers.</p>
HTML,
            ],
            [
                'Title'       => 'AEWV English Requirements Extended to Skill Level 3 Roles',
                'Slug'        => 'aewv-english-requirements-skill-level-3-roles-2026',
                'Excerpt'     => 'From 1 June 2026, minimum English language requirements apply to AEWV applicants in ANZSCO or NOL skill level 3 roles.',
                'Category'    => 'Work Visas',
                'PublishedAt' => '2026-05-25',
                'Content'     => <<<'HTML'
<h2>AEWV English Requirement Update</h2>
<p>From 1 June 2026, Accredited Employer Work Visa applicants in ANZSCO or National Occupation List skill level 3 roles must meet minimum English language requirements.</p>

<h2>What This Means for Applicants</h2>
<ul>
  <li>Skill level 3 AEWV applicants may need to show English ability.</li>
  <li>English ability may be shown through citizenship, study, work history, or an approved English test.</li>
  <li>The requirement already applied to some skill level 4 and 5 roles.</li>
</ul>

<h2>Exceptions</h2>
<p>Immigration New Zealand has indicated that the requirement does not apply to Job Change applications and does not apply to Global Workforce Seasonal Visa or Peak Seasonal Visa AEWV applications.</p>

<p>Applicants and employers should check the skill level of the role before preparing an AEWV application.</p>
HTML,
            ],
            [
                'Title'       => 'Further Skilled Migrant Category Changes Coming in August 2026',
                'Slug'        => 'skilled-migrant-category-changes-august-2026',
                'Excerpt'     => 'Immigration New Zealand has confirmed further Skilled Migrant Category changes taking effect from 24 August 2026.',
                'Category'    => 'Residence',
                'PublishedAt' => '2026-03-05',
                'Content'     => <<<'HTML'
<h2>Skilled Migrant Category Update</h2>
<p>Immigration New Zealand has announced further details on Skilled Migrant Category changes that are expected to take effect from 24 August 2026.</p>

<h2>Key Changes</h2>
<ul>
  <li>Confirmation of key occupation lists, including Trades and Technician pathway lists.</li>
  <li>Simplified median wage settings across Skilled Migrant Category pathways.</li>
  <li>Clarified qualification requirements for claiming points.</li>
  <li>Extended English language test validity for some applicants.</li>
  <li>Recognition of a new occupational registration pathway for accountants.</li>
</ul>

<h2>Why This Matters</h2>
<p>The Skilled Migrant Category remains one of New Zealand's key residence pathways for skilled workers. Applicants should review how the changes may affect their points, occupation pathway, and evidence requirements.</p>

<p>If you are planning a residence application, it is important to assess your eligibility under the latest settings before applying.</p>
HTML,
            ],
            [
                'Title'       => 'Family of Temporary Visa Holder Applications Move Online',
                'Slug'        => 'family-temporary-visa-holder-applications-online-2026',
                'Excerpt'     => 'From 1 June 2026, several family visa applications for temporary visa holders move to Immigration New Zealand’s enhanced Immigration Online system.',
                'Category'    => 'Family Visas',
                'PublishedAt' => '2026-03-31',
                'Content'     => <<<'HTML'
<h2>Family Visa Application System Update</h2>
<p>From 1 June 2026, family of temporary visa holder applications are moving to Immigration New Zealand's enhanced Immigration Online system.</p>

<h2>Visa Types Included</h2>
<ul>
  <li>Dependent Child Student Visa</li>
  <li>Partner of a Worker Work Visa</li>
  <li>Partner of Military Work Visa</li>
  <li>Partner of a Student Work Visa</li>
  <li>Partner of an NZ Scholarship Student Work Visa</li>
</ul>

<h2>What Applicants Should Do</h2>
<p>Applicants should prepare documents carefully and follow the correct online application process. Any missing or inconsistent information may delay processing.</p>

<p>Families applying during the transition period should check which online system applies to their situation before lodging an application.</p>
HTML,
            ],
            [
                'Title'       => 'Pacific and Parent Visa Income Thresholds Increased',
                'Slug'        => 'pacific-parent-visa-income-thresholds-increase-2026',
                'Excerpt'     => 'From 30 April 2026, income and sponsorship thresholds increased for several Pacific and parent visa categories.',
                'Category'    => 'Family Visas',
                'PublishedAt' => '2026-04-14',
                'Content'     => <<<'HTML'
<h2>Income Threshold Changes</h2>
<p>Immigration New Zealand has confirmed that from 30 April 2026, income and sponsorship thresholds increased for several Pacific and family visa categories.</p>

<h2>Categories Affected</h2>
<ul>
  <li>Pacific Access Category</li>
  <li>Samoan Quota</li>
  <li>Parent Category Resident Visa</li>
  <li>Parent Boost Visitor Visa</li>
</ul>

<h2>Why This Matters</h2>
<p>Sponsors and applicants should check the latest income requirements before submitting an application. Meeting the correct financial threshold is an important part of family and parent visa eligibility.</p>

<p>If you are planning to sponsor family members, it is important to confirm the updated requirements before lodging your application.</p>
HTML,
            ],
            [
                'Title'       => 'Australia Updates Training Visa Subclass 407 Requirements',
                'Slug'        => 'australia-training-visa-subclass-407-requirements-2026',
                'Excerpt'     => 'The Australian Department of Home Affairs has updated requirements for Training visa subclass 407 applications.',
                'Category'    => 'Australia Visas',
                'PublishedAt' => '2026-03-10',
                'Content'     => <<<'HTML'
<h2>Training Visa Update</h2>
<p>The Australian Department of Home Affairs has announced changes to Training visa subclass 407 application requirements.</p>

<h2>What Applicants Should Know</h2>
<ul>
  <li>Training visa applications must meet the updated lodgement requirements.</li>
  <li>Sponsor and nomination details are important parts of the process.</li>
  <li>Applications submitted incorrectly may not be valid.</li>
</ul>

<h2>Why This Matters</h2>
<p>The Training visa is used by applicants undertaking workplace-based training or professional development in Australia. Sponsors and applicants should ensure the correct steps are followed before lodging.</p>

<p>Professional guidance can help reduce avoidable errors in sponsor, nomination, and visa application stages.</p>
HTML,
            ],
            [
                'Title'       => 'Australia Work and Holiday Visa Ballot Opens for 2026–2027',
                'Slug'        => 'australia-work-and-holiday-visa-ballot-2026-2027',
                'Excerpt'     => 'Registrations for the China, India and Vietnam Work and Holiday subclass 462 visa ballot opened in June 2026.',
                'Category'    => 'Australia Visas',
                'PublishedAt' => '2026-06-04',
                'Content'     => <<<'HTML'
<h2>Work and Holiday Ballot Update</h2>
<p>Australia has opened registrations for the Work and Holiday subclass 462 visa ballot for the 2026–2027 program year for China, India and Vietnam.</p>

<h2>Key Dates</h2>
<ul>
  <li>Registrations opened on 4 June 2026.</li>
  <li>Registrations close on 25 June 2026.</li>
  <li>The ballot applies to first Work and Holiday subclass 462 visa applicants from eligible countries.</li>
</ul>

<h2>Important for Indian Applicants</h2>
<p>India is part of Australia's Work and Holiday visa ballot process. Eligible Indian passport holders may register for the opportunity to be selected to apply.</p>

<p>Applicants should ensure they meet age, passport, education, and other eligibility requirements before registering.</p>
HTML,
            ],
            [
                'Title'       => 'Australia Encourages Complete Student Visa Applications for 2026',
                'Slug'        => 'australia-student-visa-complete-application-2026',
                'Excerpt'     => 'Students planning to study in Australia in 2026 are encouraged to lodge complete student visa applications as early as practical.',
                'Category'    => 'Student Visas',
                'PublishedAt' => '2025-11-13',
                'Content'     => <<<'HTML'
<h2>Student Visa Lodgement Reminder</h2>
<p>The Australian Department of Home Affairs has reminded students planning to study in Australia in 2026 to lodge complete student visa applications as soon as practical.</p>

<h2>Why Complete Applications Matter</h2>
<ul>
  <li>Incomplete applications may delay processing.</li>
  <li>Applicants should provide required identity, financial, study, health and character documents.</li>
  <li>Students should allow enough time before their course start date.</li>
</ul>

<h2>Planning Ahead</h2>
<p>International students should prepare early and ensure their application is consistent with their study plans, course enrolment, and Genuine Student requirements.</p>

<p>If you are planning to study in Australia, early preparation can help reduce unnecessary delays.</p>
HTML,
            ],
        ];
    }
}
