<?php
/*
Plugin Name: Elasticpress Featured Image Url
Plugin URI: https://github.com/rveitch/elasticpress-featured-image-url
Description: Adds the featured image url to WordPress post meta for easy indexing with Elasticpress.
Author: Ryan Veitch
Author URI: http://forumcomm.com/
Version: 1.16.06.15
*/

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/*--------------------------------------------------------------
# Plugin Functions
--------------------------------------------------------------*/

/**
 * Add Featured Image URL to Post Meta
 *
 * Triggers on save, edit or update of published posts
 * Works in "Quick Edit", but not bulk edit.
 * @since 1.16.05.05
 * @version 1.16.05.05
 */
function ep_add_featured_image_post_meta_hook( $post_id, $post, $update ) {
	if ( 'post' == $post->post_type && 'publish' == $post->post_status ) {
		$featured_image_url = wp_get_attachment_url( get_post_thumbnail_id( $post_id ) );
		update_post_meta( $post_id, 'featured_image_url', $featured_image_url );
	}
}
add_action( 'wp_insert_post', 'ep_add_featured_image_post_meta_hook', 10, 3 );


/*--------------------------------------------------------------
# WP-CLI Commands
--------------------------------------------------------------*/

/**
 * @since 1.16.06.15
 * @version 1.16.06.15
 */
function update_featured_image_meta( $args, $assoc_args ) {

	$url = $assoc_args['url'];

	if ( $url ) {
		/* Multisite */
		$response = WP_CLI::launch_self( 'post list', array(), array( 'format' => 'json', 'field' => 'ID', 'post_type' => 'post', 'post_status' => 'publish', 'url' => $url ), false, true );
	} else {
		/* Single Site (Main) */
		$response = WP_CLI::launch_self( 'post list', array(), array( 'format' => 'json', 'field' => 'ID', 'post_type' => 'post', 'post_status' => 'publish' ), false, true );
	}

	$post_ids = json_decode( $response->stdout );
	$count = count( $post_ids );
	$skipped = 0;
	$success = 0;

	if ( $url ) {
		/* Multisite */
		$notify = \WP_CLI\Utils\make_progress_bar( WP_CLI::colorize( "%B$url%n" ) . ': Processing ' . WP_CLI::colorize( "%c$count%n" ) . ' posts.' , intval( $count ) );
	} else {
		/* Single Site (Main) */
		$notify = \WP_CLI\Utils\make_progress_bar( 'Processing ' . WP_CLI::colorize( "%c$count%n" ) . ' posts', intval( $count ) );
	};

	foreach ( $post_ids as $post_id ) {
		$featured_image_url = wp_get_attachment_url( get_post_thumbnail_id( $post_id ) );
		if ( $featured_image_url ) {
			update_post_meta( $post_id, 'featured_image_url', $featured_image_url );
			//$success++;
			//WP_CLI::success( 'Post ' . $post_id . ' updated with ' . $featured_image_url );
		} else {
			//WP_CLI::warning( 'No thumbnail found, skipping post ' . $post_id );
			//$skipped++;
		}
		$notify->tick();
	}
	$notify->finish();
}
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'update-featured-image-meta', 'update_featured_image_meta' );
}

/**
 * @since 1.16.06.15
 * @version 1.16.06.15
 */
$network_update_featured_image_meta_command = function() {
	$response = WP_CLI::launch_self( 'site list', array(), array( 'format' => 'json' ), false, true );
	$sites = json_decode( $response->stdout );
	$count = count( $sites );
	foreach ( $sites as $site ) {
		$site_id = $site->blog_id;
		switch_to_blog( $site_id );
		update_featured_image_meta( array(), array( 'url' => $site->url ) );
		restore_current_blog();
	}
};

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'network-update-featured-image-meta', $network_update_featured_image_meta_command, array(
		'before_invoke' => function(){
			if ( ! is_multisite() ) {
				WP_CLI::error( 'This is not a multisite install.' );
			}
		},
	));
}
