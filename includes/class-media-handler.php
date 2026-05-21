<?php
if (!defined('ABSPATH')) {
    exit;
}

final class AI_Publisher_Media_Handler {
    public static function sideload_base64_png(string $base64, string $title): int|WP_Error {
        $upload = wp_upload_bits(sanitize_title($title) . '-ai-image.png', null, base64_decode($base64));
        if (!empty($upload['error'])) { return new WP_Error('upload_error', $upload['error']); }

        $file_path = $upload['file'];
        $file_type = wp_check_filetype(basename($file_path), null);
        $attachment_id = wp_insert_attachment([
            'post_mime_type' => $file_type['type'] ?: 'image/png',
            'post_title' => sanitize_text_field($title),
            'post_content' => '',
            'post_status' => 'inherit',
        ], $file_path);

        if (is_wp_error($attachment_id)) { return $attachment_id; }
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $metadata);
        return (int)$attachment_id;
    }
}
