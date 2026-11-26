<?php
/**
 * Sensei LMS Fill In Blanks Transformer for Tutor LMS.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\SenseiMigration\Quizzes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transforms Sensei gap fill syntax to Tutor LMS format.
 */
class FillInBlanksTransformer {

	/**
	 * Transform Sensei gap fill representation to Tutor LMS format.
	 *
	 * Sensei stores gap fill in one of two formats:
	 * 1) "pre || gap || post" stored in _question_right_answer
	 * 2) Question text containing bracketed gaps like "Sentence with [gap] word."
	 *
	 * Tutor LMS expects:
	 * - answer_title: Sentence containing "{dash}" placeholders
	 * - answer_two_gap_match: Pipe-separated correct words e.g. "word" or "word1|word2"
	 *
	 * @param string $right_answer Sensei _question_right_answer meta.
	 * @param string $question_text Sensei question post content or title.
	 * @return array{answer_title: string, answer_two_gap_match: string}
	 */
	public function transform( string $right_answer, string $question_text = '' ): array {
		$right_answer  = trim( $right_answer );
		$question_text = trim( $question_text );

		// 1. Delimited by '||': pre || gap || post
		if ( strpos( $right_answer, '||' ) !== false ) {
			$parts = explode( '||', $right_answer );
			$pre   = trim( $parts[0] ?? '' );
			$gap   = trim( $parts[1] ?? '', " \t\n\r\0\x0B[]{}" );
			$post  = trim( $parts[2] ?? '' );

			// Sensei allows pipe-separated alternative answers for a single gap (e.g. "Paris|France").
			// Tutor LMS uses "|" as a gap separator, not an alternatives separator, so only the
			// first (canonical) answer is kept to avoid multi-gap mis-interpretation.
			if ( strpos( $gap, '|' ) !== false ) {
				$gap = trim( explode( '|', $gap )[0] );
			}

			$sentence = trim( $pre . ' {dash} ' . $post );
			return array(
				'answer_title'         => $sentence,
				'answer_two_gap_match' => $gap,
			);
		}

		// 2. Bracketed gaps in question_text or right_answer: e.g. [Paris] or {Paris}
		$target_text = ! empty( $question_text ) && ( strpos( $question_text, '[' ) !== false || strpos( $question_text, '{' ) !== false )
			? $question_text
			: $right_answer;

		if ( preg_match_all( '/\[(.*?)\]|\{(.*?)\}/', $target_text, $matches ) ) {
			$extracted = array();
			foreach ( $matches[1] as $idx => $m1 ) {
				$val = ! empty( $m1 ) ? $m1 : ( $matches[2][ $idx ] ?? '' );
				$val = trim( $val );
				if ( '' !== $val ) {
					$extracted[] = $val;
				}
			}

			$sentence = preg_replace( '/\[.*?\]|\{.*?\}/', '{dash}', $target_text );
			return array(
				'answer_title'         => trim( (string) $sentence ),
				'answer_two_gap_match' => implode( '|', $extracted ),
			);
		}

		// 3. Fallback: question text with blank appended, or right answer
		$fallback_sentence = ! empty( $question_text ) ? $question_text . ' {dash}' : '{dash}';
		return array(
			'answer_title'         => $fallback_sentence,
			'answer_two_gap_match' => $right_answer,
		);
	}
}
