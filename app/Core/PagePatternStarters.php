<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

/** Optional starter sections for the Page Builder pattern library. */
final class PagePatternStarters
{
    /** @return list<array<string,mixed>> */
    public static function catalog(?array $existingPatterns = null): array
    {
        $installed = [];
        foreach ($existingPatterns ?? PagePatterns::all() as $pattern) {
            $installed[mb_strtolower((string)$pattern['name'])] = (int)$pattern['id'];
        }

        $modelsByKey = [];
        foreach (ContentModels::all() as $model) $modelsByKey[mb_strtolower((string)$model['model_key'])] = $model;
        $items = [];
        foreach (self::templates() as $key => $template) {
            $requires = is_array($template['requires'] ?? null) ? $template['requires'] : [];
            $requiredModelKey = (string)($requires['model_key'] ?? '');
            $requiredModel = $requiredModelKey !== '' ? ($modelsByKey[mb_strtolower($requiredModelKey)] ?? null) : null;
            $items[] = [
                'key' => $key,
                'name' => (string)$template['name'],
                'summary' => (string)$template['summary'],
                'category' => (string)($template['category'] ?? 'General'),
                'block_type' => (string)$template['block_type'],
                'preview_type' => (string)(($template['blocks'][0]['type'] ?? 'custom')),
                'preview_types' => array_values(array_slice(array_map(static fn(array $block): string => (string)($block['type'] ?? 'custom'), array_values(array_filter($template['blocks'] ?? [], 'is_array'))), 0, 4)),
                'installed_id' => (int)($installed[mb_strtolower((string)$template['name'])] ?? 0),
                'required_model_key' => $requiredModelKey,
                'required_model_name' => (string)($requires['label'] ?? ''),
                'required_model_installed' => $requiredModel !== null,
                'required_model_public' => $requiredModel ? (int)$requiredModel['is_public'] === 1 : false,
            ];
        }
        return $items;
    }

    public static function install(string $key, int $userId): int
    {
        $templates = self::templates();
        if (!isset($templates[$key])) throw new RuntimeException('Starter pattern not found.');
        if ($userId < 1) throw new RuntimeException('A signed-in editor is required.');

        $template = $templates[$key];
        foreach (PagePatterns::all() as $existing) {
            if (mb_strtolower((string)$existing['name']) === mb_strtolower((string)$template['name'])) {
                throw new RuntimeException((string)$template['name'] . ' is already in your pattern library.');
            }
        }

        $db = Database::connection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            $requires = is_array($template['requires'] ?? null) ? $template['requires'] : [];
            $requiredModelKey = (string)($requires['model_key'] ?? '');
            $requiredStarter = (string)($requires['starter_key'] ?? '');
            if ($requiredModelKey !== '' && ContentModels::findByKey($requiredModelKey) === null) {
                if ($requiredStarter === '') throw new RuntimeException('Install the required content model before adding this pattern.');
                ContentModelStarters::install($requiredStarter, $userId);
            }

            $json = json_encode($template['blocks'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($json)) throw new RuntimeException('Starter pattern could not be encoded.');
            $validated = PagePatterns::validate([
                'name' => (string)$template['name'],
                'mode' => 'regular',
                'page_blocks_json' => $json,
            ]);
            if ($validated['errors']) throw new RuntimeException(implode(' ', $validated['errors']));
            $id = PagePatterns::create($validated['data'], $userId);
            if ($ownsTransaction) $db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** @return array<string,array<string,mixed>> */
    private static function templates(): array
    {
        return [
            'hero-with-cta' => [
                'name' => 'Hero with CTA',
                'summary' => 'A focused introduction with supporting copy and two clear actions.',
                'category' => 'Hero',
                'block_type' => 'Hero',
                'blocks' => [[
                    'id' => 'starterhero01', 'type' => 'hero', 'enabled' => true,
                    'eyebrow' => 'Welcome',
                    'heading' => 'A clear headline that explains what matters',
                    'intro' => 'Use one or two short sentences to explain the value of this page and help visitors understand what to do next.',
                    'primary_enabled' => true, 'primary_label' => 'Primary action', 'primary_url' => '/',
                    'secondary_enabled' => true, 'secondary_label' => 'Learn more', 'secondary_url' => '/',
                    'image_path' => '', 'image_alt' => '',
                ]],
            ],
            'feature-highlights' => [
                'name' => 'Feature highlights',
                'summary' => 'Three concise benefits or values that are easy to scan.',
                'category' => 'Trust',
                'block_type' => 'Values',
                'blocks' => [[
                    'id' => 'startervalues01', 'type' => 'values', 'enabled' => true,
                    'items' => [
                        ['icon' => 'sparkles', 'title' => 'First benefit', 'body' => 'Explain the first important benefit in one short, useful sentence.'],
                        ['icon' => 'shield', 'title' => 'Second benefit', 'body' => 'Explain the second benefit with concrete language rather than marketing filler.'],
                        ['icon' => 'heart', 'title' => 'Third benefit', 'body' => 'Finish with another reason a visitor should trust or choose you.'],
                    ],
                ]],
            ],
            'testimonials' => [
                'name' => 'Testimonials section',
                'summary' => 'A clean three-quote social-proof section.',
                'category' => 'Trust',
                'block_type' => 'Testimonials',
                'blocks' => [[
                    'id' => 'startertest01', 'type' => 'testimonials', 'enabled' => true,
                    'eyebrow' => 'What people say', 'heading' => 'Trusted by people we work with',
                    'items' => [
                        ['quote' => 'Add a short, specific customer or client quote here.', 'name' => 'Customer name', 'role' => 'Role or company'],
                        ['quote' => 'Use testimonials that describe a real outcome or experience.', 'name' => 'Customer name', 'role' => 'Role or company'],
                        ['quote' => 'Keep each quote focused so the section remains easy to scan.', 'name' => 'Customer name', 'role' => 'Role or company'],
                    ],
                ]],
            ],
            'faq' => [
                'name' => 'FAQ section',
                'summary' => 'Four common questions with concise answers.',
                'category' => 'Information',
                'block_type' => 'FAQ',
                'blocks' => [[
                    'id' => 'starterfaq001', 'type' => 'faq', 'enabled' => true,
                    'eyebrow' => 'Questions', 'heading' => 'Frequently asked questions',
                    'items' => [
                        ['question' => 'What should visitors know first?', 'answer' => 'Answer the most common question clearly and directly.'],
                        ['question' => 'How does it work?', 'answer' => 'Describe the process in plain language and keep the answer focused.'],
                        ['question' => 'What is included?', 'answer' => 'Set expectations by explaining what is included and what is not.'],
                        ['question' => 'How can I get in touch?', 'answer' => 'Tell visitors the easiest way to contact you or take the next step.'],
                    ],
                ]],
            ],
            'key-statistics' => [
                'name' => 'Key statistics',
                'summary' => 'A compact trust strip for meaningful numbers and proof points.',
                'category' => 'Trust',
                'block_type' => 'Statistics',
                'blocks' => [[
                    'id' => 'starterstats01', 'type' => 'stats', 'enabled' => true,
                    'eyebrow' => 'At a glance', 'heading' => 'A few numbers that tell the story',
                    'items' => [
                        ['value' => '10+', 'label' => 'Years', 'body' => 'Replace with a meaningful measure.'],
                        ['value' => '250', 'label' => 'Projects', 'body' => 'Use real, verifiable numbers.'],
                        ['value' => '98%', 'label' => 'Positive', 'body' => 'Keep the supporting label short.'],
                    ],
                ]],
            ],
            'call-to-action' => [
                'name' => 'Call to action',
                'summary' => 'A simple closing section that gives visitors one obvious next step.',
                'category' => 'Conversion',
                'block_type' => 'CTA',
                'blocks' => [[
                    'id' => 'startercta001', 'type' => 'cta', 'enabled' => true,
                    'eyebrow' => 'Ready when you are',
                    'heading' => 'Give visitors a clear reason to take the next step',
                    'button_label' => 'Get started', 'button_url' => '/',
                ]],
            ],
            'latest-posts' => [
                'name' => 'Latest posts',
                'summary' => 'A reusable section that automatically surfaces recent blog content.',
                'category' => 'Publishing',
                'block_type' => 'Latest posts',
                'blocks' => [[
                    'id' => 'starterlatest1', 'type' => 'latest_posts', 'enabled' => true,
                    'eyebrow' => 'From the blog', 'heading' => 'Latest stories', 'view_label' => 'View all posts', 'count' => 3,
                ]],
            ],

            'services-from-content' => [
                'name' => 'Live services grid',
                'summary' => 'A live service grid that updates automatically when published Service entries change.',
                'category' => 'Connected content',
                'block_type' => 'Content collection',
                'requires' => ['starter_key' => 'service', 'model_key' => 'service', 'label' => 'Services'],
                'blocks' => [[
                    'id' => 'dynservices01', 'type' => 'collection', 'enabled' => true,
                    'model_key' => 'service', 'presentation' => 'cards',
                    'eyebrow' => 'What we do', 'heading' => 'Services built around what you need',
                    'view_label' => 'View all services', 'view_url' => '', 'count' => 6, 'sort' => 'title_asc', 'featured_only' => false,
                ]],
            ],
            'team-from-content' => [
                'name' => 'Live team grid',
                'summary' => 'A people grid backed by Team member entries, including roles, locations and featured images.',
                'category' => 'Connected content',
                'block_type' => 'Content collection',
                'requires' => ['starter_key' => 'team-member', 'model_key' => 'team_member', 'label' => 'Team members'],
                'blocks' => [[
                    'id' => 'dynteam00001', 'type' => 'collection', 'enabled' => true,
                    'model_key' => 'team_member', 'presentation' => 'people',
                    'eyebrow' => 'People', 'heading' => 'Meet the team',
                    'view_label' => 'Meet everyone', 'view_url' => '', 'count' => 8, 'sort' => 'title_asc', 'featured_only' => false,
                ]],
            ],
            'testimonials-from-content' => [
                'name' => 'Live testimonials',
                'summary' => 'Live social proof backed by reusable Testimonial entries instead of copied page text.',
                'category' => 'Connected content',
                'block_type' => 'Content collection',
                'requires' => ['starter_key' => 'testimonial', 'model_key' => 'testimonial', 'label' => 'Testimonials'],
                'blocks' => [[
                    'id' => 'dyntest00001', 'type' => 'collection', 'enabled' => true,
                    'model_key' => 'testimonial', 'presentation' => 'testimonials',
                    'eyebrow' => 'What people say', 'heading' => 'Trusted by people we work with',
                    'view_label' => '', 'view_url' => '', 'count' => 6, 'sort' => 'newest', 'featured_only' => false,
                ]],
            ],
            'faq-from-content' => [
                'name' => 'Live FAQ',
                'summary' => 'Reusable FAQ entries rendered as an accessible accordion and updated from one source.',
                'category' => 'Connected content',
                'block_type' => 'Content collection',
                'requires' => ['starter_key' => 'faq-item', 'model_key' => 'faq_item', 'label' => 'FAQ items'],
                'blocks' => [[
                    'id' => 'dynfaq000001', 'type' => 'collection', 'enabled' => true,
                    'model_key' => 'faq_item', 'presentation' => 'faq',
                    'eyebrow' => 'Questions', 'heading' => 'Frequently asked questions',
                    'view_label' => '', 'view_url' => '', 'count' => 8, 'sort' => 'title_asc', 'featured_only' => false,
                ]],
            ],
            'portfolio-from-content' => [
                'name' => 'Live portfolio',
                'summary' => 'Featured work cards backed by Portfolio entries, client metadata and reusable project imagery.',
                'category' => 'Connected content',
                'block_type' => 'Content collection',
                'requires' => ['starter_key' => 'portfolio-item', 'model_key' => 'portfolio_item', 'label' => 'Portfolio items'],
                'blocks' => [[
                    'id' => 'dynwork00001', 'type' => 'collection', 'enabled' => true,
                    'model_key' => 'portfolio_item', 'presentation' => 'cards',
                    'eyebrow' => 'Selected work', 'heading' => 'A few projects we are proud of',
                    'view_label' => 'View all work', 'view_url' => '', 'count' => 6, 'sort' => 'newest', 'featured_only' => false,
                ]],
            ],
            'pricing-from-content' => [
                'name' => 'Live pricing',
                'summary' => 'Reusable pricing plans rendered from structured price, period, features and CTA fields.',
                'category' => 'Connected content',
                'block_type' => 'Content collection',
                'requires' => ['starter_key' => 'pricing-plan', 'model_key' => 'pricing_plan', 'label' => 'Pricing plans'],
                'blocks' => [[
                    'id' => 'dynpricing01', 'type' => 'collection', 'enabled' => true,
                    'model_key' => 'pricing_plan', 'presentation' => 'pricing',
                    'eyebrow' => 'Pricing', 'heading' => 'Choose the option that fits',
                    'view_label' => '', 'view_url' => '', 'count' => 4, 'sort' => 'title_asc', 'featured_only' => false,
                ]],
            ],
            'events-from-content' => [
                'name' => 'Live events',
                'summary' => 'A live event section backed by Event entries, dates, venues and registration links.',
                'category' => 'Connected content',
                'block_type' => 'Content collection',
                'requires' => ['starter_key' => 'event', 'model_key' => 'event', 'label' => 'Events'],
                'blocks' => [[
                    'id' => 'dynevents001', 'type' => 'collection', 'enabled' => true,
                    'model_key' => 'event', 'presentation' => 'events',
                    'eyebrow' => 'Coming up', 'heading' => 'Events and important dates',
                    'view_label' => 'View all events', 'view_url' => '', 'count' => 6, 'sort' => 'newest', 'featured_only' => false,
                ]],
            ],
            'products-from-content' => [
                'name' => 'Live products',
                'summary' => 'A live catalogue grid backed by Product entries, pricing, availability and Media Library images.',
                'category' => 'Connected content',
                'block_type' => 'Content collection',
                'requires' => ['starter_key' => 'product', 'model_key' => 'product', 'label' => 'Products'],
                'blocks' => [[
                    'id' => 'dynproduct01', 'type' => 'collection', 'enabled' => true,
                    'model_key' => 'product', 'presentation' => 'cards',
                    'eyebrow' => 'Products', 'heading' => 'Explore the collection',
                    'view_label' => 'View all products', 'view_url' => '', 'count' => 8, 'sort' => 'title_asc', 'featured_only' => false,
                ]],
            ],
            'resources-from-content' => [
                'name' => 'Live resources',
                'summary' => 'Published guides, reports and downloads displayed from the Resource content model.',
                'category' => 'Connected content',
                'block_type' => 'Content collection',
                'requires' => ['starter_key' => 'resource', 'model_key' => 'resource', 'label' => 'Resources'],
                'blocks' => [[
                    'id' => 'dynresource1', 'type' => 'collection', 'enabled' => true,
                    'model_key' => 'resource', 'presentation' => 'resources',
                    'eyebrow' => 'Resources', 'heading' => 'Useful things to explore',
                    'view_label' => 'View all resources', 'view_url' => '', 'count' => 6, 'sort' => 'newest', 'featured_only' => false,
                ]],
            ],
            'locations-from-content' => [
                'name' => 'Live locations',
                'summary' => 'A live locations grid using structured address, city and contact information.',
                'category' => 'Connected content',
                'block_type' => 'Content collection',
                'requires' => ['starter_key' => 'location', 'model_key' => 'location', 'label' => 'Locations'],
                'blocks' => [[
                    'id' => 'dynlocation1', 'type' => 'collection', 'enabled' => true,
                    'model_key' => 'location', 'presentation' => 'cards',
                    'eyebrow' => 'Find us', 'heading' => 'Locations',
                    'view_label' => 'View all locations', 'view_url' => '', 'count' => 8, 'sort' => 'title_asc', 'featured_only' => false,
                ]],
            ],
            'jobs-from-content' => [
                'name' => 'Live job openings',
                'summary' => 'Current vacancies pulled from Job opening entries, with location, workplace and employment details.',
                'category' => 'Connected content',
                'block_type' => 'Content collection',
                'requires' => ['starter_key' => 'job-opening', 'model_key' => 'job_opening', 'label' => 'Job openings'],
                'blocks' => [[
                    'id' => 'dynjobs00001', 'type' => 'collection', 'enabled' => true,
                    'model_key' => 'job_opening', 'presentation' => 'resources',
                    'eyebrow' => 'Careers', 'heading' => 'Open opportunities',
                    'view_label' => 'View all openings', 'view_url' => '', 'count' => 8, 'sort' => 'newest', 'featured_only' => false,
                ]],
            ],
            'partners-from-content' => [
                'name' => 'Live partner wall',
                'summary' => 'A reusable trust section populated from Partner entries and their featured logos.',
                'category' => 'Connected content',
                'block_type' => 'Content collection',
                'requires' => ['starter_key' => 'partner', 'model_key' => 'partner', 'label' => 'Partners'],
                'blocks' => [[
                    'id' => 'dynpartners1', 'type' => 'collection', 'enabled' => true,
                    'model_key' => 'partner', 'presentation' => 'logos',
                    'eyebrow' => 'Trusted network', 'heading' => 'Partners and organisations we work with',
                    'view_label' => '', 'view_url' => '', 'count' => 12, 'sort' => 'title_asc', 'featured_only' => false,
                ]],
            ],
            'awards-from-content' => [
                'name' => 'Live awards & certifications',
                'summary' => 'Trust signals sourced from Award & certification entries instead of manually repeated page copy.',
                'category' => 'Connected content',
                'block_type' => 'Content collection',
                'requires' => ['starter_key' => 'award', 'model_key' => 'award_certification', 'label' => 'Awards & certifications'],
                'blocks' => [[
                    'id' => 'dynawards001', 'type' => 'collection', 'enabled' => true,
                    'model_key' => 'award_certification', 'presentation' => 'logos',
                    'eyebrow' => 'Recognition', 'heading' => 'Awards and certifications',
                    'view_label' => '', 'view_url' => '', 'count' => 12, 'sort' => 'newest', 'featured_only' => false,
                ]],
            ],
            'courses-from-content' => [
                'name' => 'Live courses & programmes',
                'summary' => 'Course cards backed by structured programme entries, including level, format, dates and registration.',
                'category' => 'Connected content',
                'block_type' => 'Content collection',
                'requires' => ['starter_key' => 'course', 'model_key' => 'course_programme', 'label' => 'Courses & programmes'],
                'blocks' => [[
                    'id' => 'dyncourses01', 'type' => 'collection', 'enabled' => true,
                    'model_key' => 'course_programme', 'presentation' => 'events',
                    'eyebrow' => 'Learn with us', 'heading' => 'Courses and programmes',
                    'view_label' => 'View all courses', 'view_url' => '', 'count' => 8, 'sort' => 'newest', 'featured_only' => false,
                ]],
            ],
            'press-from-content' => [
                'name' => 'Live press mentions',
                'summary' => 'Media coverage pulled from Press mention entries, keeping publication and article links in one place.',
                'category' => 'Connected content',
                'block_type' => 'Content collection',
                'requires' => ['starter_key' => 'press-mention', 'model_key' => 'press_mention', 'label' => 'Press mentions'],
                'blocks' => [[
                    'id' => 'dynpress0001', 'type' => 'collection', 'enabled' => true,
                    'model_key' => 'press_mention', 'presentation' => 'resources',
                    'eyebrow' => 'In the press', 'heading' => 'What others are saying',
                    'view_label' => '', 'view_url' => '', 'count' => 8, 'sort' => 'newest', 'featured_only' => false,
                ]],
            ],

            'about-story' => [
                'name' => 'About story',
                'summary' => 'A split-layout story section for your background, mission or founder introduction.',
                'category' => 'About',
                'block_type' => 'Custom',
                'blocks' => [[
                    'id' => 'starterabout1', 'type' => 'custom', 'enabled' => true,
                    'eyebrow' => 'Our story',
                    'heading' => 'Share the story behind what you do',
                    'body' => 'Explain where you started, what you believe in and why your work matters. Keep the story human, specific and easy to scan.',
                    'layout' => 'split-right', 'tone' => 'plain',
                    'primary_enabled' => false, 'primary_label' => '', 'primary_url' => '',
                    'secondary_enabled' => false, 'secondary_label' => '', 'secondary_url' => '',
                    'image_path' => '', 'image_alt' => '',
                ]],
            ],
            'services-grid' => [
                'name' => 'Services grid',
                'summary' => 'Three scannable service cards that work well on home and services pages.',
                'category' => 'Business',
                'block_type' => 'Cards',
                'blocks' => [[
                    'id' => 'startersrv001', 'type' => 'cards', 'enabled' => true,
                    'eyebrow' => 'What we do', 'heading' => 'Services built around what you need',
                    'view_label' => 'View all services', 'view_url' => '/services',
                    'items' => [
                        ['title' => 'Service one', 'meta' => 'Short category or benefit', 'url' => '/', 'image_path' => '', 'image_alt' => ''],
                        ['title' => 'Service two', 'meta' => 'Short category or benefit', 'url' => '/', 'image_path' => '', 'image_alt' => ''],
                        ['title' => 'Service three', 'meta' => 'Short category or benefit', 'url' => '/', 'image_path' => '', 'image_alt' => ''],
                    ],
                ]],
            ],
            'process-steps' => [
                'name' => 'Process steps',
                'summary' => 'A four-step “how it works” section for explaining a service or customer journey.',
                'category' => 'Information',
                'block_type' => 'Values',
                'blocks' => [[
                    'id' => 'starterstep01', 'type' => 'values', 'enabled' => true,
                    'items' => [
                        ['icon' => 'sparkles', 'title' => '1. Discover', 'body' => 'Start by understanding the goal, context and important constraints.'],
                        ['icon' => 'support', 'title' => '2. Plan', 'body' => 'Turn what you learned into a clear, practical plan everyone can understand.'],
                        ['icon' => 'star', 'title' => '3. Deliver', 'body' => 'Do the work with clear communication and attention to the details that matter.'],
                        ['icon' => 'heart', 'title' => '4. Support', 'body' => 'Follow through, measure the outcome and make the next step easy.'],
                    ],
                ]],
            ],
            'team-grid' => [
                'name' => 'Team grid',
                'summary' => 'A simple people grid for introducing a team, leadership group or contributors.',
                'category' => 'People',
                'block_type' => 'Cards',
                'blocks' => [[
                    'id' => 'starterteam01', 'type' => 'cards', 'enabled' => true,
                    'eyebrow' => 'People', 'heading' => 'Meet the team', 'view_label' => '', 'view_url' => '',
                    'items' => [
                        ['title' => 'Person one', 'meta' => 'Role or speciality', 'url' => '/', 'image_path' => '', 'image_alt' => ''],
                        ['title' => 'Person two', 'meta' => 'Role or speciality', 'url' => '/', 'image_path' => '', 'image_alt' => ''],
                        ['title' => 'Person three', 'meta' => 'Role or speciality', 'url' => '/', 'image_path' => '', 'image_alt' => ''],
                    ],
                ]],
            ],
            'featured-work' => [
                'name' => 'Featured work',
                'summary' => 'A portfolio-style card section for projects, case studies or selected work.',
                'category' => 'Portfolio',
                'block_type' => 'Cards',
                'blocks' => [[
                    'id' => 'starterwork01', 'type' => 'cards', 'enabled' => true,
                    'eyebrow' => 'Selected work', 'heading' => 'A few projects we are proud of',
                    'view_label' => 'View all work', 'view_url' => '/work',
                    'items' => [
                        ['title' => 'Project one', 'meta' => 'Category · 2026', 'url' => '/', 'image_path' => '', 'image_alt' => ''],
                        ['title' => 'Project two', 'meta' => 'Category · 2026', 'url' => '/', 'image_path' => '', 'image_alt' => ''],
                        ['title' => 'Project three', 'meta' => 'Category · 2026', 'url' => '/', 'image_path' => '', 'image_alt' => ''],
                    ],
                ]],
            ],
            'pricing-overview' => [
                'name' => 'Pricing overview',
                'summary' => 'Three pricing or membership cards with a clear highlighted middle option.',
                'category' => 'Conversion',
                'block_type' => 'Cards',
                'blocks' => [[
                    'id' => 'starterprice1', 'type' => 'cards', 'enabled' => true,
                    'eyebrow' => 'Pricing', 'heading' => 'Choose the option that fits', 'view_label' => '', 'view_url' => '',
                    'items' => [
                        ['title' => 'Essential', 'meta' => '€19 / month', 'url' => '/contact', 'image_path' => '', 'image_alt' => ''],
                        ['title' => 'Professional', 'meta' => '€49 / month · Most popular', 'url' => '/contact', 'image_path' => '', 'image_alt' => ''],
                        ['title' => 'Business', 'meta' => 'Talk to us', 'url' => '/contact', 'image_path' => '', 'image_alt' => ''],
                    ],
                ]],
            ],
            'image-gallery' => [
                'name' => 'Image gallery',
                'summary' => 'A six-slot gallery ready for photography, products, locations or project imagery.',
                'category' => 'Media',
                'block_type' => 'Gallery',
                'blocks' => [[
                    'id' => 'startergal001', 'type' => 'gallery', 'enabled' => true,
                    'eyebrow' => 'Gallery', 'heading' => 'A closer look', 'layout' => 'grid',
                    'items' => [
                        ['caption' => '', 'image_path' => '', 'image_alt' => ''],
                        ['caption' => '', 'image_path' => '', 'image_alt' => ''],
                        ['caption' => '', 'image_path' => '', 'image_alt' => ''],
                        ['caption' => '', 'image_path' => '', 'image_alt' => ''],
                        ['caption' => '', 'image_path' => '', 'image_alt' => ''],
                        ['caption' => '', 'image_path' => '', 'image_alt' => ''],
                    ],
                ]],
            ],
            'contact-section' => [
                'name' => 'Contact section',
                'summary' => 'A friendly contact prompt with space for key context and one strong action.',
                'category' => 'Conversion',
                'block_type' => 'Custom',
                'blocks' => [[
                    'id' => 'startercontact', 'type' => 'custom', 'enabled' => true,
                    'eyebrow' => 'Get in touch', 'heading' => 'Have a question or a project in mind?',
                    'body' => 'Tell visitors when they should contact you, what information is useful to include and when they can expect a reply.',
                    'layout' => 'centered', 'tone' => 'soft',
                    'primary_enabled' => true, 'primary_label' => 'Contact us', 'primary_url' => '/contact',
                    'secondary_enabled' => false, 'secondary_label' => '', 'secondary_url' => '',
                    'image_path' => '', 'image_alt' => '',
                ]],
            ],
            'trust-guarantees' => [
                'name' => 'Trust & guarantees',
                'summary' => 'Four reassuring proof points for standards, privacy, support or guarantees.',
                'category' => 'Trust',
                'block_type' => 'Values',
                'blocks' => [[
                    'id' => 'startertrust1', 'type' => 'values', 'enabled' => true,
                    'items' => [
                        ['icon' => 'shield', 'title' => 'Clear standards', 'body' => 'Explain a concrete standard, certification or quality promise.'],
                        ['icon' => 'heart', 'title' => 'People first', 'body' => 'Describe how customers, members or clients are supported.'],
                        ['icon' => 'clock', 'title' => 'Reliable', 'body' => 'Set a clear expectation around timing, availability or delivery.'],
                        ['icon' => 'support', 'title' => 'Here to help', 'body' => 'Explain what happens when someone needs assistance after the first step.'],
                    ],
                ]],
            ],
            'landing-page-essentials' => [
                'name' => 'Landing page essentials',
                'summary' => 'A ready-to-edit four-block landing-page flow: hero, benefits, proof and final CTA.',
                'category' => 'Page starter',
                'block_type' => '4 blocks',
                'blocks' => [
                    [
                        'id' => 'starterland01', 'type' => 'hero', 'enabled' => true,
                        'eyebrow' => 'A better way', 'heading' => 'Lead with the outcome your visitor cares about',
                        'intro' => 'Explain the offer in plain language, then give visitors one obvious next step.',
                        'primary_enabled' => true, 'primary_label' => 'Get started', 'primary_url' => '/contact',
                        'secondary_enabled' => true, 'secondary_label' => 'Learn more', 'secondary_url' => '/',
                        'image_path' => '', 'image_alt' => '',
                    ],
                    [
                        'id' => 'starterland02', 'type' => 'values', 'enabled' => true,
                        'items' => [
                            ['icon' => 'sparkles', 'title' => 'Clear benefit', 'body' => 'Explain the first meaningful outcome in one concise sentence.'],
                            ['icon' => 'shield', 'title' => 'Low risk', 'body' => 'Reduce uncertainty with a concrete trust or reassurance point.'],
                            ['icon' => 'heart', 'title' => 'Human support', 'body' => 'Tell visitors what kind of help or experience they can expect.'],
                        ],
                    ],
                    [
                        'id' => 'starterland03', 'type' => 'testimonials', 'enabled' => true,
                        'eyebrow' => 'Proof', 'heading' => 'What people say',
                        'items' => [
                            ['quote' => 'Replace this with a short quote that describes a real outcome.', 'name' => 'Customer name', 'role' => 'Role or company'],
                            ['quote' => 'Use specific, believable language rather than generic praise.', 'name' => 'Customer name', 'role' => 'Role or company'],
                        ],
                    ],
                    [
                        'id' => 'starterland04', 'type' => 'cta', 'enabled' => true,
                        'eyebrow' => 'Next step', 'heading' => 'Ready to move forward?',
                        'button_label' => 'Get started', 'button_url' => '/contact',
                    ],
                ],
            ],
        ];
    }

    private function __construct() {}
}
