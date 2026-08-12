<?php
/**
 * LearnPress fill-in-the-blanks → Tutor transformer.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.5.0
 */

namespace Themeum\TutorLMSMigrationTool\LPMigration\Quizzes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts LearnPress fill_in_blanks answers to Tutor fill_in_the_blank format.
 *
 * LearnPress stores passage text with `[fib id="…" fill="…"]` shortcodes (or
 * legacy `{{blank}}` placeholders) plus `_blanks` answer meta.
 * Tutor expects `{dash}` placeholders in `answer_title` and pipe-separated
 * correct answers in `answer_two_gap_match`.
 *
 * @since 2.5.0
 */
class FillInBlanksTransformer {

	/**
	 * LearnPress question type slug.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	const LP_TYPE = 'fill_in_blanks';

	/**
	 * Tutor question type slug.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	const TUTOR_TYPE = 'fill_in_the_blank';

	/**
	 * Transform an LP fill-in-blanks answer title + blanks meta into Tutor fields.
	 *
	 * @since 2.5.0
	 *
	 * @param string              $title  LP answer title / passage.
	 * @param array|string|null   $blanks `_blanks` meta (array or serialized).
	 *
	 * @return array{answer_title: string, answer_two_gap_match: string}
	 */
	public function transform( $title, $blanks = null ) {
		$title  = is_string( $title ) ? $title : '';
		$blanks = $this->normalize_blanks( $blanks );

		if ( $this->has_fib_shortcodes( $title ) ) {
			return $this->transform_from_shortcodes( $title, $blanks );
		}

		if ( false !== strpos( $title, '{{blank}}' ) ) {
			return $this->transform_from_blank_placeholders( $title, $blanks );
		}

		// Passage already uses Tutor-style dashes, or has no blanks — use meta fills if present.
		$fills = $this->extract_fills_from_blanks( $blanks );
		if ( ! empty( $fills ) && false === strpos( $title, '{dash}' ) ) {
			// No placeholders left; append dashes so Tutor still renders inputs.
			$title = trim( $title . ' ' . str_repeat( '{dash} ', count( $fills ) ) );
		}

		return array(
			'answer_title'         => trim( $title ),
			'answer_two_gap_match' => implode( '|', $fills ),
		);
	}

	/**
	 * Whether the title contains LearnPress `[fib …]` shortcodes.
	 *
	 * @since 2.5.0
	 *
	 * @param string $title Answer title.
	 *
	 * @return bool
	 */
	private function has_fib_shortcodes( $title ) {
		return (bool) preg_match( '/\[fib\b/i', $title );
	}

	/**
	 * Transform shortcode-based LP passage.
	 *
	 * @since 2.5.0
	 *
	 * @param string $title  Answer title with `[fib]` shortcodes.
	 * @param array  $blanks Normalized blanks keyed preferably by id.
	 *
	 * @return array{answer_title: string, answer_two_gap_match: string}
	 */
	private function transform_from_shortcodes( $title, array $blanks ) {
		$fills = array();

		$transformed = preg_replace_callback(
			'/\[fib\b([^\]]*)\]/i',
			function ( $matches ) use ( $blanks, &$fills ) {
				$atts = $this->parse_shortcode_atts( $matches[0] );
				$id   = isset( $atts['id'] ) ? (string) $atts['id'] : '';
				$fill = isset( $atts['fill'] ) ? (string) $atts['fill'] : '';

				if ( '' === $fill && '' !== $id && isset( $blanks[ $id ]['fill'] ) ) {
					$fill = (string) $blanks[ $id ]['fill'];
				}

				$fills[] = $this->sanitize_fill( $fill );

				return '{dash}';
			},
			$title
		);

		if ( empty( $fills ) ) {
			$fills = $this->extract_fills_from_blanks( $blanks );
		}

		return array(
			'answer_title'         => is_string( $transformed ) ? trim( $transformed ) : trim( $title ),
			'answer_two_gap_match' => implode( '|', $fills ),
		);
	}

	/**
	 * Transform legacy `{{blank}}` placeholder passages (e.g. seed data).
	 *
	 * @since 2.5.0
	 *
	 * @param string $title  Answer title with `{{blank}}` tokens.
	 * @param array  $blanks Normalized blanks.
	 *
	 * @return array{answer_title: string, answer_two_gap_match: string}
	 */
	private function transform_from_blank_placeholders( $title, array $blanks ) {
		$fills = $this->extract_fills_from_blanks( $blanks );

		return array(
			'answer_title'         => trim( str_replace( '{{blank}}', '{dash}', $title ) ),
			'answer_two_gap_match' => implode( '|', $fills ),
		);
	}

	/**
	 * Normalize `_blanks` meta into an id-keyed array of blank definitions.
	 *
	 * @since 2.5.0
	 *
	 * @param array|string|null $blanks Raw blanks meta.
	 *
	 * @return array
	 */
	private function normalize_blanks( $blanks ) {
		if ( is_string( $blanks ) ) {
			$blanks = maybe_unserialize( $blanks );
		}

		if ( ! is_array( $blanks ) || empty( $blanks ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $blanks as $key => $blank ) {
			if ( ! is_array( $blank ) ) {
				continue;
			}

			$id = '';
			if ( isset( $blank['id'] ) && '' !== (string) $blank['id'] ) {
				$id = (string) $blank['id'];
			} elseif ( ! is_int( $key ) ) {
				$id = (string) $key;
			}

			if ( '' === $id ) {
				$id = 'blank_' . count( $normalized );
			}

			$blank['id']        = $id;
			$normalized[ $id ] = $blank;
		}

		return $normalized;
	}

	/**
	 * Extract fill values from blanks in stored order.
	 *
	 * @since 2.5.0
	 *
	 * @param array $blanks Normalized blanks.
	 *
	 * @return string[]
	 */
	private function extract_fills_from_blanks( array $blanks ) {
		$fills = array();

		foreach ( $blanks as $blank ) {
			if ( ! is_array( $blank ) ) {
				continue;
			}
			$fills[] = $this->sanitize_fill( isset( $blank['fill'] ) ? $blank['fill'] : '' );
		}

		return $fills;
	}

	/**
	 * Parse attributes from a `[fib …]` shortcode string.
	 *
	 * @since 2.5.0
	 *
	 * @param string $shortcode Full shortcode including brackets.
	 *
	 * @return array
	 */
	private function parse_shortcode_atts( $shortcode ) {
		if ( function_exists( 'shortcode_parse_atts' ) ) {
			$inner = preg_replace( '/^\[fib\b/i', '', $shortcode );
			$inner = rtrim( (string) $inner, ']' );
			$atts  = shortcode_parse_atts( trim( (string) $inner ) );

			return is_array( $atts ) ? $atts : array();
		}

		$atts = array();
		if ( preg_match_all( '/(\w+)\s*=\s*"([^"]*)"/', $shortcode, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$atts[ $match[1] ] = $match[2];
			}
		}

		return $atts;
	}

	/**
	 * Sanitize a single blank fill value.
	 *
	 * @since 2.5.0
	 *
	 * @param mixed $fill Raw fill.
	 *
	 * @return string
	 */
	private function sanitize_fill( $fill ) {
		$fill = is_scalar( $fill ) ? (string) $fill : '';
		$fill = html_entity_decode( $fill, ENT_QUOTES, 'UTF-8' );
		$fill = wp_strip_all_tags( $fill );

		return trim( $fill );
	}
}
