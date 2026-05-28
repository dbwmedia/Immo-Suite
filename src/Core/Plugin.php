<?php

namespace DBW\ImmoSuite\Core;

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
        $this->define_public_hooks();
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
    public function enqueue_admin_scripts()
    {
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
        $plugin_post_types = new \DBW\ImmoSuite\PostTypes\Property();
        $this->loader->add_action('init', $plugin_post_types, 'register_post_type');

        $plugin_tax_objektart = new \DBW\ImmoSuite\Taxonomies\PropertyType();
        $this->loader->add_action('init', $plugin_tax_objektart, 'register_taxonomy');

        $plugin_tax_vermarktung = new \DBW\ImmoSuite\Taxonomies\MarketingType();
        $this->loader->add_action('init', $plugin_tax_vermarktung, 'register_taxonomy');

        $plugin_tax_location = new \DBW\ImmoSuite\Taxonomies\Location();
        $this->loader->add_action('init', $plugin_tax_location, 'register_taxonomy');

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
        // Register assets so blocks/shortcodes can enqueue them on-demand
        wp_register_style('dbw-immo-frontend', DBW_IMMO_SUITE_URL . 'assets/css/frontend.css', array(), DBW_IMMO_SUITE_VERSION, 'all');
        wp_register_script('dbw-immo-frontend-js', DBW_IMMO_SUITE_URL . 'assets/js/frontend.js', array(), DBW_IMMO_SUITE_VERSION, true);
        wp_register_script('dbw-immo-view-switch-js', DBW_IMMO_SUITE_URL . 'assets/js/view-switch.js', array(), DBW_IMMO_SUITE_VERSION, true);

        // Auto-enqueue on immobilie CPT pages, archives, and taxonomy pages
        if (is_singular('immobilie') || is_post_type_archive('immobilie') || is_tax(array('objektart', 'vermarktungsart', 'ort'))) {
            wp_enqueue_style('dbw-immo-frontend');
            wp_enqueue_script('dbw-immo-frontend-js');
            wp_enqueue_script('dbw-immo-view-switch-js');
        }

        // Contact modal script (single property pages only)
        if (is_singular('immobilie')) {
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
                'title' => __('dbw Immo Suite', 'dbw-immo-suite'),
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