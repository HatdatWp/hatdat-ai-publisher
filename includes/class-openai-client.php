<?php
if (!defined('ABSPATH')) {
    exit;
}


final class AI_Publisher_OpenAI_Client {
    private string $api_key;
    private array $last_usage = [];

    public function __construct(string $api_key) {
        $this->api_key = $api_key;
    }

    public function generate_article(string $system_prompt, string $input, string $model): array|WP_Error {
        if ($this->api_key === '') { return new WP_Error('missing_api_key', __('OpenAI API key is missing.', 'hatdat-ai-publisher')); }

        $schema_prompt = __('Reply only as valid JSON without Markdown. Structure:', 'hatdat-ai-publisher') . "
" .
            '{"title":"","slug":"","excerpt":"","content":"","seo_title":"","seo_description":"","focus_keyword":"","additional_keywords":[],"image_prompt":""}' . "
" .
            __('content must be valid WordPress HTML with H2/H3 headings, paragraphs and links.', 'hatdat-ai-publisher');

        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt . "

" . $schema_prompt],
                ['role' => 'user', 'content' => $input],
            ],
            'response_format' => ['type' => 'json_object'],
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 300,
        ]);

        if (is_wp_error($response)) { return $response; }
        $code = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        if ($code < 200 || $code >= 300) {
            return $this->create_openai_error($raw, 'openai_error');
        }
        $json = json_decode($raw, true);
        $this->last_usage = is_array($json) ? (array) ($json['usage'] ?? []) : [];
        $content = $json['choices'][0]['message']['content'] ?? '';
        return AI_Publisher_JSON_Parser::parse_article($content);
    }

    public function generate_image(string $prompt, string $model, string $size): string|WP_Error {
        if ($this->api_key === '') { return new WP_Error('missing_api_key', __('OpenAI API key is missing.', 'hatdat-ai-publisher')); }
        if (trim($prompt) === '') { return new WP_Error('missing_image_prompt', __('Image prompt is missing.', 'hatdat-ai-publisher')); }

        $body = [
            'model' => $model,
            'prompt' => $prompt,
            'size' => $size,
        ];

        $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 300,
        ]);

        if (is_wp_error($response)) { return $response; }
        $code = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        if ($code < 200 || $code >= 300) {
            return $this->create_openai_error($raw, 'openai_image_error');
        }
        $json = json_decode($raw, true);
        $b64 = $json['data'][0]['b64_json'] ?? '';
        return $b64 ?: new WP_Error('image_decode_error', __('No image data received.', 'hatdat-ai-publisher'));
    }


    public function check_billing(string $model): array|WP_Error {
        if ($this->api_key === '') {
            return new WP_Error('missing_api_key', __('OpenAI API key is missing.', 'hatdat-ai-publisher'));
        }

        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Reply with OK.',
                ],
            ],
            'max_completion_tokens' => 8,
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 300,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            return $this->create_openai_error($raw, 'openai_billing_check_error');
        }

        $json = json_decode($raw, true);
        $this->last_usage = is_array($json) ? (array) ($json['usage'] ?? []) : [];

        return [
            'status' => 'ok',
            'usage' => $this->last_usage,
        ];
    }



    public function get_credit_balance(): array|WP_Error {
        if ($this->api_key === '') {
            return new WP_Error('missing_api_key', __('OpenAI API key is missing.', 'hatdat-ai-publisher'));
        }

        $response = wp_remote_get('https://api.openai.com/dashboard/billing/credit_grants', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 300,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            return $this->create_openai_error($raw, 'openai_credit_balance_error');
        }

        $json = json_decode($raw, true);

        return [
            'total_granted' => (float) ($json['total_granted'] ?? 0),
            'total_used' => (float) ($json['total_used'] ?? 0),
            'total_available' => (float) ($json['total_available'] ?? 0),
        ];
    }

    public function get_last_usage(): array {
        return $this->last_usage;
    }

    private function create_openai_error(string $raw_body, string $fallback_code): WP_Error {
        $data = json_decode($raw_body, true);
        $error = is_array($data) ? ($data['error'] ?? []) : [];

        $openai_message = is_array($error) ? (string) ($error['message'] ?? '') : '';
        $openai_type = is_array($error) ? (string) ($error['type'] ?? '') : '';
        $openai_code = is_array($error) ? (string) ($error['code'] ?? '') : '';
        $effective_code = $openai_code !== '' ? $openai_code : ($openai_type !== '' ? $openai_type : $fallback_code);

        switch ($effective_code) {
            case 'insufficient_quota':
                $message = sprintf(
                    /* translators: 1: Link placeholder for OpenAI credits, 2: Link placeholder for OpenAI usage limits. */
                    __(
                        'OpenAI API quota exceeded. Please check your OpenAI billing status, available %1$s and %2$s.',
                        'hatdat-ai-publisher'
                    ),
                    '{{ai_publisher_credits_link}}',
                    '{{ai_publisher_usage_limits_link}}'
                );
                break;

            case 'invalid_api_key':
            case 'authentication_error':
                $message = sprintf(
                    /* translators: %s: Link placeholder for the OpenAI API keys page. */
                    __(
                        'The OpenAI API key is invalid or was rejected. Please check the API key in the Hatdat AI Publisher settings or create a new key under %s.',
                        'hatdat-ai-publisher'
                    ),
                    '{{ai_publisher_api_keys_link}}'
                );
                break;

            case 'model_not_found':
                $message = __('The selected OpenAI model is not available for this API key or does not exist. Please check the configured text and image models.', 'hatdat-ai-publisher');
                break;

            case 'rate_limit_exceeded':
                $message = sprintf(
                    /* translators: %s: Link placeholder for OpenAI usage limits. */
                    __(
                        'OpenAI rate limit exceeded. Please try again later or check your organization %s.',
                        'hatdat-ai-publisher'
                    ),
                    '{{ai_publisher_usage_limits_link}}'
                );
                break;

            case 'context_length_exceeded':
                $message = __('The request is too long for the selected text model. Please shorten the input or use a model with a larger context window.', 'hatdat-ai-publisher');
                break;

            case 'content_policy_violation':
                $message = __('OpenAI rejected the request because it may violate the content policy. Please change the prompt or article input.', 'hatdat-ai-publisher');
                break;

            case 'server_error':
            case 'service_unavailable':
                $message = __('OpenAI is currently unavailable or returned a server error. Please try again later.', 'hatdat-ai-publisher');
                break;

            case 'invalid_request_error':
                $message = __('OpenAI rejected the request as invalid. Please check the selected model, image size and prompt configuration.', 'hatdat-ai-publisher');
                break;

            default:
                $message = __('OpenAI returned an error.', 'hatdat-ai-publisher');
                if ($openai_message !== '') {
                    $message .= "\n" . sprintf(
                            /* translators: %s: Error details returned by OpenAI. */
                            __('Details: %s', 'hatdat-ai-publisher'),
                            $openai_message
                        );
                }
                break;
        }

        if ($openai_message !== '' && !str_contains($message, $openai_message) && $effective_code !== 'insufficient_quota') {
            $message .= "\n" . sprintf(
                /* translators: %s: Message returned by OpenAI. */
                __('OpenAI message: %s', 'hatdat-ai-publisher'),
                $openai_message
            );
        }

        return new WP_Error($effective_code, $message, [
            'openai_code' => $openai_code,
            'openai_type' => $openai_type,
            'openai_message' => $openai_message,
        ]);
    }



    private function external_link(string $url, string $label): string {
        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($url),
            esc_html($label)
        );
    }

    private function allowed_link_html(): array {
        return [
            'a' => [
                'href' => [],
                'target' => [],
                'rel' => [],
            ],
        ];
    }

}
