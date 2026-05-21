<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once AI_PUBLISHER_DIR . 'includes/class-cost-estimator.php';
require_once AI_PUBLISHER_DIR . 'includes/class-settings.php';
require_once AI_PUBLISHER_DIR . 'includes/class-prompt-manager.php';
require_once AI_PUBLISHER_DIR . 'includes/class-openai-client.php';
require_once AI_PUBLISHER_DIR . 'includes/class-json-parser.php';
require_once AI_PUBLISHER_DIR . 'includes/class-media-handler.php';
require_once AI_PUBLISHER_DIR . 'includes/class-post-generator.php';
require_once AI_PUBLISHER_DIR . 'includes/class-admin.php';

final class AI_Publisher_Plugin {
    private static ?AI_Publisher_Plugin $instance = null;

    public static function instance(): AI_Publisher_Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function run(): void {
        AI_Publisher_Settings::ensure_current_model_defaults();
        AI_Publisher_Prompt_Manager::maybe_install();
        if (is_admin()) {
            (new AI_Publisher_Admin())->init();
        }

        add_action('admin_init', [self::class, 'add_privacy_policy_content']);
    }

    public static function add_privacy_policy_content(): void {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = '<p>' . esc_html__('Hatdat AI Publisher can send prompts, article content and image generation requests entered by administrators to the OpenAI API for processing.', 'hatdat-ai-publisher') . '</p>';
        $content .= '<p>' . esc_html__('Hatdat AI Publisher does not automatically transmit WordPress user accounts, passwords, email addresses, profile data or other WordPress user records to OpenAI.', 'hatdat-ai-publisher') . '</p>';
        $content .= '<p>' . esc_html__('Only content intentionally submitted through the Hatdat AI Publisher administration interface is transmitted to OpenAI. Site administrators are responsible for ensuring that submitted content complies with applicable privacy and data protection laws.', 'hatdat-ai-publisher') . '</p>';
        $content .= '<p>' . esc_html__('Using the OpenAI API requires a separate OpenAI API account and may generate additional costs billed by OpenAI.', 'hatdat-ai-publisher') . '</p>';

        wp_add_privacy_policy_content('Hatdat AI Publisher', wp_kses_post($content));
    }

    public static function activate(): void {
        AI_Publisher_Settings::add_defaults();
        AI_Publisher_Settings::ensure_current_model_defaults();
        AI_Publisher_Prompt_Manager::install();
    }

    public static function deactivate(): void {
        // No rewrite rules are registered by Hatdat AI Publisher.
    }
}
