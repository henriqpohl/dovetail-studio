<?php
/**
 * Template loader for the Studio and its modules.
 *
 * @package DovetailStudio\Core
 */

namespace DovetailStudio\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the PHP templates kept in a `views/` folder.
 *
 * Classes prepare the data and check permissions; templates only print it,
 * escaping on output. A template reads its values from `$args` and is included
 * inside a function, so it never sees or changes the caller's variables:
 *
 *     View::render( dirname( __DIR__ ), 'tab-settings', array( 'settings' => $settings ) );
 *
 * prints `modules/<slug>/views/tab-settings.php`. Partials go in subfolders,
 * for example `partials/checkbox-row`.
 */
final class View {
	/**
	 * Print a template.
	 *
	 * @param string              $dir  Folder that holds `views/`: a module folder, or `src/Core` for Studio screens.
	 * @param string              $name Template path inside `views/`, without `.php`: lowercase letters, numbers, hyphens and slashes.
	 * @param array<string,mixed> $args Values the template reads.
	 * @return void
	 */
	public static function render( string $dir, string $name, array $args = array() ): void {
		$file = self::path( $dir, $name );

		if ( '' === $file ) {
			_doing_it_wrong( __METHOD__, esc_html( sprintf( 'Template "%s" not found in %s/views.', $name, $dir ) ), '2.6.0' );

			return;
		}

		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $args is read by the included template.
		( static function ( string $dovetail_view_file, array $args ): void {
			include $dovetail_view_file;
		} )( $file, $args );
	}

	/**
	 * A template's output as a string.
	 *
	 * @param string              $dir  Folder that holds `views/`.
	 * @param string              $name Template path inside `views/`, without `.php`.
	 * @param array<string,mixed> $args Values the template reads.
	 * @return string
	 */
	public static function capture( string $dir, string $name, array $args = array() ): string {
		ob_start();

		try {
			self::render( $dir, $name, $args );
		} finally {
			$output = (string) ob_get_clean();
		}

		return $output;
	}

	/**
	 * Full path of a template, or '' when the name is not valid or the file does not exist.
	 *
	 * @param string $dir  Folder that holds `views/`.
	 * @param string $name Template path inside `views/`, without `.php`.
	 * @return string
	 */
	private static function path( string $dir, string $name ): string {
		// Only plain names, so a value can never point outside views/.
		if ( ! preg_match( '#^[a-z0-9-]+(?:/[a-z0-9-]+)*$#', $name ) ) {
			return '';
		}

		$file = trailingslashit( $dir ) . 'views/' . $name . '.php';

		return is_readable( $file ) ? $file : '';
	}
}
