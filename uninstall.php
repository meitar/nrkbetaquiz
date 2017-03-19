<?php
/**
 * Quiz Commenters uninstaller
 *
 * @package plugin
 */

// Don't execute any uninstall code unless WordPress core requests it.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit(); }

delete_post_meta_by_key( 'quizcommenters' );
