<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

/**
 * Page number for custom property queries.
 *
 * On a static front page WordPress moves /page/2/ from 'paged' into 'page'
 * (WP_Query::parse_query), so reading only 'paged' always returns page 1
 * there: the links point to page 2, but the grid keeps showing page 1.
 */
class Pagination
{
    public static function current()
    {
        $paged = (int) get_query_var('paged');
        if ($paged < 1 && is_front_page()) {
            $paged = (int) get_query_var('page');
        }
        return max(1, $paged);
    }
}
