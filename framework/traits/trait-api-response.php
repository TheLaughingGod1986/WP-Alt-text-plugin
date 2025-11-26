<?php
/**
 * API Response Trait
 *
 * Provides standardized API response handling.
 *
 * @package Optti\Framework\Traits
 */

namespace Optti\Framework\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait API_Response
 *
 * Use this trait to standardize API responses.
 */
trait API_Response {

	/**
	 * Normalize API error response.
	 *
	 * @param string|array $error Error message or error data.
	 * @param int          $code HTTP status code.
	 * @return array Normalized error response.
	 */
	protected function normalize_error( $error, $code = 400 ) {
		if ( is_string( $error ) ) {
			return [
				'success' => false,
				'error'   => $error,
				'code'    => $code,
			];
		}

		return array_merge(
			[
				'success' => false,
				'code'    => $code,
			],
			is_array( $error ) ? $error : [ 'error' => $error ]
		);
	}

	/**
	 * Normalize API success response.
	 *
	 * @param mixed $data Response data.
	 * @param int   $code HTTP status code.
	 * @return array Normalized success response.
	 */
	protected function normalize_success( $data = null, $code = 200 ) {
		$response = [
			'success' => true,
			'code'    => $code,
		];

		if ( null !== $data ) {
			$response['data'] = $data;
		}

		return $response;
	}
}

