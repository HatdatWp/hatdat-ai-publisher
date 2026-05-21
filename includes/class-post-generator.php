<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once AI_PUBLISHER_DIR . 'includes/seo/class-rankmath-provider.php';
require_once AI_PUBLISHER_DIR . 'includes/seo/class-yoast-provider.php';
require_once AI_PUBLISHER_DIR . 'includes/seo/class-null-provider.php';

final class AI_Publisher_Post_Generator {
    public function create_post(array $article, array $settings, ?int $attachment_id = null): int|WP_Error {
        $article = $this->normalize_seo_article($article);

        $postarr = [
            'post_title' => sanitize_text_field($article['title']),
            'post_name' => $this->limit_slug($article['slug'] ?: $article['title'], 75),
            'post_excerpt' => sanitize_textarea_field($article['excerpt']),
            'post_content' => wp_kses_post($article['content']),
            'post_status' => $settings['default_status'] ?: 'draft',
            'post_type' => $this->post_type($settings['post_type'] ?? 'post'),
        ];
        if ($postarr['post_type'] === 'post' && !empty($settings['default_category'])) { $postarr['post_category'] = [(int)$settings['default_category']]; }
        $post_id = wp_insert_post($postarr, true);
        if (is_wp_error($post_id)) { return $post_id; }
        if ($attachment_id) { set_post_thumbnail($post_id, $attachment_id); }
        $this->seo_provider($settings['seo_provider'] ?? 'auto')->save_meta((int)$post_id, $article);
        return (int)$post_id;
    }


    private function post_type(string $post_type): string {
        return in_array($post_type, ['post', 'page'], true) ? $post_type : 'post';
    }

    private function normalize_seo_article(array $article): array {
        $title = trim(wp_strip_all_tags((string)($article['title'] ?? '')));
        $content = (string)($article['content'] ?? '');

        $focus = trim(wp_strip_all_tags((string)($article['focus_keyword'] ?? '')));
        if ($focus === '') {
            $focus = $this->derive_focus_keyword($title);
        }
        $focus = $this->limit_words($focus, 4);

        if ($focus !== '' && stripos($title, $focus) === false) {
            $title = $this->prepend_keyword_to_title($focus, $title);
            $article['title'] = $title;
        }

        $seo_title = trim(wp_strip_all_tags((string)($article['seo_title'] ?? '')));
        if ($seo_title === '') {
            $seo_title = $title;
        }
        if ($focus !== '' && stripos($seo_title, $focus) === false) {
            $seo_title = $this->prepend_keyword_to_title($focus, $seo_title);
        }
        $article['seo_title'] = $this->limit_chars($seo_title, 60);

        $description = trim(wp_strip_all_tags((string)($article['seo_description'] ?? '')));
        if ($description === '') {
            $description = trim(wp_strip_all_tags((string)($article['excerpt'] ?? '')));
        }
        if ($description === '') {
            $description = trim(wp_strip_all_tags($content));
        }
        if ($focus !== '' && stripos($description, $focus) === false) {
            $description = $focus . ': ' . $description;
        }
        $article['seo_description'] = $this->limit_chars($description, 155);

        if ($focus !== '') {
            $content = $this->ensure_keyword_in_content($content, $focus);
        }

        $article['focus_keyword'] = $focus;
        $article['content'] = $content;

        if (empty($article['additional_keywords']) || !is_array($article['additional_keywords'])) {
            $article['additional_keywords'] = $this->derive_additional_keywords($title, $focus);
        } else {
            $article['additional_keywords'] = array_values(array_filter(array_map(
                fn($kw) => $this->limit_words(trim(wp_strip_all_tags((string)$kw)), 4),
                $article['additional_keywords']
            )));
        }

        return $article;
    }

    private function ensure_keyword_in_content(string $content, string $focus): string {
        if (stripos(wp_strip_all_tags($content), $focus) !== false) {
            return $content;
        }

        $intro = '<p><strong>' . esc_html($focus) . '</strong> ist das zentrale Thema dieses Beitrags. Der Artikel erklärt die wichtigsten Hintergründe verständlich und ordnet sie praktisch ein.</p>' . "

";

        return $intro . $content;
    }

    private function derive_focus_keyword(string $title): string {
        $title = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($title)));
        if ($title === '') {
            return '';
        }

        $stopwords = ['der','die','das','ein','eine','und','oder','mit','bei','für','von','im','in','am','an','auf','zu','den','dem','des','ist','sind','warum','wie','was','the','a','an','and','or','with','for','of','in','on','to'];
        $words = preg_split('/\s+/', mb_strtolower($title));
        $words = array_values(array_filter($words, fn($w) => mb_strlen($w) > 2 && !in_array($w, $stopwords, true)));
        if (!$words) {
            return $this->limit_words($title, 4);
        }

        return implode(' ', array_slice($words, 0, min(3, count($words))));
    }

    private function derive_additional_keywords(string $title, string $focus): array {
        $clean = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($title)));
        $keywords = [];

        if ($focus !== '') {
            $keywords[] = $focus;
        }

        $words = preg_split('/\s+/', $clean);
        for ($i = 0; $i < count($words) - 1; $i++) {
            $phrase = trim($words[$i] . ' ' . $words[$i + 1]);
            if (mb_strlen($phrase) > 4 && stripos($phrase, $focus) === false) {
                $keywords[] = $phrase;
            }
            if (count($keywords) >= 6) {
                break;
            }
        }

        return array_values(array_unique($keywords));
    }

    private function prepend_keyword_to_title(string $keyword, string $title): string {
        $keyword = trim($keyword);
        $title = trim($title);
        if ($title === '') {
            return $keyword;
        }

        return $keyword . ': ' . preg_replace('/^' . preg_quote($keyword, '/') . '\s*[:\-–]?\s*/i', '', $title);
    }

    private function limit_words(string $value, int $max_words): string {
        $value = trim(preg_replace('/\s+/', ' ', $value));
        if ($value === '') {
            return '';
        }

        $words = preg_split('/\s+/', $value);
        return implode(' ', array_slice($words, 0, $max_words));
    }

    private function limit_chars(string $value, int $max): string {
        $value = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($value)));
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        $cut = mb_substr($value, 0, $max - 1);
        $last_space = mb_strrpos($cut, ' ');
        if ($last_space !== false && $last_space > 20) {
            $cut = mb_substr($cut, 0, $last_space);
        }

        return rtrim($cut, " .,:;!?") . '…';
    }


    private function limit_slug(string $value, int $max = 75): string {
        $slug = sanitize_title($value);

        if (mb_strlen($slug) <= $max) {
            return $slug;
        }

        $slug = mb_substr($slug, 0, $max);

        // avoid cutting in the middle of a word
        $last_dash = mb_strrpos($slug, '-');
        if ($last_dash !== false && $last_dash > 20) {
            $slug = mb_substr($slug, 0, $last_dash);
        }

        $slug = trim($slug, '-');

        return sanitize_title($slug);
    }

    private function seo_provider(string $provider): AI_Publisher_SEO_Provider {
        if ($provider === 'auto') {
            if (defined('RANK_MATH_VERSION')) { return new AI_Publisher_RankMath_Provider(); }
            if (defined('WPSEO_VERSION')) { return new AI_Publisher_Yoast_Provider(); }
            return new AI_Publisher_Null_Provider();
        }
        return match ($provider) {
            'rankmath' => new AI_Publisher_RankMath_Provider(),
            'yoast' => new AI_Publisher_Yoast_Provider(),
            default => new AI_Publisher_Null_Provider(),
        };
    }
}
