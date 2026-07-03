<?php

namespace DBW\ImmoSuite\Core;

use DBW\ImmoSuite\PostTypes\Inquiry;

if (!defined('ABSPATH')) { exit; }

class Privacy
{
    const PER_PAGE = 50;

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

    /**
     * Inquiries matching the given email (paged).
     */
    private function find_inquiries($email, $page)
    {
        return get_posts(array(
            'post_type'      => Inquiry::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => self::PER_PAGE,
            'paged'          => max(1, (int) $page),
            'fields'         => 'ids',
            'meta_key'       => '_dbw_anfrage_email',
            'meta_value'     => sanitize_email($email),
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ));
    }

    /**
     * Export stored inquiries for the WP privacy export (Art. 15 GDPR).
     */
    public function export_personal_data($email, $page = 1)
    {
        $ids  = $this->find_inquiries($email, $page);
        $data = array();

        $intent_labels = Inquiry::get_intent_labels();

        foreach ($ids as $post_id) {
            $m = function ($key) use ($post_id) {
                return get_post_meta($post_id, '_dbw_anfrage_' . $key, true);
            };
            $intent = $m('intent');

            $item_data = array(
                array('name' => __('Datum', 'dbw-immo-suite'), 'value' => get_the_date('Y-m-d H:i', $post_id)),
                array('name' => __('Name', 'dbw-immo-suite'), 'value' => $m('name')),
                array('name' => __('E-Mail', 'dbw-immo-suite'), 'value' => $m('email')),
                array('name' => __('Telefon', 'dbw-immo-suite'), 'value' => $m('phone')),
                array('name' => __('Anliegen', 'dbw-immo-suite'), 'value' => isset($intent_labels[$intent]) ? $intent_labels[$intent] : $intent),
                array('name' => __('Nachricht', 'dbw-immo-suite'), 'value' => $m('message')),
                array('name' => __('Objekt', 'dbw-immo-suite'), 'value' => get_the_title((int) $m('property'))),
                array('name' => __('Datenschutz-Zustimmung', 'dbw-immo-suite'), 'value' => $m('consent')),
            );

            $data[] = array(
                'group_id'    => 'dbw-immo-anfragen',
                'group_label' => __('Immobilien-Anfragen', 'dbw-immo-suite'),
                'item_id'     => 'dbw-anfrage-' . $post_id,
                'data'        => array_values(array_filter($item_data, function ($row) {
                    return $row['value'] !== '' && $row['value'] !== null;
                })),
            );
        }

        return array(
            'data' => $data,
            'done' => count($ids) < self::PER_PAGE,
        );
    }

    /**
     * Erase stored inquiries for the WP privacy eraser (Art. 17 GDPR).
     */
    public function erase_personal_data($email, $page = 1)
    {
        // Always delete from page 1: removed posts shift the paging window
        $ids     = $this->find_inquiries($email, 1);
        $removed = false;

        foreach ($ids as $post_id) {
            if (wp_delete_post($post_id, true)) {
                $removed = true;
            }
        }

        return array(
            'items_removed'  => $removed,
            'items_retained' => false,
            'messages'       => array(),
            'done'           => count($ids) < self::PER_PAGE,
        );
    }
}
