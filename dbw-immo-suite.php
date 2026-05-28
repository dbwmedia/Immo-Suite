<?php
/**
 * Plugin Name: dbw Immo Suite
 * Plugin URI: https://dbw-media.de
 * Description: Die Brücke zwischen Maklersoftware und moderner Website. dbw Immo Suite importiert OpenImmo XML, strukturiert Immobilien als sauberen Custom Post Type und sorgt für eine performante, zeitgemäße Darstellung im Frontend.
 * Version: 1.4.0
 * Author: Dennis Buchwald – dbw media
 * Author URI: https://dbw-media.de
 * Text Domain: dbw-immo-suite
 * Domain Path: /languages
 */

namespace DBW\ImmoSuite;

if (!defined('ABSPATH')) {
	exit;
}

// Define Constants
define('DBW_IMMO_SUITE_VERSION', '1.4.0');
define('DBW_IMMO_SUITE_PATH', plugin_dir_path(__FILE__));
define('DBW_IMMO_SUITE_URL', plugin_dir_url(__FILE__));

// PSR-4 Autoloader
spl_autoload_register(function ($class) {
	$prefix = 'DBW\\ImmoSuite\\';
	$base_dir = DBW_IMMO_SUITE_PATH . 'src/';

	$len = strlen($prefix);
	if (strncmp($prefix, $class, $len) !== 0) {
		return;
	}

	$relative_class = substr($class, $len);
	$file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

	if (file_exists($file)) {
		require $file;
	}
});

// Activation Hook
register_activation_hook(__FILE__, function () {
	// Register CPT so rewrite rules include it
	$property = new PostTypes\Property();
	$property->register_post_type();
	flush_rewrite_rules();
});

// Deactivation Hook
register_deactivation_hook(__FILE__, function () {
	wp_clear_scheduled_hook('dbw_immo_cron_hook');
	flush_rewrite_rules();
});

// Initialize Plugin
function run_dbw_immo_suite()
{
	$plugin = new Core\Plugin();
	$plugin->run();
}
add_action('plugins_loaded', 'DBW\ImmoSuite\run_dbw_immo_suite');