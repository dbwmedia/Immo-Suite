<?php
/**
 * Plugin Name: Immo Suite
 * Plugin URI: https://dennisbuchwald.de/immo-suite
 * Description: Die Brücke zwischen Maklersoftware und moderner Website. Immo Suite importiert OpenImmo XML, strukturiert Immobilien als sauberen Custom Post Type und sorgt für eine performante, zeitgemäße Darstellung im Frontend.
 * Version: 1.14.2
 * Author: Dennis Buchwald
 * Author URI: https://dennisbuchwald.de
 * Text Domain: dbw-immo-suite
 * Domain Path: /languages
 */

namespace DBW\ImmoSuite;

if (!defined('ABSPATH')) {
	exit;
}

// Define Constants
define('DBW_IMMO_SUITE_VERSION', '1.14.2');
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

// Check required PHP extensions
function check_requirements()
{
	$missing = array();
	if (!class_exists('ZipArchive')) {
		$missing[] = 'zip';
	}
	if (!function_exists('simplexml_load_file')) {
		$missing[] = 'simplexml';
	}
	if (!empty($missing)) {
		add_action('admin_notices', function () use ($missing) {
			echo '<div class="notice notice-error"><p><strong>Immo Suite:</strong> '
				. sprintf(
					__('Fehlende PHP-Erweiterungen: %s. Der OpenImmo-Import wird nicht funktionieren.', 'dbw-immo-suite'),
					'<code>' . implode('</code>, <code>', $missing) . '</code>'
				)
				. '</p></div>';
		});
	}
}

// Global anrede helper shortcut
function dbw_anrede($sie, $du)
{
	return Core\Anrede::pick($sie, $du);
}

/**
 * Format a numeric property value for display.
 *
 * @param mixed  $value Raw value from post meta.
 * @param string $type  'zimmer', 'flaeche', or 'preis'.
 * @return string Formatted string (without unit suffix).
 */
function dbw_format_number($value, $type = 'flaeche')
{
	$num = (float) $value;
	if ($num <= 0) {
		return '';
	}

	switch ($type) {
		case 'zimmer':
			// 3.00 → "3", 2.50 → "2,5"
			if (fmod($num, 1) == 0) {
				return number_format($num, 0, ',', '.');
			}
			return rtrim(rtrim(number_format($num, 1, ',', '.'), '0'), ',');

		case 'preis':
			return number_format($num, 0, ',', '.');

		case 'flaeche':
		default:
			return number_format(round($num), 0, ',', '.');
	}
}

/**
 * Format a phone number for display.
 * Normalizes 0049/+49 prefix, inserts spaces after country code and area/mobile prefix.
 *
 * @param string $raw Raw phone number from OpenImmo.
 * @return array{display: string, tel: string} Display string and tel: URI value.
 */
function dbw_format_phone($raw)
{
	// Strip all non-digit/plus characters
	$digits = preg_replace('/[^0-9+]/', '', $raw);

	// Normalize 0049 → +49
	if (strpos($digits, '0049') === 0) {
		$digits = '+49' . substr($digits, 4);
	}
	// Normalize 49... (without plus, but clearly international)
	if (strpos($digits, '49') === 0 && strlen($digits) > 10) {
		$digits = '+49' . substr($digits, 2);
	}
	// Normalize domestic 0... → +49...
	if (strpos($digits, '0') === 0 && strpos($digits, '+') === false) {
		$digits = '+49' . substr($digits, 1);
	}

	$tel = $digits; // clean for tel: href

	// Format display: +49 XXX XXXXXXXX
	$display = $digits;
	if (strpos($digits, '+49') === 0) {
		$national = substr($digits, 3);
		// Mobile prefixes: 15x, 16x, 17x (3 digits), then rest
		if (preg_match('/^(1[5-7]\d)(\d+)$/', $national, $m)) {
			$display = '+49 ' . $m[1] . ' ' . $m[2];
		}
		// Landline: variable length area code — use first 2-5 digits as area code
		elseif (preg_match('/^(\d{2,5})(\d{4,})$/', $national, $m)) {
			$display = '+49 ' . $m[1] . ' ' . $m[2];
		}
	}

	return array('display' => $display, 'tel' => $tel);
}

// GitHub Update Checker
require_once DBW_IMMO_SUITE_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';
$dbw_immo_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/dbwmedia/dbw-immo-suite/',
	__FILE__,
	'dbw-immo-suite'
);
$dbw_immo_update_checker->setBranch('main');

// Plugin row meta links
add_filter('plugin_row_meta', function ($links, $file) {
	if ($file === plugin_basename(__FILE__)) {
		$links[] = '<a href="https://dbw-media.de" target="_blank">Agentur: dbw media</a>';
	}
	return $links;
}, 10, 2);

// Initialize Plugin
function run_dbw_immo_suite()
{
	check_requirements();
	$plugin = new Core\Plugin();
	$plugin->run();
}
add_action('plugins_loaded', 'DBW\ImmoSuite\run_dbw_immo_suite');