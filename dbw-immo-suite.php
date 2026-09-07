<?php
/**
 * Plugin Name:       Immo Suite
 * Plugin URI:        https://dennisbuchwald.de/apps/immo-suite
 * Description:       Die Brücke zwischen Maklersoftware und moderner Website. Immo Suite importiert OpenImmo XML, strukturiert Immobilien als sauberen Custom Post Type und sorgt für eine performante, zeitgemäße Darstellung im Frontend.
 * Version:           2.13.0
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
define('DBW_IMMO_SUITE_VERSION', '2.13.0');
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
 * May the exact address of this property be shown?
 *
 * Two gates: the site-wide Customizer toggle and the per-object release from
 * OpenImmo (<verwaltung_objekt><objektadresse_freigeben>). A missing field keeps
 * the previous behaviour; only an explicit "no" from the broker hides the street.
 *
 * @param int $post_id
 * @return bool
 */
function dbw_show_address($post_id)
{
	if (!get_theme_mod('dbw_immo_single_show_address', true)) {
		return false;
	}

	return get_post_meta($post_id, 'adresse_freigegeben', true) !== '0';
}

/**
 * Human readable condition (<zustand zustand_art="GEPFLEGT"/>).
 *
 * @param string $raw
 * @return string
 */
function dbw_zustand_label($raw)
{
	$raw = strtoupper(trim((string) $raw));
	if ($raw === '') {
		return '';
	}

	$map = array(
		'ERSTBEZUG'                   => __('Erstbezug', 'dbw-immo-suite'),
		'NEUWERTIG'                   => __('Neuwertig', 'dbw-immo-suite'),
		'GEPFLEGT'                    => __('Gepflegt', 'dbw-immo-suite'),
		'MODERNISIERT'                => __('Modernisiert', 'dbw-immo-suite'),
		'SANIERT'                     => __('Saniert', 'dbw-immo-suite'),
		'TEILSANIERT'                 => __('Teilsaniert', 'dbw-immo-suite'),
		'KERNSANIERT'                 => __('Kernsaniert', 'dbw-immo-suite'),
		'RENOVIERT'                   => __('Renoviert', 'dbw-immo-suite'),
		'TEILRENOVIERT'               => __('Teilrenoviert', 'dbw-immo-suite'),
		'RENOVIERUNGSBEDUERFTIG'      => __('Renovierungsbedürftig', 'dbw-immo-suite'),
		'SANIERUNGSBEDUERFTIG'        => __('Sanierungsbedürftig', 'dbw-immo-suite'),
		'TEIL_VOLLRENOVIERUNGSBED'    => __('Renovierungsbedürftig', 'dbw-immo-suite'),
		'BAUFAELLIG'                  => __('Baufällig', 'dbw-immo-suite'),
		'ENTKERNT'                    => __('Entkernt', 'dbw-immo-suite'),
		'ABRISSOBJEKT'                => __('Abrissobjekt', 'dbw-immo-suite'),
		'PROJEKTIERT'                 => __('Projektiert', 'dbw-immo-suite'),
		'ROHBAU'                      => __('Rohbau', 'dbw-immo-suite'),
	);

	if (isset($map[$raw])) {
		return $map[$raw];
	}

	// Unknown value from an exotic export: show it readable instead of dropping it.
	return ucfirst(strtolower(str_replace('_', ' ', $raw)));
}

/**
 * Object data rows for the detail page, the expose and the backend.
 *
 * One source for all three, so a new field never has to be added in three places.
 * Only fields the broker actually filled show up; a plain "no" is skipped where
 * it carries no information ("Denkmalgeschützt: nein" tells nobody anything).
 *
 * @param int $post_id
 * @return array<int, array{label: string, value: string}>
 */
function dbw_objektdaten($post_id)
{
	$get = function ($key) use ($post_id) {
		return trim((string) get_post_meta($post_id, $key, true));
	};

	$rows = array();
	$add = function ($label, $value) use (&$rows) {
		if (trim((string) $value) !== '') {
			$rows[] = array('label' => $label, 'value' => (string) $value);
		}
	};

	$add(__('Objektnummer', 'dbw-immo-suite'), $get('objektnr_extern'));

	// Etage: "2 von 4" reads better than two separate rows
	$etage = $get('etage');
	$etagen = $get('anzahl_etagen');
	if ($etage !== '') {
		$add(
			__('Etage', 'dbw-immo-suite'),
			$etagen !== '' ? sprintf(__('%1$s von %2$s', 'dbw-immo-suite'), $etage, $etagen) : $etage
		);
	} elseif ($etagen !== '') {
		$add(__('Etagen im Haus', 'dbw-immo-suite'), $etagen);
	}

	$add(__('Zustand', 'dbw-immo-suite'), dbw_zustand_label($get('zustand_art')));

	$alter_map = array(
		'ALTBAU' => __('Altbau', 'dbw-immo-suite'),
		'NEUBAU' => __('Neubau', 'dbw-immo-suite'),
	);
	$alter = strtoupper($get('objekt_alter'));
	$add(__('Bauart', 'dbw-immo-suite'), isset($alter_map[$alter]) ? $alter_map[$alter] : '');

	$add(__('Letzte Modernisierung', 'dbw-immo-suite'), $get('letzte_modernisierung'));
	$add(__('Ausstattungsqualität', 'dbw-immo-suite'), $get('ausstattungsqualitaet'));
	$add(__('Ausrichtung Balkon/Terrasse', 'dbw-immo-suite'), $get('ausrichtung'));

	// Availability: the broker's own wording ("nach Absprache") beats a date
	$verfuegbar = $get('verfuegbar_ab');
	if ($verfuegbar === '') {
		$datum = $get('verfuegbar_ab_datum');
		$ts = $datum !== '' ? strtotime($datum) : false;
		if ($ts) {
			$verfuegbar = date_i18n('d.m.Y', $ts);
		}
	}
	$add(__('Verfügbar ab', 'dbw-immo-suite'), $verfuegbar);

	$haustiere = $get('haustiere');
	if ($haustiere !== '') {
		$add(__('Haustiere', 'dbw-immo-suite'), $haustiere === '1' ? __('Erlaubt', 'dbw-immo-suite') : __('Nicht erlaubt', 'dbw-immo-suite'));
	}

	// Only the "yes" carries information here
	if ($get('vermietet') === '1') {
		$add(__('Vermietet', 'dbw-immo-suite'), __('Ja', 'dbw-immo-suite'));
	}
	if ($get('denkmalgeschuetzt') === '1') {
		$add(__('Denkmalgeschützt', 'dbw-immo-suite'), __('Ja', 'dbw-immo-suite'));
	}
	if ($get('wbs_sozialwohnung') === '1') {
		$add(__('Wohnberechtigungsschein', 'dbw-immo-suite'), __('Erforderlich', 'dbw-immo-suite'));
	}

	return apply_filters('dbw_immo_objektdaten', $rows, $post_id);
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