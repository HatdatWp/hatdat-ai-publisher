<?php
if (!defined('ABSPATH')) {
    exit;
}

final class AI_Publisher_Settings {
    public const OPTION = 'ai_publisher_settings';
    public const CURRENT_TEXT_MODEL = 'gpt-5.5';
    public const CURRENT_IMAGE_MODEL = 'gpt-image-2';

    public static function defaults(): array {
        return [
            'api_key' => '',
            'text_model' => self::CURRENT_TEXT_MODEL,
            'image_model' => self::CURRENT_IMAGE_MODEL,
            'seo_provider' => 'auto',
            'default_status' => 'draft',
            'default_category' => 0,
            'generate_image' => 1,
            'disable_image_on_billing_error' => 1,
            'openai_consent_data_processing' => 0,
            'openai_consent_costs' => 0,
            'openai_consent_responsibility' => 0,
            'openai_consent_timestamp' => '',
            'image_size' => '1920x1088',
            'cost_input_usd_per_1m' => AI_Publisher_Cost_Estimator::DEFAULT_INPUT_USD_PER_1M,
            'cost_output_usd_per_1m' => AI_Publisher_Cost_Estimator::DEFAULT_OUTPUT_USD_PER_1M,
        ];
    }

    public static function add_defaults(): void {
        if (!get_option(self::OPTION)) {
            add_option(self::OPTION, self::defaults(), '', false);
        }
    }

    public static function ensure_current_model_defaults(): void {
        $settings = self::get();
        $changed = false;

        $legacy_text_models = ['', 'gpt-4o', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4.1-mini'];
        $legacy_image_models = ['', 'dall-e-2', 'dall-e-3', 'gpt-image-1'];

        if (in_array((string) $settings['text_model'], $legacy_text_models, true)) {
            $settings['text_model'] = self::CURRENT_TEXT_MODEL;
            $changed = true;
        }

        if (in_array((string) $settings['image_model'], $legacy_image_models, true)) {
            $settings['image_model'] = self::CURRENT_IMAGE_MODEL;
            $changed = true;
        }

        if ($changed) {
            update_option(self::OPTION, $settings, false);
        }
    }

    public static function get(): array {
        return wp_parse_args((array) get_option(self::OPTION, []), self::defaults());
    }

    public static function update(array $data): void {
        $old = self::get();
        $new = $old;
        $new['api_key'] = isset($data['api_key']) ? sanitize_text_field(wp_unslash($data['api_key'])) : $old['api_key'];
        $new['text_model'] = sanitize_text_field(wp_unslash($data['text_model'] ?? $old['text_model']));
        $new['image_model'] = sanitize_text_field(wp_unslash($data['image_model'] ?? $old['image_model']));
        $new['seo_provider'] = sanitize_key($data['seo_provider'] ?? $old['seo_provider']);
        $new['default_status'] = in_array(($data['default_status'] ?? 'draft'), ['draft', 'publish', 'pending', 'private'], true) ? $data['default_status'] : 'draft';
        $new['default_category'] = absint($data['default_category'] ?? 0);
        $new['generate_image'] = !empty($data['generate_image']) ? 1 : 0;
        $new['disable_image_on_billing_error'] = !empty($data['disable_image_on_billing_error']) ? 1 : 0;
        $new['openai_consent_data_processing'] = !empty($data['openai_consent_data_processing']) ? 1 : 0;
        $new['openai_consent_costs'] = !empty($data['openai_consent_costs']) ? 1 : 0;
        $new['openai_consent_responsibility'] = !empty($data['openai_consent_responsibility']) ? 1 : 0;

        if (
            $new['openai_consent_data_processing']
            && $new['openai_consent_costs']
            && $new['openai_consent_responsibility']
            && empty($old['openai_consent_timestamp'])
        ) {
            $new['openai_consent_timestamp'] = current_time('mysql');
        }

        if (
            !$new['openai_consent_data_processing']
            || !$new['openai_consent_costs']
            || !$new['openai_consent_responsibility']
        ) {
            $new['openai_consent_timestamp'] = '';
        }

        $new['cost_input_usd_per_1m'] = max(0, (float) str_replace(',', '.', (string) wp_unslash($data['cost_input_usd_per_1m'] ?? $old['cost_input_usd_per_1m'])));
        $new['cost_output_usd_per_1m'] = max(0, (float) str_replace(',', '.', (string) wp_unslash($data['cost_output_usd_per_1m'] ?? $old['cost_output_usd_per_1m'])));

        $allowed_image_sizes = ['1024x1024', '1536x1024', '1792x1024', '1920x1088', '1024x1536'];
        $image_size = sanitize_text_field(wp_unslash($data['image_size'] ?? $old['image_size']));
        $new['image_size'] = in_array($image_size, $allowed_image_sizes, true) ? $image_size : $old['image_size'];

        update_option(self::OPTION, $new, false);
    }

    public static function has_required_openai_consent(): bool {
        $settings = self::get();

        return !empty($settings['openai_consent_data_processing'])
            && !empty($settings['openai_consent_costs'])
            && !empty($settings['openai_consent_responsibility']);
    }

    public static function disable_image_generation(): void {
        $settings = self::get();
        $settings['generate_image'] = 0;
        update_option(self::OPTION, $settings, false);
    }
}
