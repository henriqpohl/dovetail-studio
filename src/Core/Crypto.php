<?php
/**
 * Shared secret encryption for values stored in the database.
 *
 * @package DovetailStudio\Core
 */

namespace DovetailStudio\Core;

defined( 'ABSPATH' ) || exit;

/**
 * AES-256-GCM encryption keyed from the WordPress security salts.
 *
 * Every caller passes a context string. The context is part of the key and of
 * the authenticated data, so a value encrypted for one module cannot be
 * decrypted as another module's secret.
 *
 * Changing the AUTH_KEY/SECURE_AUTH_KEY salts makes saved values unreadable;
 * decrypt() then returns an empty string and the secret must be saved again.
 */
final class Crypto {

	/** Prefix of every encrypted value. */
	const PREFIX = 'dovetail_enc_v1:';

	/**
	 * Whether a stored value is already encrypted.
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	public static function is_encrypted( $value ): bool {
		return is_string( $value ) && 0 === strpos( $value, self::PREFIX );
	}

	/**
	 * Encrypts a secret.
	 *
	 * @param string $plain_text Secret.
	 * @param string $context    Caller context, e.g. the module slug.
	 * @return string|\WP_Error Encrypted value.
	 */
	public static function encrypt( string $plain_text, string $context ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return new \WP_Error(
				'dovetail_crypto_openssl_missing',
				__( 'The secret could not be saved because the OpenSSL extension is unavailable.', 'dovetail-studio' )
			);
		}

		try {
			$iv = random_bytes( 12 );
		} catch ( \Exception $exception ) {
			return new \WP_Error(
				'dovetail_crypto_random_failed',
				__( 'The secret could not be encrypted. Please contact the website administrator.', 'dovetail-studio' )
			);
		}

		$tag        = '';
		$ciphertext = openssl_encrypt(
			$plain_text,
			'aes-256-gcm',
			self::key( $context ),
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			$context
		);

		if ( false === $ciphertext ) {
			return new \WP_Error(
				'dovetail_crypto_failed',
				__( 'The secret could not be encrypted. Please contact the website administrator.', 'dovetail-studio' )
			);
		}

		return self::PREFIX . base64_encode( $iv . $tag . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary payload encoding.
	}

	/**
	 * Decrypts a stored secret. Returns an empty string when it cannot be read.
	 *
	 * @param mixed  $stored_value Encrypted value.
	 * @param string $context      Context used when encrypting.
	 * @return string
	 */
	public static function decrypt( $stored_value, string $context ): string {
		if ( ! self::is_encrypted( $stored_value ) || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$payload = base64_decode( substr( $stored_value, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary payload decoding.

		if ( false === $payload || strlen( $payload ) < 29 ) {
			return '';
		}

		$plain_text = openssl_decrypt(
			substr( $payload, 28 ),
			'aes-256-gcm',
			self::key( $context ),
			OPENSSL_RAW_DATA,
			substr( $payload, 0, 12 ),
			substr( $payload, 12, 16 ),
			$context
		);

		return false === $plain_text ? '' : $plain_text;
	}

	/**
	 * Binary key for a context.
	 *
	 * @param string $context Caller context.
	 * @return string
	 */
	private static function key( string $context ): string {
		return hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ) . '|' . $context . '|v1', true );
	}
}
