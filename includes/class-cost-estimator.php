<?php
if (!defined('ABSPATH')) {
    exit;
}

final class AI_Publisher_Cost_Estimator {
    public const DEFAULT_INPUT_USD_PER_1M = 1.25;
    public const DEFAULT_OUTPUT_USD_PER_1M = 10.00;
    public const DEFAULT_FALLBACK_OUTPUT_TOKENS = 2500;

    public static function image_costs(): array {
        return [
            '1024x1024' => 0.04,
            '1536x1024' => 0.08,
            '1792x1024' => 0.12,
            '1920x1088' => 0.14,
            '1024x1536' => 0.08,
        ];
    }

    public static function estimate_from_character_count(int $characters, bool $include_image, array $settings): array {
        $input_tokens = self::estimate_tokens_from_characters($characters);
        $output_tokens = self::DEFAULT_FALLBACK_OUTPUT_TOKENS;

        return self::calculate($input_tokens, $output_tokens, $include_image, $settings);
    }

    public static function estimate_tokens_from_characters(int $characters): int {
        return max(1, (int) ceil($characters / 4));
    }

    public static function calculate_from_usage(array $usage, bool $include_image, array $settings): array {
        $input_tokens = absint($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0);
        $output_tokens = absint($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0);

        return self::calculate($input_tokens, $output_tokens, $include_image, $settings);
    }

    public static function calculate(int $input_tokens, int $output_tokens, bool $include_image, array $settings): array {
        $input_rate = (float) ($settings['cost_input_usd_per_1m'] ?? self::DEFAULT_INPUT_USD_PER_1M);
        $output_rate = (float) ($settings['cost_output_usd_per_1m'] ?? self::DEFAULT_OUTPUT_USD_PER_1M);
        $image_size = (string) ($settings['image_size'] ?? '1920x1088');
        $image_costs = self::image_costs();
        $image_cost = $include_image ? (float) ($image_costs[$image_size] ?? 0.00) : 0.00;
        $text_cost = ($input_tokens / 1000000 * $input_rate) + ($output_tokens / 1000000 * $output_rate);
        $total_cost = $text_cost + $image_cost;

        return [
            'input_tokens' => $input_tokens,
            'output_tokens' => $output_tokens,
            'text_cost' => $text_cost,
            'image_cost' => $image_cost,
            'total_cost' => $total_cost,
            'image_size' => $image_size,
            'input_rate' => $input_rate,
            'output_rate' => $output_rate,
        ];
    }

    public static function format_usd(float $amount): string {
        return '$' . number_format_i18n($amount, 4);
    }
}
