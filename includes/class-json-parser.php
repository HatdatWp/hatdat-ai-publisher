<?php
if (!defined('ABSPATH')) {
    exit;
}

final class AI_Publisher_JSON_Parser {
    public static function parse_article(string $content): array|WP_Error {
        $content = trim($content);
        $content = preg_replace('/^```json\s*|\s*```$/m', '', $content);
        $data = json_decode($content, true);
        if (!is_array($data)) { return new WP_Error('invalid_json', __('The AI response was not valid JSON.', 'hatdat-ai-publisher')); }

        $fields = ['title','slug','excerpt','content','seo_title','seo_description','focus_keyword','image_prompt'];
        $out = [];
        foreach ($fields as $field) { $out[$field] = isset($data[$field]) ? (string)$data[$field] : ''; }

        $out['additional_keywords'] = [];
        if (isset($data['additional_keywords'])) {
            if (is_array($data['additional_keywords'])) {
                $out['additional_keywords'] = array_values(array_filter(array_map('strval', $data['additional_keywords'])));
            } elseif (is_string($data['additional_keywords'])) {
                $out['additional_keywords'] = array_values(array_filter(array_map('trim', explode(',', $data['additional_keywords']))));
            }
        }

        if (trim($out['title']) === '' || trim($out['content']) === '') {
            return new WP_Error('missing_fields', __('The AI response is missing title or content.', 'hatdat-ai-publisher'));
        }
        return $out;
    }
}
