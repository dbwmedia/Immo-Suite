<?php

namespace DBW\ImmoSuite\Core;

if (!defined('ABSPATH')) { exit; }

/**
 * Deletes attached media when an Immobilie is permanently deleted.
 */
class MediaCleanup
{
    public function init()
    {
        add_action('before_delete_post', array($this, 'delete_attachments'));
    }

    /**
     * Delete all attachments that belong to an immobilie post.
     */
    public function delete_attachments($post_id)
    {
        if (get_post_type($post_id) !== 'immobilie') {
            return;
        }

        $attachments = get_posts(array(
            'post_type'      => 'attachment',
            'post_parent'    => $post_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => 'any',
        ));

        foreach ($attachments as $att_id) {
            wp_delete_attachment($att_id, true);
        }
    }
}
