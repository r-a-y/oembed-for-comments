<?php
/*
Plugin Name: oEmbed for Comments
Description: Use oEmbed to transform embed links to rich-media in comments.
Author: r-a-y
Author URI: https://profiles.wordpress.org/r-a-y
Version: 1.0.0
License: GPLv2 or later
*/

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', array( 'Ray_Comments_Embed', 'init' ) );

/**
 * Enable oEmbeds in comments.
 *
 * @since 2.0.0
 *
 * @see WP_Embed
 */
class Ray_Comments_Embed extends WP_Embed {
	/**
	 * Embed handlers holder.
	 *
	 * @var array
	 */
	public $handlers = array();

	/**
	 * Comment ID.
	 *
	 * Specific to the {@link Ray_Comments_Embed} class.
	 *
	 * @var int
	 */
	protected $comment_id = 0;

	/**
	 * Static initializer.
	 *
	 * Specific to the {@link Ray_Comments_Embed} class.
	 */
	public static function init() {
		return new self();
	}

	/**
	 * Constructor
	 *
	 * @global WP_Embed $wp_embed
	 */
	public function __construct() {
		/*
		 * Make sure we populate the WP_Embed handlers array.
		 *
		 * These are providers that use a regex callback on the URL in question.
		 * Do not confuse with oEmbed providers, which require an external ping.
		 * Used in WP_Embed::shortcode().
		 */
		$this->handlers = $GLOBALS['wp_embed']->handlers;

		add_filter( 'get_comment_text', array( $this, 'get_comment_id' ), 1, 2 );
		add_filter( 'get_comment_text', array( $this, 'autoembed' ), 8 );
		add_filter( 'get_comment_text', array( $this, 'run_shortcode' ), 7 );
	}

	/**
	 * Get the comment ID, to be used for fetching oEmbed cache.
	 *
	 * @since 2.0.0
	 *
	 * @param string     $retval  Comment text
	 * @param WP_Comment $comment The comment object.
	 */
	public function get_comment_id( $retval, $comment ) {
		$this->comment_id = $comment->comment_ID;
		return $retval;
	}

	/**
	 * The do_shortcode() callback function.
	 *
	 * Overrides the parent {@link WP_Embed} class to be specific to comments and
	 * not posts.
	 *
	 * @param array $attr {
	 *     Shortcode attributes. Optional.
	 *
	 *     @type int $width  Width of the embed in pixels.
	 *     @type int $height Height of the embed in pixels.
	 * }
	 * @param string $url The URL attempting to be embedded.
	 * @return string|false The embed HTML on success, otherwise the original URL.
	 *                      `->maybe_make_link()` can return false on failure.
	 */
	public function shortcode( $attr, $url = '' ) {
		if ( empty( $url ) ) {
			return '';
		}

		$rawattr = $attr;
		$attr = wp_parse_args( $attr, wp_embed_defaults() );

		// Use kses to convert & into &amp; and we need to undo this
		// See https://core.trac.wordpress.org/ticket/11311.
		$url = str_replace( '&amp;', '&', $url );

		// Look for known internal handlers.
		ksort( $this->handlers );
		foreach ( $this->handlers as $priority => $handlers ) {
			foreach ( $handlers as $hid => $handler ) {
				if ( preg_match( $handler['regex'], $url, $matches ) && is_callable( $handler['callback'] ) ) {
					if ( false !== $return = call_user_func( $handler['callback'], $matches, $attr, $url, $rawattr ) ) {

						/** This filter is documented in /wp-includes/class-wp-embed.php */
						return apply_filters( 'embed_handler_html', $return, $url, $attr );
					}
				}
			}
		}

		$unfiltered_html   = current_user_can( 'unfiltered_html' );
		$default_discovery = false;

		// Since 4.4, WordPress is now an oEmbed provider.
		if ( function_exists( 'wp_oembed_register_route' ) ) {
			$unfiltered_html   = true;
			$default_discovery = true;
		}

		/** This filter is documented in /wp-includes/class-wp-embed.php */
		$attr['discover'] = ( apply_filters( 'embed_oembed_discover', $default_discovery ) && $unfiltered_html );

		// Set up a new WP oEmbed object to check URL with registered oEmbed providers.
		require_once( ABSPATH . WPINC . '/class-oembed.php' );
		$oembed_obj = _wp_oembed_get_object();

		// If oEmbed discovery is true, skip oEmbed provider check.
		$is_oembed_link = false;
		if ( ! $attr['discover'] ) {
			foreach ( (array) $oembed_obj->providers as $provider_matchmask => $provider ) {
				$regex = ( $is_regex = $provider[1] ) ? $provider_matchmask : '#' . str_replace( '___wildcard___', '(.+)', preg_quote( str_replace( '*', '___wildcard___', $provider_matchmask ), '#' ) ) . '#i';

				if ( preg_match( $regex, $url ) )
					$is_oembed_link = true;
			}

			// If url doesn't match a WP oEmbed provider, stop parsing.
			if ( ! $is_oembed_link )
				return $this->maybe_make_link( $url );
		}

		$id = $this->comment_id;

		if ( $id ) {
			// Setup the cachekey.
			$cachekey = '_oembed_' . md5( $url . serialize( $attr ) );

			$cache = get_comment_meta( $id, $cachekey, true );

			// Return cached oEmbed response.
			if ( ! empty( $cache ) ) {
				// Return URL for cached failure.
				if ( '{{unknown}}' === $cache ) {
					return $this->maybe_make_link( $url );
				}

				/** This filter is documented in /wp-includes/class-wp-embed.php */
				return apply_filters( 'embed_oembed_html', $cache, $url, $attr, 0 );

			// If no cache, ping the oEmbed provider and cache the result.
			} else {
				$html = wp_oembed_get( $url, $attr );

				// If there was a result, return it.
				if ( $html ) {
					update_comment_meta( $id, $cachekey, $html );

					/** This filter is documented in /wp-includes/class-wp-embed.php */
					return apply_filters( 'embed_oembed_html', $html, $url, $attr, 0 );
				} else {
					update_comment_meta( $id, $cachekey, '{{unknown}}' );
				}
			}
		}

		// Still unknown.
		return $this->maybe_make_link( $url );
	}
}
