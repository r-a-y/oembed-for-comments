<?php
/**
 * Unit Tests for oEmbed for Comments.
 *
 * @package OEmbed_For_Comments
 */

/**
 * oEmbed unit tests for WordPress comments.
 */
class OEmbed_For_Comments_Test extends WP_UnitTestCase {
	protected static $user_id;
	protected static $post_id;
	protected static $ping = false;

	public function setUp() {
		parent::setUp();
	}

	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create( array(
			'role'       => 'author',
			'user_login' => 'test_wp_user_get',
			'user_pass'  => 'password',
			'user_email' => 'test@test.com',
		) );

		self::$post_id = $factory->post->create( array(
			'post_author' => self::$user_id
		) );

		static::$ping = false;
	}

	/**
	 * Test YouTube oEmbed.
	 *
	 * @group youtube
	 */
	public function test_youtube() {
		$data = array(
			'comment_post_ID' => self::$post_id,
			'comment_author' => 'Comment Author',
			'comment_author_url' => '',
			'comment_author_email' => '',
			'comment_type' => '',
			'comment_content' => 'Test

https://www.youtube.com/watch?v=fsmXRcD_jYI

Ha
',
			'comment_date' => '2011-01-01 10:00:00',
			'comment_date_gmt' => '2011-01-01 10:00:00',
		);

		// Add our comment.
		$id = wp_new_comment( $data );

		// Assert that YouTube link was converted to iframe.
		add_filter( 'oembed_result', array( $this, 'check_oembed_ping' ) );
			$this->assertContains( '<iframe', get_comment_text( $id ) );

			// oEmbed was used.
			$this->assertTrue( self::$ping );
		remove_filter( 'oembed_result', array( $this, 'check_oembed_ping' ) );

		// Check cache.
		$meta  = get_comment_meta( $id );
		$cache = current( current( $meta ) );
		$this->assertContains( '<iframe', $cache );
		$this->assertTrue( 0 === strpos( key( $meta ), '_oembed_' ) );

		// Grab comment again and see if oEmbed fires or not.
		self::$ping = false;
		add_filter( 'oembed_result', array( $this, 'check_oembed_ping' ) );
			$this->assertContains( '<iframe', get_comment_text( $id ) );
		remove_filter( 'oembed_result', array( $this, 'check_oembed_ping' ) );
		$this->assertFalse( self::$ping );
	}

	/**
	 * Test an oEmbed provider that is not part of a default WP install.
	 *
	 * @group discovery
	 * @requires function wp_oembed_register_route
	 */
	public function test_oembed_discovery() {
		$data = array(
			'comment_post_ID' => self::$post_id,
			'comment_author' => 'Comment Author',
			'comment_author_url' => '',
			'comment_author_email' => '',
			'comment_type' => '',
			'comment_content' => 'Test

https://hwdsb.tv/media/connect-google-drive-and-the-commons-to-streamline-sharing/

Ha
',
			'comment_date' => '2011-01-01 10:00:00',
			'comment_date_gmt' => '2011-01-01 10:00:00',
		);

		// Add our comment.
		$id = wp_new_comment( $data );

		// Assert that our oEmbed link was converted to iframe.
		add_filter( 'oembed_result', array( $this, 'check_oembed_ping' ) );
			$this->assertContains( '<iframe', get_comment_text( $id ) );

			// oEmbed was used.
			$this->assertTrue( self::$ping );
		remove_filter( 'oembed_result', array( $this, 'check_oembed_ping' ) );

		// Check cache.
		$meta  = get_comment_meta( $id );
		$cache = current( current( $meta ) );
		$this->assertContains( '<iframe', $cache );
		$this->assertTrue( 0 === strpos( key( $meta ), '_oembed_' ) );

		// Grab comment again and see if oEmbed fires or not.
		self::$ping = false;
		add_filter( 'oembed_result', array( $this, 'check_oembed_ping' ) );
			$this->assertContains( '<iframe', get_comment_text( $id ) );
		remove_filter( 'oembed_result', array( $this, 'check_oembed_ping' ) );
		$this->assertFalse( self::$ping );
	}

	/**
	 * Helper method to determine if we used oEmbed to ping a provider.
	 */
	public function check_oembed_ping( $retval ) {
		self::$ping = true;
		return $retval;
	}
}
