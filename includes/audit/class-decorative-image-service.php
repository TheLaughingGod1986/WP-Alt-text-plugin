<?php
/**
 * Administrator decisions about decorative images.
 *
 * A decorative image is one that carries no information — a divider, a spacer,
 * a purely ornamental flourish — and an empty alt attribute is the correct
 * markup for it. Nothing here guesses: this records a person's decision and
 * lets them take it back.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decorative marking.
 */
class Decorative_Image_Service {

	const META_FLAG   = '_bbai_decorative';
	const META_SET_AT = '_bbai_decorative_set_at';
	const META_SET_BY = '_bbai_decorative_set_by';

	/**
	 * Whether an image is marked decorative.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_decorative( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			return false;
		}

		return '' !== (string) get_post_meta( $attachment_id, self::META_FLAG, true );
	}

	/**
	 * Record that an image is decorative.
	 *
	 * The image's alt text is deliberately left alone. Emptying it would be an
	 * automatic overwrite of existing content, which this phase does not do.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $user_id       Who decided.
	 * @return bool
	 */
	public static function mark( $attachment_id, $user_id = 0 ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			return false;
		}

		update_post_meta( $attachment_id, self::META_FLAG, '1' );
		update_post_meta( $attachment_id, self::META_SET_AT, current_time( 'mysql' ) );
		update_post_meta( $attachment_id, self::META_SET_BY, (int) $user_id );

		/**
		 * Fires after an image is marked decorative.
		 *
		 * @since 4.8.0
		 *
		 * @param int $attachment_id Attachment ID.
		 * @param int $user_id       User who decided.
		 */
		do_action( 'bbai_audit_image_marked_decorative', $attachment_id, (int) $user_id );

		return true;
	}

	/**
	 * Take back a decorative decision.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function unmark( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			return false;
		}

		delete_post_meta( $attachment_id, self::META_FLAG );
		delete_post_meta( $attachment_id, self::META_SET_AT );
		delete_post_meta( $attachment_id, self::META_SET_BY );

		/**
		 * Fires after a decorative decision is reversed.
		 *
		 * @since 4.8.0
		 *
		 * @param int $attachment_id Attachment ID.
		 */
		do_action( 'bbai_audit_image_decorative_reversed', $attachment_id );

		return true;
	}

	/**
	 * Set or clear the decision in one call.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $decorative    Desired state.
	 * @param int  $user_id       Who decided.
	 * @return bool
	 */
	public static function set( $attachment_id, $decorative, $user_id = 0 ) {
		return $decorative
			? self::mark( $attachment_id, $user_id )
			: self::unmark( $attachment_id );
	}

	/**
	 * When and by whom the decision was made.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{decorative:bool,set_at:string,set_by:int}
	 */
	public static function get_decision( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		return array(
			'decorative' => self::is_decorative( $attachment_id ),
			'set_at'     => $attachment_id > 0 ? (string) get_post_meta( $attachment_id, self::META_SET_AT, true ) : '',
			'set_by'     => $attachment_id > 0 ? (int) get_post_meta( $attachment_id, self::META_SET_BY, true ) : 0,
		);
	}
}
