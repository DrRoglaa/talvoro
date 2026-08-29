<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

/**
 * Opt-in seed for Talvoro's own public product website.
 *
 * This is intentionally NOT called by the installer or updater. Talvoro is a
 * self-hosted CMS and customer websites must never be converted into Talvoro's
 * marketing site during an upgrade. The development/official Talvoro website
 * applies this preset explicitly through bin/apply-talvoro-product-site.php.
 */
final class PublicSitePreset
{
    private const APPLIED_KEY = 'redesign.product_site_preset';
    private const APPLIED_VALUE = 'talvoro-editorial-v1';
    private const GITHUB_URL = 'https://github.com/DrRoglaa/talvoro';

    /** @return array{status:string,pages_created:int,home_replaced:bool,menus_created:int,seo_seeded:int,message:string} */
    public static function apply(?int $actorId = null, bool $force = false): array
    {
        if (Settings::get(self::APPLIED_KEY, '') === self::APPLIED_VALUE) {
            return self::result('skip', 0, false, 0, 0, 'Talvoro product-site preset is already applied.');
        }

        $actorId = $actorId && $actorId > 0 ? $actorId : self::resolveActorId();
        if ($actorId < 1) {
            return self::result('deferred', 0, false, 0, 0, 'No active administrator account is available yet.');
        }

        if (!$force && !self::looksLikeTalvoroStarter()) {
            return self::result(
                'protected',
                0,
                false,
                0,
                0,
                'The current site looks customized, so Talvoro did not replace its public content. Use --force only for the dedicated Talvoro product website.'
            );
        }

        $db = Database::connection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();

        try {
            Settings::set('branding.site_name', 'Talvoro', $actorId);
            Settings::set('branding.tagline', 'Self-hosted publishing. Beautifully yours.', $actorId);

            $homeId = Pages::ensureHomePage($actorId);
            if ($homeId < 1) throw new RuntimeException('Talvoro could not resolve the Home page.');

            $home = self::homeDefinition();
            self::replaceHome($homeId, $home);
            $seoSeeded = self::seedSeo('/', $home, $actorId) ? 1 : 0;

            $pagesCreated = 0;
            foreach (self::pageDefinitions() as $path => $definition) {
                if (!self::pageExists($path)) {
                    self::createPage($path, $definition, $actorId);
                    $pagesCreated++;
                }
                if (self::seedSeo($path, $definition, $actorId)) $seoSeeded++;
            }

            $menusCreated = self::seedPrimaryMenu($actorId) + self::seedFooterMenu($actorId);
            Settings::set(self::APPLIED_KEY, self::APPLIED_VALUE, $actorId);

            if ($ownsTransaction) $db->commit();
            return self::result('applied', $pagesCreated, true, $menusCreated, $seoSeeded, 'Talvoro Editorial product site applied.');
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    private static function homeDefinition(): array
    {
        $path = '/';
        return self::definition(
            'Talvoro',
            'Write beautifully. Own completely.',
            'Self-hosted publishing for creators, indie developers, freelancers and small businesses who want a polished website without giving away control.',
            [
                self::hero($path, 1, 'Independent publishing', 'Write beautifully. *Own completely.*', 'Talvoro gives you a calm publishing workspace, a flexible visual system and the freedom to run your site on infrastructure you control.', 'Explore Talvoro', '/product', 'Get Talvoro', '/self-hosting', 'minimal', 'wide', 'spacious', 'center'),
                self::values($path, 2, [
                    ['shield', 'Self-hosted', 'Run Talvoro on infrastructure you choose. Your website is not dependent on a mandatory SaaS account.'],
                    ['leaf', 'Privacy-first', 'No third-party tracker or advertising network is required for the core product to work.'],
                    ['home', 'Your data', 'Keep your database, uploads, content and backups under your own operational control.'],
                    ['sparkles', 'No lock-in', 'Export, back up and move your website without a remote entitlement disabling what you already run.'],
                ], 'wide', 'compact'),
                self::custom($path, 3, 'Publishing', 'A workspace designed for the work itself.', 'Write, organize media, preview, schedule and publish without fighting the interface. Talvoro keeps common actions close and advanced controls contextual.', 'product-ui', 'wide', 'spacious'),
                self::custom($path, 4, 'Design freedom', 'Shape the site without rebuilding it.', 'Compose pages with semantic sections, reusable patterns, navigation and theme tokens. Change the visual system globally while your content stays structured.', 'theme-showcase', 'wide', 'spacious'),
                self::custom($path, 5, 'Ownership', 'Your domain. Your database. Your media.', 'Talvoro is designed around a simple principle: the website should remain yours operationally as well as creatively.', 'ownership', 'wide', 'spacious'),
                self::custom($path, 6, 'Power when needed', 'Start simple. Go deep when the site demands it.', 'SEO controls, revisions, redirects, analytics, forms, backups, security and structured content are available without turning the first-use experience into an enterprise control panel.', 'capabilities', 'wide', 'spacious'),
                self::cards($path, 7, 'Built for independent work', 'One platform, different kinds of ownership.', [
                    ['Creators', 'Publish essays, projects and ideas without building your workflow around an ad platform.', '/product'],
                    ['Indie developers', 'Ship product pages, changelogs, documentation and structured content from one self-hosted system.', '/product'],
                    ['Freelancers', 'Present work, services and contact paths with a site you can hand over, move or extend.', '/product'],
                    ['Small businesses', 'Keep publishing approachable while retaining backups, security, SEO and operational control.', '/product'],
                ], 'audiences', 'wide', 'spacious'),
                self::custom($path, 8, 'Talvoro Editorial', 'A polished default that does not feel generic.', 'Warm surfaces, restrained coral actions, clean system typography and editorial spacing create a professional starting point without locking your content to one look.', 'theme-showcase', 'wide', 'spacious'),
                self::stats($path, 9, 'Open and independent', 'A deliberately straightforward stack.', [
                    ['PHP 8.5', 'Application', 'Server-rendered and dependency-conscious.'],
                    ['MySQL', 'Data', 'Portable relational storage you control.'],
                    ['Vanilla JS', 'Interaction', 'Progressive behavior without a frontend framework runtime.'],
                    ['Self-hosted', 'Ownership', 'Docker or traditional PHP hosting.'],
                ], 'inline', 'wide', 'normal'),
                self::latestPosts($path, 10, 'From the project', 'Notes, releases and useful context.', 3, 'Read the journal', 'wide'),
                self::cta($path, 11, 'Own the whole thing', 'Build your website around your work — not around somebody else\'s platform.', 'See self-hosting', '/self-hosting', 'minimal', 'wide', 'spacious'),
            ],
            'Talvoro — self-hosted publishing',
            'A premium self-hosted publishing platform with a calm CMS, visual page building, structured content, privacy-first analytics and operational ownership.'
        );
    }

    /** @return array<string,array<string,mixed>> */
    private static function pageDefinitions(): array
    {
        return [
            '/product' => self::definition('Product', 'Create, design, publish and operate from one calm workspace.', 'Talvoro keeps the first layer approachable while deeper publishing, design and operational tools remain close when you need them.', [
                self::hero('/product', 1, 'Product', 'Publishing that grows with the work.', 'Start with pages and posts. Add structured content, patterns, forms, analytics, redirects, revisions and advanced design controls when the site earns that complexity.', 'See the workflow', '/demo', 'Self-host Talvoro', '/self-hosting', 'minimal', 'wide', 'spacious', 'left'),
                self::values('/product', 2, [
                    ['sparkles','Create','Write pages, posts and structured entries in focused editors with drafts, revisions and preview.'],
                    ['award','Design','Use Page Builder, patterns, themes, navigation and semantic design tokens instead of one-off page styling.'],
                    ['star','Publish','Schedule, preview and publish with clear status, metadata and content-history controls.'],
                    ['clock','Understand','Use first-party analytics, SEO coverage, redirects and site health without a mandatory third-party tracker.'],
                    ['shield','Protect','MFA, permissions, backups, safe updates, recovery and audit history protect the site without cloud dependence.'],
                    ['leaf','Extend','Structured content models and reusable components let the site grow beyond pages without becoming a custom framework project.'],
                ], 'wide', 'normal'),
                self::custom('/product', 3, 'The daily workspace', 'Common work stays obvious.', 'Talvoro is designed so publishing, media and status are easy to understand at a glance. Advanced settings live nearby, not everywhere.', 'product-ui', 'wide', 'spacious'),
                self::custom('/product', 4, 'Advanced capabilities', 'Power without permanent visual noise.', 'Use the deeper tools when they solve a real problem. They should support the publishing workflow rather than become the product itself.', 'capabilities', 'wide', 'spacious'),
                self::faq('/product', 5, 'Questions', 'The product model, plainly.', [
                    ['Is Talvoro a SaaS?', 'No. Talvoro is designed to be self-hosted. The core website keeps running on infrastructure you control.'],
                    ['Does it require a JavaScript framework?', 'No. The public site is server-rendered and interaction uses progressive vanilla JavaScript.'],
                    ['Can a simple site stay simple?', 'Yes. You can use pages, posts, media and a theme without adopting every advanced capability.'],
                    ['Can it grow into structured content?', 'Yes. Content models, relations, reusable patterns and dynamic collections are part of the architecture.'],
                ], 'wide'),
                self::cta('/product', 6, 'Try the experience', 'See how the pieces fit together, then choose the installation path that suits your environment.', 'Open the demo', '/demo', 'minimal', 'wide', 'spacious'),
            ], 'Talvoro Product — create, design, publish and operate', 'Explore Talvoro workflows for content creation, design, publishing, analytics, security, backups and structured content.'),

            '/themes' => self::definition('Themes', 'A strong default. A clean path to your own identity.', 'Talvoro Editorial is the flagship built-in theme: warm, light, restrained and designed to make content feel intentional rather than templated.', [
                self::hero('/themes', 1, 'Themes', 'Start polished. Make it *yours.*', 'Talvoro separates content structure from presentation so a theme can evolve without turning every page into a migration project.', 'See Talvoro Editorial', '/demo', 'Design freedom', '/product', 'minimal', 'wide', 'spacious', 'center'),
                self::custom('/themes', 2, 'Flagship theme', 'Talvoro Editorial.', 'Warm light surfaces, system typography, restrained action color and calm editorial rhythm form the default visual language approved for Talvoro.', 'theme-showcase', 'wide', 'spacious'),
                self::values('/themes', 3, [
                    ['sparkles','Semantic tokens','Brand, typography, surfaces, radius, spacing and action hierarchy are expressed as reusable design intent.'],
                    ['award','Patterns','Save composed sections and reuse proven layouts instead of rebuilding the same structure repeatedly.'],
                    ['leaf','Portable content','Theme changes do not rewrite the underlying pages, posts or structured content.'],
                    ['shield','Safe customization','Theme packages are validated and isolated; executable PHP or JavaScript is not accepted as theme content.'],
                ], 'wide', 'normal'),
                self::cta('/themes', 4, 'Your site, not a demo forever', 'Use the default as a finished starting point, then change only what makes the identity genuinely yours.', 'Explore the demo', '/demo', 'minimal', 'wide', 'spacious'),
            ], 'Talvoro Themes — Talvoro Editorial and semantic design', 'Explore Talvoro Editorial, semantic design tokens, patterns and the theme system for self-hosted websites.'),

            '/resources' => self::definition('Resources', 'Guides, documentation, releases and the roadmap.', 'Use the level of detail you need: practical guides for common workflows, technical documentation for deeper operation, and transparent release context.', [
                self::hero('/resources', 1, 'Resources', 'Useful context, without the content maze.', 'Start with a guide, go deeper in documentation, or review what changed and what is planned next.', 'Read guides', '/guides', 'Documentation', '/docs', 'minimal', 'wide', 'spacious', 'left'),
                self::cards('/resources', 2, 'Choose your depth', 'Four useful ways into the project.', [
                    ['Guides','Practical workflows for setup, publishing, design, backups and everyday CMS work.','/guides'],
                    ['Documentation','Technical reading for configuration, architecture, deployment and operational behavior.','/docs'],
                    ['Changelog','What changed, what was fixed and what a release means for an existing installation.','/changelog'],
                    ['Roadmap','The direction of the product, organized as finite milestones rather than an endless feature list.','/roadmap'],
                ], 'audiences', 'wide', 'spacious'),
                self::latestPosts('/resources', 3, 'Journal', 'Project notes and useful publishing context.', 4, 'Read all posts', 'wide'),
                self::cta('/resources', 4, 'Need help with a specific install?', 'Use the support page for practical help, or review self-hosting before choosing a deployment path.', 'Get support', '/support', 'minimal', 'wide', 'spacious'),
            ], 'Talvoro Resources — guides, docs, changelog and roadmap', 'Find Talvoro guides, documentation, release notes, roadmap context and support resources.'),

            '/self-hosting' => self::definition('Self-hosting', 'The website should remain operationally yours.', 'Choose Docker for a reproducible stack or standard PHP hosting when that better fits your environment. Either way, keep your domain, database, media and backups under your control.', [
                self::hero('/self-hosting', 1, 'Self-hosting', 'Own the website beyond the design.', 'Talvoro is built for people who want a polished publishing experience without making their public site dependent on a mandatory remote account.', 'Installation paths', '/self-hosting#install', 'Open source', '/open-source', 'minimal', 'wide', 'spacious', 'center'),
                self::custom('/self-hosting', 2, 'Ownership model', 'Your infrastructure stays part of the product decision.', 'The application, database and media live where you decide. Backups and migration are first-class operational concerns rather than afterthoughts.', 'ownership', 'wide', 'spacious'),
                self::custom('/self-hosting', 3, 'Install', 'Two supported ways to start.', 'Docker is recommended for a reproducible deployment. Traditional PHP hosting remains a supported path for environments where containers are not appropriate.', 'install', 'wide', 'spacious'),
                self::values('/self-hosting', 4, [
                    ['shield','Back up before change','Source and database backups are part of the update discipline, not an emergency-only feature.'],
                    ['clock','Recover conservatively','Updates are designed around staged files, migrations and recovery rather than blind in-place replacement.'],
                    ['home','Move when needed','Your site is not designed around one hosting company or one remote runtime.'],
                    ['leaf','Keep runtime data separate','Uploads, storage and secrets stay outside distributable source packages.'],
                ], 'wide', 'normal'),
                self::cta('/self-hosting', 5, 'Get the source', 'Review the project, installation notes and release artifacts before deploying.', 'Open GitHub', self::GITHUB_URL, 'minimal', 'wide', 'spacious'),
            ], 'Self-host Talvoro — Docker or PHP hosting', 'Learn how Talvoro approaches self-hosting, Docker, traditional PHP hosting, backups, portability and operational ownership.'),

            '/open-source' => self::definition('Open source', 'Open enough to inspect. Independent enough to keep running.', 'Talvoro Community is the self-hosted core: source-visible, privacy-first and designed so an optional future service cannot remotely disable an existing website.', [
                self::hero('/open-source', 1, 'Project', 'Open, inspectable and deliberately independent.', 'The project favors understandable infrastructure, conservative dependencies and a release process you can verify.', 'View the repository', self::GITHUB_URL, 'Security', '/security', 'minimal', 'wide', 'spacious', 'left'),
                self::stats('/open-source', 2, 'The stack', 'Straightforward by design.', [
                    ['PHP 8.5','Backend','Server-rendered application code.'],
                    ['MySQL','Database','Portable relational content and configuration.'],
                    ['HTML + CSS','Presentation','Native web platform first.'],
                    ['Vanilla JS','Behavior','Progressive enhancements, not a framework dependency.'],
                ], 'inline', 'wide', 'normal'),
                self::custom('/open-source', 3, 'Independence', 'No remote entitlement should own your website.', 'Future optional services may add convenience, support or managed infrastructure. They should not turn an existing self-hosted site into a remotely controlled rental.', 'ownership', 'wide', 'spacious'),
                self::cta('/open-source', 4, 'Read the source', 'Inspect the code, release history and security policy directly.', 'Open GitHub', self::GITHUB_URL, 'minimal', 'wide', 'spacious'),
            ], 'Talvoro Open Source — inspectable self-hosted publishing', 'Talvoro Community is an inspectable, self-hosted publishing platform built with PHP, MySQL, HTML, CSS and vanilla JavaScript.'),

            '/support' => self::definition('Support', 'Useful help starts with useful context.', 'Describe what you are trying to do, what you expected, what happened instead and the environment you are running. That makes support faster and safer.', [
                self::hero('/support', 1, 'Support', 'Get unstuck without giving up control.', 'Start with the resources and self-hosting notes. If the problem is specific, send enough context to reproduce it without including passwords or secrets.', 'Browse resources', '/resources', 'Self-hosting', '/self-hosting', 'minimal', 'wide', 'spacious', 'left'),
                self::faq('/support', 2, 'Before you send', 'The details that help most.', [
                    ['What should I include?', 'Talvoro version, deployment type, the exact action you took, the visible error and relevant non-secret logs.'],
                    ['Should I send my .env file?', 'No. Never send passwords, APP_KEY values, database credentials or private keys in a support message.'],
                    ['Where should I start with update problems?', 'Confirm the source and database backups exist, check container/service health, then capture the first meaningful error rather than repeatedly rebuilding.'],
                    ['Can I report a security issue publicly?', 'Use the security guidance first. Sensitive vulnerability reports should follow the private reporting path documented by the project.'],
                ], 'wide'),
                self::contact('/support', 3, 'Send a support message', 'Explain the goal, the environment and the first error you can reproduce. Do not include credentials or private keys.', 'Support', 'Send message', 'wide'),
            ], 'Talvoro Support — guides and contact', 'Get Talvoro support, troubleshooting guidance and a privacy-conscious contact path.'),

            '/demo' => self::definition('Demo', 'See the product language before you install it.', 'This guided product page shows the publishing workspace, default theme language and self-hosting model without pretending a screenshot is the whole product.', [
                self::hero('/demo', 1, 'Demo', 'A calm CMS. A polished public site.', 'The same design foundation connects content work in the CMS with the site your visitors see, while keeping their interaction models appropriately different.', 'Explore product', '/product', 'Get Talvoro', '/self-hosting', 'minimal', 'wide', 'spacious', 'center'),
                self::custom('/demo', 2, 'CMS', 'Publishing without dashboard theatre.', 'The overview answers what needs attention, what changed, how the site is doing and what you can do next.', 'product-ui', 'wide', 'spacious'),
                self::custom('/demo', 3, 'Frontend', 'Talvoro Editorial keeps the public experience warm and restrained.', 'The theme is deliberately light, typographically calm and content-first, with a deeper terracotta action hierarchy instead of loud saturated controls.', 'theme-showcase', 'wide', 'spacious'),
                self::custom('/demo', 4, 'Underneath', 'The visual polish does not require surrendering the stack.', 'PHP, MySQL, HTML, CSS and progressive vanilla JavaScript keep the architecture understandable and self-hostable.', 'ownership', 'wide', 'spacious'),
                self::cta('/demo', 5, 'Ready to try it on your infrastructure?', 'Choose an installation path and keep the whole website under your control.', 'Self-host Talvoro', '/self-hosting#install', 'minimal', 'wide', 'spacious'),
            ], 'Talvoro Demo — CMS and Editorial theme preview', 'Preview Talvoro’s publishing workspace, Talvoro Editorial theme and self-hosted architecture.'),

            '/guides' => self::definition('Guides', 'Practical paths through common Talvoro work.', 'Short guidance should help you complete a task without first learning the entire architecture.', [
                self::hero('/guides', 1, 'Guides', 'Start with the job you need to finish.', 'Use Talvoro’s CMS for publishing, design, media, forms and operations without turning every task into a systems project.', 'Documentation', '/docs', 'Get support', '/support', 'minimal', 'wide', 'spacious', 'left'),
                self::cards('/guides', 2, 'Common workflows', 'Start here.', [
                    ['Publish a page','Create, preview and publish a page while keeping status, URL and SEO context clear.','/product'],
                    ['Build with patterns','Compose reusable sections and keep page structure intentional instead of duplicating markup.','/themes'],
                    ['Back up before updates','Protect database and source state before migrations or package replacement.','/self-hosting'],
                    ['Troubleshoot safely','Capture the first reproducible error and relevant logs without exposing secrets.','/support'],
                ], 'audiences', 'wide', 'spacious'),
            ], 'Talvoro Guides — practical publishing and operations', 'Practical Talvoro guides for publishing, design, backups, updates and troubleshooting.'),

            '/docs' => self::definition('Documentation', 'Technical context for running and extending Talvoro.', 'Documentation favors explicit behavior, operational boundaries and compatibility over marketing language.', [
                self::hero('/docs', 1, 'Documentation', 'Understand what the system actually does.', 'Use the technical documentation when deployment, architecture, security, data or extension behavior matters.', 'Self-hosting', '/self-hosting', 'GitHub', self::GITHUB_URL, 'minimal', 'wide', 'spacious', 'left'),
                self::custom('/docs', 2, 'Architecture', 'Server-rendered by default.', 'Talvoro uses PHP 8.5, MySQL, HTML, CSS and progressive vanilla JavaScript. Public content and CMS interactions remain usable without a client-side application runtime.', 'capabilities', 'wide', 'spacious'),
                self::custom('/docs', 3, 'Deployment', 'Treat source, configuration and runtime data as different things.', 'Distribution packages exclude secrets and runtime state. Existing checkouts preserve .git, .env, storage and uploads during source replacement.', 'install', 'wide', 'spacious'),
            ], 'Talvoro Documentation — architecture and deployment', 'Technical Talvoro documentation for architecture, deployment, security, configuration and self-hosted operation.'),

            '/changelog' => self::definition('Changelog', 'Changes should be understandable before they are installed.', 'Release notes explain what changed, whether a migration is involved and what operators should verify afterward.', [
                self::hero('/changelog', 1, 'Changelog', 'Know what changed before you update.', 'Talvoro’s release process is designed around explicit versions, signed tags, release artifacts and checksums.', 'Project journal', '/blog', 'GitHub releases', self::GITHUB_URL, 'minimal', 'wide', 'spacious', 'left'),
                self::latestPosts('/changelog', 2, 'Recent notes', 'Project updates and release context.', 6, 'Read all posts', 'wide'),
            ], 'Talvoro Changelog — releases and update context', 'Talvoro release context, changes, migrations and update notes.'),

            '/roadmap' => self::definition('Roadmap', 'Finite milestones, not an endless promise list.', 'Talvoro evolves through bounded product milestones that can be finished, verified and released before the next layer begins.', [
                self::hero('/roadmap', 1, 'Roadmap', 'Build the foundation. Finish the layer. Move on.', 'The roadmap prioritizes coherent milestones: visual foundation, public product site, CMS information architecture, editing, Page Builder, system UX, backend modernization and release hardening.', 'Product', '/product', 'Changelog', '/changelog', 'minimal', 'wide', 'spacious', 'left'),
                self::values('/roadmap', 2, [
                    ['award','02 · Public product site','Talvoro Editorial and the story-driven public website.'],
                    ['sparkles','03 · CMS structure','Clear Content, Design, Insights and System information architecture.'],
                    ['star','04–05 · Editing','Flagship content editing and a structured visual Page Builder workflow.'],
                    ['shield','06–08 · Hardening','System UX, targeted backend modernization, accessibility, upgrade and release hardening.'],
                ], 'wide', 'normal'),
            ], 'Talvoro Roadmap — finite product milestones', 'See the Talvoro roadmap organized as finite, verifiable product milestones.'),

            '/privacy' => self::definition('Privacy', 'Privacy is an architecture decision before it is a policy page.', 'Talvoro’s core product is designed to work without mandatory advertising networks, third-party trackers or cloud accounts.', [
                self::hero('/privacy', 1, 'Privacy', 'Your visitors should not pay for your CMS with their data.', 'Self-hosted operation keeps the core data path between your visitors and infrastructure you control.', 'Security', '/security', 'Open source', '/open-source', 'minimal', 'wide', 'spacious', 'left'),
                self::values('/privacy', 2, [
                    ['shield','No mandatory tracker','Core publishing and first-party analytics do not require a third-party tracking script.'],
                    ['home','Local ownership','Content, submissions, media and operational data stay with your deployment unless you deliberately integrate something else.'],
                    ['leaf','Minimal defaults','Collect only what the workflow actually needs and keep retention under operator control.'],
                    ['support','Clear integrations','External services should be explicit choices rather than hidden infrastructure dependencies.'],
                ], 'wide', 'normal'),
            ], 'Talvoro Privacy — privacy-first self-hosted publishing', 'How Talvoro’s self-hosted, privacy-first architecture reduces mandatory third-party data sharing.'),

            '/security' => self::definition('Security', 'Secure defaults matter more than security theatre.', 'Talvoro centralizes authentication, MFA, CSRF protection, authorization, rate limiting, audit history, backup/recovery and safe update boundaries.', [
                self::hero('/security', 1, 'Security', 'Protect the website without hiding how it works.', 'Security-sensitive workflows remain application-controlled while public publishing stays flexible and CMS-managed.', 'Support', '/support', 'Project source', self::GITHUB_URL, 'minimal', 'wide', 'spacious', 'left'),
                self::custom('/security', 2, 'Defense in depth', 'Practical controls around real failure modes.', 'Authentication, permissions, CSRF protection, rate limiting, MFA, backups, audit history and conservative update recovery work together instead of relying on one magic layer.', 'capabilities', 'wide', 'spacious'),
            ], 'Talvoro Security — authentication, backups and safe updates', 'Talvoro security principles covering authentication, MFA, authorization, rate limiting, backups, audit history and update recovery.'),
        ];
    }

    /** @param list<array<string,mixed>> $blocks @return array<string,mixed> */
    private static function definition(string $title, string $excerpt, string $description, array $blocks, string $metaTitle, string $metaDescription): array
    {
        return [
            'title' => $title,
            'excerpt' => $excerpt,
            'description' => $description,
            'eyebrow' => strtoupper($title),
            'blocks' => $blocks,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
        ];
    }

    /** @return array<string,mixed> */
    private static function hero(string $path, int $n, string $eyebrow, string $heading, string $intro, string $primaryLabel, string $primaryUrl, string $secondaryLabel, string $secondaryUrl, string $variant='minimal', string $width='wide', string $spacing='spacious', string $alignment='left'): array
    {
        return self::styled($path, $n, [
            'type'=>'hero','enabled'=>true,'eyebrow'=>$eyebrow,'heading'=>$heading,'intro'=>$intro,
            'primary_enabled'=>$primaryLabel!=='','primary_label'=>$primaryLabel,'primary_url'=>$primaryUrl,
            'secondary_enabled'=>$secondaryLabel!=='','secondary_label'=>$secondaryLabel,'secondary_url'=>$secondaryUrl,
            'image_path'=>'','image_alt'=>'',
        ], 'default', $width, $spacing, $alignment, $variant);
    }

    /** @param list<array{0:string,1:string,2:string}> $items @return array<string,mixed> */
    private static function values(string $path, int $n, array $items, string $width='wide', string $spacing='normal'): array
    {
        return self::styled($path, $n, ['type'=>'values','enabled'=>true,'items'=>array_map(static fn(array $i): array => ['icon'=>$i[0],'title'=>$i[1],'body'=>$i[2]], $items)], 'default', $width, $spacing, 'left', 'default');
    }

    /** @return array<string,mixed> */
    private static function custom(string $path, int $n, string $eyebrow, string $heading, string $body, string $variant, string $width='wide', string $spacing='spacious'): array
    {
        return self::styled($path, $n, [
            'type'=>'custom','enabled'=>true,'eyebrow'=>$eyebrow,'heading'=>$heading,'body'=>$body,'layout'=>'stacked','tone'=>'plain',
            'primary_enabled'=>false,'primary_label'=>'','primary_url'=>'','secondary_enabled'=>false,'secondary_label'=>'','secondary_url'=>'','image_path'=>'','image_alt'=>'',
        ], 'default', $width, $spacing, 'left', $variant);
    }

    /** @param list<array{0:string,1:string,2:string}> $items @return array<string,mixed> */
    private static function cards(string $path, int $n, string $eyebrow, string $heading, array $items, string $variant='audiences', string $width='wide', string $spacing='spacious'): array
    {
        return self::styled($path, $n, [
            'type'=>'cards','enabled'=>true,'eyebrow'=>$eyebrow,'heading'=>$heading,'view_label'=>'','view_url'=>'',
            'items'=>array_map(static fn(array $i): array => ['title'=>$i[0],'meta'=>$i[1],'url'=>$i[2],'image_path'=>'','image_alt'=>''], $items),
        ], 'default', $width, $spacing, 'left', $variant);
    }

    /** @param list<array{0:string,1:string,2:string}> $items @return array<string,mixed> */
    private static function stats(string $path, int $n, string $eyebrow, string $heading, array $items, string $variant='inline', string $width='wide', string $spacing='normal'): array
    {
        return self::styled($path, $n, ['type'=>'stats','enabled'=>true,'eyebrow'=>$eyebrow,'heading'=>$heading,'items'=>array_map(static fn(array $i): array => ['value'=>$i[0],'label'=>$i[1],'body'=>$i[2]], $items)], 'default', $width, $spacing, 'left', $variant);
    }

    /** @param list<array{0:string,1:string}> $items @return array<string,mixed> */
    private static function faq(string $path, int $n, string $eyebrow, string $heading, array $items, string $width='wide'): array
    {
        return self::styled($path, $n, ['type'=>'faq','enabled'=>true,'eyebrow'=>$eyebrow,'heading'=>$heading,'items'=>array_map(static fn(array $i): array => ['question'=>$i[0],'answer'=>$i[1]], $items)], 'default', $width, 'spacious', 'left', 'default');
    }

    /** @return array<string,mixed> */
    private static function latestPosts(string $path, int $n, string $eyebrow, string $heading, int $count, string $viewLabel, string $width='wide'): array
    {
        return self::styled($path, $n, ['type'=>'latest_posts','enabled'=>true,'eyebrow'=>$eyebrow,'heading'=>$heading,'view_label'=>$viewLabel,'count'=>$count], 'default', $width, 'spacious', 'left', 'default');
    }

    /** @return array<string,mixed> */
    private static function cta(string $path, int $n, string $eyebrow, string $heading, string $label, string $url, string $variant='minimal', string $width='wide', string $spacing='spacious'): array
    {
        return self::styled($path, $n, ['type'=>'cta','enabled'=>true,'eyebrow'=>$eyebrow,'heading'=>$heading,'button_label'=>$label,'button_url'=>$url], 'soft', $width, $spacing, 'left', $variant);
    }

    /** @return array<string,mixed> */
    private static function contact(string $path, int $n, string $heading, string $intro, string $prefix, string $submitLabel, string $width='wide'): array
    {
        return self::styled($path, $n, [
            'type'=>'contact','enabled'=>true,'heading'=>$heading,'intro'=>$intro,'show_subject'=>true,'require_subject'=>false,
            'subject_prefix'=>$prefix,'submit_label'=>$submitLabel,'success_message'=>'Thanks — your message has been received.',
        ], 'default', $width, 'spacious', 'left', 'default');
    }

    /** @param array<string,mixed> $block @return array<string,mixed> */
    private static function styled(string $path, int $n, array $block, string $tone, string $width, string $spacing, string $alignment, string $variant): array
    {
        return array_merge(['id'=>substr(hash('sha256', $path . '|redesign02|' . $n), 0, 12)], $block, [
            'style_tone'=>$tone,'style_width'=>$width,'style_spacing'=>$spacing,'style_alignment'=>$alignment,'style_variant'=>$variant,
        ]);
    }

    /** @param array<string,mixed> $definition */
    private static function replaceHome(int $homeId, array $definition): void
    {
        $json = self::validatedBlocksJson($definition['blocks']);
        $stmt = Database::connection()->prepare(
            "UPDATE pages SET title=?,page_template='home',excerpt=?,eyebrow=?,body='',body_html='',blocks_json=?,status='published',show_in_navigation=0,show_in_footer=0,published_at=COALESCE(published_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=? AND deleted_at IS NULL"
        );
        $stmt->execute([(string)$definition['title'], (string)$definition['excerpt'], (string)$definition['eyebrow'], $json, $homeId]);
    }

    /** @param array<string,mixed> $definition */
    private static function createPage(string $path, array $definition, int $actorId): void
    {
        $data = [
            'title'=>(string)$definition['title'],'path'=>$path,'page_template'=>'default','excerpt'=>(string)$definition['excerpt'],'eyebrow'=>(string)$definition['eyebrow'],
            'body'=>'','body_html'=>'','blocks_json'=>self::validatedBlocksJson($definition['blocks']),'status'=>'published',
            'show_in_navigation'=>0,'navigation_label'=>'','navigation_order'=>100,'show_in_footer'=>0,'footer_label'=>'','footer_order'=>100,
        ];
        Pages::create($data, $actorId);
    }

    /** @param list<array<string,mixed>> $blocks */
    private static function validatedBlocksJson(array $blocks): string
    {
        $json = json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) throw new RuntimeException('Talvoro product-site blocks could not be encoded.');
        $validated = PageBlocks::validateSubmitted($json);
        if ($validated['errors'] !== []) {
            throw new RuntimeException('Talvoro product-site block validation failed: ' . implode(' ', $validated['errors']));
        }
        return $validated['json'];
    }

    /** @param array<string,mixed> $definition */
    private static function seedSeo(string $path, array $definition, int $actorId): bool
    {
        $existing = SEO::get($path);
        if ($existing && (trim((string)($existing['meta_title'] ?? '')) !== '' || trim((string)($existing['meta_description'] ?? '')) !== '')) return false;
        SEO::save([
            'path'=>$path,'search_phrase'=>'','meta_title'=>(string)$definition['meta_title'],'meta_description'=>(string)$definition['meta_description'],
            'social_title'=>(string)$definition['meta_title'],'social_description'=>(string)$definition['meta_description'],'social_media_id'=>0,
            'canonical_url'=>'','robots'=>'index,follow','sitemap_enabled'=>'1','schema_type'=>$path === '/resources' ? 'CollectionPage' : 'WebPage',
        ], $actorId);
        return true;
    }

    private static function seedPrimaryMenu(int $actorId): int
    {
        $db = Database::connection();
        // Literal location='primary' is intentional: only create the product menu when the location is empty.
        $existing = (int)$db->query("SELECT id FROM menus WHERE location='primary' ORDER BY id LIMIT 1")->fetchColumn();
        if ($existing > 0) return 0;
        $stmt = $db->prepare("INSERT INTO menus (name,menu_key,location,description,created_by,created_at,updated_at) VALUES ('Talvoro product navigation','talvoro_product_primary','primary','Primary navigation for the Talvoro product website.',?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([$actorId]);
        $menuId = (int)$db->lastInsertId();
        $items = [
            ['Product','/product',0],['Themes','/themes',0],['Resources','/resources',0],['Self-hosting','/self-hosting',0],
            ['GitHub',self::GITHUB_URL,1],['Demo','/demo',0],['Get Talvoro','/self-hosting#install',0],
        ];
        foreach ($items as $index => [$label,$url,$newTab]) self::insertMenuItem($menuId, null, $label, $url, $newTab, ($index+1)*10);
        return 1;
    }

    private static function seedFooterMenu(int $actorId): int
    {
        $db = Database::connection();
        // Literal location='footer' is intentional: never replace an operator-managed footer menu.
        $existing = (int)$db->query("SELECT id FROM menus WHERE location='footer' ORDER BY id LIMIT 1")->fetchColumn();
        if ($existing > 0) return 0;
        $stmt = $db->prepare("INSERT INTO menus (name,menu_key,location,description,created_by,created_at,updated_at) VALUES ('Talvoro product footer','talvoro_product_footer','footer','Grouped footer navigation for the Talvoro product website.',?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([$actorId]);
        $menuId = (int)$db->lastInsertId();

        $groups = [
            ['Product','/product', [['Product','/product'],['Themes','/themes'],['Demo','/demo'],['Self-hosting','/self-hosting']]],
            ['Resources','/resources', [['Guides','/guides'],['Documentation','/docs'],['Changelog','/changelog'],['Roadmap','/roadmap'],['Journal','/blog']]],
            ['Project','/open-source', [['Open source','/open-source'],['GitHub',self::GITHUB_URL,1],['Support','/support']]],
            ['Legal','/privacy', [['Privacy','/privacy'],['Security','/security']]],
        ];
        foreach ($groups as $gIndex => $group) {
            $parent = self::insertMenuItem($menuId, null, (string)$group[0], (string)$group[1], 0, ($gIndex+1)*10);
            foreach ($group[2] as $i => $child) self::insertMenuItem($menuId, $parent, (string)$child[0], (string)$child[1], (int)($child[2] ?? 0), ($i+1)*10);
        }
        return 1;
    }

    private static function insertMenuItem(int $menuId, ?int $parentId, string $label, string $url, int $newTab, int $sort): int
    {
        // Avoid interpolating product/content strings into SQL even though this preset owns every value.
        $stmt = Database::connection()->prepare(
            'INSERT INTO menu_items (menu_id,parent_id,label,target_type,target_id,target_model_id,custom_url,open_new_tab,is_enabled,sort_order,created_at,updated_at) VALUES (?,?,?,\'custom\',NULL,NULL,?,?,1,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
        );
        $stmt->execute([$menuId,$parentId,$label,$url,$newTab,$sort]);
        return (int)Database::connection()->lastInsertId();
    }

    private static function pageExists(string $path): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM pages WHERE path=? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$path]);
        return (bool)$stmt->fetchColumn();
    }

    private static function resolveActorId(): int
    {
        try {
            return (int)Database::connection()->query(
                "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.status='active' ORDER BY CASE r.name WHEN 'super_administrator' THEN 0 WHEN 'administrator' THEN 1 ELSE 2 END,u.id LIMIT 1"
            )->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function looksLikeTalvoroStarter(): bool
    {
        $appName = trim((string)Env::get('APP_NAME', 'My Website'));
        $branding = trim((string)Settings::get('branding.site_name', ''));
        $allowedNames = ['', 'My Website', 'Talvoro'];
        if (!in_array($appName, ['My Website','Talvoro'], true) || !in_array($branding, $allowedNames, true)) return false;

        try {
            $row = Database::connection()->query("SELECT title,blocks_json FROM pages WHERE path='/' AND deleted_at IS NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!$row) return true;
            $json = trim((string)($row['blocks_json'] ?? ''));
            if ($json === '') return true;
            foreach ([
                'legacyhero001',
                'Create a *beautiful* place for what matters.',
                'Build a *beautiful* home for your story.',
                'A homepage you can make your own.',
                'Meet what matters most.',
                'Latest stories',
                'Preserving the beauty of a *timeless* breed.',
            ] as $needle) {
                if (str_contains($json, $needle)) return true;
            }
            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{status:string,pages_created:int,home_replaced:bool,menus_created:int,seo_seeded:int,message:string} */
    private static function result(string $status, int $pages, bool $home, int $menus, int $seo, string $message): array
    {
        return ['status'=>$status,'pages_created'=>$pages,'home_replaced'=>$home,'menus_created'=>$menus,'seo_seeded'=>$seo,'message'=>$message];
    }

    private function __construct() {}
}
