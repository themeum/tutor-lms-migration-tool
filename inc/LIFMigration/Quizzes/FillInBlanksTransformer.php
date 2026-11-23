<?php
/**
 * LifterLMS fill-in-the-blank → Tutor transformer.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.6.0
 */

namespace Themeum\TutorLMSMigrationTool\LIFMigration\Quizzes;

defined( 'ABSPATH' ) || exit;

/**
 * Converts LifterLMS blank questions to Tutor fill_in_the_blank answers.
 *
 * Lifter (Advanced Quizzes) stores correct fills in `_llms_correct_value`
 * as a pipe-separated string and the passage in the question content/title.
 * Tutor expects `{dash}` placeholders in `answer_title` and pipe-separated
 * fills in `answer_two_gap_match`.
 *
 * @since 2.6.0
 */
class FillInBlanksTransformer {

	/**
	 * Tutor question type slug.
	 *
	 * @since 2.6.0
	 *
	 * @var string
	 */
	const TUTOR_TYPE = 'fill_in_the_blank';

	/**
	 * Transform a Lifter blank question into Tutor answer fields.
	 *
	 * @since 2.6.0
	 *
	 * @param string $passage       Question title/content used as the passage.
	 * @param string $correct_value Lifter `_llms_correct_value` (pipe-separated).
	 *
	 * @return array{answer_title: string, answer_two_gap_match: string}|null
	 */
	public function transform( string $passage, string $correct_value ) {
		$fills = $this->parse_fills( $correct_value );
		if ( empty( $fills ) ) {
			return null;
		}

		$passage = $this->normalize_passage( $passage, count( $fills ) );

		return array(
			'answer_title'         => $passage,
			'answer_two_gap_match' => implode( '|', $fills ),
		);
	}

	/**
	 * Parse Lifter correct_value into trimmed fill strings.
	 *
	 * @since 2.6.0
	 *
	 * @param string $correct_value Pipe-separated fills.
	 *
	 * @return string[]
	 */
	private function parse_fills( string $correct_value ): array {
		$correct_value = trim( $correct_value );
		if ( '' === $correct_value ) {
			return array();
		}

		$parts = array_map( 'trim', explode( '|', $correct_value ) );
		return array_values( array_filter( $parts, static fn( $v ) => '' !== $v ) );
	}

	/**
	 * Normalize Lifter blank markers to Tutor `{dash}` placeholders.
	 *
	 * @since 2.6.0
	 *
	 * @param string $passage   Raw passage text.
	 * @param int    $fill_count Number of expected blanks.
	 *
	 * @return string
	 */
	private function normalize_passage( string $passage, int $fill_count ): string {
		$passage = trim( wp_strip_all_tags( $passage ) );

		$replacements = array(
			'{blank}'   => '{dash}',
			'[blank]'   => '{dash}',
			'__________' => '{dash}',
			'________'  => '{dash}',
			'______'    => '{dash}',
			'____'      => '{dash}',
		);
		$passage = str_ireplace( array_keys( $replacements ), array_values( $replacements ), $passage );

		$dash_count = substr_count( $passage, '{dash}' );
		if ( $dash_count < $fill_count ) {
			$passage = trim( $passage . ' ' . str_repeat( '{dash} ', $fill_count - $dash_count ) );
		}

		return trim( $passage );
	}
}
