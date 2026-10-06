<?php
/**
 * API key generation, hashing, and metadata persistence.
 *
 * @package DYDAPS_API_Protection
 */

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates secret lifecycle management.
 *
 * Only the hash is stored. The plain key is returned to the admin controller
 * exactly once at generation or rotation time so the UI can present it
 * transiently without persisting recoverable credentials.
 */
final class Dydaps_Api_Key_Service {
	/**
	 * Hash option name.
	 *
	 * @var string
	 */
	private $hash_option_name;

	/**
	 * Creation timestamp option name.
	 *
	 * @var string
	 */
	private $created_at_option_name;

	/**
	 * Rotation timestamp option name.
	 *
	 * @var string
	 */
	private $last_rotated_at_option_name;

	/**
	 * Header name.
	 *
	 * @var string
	 */
	private $header_name;

	/**
	 * Constructor.
	 *
	 * @param string $hash_option_name            Option used for the key hash.
	 * @param string $created_at_option_name      Option used for the created timestamp.
	 * @param string $last_rotated_at_option_name Option used for the rotation timestamp.
	 * @param string $header_name                 Header name expected by the API.
	 */
	public function __construct( $hash_option_name, $created_at_option_name, $last_rotated_at_option_name, $header_name ) {
		$this->hash_option_name            = $hash_option_name;
		$this->created_at_option_name      = $created_at_option_name;
		$this->last_rotated_at_option_name = $last_rotated_at_option_name;
		$this->header_name                 = $header_name;
	}

	/**
	 * Create the first key.
	 *
	 * @return string Plain key shown once to the administrator.
	 */
	public function create_key() {
		$plain_key = $this->generate_secret();

		$this->persist_hash( $plain_key );
		Dydaps_Api_Option_Store::set( $this->created_at_option_name, (string) time() );
		Dydaps_Api_Option_Store::set( $this->last_rotated_at_option_name, '' );

		return $plain_key;
	}

	/**
	 * Rotate the current key.
	 *
	 * @return string Plain key shown once to the administrator.
	 */
	public function rotate_key() {
		$plain_key = $this->generate_secret();

		$this->persist_hash( $plain_key );

		if ( ! $this->has_key_metadata() ) {
			Dydaps_Api_Option_Store::set( $this->created_at_option_name, (string) time() );
		}

		Dydaps_Api_Option_Store::set( $this->last_rotated_at_option_name, (string) time() );

		return $plain_key;
	}

	/**
	 * Revoke the current key and delete metadata.
	 *
	 * @return void
	 */
	public function revoke_key() {
		Dydaps_Api_Option_Store::delete_many(
			[
				$this->hash_option_name,
				$this->created_at_option_name,
				$this->last_rotated_at_option_name,
			]
		);
	}

	/**
	 * Determine whether a key exists.
	 *
	 * @return bool
	 */
	public function has_key() {
		$stored_hash = get_option( $this->hash_option_name, '' );

		return is_string( $stored_hash ) && '' !== $stored_hash;
	}

	/**
	 * Verify a provided key against the stored hash.
	 *
	 * The hash is deterministic and compared with hash_equals() to avoid timing
	 * leaks in string comparison.
	 *
	 * @param string $plain_key Candidate key from the request header.
	 * @return bool
	 */
	public function verify_key( $plain_key ) {
		if ( '' === $plain_key ) {
			return false;
		}

		$stored_hash = get_option( $this->hash_option_name, '' );

		if ( ! is_string( $stored_hash ) || '' === $stored_hash ) {
			return false;
		}

		return hash_equals( $stored_hash, $this->hash_key( $plain_key ) );
	}

	/**
	 * Get the custom header name.
	 *
	 * @return string
	 */
	public function get_header_name() {
		return $this->header_name;
	}

	/**
	 * Get a masked preview for the admin UI.
	 *
	 * The plugin cannot recover the secret by design, so the preview is a fixed
	 * placeholder rather than a partial reveal.
	 *
	 * @return string
	 */
	public function get_masked_preview() {
		return str_repeat( '*', Dydaps_Api_Config::MINIMUM_API_KEY_LENGTH );
	}

	/**
	 * Get the stored creation timestamp.
	 *
	 * @return int|null
	 */
	public function get_created_at() {
		return $this->normalize_timestamp( get_option( $this->created_at_option_name, '' ) );
	}

	/**
	 * Get the stored rotation timestamp.
	 *
	 * @return int|null
	 */
	public function get_last_rotated_at() {
		return $this->normalize_timestamp( get_option( $this->last_rotated_at_option_name, '' ) );
	}

	/**
	 * Generate a cryptographically secure API key.
	 *
	 * The key is intentionally restricted to hexadecimal characters so it stays
	 * easy to copy into HTTP headers, curl commands, CI variables, and reverse
	 * proxies without escaping issues caused by special characters.
	 *
	 * @return string
	 */
	private function generate_secret() {
		try {
			$secret = bin2hex( random_bytes( Dydaps_Api_Config::MINIMUM_API_KEY_LENGTH / 2 ) );
		} catch ( Exception $exception ) {
			$secret = wp_generate_password( Dydaps_Api_Config::MINIMUM_API_KEY_LENGTH, false, false );
		}

		if ( strlen( $secret ) < Dydaps_Api_Config::MINIMUM_API_KEY_LENGTH ) {
			$secret = str_pad( $secret, Dydaps_Api_Config::MINIMUM_API_KEY_LENGTH, 'a' );
		}

		return $secret;
	}

	/**
	 * Persist the deterministic hash representation of the plain key.
	 *
	 * @param string $plain_key Plain key to hash.
	 * @return void
	 */
	private function persist_hash( $plain_key ) {
		Dydaps_Api_Option_Store::set( $this->hash_option_name, $this->hash_key( $plain_key ) );
	}

	/**
	 * Hash the key before storage.
	 *
	 * @param string $plain_key Plain key to hash.
	 * @return string
	 */
	private function hash_key( $plain_key ) {
		return hash( 'sha256', $plain_key );
	}

	/**
	 * Determine whether the created timestamp already exists.
	 *
	 * @return bool
	 */
	private function has_key_metadata() {
		return null !== $this->get_created_at();
	}

	/**
	 * Normalize stored timestamps.
	 *
	 * @param mixed $value Raw option value.
	 * @return int|null
	 */
	private function normalize_timestamp( $value ) {
		if ( ! is_scalar( $value ) || '' === (string) $value ) {
			return null;
		}

		$timestamp = absint( $value );

		return $timestamp > 0 ? $timestamp : null;
	}
}
