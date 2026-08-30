<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class StarterResourceRegistry
{

    /** @var array<string,class-string<StarterResourceAdapter>> */
    private const ADAPTERS = [
        'media' => \CMS\Core\StarterResources\MediaStarterResource::class,
        'content_component' => \CMS\Core\StarterResources\StructuredContentStarterResource::class,
        'component_field' => \CMS\Core\StarterResources\StructuredContentStarterResource::class,
        'content_model' => \CMS\Core\StarterResources\StructuredContentStarterResource::class,
        'content_field' => \CMS\Core\StarterResources\StructuredContentStarterResource::class,
        'content_entry' => \CMS\Core\StarterResources\StructuredContentStarterResource::class,
        'blog_category' => \CMS\Core\StarterResources\PublishingStarterResource::class,
        'post' => \CMS\Core\StarterResources\PublishingStarterResource::class,
        'page' => \CMS\Core\StarterResources\PublishingStarterResource::class,
        'seo' => \CMS\Core\StarterResources\PublishingStarterResource::class,
        'menu' => \CMS\Core\StarterResources\NavigationStarterResource::class,
        'menu_item' => \CMS\Core\StarterResources\NavigationStarterResource::class,
        'setting' => \CMS\Core\StarterResources\ConfigurationStarterResource::class,
        'theme_design' => \CMS\Core\StarterResources\ConfigurationStarterResource::class,
    ];

    /** @var array<string,string> */
    private const LABELS = [
        'media' => 'Media items',
        'content_component' => 'Structured Content components',
        'component_field' => 'Component fields',
        'content_model' => 'Structured Content models',
        'content_field' => 'Structured Content fields',
        'content_entry' => 'Structured Content entries',
        'blog_category' => 'Journal categories',
        'post' => 'Journal posts',
        'page' => 'Pages',
        'menu' => 'Menus',
        'menu_item' => 'Menu items',
        'seo' => 'SEO records',
        'setting' => 'Site settings',
        'theme_design' => 'Theme design',
    ];

    /** @return list<string> */
    public static function supportedTypes(): array
    {
        return array_keys(self::LABELS);
    }

    public static function supports(string $type): bool
    {
        return isset(self::LABELS[$type]);
    }

    public static function label(string $type): string
    {
        if (!isset(self::LABELS[$type])) {
            throw new RuntimeException('Unsupported starter resource type: ' . $type . '.');
        }
        return self::LABELS[$type];
    }


    /** @return array<string,class-string<StarterResourceAdapter>> */
    public static function adapterGroups(): array
    {
        $groups=[];
        foreach(self::ADAPTERS as $type=>$class) $groups[$class]=$class;
        return $groups;
    }

    /** @return class-string<StarterResourceAdapter>|'' */
    public static function adapterClass(string $type): string
    {
        return self::ADAPTERS[$type] ?? '';
    }

    public static function adapter(string $type): StarterResourceAdapter
    {
        $class=self::adapterClass($type);
        if($class==='') throw new RuntimeException('Unsupported starter resource type: '.$type.'.');
        return new $class();
    }

    private function __construct() {}
}
