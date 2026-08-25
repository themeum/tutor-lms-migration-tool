<?php
/**
 * LifterLMS enrollment and completion migration.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.6.0
 */

namespace Themeum\TutorLMSMigrationTool\LIFMigration;

use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\ErrorHandler;
use Themeum\TutorLMSMigrationTool\Factories\StudentProgressFactory;
use Themeum\TutorLMSMigrationTool\MigrationTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Batched LifterLMS → Tutor enrollment migrator.
 *
 * Runs after course structure migration. Each request processes a limited
 * number of student–course pairs so large sites stay under PHP/proxy timeouts.
 * Also migrates lesson-level progress for each pair via StudentProgress.
 *
 * @since 2.6.0
 */
class Enrollments {

	/**
	 * Post meta marking a course whose enrollments were migrated.
	 *
	 * @since 2.6.0
	 *
	 * @var string
	 */
	const MIGRATED_META = '_tlmt_lif_enrollment_migrated';

	/**
	 * User meta prefix for a migrated enrollment pair.
	 *
	 * Full key: `_tlmt_lif_enroll_user_{course_id}`.
	 *
	 * @since 2.6.0
	 *
	 * @var string
	 */
	const USER_META_PREFIX = '_tlmt_lif_enroll_user_';

	/**
	 * Option key for total pending pairs at migration start.
	 *
	 * @since 2.6.0
	 *
	 * @var string
	 */
	const TOTAL_OPT = '_tlmt_lif_enrollments_migration_total';

	/**
	 * Default students processed per AJAX request.
	 *
	 * @since 2.6.0
	 *
	 * @var int
	 */
	const BATCH_SIZE = 50;

	/**
	 * Migrate a batch of LifterLMS enrollments, completions, and lesson progress.
	 *
	 * @since 2.6.0
	 *
	 * @param int  $batch_size     Max student–course pairs this request.
	 * @param bool $is_first_batch Whether this is the first AJAX batch.
	 *
	 * @return array{has_more: bool, migrated: int, total: int, remaining: int, batch_size: int}|false
	 */
	public function migrate_batch( int $batch_size, bool $is_first_batch ) {
		if ( $batch_size < 1 ) {
			$batch_size = self::BATCH_SIZE;
		}

		if ( ! $this->has_migrated_tutor_courses() ) {
			ErrorHandler::set_error(
				ContentTypes::ENROLLMENTS,
				__( 'No Tutor courses found. Complete course migration before migrating enrollments.', 'tutor-lms-migration-tool' )
			);
			return false;
		}

		if ( $is_first_batch ) {
			delete_option( self::TOTAL_OPT );
			delete_option( '_tutor_migrated_items_count' );
			$this->mark_courses_without_enrollment_activity();
		}

		$remaining_total = $this->count_pending();
		if ( $is_first_batch ) {
			update_option( self::TOTAL_OPT, $remaining_total, false );
		}

		$total = (int) get_option( self::TOTAL_OPT, $remaining_total );

		if ( 0 === $remaining_total ) {
			$this->mark_courses_without_enrollment_activity();
			delete_option( self::TOTAL_OPT );
			return array(
				'has_more'   => false,
				'migrated'   => $total,
				'total'      => $total,
				'remaining'  => 0,
				'batch_size' => $batch_size,
			);
		}

		try {
			$progress = StudentProgressFactory::create( MigrationTypes::LIF_TO_TUTOR );
		} catch ( \Throwable $th ) {
			ErrorHandler::set_error(
				ContentTypes::ENROLLMENTS,
				__( 'Error creating student progress migration object.', 'tutor-lms-migration-tool' )
			);
			return false;
		}

		$students        = $this->get_pending_batch( $batch_size );
		$item_i          = (int) get_option( '_tutor_migrated_items_count' );
		$touched_courses = array();

		foreach ( $students as $student ) {
			++$item_i;
			$course_id = (int) $student->course_id;
			$user_id   = (int) $student->user_id;

			try {
				$this->migrate_user_course( $course_id, $user_id );
				$progress->migrate( $course_id, $user_id );
				update_user_meta( $user_id, $this->user_meta_key( $course_id ), 1 );
				update_option( '_tutor_migrated_items_count', $item_i );
				$touched_courses[ $course_id ] = true;
			} catch ( \Throwable $th ) {
				ErrorHandler::set_error(
					ContentTypes::ENROLLMENTS,
					sprintf(
						/* translators: 1: course ID, 2: user ID */
						__( 'Failed to migrate enrollment for course %1$d, user %2$d.', 'tutor-lms-migration-tool' ),
						$course_id,
						$user_id
					) . ' ' . $th->getMessage()
				);
				throw $th;
			}
		}

		foreach ( array_keys( $touched_courses ) as $course_id ) {
			if ( 0 === $this->count_pending_for_course( (int) $course_id ) ) {
				update_post_meta( (int) $course_id, self::MIGRATED_META, 1 );
			}
		}

		$this->mark_courses_without_enrollment_activity();

		$remaining_after = $this->count_pending();
		$has_more        = $remaining_after > 0;
		$migrated_count  = max( 0, $total - $remaining_after );

		if ( ! $has_more ) {
			delete_option( self::TOTAL_OPT );
		}

		return array(
			'has_more'   => $has_more,
			'migrated'   => $migrated_count,
			'total'      => $total,
			'remaining'  => $remaining_after,
			'batch_size' => $batch_size,
		);
	}

	/**
	 * Migrate enrollment + course completion for one student–course pair.
	 *
	 * @since 2.6.0
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID.
	 *
	 * @return void
	 */
	public function migrate_user_course( int $course_id, int $user_id ) {
		global $wpdb;

		if ( $course_id < 1 || $user_id < 1 ) {
			return;
		}

		$table = $this->get_user_postmeta_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$enrollment_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE post_id = %d AND user_id = %d AND meta_key = '_status' AND meta_value = 'enrolled'
				ORDER BY updated_date DESC
				LIMIT 1",
				$course_id,
				$user_id
			)
		);

		$completion_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE post_id = %d AND user_id = %d AND meta_key = '_is_complete' AND meta_value = 'yes'
				ORDER BY updated_date DESC
				LIMIT 1",
				$course_id,
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $completion_row && ! tutils()->is_enrolled( $course_id, $user_id ) ) {
			$this->insert_course_completion( $course_id, $user_id );
		}

		if ( ! $enrollment_row || tutils()->is_enrolled( $course_id, $user_id ) ) {
			return;
		}

		$order_time = strtotime( (string) $enrollment_row->updated_date );
		if ( $order_time <= 0 ) {
			$order_time = tutor_time();
		}

		$title = __( 'Course Enrolled', 'tutor' ) . ' &ndash; ' . date_i18n( get_option( 'date_format' ), $order_time ) . ' @ ' . date_i18n( get_option( 'time_format' ), $order_time );

		$enrollment_id = wp_insert_post(
			array(
				'post_type'   => 'tutor_enrolled',
				'post_title'  => $title,
				'post_status' => 'completed',
				'post_author' => $user_id,
				'post_parent' => $course_id,
			)
		);

		if ( ! $enrollment_id ) {
			return;
		}

		update_user_meta( $user_id, '_is_tutor_student', $order_time );

		if ( ! class_exists( 'LLMS_Student' ) ) {
			return;
		}

		$student  = new \LLMS_Student( $user_id );
		$progress = $student->get_progress( $course_id, 'course' );
		if ( 100 == $progress ) {
			$this->insert_course_completion( $course_id, $user_id, (string) $progress );
		}
	}

	/**
	 * Insert a Tutor course completion comment.
	 *
	 * @since 2.6.0
	 *
	 * @param int    $course_id Course ID.
	 * @param int    $user_id   User ID.
	 * @param string $content   Optional comment content/hash.
	 *
	 * @return void
	 */
	private function insert_course_completion( int $course_id, int $user_id, string $content = '' ) {
		global $wpdb;

		if ( '' === $content ) {
			$date = gmdate( 'Y-m-d H:i:s', tutor_time() );
			do {
				$hash     = substr( md5( wp_generate_password( 32 ) . $date . $course_id . $user_id ), 0, 16 );
				$has_hash = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(comment_ID) FROM {$wpdb->comments}
						WHERE comment_agent = 'TutorLMSPlugin' AND comment_type = 'course_completed' AND comment_content = %s",
						$hash
					)
				);
			} while ( $has_hash > 0 );
			$content = $hash;
		}

		wp_insert_comment(
			array(
				'comment_type'     => 'course_completed',
				'comment_agent'    => 'TutorLMSPlugin',
				'comment_approved' => 'approved',
				'comment_content'  => $content,
				'user_id'          => $user_id,
				'comment_author'   => $user_id,
				'comment_post_ID'  => $course_id,
			)
		);
	}

	/**
	 * User meta key for a migrated enrollment pair.
	 *
	 * @since 2.6.0
	 *
	 * @param int $course_id Course ID.
	 *
	 * @return string
	 */
	private function user_meta_key( int $course_id ): string {
		return self::USER_META_PREFIX . $course_id;
	}

	/**
	 * LifterLMS user postmeta table name.
	 *
	 * @since 2.6.0
	 *
	 * @return string
	 */
	private function get_user_postmeta_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'lifterlms_user_postmeta';
	}

	/**
	 * Whether any Tutor courses exist (post-course-migration prerequisite).
	 *
	 * @since 2.6.0
	 *
	 * @return bool
	 */
	private function has_migrated_tutor_courses(): bool {
		global $wpdb;

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_was_lif_course'
				WHERE p.post_type = %s
					AND p.post_status IN ('publish', 'draft', 'private')",
				tutor()->course_post_type
			)
		);

		return $count > 0;
	}

	/**
	 * Mark former Lifter courses with no enrollment activity as done.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	private function mark_courses_without_enrollment_activity() {
		global $wpdb;

		$table = $this->get_user_postmeta_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$course_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} was
					ON p.ID = was.post_id AND was.meta_key = '_was_lif_course'
				LEFT JOIN {$wpdb->postmeta} pm
					ON p.ID = pm.post_id AND pm.meta_key = %s
				LEFT JOIN {$table} lifuer
					ON lifuer.post_id = p.ID
					AND lifuer.user_id > 0
					AND (
						( lifuer.meta_key = '_status' AND lifuer.meta_value = 'enrolled' )
						OR ( lifuer.meta_key = '_is_complete' AND lifuer.meta_value = 'yes' )
					)
				WHERE p.post_type = %s
					AND p.post_status IN ('publish', 'draft', 'private')
					AND pm.meta_id IS NULL
					AND lifuer.user_id IS NULL",
				self::MIGRATED_META,
				tutor()->course_post_type
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $course_ids ? $course_ids : array() as $course_id ) {
			update_post_meta( (int) $course_id, self::MIGRATED_META, 1 );
		}
	}

	/**
	 * Count pending student–course enrollment pairs.
	 *
	 * @since 2.6.0
	 *
	 * @return int
	 */
	public function count_pending(): int {
		global $wpdb;

		$table = $this->get_user_postmeta_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (
					SELECT DISTINCT lifuer.post_id, lifuer.user_id
					FROM {$table} lifuer
					INNER JOIN {$wpdb->posts} p ON p.ID = lifuer.post_id
					INNER JOIN {$wpdb->postmeta} was
						ON p.ID = was.post_id AND was.meta_key = '_was_lif_course'
					LEFT JOIN {$wpdb->postmeta} pm
						ON p.ID = pm.post_id AND pm.meta_key = %s
					LEFT JOIN {$wpdb->usermeta} um
						ON um.user_id = lifuer.user_id
						AND um.meta_key = CONCAT(%s, lifuer.post_id)
					WHERE lifuer.user_id > 0
						AND (
							( lifuer.meta_key = '_status' AND lifuer.meta_value = 'enrolled' )
							OR ( lifuer.meta_key = '_is_complete' AND lifuer.meta_value = 'yes' )
						)
						AND p.post_type = %s
						AND p.post_status IN ('publish', 'draft', 'private')
						AND pm.meta_id IS NULL
						AND um.umeta_id IS NULL
				) pending_enrollments",
				self::MIGRATED_META,
				self::USER_META_PREFIX,
				tutor()->course_post_type
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Count pending students for a single course.
	 *
	 * @since 2.6.0
	 *
	 * @param int $course_id Course ID.
	 *
	 * @return int
	 */
	private function count_pending_for_course( int $course_id ): int {
		global $wpdb;

		$table = $this->get_user_postmeta_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (
					SELECT DISTINCT lifuer.user_id
					FROM {$table} lifuer
					LEFT JOIN {$wpdb->usermeta} um
						ON um.user_id = lifuer.user_id
						AND um.meta_key = %s
					WHERE lifuer.post_id = %d
						AND lifuer.user_id > 0
						AND (
							( lifuer.meta_key = '_status' AND lifuer.meta_value = 'enrolled' )
							OR ( lifuer.meta_key = '_is_complete' AND lifuer.meta_value = 'yes' )
						)
						AND um.umeta_id IS NULL
				) pending_course_enrollments",
				$this->user_meta_key( $course_id ),
				$course_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Fetch a batch of pending student–course pairs.
	 *
	 * @since 2.6.0
	 *
	 * @param int $limit Batch size.
	 *
	 * @return array<int, object{course_id: int, user_id: int}>
	 */
	private function get_pending_batch( int $limit ): array {
		global $wpdb;

		$table = $this->get_user_postmeta_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT lifuer.post_id AS course_id, lifuer.user_id
				FROM {$table} lifuer
				INNER JOIN {$wpdb->posts} p ON p.ID = lifuer.post_id
				INNER JOIN {$wpdb->postmeta} was
					ON p.ID = was.post_id AND was.meta_key = '_was_lif_course'
				LEFT JOIN {$wpdb->postmeta} pm
					ON p.ID = pm.post_id AND pm.meta_key = %s
				LEFT JOIN {$wpdb->usermeta} um
					ON um.user_id = lifuer.user_id
					AND um.meta_key = CONCAT(%s, lifuer.post_id)
				WHERE lifuer.user_id > 0
					AND (
						( lifuer.meta_key = '_status' AND lifuer.meta_value = 'enrolled' )
						OR ( lifuer.meta_key = '_is_complete' AND lifuer.meta_value = 'yes' )
					)
					AND p.post_type = %s
					AND p.post_status IN ('publish', 'draft', 'private')
					AND pm.meta_id IS NULL
					AND um.umeta_id IS NULL
				ORDER BY lifuer.post_id ASC, lifuer.user_id ASC
				LIMIT %d",
				self::MIGRATED_META,
				self::USER_META_PREFIX,
				tutor()->course_post_type,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $results ) ? $results : array();
	}
}
