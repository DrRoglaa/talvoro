<?php
use CMS\Core\PageBlocks;

$blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : PageBlocks::decode((string)($page['blocks_json'] ?? ''));
$blocks = PageBlocks::renderBlocks($blocks);
?>
<?php if ($blocks): ?>
<div class="page-blocks<?= (($page['path'] ?? '') === '/') ? ' page-blocks-home' : '' ?>">
    <?php foreach ($blocks as $block):
        $type = (string)($block['type'] ?? '');
        $partial = __DIR__ . '/blocks/' . $type . '.php';
        if (!is_file($partial)) continue;
        require $partial;
    endforeach; ?>
</div>
<?php endif; ?>
