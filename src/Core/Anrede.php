<?php

if (!defined('ABSPATH')) { exit; }

namespace DBW\ImmoSuite\Core;

/**
 * Global Du/Sie address mode helper.
 *
 * Usage: Anrede::pick('Sie-Text', 'Du-Text') or dbw_anrede('Sie', 'Du')
 */
class Anrede
{
    /**
     * Returns the appropriate text based on the global anrede setting.
     */
    public static function pick($sie_text, $du_text)
    {
        return self::mode() === 'du' ? $du_text : $sie_text;
    }

    /**
     * Returns 'du' or 'sie' as raw mode value.
     */
    public static function mode()
    {
        $opts = get_option('dbw_immo_suite_settings');
        return isset($opts['anrede']) ? $opts['anrede'] : 'sie';
    }

    /**
     * Possessive pronoun: Ihre/deine
     */
    public static function ihre($capitalize = false)
    {
        if (self::mode() === 'du') {
            return $capitalize ? 'Deine' : 'deine';
        }
        return $capitalize ? 'Ihre' : 'ihre';
    }

    /**
     * Dative pronoun: Ihnen/dir
     */
    public static function ihnen($capitalize = false)
    {
        if (self::mode() === 'du') {
            return $capitalize ? 'Dir' : 'dir';
        }
        return $capitalize ? 'Ihnen' : 'ihnen';
    }

    /**
     * Personal pronoun: Sie/du
     */
    public static function sie_pronoun($capitalize = true)
    {
        if (self::mode() === 'du') {
            return $capitalize ? 'Du' : 'du';
        }
        return $capitalize ? 'Sie' : 'sie';
    }
}
