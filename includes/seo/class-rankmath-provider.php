<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once __DIR__ . '/interface-seo-provider.php';

final class AI_Publisher_RankMath_Provider implements AI_Publisher_SEO_Provider {
    public function save_meta(int $post_id, array $seo_data): void {
        $title = $this->first_non_empty(
            $seo_data['seo_title'] ?? '',
            $seo_data['title'] ?? '',
            get_the_title($post_id)
        );

        $description = $this->first_non_empty(
            $seo_data['seo_description'] ?? '',
            $seo_data['excerpt'] ?? '',
            $this->excerpt_from_content($seo_data['content'] ?? '')
        );

        $focus_keyword = $this->first_non_empty(
            $seo_data['focus_keyword'] ?? '',
            $this->keyword_from_title($seo_data['title'] ?? get_the_title($post_id))
        );

        update_post_meta($post_id, 'rank_math_title', sanitize_text_field($title));
        update_post_meta($post_id, 'rank_math_description', sanitize_textarea_field($description));
        update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($focus_keyword));

        if (!empty($seo_data['additional_keywords']) && is_array($seo_data['additional_keywords'])) {
            $additional_keywords = array_values(array_filter(array_map('sanitize_text_field', $seo_data['additional_keywords'])));
            update_post_meta($post_id, 'rank_math_additional_keywords', $additional_keywords);
        }

        if (!empty($seo_data['slug'])) {
            $permalink = sanitize_title($seo_data['slug']);
            if (mb_strlen($permalink) > 75) {
                $permalink = mb_substr($permalink, 0, 75);
                $permalink = trim($permalink, '-');
            }
            update_post_meta($post_id, 'rank_math_permalink', $permalink);
        }
    }

    private function first_non_empty(string ...$values): string {
        foreach ($values as $value) {
            $value = trim(wp_strip_all_tags($value));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function excerpt_from_content(string $content): string {
        $content = trim(wp_strip_all_tags($content));
        if ($content === '') {
            return '';
        }

        return wp_html_excerpt($content, 155, '…');
    }

    private function keyword_from_title(string $title): string {
        $title = trim(wp_strip_all_tags($title));
        if ($title === '') {
            return '';
        }

        $title = preg_replace('/\s+/', ' ', $title);
        $parts = explode(' ', $title);

        return implode(' ', array_slice($parts, 0, min(4, count($parts))));
    }
}
