<?php

class Short_Url_Test extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();
		$this->short_url = Short_Url::instance();
	}

	public function tearDown() {
		parent::tearDown();
		unset( $this->short_url );
	}

	// public function test_actions() {
		// $this->assertSame( 10, has_action( 'send_headers', $this->short_url ) );
	// }

	public function test_create_short_url() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$post_id = $this->factory->post->create();
		$_POST = [
			'post_type'            => 'post',
			'short_url_meta_nonce' => wp_create_nonce( 'short_url_save_data' ),
			'short_url_field'      => 'books'
		];

		$this->short_url->save_post( $post_id );
		$this->assertSame( 'books', $this->short_url->get_short_url( $post_id, true ) );
		$this->assertSame( home_url( 'books' ), $this->short_url->get_short_url( $post_id ) );

		wp_set_current_user( 0 );
	}
}
