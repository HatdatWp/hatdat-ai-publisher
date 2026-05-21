<?php
if (!defined('ABSPATH')) {
    exit;
}

interface AI_Publisher_SEO_Provider {
    public function save_meta(int $post_id, array $seo_data): void;
}
