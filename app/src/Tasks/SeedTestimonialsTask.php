<?php

namespace App\Tasks;

use App\Models\Tenant;
use App\Models\TenantTestimonial;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

/**
 * Seeds the initial Credo testimonials into the database via the ORM.
 *
 * Safe to re-run: testimonials already present (matched by ClientName within
 * the tenant) are skipped, so it never creates duplicates.
 *
 * Run from CLI:   vendor/bin/sake dev/tasks/SeedTestimonialsTask
 * Run in browser: http://credo-api.test/dev/tasks/SeedTestimonialsTask
 */
class SeedTestimonialsTask extends BuildTask
{
    private static $segment = 'SeedTestimonialsTask';

    protected $title = 'Seed Credo Testimonials';

    protected $description =
        'Creates the initial testimonials for the Credo tenant. '
        . 'Idempotent — existing testimonials (matched by client name) are skipped.';

    /** The tenant these testimonials belong to. */
    private const TENANT_SLUG = 'credo';

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
            "Seeding testimonials for tenant '{$tenant->Name}' (ID {$tenant->ID})",
            'info'
        );

        $created = 0;
        $skipped = 0;
        $order   = 0;

        foreach ($this->testimonialData() as $data) {
            $order++;

            $alreadyExists = TenantTestimonial::get()->filter([
                'TenantID'   => $tenant->ID,
                'ClientName' => $data['ClientName'],
            ])->exists();

            if ($alreadyExists) {
                $skipped++;
                DB::alteration_message("Skipped (exists): {$data['ClientName']}", 'notice');
                continue;
            }

            $t = TenantTestimonial::create();
            $t->TenantID        = $tenant->ID;
            $t->ClientName      = $data['ClientName'];
            $t->Location        = $data['Location'];
            $t->Rating          = $data['Rating'];
            $t->TestimonialText = $data['Text'];
            $t->IsFeatured      = $data['IsFeatured'];
            $t->IsActive        = true;
            $t->DisplayOrder    = $order;
            $t->write();

            $created++;
            DB::alteration_message("Created: {$data['ClientName']}", 'created');
        }

        DB::alteration_message(
            "Finished. Created {$created}, skipped {$skipped}.",
            'created'
        );
    }

    /**
     * The source testimonials, mapped to TenantTestimonial fields.
     * Featured items are listed first so DisplayOrder keeps them on top.
     */
    private function testimonialData(): array
    {
        return [
            [
                'ClientName' => 'Neeru Sharma',
                'Location'   => 'New Zealand',
                'Rating'     => 5,
                'IsFeatured' => true,
                'Text'       => "The best immigration consultant ever!! Credo Migration were very supportive and patiently listened to me and helped me in the best way to get my work permit in the most professional way. My consultant - Ms. Manisha Verma - special thank you so much for really supporting and guiding through the process, many hurdles and odd times!! But she encouraged and was helpful all the time. Thank you again!",
            ],
            [
                'ClientName' => 'Mj Singh',
                'Location'   => 'New Zealand',
                'Rating'     => 5,
                'IsFeatured' => true,
                'Text'       => "Started from Work visa. Because of incompetency of my previous lawyer I had to face a lot of problems then via a mutual friend I met with Manisha and today I will say thank you so much Manisha for helping us to settle down in this beautiful country. The professional knowledge and skills you are providing are transparent and efficient. Manisha knows how to tackle all sort of hurdles through a proper channel. Highly recommended Credo Migration!",
            ],
            [
                'ClientName' => 'Gurleen Kaur',
                'Location'   => 'New Zealand',
                'Rating'     => 5,
                'IsFeatured' => true,
                'Text'       => "Really appreciate the guidance provided by Manisha. From my student visa and till Residency, she made all the steps very clear and understandable which made me achieve my goal. Did a great job! Highly recommended.",
            ],
            [
                'ClientName' => 'Simran Kang',
                'Location'   => 'Auckland, NZ',
                'Rating'     => 5,
                'IsFeatured' => true,
                'Text'       => "Thank you so much Manisha mam for helping us with our partnership residence visa. We really appreciate the effort and guidance you provided us. Some years ago, I felt very upset when I submitted my husband visa application through another lawyer as I received refusal. Afterward one of our friends recommended Credo Migration. Manisha mam assisted me in overcoming this challenging situation and I successfully got my husband visa with her support. I truly value her help and highly recommend to everyone Credo Migration Services.",
            ],
            [
                'ClientName' => 'Mankiran Sidhu',
                'Location'   => 'New Zealand',
                'Rating'     => 5,
                'IsFeatured' => false,
                'Text'       => "The efforts poured into each visa application is highly appreciated. Manisha, you continue working on our family visas since my husband's study visa then eventually family dependent visa in November 2016. I highly recommend Credo Migration Services. I can't forget your personal touch in your professional advice. Thanks again and will take services in future as well.",
            ],
            [
                'ClientName' => 'Sumit Mehta',
                'Location'   => 'New Zealand',
                'Rating'     => 5,
                'IsFeatured' => false,
                'Text'       => "Hi Manisha I want to thank you for the excellent work which you did in approving my 3 work visas and then the Permanent Residency too which I never thought I would get. I am so grateful for your dedication and professionalism which I got and would recommend people going through you.",
            ],
            [
                'ClientName' => 'Rajat Sehdev',
                'Location'   => 'New Zealand',
                'Rating'     => 5,
                'IsFeatured' => false,
                'Text'       => "I would like to thank Manisha Mam. Because I have successfully got the work visa. You made things very easy for me. I am really happy with the advice and quick service. CREDO MIGRATION SERVICES LTD are the best advisor to get visa. It is the best place for best advice. Once again Thank you so much Manisha Mam.",
            ],
            [
                'ClientName' => 'Aman Sidhu',
                'Location'   => 'New Zealand',
                'Rating'     => 5,
                'IsFeatured' => false,
                'Text'       => "Really happy with my quick visa and the way my case was presented by Mam Manisha. Really helpful and I will for sure recommend it to all my friends. Thanks mam for taking my stress instead and making my life easy.",
            ],
            [
                'ClientName' => 'Soni Grewal Toor',
                'Location'   => 'New Zealand',
                'Rating'     => 5,
                'IsFeatured' => false,
                'Text'       => "Credo Migration took me out from stress every time. Yes, I applied for all my visas from Credo Migration and they helped me a lot in getting all my visas. I can say to trust 100 percent on Credo Migration because not only me, my friends whom I suggested to come and try from Credo they all get 100 percent positive results.",
            ],
            [
                'ClientName' => 'Ankur Sharma',
                'Location'   => 'New Zealand',
                'Rating'     => 5,
                'IsFeatured' => false,
                'Text'       => "Credo Migration is very good. I was so tensed but it took me out from stress in just few days. I think it has 100 percent result so just trust and follow the Credo Migration. Thanks to you!",
            ],
            [
                'ClientName' => 'Jack Jack',
                'Location'   => 'New Zealand',
                'Rating'     => 5,
                'IsFeatured' => false,
                'Text'       => "Thank Manisha so much for your service and support. Finally I got my residency!",
            ],
            [
                'ClientName' => 'Amrit Threeke',
                'Location'   => 'New Zealand',
                'Rating'     => 5,
                'IsFeatured' => false,
                'Text'       => "Thank you so much Manisha for helping us with our partnership residence visa application. We really appreciate the effort and guidance you provided. You are very trustworthy & honest with us throughout the whole visa process. Highly recommended to everyone & we will continue to take Credo Services in future too.",
            ],
            [
                'ClientName' => 'Sahil Kashyap',
                'Location'   => 'New Zealand',
                'Rating'     => 5,
                'IsFeatured' => false,
                'Text'       => "A big thank you to Credo Migration Services in helping me attain my Essential Skills for 3 years! Thank you so much Manisha Ma'am for your dedicated relentless efforts, your guidance and skills that helped me in attaining my visa smoothly. Highly recommended. Thank you and Good luck for your future endeavours!",
            ],
        ];
    }
}
