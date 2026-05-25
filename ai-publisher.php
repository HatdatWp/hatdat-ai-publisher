<?php
/**
 * Plugin Name: Hatdat AI Publisher
 * Plugin URI: https://hatdat.de/ai-publisher-smart-ai-content-publishing-for-wordpress/
 * Description: AI-assisted creation of WordPress posts including featured images and SEO metadata.
 * Version: 1.1.6
 * Author: Peter Liebetrau
 * Author URI: https://hatdat.de
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Text Domain: hatdat-ai-publisher
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AI_PUBLISHER_VERSION', '1.1.5');
define('AI_PUBLISHER_FILE', __FILE__);
define('AI_PUBLISHER_DIR', plugin_dir_path(__FILE__));
define('AI_PUBLISHER_URL', plugin_dir_url(__FILE__));

require_once AI_PUBLISHER_DIR . 'includes/helpers.php';
require_once AI_PUBLISHER_DIR . 'includes/class-plugin.php';

register_activation_hook(__FILE__, ['AI_Publisher_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['AI_Publisher_Plugin', 'deactivate']);

add_action('plugins_loaded', static function () {
    AI_Publisher_Plugin::instance()->run();
});
