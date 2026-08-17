<?php
/**
 * Phase 17 — In-product assistant (rule-based + filter hook for LLM backends).
 *
 * Does not change scoring algorithms; explains workflows and points to screens.
 *
 * @package BeepBeep_AI
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Phase17_Assistant {

	/**
	 * @param string               $message User message (already sanitized).
	 * @param array<string, mixed> $context Optional: page, attachment_id, score_label, etc.
	 * @return array{reply:string,suggestions:array<int,string>,sources:array<int,array{label:string,url:string}>,mode:string}
	 */
	public static function reply( string $message, array $context = [] ): array {
		$message = mb_substr( trim( wp_strip_all_tags( $message ) ), 0, 2000 );
		if ( '' === $message ) {
			return self::wrap(
				__( 'Ask a question about ALT text, credits, the library, or WooCommerce — I’ll point you to the right place.', 'beepbeep-ai-alt-text-generator' ),
				[],
				[],
				'empty'
			);
		}

		$lower = mb_strtolower( $message );

		$filtered = apply_filters( 'bbai_phase17_assistant_reply', null, $message, $context );
		if ( is_array( $filtered ) && isset( $filtered['reply'] ) && is_string( $filtered['reply'] ) ) {
			return [
				'reply'        => (string) $filtered['reply'],
				'suggestions'  => isset( $filtered['suggestions'] ) && is_array( $filtered['suggestions'] ) ? array_values( array_filter( array_map( 'strval', $filtered['suggestions'] ) ) ) : [],
				'sources'      => self::normalize_sources( $filtered['sources'] ?? [] ),
				'mode'         => isset( $filtered['mode'] ) ? sanitize_key( (string) $filtered['mode'] ) : 'custom',
			];
		}

		$sources_lib   = [ 'label' => __( 'ALT Library', 'beepbeep-ai-alt-text-generator' ), 'url' => admin_url( 'admin.php?page=bbai-library' ) ];
		$sources_dash  = [ 'label' => __( 'Dashboard', 'beepbeep-ai-alt-text-generator' ), 'url' => admin_url( 'admin.php?page=bbai' ) ];
		$sources_usage = [ 'label' => __( 'Usage & plan', 'beepbeep-ai-alt-text-generator' ), 'url' => admin_url( 'admin.php?page=bbai-credit-usage' ) ];
		$sources_set   = [ 'label' => __( 'Settings', 'beepbeep-ai-alt-text-generator' ), 'url' => admin_url( 'admin.php?page=bbai-settings' ) ];
		$sources_guide = [ 'label' => __( 'Guide', 'beepbeep-ai-alt-text-generator' ), 'url' => admin_url( 'admin.php?page=bbai-guide' ) ];

		// Credits / quota / upgrade (no pricing logic — links only).
		if ( preg_match( '/\b(credit|quota|limit|plan|upgrade|pay|billing)\b/i', $message ) ) {
			return self::wrap(
				__( 'Credits are used each time AI generates or significantly revises ALT text. Open Usage & plan to see what’s left and when your cycle resets. Upgrade options live there if you need higher limits or automation.', 'beepbeep-ai-alt-text-generator' ),
				[
					__( 'Open Usage & plan', 'beepbeep-ai-alt-text-generator' ),
					__( 'Open Settings for on-upload automation', 'beepbeep-ai-alt-text-generator' ),
				],
				[ $sources_usage, $sources_set ],
				'credits'
			);
		}

		// Score / weak / needs review — explain without changing scoring rules.
		if ( preg_match( '/\b(score|weak|review|quality|bad alt|poor)\b/i', $message ) ) {
			$extra = '';
			if ( ! empty( $context['score_label'] ) ) {
				$extra = ' ' . sprintf(
					/* translators: %s: label from product (e.g. needs review). */
					__( 'Your context shows: %s.', 'beepbeep-ai-alt-text-generator' ),
					sanitize_text_field( (string) $context['score_label'] )
				);
			}
			return self::wrap(
				__( '“Needs review” or weaker scores usually mean the text may be too short, generic, repetitive, or a poor match for the image. Open the image in ALT Library, edit manually, or use “Improve ALT” (regenerate) for a fresh AI suggestion — then save when you’re happy.', 'beepbeep-ai-alt-text-generator' ) . $extra,
				[
					__( 'Filter “Needs review” in ALT Library', 'beepbeep-ai-alt-text-generator' ),
					__( 'Regenerate a single image from the row actions', 'beepbeep-ai-alt-text-generator' ),
				],
				[ $sources_lib, $sources_guide ],
				'score'
			);
		}

		if ( preg_match( '/\b(woo|commerce|product|shop|catalog)\b/i', $message ) ) {
			return self::wrap(
				__( 'BeepBeep AI can target WooCommerce product images alongside the Media Library. Run a scan, then bulk-generate or fix missing ALT on product imagery from ALT Library.', 'beepbeep-ai-alt-text-generator' ),
				[ __( 'Open ALT Library', 'beepbeep-ai-alt-text-generator' ) ],
				[ $sources_lib, $sources_guide ],
				'woocommerce'
			);
		}

		if ( preg_match( '/\b(scan|coverage|missing)\b/i', $message ) ) {
			return self::wrap(
				__( 'Start from the Dashboard: refresh coverage to see missing vs optimised images. Then jump to ALT Library with the Missing filter to generate in bulk or row-by-row.', 'beepbeep-ai-alt-text-generator' ),
				[ __( 'Run coverage refresh', 'beepbeep-ai-alt-text-generator' ) ],
				[ $sources_dash, $sources_lib ],
				'scan'
			);
		}

		if ( preg_match( '/\b(automat|upload|new image)\b/i', $message ) ) {
			return self::wrap(
				__( 'On-upload automation lives in Settings. When enabled, new uploads can receive AI ALT text automatically (subject to your plan and credits).', 'beepbeep-ai-alt-text-generator' ),
				[ __( 'Open Settings → automation', 'beepbeep-ai-alt-text-generator' ) ],
				[ $sources_set, $sources_guide ],
				'automation'
			);
		}

		if ( preg_match( '/\b(access|wcag|a11y|ada)\b/i', $message ) ) {
			return self::wrap(
				__( 'Descriptive ALT text helps screen readers and satisfies common WCAG expectations for non-decorative images. Use specific, concise descriptions of what’s in the image — avoid keyword stuffing.', 'beepbeep-ai-alt-text-generator' ),
				[],
				[ $sources_guide ],
				'a11y'
			);
		}

		return self::wrap(
			__( 'I’m a lightweight guide inside the plugin. Try asking about credits, missing ALT, “needs review”, WooCommerce, scans, or automation. For account or billing issues, use Support from the plugin menu or your host.', 'beepbeep-ai-alt-text-generator' ),
			[
				__( 'Credits and usage', 'beepbeep-ai-alt-text-generator' ),
				__( 'ALT Library workflow', 'beepbeep-ai-alt-text-generator' ),
				__( 'WooCommerce images', 'beepbeep-ai-alt-text-generator' ),
			],
			[ $sources_dash, $sources_lib, $sources_guide ],
			'fallback'
		);
	}

	/**
	 * Heuristic text suggestion when user cannot spend a credit (optional copy-only).
	 *
	 * @param string $current Current ALT text.
	 * @return string
	 */
	public static function suggest_text_only( string $current ): string {
		$current = trim( wp_strip_all_tags( $current ) );
		if ( '' === $current ) {
			return '';
		}
		if ( mb_strlen( $current ) < 40 ) {
			return __( 'Tip: expand with the main subject, setting, and one concrete detail (e.g. “Red hiking boots on a wooden bench in sunlight”). Keep under ~125 characters when possible.', 'beepbeep-ai-alt-text-generator' );
		}
		if ( preg_match( '/^(image|img|photo|picture|pic)\s*[\.#\-:]/i', $current ) ) {
			return __( 'Tip: replace generic prefixes with what the image actually shows; lead with the subject.', 'beepbeep-ai-alt-text-generator' );
		}
		return __( 'Tip: remove duplication, add specificity (who/what/where), and avoid repeating the page title verbatim.', 'beepbeep-ai-alt-text-generator' );
	}

	/**
	 * @param array<int, mixed> $sources
	 * @return array<int, array{label:string,url:string}>
	 */
	private static function normalize_sources( array $sources ): array {
		$out = [];
		foreach ( $sources as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
			$url   = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '';
			if ( '' === $label || '' === $url ) {
				continue;
			}
			$out[] = compact( 'label', 'url' );
		}
		return $out;
	}

	/**
	 * @param array<int,string> $suggestions
	 * @param array<int, array{label:string,url:string}> $sources
	 * @return array{reply:string,suggestions:array<int,string>,sources:array<int,array{label:string,url:string}>,mode:string}
	 */
	private static function wrap( string $reply, array $suggestions, array $sources, string $mode ): array {
		return [
			'reply'       => $reply,
			'suggestions' => $suggestions,
			'sources'     => $sources,
			'mode'        => $mode,
		];
	}
}
