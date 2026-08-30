<?php
declare(strict_types=1);

$show=(string)@file_get_contents(__DIR__.'/../resources/views/content/show.php');
$archive=(string)@file_get_contents(__DIR__.'/../resources/views/content/archive.php');
$blog=(string)@file_get_contents(__DIR__.'/../resources/views/blog/index.php');
$settings=(string)@file_get_contents(__DIR__.'/../app/Core/Settings.php');
$layout=(string)@file_get_contents(__DIR__.'/../resources/views/layouts/app.php');
$contact=(string)@file_get_contents(__DIR__.'/../resources/views/page/blocks/contact.php');
$homePage=(string)@file_get_contents(__DIR__.'/../app/Core/HomePage.php');
$pageForm=(string)@file_get_contents(__DIR__.'/../resources/views/admin/pages/form.php');
$publicCss=(string)@file_get_contents(__DIR__.'/../public/assets/css/talvoro-public.css');
$checks=[
    'Structured Content detail exposes a model-key hook' => str_contains($show,'data-model-key') && str_contains($show,'model-'),
    'Structured Content detail exposes field-key hooks' => str_contains($show,'data-field-key') && str_contains($show,'field-'),
    'Structured Content archive exposes a model-key hook' => str_contains($archive,'data-model-key') && str_contains($archive,'model-'),
    'Page Builder hero keeps legacy selectors and adds neutral theme hooks' => str_contains((string)@file_get_contents(__DIR__.'/../resources/views/page/blocks/hero.php'),'talvoro-page-hero'),
    'Journal archive copy can be configured generically' => str_contains($settings,'blogArchiveTitle') && str_contains($settings,'blogArchiveIntro') && str_contains($blog,'Settings::blogArchiveTitle()') && str_contains($blog,'Settings::blogArchiveIntro()'),
    'Customer footer copy is site-configurable' => str_contains($settings,'publicFooterText') && str_contains($settings,'publicFooterNote') && str_contains($layout,'Settings::publicFooterText()') && str_contains($layout,'Settings::publicFooterNote($siteName)'),
    'Customer contact privacy note is CMS-brand neutral' => !str_contains($contact,'Talvoro does not require a third-party form service'),
    'Footer note is editable from Home Header & footer settings' => str_contains($homePage,"'branding.footer_note'") && str_contains($homePage,"'branding_footer_note'") && str_contains($pageForm,'name="branding_footer_note"'),
    'Talvoro product footer uses configurable note with preserved default copy' => str_contains($layout,'Settings::publicFooterNote($siteName,') && str_contains($layout,'Independent software, built with'),
    'Talvoro product footer uses Proudly built with linked version credit' => str_contains($layout,'Proudly built with') && str_contains($layout,'https://github.com/DrRoglaa/talvoro') && str_contains($layout,'Talvoro</a> v:') && !str_contains($layout,'Talvoro version:'),
    'Footer metadata links inherit footer styling and expose focus state' => str_contains($publicCss,'.public-footer-bottom a') && str_contains($publicCss,'.public-footer-bottom a:focus-visible'),
    'Page editor uses a single primary save action' => substr_count($pageForm, 'type="submit"') === 2 && str_contains($pageForm, '<div class="editor-save-bar page-editor-save-bar">'),
];
$failed=[];
foreach($checks as $name=>$ok){echo ($ok?'[OK]   ':'[FAIL] ').$name.PHP_EOL;if(!$ok)$failed[]=$name;}
if($failed){fwrite(STDERR,PHP_EOL.'Talvoro 0.17.0 public theme hook checks failed: '.implode(', ',$failed).PHP_EOL);exit(1);} 
echo PHP_EOL.'Talvoro 0.17.0 public theme hook checks passed.'.PHP_EOL;
