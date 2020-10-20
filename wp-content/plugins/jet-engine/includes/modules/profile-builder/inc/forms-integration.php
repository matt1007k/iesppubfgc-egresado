<?php
namespace Jet_Engine\Modules\Profile_Builder;

class Forms_Integration {

	/**
	 * Constructor for the class
	 */
	public function __construct() {
		add_filter( 'jet-engine/forms/insert-post/pre-check', array( $this, 'check_posts_limit' ), 10, 4 );
	}

	/**
	 * Check posts limit
	 */
	public function check_posts_limit( $res, $postarr, $args, $notifications ) {

		// Apply restirictions only for post inserting, not the update
		if ( ! empty( $postarr['ID'] ) ) {
			return $res;
		}

		$restrictions = Module::instance()->settings->get( 'posts_restrictions' );

		if ( empty( $restrictions ) ){
			return $res;
		}

		$found_restriction = false;

		if ( ! is_user_logged_in() ) {
			$role = 'jet-engine-guest';
		} else {
			$user  = wp_get_current_user();
			$roles = $user->roles;
			$role  = $roles[0];
		}

		foreach ( $restrictions as $restriction ) {
			if ( ! empty( $restriction['role'] ) && in_array( $role, $restriction['role'] ) ) {
				$found_restriction = $restriction;
			}
		}

		$limit = ! empty( $found_restriction['limit'] ) ? absint( $found_restriction['limit'] ) : 0;

		if ( ! $limit ) {
			return $res;
		}

		$user_posts = $this->get_user_posts( $user->ID );

		if ( $user_posts >= $limit ) {
			$message = ! empty( $found_restriction['error_message'] ) ? $found_restriction['error_message'] : __( 'Posts limit reached', 'jet-engine' );
			$notifications->set_specific_status( $message );
			$res = false;
		}

		return $res;

	}

	/**
	 * Get user posts count
	 */
	public function get_user_posts( $user_id ) {

		global $wpdb;

		$posts = $wpdb->posts;
		$user_id = absint( $user_id );

		$posts_num = $wpdb->get_var( "SELECT COUNT(*) FROM $posts WHERE post_author = $user_id AND post_status IN ( 'draft', 'publish', 'trash' );" );

		return absint( $posts_num );

	}

}
