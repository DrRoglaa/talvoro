<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): string
    {
        $viewFile = base_path('resources/views/' . $view . '.php');
        $layoutFile = base_path('resources/views/' . $layout . '.php');
        if (!is_file($viewFile) || !is_file($layoutFile)) {
            throw new RuntimeException('View not found: ' . $view);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $viewFile;
            $content = (string)ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        ob_start();
        try {
            require $layoutFile;
            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
