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

        $import_dashboard = new \DBW\ImmoSuite\Admin\ImportDashboard();
        $this->loader->add_action('init', $import_dashboard, 'init');

        $page_generator = new \DBW\ImmoSuite\Core\PageGenerator();
        $this->loader->add_action('init', $page_generator, 'init');

        // AJAX Import
        $importer = new \DBW\ImmoSuite\Import\Importer();
        $this->loader->add_action('wp_ajax_dbw_immo_run_import', $importer, 'ajax_run_import');
        $this->loader->add_action('wp_ajax_dbw_immo_prepare_import', $importer, 'ajax_prepare_import');
        $this->loader->add_action('wp_ajax_dbw_immo_process_batch', $importer, 'ajax_process_batch');
        $this->loader->add_action('wp_ajax_dbw_immo_finalize_import', $importer, 'ajax_finalize_import');
        $this->loader->add_action('wp_ajax_dbw_immo_import_progress', $importer, 'ajax_import_progress');

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
     * Enqueue Admin Scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        $screen = get_current_screen();
        if (!$screen) return;

        $allowed = array(
            'immobilie_page_dbw-immo-import',
            'immobilie_page_dbw-immo-settings',
            'immobilie',
            'edit-immobilie',
        );

        if (!in_array($screen->id, $allowed, true)) return;

        wp_enqueue_script('dbw-immo-admin', DBW_IMMO_SUITE_URL . 'assets/js/admin.js', array('jquery'), DBW_IMMO_SUITE_VERSION, false);
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

        $plugin_media_cleanup = new \DBW\ImmoSuite\Core\MediaCleanup();
        $this->loader->add_action('init', $plugin_media_cleanup, 'init');

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
        $frontend_deps = array();
        if (\DBW\ImmoSuite\Frontend\Favorites::is_enabled()) {
            wp_register_script('dbw-immo-favorites-js', DBW_IMMO_SUITE_URL . 'assets/js/favorites.js', array(), DBW_IMMO_SUITE_VERSION, true);
            wp_localize_script('dbw-immo-favorites-js', 'dbwFavorites', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'i18n'    => array(
                    'add'    => __('Zur Merkliste hinzufuegen', 'dbw-immo-suite'),
                    'remove' => __('Von der Merkliste entfernen', 'dbw-immo-suite'),
                    'empty'  => \DBW\ImmoSuite\dbw_anrede(
                        __('Noch keine Objekte gemerkt. Klicken Sie das Herz auf einer Immobilie, um sie hier zu sammeln.', 'dbw-immo-suite'),
                        __('Noch keine Objekte gemerkt. Klicke das Herz auf einer Immobilie, um sie hier zu sammeln.', 'dbw-immo-suite')
                    ),
                ),
            ));
            $frontend_deps[] = 'dbw-immo-favorites-js';
        }

        // dashicons as dependency: used for view switcher, filter and location pins —
        // WP only auto-loads them for logged-in users (admin bar), not for visitors
        wp_register_style('dbw-immo-frontend', DBW_IMMO_SUITE_URL . 'assets/css/frontend.css', array('dashicons'), DBW_IMMO_SUITE_VERSION, 'all');
        wp_register_script('dbw-immo-frontend-js', DBW_IMMO_SUITE_URL . 'assets/js/frontend.js', $frontend_deps, DBW_IMMO_SUITE_VERSION, true);
        wp_register_script('dbw-immo-view-switch-js', DBW_IMMO_SUITE_URL . 'assets/js/view-switch.js', array(), DBW_IMMO_SUITE_VERSION, true);

        // Auto-enqueue on immobilie CPT pages, archives, and taxonomy pages
        if (is_singular('immobilie') || is_post_type_archive('immobilie') || is_tax(array('objektart', 'vermarktungsart', 'ort'))) {
            wp_enqueue_style('dbw-immo-frontend');
            wp_enqueue_script('dbw-immo-frontend-js');
            wp_enqueue_script('dbw-immo-view-switch-js');
        }

        // Archive map view (Leaflet from local vendor copy)
        if ((is_post_type_archive('immobilie') || is_tax(array('objektart', 'vermarktungsart', 'ort')))
            && \DBW\ImmoSuite\Frontend\ArchiveMap::is_enabled()) {
            wp_enqueue_style('leaflet', DBW_IMMO_SUITE_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4');
            wp_enqueue_script('leaflet', DBW_IMMO_SUITE_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true);
            wp_enqueue_script('dbw-immo-archive-map-js', DBW_IMMO_SUITE_URL . 'assets/js/archive-map.js', array('leaflet'), DBW_IMMO_SUITE_VERSION, true);
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
            }

            wp_enqueue_script('dbw-immo-lightbox', DBW_IMMO_SUITE_URL . 'assets/js/lightbox.js', array(), DBW_IMMO_SUITE_VERSION, true);
            wp_enqueue_script('dbw-immo-contact-modal', DBW_IMMO_SUITE_URL . 'assets/js/contact-modal.js', array(), DBW_IMMO_SUITE_VERSION, true);
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
            wp_enqueue_style('dbw-immo-frontend', DBW_IMMO_SUITE_URL . 'assets/css/frontend.css', array(), filemtime(DBW_IMMO_SUITE_PATH . 'assets/css/frontend.css'), 'all');
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
                'icon' => 'admin-home',
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
