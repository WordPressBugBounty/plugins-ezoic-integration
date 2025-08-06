<?php

namespace Ezoic_Namespace;

/**
 * Centralized logging utility for Ezoic Integration plugin
 *
 * @link       https://ezoic.com
 * @since      1.0.0
 *
 * @package    Ezoic_Integration
 * @subpackage Ezoic_Integration/includes
 */
class Ezoic_Integration_Logger
{
	/**
	 * Log a general message with Ezoic prefix
	 *
	 * @param string $message The message to log
	 * @param string $context Optional context identifier (e.g., 'AdsTxt', 'JS Integration')
	 */
	public static function log($message, $context = '')
	{
		$prefix = $context ? "[ Ezoic - {$context} ]" : '[ Ezoic ]';
		error_log($prefix . ' ' . $message);
	}

	/**
	 * Log an error message
	 *
	 * @param string $message The error message to log
	 * @param string $context Optional context identifier
	 */
	public static function log_error($message, $context = '')
	{
		self::log('ERROR: ' . $message, $context);
	}

	/**
	 * Log a warning message
	 *
	 * @param string $message The warning message to log
	 * @param string $context Optional context identifier
	 */
	public static function log_warning($message, $context = '')
	{
		self::log('WARNING: ' . $message, $context);
	}

	/**
	 * Log debug information (only if debugging is enabled)
	 *
	 * @param string $message The debug message to log
	 * @param string $context Optional context identifier
	 */
	public static function log_debug($message, $context = '')
	{
		if (defined('EZOIC_DEBUG') && EZOIC_DEBUG) {
			self::log('DEBUG: ' . $message, $context);
		}
	}

	/**
	 * Log an exception with full details
	 *
	 * @param \Exception $exception The exception to log
	 * @param string $context Optional context identifier
	 */
	public static function log_exception($exception, $context = '')
	{
		$message = sprintf(
			'EXCEPTION: %s in %s:%d - %s',
			get_class($exception),
			$exception->getFile(),
			$exception->getLine(),
			$exception->getMessage()
		);
		self::log($message, $context);
	}

	/**
	 * Log API communication errors with request details
	 *
	 * @param string $endpoint The API endpoint
	 * @param mixed $response The response data
	 * @param string $context Optional context identifier
	 */
	public static function log_api_error($endpoint, $response, $context = 'API')
	{
		$message = sprintf(
			'API Error communicating with %s: %s',
			$endpoint,
			is_array($response) || is_object($response) ? print_r($response, true) : $response
		);
		self::log_error($message, $context);
	}
}