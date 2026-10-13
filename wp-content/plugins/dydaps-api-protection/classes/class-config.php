<?php
/**
 * Plugin configuration constants and defaults.
 *
 * @package DYDAPS_API_Protection
 */

defined( 'ABSPATH' ) || exit;

/**
 * Centralized configuration for the plugin.
 *
 * Defines constants, option names, and default values in a single location
 * to avoid duplication and ensure consistency.
 */
final class Dydaps_Api_Config {
	/**
	 * Plugin text domain for translations.
	 *
	 * @var string
	 */
	public const TEXT_DOMAIN = 'dydaps-api-protection';

	/**
	 * Custom HTTP header name for API key transmission.
	 *
	 * @var string
	 */
	public const HEADER_NAME = 'X-DYDAPS-API-Key';

	/**
	 * Default protected route prefixes (WordPress core routes).
	 *
	 * These can be overridden by the user via admin settings.
	 *
	 * @var string[]
	 */
	public const DEFAULT_PROTECTED_ROUTE_PREFIXES = [
		'/wp/v2/posts',
		'/wp/v2/pages',
		'/wp/v2/media',
		'/wp/v2/categories',
		'/wp/v2/tags',
	];

	/**
	 * Option name: API key hash storage.
	 *
	 * @var string
	 */
	public const OPTION_API_KEY_HASH = 'dydaps_api_key_hash';

	/**
	 * Option name: API key creation timestamp.
	 *
	 * @var string
	 */
	public const OPTION_API_KEY_CREATED_AT = 'dydaps_api_key_created_at';

	/**
	 * Option name: API key last rotation timestamp.
	 *
	 * @var string
	 */
	public const OPTION_API_KEY_LAST_ROTATED_AT = 'dydaps_api_key_last_rotated_at';

	/**
	 * Option name: Custom protected route prefixes.
	 *
	 * @var string
	 */
	public const OPTION_PROTECTED_ROUTE_PREFIXES = 'dydaps_api_protected_route_prefixes';

	/**
	 * Option name: Full REST API blocking toggle.
	 *
	 * @var string
	 */
	public const OPTION_BLOCK_ALL_REST_API = 'dydaps_api_block_all_rest_api';

	/**
	 * Option name: Public route exceptions list.
	 *
	 * @var string
	 */
	public const OPTION_PUBLIC_ROUTE_PREFIXES = 'dydaps_api_public_route_prefixes';

	/** Option name: allow same-process REST requests made through rest_do_request(). */
	public const OPTION_ALLOW_INTERNAL_REST_REQUESTS = 'dydaps_api_allow_internal_rest_requests';

	/** Option name: explicitly allowed REST namespaces. */
	public const OPTION_ALLOWED_REST_NAMESPACES = 'dydaps_api_allowed_rest_namespaces';

	/**
	 * All plugin-managed option names.
	 *
	 * @var string[]
	 */
	public const OPTION_NAMES = [
		self::OPTION_API_KEY_HASH,
		self::OPTION_API_KEY_CREATED_AT,
		self::OPTION_API_KEY_LAST_ROTATED_AT,
		self::OPTION_PROTECTED_ROUTE_PREFIXES,
		self::OPTION_BLOCK_ALL_REST_API,
		self::OPTION_PUBLIC_ROUTE_PREFIXES,
		self::OPTION_ALLOW_INTERNAL_REST_REQUESTS,
		self::OPTION_ALLOWED_REST_NAMESPACES,
	];

	/**
	 * Transient name: Temporary plain key display (one-time).
	 *
	 * @var string
	 */
	public const TRANSIENT_PLAIN_KEY = 'dydaps_api_protection_plain_key';

	/**
	 * Transient TTL: How long the plain key is displayed (in seconds).
	 *
	 * @var int
	 */
	public const TRANSIENT_PLAIN_KEY_TTL = 300; // 5 minutes.

	/**
	 * Minimum required WordPress version.
	 *
	 * @var string
	 */
	public const MINIMUM_WP_VERSION = '6.4';

	/**
	 * Minimum required PHP version.
	 *
	 * @var string
	 */
	public const MINIMUM_PHP_VERSION = '7.4';

	/**
	 * API key minimum length in characters.
	 *
	 * @var int
	 */
	public const MINIMUM_API_KEY_LENGTH = 64;

	/**
	 * Admin settings page slug.
	 *
	 * @var string
	 */
	public const ADMIN_PAGE_SLUG = 'dydaps-api-protection';

	/**
	 * Admin stylesheet handle.
	 *
	 * @var string
	 */
	public const ADMIN_STYLE_HANDLE = 'dydaps-api-protection-admin';

	/**
	 * Admin script handle.
	 *
	 * @var string
	 */
	public const ADMIN_SCRIPT_HANDLE = 'dydaps-api-protection-admin';
}
