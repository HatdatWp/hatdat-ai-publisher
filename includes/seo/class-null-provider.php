<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once __DIR__ . '/interface-seo-provider.php';

final class AI_Publisher_Null_Provider implements AI_Publisher_SEO_Provider {
    public function save_meta(int $post_id, array $seo_data): void {}
}
