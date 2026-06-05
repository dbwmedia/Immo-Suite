<?php

namespace DBW\ImmoSuite\Admin;

if (!defined('ABSPATH')) { exit; }

/**
 * Settings Page for DBW ImmoSuite
 */
class Settings
{

	private $option_group = 'dbw_immo_suite_options';
	private $option_name = 'dbw_immo_suite_settings';

	public function init()
	{
		add_action('admin_menu', array($this, 'add_plugin_page'));
		add_action('admin_init', array($this, 'page_init'));
		add_action('wp_ajax_dbw_immo_validate_path', array($this, 'ajax_validate_path'));
		add_action('update_option_dbw_immo_suite_settings', array($this, 'on_settings_update'), 10, 2);
		add_action('admin_notices', array($this, 'anrede_changed_notice'));
	}

	public function add_plugin_page()
	{
		add_submenu_page(
			'edit.php?post_type=immobilie',
			__('ImmoSuite Einstellungen', 'dbw-immo-suite'),
			__('Einstellungen', 'dbw-immo-suite'),
			'manage_options',
			'dbw-immo-suite-settings',
			array($this, 'create_admin_page')
		);
	}

	public function create_admin_page()
	{
		$tabs = array(
			'import'      => __('Import', 'dbw-immo-suite'),
			'display'     => __('Darstellung', 'dbw-immo-suite'),
			'calculator'  => __('Rechner', 'dbw-immo-suite'),
			'references'  => __('Referenzen & Verkauf', 'dbw-immo-suite'),
			'seo'         => __('Maklerfirma (SEO)', 'dbw-immo-suite'),
			'shortcodes'  => __('Shortcodes', 'dbw-immo-suite'),
			'license'     => __('Lizenz', 'dbw-immo-suite'),
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>

			<nav class="nav-tab-wrapper dbw-settings-tabs">
				<?php foreach ($tabs as $slug => $label) : ?>
					<a href="#tab-<?php echo esc_attr($slug); ?>"
					   class="nav-tab<?php echo ($slug === 'import') ? ' nav-tab-active' : ''; ?>"
					   data-tab="<?php echo esc_attr($slug); ?>">
						<?php echo esc_html($label); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields($this->option_group); ?>

				<div class="dbw-tab-panel" id="tab-import">
					<?php do_settings_sections('dbw-settings-import'); ?>
				</div>

				<div class="dbw-tab-panel" id="tab-display" style="display:none;">
					<?php do_settings_sections('dbw-settings-display'); ?>
				</div>

				<div class="dbw-tab-panel" id="tab-calculator" style="display:none;">
					<?php do_settings_sections('dbw-settings-calculator'); ?>
				</div>

				<div class="dbw-tab-panel" id="tab-references" style="display:none;">
					<?php do_settings_sections('dbw-settings-references'); ?>
				</div>

				<div class="dbw-tab-panel" id="tab-seo" style="display:none;">
					<?php do_settings_sections('dbw-settings-seo'); ?>
				</div>

				<?php submit_button(); ?>
			</form>

			<div class="dbw-tab-panel" id="tab-shortcodes" style="display:none;">
				<?php $this->render_shortcode_reference(); ?>
			</div>

			<div class="dbw-tab-panel" id="tab-license" style="display:none;">
				<?php $this->render_license_tab(); ?>
			</div>
		</div>

		<script>
		(function() {
			var tabs = document.querySelectorAll('.dbw-settings-tabs .nav-tab');
			var panels = document.querySelectorAll('.dbw-tab-panel');

			function activate(slug) {
				tabs.forEach(function(t) {
					t.classList.toggle('nav-tab-active', t.dataset.tab === slug);
				});
				panels.forEach(function(p) {
					p.style.display = (p.id === 'tab-' + slug) ? '' : 'none';
				});
				// Hide submit button on non-form tabs
				var submit = document.querySelector('.wrap .submit');
				if (submit) submit.style.display = (slug === 'shortcodes' || slug === 'license') ? 'none' : '';
			}

			tabs.forEach(function(tab) {
				tab.addEventListener('click', function(e) {
					e.preventDefault();
					var slug = this.dataset.tab;
					activate(slug);
					history.replaceState(null, '', '#tab-' + slug);
				});
			});

			// Restore tab from hash
			var hash = window.location.hash.replace('#tab-', '');
			if (hash && document.getElementById('tab-' + hash)) {
				activate(hash);
			}
		})();
		</script>
		<?php
	}

	/**
	 * Render the shortcode reference table (read-only, no form).
	 */
	private function render_shortcode_reference()
	{
		?>
		<h2><?php esc_html_e('Shortcode-Referenz', 'dbw-immo-suite'); ?></h2>
		<p><?php esc_html_e('Diese Shortcodes koennen in Elementor, Classic Editor oder jedem Page Builder verwendet werden:', 'dbw-immo-suite'); ?></p>

		<table class="wp-list-table widefat fixed striped" style="max-width: 900px;">
			<thead>
				<tr>
					<th style="width: 35%;"><?php esc_html_e('Shortcode', 'dbw-immo-suite'); ?></th>
					<th><?php esc_html_e('Beschreibung', 'dbw-immo-suite'); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>[dbw_immo_grid]</code></td>
					<td><?php esc_html_e('Zeigt aktuelle Immobilien im Grid an.', 'dbw-immo-suite'); ?></td>
				</tr>
				<tr>
					<td><code>[dbw_immo_grid count="6" columns="3"]</code></td>
					<td><?php esc_html_e('6 Immobilien in 3 Spalten.', 'dbw-immo-suite'); ?></td>
				</tr>
				<tr>
					<td><code>[dbw_immo_grid location="muenchen"]</code></td>
					<td><?php esc_html_e('Nur Immobilien in Muenchen (Ort-Slug). Ideal fuer Geo-Landing-Pages.', 'dbw-immo-suite'); ?></td>
				</tr>
				<tr>
					<td><code>[dbw_immo_grid marketing="kauf" type="haus"]</code></td>
					<td><?php esc_html_e('Nur Haeuser zum Kauf.', 'dbw-immo-suite'); ?></td>
				</tr>
				<tr>
					<td><code>[dbw_immo_grid highlights="yes"]</code></td>
					<td><?php esc_html_e('Nur als Highlight markierte Immobilien.', 'dbw-immo-suite'); ?></td>
				</tr>
				<tr>
					<td><code>[dbw_immo_references]</code></td>
					<td><?php esc_html_e('Zeigt verkaufte/Referenz-Objekte an.', 'dbw-immo-suite'); ?></td>
				</tr>
				<tr>
					<td><code>[dbw_immo_references location="muenchen"]</code></td>
					<td><?php esc_html_e('Referenzen nur aus Muenchen. Ideal fuer Geo-Landing-Pages.', 'dbw-immo-suite'); ?></td>
				</tr>
				<tr>
					<td><code>[dbw_immo_references count="6" columns="2" status="verkauft"]</code></td>
					<td><?php esc_html_e('6 verkaufte Objekte in 2 Spalten (ohne Referenzen).', 'dbw-immo-suite'); ?></td>
				</tr>
			</tbody>
		</table>

		<p class="description" style="margin-top: 10px;">
			<?php esc_html_e('Tipp: Im Gutenberg-Editor stehen diese Funktionen auch als native Bloecke unter "dbw Immo Suite" zur Verfuegung.', 'dbw-immo-suite'); ?>
		</p>
		<?php
	}

	/**
	 * Render the license activation tab.
	 */
	private function render_license_tab()
	{
		$is_valid = \DBW\ImmoSuite\Core\License::is_valid();
		$stored_key = get_option(\DBW\ImmoSuite\Core\License::OPTION_KEY, '');

		// Feedback messages
		if (isset($_GET['dbw_license'])) {
			if ($_GET['dbw_license'] === 'activated') {
				echo '<div class="notice notice-success inline"><p>' . esc_html__('Lizenz erfolgreich aktiviert!', 'dbw-immo-suite') . '</p></div>';
			} elseif ($_GET['dbw_license'] === 'invalid') {
				echo '<div class="notice notice-error inline"><p>' . esc_html__('Ungültiger Lizenzschlüssel.', 'dbw-immo-suite') . '</p></div>';
			}
		}
		?>
		<h2><?php esc_html_e('Lizenz', 'dbw-immo-suite'); ?></h2>

		<?php if ($is_valid) : ?>
			<div style="background:#d4edda; border-left:4px solid #00a32a; padding:12px 16px; margin-bottom:16px; border-radius:0 4px 4px 0;">
				<strong><?php esc_html_e('Lizenz aktiv', 'dbw-immo-suite'); ?></strong><br>
				<code><?php echo esc_html($stored_key); ?></code>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('dbw_immo_license_activate'); ?>
			<input type="hidden" name="action" value="dbw_immo_activate_license">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="dbw_immo_license_key"><?php esc_html_e('Lizenzschlüssel', 'dbw-immo-suite'); ?></label></th>
					<td>
						<input type="text" id="dbw_immo_license_key" name="dbw_immo_license_key"
							   value="<?php echo esc_attr($stored_key); ?>"
							   class="regular-text" placeholder="DBWIS-XXXX-XXXX-XXXX" style="font-family:monospace;" />
					</td>
				</tr>
			</table>
			<?php submit_button(__('Lizenz aktivieren', 'dbw-immo-suite'), 'primary', 'submit', false); ?>
		</form>
		<?php
	}

	public function page_init()
	{
		register_setting(
			$this->option_group,
			$this->option_name,
			array($this, 'sanitize')
		);

		// ── Tab 1: Import ──
		add_settings_section('section_import', __('OpenImmo Import Einstellungen', 'dbw-immo-suite'), array($this, 'print_section_info'), 'dbw-settings-import');
		add_settings_field('xml_path', __('Pfad zu XML-Dateien', 'dbw-immo-suite'), array($this, 'xml_path_callback'), 'dbw-settings-import', 'section_import');
		add_settings_field('cpt_slug', __('URL Slug (Permalink)', 'dbw-immo-suite'), array($this, 'cpt_slug_callback'), 'dbw-settings-import', 'section_import');
		add_settings_field('enable_garbage_collection', __('Garbage Collection (Full Sync)', 'dbw-immo-suite'), array($this, 'enable_garbage_collection_callback'), 'dbw-settings-import', 'section_import');

		// ── Tab 2: Darstellung ──
		add_settings_section('section_display', __('Darstellung', 'dbw-immo-suite'), array($this, 'print_display_section_info'), 'dbw-settings-display');
		add_settings_field('anrede', __('Anrede', 'dbw-immo-suite'), array($this, 'anrede_callback'), 'dbw-settings-display', 'section_display');
		add_settings_field('grayscale_sold', __('Grayscale bei Verkauft', 'dbw-immo-suite'), array($this, 'grayscale_sold_callback'), 'dbw-settings-display', 'section_display');
		add_settings_field('grayscale_reserved', __('Grayscale bei Reserviert', 'dbw-immo-suite'), array($this, 'grayscale_reserved_callback'), 'dbw-settings-display', 'section_display');

		// ── Preis pro m² ──
		add_settings_section('section_price_sqm', __('Preis pro m²', 'dbw-immo-suite'), array($this, 'print_price_sqm_section_info'), 'dbw-settings-display');
		add_settings_field('show_price_per_sqm', __('Preis/m² anzeigen', 'dbw-immo-suite'), array($this, 'show_price_per_sqm_callback'), 'dbw-settings-display', 'section_price_sqm');
		add_settings_field('show_price_per_sqm_comparison', __('Vergleich anzeigen', 'dbw-immo-suite'), array($this, 'show_price_per_sqm_comparison_callback'), 'dbw-settings-display', 'section_price_sqm');
		add_settings_field('show_price_per_sqm_archive', __('Auf Karten anzeigen', 'dbw-immo-suite'), array($this, 'show_price_per_sqm_archive_callback'), 'dbw-settings-display', 'section_price_sqm');
		add_settings_field('price_per_sqm_min_comparables', __('Mind. Vergleichsobjekte', 'dbw-immo-suite'), array($this, 'price_per_sqm_min_comparables_callback'), 'dbw-settings-display', 'section_price_sqm');
		add_settings_field('price_per_sqm_cache_hours', __('Cache-Dauer (Stunden)', 'dbw-immo-suite'), array($this, 'price_per_sqm_cache_hours_callback'), 'dbw-settings-display', 'section_price_sqm');

		// ── WhatsApp ──
		add_settings_section('section_whatsapp', __('WhatsApp', 'dbw-immo-suite'), array($this, 'print_whatsapp_section_info'), 'dbw-settings-display');
		add_settings_field('whatsapp_enabled', __('Aktivieren', 'dbw-immo-suite'), array($this, 'whatsapp_enabled_callback'), 'dbw-settings-display', 'section_whatsapp');
		add_settings_field('whatsapp_floating', __('Floating-Button', 'dbw-immo-suite'), array($this, 'whatsapp_floating_callback'), 'dbw-settings-display', 'section_whatsapp');
		add_settings_field('whatsapp_number_override', __('Globale WhatsApp-Nummer', 'dbw-immo-suite'), array($this, 'whatsapp_number_override_callback'), 'dbw-settings-display', 'section_whatsapp');
		add_settings_field('whatsapp_cta_text', __('Button-Text', 'dbw-immo-suite'), array($this, 'whatsapp_cta_text_callback'), 'dbw-settings-display', 'section_whatsapp');
		add_settings_field('whatsapp_message_template', __('Nachrichtenvorlage', 'dbw-immo-suite'), array($this, 'whatsapp_message_template_callback'), 'dbw-settings-display', 'section_whatsapp');

		// ── Tab 3: Finanzierungsrechner ──
		add_settings_section('section_calculator', __('Kaufnebenkosten & Finanzierung', 'dbw-immo-suite'), array($this, 'print_calculator_section_info'), 'dbw-settings-calculator');
		add_settings_field('calc_notar_percent', __('Notarkosten (%)', 'dbw-immo-suite'), array($this, 'calc_notar_callback'), 'dbw-settings-calculator', 'section_calculator');
		add_settings_field('calc_grundbuch_percent', __('Grundbuchamt (%)', 'dbw-immo-suite'), array($this, 'calc_grundbuch_callback'), 'dbw-settings-calculator', 'section_calculator');
		add_settings_field('calc_default_zinssatz', __('Zinssatz Standard (%)', 'dbw-immo-suite'), array($this, 'calc_zinssatz_callback'), 'dbw-settings-calculator', 'section_calculator');
		add_settings_field('calc_default_tilgung', __('Tilgung Standard (%)', 'dbw-immo-suite'), array($this, 'calc_tilgung_callback'), 'dbw-settings-calculator', 'section_calculator');
		add_settings_field('calc_gest_override', __('Grunderwerbsteuer Override (%)', 'dbw-immo-suite'), array($this, 'calc_gest_override_callback'), 'dbw-settings-calculator', 'section_calculator');

		// ── Tab 3b: Energiekosten-Rechner ──
		add_settings_section('section_energy_calc', __('Energiekosten-Schaetzung', 'dbw-immo-suite'), array($this, 'print_energy_calc_section_info'), 'dbw-settings-calculator');
		add_settings_field('energy_show_costs', __('Anzeigen', 'dbw-immo-suite'), array($this, 'energy_show_costs_callback'), 'dbw-settings-calculator', 'section_energy_calc');

		$energy_sources = array(
			'gas'         => array('label' => __('Gas', 'dbw-immo-suite'), 'default' => 0.12),
			'oel'         => array('label' => __('Oel', 'dbw-immo-suite'), 'default' => 0.10),
			'fernwaerme'  => array('label' => __('Fernwaerme', 'dbw-immo-suite'), 'default' => 0.14),
			'strom'       => array('label' => __('Strom (Direktheizung)', 'dbw-immo-suite'), 'default' => 0.35),
			'holz'        => array('label' => __('Holz', 'dbw-immo-suite'), 'default' => 0.06),
			'pellet'      => array('label' => __('Pellet', 'dbw-immo-suite'), 'default' => 0.06),
			'waermepumpe'  => array('label' => __('Waermepumpe', 'dbw-immo-suite'), 'default' => 0.12),
			'fluessiggas' => array('label' => __('Fluessiggas', 'dbw-immo-suite'), 'default' => 0.09),
			'solar'       => array('label' => __('Solar', 'dbw-immo-suite'), 'default' => 0.00),
		);
		foreach ($energy_sources as $key => $cfg) {
			add_settings_field(
				'energy_price_' . $key,
				sprintf(__('Preis %s (EUR/kWh)', 'dbw-immo-suite'), $cfg['label']),
				array($this, 'energy_price_callback'),
				'dbw-settings-calculator',
				'section_energy_calc',
				array('key' => $key, 'default' => $cfg['default'])
			);
		}

		// ── Tab 4: Referenzen & Verkauf ──
		add_settings_section('section_references', __('Referenzen & Verkaufte Objekte', 'dbw-immo-suite'), array($this, 'print_reference_section_info'), 'dbw-settings-references');
		add_settings_field('enable_references', __('Aktivieren', 'dbw-immo-suite'), array($this, 'enable_references_callback'), 'dbw-settings-references', 'section_references');
		add_settings_field('reference_slug', __('Seiten-Slug (URL)', 'dbw-immo-suite'), array($this, 'reference_slug_callback'), 'dbw-settings-references', 'section_references');
		add_settings_field('filter_sold_from_main', __('Archiv bereinigen', 'dbw-immo-suite'), array($this, 'filter_sold_from_main_callback'), 'dbw-settings-references', 'section_references');
		add_settings_field('reference_badge_text', __('Badge: Referenz', 'dbw-immo-suite'), array($this, 'reference_badge_text_callback'), 'dbw-settings-references', 'section_references');
		add_settings_field('sold_badge_text', __('Badge: Verkauft', 'dbw-immo-suite'), array($this, 'sold_badge_text_callback'), 'dbw-settings-references', 'section_references');
		add_settings_field('hide_price_sold', __('Preise ausblenden', 'dbw-immo-suite'), array($this, 'hide_price_sold_callback'), 'dbw-settings-references', 'section_references');
		add_settings_field('show_sold_date', __('Verkaufsdatum', 'dbw-immo-suite'), array($this, 'show_sold_date_callback'), 'dbw-settings-references', 'section_references');

		// ── Tab 4: Maklerfirma (SEO) ──
		add_settings_section('section_seo', __('Maklerfirma (SEO)', 'dbw-immo-suite'), array($this, 'print_seo_section_info'), 'dbw-settings-seo');

		$seo_fields = array(
			'org_name'     => __('Firmenname', 'dbw-immo-suite'),
			'org_url'      => __('Website-URL', 'dbw-immo-suite'),
			'org_logo_url' => __('Logo-URL', 'dbw-immo-suite'),
			'org_phone'    => __('Telefon', 'dbw-immo-suite'),
			'org_email'    => __('E-Mail', 'dbw-immo-suite'),
			'org_street'   => __('Straße', 'dbw-immo-suite'),
			'org_zip'      => __('PLZ', 'dbw-immo-suite'),
			'org_city'     => __('Stadt', 'dbw-immo-suite'),
		);

		foreach ($seo_fields as $field_id => $label) {
			add_settings_field($field_id, $label, array($this, 'seo_field_callback'), 'dbw-settings-seo', 'section_seo', array('id' => $field_id));
		}
	}

	public function sanitize($input)
	{
		$new_input = array();
		// Resolve path key to absolute path (avoids WAF blocking raw paths)
		if (isset($input['xml_path_key'])) {
			$key = sanitize_text_field($input['xml_path_key']);
			$presets = $this->get_path_presets();
			if (isset($presets[$key])) {
				$new_input['xml_path'] = $presets[$key];
			} elseif ($key === 'custom' && isset($input['xml_path_custom'])) {
				$new_input['xml_path'] = sanitize_text_field($input['xml_path_custom']);
			}
		} elseif (isset($input['xml_path'])) {
			// Fallback for legacy
			$new_input['xml_path'] = sanitize_text_field($input['xml_path']);
		}
		if (isset($input['cpt_slug'])) {
			$new_input['cpt_slug'] = sanitize_title($input['cpt_slug']);
		}
		$new_input['enable_garbage_collection'] = isset($input['enable_garbage_collection']) ? 1 : 0;

		// Anrede
		$new_input['anrede'] = in_array($input['anrede'] ?? 'sie', ['sie', 'du'], true) ? $input['anrede'] : 'sie';

		// Grayscale
		$new_input['grayscale_sold'] = isset($input['grayscale_sold']) ? 1 : 0;
		$new_input['grayscale_reserved'] = isset($input['grayscale_reserved']) ? 1 : 0;

		// Price per sqm
		$new_input['show_price_per_sqm'] = isset($input['show_price_per_sqm']) ? 1 : 0;
		$new_input['show_price_per_sqm_comparison'] = isset($input['show_price_per_sqm_comparison']) ? 1 : 0;
		$new_input['show_price_per_sqm_archive'] = isset($input['show_price_per_sqm_archive']) ? 1 : 0;
		if (isset($input['price_per_sqm_min_comparables'])) {
			$new_input['price_per_sqm_min_comparables'] = max(1, min(50, (int) $input['price_per_sqm_min_comparables']));
		}
		if (isset($input['price_per_sqm_cache_hours'])) {
			$new_input['price_per_sqm_cache_hours'] = max(1, min(168, (int) $input['price_per_sqm_cache_hours']));
		}

		// WhatsApp
		$new_input['whatsapp_enabled'] = isset($input['whatsapp_enabled']) ? 1 : 0;
		$new_input['whatsapp_floating'] = isset($input['whatsapp_floating']) ? 1 : 0;
		if (isset($input['whatsapp_number_override'])) {
			$new_input['whatsapp_number_override'] = sanitize_text_field($input['whatsapp_number_override']);
		}
		if (isset($input['whatsapp_cta_text'])) {
			$new_input['whatsapp_cta_text'] = sanitize_text_field($input['whatsapp_cta_text']);
		}
		if (isset($input['whatsapp_message_template'])) {
			$new_input['whatsapp_message_template'] = sanitize_textarea_field($input['whatsapp_message_template']);
		}

		// Finance Calculator
		$calc_floats = array(
			'calc_notar_percent'    => 1.5,
			'calc_grundbuch_percent'=> 0.5,
			'calc_default_zinssatz' => 3.5,
			'calc_default_tilgung'  => 2.0,
		);
		foreach ($calc_floats as $key => $default) {
			if (isset($input[$key]) && $input[$key] !== '') {
				$new_input[$key] = max(0, min(20, (float) str_replace(',', '.', $input[$key])));
			}
		}
		if (isset($input['calc_gest_override']) && $input['calc_gest_override'] !== '') {
			$new_input['calc_gest_override'] = max(0, min(20, (float) str_replace(',', '.', $input['calc_gest_override'])));
		}

		// Energy Cost Calculator
		$new_input['energy_show_costs'] = isset($input['energy_show_costs']) ? 1 : 0;
		$energy_keys = array('gas', 'oel', 'fernwaerme', 'strom', 'holz', 'pellet', 'waermepumpe', 'fluessiggas', 'solar');
		foreach ($energy_keys as $ek) {
			$field = 'energy_price_' . $ek;
			if (isset($input[$field]) && $input[$field] !== '') {
				$new_input[$field] = max(0, min(2, (float) str_replace(',', '.', $input[$field])));
			}
		}

		// Reference Settings
		$new_input['enable_references'] = isset($input['enable_references']) ? 1 : 0;
		if (isset($input['reference_slug'])) {
			$new_input['reference_slug'] = sanitize_title($input['reference_slug']);
		}
		if (isset($input['reference_badge_text'])) {
			$new_input['reference_badge_text'] = sanitize_text_field($input['reference_badge_text']);
		}
		if (isset($input['sold_badge_text'])) {
			$new_input['sold_badge_text'] = sanitize_text_field($input['sold_badge_text']);
		}
		$new_input['hide_price_sold'] = isset($input['hide_price_sold']) ? 1 : 0;
		$new_input['show_sold_date'] = isset($input['show_sold_date']) ? 1 : 0;
		$new_input['filter_sold_from_main'] = isset($input['filter_sold_from_main']) ? 1 : 0; // Default off, user must enable

		// SEO / Maklerfirma fields
		$seo_text_fields = array('org_name', 'org_street', 'org_zip', 'org_city', 'org_phone');
		foreach ($seo_text_fields as $f) {
			if (isset($input[$f])) {
				$new_input[$f] = sanitize_text_field($input[$f]);
			}
		}
		if (isset($input['org_url'])) {
			$new_input['org_url'] = esc_url_raw($input['org_url']);
		}
		if (isset($input['org_logo_url'])) {
			$new_input['org_logo_url'] = esc_url_raw($input['org_logo_url']);
		}
		if (isset($input['org_email'])) {
			$new_input['org_email'] = sanitize_email($input['org_email']);
		}

		// Trigger Page Generation if enabled and changed
		$old_options = get_option($this->option_name);
		$old_enable = isset($old_options['enable_references']) ? $old_options['enable_references'] : 0;

		if ($new_input['enable_references'] == 1 && $old_enable == 0) {
			// Just enabled
			do_action('dbw_immo_references_enabled', $new_input);
		}

		return $new_input;
	}

	public function cpt_slug_callback()
	{
		$options = get_option($this->option_name);
		$val = !empty($options['cpt_slug']) ? $options['cpt_slug'] : 'immobilien';

		printf(
			'<input type="text" id="cpt_slug" name="%s[cpt_slug]" value="%s" class="regular-text" />',
			$this->option_name,
			esc_attr($val)
		);
		echo '<p class="description">' . __('Standard: immobilien. Nach Änderung bitte Permalinks neu speichern!', 'dbw-immo-suite') . '</p>';
	}

	public function print_section_info()
	{
		print __('Bitte geben Sie den absoluten Pfad zum Ordner an, in dem die OpenImmo-Dateien liegen. Standardmäßig sucht das Plugin im Uploads-Verzeichnis under /openimmo/.', 'dbw-immo-suite');
	}

	public function print_reference_section_info()
	{
		print __('Konfiguration für das Referenz-System und verkaufte Immobilien.', 'dbw-immo-suite');
	}

	public function xml_path_callback()
	{
		$options = get_option($this->option_name);
		$val = isset($options['xml_path']) ? $options['xml_path'] : '';

		$upload_dir = wp_upload_dir();
		$preset_map = $this->get_path_presets();

		// Determine current selection
		$current_key = '';
		foreach ($preset_map as $key => $abs_path) {
			if ($val === $abs_path) {
				$current_key = $key;
				break;
			}
		}
		$is_custom = !empty($val) && empty($current_key);
		if ($is_custom) {
			$current_key = 'custom';
		}

		echo '<div style="display:flex; flex-direction:column; gap:8px; max-width:600px;">';

		echo '<select id="dbw_xml_path_preset" name="' . esc_attr($this->option_name) . '[xml_path_key]" style="max-width:100%;" onchange="dbwPathPresetChange(this)">';
		echo '<option value="">' . esc_html__('-- Bitte wählen --', 'dbw-immo-suite') . '</option>';
		echo '<option value="preset_uploads"' . selected($current_key, 'preset_uploads', false) . '>wp-content/uploads/openimmo/ (Standard)</option>';
		echo '<option value="preset_root"' . selected($current_key, 'preset_root', false) . '>WordPress-Root/openimmo/</option>';
		echo '<option value="custom"' . selected($current_key, 'custom', false) . '>' . esc_html__('Eigener Pfad...', 'dbw-immo-suite') . '</option>';
		echo '</select>';

		// Custom path input
		$custom_display = $is_custom ? 'flex' : 'none';
		echo '<div id="dbw_custom_path_wrap" style="display:' . esc_attr($custom_display) . '; align-items:center; gap:8px;">';
		echo '<input type="text" id="xml_path_custom" name="' . esc_attr($this->option_name) . '[xml_path_custom]" value="' . esc_attr($is_custom ? $val : '') . '" class="regular-text" placeholder="' . esc_attr('openimmo oder /absoluter/pfad/') . '" style="flex:1;" />';
		echo '</div>';

		// Validate button
		echo '<div style="display:flex; align-items:center; gap:10px;">';
		echo '<button type="button" class="button" onclick="dbwValidatePath()" style="white-space:nowrap;">📂 Pfad prüfen</button>';
		echo '<span id="dbw_path_status"></span>';
		echo '</div>';

		// Info box
		echo '<div style="background:#f0f0f1; border-left:4px solid #2271b1; padding:8px 12px; font-size:12px; color:#555; border-radius:0 4px 4px 0;">';
		echo '<strong>Server-Info:</strong><br>';
		echo 'WordPress-Root: <code>' . esc_html(ABSPATH) . '</code><br>';
		echo 'Uploads: <code>' . esc_html($upload_dir['basedir']) . '</code>';
		echo '</div>';

		echo '</div>';

		?>
		<script>
		function dbwPathPresetChange(sel) {
			document.getElementById('dbw_custom_path_wrap').style.display = (sel.value === 'custom') ? 'flex' : 'none';
			document.getElementById('dbw_path_status').innerHTML = '';
		}
		function dbwValidatePath() {
			var preset = document.getElementById('dbw_xml_path_preset');
			var status = document.getElementById('dbw_path_status');
			status.innerHTML = '<span style="color:#666;">⏳ Prüfe...</span>';
			var data = new FormData();
			data.append('action', 'dbw_immo_validate_path');
			data.append('path_key', preset.value);
			if (preset.value === 'custom') {
				data.append('custom_path', document.getElementById('xml_path_custom').value);
			}
			data.append('_wpnonce', '<?php echo wp_create_nonce('dbw_immo_validate_path'); ?>');
			fetch(ajaxurl, { method: 'POST', body: data })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					if (res.success) {
						status.innerHTML = '<span style="color:#00a32a; font-weight:bold;">✅ ' + res.data.message + '</span>';
					} else {
						status.innerHTML = '<span style="color:#d63638; font-weight:bold;">❌ ' + res.data.message + '</span>';
					}
				})
				.catch(function() {
					status.innerHTML = '<span style="color:#d63638;">❌ Fehler bei der Prüfung</span>';
				});
		}
		</script>
		<?php
	}

	/**
	 * Resolve safe key to absolute path
	 */
	private function get_path_presets()
	{
		$upload_dir = wp_upload_dir();
		return array(
			'preset_uploads' => trailingslashit($upload_dir['basedir']) . 'openimmo/',
			'preset_root'    => ABSPATH . 'openimmo/',
		);
	}

	public function enable_garbage_collection_callback()
	{
		$this->checkbox_callback('enable_garbage_collection', 'WICHTIG: Nur aktivieren, wenn die Maklersoftware immer den <b>kompletten Bestand</b> (Full Sync) uebertraegt! Fehlt ein Objekt in der ZIP, wird es andernfalls archiviert.');
	}

	// -- Reference Callbacks --

	public function enable_references_callback()
	{
		$this->checkbox_callback('enable_references', __('Referenz-System aktivieren (erstellt automatisch eine Referenz-Seite und ermoeglicht die Anzeige verkaufter Objekte)', 'dbw-immo-suite'));
	}

	public function reference_slug_callback()
	{
		$options = get_option($this->option_name);
		$val = !empty($options['reference_slug']) ? $options['reference_slug'] : 'referenzen';
		printf(
			'<input type="text" id="reference_slug" name="%s[reference_slug]" value="%s" class="regular-text" />',
			$this->option_name,
			esc_attr($val)
		);
		echo '<p class="description">' . __('Slug der automatisch erstellten Seite.', 'dbw-immo-suite') . '</p>';
	}

	public function reference_badge_text_callback()
	{
		$this->text_callback('reference_badge_text', 'Referenz', 'Text für Referenz-Badge');
	}

	public function sold_badge_text_callback()
	{
		$this->text_callback('sold_badge_text', 'Verkauft', 'Text für Verkauft-Badge');
	}

	public function hide_price_sold_callback()
	{
		$this->checkbox_callback('hide_price_sold', 'Preis bei verkauften Objekten ausblenden');
	}

	public function show_sold_date_callback()
	{
		$this->checkbox_callback('show_sold_date', 'Verkaufsdatum anzeigen');
	}

	public function filter_sold_from_main_callback()
	{
		$this->checkbox_callback('filter_sold_from_main', 'Verkaufte Objekte aus normaler Liste ausblenden');
	}

	private function checkbox_callback($id, $label)
	{
		$options = get_option($this->option_name);
		$val = isset($options[$id]) && $options[$id] == 1 ? 'checked' : '';
		printf(
			'<input type="checkbox" id="%s" name="%s[%s]" value="1" %s /> <label for="%s">%s</label>',
			esc_attr($id), esc_attr($this->option_name), esc_attr($id), $val, esc_attr($id), wp_kses($label, array('b' => array(), 'strong' => array()))
		);
	}

	private function text_callback($id, $default, $desc = '')
	{
		$options = get_option($this->option_name);
		$val = !empty($options[$id]) ? $options[$id] : $default;
		printf(
			'<input type="text" id="%s" name="%s[%s]" value="%s" class="regular-text" />',
			$id, $this->option_name, $id, esc_attr($val)
		);
		if ($desc)
			echo '<p class="description">' . wp_kses_post($desc) . '</p>';
	}

	public function print_display_section_info()
	{
		print __('Einstellungen fuer die Darstellung im Frontend.', 'dbw-immo-suite');
	}

	public function grayscale_sold_callback()
	{
		$this->checkbox_callback('grayscale_sold', __('Karten-Bilder verkaufter Objekte grau darstellen', 'dbw-immo-suite'));
	}

	public function grayscale_reserved_callback()
	{
		$this->checkbox_callback('grayscale_reserved', __('Karten-Bilder reservierter Objekte grau darstellen', 'dbw-immo-suite'));
	}

	public function anrede_callback()
	{
		$settings = get_option($this->option_name);
		$value = isset($settings['anrede']) ? $settings['anrede'] : 'sie';
		$preview_sie = __('Wie koennen wir Ihnen weiterhelfen?', 'dbw-immo-suite');
		$preview_du = __('Wie koennen wir dir weiterhelfen?', 'dbw-immo-suite');
		?>
		<fieldset>
			<label>
				<input type="radio" name="<?php echo esc_attr($this->option_name); ?>[anrede]" value="sie" <?php checked($value, 'sie'); ?> onchange="dbwAnredePreview(this.value)">
				<strong>Sie</strong> &mdash; <?php esc_html_e('Foermliche Anrede (Standard)', 'dbw-immo-suite'); ?>
			</label><br>
			<label>
				<input type="radio" name="<?php echo esc_attr($this->option_name); ?>[anrede]" value="du" <?php checked($value, 'du'); ?> onchange="dbwAnredePreview(this.value)">
				<strong>Du</strong> &mdash; <?php esc_html_e('Persoenliche Anrede', 'dbw-immo-suite'); ?>
			</label>
		</fieldset>
		<p class="description"><?php esc_html_e('Beeinflusst alle vom Plugin gerenderten Texte (Formulare, Buttons, E-Mails).', 'dbw-immo-suite'); ?></p>
		<div id="dbw-anrede-preview" style="margin-top: 10px; padding: 10px 14px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 0 4px 4px 0; font-style: italic; color: #555;">
			<?php echo esc_html($value === 'du' ? $preview_du : $preview_sie); ?>
		</div>
		<script>
		function dbwAnredePreview(mode) {
			var el = document.getElementById('dbw-anrede-preview');
			el.textContent = (mode === 'du')
				? <?php echo wp_json_encode($preview_du); ?>
				: <?php echo wp_json_encode($preview_sie); ?>;
		}
		</script>
		<?php
	}

	public function print_price_sqm_section_info()
	{
		print __('Preis-pro-Quadratmeter-Anzeige mit Vergleichswert auf der Einzelansicht und optional auf den Karten.', 'dbw-immo-suite');
	}

	public function show_price_per_sqm_callback()
	{
		$this->checkbox_callback('show_price_per_sqm', __('Preis/m² auf der Einzelansicht anzeigen', 'dbw-immo-suite'));
	}

	public function show_price_per_sqm_comparison_callback()
	{
		$this->checkbox_callback('show_price_per_sqm_comparison', __('Vergleich mit Durchschnitt des gleichen Ortes anzeigen', 'dbw-immo-suite'));
	}

	public function show_price_per_sqm_archive_callback()
	{
		$this->checkbox_callback('show_price_per_sqm_archive', __('Preis/m² als Badge auf Archiv-Karten anzeigen', 'dbw-immo-suite'));
	}

	public function price_per_sqm_min_comparables_callback()
	{
		$this->number_field_callback('price_per_sqm_min_comparables', 3, 1, 1, 50, __('Mindestanzahl Vergleichsobjekte fuer die Durchschnittsanzeige', 'dbw-immo-suite'));
	}

	public function price_per_sqm_cache_hours_callback()
	{
		$this->number_field_callback('price_per_sqm_cache_hours', 24, 1, 1, 168, __('Cache-Dauer fuer Durchschnittswerte in Stunden (Standard: 24)', 'dbw-immo-suite'));
	}

	public function print_whatsapp_section_info()
	{
		print __('WhatsApp-Kontaktbutton auf der Immobilien-Detailseite. Nutzt die Telefonnummer des Ansprechpartners oder eine zentrale Nummer.', 'dbw-immo-suite');
	}

	public function whatsapp_enabled_callback()
	{
		$this->checkbox_callback('whatsapp_enabled', __('WhatsApp-Button global aktivieren', 'dbw-immo-suite'));
	}

	public function whatsapp_floating_callback()
	{
		$this->checkbox_callback('whatsapp_floating', __('Floating-Button (runder Button unten rechts) anzeigen', 'dbw-immo-suite'));
	}

	public function whatsapp_number_override_callback()
	{
		$options = get_option($this->option_name);
		$val = !empty($options['whatsapp_number_override']) ? $options['whatsapp_number_override'] : '';
		printf(
			'<input type="tel" id="whatsapp_number_override" name="%s[whatsapp_number_override]" value="%s" class="regular-text" placeholder="+49 151 22632768" />',
			esc_attr($this->option_name),
			esc_attr($val)
		);
		echo '<p class="description">' . esc_html__('Ueberschreibt die Kontaktperson-Nummer. Fuer Makler mit zentraler WhatsApp-Business-Nummer. Leer = Nummer aus Objekt-Daten.', 'dbw-immo-suite') . '</p>';
	}

	public function whatsapp_cta_text_callback()
	{
		$this->text_callback('whatsapp_cta_text', 'Per WhatsApp anfragen', __('Beschriftung des WhatsApp-Buttons', 'dbw-immo-suite'));
	}

	public function whatsapp_message_template_callback()
	{
		$options = get_option($this->option_name);
		$val = !empty($options['whatsapp_message_template']) ? $options['whatsapp_message_template'] : '';
		$placeholder = "Hallo {ansprechpartner},\n\nich interessiere mich fuer diese Immobilie:\n{titel}\n{url}\n\nKoennten Sie mir weitere Informationen zukommen lassen?";
		printf(
			'<textarea id="whatsapp_message_template" name="%s[whatsapp_message_template]" rows="5" class="large-text" placeholder="%s">%s</textarea>',
			esc_attr($this->option_name),
			esc_attr($placeholder),
			esc_textarea($val)
		);
		echo '<p class="description">' . esc_html__('Platzhalter: {ansprechpartner}, {titel}, {url}, {name}. Leer = Standardtext (beruecksichtigt Du/Sie-Einstellung).', 'dbw-immo-suite') . '</p>';
	}

	public function print_calculator_section_info()
	{
		print __('Standardwerte fuer den Kaufnebenkosten- und Finanzierungsrechner auf der Detailseite. Die Grunderwerbsteuer wird automatisch per PLZ ermittelt, kann aber hier global ueberschrieben werden.', 'dbw-immo-suite');
	}

	public function calc_notar_callback()
	{
		$this->number_field_callback('calc_notar_percent', 1.5, 0.1, 0, 10, __('Standard: 1,5 %', 'dbw-immo-suite'));
	}

	public function calc_grundbuch_callback()
	{
		$this->number_field_callback('calc_grundbuch_percent', 0.5, 0.1, 0, 10, __('Standard: 0,5 %', 'dbw-immo-suite'));
	}

	public function calc_zinssatz_callback()
	{
		$this->number_field_callback('calc_default_zinssatz', 3.5, 0.1, 0.5, 10, __('Standard: 3,5 % — Anfangswert des Sliders', 'dbw-immo-suite'));
	}

	public function calc_tilgung_callback()
	{
		$this->number_field_callback('calc_default_tilgung', 2.0, 0.1, 0.5, 10, __('Standard: 2,0 % — Anfangswert des Sliders', 'dbw-immo-suite'));
	}

	public function calc_gest_override_callback()
	{
		$this->number_field_callback('calc_gest_override', '', 0.1, 0, 20, __('Leer lassen fuer automatische Erkennung per PLZ. Nur setzen, um die PLZ-Erkennung global zu ueberschreiben.', 'dbw-immo-suite'));
	}

	public function print_energy_calc_section_info()
	{
		print __('Energiepreise pro kWh fuer die Heizkostenschaetzung auf der Detailseite. Preise werden als Standard verwendet, Besucher koennen den Preis per Slider anpassen.', 'dbw-immo-suite');
	}

	public function energy_show_costs_callback()
	{
		$this->checkbox_callback('energy_show_costs', __('Geschaetzte Heizkosten im Energieausweis-Bereich anzeigen', 'dbw-immo-suite'));
	}

	public function energy_price_callback($args)
	{
		$key = $args['key'];
		$default = $args['default'];
		$id = 'energy_price_' . $key;
		$this->number_field_callback($id, $default, 0.01, 0, 2, sprintf(__('Standard: %s EUR/kWh', 'dbw-immo-suite'), number_format($default, 2, ',', '.')));
	}

	private function number_field_callback($id, $default, $step, $min, $max, $desc = '')
	{
		$options = get_option($this->option_name);
		$val = isset($options[$id]) ? $options[$id] : $default;
		printf(
			'<input type="number" id="%s" name="%s[%s]" value="%s" step="%s" min="%s" max="%s" class="small-text" />',
			esc_attr($id), esc_attr($this->option_name), esc_attr($id),
			esc_attr($val !== '' ? (string) $val : ''),
			esc_attr($step), esc_attr($min), esc_attr($max)
		);
		if ($desc) {
			echo '<p class="description">' . esc_html($desc) . '</p>';
		}
	}

	public function print_seo_section_info()
	{
		print __('Diese Angaben werden als strukturierte Daten (Schema.org / JSON-LD) ausgegeben und verbessern die Sichtbarkeit in Google Rich Results, AI Overviews und Sprachassistenten.', 'dbw-immo-suite');
	}

	public function seo_field_callback($args)
	{
		$id = $args['id'];
		$options = get_option($this->option_name);
		$val = !empty($options[$id]) ? $options[$id] : '';

		$type = 'text';
		$placeholder = '';
		if ($id === 'org_url') {
			$type = 'url';
			$placeholder = home_url('/');
		} elseif ($id === 'org_email') {
			$type = 'email';
		} elseif ($id === 'org_logo_url') {
			$type = 'url';
			$placeholder = 'https://example.com/logo.png';
		} elseif ($id === 'org_phone') {
			$type = 'tel';
		}

		printf(
			'<input type="%s" id="%s" name="%s[%s]" value="%s" class="regular-text" placeholder="%s" />',
			$type, $id, $this->option_name, $id, esc_attr($val), esc_attr($placeholder)
		);
	}

	/**
	 * AJAX handler to validate the import path
	 */
	public function ajax_validate_path()
	{
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => 'Keine Berechtigung.'));
		}

		check_ajax_referer('dbw_immo_validate_path', '_wpnonce');

		$path_key = isset($_POST['path_key']) ? sanitize_text_field(wp_unslash($_POST['path_key'])) : '';

		if (empty($path_key)) {
			wp_send_json_error(array('message' => 'Kein Pfad ausgewählt.'));
		}

		// Resolve key to absolute path
		$presets = $this->get_path_presets();
		if (isset($presets[$path_key])) {
			$resolved = $presets[$path_key];
		} elseif ($path_key === 'custom') {
			$custom = isset($_POST['custom_path']) ? sanitize_text_field(wp_unslash($_POST['custom_path'])) : '';
			if (empty($custom)) {
				wp_send_json_error(array('message' => 'Bitte einen Pfad eingeben.'));
			}
			$resolved = $custom;
			// Try relative from ABSPATH if not absolute
			if (!is_dir($resolved)) {
				$resolved = ABSPATH . ltrim($custom, '/');
			}
			// Prevent path traversal outside WordPress root
			$real_resolved = realpath($resolved);
			$real_abspath = realpath(ABSPATH);
			if ($real_resolved && $real_abspath && strpos($real_resolved, $real_abspath) !== 0) {
				wp_send_json_error(array('message' => 'Pfad liegt ausserhalb des WordPress-Verzeichnisses.'));
			}
		} else {
			wp_send_json_error(array('message' => 'Ungültige Auswahl.'));
		}

		if (!is_dir($resolved)) {
			wp_send_json_error(array(
				'message' => sprintf('Verzeichnis nicht gefunden: %s', $resolved)
			));
		}

		// Check for XML/ZIP files
		$dir = trailingslashit($resolved);
		$zips = glob($dir . '*.zip');
		$xmls = glob($dir . '*.xml');

		// glob() returns false on error (permissions, open_basedir)
		if ($zips === false || $xmls === false) {
			$reason = $this->diagnose_glob_failure($dir);
			wp_send_json_error(array(
				'message' => sprintf('Verzeichnis existiert (%s), aber ist nicht lesbar. %s', $resolved, $reason),
			));
		}

		$file_count = count($zips) + count($xmls);

		$msg = sprintf('Verzeichnis existiert (%s)', $resolved);
		if ($file_count > 0) {
			$msg .= sprintf(' — %d Datei(en) gefunden (%d ZIP, %d XML)', $file_count, count($zips), count($xmls));
		} else {
			// Hint: maybe files exist but glob can't see them
			if (!is_readable($dir)) {
				$msg .= ' — Verzeichnis nicht lesbar (Berechtigungsproblem). Bitte Dateiberechtigungen prüfen oder den Uploads-Pfad verwenden.';
			} else {
				$msg .= ' — Noch keine Import-Dateien vorhanden.';
			}
		}

		wp_send_json_success(array('message' => $msg));
	}

	/**
	 * Diagnose why glob() failed on a directory.
	 */
	private function diagnose_glob_failure($dir)
	{
		$hints = array();

		if (!is_readable($dir)) {
			$hints[] = 'Verzeichnis ist nicht lesbar (Dateiberechtigungen prüfen).';
		}

		$open_basedir = ini_get('open_basedir');
		if (!empty($open_basedir)) {
			$hints[] = sprintf('PHP open_basedir ist aktiv: %s', $open_basedir);
			$allowed = explode(PATH_SEPARATOR, $open_basedir);
			$inside = false;
			foreach ($allowed as $base) {
				if (!empty($base) && strpos($dir, rtrim($base, '/')) === 0) {
					$inside = true;
					break;
				}
			}
			if (!$inside) {
				$hints[] = 'Das Verzeichnis liegt AUSSERHALB der erlaubten open_basedir Pfade!';
			}
		}

		$upload_dir = wp_upload_dir();
		$fallback = trailingslashit($upload_dir['basedir']) . 'openimmo/';
		if ($fallback !== $dir) {
			$hints[] = sprintf('Empfehlung: Uploads-Pfad verwenden (%s) — dieser ist garantiert erreichbar.', $fallback);
		}

		return implode(' ', $hints) ?: 'Ursache unbekannt.';
	}

	/**
	 * Detect anrede change and set transient for admin notice.
	 */
	public function on_settings_update($old_value, $new_value)
	{
		if (($old_value['anrede'] ?? 'sie') !== ($new_value['anrede'] ?? 'sie')) {
			set_transient('dbw_immo_anrede_changed', true, 30);
		}
	}

	/**
	 * Show admin notice after anrede change.
	 */
	public function anrede_changed_notice()
	{
		if (get_transient('dbw_immo_anrede_changed')) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__('Anrede umgestellt. Falls Caching aktiv ist, bitte einmal leeren.', 'dbw-immo-suite')
				. '</p></div>';
			delete_transient('dbw_immo_anrede_changed');
		}
	}
}