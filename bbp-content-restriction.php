<?php

/**
 * Check if forum is marked "restricted".
 *
 * @since  2.0.0
 *
 * @param  integer      $forum_id Forum post ID
 * @return integer|bool           Product ID if access is restricted, otherwise false
 */
function themo_wcps_is_forum_restricted( $forum_id = 0 ) {

	// Look for a connected product
	$product_id = absint( get_post_meta( $forum_id, '_themo_wcps_connected_product', true ) );

	// See if product is set to restrict access
	if ( $product_id && $restricted = get_post_meta( $product_id, '_product_limit_access', true ) )
		return $product_id;

	// If not, return false
	return false;
}

/**
 * Determine if user owns a given product.
 *
 * @since  2.0.0
 *
 * @param  integer $user_id    User ID
 * @param  integer $product_id Product post ID
 * @return bool                True if user owns product, otherwise false
 */
function themo_wcps_user_has_product( $user_id = 0, $product_id = 0 ) {

	// If we're using EDD
	if ( class_exists( 'EDD' ) )
		return edd_has_user_purchased( $user_id, $product_id );

	// Or if we're using WooCommerce
	elseif ( class_exists( 'WooCommerce' ) ) {
		add_filter( 'woocommerce_reports_order_statuses', 'themo_wcps_user_has_product_status_filter', 20, 1 );

		$has_product = woocommerce_customer_bought_product( null, $user_id, $product_id );

		remove_filter( 'woocommerce_reports_order_statuses', 'themo_wcps_user_has_product_status_filter' );

		return $has_product;
	}

	// Otherwise, we don't care
	else
		return false;

}

/**
 * Filter which statuses are allowed to determine if the current customer has valid products (order=processing,completed)
 *
 * @param $status_list
 *
 * @return array
 */
function themo_wcps_user_has_product_status_filter( $status_list ) {

	// Force 'processing', 'completed' status, don't allow others
	$status_list = array(
		'processing',
		//'on-hold',
		'completed'
	);

	return $status_list;

}

/**
 * Hide restricted forum topics
 *
 * @since  2.0.0
 *
 * @param  array $query Topic query
 * @return array        Potentially modified query
 */
function themo_wcps_filter_bbp_topics_list( $query ) {

	global $user_ID;

	if ( current_user_can( 'manage_options' ) )
		return $query;

	if ( bbp_is_single_forum() ) {

		$restricted = themo_wcps_is_forum_restricted( bbp_get_forum_id() );

		// If this forum is restricted and the user is not logged in nor a product owner
		if ( $restricted && ( ! is_user_logged_in() || ! themo_wcps_user_has_product( $user_ID, $restricted ) ) ) {
			return array(); // return an empty query
		}
	}

	return $query;
}
add_filter( 'bbp_has_topics_query', 'themo_wcps_filter_bbp_topics_list' );

/**
 * Hide topic reply content.
 *
 * @since  2.0.0
 *
 * @param  string  $content  Reply content
 * @param  integer $reply_id Reply post ID
 * @return string            Potentially modified reply content
 */
function themo_wcps_filter_replies( $content, $reply_id ) {
	global $user_ID, $post;

	if ( current_user_can( 'manage_options' ) )
		return $content;

	$restricted_to = themo_wcps_is_forum_restricted( bbp_get_topic_id() );

	$restricted_id = bbp_get_topic_id();

	if ( ! $restricted_to ) {
		$restricted_to = themo_wcps_is_forum_restricted( bbp_get_forum_id() ); // check for parent forum restriction
		$restricted_id = bbp_get_forum_id();
	}

	if ( $restricted_to && ! themo_wcps_user_has_product( $user_ID, $restricted_to ) ) {

		$return = '<div class="wds_wcps_message">' . sprintf(
			__( 'This content is restricted to owners of %s.', 'wcps' ),
			'<a href="' . get_permalink( $restricted_to ) . '">' . get_the_title( $restricted_to ) . '</a>'
		) . '</div>';

		return $return;

	}

	return $content; // not restricted
}
add_filter( 'bbp_get_reply_content', 'themo_wcps_filter_replies', 2, 999 );

/**
 * Hide "new topic" form.
 *
 * @since  2.0.0
 *
 * @param  bool $can_access User's current topic access
 * @return bool             User's modified topic access
 */
function themo_wcps_hide_new_topic_form( $can_access ) {
	global $user_ID;

	if ( current_user_can( 'manage_options' ) )
		return $can_access;

	$restricted_to = themo_wcps_is_forum_restricted( bbp_get_forum_id() ); // check for parent forum restriction
	$restricted_id = bbp_get_forum_id();

	if ( $restricted_to && ! themo_wcps_user_has_product( $user_ID, $restricted_to ) ) {
		$can_access = false;
	}
	return $can_access;
}
add_filter( 'bbp_current_user_can_access_create_topic_form', 'themo_wcps_hide_new_topic_form' );

/**
 * Hide "new reply" form
 *
 * @since  2.0.0
 *
 * @param  bool $can_access User's current reply access
 * @return bool             User's modified reply access
 */
function themo_wcps_hide_new_replies_form( $can_access ) {
	global $user_ID;

	if ( current_user_can( 'manage_options' ) )
		return $can_access;

	$restricted_to = themo_wcps_is_forum_restricted( bbp_get_topic_id() );

	$restricted_id = bbp_get_topic_id();

	if ( ! $restricted_to ) {
		$restricted_to = themo_wcps_is_forum_restricted( bbp_get_forum_id() ); // check for parent forum restriction
		$restricted_id = bbp_get_forum_id();
	}

	if ( $restricted_to && ! themo_wcps_user_has_product( $user_ID, $restricted_to ) ) {
		$can_access = false;
	}
	return $can_access;
}
add_filter( 'bbp_current_user_can_access_create_reply_form', 'themo_wcps_hide_new_replies_form' );
add_filter( 'bbp_current_user_can_access_create_topic_form', 'themo_wcps_hide_new_replies_form' );

/**
 * Apply custom feedback messages on page load.
 *
 * @since  2.0.0
 */
function themo_wcps_apply_feedback_messages() {
	global $user_ID;

	if ( bbp_is_single_topic() ) {
		add_filter( 'gettext', 'themo_wcps_topic_feedback_messages', 20, 3 );
	} else if ( bbp_is_single_forum() && themo_wcps_is_forum_restricted( bbp_get_forum_id() ) ) {
			add_filter( 'gettext', 'themo_wcps_forum_feedback_messages', 20, 3 );
		}
}
add_action( 'template_redirect', 'themo_wcps_apply_feedback_messages' );

/**
 * Generate custom feedback messages for restricted topics
 *
 * @since  2.0.0
 *
 * @param  string $translated_text Translated content
 * @param  string $text            Original content
 * @param  string $domain          Textdomain
 * @return string                  Updated content
 */
function themo_wcps_topic_feedback_messages( $translated_text, $text, $domain ) {

	switch ( $text ) {
		case 'You cannot reply to this topic.':
			$translated_text = __( 'Topic creation is restricted to product owners.', 'wcps' );
			break;
	}
	return $translated_text;
}

/**
 * Generate custom feedback messages for restricted forums
 *
 * @since  2.0.0
 *
 * @param  string $translated_text Translated content
 * @param  string $text            Original content
 * @param  string $domain          Textdomain
 * @return string                  Updated content
 */
function themo_wcps_forum_feedback_messages( $translated_text, $text, $domain ) {

	switch ( $text ) {
		case 'Oh bother! No topics were found here!':
			$translated_text = __( 'This forum is restricted to product owners.', 'wcps' );
			break;
		case 'You cannot create new topics at this time.':
			$translated_text = __( 'Only product owners can create topics.', 'wcps' );
			break;
	}
	return $translated_text;
}
