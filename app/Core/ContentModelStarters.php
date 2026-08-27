<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

/**
 * Curated starter schemas for common real-world content.
 *
 * Starters are intentionally optional: they never appear under Content until
 * an administrator explicitly installs one. This keeps existing sites clean
 * while giving new sites a fast, editor-friendly starting point.
 */
final class ContentModelStarters
{
    /** @return list<array<string,mixed>> */
    public static function catalog(?array $existingModels = null): array
    {
        $installed = [];
        foreach ($existingModels ?? ContentModels::all() as $model) {
            $installed[mb_strtolower((string)$model['model_key'])] = (int)$model['id'];
        }

        $items = [];
        foreach (self::templates() as $key => $template) {
            $modelKey = (string)$template['model']['model_key'];
            $items[] = [
                'key' => $key,
                'name' => (string)$template['name'],
                'summary' => (string)$template['summary'],
                'category' => (string)($template['category'] ?? 'General'),
                'icon' => (string)$template['model']['icon'],
                'field_count' => count($template['fields']),
                'installed_id' => (int)($installed[mb_strtolower($modelKey)] ?? 0),
            ];
        }
        return $items;
    }

    public static function modelKeyForStarter(string $key): string
    {
        $templates = self::templates();
        return isset($templates[$key]) ? (string)$templates[$key]['model']['model_key'] : '';
    }

    public static function nameForStarter(string $key): string
    {
        $templates = self::templates();
        return isset($templates[$key]) ? (string)$templates[$key]['name'] : '';
    }

    public static function install(string $key, int $userId): int
    {
        $templates = self::templates();
        if (!isset($templates[$key])) throw new RuntimeException('Starter content model not found.');
        if ($userId < 1) throw new RuntimeException('A signed-in administrator is required.');

        $template = $templates[$key];
        $modelKey = (string)$template['model']['model_key'];
        $existing = ContentModels::findByKey($modelKey);
        if ($existing) {
            throw new RuntimeException((string)$template['name'] . ' is already installed.');
        }

        $db = Database::connection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();

        try {
            $validatedModel = ContentModels::validateModel($template['model']);
            if ($validatedModel['errors']) {
                throw new RuntimeException(implode(' ', $validatedModel['errors']));
            }

            $modelId = ContentModels::createModel($validatedModel['data'], $userId);
            foreach ($template['fields'] as $field) {
                $validatedField = ContentModels::validateField($field, $modelId);
                if ($validatedField['errors']) {
                    throw new RuntimeException(
                        'Could not add the starter field ' . (string)($field['label'] ?? '') . ': ' . implode(' ', $validatedField['errors'])
                    );
                }
                ContentModels::saveField($modelId, $validatedField['data']);
            }

            if ($ownsTransaction) $db->commit();
            return $modelId;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** @return array<string,array<string,mixed>> */
    private static function templates(): array
    {
        return [
            'team-member' => [
                'name' => 'Team members',
                'summary' => 'People, roles, biographies and contact details for an About or Team section.',
                'category' => 'People',
                'model' => self::publicModel('Team member', 'Team members', 'team_member', 'team', 'person', 'People who are part of your organisation, studio or project.'),
                'fields' => [
                    self::field('Role / title', 'role', 'text', 10, true, ['searchable' => '1', 'max_length' => '160'], 'Designer, Founder, Veterinarian...'),
                    self::field('Short introduction', 'short_intro', 'textarea', 20, false, ['searchable' => '1', 'max_length' => '420'], 'A short description for cards and listings.'),
                    self::field('Biography', 'biography', 'rich_text', 30),
                    self::field('Email', 'email', 'email', 40),
                    self::field('Website', 'website', 'url', 50),
                    self::field('Location', 'location', 'text', 60, false, ['searchable' => '1', 'max_length' => '160']),
                ],
            ],
            'testimonial' => [
                'name' => 'Testimonials',
                'summary' => 'Reusable quotes and attribution without creating unnecessary public URLs.',
                'category' => 'Marketing',
                'model' => self::embeddedModel('Testimonial', 'Testimonials', 'testimonial', 'testimonials', 'quote', 'Customer, client or community testimonials that can be reused around the site.', true),
                'fields' => [
                    self::field('Quote', 'quote', 'textarea', 10, true, ['max_length' => '1200']),
                    self::field('Person', 'person', 'text', 20, true, ['searchable' => '1', 'max_length' => '160']),
                    self::field('Role / company', 'role_company', 'text', 30, false, ['max_length' => '180']),
                    self::field('Rating', 'rating', 'number', 40, false, ['min' => '1', 'max' => '5', 'step' => '1']),
                    self::field('Source URL', 'source_url', 'url', 50),
                    self::field('Featured', 'featured', 'boolean', 60),
                ],
            ],
            'event' => [
                'name' => 'Events',
                'summary' => 'Public events with dates, venues, registration links and rich event details.',
                'category' => 'Events',
                'model' => self::publicModel('Event', 'Events', 'event', 'events', 'calendar', 'Events, meetups, shows, launches or other date-based public content.', true),
                'fields' => [
                    self::field('Starts', 'starts_at', 'datetime', 10, true),
                    self::field('Ends', 'ends_at', 'datetime', 20),
                    self::field('Venue', 'venue', 'text', 30, false, ['searchable' => '1', 'max_length' => '180']),
                    self::field('Location', 'location', 'text', 40, false, ['searchable' => '1', 'max_length' => '220']),
                    self::field('Registration / ticket URL', 'registration_url', 'url', 50),
                    self::field('Summary', 'summary', 'textarea', 60, false, ['searchable' => '1', 'max_length' => '500']),
                    self::field('Details', 'details', 'rich_text', 70),
                ],
            ],
            'portfolio-item' => [
                'name' => 'Portfolio items',
                'summary' => 'Projects and case studies with services, gallery media and project outcomes.',
                'category' => 'Portfolio',
                'model' => self::publicModel('Portfolio item', 'Portfolio items', 'portfolio_item', 'work', 'portfolio', 'Projects, work samples and case studies for a portfolio or agency site.'),
                'fields' => [
                    self::field('Client / organisation', 'client', 'text', 10, false, ['searchable' => '1', 'max_length' => '180']),
                    self::field('Project date', 'project_date', 'date', 20),
                    self::field('Services', 'services', 'multiselect', 30, false, ['options' => "Strategy\nDesign\nDevelopment\nContent\nMarketing\nPhotography\nOther"]),
                    self::field('Project URL', 'project_url', 'url', 40),
                    self::field('Gallery', 'gallery', 'gallery', 50),
                    self::field('Summary', 'summary', 'textarea', 60, false, ['searchable' => '1', 'max_length' => '500']),
                    self::field('Case study', 'case_study', 'rich_text', 70),
                ],
            ],
            'location' => [
                'name' => 'Locations',
                'summary' => 'Addresses, contact information, opening details and location pages.',
                'category' => 'Business',
                'model' => self::publicModel('Location', 'Locations', 'location', 'locations', 'pin', 'Physical offices, stores, venues, branches or other places.'),
                'fields' => [
                    self::field('Street address', 'street_address', 'text', 10, true, ['searchable' => '1', 'max_length' => '220']),
                    self::field('City', 'city', 'text', 20, true, ['searchable' => '1', 'max_length' => '120']),
                    self::field('Region / state', 'region', 'text', 30, false, ['searchable' => '1', 'max_length' => '120']),
                    self::field('Postal code', 'postal_code', 'text', 40, false, ['max_length' => '40']),
                    self::field('Country', 'country', 'text', 50, false, ['searchable' => '1', 'max_length' => '120']),
                    self::field('Phone', 'phone', 'text', 60, false, ['max_length' => '80']),
                    self::field('Email', 'email', 'email', 70),
                    self::field('Map URL', 'map_url', 'url', 80),
                    self::field('Opening hours', 'opening_hours', 'textarea', 90, false, ['max_length' => '1000']),
                    self::field('Description', 'description_text', 'rich_text', 100),
                ],
            ],
            'product' => [
                'name' => 'Products',
                'summary' => 'A flexible product catalogue without forcing commerce or checkout features.',
                'category' => 'Catalogue',
                'model' => self::publicModel('Product', 'Products', 'product', 'products', 'product', 'Products or services presented as structured catalogue entries.'),
                'fields' => [
                    self::field('SKU / reference', 'sku', 'text', 10, false, ['unique' => '1', 'searchable' => '1', 'max_length' => '120']),
                    self::field('Price', 'price', 'number', 20, false, ['min' => '0', 'step' => '0.01']),
                    self::field('Currency', 'currency', 'select', 30, false, ['options' => "EUR\nUSD\nGBP\nCHF\nCAD\nAUD", 'default_value' => 'EUR']),
                    self::field('Availability', 'availability', 'select', 40, false, ['options' => "Available\nOut of stock\nComing soon\nDiscontinued", 'default_value' => 'Available']),
                    self::field('Short description', 'short_description', 'textarea', 50, false, ['searchable' => '1', 'max_length' => '500']),
                    self::field('Description', 'description_text', 'rich_text', 60),
                    self::field('Gallery', 'gallery', 'gallery', 70),
                ],
            ],
            'service' => [
                'name' => 'Services',
                'summary' => 'Public service pages with concise summaries, detail, pricing hints and clear next steps.',
                'category' => 'Business',
                'model' => self::publicModel('Service', 'Services', 'service', 'services', 'collection', 'Services, capabilities or offers that your organisation provides.'),
                'fields' => [
                    self::field('Short description', 'short_description', 'textarea', 10, true, ['searchable' => '1', 'max_length' => '500']),
                    self::field('Full description', 'full_description', 'rich_text', 20),
                    self::field('Starting price', 'starting_price', 'number', 30, false, ['min' => '0', 'step' => '0.01']),
                    self::field('Currency', 'currency', 'select', 40, false, ['options' => "EUR\nUSD\nGBP\nCHF\nCAD\nAUD", 'default_value' => 'EUR']),
                    self::field('CTA label', 'cta_label', 'text', 50, false, ['max_length' => '80'], 'Book a consultation'),
                    self::field('CTA URL', 'cta_url', 'url', 60),
                    self::field('Featured', 'featured', 'boolean', 70),
                ],
            ],
            'job-opening' => [
                'name' => 'Job openings',
                'summary' => 'Vacancies with location, workplace type, employment details and an application link.',
                'category' => 'People',
                'model' => self::publicModel('Job opening', 'Job openings', 'job_opening', 'jobs', 'person', 'Open roles and career opportunities published on your website.', true),
                'fields' => [
                    self::field('Department', 'department', 'text', 10, false, ['searchable' => '1', 'max_length' => '140']),
                    self::field('Location', 'location', 'text', 20, false, ['searchable' => '1', 'max_length' => '180']),
                    self::field('Workplace', 'workplace', 'select', 30, false, ['options' => "On-site\nHybrid\nRemote"]),
                    self::field('Employment type', 'employment_type', 'select', 40, false, ['options' => "Full-time\nPart-time\nContract\nTemporary\nInternship"]),
                    self::field('Closes on', 'closes_on', 'date', 50),
                    self::field('Application URL', 'application_url', 'url', 60),
                    self::field('Summary', 'summary', 'textarea', 70, true, ['searchable' => '1', 'max_length' => '600']),
                    self::field('Role description', 'role_description', 'rich_text', 80),
                ],
            ],
            'resource' => [
                'name' => 'Resources',
                'summary' => 'Guides, reports, downloads and useful links with structured metadata.',
                'category' => 'Publishing',
                'model' => self::publicModel('Resource', 'Resources', 'resource', 'resources', 'collection', 'Guides, documents, reports and other resources visitors can browse or download.'),
                'fields' => [
                    self::field('Resource type', 'resource_type', 'select', 10, true, ['options' => "Guide\nReport\nWhite paper\nChecklist\nTemplate\nVideo\nExternal link\nOther"]),
                    self::field('Summary', 'summary', 'textarea', 20, true, ['searchable' => '1', 'max_length' => '600']),
                    self::field('Document / file', 'document', 'media', 30),
                    self::field('External URL', 'external_url', 'url', 40),
                    self::field('Published date', 'resource_date', 'date', 50),
                    self::field('Author / organisation', 'author_name', 'text', 60, false, ['searchable' => '1', 'max_length' => '160']),
                    self::field('Description', 'description_text', 'rich_text', 70),
                ],
            ],
            'partner' => [
                'name' => 'Partners',
                'summary' => 'Reusable partner, sponsor or client records with logo, website and attribution details.',
                'category' => 'Business',
                'model' => self::embeddedModel('Partner', 'Partners', 'partner', 'partners', 'heart', 'Partners, sponsors, clients or supporting organisations reused across the site.', true),
                'fields' => [
                    self::field('Partner type', 'partner_type', 'select', 10, false, ['options' => "Partner\nSponsor\nClient\nSupplier\nMember\nOther"]),
                    self::field('Website', 'website', 'url', 20),
                    self::field('Short description', 'short_description', 'textarea', 30, false, ['searchable' => '1', 'max_length' => '500']),
                    self::field('Since', 'partner_since', 'date', 40),
                    self::field('Featured', 'featured', 'boolean', 50),
                ],
            ],
            'award' => [
                'name' => 'Awards & certifications',
                'summary' => 'Reusable awards, certificates, accreditations and verification information.',
                'category' => 'Trust',
                'model' => self::embeddedModel('Award or certification', 'Awards & certifications', 'award_certification', 'awards', 'star', 'Awards, certificates and accreditations that provide reusable trust signals.', true),
                'fields' => [
                    self::field('Issuer', 'issuer', 'text', 10, true, ['searchable' => '1', 'max_length' => '180']),
                    self::field('Date awarded', 'awarded_on', 'date', 20),
                    self::field('Expires on', 'expires_on', 'date', 30),
                    self::field('Credential / certificate', 'credential', 'media', 40),
                    self::field('Verification URL', 'verification_url', 'url', 50),
                    self::field('Description', 'description_text', 'textarea', 60, false, ['max_length' => '800']),
                ],
            ],
            'faq-item' => [
                'name' => 'FAQ items',
                'summary' => 'Reusable questions and answers that can later be related to pages, products or services.',
                'category' => 'Publishing',
                'model' => self::embeddedModel('FAQ item', 'FAQ items', 'faq_item', 'faq-items', 'collection', 'Reusable frequently asked questions kept separate from page-specific FAQ blocks.'),
                'fields' => [
                    self::field('Answer', 'answer', 'rich_text', 10, true),
                    self::field('Category', 'category', 'text', 20, false, ['searchable' => '1', 'max_length' => '120']),
                    self::field('Short answer', 'short_answer', 'textarea', 30, false, ['searchable' => '1', 'max_length' => '500']),
                    self::field('Featured', 'featured', 'boolean', 40),
                ],
            ],
            'pricing-plan' => [
                'name' => 'Pricing plans',
                'summary' => 'Reusable plan names, prices, billing periods, feature copy and conversion actions.',
                'category' => 'Marketing',
                'model' => self::embeddedModel('Pricing plan', 'Pricing plans', 'pricing_plan', 'pricing-plans', 'product', 'Reusable pricing or membership plans for landing pages and comparison sections.'),
                'fields' => [
                    self::field('Price', 'price', 'number', 10, true, ['min' => '0', 'step' => '0.01']),
                    self::field('Currency', 'currency', 'select', 20, false, ['options' => "EUR\nUSD\nGBP\nCHF\nCAD\nAUD", 'default_value' => 'EUR']),
                    self::field('Billing period', 'billing_period', 'select', 30, false, ['options' => "One-time\nMonthly\nQuarterly\nYearly\nCustom"]),
                    self::field('Summary', 'summary', 'textarea', 40, false, ['max_length' => '400']),
                    self::field('Features', 'features', 'rich_text', 50),
                    self::field('CTA label', 'cta_label', 'text', 60, false, ['max_length' => '80'], 'Choose plan'),
                    self::field('CTA URL', 'cta_url', 'url', 70),
                    self::field('Highlight this plan', 'highlighted', 'boolean', 80),
                ],
            ],
            'course' => [
                'name' => 'Courses & programmes',
                'summary' => 'Training, classes and programmes with level, format, dates, pricing and registration.',
                'category' => 'Education',
                'model' => self::publicModel('Course or programme', 'Courses & programmes', 'course_programme', 'courses', 'calendar', 'Courses, workshops, classes and structured programmes offered by your organisation.', true),
                'fields' => [
                    self::field('Level', 'level', 'select', 10, false, ['options' => "Beginner\nIntermediate\nAdvanced\nAll levels"]),
                    self::field('Format', 'format', 'select', 20, false, ['options' => "In person\nOnline\nHybrid\nSelf-paced"]),
                    self::field('Duration', 'duration', 'text', 30, false, ['max_length' => '120'], '6 weeks, 2 days, self-paced...'),
                    self::field('Starts on', 'starts_on', 'date', 40),
                    self::field('Price', 'price', 'number', 50, false, ['min' => '0', 'step' => '0.01']),
                    self::field('Currency', 'currency', 'select', 60, false, ['options' => "EUR\nUSD\nGBP\nCHF\nCAD\nAUD", 'default_value' => 'EUR']),
                    self::field('Registration URL', 'registration_url', 'url', 70),
                    self::field('Summary', 'summary', 'textarea', 80, true, ['searchable' => '1', 'max_length' => '600']),
                    self::field('Programme details', 'programme_details', 'rich_text', 90),
                ],
            ],
            'press-mention' => [
                'name' => 'Press mentions',
                'summary' => 'Articles, reviews and media coverage that can be reused as proof across the site.',
                'category' => 'Marketing',
                'model' => self::embeddedModel('Press mention', 'Press mentions', 'press_mention', 'press', 'quote', 'External press coverage, reviews, interviews and media mentions.'),
                'fields' => [
                    self::field('Publication', 'publication', 'text', 10, true, ['searchable' => '1', 'max_length' => '180']),
                    self::field('Published date', 'published_on', 'date', 20),
                    self::field('Article URL', 'article_url', 'url', 30, true),
                    self::field('Author', 'author_name', 'text', 40, false, ['max_length' => '160']),
                    self::field('Excerpt', 'excerpt', 'textarea', 50, false, ['max_length' => '700']),
                    self::field('Featured', 'featured', 'boolean', 60),
                ],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private static function publicModel(string $singular, string $plural, string $key, string $slug, string $icon, string $description, bool $scheduling = false): array
    {
        $model = [
            'singular_name' => $singular,
            'plural_name' => $plural,
            'model_key' => $key,
            'slug' => $slug,
            'icon' => $icon,
            'description' => $description,
            'status' => 'active',
            'is_public' => '1',
            'has_archive' => '1',
            'has_urls' => '1',
            'searchable' => '1',
            'sitemap_enabled' => '1',
            'enable_revisions' => '1',
            'enable_autosave' => '1',
            'enable_trash' => '1',
            'enable_seo' => '1',
            'enable_featured_image' => '1',
        ];
        if ($scheduling) $model['enable_scheduling'] = '1';
        return $model;
    }

    /**
     * Publicly embeddable structured content without automatic archive/detail URLs.
     * Ideal for testimonials, FAQ items, partners and pricing plans that are
     * displayed through Page Builder collections rather than standalone pages.
     *
     * @return array<string,mixed>
     */
    private static function embeddedModel(string $singular, string $plural, string $key, string $slug, string $icon, string $description, bool $featuredImage = false): array
    {
        $model = [
            'singular_name' => $singular,
            'plural_name' => $plural,
            'model_key' => $key,
            'slug' => $slug,
            'icon' => $icon,
            'description' => $description,
            'status' => 'active',
            'is_public' => '1',
            'enable_revisions' => '1',
            'enable_autosave' => '1',
            'enable_trash' => '1',
        ];
        if ($featuredImage) $model['enable_featured_image'] = '1';
        return $model;
    }

    /** @return array<string,mixed> */
    private static function privateModel(string $singular, string $plural, string $key, string $slug, string $icon, string $description, bool $featuredImage = false): array
    {
        $model = [
            'singular_name' => $singular,
            'plural_name' => $plural,
            'model_key' => $key,
            'slug' => $slug,
            'icon' => $icon,
            'description' => $description,
            'status' => 'active',
            'enable_revisions' => '1',
            'enable_autosave' => '1',
            'enable_trash' => '1',
        ];
        if ($featuredImage) $model['enable_featured_image'] = '1';
        return $model;
    }

    /** @return array<string,mixed> */
    private static function field(string $label, string $key, string $type, int $sort, bool $required = false, array $settings = [], string $placeholder = ''): array
    {
        $field = [
            'label' => $label,
            'field_key' => $key,
            'field_type' => $type,
            'sort_order' => $sort,
            'help_text' => '',
            'placeholder' => $placeholder,
        ];
        if ($required) $field['is_required'] = '1';
        foreach ($settings as $name => $value) $field[$name] = $value;
        return $field;
    }

    private function __construct() {}
}
