<?php
if (!defined('ABSPATH')) {
    exit;
}

final class AI_Publisher_Admin {
    private const CAPABILITY = 'manage_options';

    public function init(): void {
        add_action('admin_menu', [$this, 'menu'], 5);
        add_action('admin_post_ai_publisher_save_settings', [$this, 'save_settings']);
        add_action('admin_post_ai_publisher_check_billing', [$this, 'check_billing']);
        add_action('admin_post_ai_publisher_save_prompt', [$this, 'save_prompt']);
        add_action('admin_post_ai_publisher_delete_prompt', [$this, 'delete_prompt']);
        add_action('admin_post_ai_publisher_copy_prompt', [$this, 'copy_prompt']);
        add_action('admin_post_ai_publisher_generate', [$this, 'generate']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_filter('parent_file', [$this, 'highlight_menu_parent']);
        add_filter('submenu_file', [$this, 'highlight_submenu_file']);
    }

    public function assets(string $hook): void {
        if (str_contains($hook, 'hatdat-ai-publisher')) {
            wp_enqueue_style('ai-publisher-admin', AI_PUBLISHER_URL . 'admin/assets/css/admin.css', [], AI_PUBLISHER_VERSION);
            wp_enqueue_script('ai-publisher-admin', AI_PUBLISHER_URL . 'admin/assets/js/admin.js', [], AI_PUBLISHER_VERSION, true);

            $settings = AI_Publisher_Settings::get();
            wp_localize_script(
                'ai-publisher-admin',
                'aiPublisherCostData',
                [
                    'inputUsdPer1m' => (float) $settings['cost_input_usd_per_1m'],
                    'outputUsdPer1m' => (float) $settings['cost_output_usd_per_1m'],
                    'fallbackOutputTokens' => AI_Publisher_Cost_Estimator::DEFAULT_FALLBACK_OUTPUT_TOKENS,
                    'imageSize' => (string) $settings['image_size'],
                    'imageCosts' => AI_Publisher_Cost_Estimator::image_costs(),
                    'labels' => [
                        'estimatedCost' => __('Estimated API cost', 'hatdat-ai-publisher'),
                        'inputTokens' => __('estimated input tokens', 'hatdat-ai-publisher'),
                        'outputTokens' => __('estimated output tokens', 'hatdat-ai-publisher'),
                        'textCost' => __('estimated text cost', 'hatdat-ai-publisher'),
                        'imageCost' => __('estimated image cost', 'hatdat-ai-publisher'),
                        'totalCost' => __('estimated total cost', 'hatdat-ai-publisher'),
                        'busyMessage' => __('Waiting for GPT response…', 'hatdat-ai-publisher'),
                    ],
                ]
            );
        }
    }

    public function admin_notices(): void {
        // Nonce verification is not required here because these values only display admin notices.
        if (!empty($_GET['ai_publisher_notice'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $message = sanitize_textarea_field(wp_unslash((string) $_GET['ai_publisher_notice'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $type = sanitize_key(wp_unslash((string) ($_GET['ai_publisher_notice_type'] ?? 'info'))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $allowed_types = ['info', 'success', 'warning', 'error'];
            $type = in_array($type, $allowed_types, true) ? $type : 'info';
            ?>
            <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
                <p>
                    <?php echo $this->format_notice_message($message); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </p>
            </div>
            <?php
        }

        if (!AI_Publisher_Settings::has_required_openai_consent()) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php
                    echo wp_kses(
                        sprintf(
                            /* translators: %s: Link to the Hatdat AI Publisher settings page. */
                            __('Hatdat AI Publisher requires your confirmation before using the OpenAI API. Please review and accept the OpenAI data processing and cost notices in the %s.', 'hatdat-ai-publisher'),
                            '<a href="' . esc_url(admin_url('admin.php?page=ai-publisher-settings')) . '">' . esc_html__('Hatdat AI Publisher settings', 'hatdat-ai-publisher') . '</a>'
                        ),
                        [
                            'a' => [
                                'href' => [],
                            ],
                        ]
                    );
                    ?>
                </p>
            </div>
            <?php
        }

        if (empty($_GET['ai_publisher_cost_notice'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $message = sanitize_textarea_field(wp_unslash((string) $_GET['ai_publisher_cost_notice'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <?php echo esc_html($message); ?>
            </p>
        </div>
        <?php
    }


    private function format_notice_message(string $message): string {
        $allowed_html = [
            'br' => [],
            'a' => [
                'href' => [],
                'target' => [],
                'rel' => [],
            ],
        ];

        $credits_link = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url('https://platform.openai.com/settings/organization/billing/overview'),
            esc_html__('credits', 'hatdat-ai-publisher')
        );

        $usage_limits_link = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url('https://platform.openai.com/settings/organization/limits'),
            esc_html__('usage limits', 'hatdat-ai-publisher')
        );

        $api_keys_link = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url('https://platform.openai.com/api-keys'),
            esc_html__('API keys', 'hatdat-ai-publisher')
        );

        $escaped_message = esc_html($message);

        $replacements = [
            '{{ai_publisher_credits_link}}' => $credits_link,
            'ai_publisher_credits_link' => $credits_link,
            '{{ai_publisher_usage_limits_link}}' => $usage_limits_link,
            'ai_publisher_usage_limits_link' => $usage_limits_link,
            '{{ai_publisher_api_keys_link}}' => $api_keys_link,
            'ai_publisher_api_keys_link' => $api_keys_link,
        ];

        $output = strtr($escaped_message, $replacements);

        return wp_kses(nl2br($output), $allowed_html);
    }

    public function menu(): void {
        $capability = self::CAPABILITY;

        add_menu_page(
            __('Hatdat AI Publisher', 'hatdat-ai-publisher'),
            __('Hatdat AI Publisher', 'hatdat-ai-publisher'),
            $capability,
            'hatdat-ai-publisher',
            [$this, 'page_generate'],
            'dashicons-edit-page',
            58
        );

        add_submenu_page('hatdat-ai-publisher', __('Generate content', 'hatdat-ai-publisher'), __('Generate content', 'hatdat-ai-publisher'), $capability, 'hatdat-ai-publisher', [$this, 'page_generate']);
        add_submenu_page('hatdat-ai-publisher', __('Prompts', 'hatdat-ai-publisher'), __('Prompts', 'hatdat-ai-publisher'), $capability, 'ai-publisher-prompts', [$this, 'page_prompts']);
        add_submenu_page('hatdat-ai-publisher', __('Settings', 'hatdat-ai-publisher'), __('Settings', 'hatdat-ai-publisher'), $capability, 'ai-publisher-settings', [$this, 'page_settings']);

    }


    public function highlight_menu_parent($parent_file) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($page !== '' && strpos($page, 'hatdat-ai-publisher') === 0) {
            return 'hatdat-ai-publisher';
        }
        return $parent_file;
    }

    public function highlight_submenu_file($submenu_file) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (in_array($page, ['hatdat-ai-publisher', 'ai-publisher-prompts', 'ai-publisher-settings'], true)) {
            return $page;
        }
        return $submenu_file;
    }

    public function page_generate(): void
    {
        $this->require_capability();
        require AI_PUBLISHER_DIR . 'admin/views/generate.phtml';
    }
    public function page_prompts(): void
    {
        $this->require_capability();
        require AI_PUBLISHER_DIR . 'admin/views/prompts.phtml';
    }
    public function page_settings(): void
    {
        $this->require_capability();
        require AI_PUBLISHER_DIR . 'admin/views/settings.phtml';
    }

    private function require_capability(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this Hatdat AI Publisher page.', 'hatdat-ai-publisher'));
        }
    }

    public function save_settings(): void {
        $this->check();
        $post_data = wp_unslash($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in AI_Publisher_Settings::update().
        AI_Publisher_Settings::update($post_data);
        wp_safe_redirect(admin_url('admin.php?page=ai-publisher-settings&updated=1'));
        exit;
    }


    public function check_billing(): void {
        $this->check();
        if (!AI_Publisher_Settings::has_required_openai_consent()) {
            $this->redirect_settings_notice(__('Please confirm the OpenAI data processing and cost notices before checking the API connection.', 'hatdat-ai-publisher'), 'warning');
        }

        $settings = AI_Publisher_Settings::get();
        $client = new AI_Publisher_OpenAI_Client($settings['api_key']);
        $result = $client->check_billing($settings['text_model']);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $message = $result->get_error_message();
            $notice_type = 'error';

            if ($error_code === 'insufficient_quota') {
                $message .= "\n" . __('No active API credit was detected. Text and image generation will fail until billing or credits are fixed.', 'hatdat-ai-publisher');

                if (!empty($settings['disable_image_on_billing_error'])) {
                    AI_Publisher_Settings::disable_image_generation();
                    $message .= "\n" . __('Featured image generation has been disabled automatically to avoid unnecessary failed image requests.', 'hatdat-ai-publisher');
                }
            }

            $this->redirect_settings_notice($message, $notice_type);
        }

        $message = __('OpenAI API check successful. The API key is valid, the configured text model responded, and no quota error was returned.', 'hatdat-ai-publisher');
        $this->redirect_settings_notice($message, 'success');
    }

    public function save_prompt(): void {
        $this->check();
        $id = absint(wp_unslash($_POST['prompt_id'] ?? 0)); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $title = sanitize_text_field(wp_unslash($_POST['prompt_title'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $content = wp_kses_post(wp_unslash($_POST['prompt_content'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        $result = AI_Publisher_Prompt_Manager::save($id, $title, $content);
        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'ai-publisher-prompts',
                'ai_error' => $result->get_error_message(),
            ], admin_url('admin.php')));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=ai-publisher-prompts&updated=1'));
        exit;
    }

    public function delete_prompt(): void {
        $this->check();
        $id = absint(wp_unslash($_POST['prompt_id'] ?? 0)); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $result = $id ? AI_Publisher_Prompt_Manager::delete($id) : new WP_Error('missing_prompt_id', __('Prompt not found.', 'hatdat-ai-publisher'));

        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'ai-publisher-prompts',
                'ai_error' => $result->get_error_message(),
            ], admin_url('admin.php')));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=ai-publisher-prompts&deleted=1'));
        exit;
    }

    public function copy_prompt(): void {
        $this->check();
        $id = absint(wp_unslash($_POST['prompt_id'] ?? 0)); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $result = $id ? AI_Publisher_Prompt_Manager::copy($id) : new WP_Error('missing_prompt_id', __('Prompt not found.', 'hatdat-ai-publisher'));

        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'ai-publisher-prompts',
                'ai_error' => $result->get_error_message(),
            ], admin_url('admin.php')));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=ai-publisher-prompts&edit=' . (int) $result . '&copied=1'));
        exit;
    }

    public function generate(): void {
        $this->check();
        if (!AI_Publisher_Settings::has_required_openai_consent()) {
            $this->redirect_error(__('Please confirm the OpenAI data processing and cost notices in the Hatdat AI Publisher settings before generating content.', 'hatdat-ai-publisher'));
        }

        $settings = AI_Publisher_Settings::get();
        $prompt_id = absint(wp_unslash($_POST['prompt_id'] ?? 0)); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $prompt = AI_Publisher_Prompt_Manager::get($prompt_id);
        if (!$prompt) { $this->redirect_error(__('Prompt not found.', 'hatdat-ai-publisher')); }
        $input = sanitize_textarea_field(wp_unslash($_POST['article_input'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ($input === '') { $this->redirect_error(__('Input is missing.', 'hatdat-ai-publisher')); }

        $settings['default_status'] = sanitize_key(wp_unslash($_POST['post_status'] ?? $settings['default_status'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $settings['default_category'] = absint(wp_unslash($_POST['category'] ?? $settings['default_category'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $settings['seo_provider'] = sanitize_key(wp_unslash($_POST['seo_provider'] ?? $settings['seo_provider'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        $requested_post_type = sanitize_key(wp_unslash($_POST['post_type'] ?? 'post')); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $settings['post_type'] = in_array($requested_post_type, ['post', 'page'], true) ? $requested_post_type : 'post';

        $generate_image = !empty($_POST['generate_image']); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        $language_options = ai_publisher_language_options();
        $content_language = sanitize_text_field(wp_unslash($_POST['content_language'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ($content_language === '' || !isset($language_options[$content_language])) {
            $content_language = ai_publisher_default_content_language();
        }

        $language_instruction = sprintf(
            "

IMPORTANT OUTPUT LANGUAGE RULES:
- The selected output language is: %s.
- Write EVERY user-facing value in the JSON response ONLY in %s.
- This applies to title, slug, excerpt, content, SEO title, SEO description, focus keyword, additional keywords and image prompt.
- Do not use German, English or any other language unless it is the selected output language.
- The administrator prompt and source input may be written in another language. Use them only as instructions or source material, not as output language.
- Do not prepend explanations, notes, meta commentary, summaries or introductory text outside the required JSON.
- Return only the final JSON object.",
            $content_language,
            $content_language
        );

        $client = new AI_Publisher_OpenAI_Client($settings['api_key']);
        $article = $client->generate_article($prompt->post_content . $language_instruction, $input, $settings['text_model']);
        if (is_wp_error($article)) { $this->redirect_error($article->get_error_message()); }

        $usage = $client->get_last_usage();

        $attachment_id = null;
        $image_warning = '';
        $image_was_generated = $generate_image;

        if ($generate_image) {
            $b64 = $client->generate_image($article['image_prompt'], $settings['image_model'], $settings['image_size']);

            if (is_wp_error($b64)) {
                $image_was_generated = false;

                if ($b64->get_error_code() === 'insufficient_quota' && !empty($settings['disable_image_on_billing_error'])) {
                    AI_Publisher_Settings::disable_image_generation();
                    $image_warning = __('Featured image generation failed because no active OpenAI API credit was detected. The post was created without a featured image and image generation was disabled in the plugin settings.', 'hatdat-ai-publisher');
                } else {
                    $this->redirect_error($b64->get_error_message());
                }
            } else {
                $attachment_id = AI_Publisher_Media_Handler::sideload_base64_png($b64, $article['title']);
                if (is_wp_error($attachment_id)) { $this->redirect_error($attachment_id->get_error_message()); }
            }
        }

        $cost_estimate = AI_Publisher_Cost_Estimator::calculate_from_usage($usage, $image_was_generated, $settings);

        $post_id = (new AI_Publisher_Post_Generator())->create_post($article, $settings, $attachment_id);
        if (is_wp_error($post_id)) { $this->redirect_error($post_id->get_error_message()); }

        update_post_meta($post_id, '_ai_publisher_generation_cost', $cost_estimate);

        $cost_notice = trim($image_warning . "\n" . sprintf(
            /* translators: 1: Estimated total API cost, 2: Input token count, 3: Output token count, 4: Estimated image cost. */
            __('Hatdat AI Publisher created the content. Estimated API cost: %1$s. Text tokens: %2$d input / %3$d output. Image estimate: %4$s.', 'hatdat-ai-publisher'),
            AI_Publisher_Cost_Estimator::format_usd((float) $cost_estimate['total_cost']),
            (int) $cost_estimate['input_tokens'],
            (int) $cost_estimate['output_tokens'],
            AI_Publisher_Cost_Estimator::format_usd((float) $cost_estimate['image_cost'])
        ));

        wp_safe_redirect(
            add_query_arg(
                [
                    'post' => $post_id,
                    'action' => 'edit',
                    'ai_publisher_cost_notice' => $cost_notice,
                ],
                admin_url('post.php')
            )
        );
        exit;
    }


    private function redirect_settings_notice(string $message, string $type = 'info'): void {
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'ai-publisher-settings',
                    'ai_publisher_notice' => $message,
                    'ai_publisher_notice_type' => sanitize_key($type),
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    private function check(): void {
        if (!current_user_can(self::CAPABILITY)) { wp_die(esc_html__('You do not have permission to perform this action.', 'hatdat-ai-publisher')); }
        check_admin_referer('ai_publisher_action');
    }

    private function redirect_error(string $message): void {
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'hatdat-ai-publisher',
                    'ai_publisher_notice' => $message,
                    'ai_publisher_notice_type' => 'error',
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }
}
