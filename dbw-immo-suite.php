<?php
/**
 * Plugin Name:       Immo Suite
 * Plugin URI:        https://dennisbuchwald.de/apps/immo-suite
 * Description:       Die Brücke zwischen Maklersoftware und moderner Website. Immo Suite importiert OpenImmo XML, strukturiert Immobilien als sauberen Custom Post Type und sorgt für eine performante, zeitgemäße Darstellung im Frontend.
 * Version:           2.11.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Dennis Buchwald
 * Author URI:        https://dennisbuchwald.de
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dbw-immo-suite
 * Domain Path:       /languages
 * Update URI:        https://dennisbuchwald.de/apps/immo-suite
 */

namespace DBW\ImmoSuite;

if (!defined('ABSPATH')) {
	exit;
}

// Define Constants
define('DBW_IMMO_SUITE_VERSION', '2.11.0');
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
	// Register CPT + taxonomies so the flushed rewrite rules include them
	// (without the taxonomies, /ort/... etc. 404s until permalinks are re-saved)
	$property = new PostTypes\Property();
	$property->register_post_type();

	(new Taxonomies\PropertyType())->register_taxonomy();
	(new Taxonomies\MarketingType())->register_taxonomy();
	(new Taxonomies\Location())->register_taxonomy();

	flush_rewrite_rules();
});

// Deactivation Hook
register_deactivation_hook(__FILE__, function () {
	wp_clear_scheduled_hook('dbw_immo_cron_hook');
	wp_clear_scheduled_hook('dbw_immo_inquiry_cleanup');
	wp_clear_scheduled_hook('dbw_immo_monitor_check');
	wp_clear_scheduled_hook('dbw_immo_media_cleanup');
	wp_clear_scheduled_hook('dbw_immo_weekly_report');
	wp_clear_scheduled_hook('dbw_immo_telemetry_ping');
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

		case 'preis_genau':
			// Keeps cents where they exist: 8000 → "8.000", 65.5 → "65,50".
			// Parking rents are often not round numbers.
			if (fmod($num, 1) == 0) {
				return number_format($num, 0, ',', '.');
			}
			return number_format($num, 2, ',', '.');

		case 'flaeche':
		default:
			return number_format(round($num), 0, ',', '.');
	}
}

/**
 * Human readable label for an OpenImmo parking space type.
 *
 * @param string $art    Type key ('tiefgarage', 'garage', 'carport', ...).
 * @param int    $anzahl Count, decides singular vs. plural.
 * @return string
 */
function dbw_stellplatz_label($art, $anzahl = 1)
{
	// Literal strings inside __() keep the labels extractable for translators.
	$labels = array(
		'carport'    => array(__('Carport', 'dbw-immo-suite'), __('Carports', 'dbw-immo-suite')),
		'duplex'     => array(__('Duplex-Stellplatz', 'dbw-immo-suite'), __('Duplex-Stellplätze', 'dbw-immo-suite')),
		'freiplatz'  => array(__('Stellplatz', 'dbw-immo-suite'), __('Stellplätze', 'dbw-immo-suite')),
		'garage'     => array(__('Garage', 'dbw-immo-suite'), __('Garagen', 'dbw-immo-suite')),
		'parkhaus'   => array(__('Parkhaus-Stellplatz', 'dbw-immo-suite'), __('Parkhaus-Stellplätze', 'dbw-immo-suite')),
		'tiefgarage' => array(__('Tiefgaragenstellplatz', 'dbw-immo-suite'), __('Tiefgaragenstellplätze', 'dbw-immo-suite')),
		'sonstige'   => array(__('Stellplatz', 'dbw-immo-suite'), __('Stellplätze', 'dbw-immo-suite')),
	);

	$key = isset($labels[$art]) ? $art : 'sonstige';
	$idx = ((int) $anzahl === 1) ? 0 : 1;

	return $labels[$key][$idx];
}

/**
 * Build display lines for the parking spaces of a property.
 *
 * OpenImmo carries the price PER space
 * (<preise><stp_tiefgarage anzahl="2" stellplatzkaufpreis="8000"/>), so the
 * "à 8.000 €" wording mirrors the source data instead of silently summing it up.
 * The total is shown separately in the highlights box.
 *
 * @param array $entries Post meta 'stellplatz_preise'.
 * @return string[] e.g. ['2 Tiefgaragenstellplätze à 8.000 € (Kauf)']
 */
function dbw_stellplatz_lines($entries)
{
	if (!is_array($entries) || empty($entries)) {
		return array();
	}

	$lines = array();

	foreach ($entries as $entry) {
		if (!is_array($entry)) {
			continue;
		}

		$anzahl = isset($entry['anzahl']) ? (int) $entry['anzahl'] : 0;
		if ($anzahl < 1) {
			$anzahl = 1;
		}
		$art = isset($entry['art']) ? $entry['art'] : 'sonstige';

		$line = $anzahl . ' ' . dbw_stellplatz_label($art, $anzahl);

		$prices = array();
		$kaufpreis = isset($entry['kaufpreis']) ? (float) $entry['kaufpreis'] : 0;
		$miete     = isset($entry['miete']) ? (float) $entry['miete'] : 0;

		if ($kaufpreis > 0) {
			$prices[] = sprintf(
				/* translators: %s: price per parking space */
				__('%s € (Kauf)', 'dbw-immo-suite'),
				dbw_format_number($kaufpreis, 'preis_genau')
			);
		}
		if ($miete > 0) {
			$prices[] = sprintf(
				/* translators: %s: monthly rent per parking space */
				__('%s € mtl. (Miete)', 'dbw-immo-suite'),
				dbw_format_number($miete, 'preis_genau')
			);
		}

		if (!empty($prices)) {
			$line .= ' ' . __('à', 'dbw-immo-suite') . ' ' . implode(' · ', $prices);
		}

		$lines[] = $line;
	}

	return $lines;
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

// GitHub Update Checker - guarded: a build ZIP without vendor/ must degrade
// to "no auto updates", never fatal the whole site
if (file_exists(DBW_IMMO_SUITE_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php')) {
	require_once DBW_IMMO_SUITE_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';
	$dbw_immo_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/dbwmedia/Immo-Suite/',
		__FILE__,
		'dbw-immo-suite'
	);
	$dbw_immo_update_checker->setBranch('main');
}

// Initialize Plugin
function run_dbw_immo_suite()
{
	check_requirements();
	$plugin = new Core\Plugin();
	$plugin->run();
}
add_action('plugins_loaded', 'DBW\ImmoSuite\run_dbw_immo_suite');