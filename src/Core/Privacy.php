<?php

namespace DBW\ImmoSuite\Core;

if (!defined('ABSPATH')) { exit; }

class Privacy
{
    public function init()
    {
        add_filter('wp_privacy_personal_data_exporters', array($this, 'register_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array($this, 'register_eraser'));
    }

    public function register_exporter($exporters)
    {
        $exporters['dbw-immo-suite'] = array(
            'exporter_friendly_name' => __('Immo Suite', 'dbw-immo-suite'),
            'callback'               => array($this, 'export_personal_data'),
        );
        return $exporters;
    }

    public function register_eraser($erasers)
    {
        $erasers['dbw-immo-suite'] = array(
            'eraser_friendly_name' => __('Immo Suite', 'dbw-immo-suite'),
            'callback'             => array($this, 'erase_personal_data'),
        );
        return $erasers;
    }

    public function export_personal_data($email, $page = 1)
    {
        // Plugin does not store personal data in the database.
        // Contact form submissions are sent via email only.
        return array(
            'data' => array(),
            'done' => true,
        );
    }

    public function erase_personal_data($email, $page = 1)
    {
        // Plugin does not store personal data in the database.
        return array(
            'items_removed'  => false,
            'items_retained' => false,
            'messages'       => array(),
            'done'           => true,
        );
    }
}
