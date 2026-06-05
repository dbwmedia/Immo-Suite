<?php

namespace DBW\ImmoSuite\Core;

if (!defined('ABSPATH')) { exit; }

/**
 * Simple license key validation.
 *
 * Keys are stored as SHA256 hashes — the plain-text keys never appear in the codebase.
 * To add more keys: hash them with hash('sha256', 'DBWIS-XXXX-XXXX-XXXX') and append to the array.
 */
class License
{
    const OPTION_KEY = 'dbw_immo_license_key';
    const OPTION_STATUS = 'dbw_immo_license_status';

    private static $valid_hashes = array(
        '7287757307d186a26846edbd02644a52b9fb569e2a19aa5cd043e6b7c28191f6',
        '68d9b7c344e43eaed8bec28298df9d0f59bbdc47c1e85294ff5ae6f107db6bf4',
        '9d87fb329ef24f52f8e763556ec0f4e18c5d77095a6eae4e44072430ae6f925e',
        'c953036bef7fbd7d6e319fb1aadd2420b6a3698c0327e21a592e88e8fbafe0f3',
        '85415b577cd1829891327c9de701474910a72662a5b6650cb8a478bc256a3caf',
        '4b2ddcda8e8720416d78fd59d8aeb93cd1f29c87f016879e6dbe61012884a832',
        '35e007386a1ee6fcf0fd0a54cfc5ea821bddb361013a94858d5022085bd4d98a',
        '32a80c22b987f59ccc498a2558a6a24ef79a6b216d6535ce5b5866d9604a084b',
        'a518caa7e7db18b8923e814587cf8ff9a07c903341de3d540750fa82f888428f',
        '1a9f0d7aa5a3285369c8d8b3e45b7aa3efdb1ea55af4e5059022d2505581ffad',
        '8dd6561b4d06c78d4eab66f6955e9283fd60af0e0dfa24274705adaaa65462e6',
        'fee9a5912a91b59b0c594e7f794a8f690b74d76baed147e67ba5d21ca356ce71',
        '37087a12019ba54c1a38b024ba09dc7fae31f50a1b0eebb9ce0bd6746ccd10c6',
        '81b5c8b7968ecaf2251ca77c5b3a247a72e145de2646c3b5111386c0de03142a',
        'c20cfc28470931eb46d8b2a4ff25c696f3605ca5af4f9c91474fb4df96a23ae4',
        'ddeca132cb41323a96edf55ba0d27fb4d70027d98619f436eca3590f6608bbc3',
        '6fd4e16c52a284f43cf9ec608f6f80896d8c3e0cd480a95320bf8671c6ac619e',
        'f1d5f0b800b6005bf1a55d9e8f30f6a4d3f79bfb206dd130c43824d682b9dfe3',
        '5c74cf2c6f088416f597c19e3835bb7e8a1306624c788d5a4202b4257fd6bcbe',
        '944c0b4d1b9b0b271854caab265b6451359f32b4d4f2da2f086b9326efb45dd7',
        '1667b6e4169293732bba8249923ecb8e797ee85f8dff31c6cfdf26651424ce6d',
        'df8a2e3c7996f688a43bc566333704fec3ed40b90471ebc0e2d7655b383735b8',
        '1638e717120f82d43214232b186d6ee207b4fb651bb51216b140c9b9127725e1',
        'f61d24f707f430dc72133c698c6ded1f174a2f64868524bfa4e84babd1e7a387',
        '473d7d654659a30250e5a7d9eb380d6ea2d494fe39d242e660a633433c4fc47d',
        'b14eb4bf2d72bdc201244957a937178071139bc4d7e56cfb4bb94306d3be1cc1',
        '29ca717aac9f39797dcbc94712fa5c5a1b6c9b3410d9531d1e09ccd096a69122',
        'c74c5d725d85fb0a71685803c66ea25ff7b276205735b6269bfa858f18c4b53e',
        'a937deafd6ef57db25ce798f5a1998dabae17723558509bf170d4368a77b3b47',
        '3a2c22700a41fe6ce2e3e4fbdb294774e6cb3be8a23f45a071f68716caad829e',
    );

    public function init()
    {
        add_action('admin_notices', array($this, 'license_notice'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_dbw_immo_activate_license', array($this, 'activate_license'));
    }

    /**
     * Check if a valid license is active.
     */
    public static function is_valid()
    {
        return get_option(self::OPTION_STATUS) === 'valid';
    }

    /**
     * Validate a key against stored hashes.
     */
    public static function validate_key($key)
    {
        $key = strtoupper(trim($key));
        $hash = hash('sha256', $key);
        return in_array($hash, self::$valid_hashes, true);
    }

    /**
     * Handle license activation form submission.
     */
    public function activate_license()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('dbw_immo_license_activate');

        $key = isset($_POST['dbw_immo_license_key']) ? sanitize_text_field($_POST['dbw_immo_license_key']) : '';

        if (self::validate_key($key)) {
            update_option(self::OPTION_KEY, $key);
            update_option(self::OPTION_STATUS, 'valid');
            $redirect = add_query_arg('dbw_license', 'activated', admin_url('edit.php?post_type=immobilie&page=dbw-immo-suite-settings#tab-license'));
        } else {
            delete_option(self::OPTION_STATUS);
            $redirect = add_query_arg('dbw_license', 'invalid', admin_url('edit.php?post_type=immobilie&page=dbw-immo-suite-settings#tab-license'));
        }

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Register license settings.
     */
    public function register_settings()
    {
        register_setting('dbw_immo_settings', self::OPTION_KEY, array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
    }

    /**
     * Show admin notice if no valid license.
     */
    public function license_notice()
    {
        if (self::is_valid()) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo '<strong>dbw Immo Suite:</strong> ';
        echo 'Bitte aktiviere deine Lizenz unter ';
        echo '<a href="' . esc_url(admin_url('edit.php?post_type=immobilie&page=dbw-immo-suite-settings#tab-license')) . '">Einstellungen</a>.';
        echo '</p></div>';
    }
}
