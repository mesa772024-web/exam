<?php
declare(strict_types=1);

const CMS_BLOCK_TYPES = [
    'paragraph', 'rich_text', 'hero', 'quote', 'image', 'gallery', 'slideshow', 'flowchart', 'html_element', 'cta',
];

function cms_page_by_slug(string $slug, string $locale, bool $includeDraft = false): ?array
{
    $sql = 'SELECT p.*, t.title, t.excerpt, t.seo_title, t.seo_description FROM pages p JOIN page_translations t ON t.page_id = p.id AND t.locale = :locale WHERE p.slug = :slug';
    if (!$includeDraft) {
        $sql .= " AND p.status = 'published'";
    }
    $statement = db()->prepare($sql . ' LIMIT 1');
    $statement->execute(['slug' => $slug, 'locale' => $locale]);
    $page = $statement->fetch();
    if (!$page && $locale === 'en') {
        return cms_page_by_slug($slug, 'ar', $includeDraft);
    }
    return $page ?: null;
}

function cms_page_blocks(int $pageId, string $locale): array
{
    $statement = db()->prepare('SELECT b.*, t.content_json FROM content_blocks b LEFT JOIN content_block_translations t ON t.block_id = b.id AND t.locale = :locale WHERE b.page_id = :page ORDER BY b.sort_order, b.id');
    $statement->execute(['locale' => $locale, 'page' => $pageId]);
    $blocks = $statement->fetchAll();
    if ($locale === 'en') {
        $fallback = db()->prepare("SELECT block_id, content_json FROM content_block_translations WHERE locale = 'ar' AND block_id IN (SELECT id FROM content_blocks WHERE page_id = :page)");
        $fallback->execute(['page' => $pageId]);
        $fallbacks = [];
        foreach ($fallback->fetchAll() as $row) {
            $fallbacks[(int) $row['block_id']] = $row['content_json'];
        }
        foreach ($blocks as &$block) {
            if (!is_string($block['content_json']) || $block['content_json'] === '') {
                $block['content_json'] = $fallbacks[(int) $block['id']] ?? '{}';
            }
        }
    }
    return $blocks;
}

function cms_decode_json(mixed $json): array
{
    $decoded = json_decode(is_string($json) ? $json : '{}', true);
    return is_array($decoded) ? $decoded : [];
}

function render_cms_block(array $block, string $locale, bool $editing = false): void
{
    $type = in_array($block['block_type'] ?? '', CMS_BLOCK_TYPES, true) ? $block['block_type'] : 'paragraph';
    $content = cms_decode_json($block['content_json'] ?? '{}');
    $settings = cms_decode_json($block['settings_json'] ?? '{}');
    $id = (int) ($block['id'] ?? 0);
    $edit = $editing ? ' data-block-id="' . $id . '" data-edit-key="block-' . $id . '"' : '';
    $editTitle = $editing ? ' data-edit-key="block-' . $id . '-title"' : '';
    $editBody = $editing ? ' data-edit-key="block-' . $id . '-body"' : '';
    $tone = in_array($settings['tone'] ?? '', ['light', 'soft', 'dark', 'green'], true) ? $settings['tone'] : 'light';

    echo '<section class="cms-block cms-' . h($type) . ' tone-' . h($tone) . '"' . $edit . '><div class="cms-shell">';
    if ($type === 'paragraph') {
        echo '<p class="cms-kicker">' . h($content['eyebrow'] ?? '') . '</p><h2' . $editTitle . '>' . h($content['title'] ?? '') . '</h2><p class="cms-lead"' . $editBody . '>' . nl2br(h($content['body'] ?? '')) . '</p>';
    } elseif ($type === 'rich_text') {
        echo '<div class="cms-rich">' . safe_rich_text((string) ($content['html'] ?? '')) . '</div>';
    } elseif ($type === 'hero') {
        echo '<p class="cms-kicker">' . h($content['eyebrow'] ?? '') . '</p><h1' . $editTitle . '>' . h($content['title'] ?? '') . '</h1><p class="cms-lead"' . $editBody . '>' . h($content['body'] ?? '') . '</p>';
        if (!empty($content['button_label']) && !empty($content['button_url'])) {
            echo '<a class="v2-btn v2-btn-primary" href="' . h(cms_safe_url((string) $content['button_url'])) . '">' . h($content['button_label']) . '</a>';
        }
    } elseif ($type === 'quote') {
        echo '<blockquote><p' . $editBody . '>' . h($content['quote'] ?? '') . '</p><footer' . $editTitle . '>' . h($content['author'] ?? '') . '</footer></blockquote>';
    } elseif ($type === 'image') {
        $src = cms_media_url($content['src'] ?? '');
        echo '<figure><img src="' . h($src) . '" alt="' . h($content['alt'] ?? '') . '" loading="lazy"><figcaption>' . h($content['caption'] ?? '') . '</figcaption></figure>';
    } elseif ($type === 'gallery' || $type === 'slideshow') {
        $images = is_array($content['images'] ?? null) ? array_slice($content['images'], 0, 20) : [];
        echo '<div class="cms-gallery' . ($type === 'slideshow' ? ' is-slider' : '') . '" data-slider="' . ($type === 'slideshow' ? 'true' : 'false') . '">';
        foreach ($images as $image) {
            if (!is_array($image)) continue;
            echo '<figure><img src="' . h(cms_media_url($image['src'] ?? '')) . '" alt="' . h($image['alt'] ?? '') . '" loading="lazy"><figcaption>' . h($image['caption'] ?? '') . '</figcaption></figure>';
        }
        echo '</div>';
    } elseif ($type === 'flowchart') {
        render_flowchart($content, $id);
    } elseif ($type === 'html_element') {
        echo '<iframe class="cms-sandbox" sandbox="allow-scripts" title="' . h($content['title'] ?? 'Custom element') . '" src="' . h(site_url('sandbox.php?block=' . $id . '&lang=' . $locale)) . '"></iframe>';
    } elseif ($type === 'cta') {
        echo '<div class="cms-cta-copy"><p class="cms-kicker">' . h($content['eyebrow'] ?? '') . '</p><h2' . $editTitle . '>' . h($content['title'] ?? '') . '</h2><p' . $editBody . '>' . h($content['body'] ?? '') . '</p></div><a class="v2-btn v2-btn-light" href="' . h(cms_safe_url((string) ($content['button_url'] ?? '#'))) . '">' . h($content['button_label'] ?? '') . '</a>';
    }
    echo '</div></section>';
}

function render_flowchart(array $content, int $blockId): void
{
    $nodes = is_array($content['nodes'] ?? null) ? array_slice($content['nodes'], 0, 40) : [];
    $edges = is_array($content['edges'] ?? null) ? array_slice($content['edges'], 0, 80) : [];
    echo '<div class="flow-board" data-flow-board data-block="' . $blockId . '"><svg class="flow-lines" aria-hidden="true">';
    foreach ($edges as $edge) {
        if (!is_array($edge)) continue;
        echo '<line data-from="' . h($edge['from'] ?? '') . '" data-to="' . h($edge['to'] ?? '') . '"></line>';
    }
    echo '</svg>';
    foreach ($nodes as $node) {
        if (!is_array($node)) continue;
        $nodeId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($node['id'] ?? '')) ?: bin2hex(random_bytes(3));
        $x = max(0, min(90, (float) ($node['x'] ?? 10)));
        $y = max(0, min(85, (float) ($node['y'] ?? 10)));
        echo '<button class="flow-node" type="button" data-node="' . h($nodeId) . '" style="--x:' . $x . '%;--y:' . $y . '%"><strong>' . h($node['title'] ?? '') . '</strong><span>' . h($node['text'] ?? '') . '</span></button>';
    }
    echo '</div>';
}

function cms_safe_url(string $url): string
{
    $url = trim($url);
    if ($url === '') return '#';
    if (str_starts_with($url, '/') || str_starts_with($url, '#') || preg_match('#^https://#i', $url) || preg_match('#^mailto:#i', $url) || preg_match('#^tel:#i', $url)) {
        return $url;
    }
    return '#';
}

function cms_media_url(mixed $path): string
{
    $path = clean_text($path, 500);
    if ($path === '') return site_url('assets/images/warehouse.jpg');
    if (preg_match('#^https://#i', $path)) return $path;
    return site_url(ltrim($path, '/'));
}

function visual_safe_url(string $url, bool $media = false): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (str_contains($url, "\0") || str_contains($url, '\\') || preg_match('#(^|/)\.\.(/|$)#', $url)) return '';
    if (preg_match('#^https?://[^\s]+$#i', $url)) return $url;
    if (!$media && (preg_match('#^(?:mailto|tel):[^\s]+$#i', $url) || str_starts_with($url, '#'))) return $url;
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    if (preg_match('~^[A-Za-z0-9][A-Za-z0-9._/-]*(?:\?[^\s#]*)?(?:#[^\s]*)?$~', $url)) return $url;
    return '';
}

function element_styles_css(string $scope): string
{
    $statement = db()->prepare('SELECT element_key, styles_json FROM element_styles WHERE page_scope = :scope');
    $statement->execute(['scope' => $scope]);
    $css = '';
    foreach ($statement->fetchAll() as $row) {
        $key = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) $row['element_key']);
        $styles = safe_style_map(cms_decode_json($row['styles_json']));
        if ($key === '' || $styles === []) continue;
        $declarations = [];
        foreach ($styles as $property => $value) {
            $cssName = preg_replace('/[A-Z]/', '-$0', $property);
            $cssName = strtolower((string) $cssName);
            if (in_array($property, ['translateX', 'translateY'], true)) continue;
            $declarations[] = $cssName . ':' . $value;
        }
        $x = $styles['translateX'] ?? '0px';
        $y = $styles['translateY'] ?? '0px';
        if ($x !== '0px' || $y !== '0px') $declarations[] = 'translate:' . $x . ' ' . $y;
        $css .= '[data-edit-key="' . $key . '"]{' . implode(';', $declarations) . '}';
    }
    return $css;
}

function save_element_style(string $scope, string $key, array $styles, int $adminId): void
{
    $scope = clean_slug($scope) ?: 'home';
    $key = preg_replace('/[^a-zA-Z0-9_.-]/', '', $key) ?? '';
    if ($key === '') throw new RuntimeException('العنصر المحدد غير صالح.');
    $styles = safe_style_map($styles);
    $current = db()->prepare('SELECT styles_json FROM element_styles WHERE page_scope = :scope AND element_key = :key');
    $current->execute(['scope' => $scope, 'key' => $key]);
    $existing = safe_style_map(cms_decode_json($current->fetchColumn()));
    $styles = array_replace($existing, $styles);
    $statement = db()->prepare('INSERT INTO element_styles(page_scope, element_key, styles_json, updated_by, updated_at) VALUES(:scope, :key, :styles, :admin, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE styles_json = VALUES(styles_json), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP');
    $statement->execute(['scope' => $scope, 'key' => $key, 'styles' => json_encode($styles), 'admin' => $adminId]);
}
