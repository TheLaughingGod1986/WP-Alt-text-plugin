<?php
/**
 * Meta Compatibility Trait
 * Handles post meta with backward compatibility for key prefixes
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

trait Meta_Compat {

    /**
     * Get post meta with compatibility for old key prefixes.
     *
     * @param int    $post_id Post ID.
     * @param string $key     Meta key (without prefix).
     * @param bool   $single  Whether to return a single value.
     * @return mixed Meta value.
     */
    private function get_meta_with_compat($post_id, $key, $single = true) {
        $new_key = '_beepbeepai_' . $key;
        $old_key = '_ai_alt_' . $key;

        // Check for new key first
        $value = get_post_meta($post_id, $new_key, $single);
        if ($value !== '' && $value !== false && $value !== null) {
            return $value;
        }

        // Check for old key and migrate if found
        $old_value = get_post_meta($post_id, $old_key, $single);
        if ($old_value !== '' && $old_value !== false && $old_value !== null) {
            // Migrate to new key
            update_post_meta($post_id, $new_key, $old_value);
            // Delete old key after migration
            delete_post_meta($post_id, $old_key);
            return $old_value;
        }

        return $single ? '' : [];
    }

    /**
     * Update post meta using new beepbeepai_ prefix.
     *
     * @param int    $post_id Post ID.
     * @param string $key     Meta key (without prefix).
     * @param mixed  $value   Meta value.
     * @return bool|int Result of update_post_meta.
     */
    private function update_meta_with_compat($post_id, $key, $value) {
        $new_key = '_beepbeepai_' . $key;
        $old_key = '_ai_alt_' . $key;

        // Update new key
        $result = update_post_meta($post_id, $new_key, $value);

        // Delete old key if it exists (migration cleanup)
        if (metadata_exists('post', $post_id, $old_key)) {
            delete_post_meta($post_id, $old_key);
        }

        return $result;
    }

    /**
     * Delete post meta from both old and new keys.
     *
     * @param int    $post_id Post ID.
     * @param string $key     Meta key (without prefix).
     * @return bool Result of delete_post_meta.
     */
    private function delete_meta_with_compat($post_id, $key) {
        $new_key = '_beepbeepai_' . $key;
        $old_key = '_ai_alt_' . $key;

        $result1 = delete_post_meta($post_id, $new_key);
        $result2 = delete_post_meta($post_id, $old_key);

        return $result1 || $result2;
    }
}
