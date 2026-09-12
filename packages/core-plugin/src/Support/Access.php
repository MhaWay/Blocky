<?php
/**
 * Unified authorization check: cookie capability OR scoped API key.
 *
 * @package Blocky\Core\Support
 */

declare( strict_types=1 );

namespace Blocky\Core\Support;

/**
 * Permission helper shared by every Blocky REST route.
 */
final class Access {

	/**
	 * Whether the request may act: logged-in capability or key scope.
	 *
	 * @param string $scope      API-key scope consulted for key auth.
	 * @param string $capability WP capability consulted for cookie auth.
	 * @return bool Authorization state.
	 */
	public static function allowed( string $scope, string $capability ): bool {
		if ( current_user_can( $capability ) ) {
			return true;
		}

		$row = ApiAuth::current_key_row();
		if ( null === $row ) {
			return false;
		}

		$scopes = explode( ',', (string) ( $row['scopes'] ?? '' ) );
		return in_array( $scope, $scopes, true );
	}

	/**
	 * Post-scoped variant of allowed().
	 *
	 * @param string $scope      API-key scope.
	 * @param string $capability Meta capability (edit_post/delete_post).
	 * @param int    $post_id    Target post id.
	 * @return bool Authorization state.
	 */
	public static function allowed_post( string $scope, string $capability, int $post_id ): bool {
		if ( current_user_can( $capability, $post_id ) ) {
			return true;
		}

		return self::allowed( $scope, '' );
	}
}
