<?php

namespace DBW\ImmoSuite\Core;

if (!defined('ABSPATH')) { exit; }

/**
 * Main Plugin Class
 */
class Plugin
{

    /**
     * Loader instance
     *
     * @var Loader
     */
    protected $loader;

    /**
     * Initialize the plugin
     */
    public function __construct()
    {
        $this->load_dependencies();
        $this->define_admin_hooks();
        if (\DBW\ImmoSuite\Core\License::is_valid()) {
            $this->define_public_hooks();
        }
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies()
    {
        $this->loader = new Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     */
    private function define_admin_hooks()
    {
        $plugin_license = new \DBW\ImmoSuite\Core\License();
        $this->loader->add_action('init', $plugin_license, 'init');

        // CPT + Taxonomies must always register (admin menu depends on them)
        $plugin_post_types = new \DBW\ImmoSuite\PostTypes\Property();
        $this->loader->add_action('init', $plugin_post_types, 'register_post_type');

        // Inquiry inbox (stores contact/expose requests as leads)
        $plugin_inquiries = new \DBW\ImmoSuite\PostTypes\Inquiry();
        $this->loader->add_action('init', $plugin_inquiries, 'register_post_type');

        $inquiry_admin = new \DBW\ImmoSuite\Admin\InquiryAdmin();
        $this->loader->add_action('init', $inquiry_admin, 'init');

        // Setup checklist + import health monitoring
        $onboarding = new \DBW\ImmoSuite\Admin\Onboarding();
        $this->loader->add_action('init', $onboarding, 'init');

        $import_monitor = new \DBW\ImmoSuite\Core\ImportMonitor();
        $this->loader->add_action('init', $import_monitor, 'init');

        $plugin_tax_objektart = new \DBW\ImmoSuite\Taxonomies\PropertyType();
        $this->loader->add_action('init', $plugin_tax_objektart, 'register_taxonomy');

        $plugin_tax_vermarktung = new \DBW\ImmoSuite\Taxonomies\MarketingType();
        $this->loader->add_action('init', $plugin_tax_vermarktung, 'register_taxonomy');

        $plugin_tax_location = new \DBW\ImmoSuite\Taxonomies\Location();
        $this->loader->add_action('init', $plugin_tax_location, 'register_taxonomy');

        $plugin_settings = new \DBW\ImmoSuite\Admin\Settings();
        $this->loader->add_action('init', $plugin_settings, 'init');

        $property_details = new \DBW\ImmoSuite\Admin\PropertyDetails();
        $this->loader->add_action('admin_init', $property_details, 'init');

        $admin_columns = new \DBW\ImmoSuite\Admin\AdminColumns();
        $this->loader->add_action('init', $admin_columns, 'init');

        $import_dashboard = new \DBW\ImmoSuite\Admin\ImportDashboard();
        $this->loader->add_action('init', $import_dashboard, 'init');

        // Media handling (library visibility, own upload folder, cleanup).
        // Registered here on purpose: an expired license must not stop the
        // cleanup and leave orphaned images behind.
        $plugin_media = new \DBW\ImmoSuite\Core\Media();
        $this->loader->add_action('init', $plugin_media, 'init');

        $media_tools = new \DBW\ImmoSuite\Admin\MediaTools();
        $this->loader->add_action('init', $media_tools, 'init');

        $page_generator = new \DBW\ImmoSuite\Core\PageGenerator();
        $this->loader->add_action('init', $page_generator, 'init');

        // AJAX Import
        $importer = new \DBW\ImmoSuite\Import\Importer();
        $this->loader->add_action('wp_ajax_dbw_immo_run_import', $importer, 'ajax_run_import');
        $this->loader->add_action('wp_ajax_dbw_immo_prepare_import', $importer, 'ajax_prepare_import');
        $this->loader->add_action('wp_ajax_dbw_immo_process_batch', $importer, 'ajax_process_batch');
        $this->loader->add_action('wp_ajax_dbw_immo_finalize_import', $importer, 'ajax_finalize_import');
        $this->loader->add_action('wp_ajax_dbw_immo_import_progress', $importer, 'ajax_import_progress');
        $this->loader->add_action('wp_ajax_dbw_immo_dry_run', $importer, 'ajax_dry_run');
        $this->loader->add_action('wp_ajax_dbw_immo_get_log', $importer, 'ajax_get_log');

        // Expose view counter (frontend), weekly report + vendor telemetry
        $stats = new \DBW\ImmoSuite\Core\Stats();
        $this->loader->add_action('init', $stats, 'init');

        $weekly_report = new \DBW\ImmoSuite\Core\WeeklyReport();
        $this->loader->add_action('init', $weekly_report, 'init');

        $telemetry = new \DBW\ImmoSuite\Core\Telemetry();
        $this->loader->add_action('init', $telemetry, 'init');

        // Admin Assets
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_admin_scripts');

        // CRON Automation
        $this->loader->add_action('dbw_immo_cron_hook', $importer, 'run_import');

        // Scheduler Check
        $this->schedule_cron();
    }

    /**
     * Schedule the hourly import event if not already scheduled.
     */
    private function schedule_cron()
    {
        if (!wp_next_scheduled('dbw_immo_cron_hook')) {
            wp_schedule_event(time(), 'hourly', 'dbw_immo_cron_hook');
        }
    }

    /**
     * Asset URL, preferring the .min build when present (skipped with SCRIPT_DEBUG).
     */
    private static function asset_url($rel)
    {
        if (!(defined('SCRIPT_DEBUG') && SCRIPT_DEBUG)) {
            $min = preg_replace('/\\.(js|css)$/', '.min.$1', $rel);
            if ($min !== $rel && file_exists(DBW_IMMO_SUITE_PATH . $min)) {
                return DBW_IMMO_SUITE_URL . $min;
            }
        }
        return DBW_IMMO_SUITE_URL . $rel;
    }

    /**
     * Consent service ids that may release the map.
     *
     * Sites name the map service differently in their consent tool
     * (Borlabs ships "maps" for Google Maps, an OSM service is created by hand),
     * so the id is configurable and filterable.
     *
     * @return array
     */
    private static function map_consent_service_ids()
    {
        $raw = (string) get_theme_mod('dbw_immo_map_consent_service', 'openstreetmap');
        $ids = array_filter(array_map('trim', explode(',', $raw)));
        if (empty($ids)) {
            $ids = array('openstreetmap');
        }
        $ids = apply_filters('dbw_immo_map_consent_service_ids', $ids);
        return array_values(array_unique(array_map('strval', (array) $ids)));
    }

    /**
     * Bridge between the map placeholder and whatever consent tool the site runs.
     * Always loaded next to Leaflet: without it the placeholder still works,
     * but an already granted consent would never skip it.
     */
    private static function enqueue_map_consent_bridge()
    {
        wp_enqueue_script('dbw-immo-map-consent', self::asset_url('assets/js/map-consent.js'), array('leaflet'), DBW_IMMO_SUITE_VERSION, array('in_footer' => true, 'strategy' => 'defer'));
        wp_localize_script('dbw-immo-map-consent', 'dbwImmoMapConsentCfg', array(
            'serviceIds' => self::map_consent_service_ids(),
            // Opt-in for the WP Consent API (Real Cookie Banner, Complianz):
            // empty by default, categories are too coarse to guess.
            'consentApiCategory' => (string) apply_filters('dbw_immo_map_consent_api_category', ''),
        ));
    }

    /**
     * Enqueue Admin Scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        $screen = get_current_screen();
        if (!$screen) return;

        $allowed = array(
            'immobilie_page_dbw-immo-import',
            'immobilie_page_dbw-immo-suite-settings',
            'immobilie',
            'edit-immobilie',
        );

        if (!in_array($screen->id, $allowed, true)) return;

        wp_enqueue_script('dbw-immo-admin', self::asset_url('assets/js/admin.js'), array('jquery'), DBW_IMMO_SUITE_VERSION, false);
        wp_localize_script('dbw-immo-admin', 'dbwImmoAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('dbw_immo_import_nonce'),
        ));
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     */
    private function define_public_hooks()
    {
        $plugin_customizer = new \DBW\ImmoSuite\Admin\Customizer();
        $this->loader->add_action('init', $plugin_customizer, 'init');

        $plugin_filter = new \DBW\ImmoSuite\Frontend\Filter();
        $this->loader->add_action('init', $plugin_filter, 'init');

        $plugin_rewrites = new \DBW\ImmoSuite\Core\Rewrites();
        $this->loader->add_action('init', $plugin_rewrites, 'init');

        $plugin_templates = new \DBW\ImmoSuite\Frontend\TemplateLoader();
        $this->loader->add_action('init', $plugin_templates, 'init');

        $plugin_shortcode = new \DBW\ImmoSuite\Frontend\Shortcode();
        $this->loader->add_action('init', $plugin_shortcode, 'init');

        $plugin_contact_form = new \DBW\ImmoSuite\Frontend\ContactForm();
        $this->loader->add_action('init', $plugin_contact_form, 'init');

        $plugin_contact_modal = new \DBW\ImmoSuite\Frontend\ContactModal();
        $this->loader->add_action('init', $plugin_contact_modal, 'init');

        $plugin_seo_meta = new \DBW\ImmoSuite\Frontend\SeoMeta();
        $this->loader->add_action('init', $plugin_seo_meta, 'init');

        $plugin_schema_output = new \DBW\ImmoSuite\Frontend\SchemaOutput();
        $this->loader->add_action('init', $plugin_schema_output, 'init');

        $plugin_pdf_expose = new \DBW\ImmoSuite\Frontend\PdfExpose();
        $this->loader->add_action('init', $plugin_pdf_expose, 'init');

        $plugin_finance_calc = new \DBW\ImmoSuite\Frontend\FinanceCalculator();
        $this->loader->add_action('init', $plugin_finance_calc, 'init');

        $plugin_infra_score = new \DBW\ImmoSuite\Frontend\InfrastructureScore();
        $this->loader->add_action('init', $plugin_infra_score, 'init');

        $plugin_price_comparison = new \DBW\ImmoSuite\Frontend\PriceComparison();
        $this->loader->add_action('init', $plugin_price_comparison, 'init');

        $plugin_whatsapp = new \DBW\ImmoSuite\Frontend\WhatsAppButton();
        $this->loader->add_action('init', $plugin_whatsapp, 'init');

        $plugin_expose_request = new \DBW\ImmoSuite\Frontend\ExposeRequest();
        $this->loader->add_action('init', $plugin_expose_request, 'init');

        $plugin_favorites = new \DBW\ImmoSuite\Frontend\Favorites();
        $this->loader->add_action('init', $plugin_favorites, 'init');

        $plugin_privacy = new \DBW\ImmoSuite\Core\Privacy();
        $this->loader->add_action('init', $plugin_privacy, 'init');

        $plugin_block_references = new \DBW\ImmoSuite\blocks\ReferencesBlock();
        $this->loader->add_action('init', $plugin_block_references, 'init');

        $plugin_block_grid = new \DBW\ImmoSuite\blocks\GridBlock();
        $this->loader->add_action('init', $plugin_block_grid, 'init');

        $this->loader->add_filter('block_categories_all', $this, 'register_block_categories', 10, 2);

        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_scripts');
        $this->loader->add_action('enqueue_block_assets', $this, 'enqueue_block_assets');
    }

    /**
     * Enqueue Public Scripts & Styles
     */
    public function enqueue_public_scripts()
    {
        // Register assets so blocks/shortcodes can enqueue them on-demand.
        // favorites.js is a dependency of frontend.js so heart buttons work
        // everywhere cards render (archive, blocks, shortcodes).
        wp_register_script('dbw-immo-toast', self::asset_url('assets/js/toast.js'), array(), DBW_IMMO_SUITE_VERSION, array('in_footer' => true, 'strategy' => 'defer'));
        wp_localize_script('dbw-immo-toast', 'dbwToastI18n', array(
            'copied'     => __('Link kopiert', 'dbw-immo-suite'),
            'copyManual' => __('Link kopieren:', 'dbw-immo-suite'),
        ));

        wp_register_script('dbw-immo-view-transition', self::asset_url('assets/js/view-transition.js'), array(), DBW_IMMO_SUITE_VERSION, array('in_footer' => true, 'strategy' => 'defer'));

        $frontend_deps = array('dbw-immo-toast', 'dbw-immo-view-transition');
        if (\DBW\ImmoSuite\Frontend\Favorites::is_enabled()) {
            wp_register_script('dbw-immo-favorites-js', self::asset_url('assets/js/favorites.js'), array('dbw-immo-toast'), DBW_IMMO_SUITE_VERSION, array('in_footer' => true, 'strategy' => 'defer'));
            wp_localize_script('dbw-immo-favorites-js', 'dbwFavorites', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'i18n'    => array(
                    'add'          => __('Zur Merkliste hinzufuegen', 'dbw-immo-suite'),
                    'remove'       => __('Von der Merkliste entfernen', 'dbw-immo-suite'),
                    'addedToast'   => __('Zur Merkliste hinzugefuegt', 'dbw-immo-suite'),
                    'removedToast' => __('Von der Merkliste entfernt', 'dbw-immo-suite'),
                    'empty'  => \DBW\ImmoSuite\dbw_anrede(
                        __('Noch keine Objekte gemerkt. Klicken Sie das Herz auf einer Immobilie, um sie hier zu sammeln.', 'dbw-immo-suite'),
                        __('Noch keine Objekte gemerkt. Klicke das Herz auf einer Immobilie, um sie hier zu sammeln.', 'dbw-immo-suite')
                    ),
                ),
            ));
            $frontend_deps[] = 'dbw-immo-favorites-js';
        }

        // Icons are inline SVGs since v2.2.0 — no dashicons font needed for visitors
        wp_register_style('dbw-immo-frontend', self::asset_url('assets/css/frontend.css'), array(), DBW_IMMO_SUITE_VERSION, 'all');
        wp_register_script('dbw-immo-frontend-js', self::asset_url('assets/js/frontend.js'), $frontend_deps, DBW_IMMO_SUITE_VERSION, array('in_footer' => true, 'strategy' => 'defer'));
        wp_register_script('dbw-immo-view-switch-js', self::asset_url('assets/js/view-switch.js'), array(), DBW_IMMO_SUITE_VERSION, array('in_footer' => true, 'strategy' => 'defer'));

        // Auto-enqueue on immobilie CPT pages, archives, and taxonomy pages
        if (is_singular('immobilie') || is_post_type_archive('immobilie') || is_tax(array('objektart', 'vermarktungsart', 'ort'))) {
            wp_enqueue_style('dbw-immo-frontend');
            wp_enqueue_script('dbw-immo-frontend-js');
            wp_enqueue_script('dbw-immo-view-switch-js');
        }

        // AJAX filtering on archive + taxonomy pages
        if (is_post_type_archive('immobilie') || is_tax(array('objektart', 'vermarktungsart', 'ort'))) {
            wp_enqueue_script('dbw-immo-filter-ajax', self::asset_url('assets/js/filter-ajax.js'), array(), DBW_IMMO_SUITE_VERSION, array('in_footer' => true, 'strategy' => 'defer'));
            wp_localize_script('dbw-immo-filter-ajax', 'dbwImmoFilter', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'i18n'    => array(
                    'showResults'  => __('%d Objekte anzeigen', 'dbw-immo-suite'),
                    'noResults'    => __('Keine Immobilien gefunden.', 'dbw-immo-suite'),
                    'networkError' => __('Verbindung fehlgeschlagen. Bitte erneut versuchen.', 'dbw-immo-suite'),
                ),
            ));
        }

        // Archive map view (Leaflet from local vendor copy)
        if ((is_post_type_archive('immobilie') || is_tax(array('objektart', 'vermarktungsart', 'ort')))
            && \DBW\ImmoSuite\Frontend\ArchiveMap::is_enabled()) {
            wp_enqueue_style('leaflet', DBW_IMMO_SUITE_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4');
            wp_enqueue_script('leaflet', DBW_IMMO_SUITE_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true);
            self::enqueue_map_consent_bridge();
            wp_enqueue_script('dbw-immo-archive-map-js', self::asset_url('assets/js/archive-map.js'), array('leaflet', 'dbw-immo-map-consent'), DBW_IMMO_SUITE_VERSION, array('in_footer' => true, 'strategy' => 'defer'));
        }

        // Single property page scripts (lightbox + contact modal)
        if (is_singular('immobilie')) {
            // Leaflet (local vendor copy) — enqueued here so the CSS lands in wp_head,
            // not mid-template after the head is already printed
            $map_post_id = get_queried_object_id();
            $lat = get_post_meta($map_post_id, 'geo_breite', true);
            $lng = get_post_meta($map_post_id, 'geo_laenge', true);
            if ($lat && $lng
                && get_theme_mod('dbw_immo_single_show_map', true)
                && get_theme_mod('dbw_immo_single_show_address', true)) {
                wp_enqueue_style('leaflet', DBW_IMMO_SUITE_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4');
                wp_enqueue_script('leaflet', DBW_IMMO_SUITE_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true);
                self::enqueue_map_consent_bridge();
            }

            wp_enqueue_script('dbw-immo-lightbox', self::asset_url('assets/js/lightbox.js'), array(), DBW_IMMO_SUITE_VERSION, array('in_footer' => true, 'strategy' => 'defer'));
            wp_enqueue_script('dbw-immo-gallery', self::asset_url('assets/js/gallery.js'), array(), DBW_IMMO_SUITE_VERSION, array('in_footer' => true, 'strategy' => 'defer'));
            wp_enqueue_script('dbw-immo-section-nav', self::asset_url('assets/js/section-nav.js'), array(), DBW_IMMO_SUITE_VERSION, array('in_footer' => true, 'strategy' => 'defer'));
            wp_localize_script('dbw-immo-section-nav', 'dbwSectionNav', array(
                'position' => get_theme_mod('dbw_immo_single_section_nav', 'top'),
                'label'    => __('Abschnitte', 'dbw-immo-suite'),
            ));
            wp_enqueue_script('dbw-immo-count-up', self::asset_url('assets/js/count-up.js'), array(), DBW_IMMO_SUITE_VERSION, array('in_footer' => true, 'strategy' => 'defer'));
            wp_enqueue_script('dbw-immo-contact-modal', self::asset_url('assets/js/contact-modal.js'), array(), DBW_IMMO_SUITE_VERSION, array('in_footer' => true, 'strategy' => 'defer'));
            wp_localize_script('dbw-immo-contact-modal', 'dbwContactModal', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'i18n'    => array(
                    'sending' => \DBW\ImmoSuite\dbw_anrede(
                        __('Senden...', 'dbw-immo-suite'),
                        __('Senden...', 'dbw-immo-suite')
                    ),
                    'network_error' => \DBW\ImmoSuite\dbw_anrede(
                        __('Netzwerkfehler', 'dbw-immo-suite'),
                        __('Netzwerkfehler', 'dbw-immo-suite')
                    ),
                ),
            ));
        }
    }

    /**
     * Enqueue assets for blocks (both frontend AND editor)
     */
    public function enqueue_block_assets()
    {
        // In the editor, always load so blocks render correctly; on the frontend, conditional loading is handled by enqueue_public_scripts
        if (is_admin()) {
            wp_enqueue_style('dbw-immo-frontend', self::asset_url('assets/css/frontend.css'), array(), filemtime(DBW_IMMO_SUITE_PATH . 'assets/css/frontend.css'), 'all');
        }
    }

    /**
     * Register custom block categories.
     */
    public function register_block_categories($categories, $post)
    {
        return array_merge(
            $categories,
            array(
                array(
                'slug' => 'dbw-immo-suite',
                'title' => __('Immo Suite', 'dbw-immo-suite'),
            ),
        )
        );
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run()
    {
        $this->loader->run();
    }
}
