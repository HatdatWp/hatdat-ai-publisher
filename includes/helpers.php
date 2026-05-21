<?php
if (!defined('ABSPATH')) {
    exit;
}

function ai_publisher_default_prompt(): string {
    return <<<'PROMPT'
You are an AI text generator for news articles for a serious information portal.

I will provide either a URL or a topic in a few words. Your task is to create an independent, detailed and easy-to-read WordPress post from it.

Guidelines:
- Do not use legally risky wording.
- Include internal and external links in the body text. If the href is unknown, use href="#".
- If the reader is addressed directly, use an informal but respectful tone.
- Use H2 headings.
- Do not use emojis.
- Provide SEO data for Rank Math or Yoast.
- Also create an image prompt for a serious, generic 16:9 featured image.
PROMPT;
}

function ai_publisher_locale_to_language_name(string $locale): string {
    $locale = str_replace('-', '_', trim($locale));
    $map = [
        'de' => __('German', 'hatdat-ai-publisher'),
        'en' => __('English', 'hatdat-ai-publisher'),
        'fr' => __('French', 'hatdat-ai-publisher'),
        'es' => __('Spanish', 'hatdat-ai-publisher'),
        'it' => __('Italian', 'hatdat-ai-publisher'),
        'nl' => __('Dutch', 'hatdat-ai-publisher'),
        'pt' => __('Portuguese', 'hatdat-ai-publisher'),
        'pl' => __('Polish', 'hatdat-ai-publisher'),
        'tr' => __('Turkish', 'hatdat-ai-publisher'),
        'ru' => __('Russian', 'hatdat-ai-publisher'),
        'uk' => __('Ukrainian', 'hatdat-ai-publisher'),
        'ar' => __('Arabic', 'hatdat-ai-publisher'),
        'zh' => __('Chinese', 'hatdat-ai-publisher'),
        'ja' => __('Japanese', 'hatdat-ai-publisher'),
        'ko' => __('Korean', 'hatdat-ai-publisher'),
        'hi' => __('Hindi', 'hatdat-ai-publisher'),
        'sv' => __('Swedish', 'hatdat-ai-publisher'),
        'da' => __('Danish', 'hatdat-ai-publisher'),
        'fi' => __('Finnish', 'hatdat-ai-publisher'),
        'no' => __('Norwegian', 'hatdat-ai-publisher'),
        'cs' => __('Czech', 'hatdat-ai-publisher'),
        'el' => __('Greek', 'hatdat-ai-publisher'),
        'he' => __('Hebrew', 'hatdat-ai-publisher'),
        'id' => __('Indonesian', 'hatdat-ai-publisher'),
        'vi' => __('Vietnamese', 'hatdat-ai-publisher'),
        'th' => __('Thai', 'hatdat-ai-publisher'),
    ];

    $code = strtolower(substr($locale, 0, 2));
    return $map[$code] ?? strtoupper($locale);
}

function ai_publisher_language_options(): array {
    $site_locale = function_exists('get_locale') ? (string) get_locale() : 'en_US';
    $user_locale = function_exists('get_user_locale') ? (string) get_user_locale() : $site_locale;

    $options = [];
    $add = static function (string $value, string $label) use (&$options): void {
        $value = trim($value);
        if ($value === '' || isset($options[$value])) {
            return;
        }
        $options[$value] = $label;
    };

    $site_name = ai_publisher_locale_to_language_name($site_locale);
    $user_name = ai_publisher_locale_to_language_name($user_locale);

    $add($site_name, sprintf(
        /* translators: %s: Human-readable site language name. */
        __('Site language: %s', 'hatdat-ai-publisher'),
        $site_name
    ));
    $add($user_name, sprintf(
        /* translators: %s: Human-readable user/admin language name. */
        __('User language: %s', 'hatdat-ai-publisher'),
        $user_name
    ));

    $languages = [
        'English', 'German', 'French', 'Spanish', 'Italian', 'Dutch', 'Portuguese', 'Polish', 'Turkish',
        'Russian', 'Ukrainian', 'Arabic', 'Chinese', 'Japanese', 'Korean', 'Hindi', 'Swedish', 'Danish',
        'Finnish', 'Norwegian', 'Czech', 'Greek', 'Hebrew', 'Indonesian', 'Vietnamese', 'Thai',
        'Romanian', 'Hungarian', 'Bulgarian', 'Croatian', 'Serbian', 'Slovak', 'Slovenian', 'Lithuanian',
        'Latvian', 'Estonian', 'Malay', 'Filipino', 'Bengali', 'Urdu', 'Persian', 'Swahili'
    ];

    foreach ($languages as $language) {
        $add($language, $language);
    }

    return $options;
}

function ai_publisher_default_content_language(): string {
    return ai_publisher_locale_to_language_name(function_exists('get_locale') ? (string) get_locale() : 'en_US');
}

function ai_publisher_allowed_content_html(): array {
    $allowed = wp_kses_allowed_html('post');
    $allowed['a']['href'] = true;
    $allowed['a']['target'] = true;
    $allowed['a']['rel'] = true;
    return $allowed;
}
