<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * Other plugins' notices stay off the MonoRanks screens, and only there. Right before WordPress prints its notice hooks,
 * every callback that does not come from WordPress itself or this plugin is taken off them for this one request, so core's
 * update, recovery-mode and password warnings keep showing. Notices printed outside those hooks or added by a script are
 * hidden by the stylesheet: the output of the hooks is tagged here, and the app only moves tagged ones into the page.
 */
class Notices {

	const HOOKS = array( 'network_admin_notices', 'user_admin_notices', 'admin_notices', 'all_admin_notices' );

	public static function register() {
		add_action( 'in_admin_header', array( __CLASS__, 'clean' ), PHP_INT_MAX );
	}

	/** Runs on every admin screen and does nothing outside the plugin's own two. */
	public static function clean() {
		if ( ! Admin::is_own_screen() ) {
			return;
		}
		global $wp_filter;
		foreach ( self::HOOKS as $hook ) {
			if ( ! isset( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( ! self::allowed( $callback['function'] ) ) {
						remove_action( $hook, $callback['function'], $priority );
					}
				}
			}
		}
		add_action( 'admin_notices', array( __CLASS__, 'open' ), PHP_INT_MIN );
		add_action( 'all_admin_notices', array( __CLASS__, 'close' ), PHP_INT_MAX );
	}

	/** True for a callback whose code lives in WordPress itself or in this plugin. */
	public static function allowed( $callback ) {
		$file = self::source_file( $callback );
		if ( '' === $file ) {
			return false;
		}
		$file = wp_normalize_path( $file );
		foreach ( array( ABSPATH . 'wp-admin/', ABSPATH . 'wp-includes/', dirname( MONORANKS_CONNECTOR_FILE ) . '/' ) as $dir ) {
			$real = realpath( $dir );
			if ( 0 === strpos( $file, wp_normalize_path( ( $real ? $real : rtrim( $dir, '/' ) ) . '/' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** The file a callback is written in, '' when that cannot be told. */
	private static function source_file( $callback ) {
		try {
			if ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$callback = explode( '::', $callback, 2 );
			}
			if ( is_array( $callback ) ) {
				$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( is_object( $callback ) && ! $callback instanceof \Closure ) {
				$reflection = new \ReflectionMethod( $callback, '__invoke' );
			} else {
				$reflection = new \ReflectionFunction( $callback );
			}
			return (string) $reflection->getFileName();
		} catch ( \ReflectionException $e ) {
			return '';
		}
	}

	/** Marks where the notice hooks start printing. */
	public static function open() {
		echo '<span id="monoranks-notices-from" hidden></span>';
	}

	/** Tags what the hooks printed, before WordPress's own script moves notices around. */
	public static function close() {
		wp_print_inline_script_tag( '(function(){var a=document.getElementById("monoranks-notices-from");if(!a){return;}var e=a.nextElementSibling;while(e&&e!==document.currentScript){e.setAttribute("data-mr-core","1");e=e.nextElementSibling;}a.remove();})();' );
	}
}
