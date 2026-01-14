<?php

/*
 * Plugin Name:     Disable Search
 * Plugin URI:      https://github.com/wvnderlab-agency/wp-disable-search/
 * Author:          Wvnderlab Agency
 * Author URI:      https://wvnderlab.com
 * Text Domain:     wvnderlab-disable-search
 * Version:         0.1.0
 */

/*
 *  ################
 *  ##            ##    Copyright (c) 2025 Wvnderlab Agency
 *  ##
 *  ##   ##  ###  ##    ✉️ moin@wvnderlab.com
 *  ##    #### ####     🔗 https://wvnderlab.com
 *  #####  ##  ###
 */

declare(strict_types=1);

namespace WvnderlabAgency\DisableSearch;

defined( 'ABSPATH' ) || die;

// Return early if running in WP-CLI context.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	return;
}

/**
 * Filter: Disable Search Enabled
 *
 * @param bool $enabled Whether to enable the disable search functionality. Default true.
 * @return bool
 */
if ( ! apply_filters( 'wvnderlab/disable-search/enabled', true ) ) {
	return;
}

/**
 * Disable or redirects any searches.
 *
 * @link   https://developer.wordpress.org/reference/hooks/template_redirect/
 * @hooked action template_redirect
 *
 * @return void
 */
function disable_or_redirect_search(): void {
	// return early if is not a search results page.
	if ( ! is_search() ) {

		return;
	}

	// return early if in admin, ajax, cron, rest api or wp-cli context.
	if (
		is_admin()
		|| wp_doing_ajax()
		|| wp_doing_cron()
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
	) {
		return;
	}

	/**
	 * Filter: Disable Search Status Code
	 *
	 * Supported:
	 * - 301 / 302 / 307 / 308  → redirect
	 * - 404 / 410              → no redirect, proper error response
	 *
	 * @param int $status_code The HTTP status code for the redirect. Default is 404 (Not Found).
	 * @return int
	 */
	$status_code = (int) apply_filters(
		'wvnderlab/disable-search/status-code',
		404
	);

	// Handle 404 and 410 status codes separately.
	if ( in_array( $status_code, array( 404, 410 ), true ) ) {
		global $wp_query;

		$wp_query->set_404();
		status_header( $status_code );
		nocache_headers();

		$template = get_query_template( '404' );

		if ( $template ) {
			include $template;
		} else {
			wp_die(
				esc_html__( '404 Not Found', 'wvnderlab-disable-search' ),
				esc_html__( 'Not Found', 'wvnderlab-disable-search' ),
				array( 'response' => esc_html( $status_code ) )
			);
		}

		exit;
	}

	// Ensure the status code is a valid redirect code.
	if ( $status_code < 300 || $status_code > 399 ) {
		$status_code = 301;
	}

	/**
	 * Filter: Disable Search Redirect URL
	 *
	 * Allows modification of the redirect URL for disabled search.
	 *
	 * @param string $redirect_url The URL to redirect to. Default is the homepage.
	 * @return string
	 */
	$redirect_url = (string) apply_filters(
		'wvnderlab/disable-search/redirect-url',
		home_url()
	);

	// Ensure the redirect URL is not empty.
	if ( empty( $redirect_url ) ) {
		$redirect_url = home_url();
	}

	wp_safe_redirect( $redirect_url, $status_code );

	exit;
}

add_action( 'template_redirect', __NAMESPACE__ . '\\disable_or_redirect_search' );

/**
 * Remove REST Search Endpoints
 *
 * @link   https://developer.wordpress.org/reference/hooks/rest_endpoints/
 * @hooked filter rest_endpoints
 *
 * @param array<string,mixed> $endpoints The REST API endpoints.
 * @return array<string,mixed>
 */
function remove_search_rest_endpoint( array $endpoints ): array {
	if ( isset( $endpoints['/wp/v2/search'] ) ) {
		unset( $endpoints['/wp/v2/search'] );
	}

	return $endpoints;
}

add_filter( 'rest_endpoints', __NAMESPACE__ . '\\remove_search_rest_endpoint', PHP_INT_MAX );

/**
 * Disable the search form output.
 *
 * @link   https://developer.wordpress.org/reference/hooks/get_search_form/
 * @hooked filter get_search_form
 *
 * @return string
 */
function disable_search_form(): string {

	return '';
}

add_filter( 'get_search_form', __NAMESPACE__ . '\\disable_search_form' );

/**
 * Remove search query var to disable search functionality.
 *
 * @link   https://developer.wordpress.org/reference/hooks/request/
 * @hooked filter request
 *
 * @param array $query_vars The query variables.
 * @return array
 */
function remove_search_from_query( array $query_vars ): array {
	if ( isset( $query_vars['s'] ) ) {
		unset( $query_vars['s'] );
	}

	return $query_vars;
}

add_filter( 'request', __NAMESPACE__ . '\\remove_search_from_query' );

/**
 * Unregister Search Blocks
 *
 * @link   https://developer.wordpress.org/reference/hooks/admin_print_scripts/
 * @hooked action admin_print_scripts
 *
 * @return void
 */
function unregister_search_blocks(): void {
	$blocks = array(
		'core/search',
	);

	echo '<script type="text/javascript">';
	echo "addEventListener('DOMContentLoaded', function() {";
	echo 'window.wp.domReady( function() {';
	foreach ( $blocks as $block ) {
		echo "window.wp.blocks.unregisterBlockType( '" . esc_js( $block ) . "' );";
	}
	echo '} );';
	echo '} );';
	echo '</script>';
}

add_action( 'admin_print_scripts', __NAMESPACE__ . '\\unregister_search_blocks', PHP_INT_MAX );

/**
 * Unregister Search Widget
 *
 * @link   https://developer.wordpress.org/reference/hooks/widgets_init/
 * @hooked action widgets_init
 *
 * @return void
 */
function unregister_search_widgets(): void {
	unregister_widget( 'WP_Widget_Search' );
}

add_action( 'widgets_init', __NAMESPACE__ . '\\unregister_search_widgets', PHP_INT_MAX );
