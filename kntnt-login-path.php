<?php

/**
 * Plugin Name:       Kntnt Login Path
 * Plugin URI:        https://github.com/Kntnt/kntnt-login-path
 * Description:       Changes the login path and where users are redirected after login and logout. The login path is set by 'KNTNT_LOGIN_PATH' constant or 'kntnt-login-path' filter. After login, users are redirected based on 'KNTNT_LOGIN_REDIRECT_PATH' constant or 'kntnt-login-redirect-path' filter. After logout, users are redirected based on 'KNTNT_LOGOUT_REDIRECT_PATH' constant or 'kntnt-logout-redirect-path' filter.
 * Version:           1.0.0
 * Requires at least: 5.3
 * Requires PHP:      8.2
 * Author:            Thomas Barregren
 * Author URI:        https://www.kntnt.com/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */

declare( strict_types=1 );

namespace Kntnt\Login_Path;

defined( 'ABSPATH' ) && new Plugin;

class Plugin {

	/** @var string Name of constant for setting custom login path */
	private const KNTNT_LOGIN_PATH_CONSTANT = 'KNTNT_LOGIN_PATH';

	/** @var string Name of filter for setting custom login path */
	private const KNTNT_LOGIN_PATH_FILTER = 'kntnt-login-path';

	/** @var string Name of constant for setting login redirect path */
	private const KNTNT_LOGIN_REDIRECT_PATH_CONSTANT = 'KNTNT_LOGIN_REDIRECT_PATH';

	/** @var string Name of filter for setting login redirect path */
	private const KNTNT_LOGIN_REDIRECT_PATH_FILTER = 'kntnt-login-redirect-path';

	/** @var string Name of constant for setting logout redirect path */
	private const KNTNT_LOGOUT_REDIRECT_PATH_CONSTANT = 'KNTNT_LOGOUT_REDIRECT_PATH';

	/** @var string Name of filter for setting logout redirect path */
	private const KNTNT_LOGOUT_REDIRECT_PATH_FILTER = 'kntnt-logout-redirect-path';

	/** @var array<string> Paths that should return 404 when custom login is active */
	private const RESTRICTED_PAGES = [
			'login',
			'wp-login',
			'wp-login.php',
			'admin',
			'wp-admin',
			'wp-signup.php',
	];

	/** @var string|null Custom login path or null if not set */
	private ?string $login_path;

	/** @var string Current request URI */
	private string $request_path;

	/**
	 * Initializes the plugin if a custom login path is configured
	 */
	public function __construct() {
		$this->login_path = $this->get_login_path();
		if ( $this->login_path !== null ) {
			add_action( 'after_setup_theme', $this->initialize( ... ) );
		}
	}

	/**
	 * Sets up WordPress hooks and actions
	 */
	public function initialize(): void {

		$this->request_path = trim( $_SERVER['REQUEST_URI'], '/' );

		add_action( 'init', $this->handle_login_request( ... ), 2 );
		add_action( 'init', $this->prevent_default_login_access( ... ), 2 );
		add_filter( 'login_url', $this->get_custom_login_url( ... ) );
		add_filter( 'site_url', $this->modify_login_url( ... ), 10, 4 );
		add_filter( 'network_site_url', $this->modify_login_url( ... ), 10, 3 );
		add_filter( 'wp_redirect', $this->modify_login_redirect( ... ), 10, 2 );
		add_filter( 'login_redirect', $this->handle_login_redirect( ... ), 99, 3 );
		add_filter( 'logout_redirect', $this->handle_logout_redirect( ... ), 99, 3 );

	}

	/**
	 * Handles requests to the custom login path
	 */
	public function handle_login_request(): void {
		$current_path = strtok( $this->request_path, '?' ) ?: '';
		if ( $current_path === $this->login_path ) {
			global $pagenow;
			$pagenow = 'wp-login.php';
			require_once ABSPATH . 'wp-login.php';
			exit;
		}
	}

	/**
	 * Returns 404 for default login paths when custom login is active
	 */
	public function prevent_default_login_access(): void {
		if ( ! is_user_logged_in() ) {
			$current_path = strtok( $this->request_path, '?' ) ?: '';
			foreach ( self::RESTRICTED_PAGES as $page ) {
				if ( $current_path !== $this->login_path && str_starts_with( $current_path, $page ) ) {
					$this->return_404();
				}
			}
		}
	}

	/**
	 * Modifies default login URLs to use custom login path
	 *
	 * @param string      $url     URL to modify
	 * @param string      $path    Path component of URL
	 * @param string|null $scheme  URL scheme
	 * @param int|null    $blog_id Blog ID for multisite
	 *
	 * @return string Modified URL
	 */
	public function modify_login_url( string $url, string $path, ?string $scheme = null, ?int $blog_id = null ): string {
		if ( ! is_user_logged_in() && str_contains( $path, 'wp-login.php' ) ) {
			return home_url( $this->login_path, $scheme );
		}
		return $url;
	}

	/**
	 * Returns the custom login URL
	 *
	 * @return string Custom login URL
	 */
	public function get_custom_login_url(): string {
		return home_url( $this->login_path );
	}

	/**
	 * Modifies redirect URLs to use custom login path
	 *
	 * @param string $location Redirect URL
	 * @param int    $status   HTTP status code
	 *
	 * @return string Modified redirect URL
	 */
	public function modify_login_redirect( string $location, int $status ): string {
		return str_replace( 'wp-login.php', $this->login_path, $location );
	}

	/**
	 * Handles redirect after successful login
	 *
	 * @param string             $redirect_to           Default redirect URL
	 * @param string             $requested_redirect_to Requested redirect URL
	 * @param \WP_User|\WP_Error $user                  User who logged in or error
	 *
	 * @return string Final redirect URL
	 */
	public function handle_login_redirect( string $redirect_to, string $requested_redirect_to, \WP_User|\WP_Error $user ): string {
		if ( $user instanceof \WP_Error ) {
			return $redirect_to;
		}
		if ( $requested_redirect_to && $requested_redirect_to !== admin_url() ) {
			return $requested_redirect_to;
		}
		return $this->get_login_redirect_path();
	}

	/**
	 * Handles redirect after logout
	 *
	 * @param string             $redirect_to           Default redirect URL
	 * @param string             $requested_redirect_to Requested redirect URL
	 * @param \WP_User|\WP_Error $user                  User who logged out or error
	 *
	 * @return string Final redirect URL
	 */
	public function handle_logout_redirect( string $redirect_to, string $requested_redirect_to, \WP_User|\WP_Error $user ): string {
		if ( $requested_redirect_to && $requested_redirect_to !== home_url() && ! str_contains( $requested_redirect_to, $this->login_path ) ) {
			return $requested_redirect_to;
		}
		if ( defined( self::KNTNT_LOGOUT_REDIRECT_PATH_CONSTANT ) ) {
			$custom_path = $this->get_logout_redirect_path();
			if ( empty( $custom_path ) ) {
				return add_query_arg( [ 'loggedout' => 'true' ], wp_login_url() );
			}
			return home_url( $custom_path );
		}
		return home_url();
	}

	// Private methods ordered by when they're called by public methods

	/**
	 * Gets the configured login path from constant or filter
	 *
	 * @return string|null Login path or null if not configured
	 */
	private function get_login_path(): ?string {
		if ( defined( self::KNTNT_LOGIN_PATH_CONSTANT ) && ( $path = trim( constant( self::KNTNT_LOGIN_PATH_CONSTANT ), '/' ) ) ) {
			return $path;
		}
		if ( $path = trim( apply_filters( self::KNTNT_LOGIN_PATH_FILTER, '' ), '/' ) ) {
			return $path;
		}
		return null;
	}

	/**
	 * Gets the configured login redirect path
	 *
	 * @return string Path to redirect to after login
	 */
	private function get_login_redirect_path(): string {
		if ( defined( self::KNTNT_LOGIN_REDIRECT_PATH_CONSTANT ) && ( $path = trim( constant( self::KNTNT_LOGIN_REDIRECT_PATH_CONSTANT ), '/' ) ) ) {
			return $path;
		}
		if ( $path = trim( apply_filters( self::KNTNT_LOGIN_REDIRECT_PATH_FILTER, '' ), '/' ) ) {
			return $path;
		}
		return 'wp-admin';
	}

	/**
	 * Gets the configured logout redirect path
	 *
	 * @return string|null Path to redirect to after logout or null if not set
	 */
	private function get_logout_redirect_path(): ?string {
		if ( defined( self::KNTNT_LOGOUT_REDIRECT_PATH_CONSTANT ) && ( $path = trim( constant( self::KNTNT_LOGOUT_REDIRECT_PATH_CONSTANT ), '/' ) ) ) {
			return $path;
		}
		if ( $path = trim( apply_filters( self::KNTNT_LOGOUT_REDIRECT_PATH_FILTER, '' ), '/' ) ) {
			return $path;
		}
		return null;
	}

	/**
	 * Returns a 404 response and exits
	 */
	private function return_404(): never {
		status_header( 404 );
		global $wp_query;
		( $wp_query ??= new \WP_Query() )->set_404();
		require_once get_404_template();
		exit;
	}

}