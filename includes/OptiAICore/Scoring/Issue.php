<?php
/**
 * Value object for a single detected issue on a scanned item.
 *
 * @package OptiAI_Core
 */

namespace OptiAI\Core\Scoring;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One rule's finding against one item.
 */
class Issue {

	const SEVERITY_CRITICAL    = 'critical';
	const SEVERITY_WARNING     = 'warning';
	const SEVERITY_REVIEW      = 'review';
	const SEVERITY_INFORMATION = 'information';

	/** @var string Stable machine code, e.g. "missing_alt_text". */
	public $code;

	/** @var string One of the SEVERITY_* constants. */
	public $severity;

	/** @var int Points deducted from the item's score for this issue. */
	public $deduction;

	/** @var string Plain-English explanation shown to the user. */
	public $message;

	/** @var array Extra context (e.g. affected count for duplicates). */
	public $meta;

	/**
	 * @param string $code      Machine code.
	 * @param string $severity  Severity band.
	 * @param int    $deduction Points deducted (positive integer).
	 * @param string $message   Plain-English explanation.
	 * @param array  $meta      Extra context.
	 */
	public function __construct( $code, $severity, $deduction, $message, $meta = array() ) {
		$this->code      = (string) $code;
		$this->severity  = (string) $severity;
		$this->deduction = max( 0, (int) $deduction );
		$this->message   = (string) $message;
		$this->meta       = is_array( $meta ) ? $meta : array();
	}

	/**
	 * Severity rank used to find the "worst" issue on an item (higher = worse).
	 *
	 * @return int
	 */
	public function severity_rank() {
		switch ( $this->severity ) {
			case self::SEVERITY_CRITICAL:
				return 4;
			case self::SEVERITY_WARNING:
				return 3;
			case self::SEVERITY_REVIEW:
				return 2;
			case self::SEVERITY_INFORMATION:
				return 1;
			default:
				return 0;
		}
	}

	/**
	 * @return array
	 */
	public function to_array() {
		return array(
			'code'      => $this->code,
			'severity'  => $this->severity,
			'deduction' => $this->deduction,
			'message'   => $this->message,
			'meta'      => $this->meta,
		);
	}
}
