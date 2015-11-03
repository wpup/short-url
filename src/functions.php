<?php

/**
 * Get short url from a post.
 *
 * @param int $post_id
 * @param bool $only_short_url Default false
 *
 * @since 2.0.0
 *
 * @return string|null
 */

function get_short_url( $post_id, $only_short_url ) {
	$short_url = short_url();

	return $short_url->get_short_url( $post_id, $only_short_url );
}
