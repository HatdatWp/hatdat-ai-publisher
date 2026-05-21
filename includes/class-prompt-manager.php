<?php
if (!defined('ABSPATH')) {
    exit;
}

final class AI_Publisher_Prompt_Manager {
    private const DB_VERSION_OPTION = 'ai_publisher_prompt_db_version';
    private const DB_VERSION = '1.0.10';
    private const SYSTEM_PROMPT_KEY_NEWS = 'news_article';

    public static function maybe_install(): void {
        if (get_option(self::DB_VERSION_OPTION) === self::DB_VERSION) {
            return;
        }

        self::install();
    }

    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            prompt_key VARCHAR(100) NOT NULL DEFAULT '',
            language VARCHAR(10) NOT NULL DEFAULT '',
            title VARCHAR(255) NOT NULL DEFAULT '',
            content LONGTEXT NOT NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY prompt_key (prompt_key),
            KEY language (language),
            KEY is_system (is_system)
        ) {$charset_collate};";

        dbDelta($sql);
        self::seed_system_prompts();
        self::cleanup_legacy_cpt_prompts();
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    public static function create_default_prompt(): void {
        self::install();
    }

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'ai_publisher_prompts';
    }

    public static function all(): array {
        self::maybe_install();
        global $wpdb;

        $prompts = [];
        $system = self::get_system_news_prompt_for_admin_locale();
        if ($system) {
            $prompts[] = $system;
        }

        $table = esc_sql(self::table_name());
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom prompt table used only in admin; caching would risk stale prompt lists.
            "SELECT * FROM {$table} WHERE is_system = 0 ORDER BY title ASC, id ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated internally from the WordPress prefix and escaped.
        );

        foreach ($rows ?: [] as $row) {
            $prompts[] = self::row_to_prompt($row);
        }

        return $prompts;
    }

    public static function get(int $id): ?object {
        self::maybe_install();
        global $wpdb;

        $table = esc_sql(self::table_name());
        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom prompt table lookup.
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated internally from the WordPress prefix and escaped.
                $id
            )
        );

        return $row ? self::row_to_prompt($row) : null;
    }

    public static function save(int $id, string $title, string $content): int|WP_Error {
        self::maybe_install();
        global $wpdb;

        $title = trim($title);
        $content = trim($content);

        if ($title === '' || $content === '') {
            return new WP_Error('missing_prompt_data', __('Prompt title and content are required.', 'hatdat-ai-publisher'));
        }

        if ($id > 0) {
            $existing = self::get($id);
            if (!$existing) {
                return new WP_Error('prompt_not_found', __('Prompt not found.', 'hatdat-ai-publisher'));
            }
            if (!empty($existing->is_system)) {
                return new WP_Error('system_prompt_locked', __('The standard prompt cannot be edited. Please create a copy and edit the copy instead.', 'hatdat-ai-publisher'));
            }

            $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom prompt table write.
                self::table_name(),
                [
                    'title' => $title,
                    'content' => $content,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            return $updated === false ? new WP_Error('prompt_save_failed', __('Prompt could not be saved.', 'hatdat-ai-publisher')) : $id;
        }

        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom prompt table write.
            self::table_name(),
            [
                'prompt_key' => '',
                'language' => '',
                'title' => $title,
                'content' => $content,
                'is_system' => 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return $inserted === false ? new WP_Error('prompt_save_failed', __('Prompt could not be saved.', 'hatdat-ai-publisher')) : (int) $wpdb->insert_id;
    }

    public static function delete(int $id): bool|WP_Error {
        self::maybe_install();
        global $wpdb;

        $existing = self::get($id);
        if (!$existing) {
            return new WP_Error('prompt_not_found', __('Prompt not found.', 'hatdat-ai-publisher'));
        }
        if (!empty($existing->is_system)) {
            return new WP_Error('system_prompt_locked', __('The standard prompt cannot be deleted.', 'hatdat-ai-publisher'));
        }

        return $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom prompt table write.
            self::table_name(),
            ['id' => $id],
            ['%d']
        ) !== false;
    }

    public static function copy(int $id): int|WP_Error {
        self::maybe_install();
        global $wpdb;

        $existing = self::get($id);
        if (!$existing) {
            return new WP_Error('prompt_not_found', __('Prompt not found.', 'hatdat-ai-publisher'));
        }

        $title = sprintf(
            /* translators: %s: Existing prompt title. */
            __('Copy of %s', 'hatdat-ai-publisher'),
            $existing->post_title
        );
        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom prompt table write.
            self::table_name(),
            [
                'prompt_key' => '',
                'language' => (string) ($existing->language ?? ''),
                'title' => $title,
                'content' => (string) $existing->post_content,
                'is_system' => 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return $inserted === false ? new WP_Error('prompt_copy_failed', __('Prompt could not be copied.', 'hatdat-ai-publisher')) : (int) $wpdb->insert_id;
    }

    private static function get_system_news_prompt_for_admin_locale(): ?object {
        global $wpdb;

        $language = self::locale_to_supported_language(function_exists('get_user_locale') ? (string) get_user_locale() : 'en_US');
        $fallback = 'en';

        $table = esc_sql(self::table_name());
        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom prompt table lookup.
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE is_system = 1 AND prompt_key = %s AND language = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated internally from the WordPress prefix and escaped.
                self::SYSTEM_PROMPT_KEY_NEWS,
                $language
            )
        );

        if (!$row && $language !== $fallback) {
            $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom prompt table lookup.
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE is_system = 1 AND prompt_key = %s AND language = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated internally from the WordPress prefix and escaped.
                    self::SYSTEM_PROMPT_KEY_NEWS,
                    $fallback
                )
            );
        }

        return $row ? self::row_to_prompt($row) : null;
    }

    private static function seed_system_prompts(): void {
        foreach (self::system_news_prompts() as $language => $data) {
            self::upsert_system_prompt(self::SYSTEM_PROMPT_KEY_NEWS, $language, $data['title'], $data['content']);
        }
    }

    private static function upsert_system_prompt(string $key, string $language, string $title, string $content): void {
        global $wpdb;

        $table = esc_sql(self::table_name());
        $existing_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom prompt table lookup before upsert.
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE is_system = 1 AND prompt_key = %s AND language = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated internally from the WordPress prefix and escaped.
                $key,
                $language
            )
        );

        $data = [
            'prompt_key' => $key,
            'language' => $language,
            'title' => $title,
            'content' => $content,
            'is_system' => 1,
            'updated_at' => current_time('mysql'),
        ];

        if ($existing_id > 0) {
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom prompt table write.
                $table,
                $data,
                ['id' => $existing_id],
                ['%s', '%s', '%s', '%s', '%d', '%s'],
                ['%d']
            );
            return;
        }

        $data['created_at'] = current_time('mysql');
        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom prompt table write.
            $table,
            $data,
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );
    }

    private static function cleanup_legacy_cpt_prompts(): void {
        if (get_option('ai_publisher_legacy_cpt_prompts_removed')) {
            return;
        }

        global $wpdb;

        $legacy_post_type = 'ai_pub_prompt';
        $posts_table = esc_sql($wpdb->posts);
        $legacy_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup during migration.
            $wpdb->prepare(
                "SELECT ID FROM {$posts_table} WHERE post_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress posts table name is generated by WordPress and escaped.
                $legacy_post_type
            )
        );

        foreach ($legacy_ids ?: [] as $legacy_id) {
            wp_delete_post((int) $legacy_id, true);
        }

        delete_option('ai_publisher_legacy_prompts_migrated');
        update_option('ai_publisher_legacy_cpt_prompts_removed', 1, false);
    }

    private static function row_to_prompt(object $row): object {
        return (object) [
            'ID' => (int) $row->id,
            'post_title' => (string) $row->title,
            'post_content' => (string) $row->content,
            'prompt_key' => (string) $row->prompt_key,
            'language' => (string) $row->language,
            'is_system' => (int) $row->is_system,
            'created_at' => (string) $row->created_at,
            'updated_at' => (string) $row->updated_at,
        ];
    }

    private static function locale_to_supported_language(string $locale): string {
        $code = strtolower(substr(str_replace('-', '_', trim($locale)), 0, 2));
        return in_array($code, ['de', 'en', 'fr', 'es'], true) ? $code : 'en';
    }

    private static function system_news_prompts(): array {
        return [
            'de' => [
                'title' => 'News Artikel',
                'content' => <<<'PROMPT'
Du bist ein KI-Textgenerator für News-Artikel für ein seriöses Informationsportal.

Ich gebe dir entweder eine URL oder ein Thema in wenigen Worten. Deine Aufgabe ist es, daraus einen eigenständigen, ausführlichen und gut lesbaren WordPress-Beitrag zu erstellen.

Richtlinien:
- Verwende keine rechtlich riskanten Formulierungen.
- Baue interne und externe Links in den Fließtext ein. Wenn die Ziel-URL unbekannt ist, verwende href="#".
- Falls Leser direkt angesprochen werden, verwende einen informellen, aber respektvollen Ton.
- Verwende H2-Überschriften.
- Verwende keine Emojis.
- Liefere SEO-Daten für Rank Math oder Yoast.
- Erstelle zusätzlich einen Bildprompt für ein seriöses, generisches Beitragsbild im Format 16:9.
PROMPT,
            ],
            'en' => [
                'title' => 'News article',
                'content' => <<<'PROMPT'
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
PROMPT,
            ],
            'fr' => [
                'title' => 'Article d’actualité',
                'content' => <<<'PROMPT'
Tu es un générateur de texte IA pour des articles d’actualité destinés à un portail d’information sérieux.

Je te fournirai soit une URL, soit un sujet en quelques mots. Ta tâche consiste à créer à partir de cela un article WordPress autonome, détaillé et facile à lire.

Consignes:
- N’utilise pas de formulations juridiquement risquées.
- Intègre des liens internes et externes dans le corps du texte. Si l’URL cible est inconnue, utilise href="#".
- Si tu t’adresses directement aux lecteurs, utilise un ton informel mais respectueux.
- Utilise des titres H2.
- N’utilise pas d’emojis.
- Fournis des données SEO pour Rank Math ou Yoast.
- Crée également un prompt d’image pour une image mise en avant sérieuse et générique au format 16:9.
PROMPT,
            ],
            'es' => [
                'title' => 'Artículo de noticias',
                'content' => <<<'PROMPT'
Eres un generador de textos con IA para artículos de noticias destinados a un portal de información serio.

Te proporcionaré una URL o un tema en pocas palabras. Tu tarea es crear a partir de ello una entrada de WordPress independiente, detallada y fácil de leer.

Directrices:
- No utilices formulaciones jurídicamente arriesgadas.
- Incluye enlaces internos y externos en el cuerpo del texto. Si no se conoce la URL de destino, usa href="#".
- Si te diriges directamente a los lectores, usa un tono informal pero respetuoso.
- Usa encabezados H2.
- No uses emojis.
- Proporciona datos SEO para Rank Math o Yoast.
- Crea también un prompt de imagen para una imagen destacada seria y genérica en formato 16:9.
PROMPT,
            ],
        ];
    }
}
