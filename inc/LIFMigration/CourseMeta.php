<?php
/**
 * LifterLMS course meta / settings migration.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.6.0
 */

namespace Themeum\TutorLMSMigrationTool\LIFMigration;

use Themeum\TutorLMSMigrationTool\Interfaces\PostMeta;
use TUTOR\Course;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates LifterLMS course settings into Tutor course meta.
 *
 * Only maps fields that have a TutorLMS equivalent. Pricing / product attach
 * remains in LIFtoTutorMigration (WC / EDD paths).
 *
 * @since 2.6.0
 */
class CourseMeta implements PostMeta {

	/**
	 * Map LifterLMS difficulty term names to Tutor course level slugs.
	 *
	 * @since 2.6.0
	 *
	 * @var array<string, string>
	 */
	const DIFFICULTY_TO_LEVEL = array(
		'beginner'     => 'beginner',
		'intermediate' => 'intermediate',
		'advanced'     => 'expert',
		'expert'       => 'expert',
		'all levels'   => 'all_levels',
		'all_levels'   => 'all_levels',
	);

	/**
	 * Current course ID.
	 *
	 * @since 2.6.0
	 *
	 * @var int
	 */
	private $course_id = 0;

	/**
	 * Migrate course meta for a LifterLMS course post.
	 *
	 * @since 2.6.0
	 *
	 * @param int $post_id Course post ID.
	 *
	 * @return void
	 */
	public function migrate( int $post_id ) {
		if ( $post_id < 1 ) {
			return;
		}

		$this->course_id = $post_id;

		$this->migrate_video();
		$this->migrate_duration();
		$this->migrate_difficulty_level();
		$this->migrate_prerequisites();
		$this->migrate_course_settings();
	}

	/**
	 * Map Lifter video embed URL to Tutor `_video` meta.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	private function migrate_video() {
		$video_embed = (string) get_post_meta( $this->course_id, '_llms_video_embed', true );
		$video_embed = trim( $video_embed );

		if ( '' === $video_embed || ! function_exists( 'tlmt_get_video_source_by_url' ) ) {
			return;
		}

		update_post_meta(
			$this->course_id,
			'_video',
			tlmt_get_video_source_by_url( $video_embed )
		);
	}

	/**
	 * Map Lifter free-text course length to Tutor `_course_duration`.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	private function migrate_duration() {
		$length = (string) get_post_meta( $this->course_id, '_llms_length', true );
		$length = trim( $length );

		if ( '' === $length ) {
			return;
		}

		$duration = $this->parse_length_to_duration( $length );
		if ( empty( $duration['hours'] ) && empty( $duration['minutes'] ) ) {
			return;
		}

		update_post_meta( $this->course_id, Course::COURSE_DURATION_META, $duration );
	}

	/**
	 * Map Lifter `course_difficulty` taxonomy to Tutor `_tutor_course_level`.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	private function migrate_difficulty_level() {
		$terms = get_the_terms( $this->course_id, 'course_difficulty' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		$term  = $terms[0];
		$slug  = strtolower( (string) $term->slug );
		$name  = strtolower( (string) $term->name );
		$level = self::DIFFICULTY_TO_LEVEL[ $slug ] ?? ( self::DIFFICULTY_TO_LEVEL[ $name ] ?? '' );

		if ( '' === $level ) {
			// Fall back to Tutor slugs when Lifter already uses the same keys.
			$tutor_levels = array( 'all_levels', 'beginner', 'intermediate', 'expert' );
			if ( in_array( $slug, $tutor_levels, true ) ) {
				$level = $slug;
			}
		}

		if ( '' === $level ) {
			return;
		}

		update_post_meta( $this->course_id, Course::COURSE_LEVEL_META, $level );
	}

	/**
	 * Map Lifter course prerequisite to Tutor Pro prerequisites meta.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	private function migrate_prerequisites() {
		if ( ! function_exists( 'tutor_pro' ) ) {
			return;
		}

		$has_prereq = get_post_meta( $this->course_id, '_llms_has_prerequisite', true );
		if ( 'yes' !== $has_prereq ) {
			return;
		}

		$prerequisite_id = (int) get_post_meta( $this->course_id, '_llms_prerequisite', true );
		if ( $prerequisite_id < 1 || $prerequisite_id === $this->course_id ) {
			return;
		}

		// Same post ID is kept through CPT conversion, so the reference stays valid.
		update_post_meta( $this->course_id, '_tutor_course_prerequisites_ids', array( $prerequisite_id ) );
	}

	/**
	 * Map Lifter capacity, enrollment window, and content drip into `_tutor_course_settings`.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	private function migrate_course_settings() {
		if ( ! function_exists( 'tutor_pro' ) ) {
			return;
		}

		$current_settings = maybe_unserialize( get_post_meta( $this->course_id, '_tutor_course_settings', true ) );
		if ( ! is_array( $current_settings ) ) {
			$current_settings = array(
				'maximum_students'         => 0,
				'enrollment_expiry'        => 0,
				'enable_content_drip'      => 0,
				'content_drip_type'        => '',
				'course_enrollment_period' => '',
				'enrollment_starts_at'     => '',
				'enrollment_ends_at'       => '',
				'pause_enrollment'         => '',
			);
		}

		$changed = false;

		// Capacity → maximum students.
		if ( 'yes' === get_post_meta( $this->course_id, '_llms_enable_capacity', true ) ) {
			$capacity = (int) get_post_meta( $this->course_id, '_llms_capacity', true );
			if ( $capacity > 0 ) {
				$current_settings['maximum_students'] = $capacity;
				$changed                              = true;
			}
		}

		// Enrollment period window.
		if ( 'yes' === get_post_meta( $this->course_id, '_llms_enrollment_period', true ) ) {
			$starts_at = $this->normalize_datetime( get_post_meta( $this->course_id, '_llms_enrollment_start_date', true ) );
			$ends_at   = $this->normalize_datetime( get_post_meta( $this->course_id, '_llms_enrollment_end_date', true ) );

			if ( $starts_at || $ends_at ) {
				$current_settings['course_enrollment_period'] = 'yes';
				$current_settings['enrollment_starts_at']     = $starts_at;
				$current_settings['enrollment_ends_at']       = $ends_at;
				$changed                                      = true;
			}
		}

		// Course-level lesson drip → Tutor content drip.
		if ( 'yes' === get_post_meta( $this->course_id, '_llms_lesson_drip', true ) ) {
			$current_settings['enable_content_drip'] = 1;
			$days                                    = (int) get_post_meta( $this->course_id, '_llms_days_before_available', true );
			$drip_method                             = (string) get_post_meta( $this->course_id, '_llms_drip_method', true );

			if ( $days > 0 || in_array( $drip_method, array( 'start', 'enrollment' ), true ) ) {
				$current_settings['content_drip_type'] = 'specific_days';
			} else {
				$current_settings['content_drip_type'] = 'unlock_sequentially';
			}

			$changed = true;
		}

		if ( $changed ) {
			update_post_meta( $this->course_id, '_tutor_course_settings', $current_settings );
		}
	}

	/**
	 * Parse Lifter free-text length into Tutor hours/minutes duration array.
	 *
	 * @since 2.6.0
	 *
	 * @param string $length Lifter `_llms_length` value.
	 *
	 * @return array{hours: int|string, minutes: int|string}
	 */
	private function parse_length_to_duration( string $length ): array {
		$hours   = 0;
		$minutes = 0;

		if ( preg_match( '/(\d+)\s*w(?:ee)?ks?/i', $length, $m ) ) {
			$hours += ( (int) $m[1] ) * 24 * 7;
		}
		if ( preg_match( '/(\d+)\s*d(?:ay)?s?/i', $length, $m ) ) {
			$hours += ( (int) $m[1] ) * 24;
		}
		if ( preg_match( '/(\d+)\s*h(?:ou)?rs?/i', $length, $m ) ) {
			$hours += (int) $m[1];
		}
		if ( preg_match( '/(\d+)\s*min(?:ute)?s?/i', $length, $m ) ) {
			$minutes += (int) $m[1];
		}

		// Bare number with no unit — treat as hours when it looks intentional.
		if ( 0 === $hours && 0 === $minutes && preg_match( '/^\d+$/', $length ) ) {
			$hours = (int) $length;
		}

		if ( $minutes >= 60 ) {
			$hours  += (int) floor( $minutes / 60 );
			$minutes = $minutes % 60;
		}

		return array(
			'hours'   => $hours,
			'minutes' => $minutes,
		);
	}

	/**
	 * Normalize a Lifter date string to `Y-m-d H:i:s` when possible.
	 *
	 * @since 2.6.0
	 *
	 * @param mixed $value Raw meta value.
	 *
	 * @return string Empty string when unparseable.
	 */
	private function normalize_datetime( $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
