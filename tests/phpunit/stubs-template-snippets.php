<?php
/**
 * Shared helpers for 4.6.143 Starter gate tests: minimal WP stubs and a way to
 * run one assignment statement lifted from a template with chosen inputs.
 */
declare(strict_types=1);

namespace {
	foreach ( array( '__', 'esc_html__', 'sanitize_text_field', 'esc_url_raw' ) as $bbai_fn ) {
		if ( ! function_exists( $bbai_fn ) ) {
			eval( 'function ' . $bbai_fn . '( $s, $d = null ) { return $s; }' );
		}
	}
	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( $key ) { return strtolower( (string) preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) ); }
	}
	if ( ! function_exists( 'admin_url' ) ) {
		function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . ltrim( (string) $path, '/' ); }
	}
	if ( ! function_exists( '_n' ) ) {
		function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
	}
	if ( ! function_exists( 'number_format_i18n' ) ) {
		function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
	}

	final class Bbai_Template_Snippet {
		/** Source of a plugin file. */
		public static function src( string $rel ): string {
			return (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . $rel );
		}

		/**
		 * The full statement that starts at $marker (up to its top-level ';').
		 */
		public static function statement( string $rel, string $marker ): string {
			$src = self::src( $rel );
			$pos = strpos( $src, $marker );
			if ( false === $pos ) {
				throw new \RuntimeException( "Marker not found in $rel: $marker" );
			}
			$depth = 0;
			$quote = '';
			$len   = strlen( $src );
			for ( $i = $pos; $i < $len; $i++ ) {
				$c = $src[ $i ];
				if ( '' !== $quote ) {
					if ( '\\' === $c ) { $i++; continue; }
					if ( $c === $quote ) { $quote = ''; }
					continue;
				}
				if ( '/' === $c && '*' === ( $src[ $i + 1 ] ?? '' ) ) {
					$end = strpos( $src, '*/', $i + 2 );
					$i   = false === $end ? $len : $end + 1;
					continue;
				}
				if ( "'" === $c || '"' === $c ) { $quote = $c; continue; }
				if ( '(' === $c || '[' === $c || '{' === $c ) { $depth++; }
				if ( ')' === $c || ']' === $c || '}' === $c ) { $depth--; }
				if ( ';' === $c && 0 === $depth ) {
					return substr( $src, $pos, $i - $pos + 1 );
				}
			}
			throw new \RuntimeException( "Unterminated statement in $rel: $marker" );
		}

		/**
		 * Run the statements with $vars in scope and return every variable afterwards.
		 *
		 * @param string[]            $statements Code.
		 * @param array<string,mixed> $vars       Inputs.
		 * @return array<string,mixed>
		 */
		public static function run( array $statements, array $vars ): array {
			return ( static function ( array $bbai_snippet_statements, array $bbai_snippet_vars ): array {
				extract( $bbai_snippet_vars ); // phpcs:ignore
				foreach ( $bbai_snippet_statements as $bbai_snippet_code ) {
					eval( $bbai_snippet_code );
				}
				unset( $bbai_snippet_statements, $bbai_snippet_vars, $bbai_snippet_code );
				return get_defined_vars();
			} )( $statements, $vars );
		}
	}
}

namespace {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-plan-tier.php';
	if ( ! class_exists( 'Plan_Tier', false ) ) {
		class_alias( \BeepBeepAI\AltTextGenerator\Plan_Tier::class, 'Plan_Tier' );
	}
}
