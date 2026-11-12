<?php
/**
 * LearnPress enrollment, completion, and progress migration.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.5.0
 */

namespace Themeum\TutorLMSMigrationTool\LPMigration;

use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\ErrorHandler;
use Themeum\TutorLMSMigrationTool\Factories\StudentProgressFactory;
use Themeum\TutorLMSMigrationTool\MigrationTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Batched LearnPress → Tutor enrollment migrator.
 *
 * Runs after course structure migration. Each request processes a limited
 * number of student–course pairs (not whole courses) so large sites stay
 * under PHP/proxy timeouts.
 *
 * @since 2.5.0
 */
class Enrollments {

	/**
	 * Post meta marking a course whose enrollments/progress were migrated.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	const MIGRATED_META = '_tlmt_lp_enrollment_migrated';

	/**
	 * User meta prefix for a migrated enrollment/progress pair.
	 *
	 * Full key: `_tlmt_lp_enroll_user_{course_id}`.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	const USER_META_PREFIX = '_tlmt_lp_enroll_user_';

	/**
	 * Option key for total pending pairs at migration start.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	const TOTAL_OPT = '_tlmt_lp_enrollments_migration_total';

	/**
	 * Default students processed per AJAX request.
	 *
	 * @since 2.5.0
	 *
	 * @var int
	 */
	const BATCH_SIZE = 50;

	/**
	 * Migrate a batch of LearnPress enrollments + completions + progress.
	 *
	 * @since 2.5.0
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
			$progress = StudentProgressFactory::create( MigrationTypes::LP_TO_TUTOR );
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
	 * @since 2.5.0
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

		$table = $wpdb->prefix . 'learnpress_user_items';

		// Prefer the latest order-linked enrollment row (same priority as legacy path).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$enrollment_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ui.*, lp_order.ID AS order_id, lp_order.post_date_gmt AS order_time_gmt
				FROM {$table} ui
				LEFT JOIN {$wpdb->posts} lp_order ON ui.ref_id = lp_order.ID AND ui.ref_type = 'lp_order'
				WHERE ui.item_id = %d
					AND ui.user_id = %d
					AND ui.item_type = 'lp_course'
				ORDER BY
					CASE WHEN ui.ref_type = 'lp_order' THEN 0 ELSE 1 END ASC,
					ui.user_item_id DESC
				LIMIT 1",
				$course_id,
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $enrollment_row ) {
			$tutor_status = $this->map_lp_enrollment_status( (string) ( $enrollment_row->status ?? '' ) );
			if ( '' !== $tutor_status && ! tutils()->is_enrolled( $course_id, $user_id, false ) ) {
				$enroll_ts = $this->lp_mysql_gmt_to_timestamp( $enrollment_row->start_time ?? '' );
				if ( $enroll_ts <= 0 ) {
					$enroll_ts = $this->lp_mysql_gmt_to_timestamp( $enrollment_row->order_time_gmt ?? '' );
				}
				$enroll_dates = $this->lp_migrate_datetimes( $enroll_ts );

				$title = __( 'Course Enrolled', 'tutor' ) . ' &ndash; ' . date_i18n( get_option( 'date_format' ), $enroll_dates['unix'] ) . ' @ ' . date_i18n( get_option( 'time_format' ), $enroll_dates['unix'] );

				$enrollment_id = wp_insert_post(
					array(
						'post_type'     => 'tutor_enrolled',
						'post_title'    => $title,
						'post_status'   => $tutor_status,
						'post_author'   => $user_id,
						'post_parent'   => $course_id,
						'post_date'     => $enroll_dates['local'],
						'post_date_gmt' => $enroll_dates['gmt'],
					)
				);

				if ( $enrollment_id ) {
					update_user_meta( $user_id, '_is_tutor_student', $enroll_dates['unix'] );

					$lp_order_id = (int) ( $enrollment_row->order_id ?? 0 );
					if ( $lp_order_id > 0 ) {
						update_post_meta( $enrollment_id, '_tutor_enrolled_by_order_id', $lp_order_id );
					}
				}
			}
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$completion_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT end_time, start_time
				FROM {$table}
				WHERE item_id = %d
					AND user_id = %d
					AND item_type = 'lp_course'
					AND graduation = 'passed'
				ORDER BY user_item_id DESC
				LIMIT 1",
				$course_id,
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $completion_row || tutils()->is_completed_course( $course_id, $user_id, false ) ) {
			return;
		}

		$complete_ts = $this->lp_mysql_gmt_to_timestamp( $completion_row->end_time ?? '' );
		if ( $complete_ts <= 0 ) {
			$complete_ts = $this->lp_mysql_gmt_to_timestamp( $completion_row->start_time ?? '' );
		}
		$complete_dates = $this->lp_migrate_datetimes( $complete_ts );

		$hash_seed = $complete_dates['gmt'] . $course_id . $user_id;
		do {
			$hash     = substr( md5( wp_generate_password( 32 ) . $hash_seed ), 0, 16 );
			$has_hash = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(comment_ID) FROM {$wpdb->comments}
					WHERE comment_agent = 'TutorLMSPlugin' AND comment_type = 'course_completed' AND comment_content = %s",
					$hash
				)
			);
		} while ( $has_hash > 0 );

		wp_insert_comment(
			array(
				'comment_type'     => 'course_completed',
				'comment_agent'    => 'TutorLMSPlugin',
				'comment_approved' => 'approved',
				'comment_content'  => $hash,
				'user_id'          => $user_id,
				'comment_author'   => $user_id,
				'comment_post_ID'  => $course_id,
				'comment_date'     => $complete_dates['local'],
				'comment_date_gmt' => $complete_dates['gmt'],
			)
		);
	}

	/**
	 * User meta key for a migrated enrollment/progress pair.
	 *
	 * @since 2.5.0
	 *
	 * @param int $course_id Course ID.
	 *
	 * @return string
	 */
	private function user_meta_key( int $course_id ): string {
		return self::USER_META_PREFIX . $course_id;
	}

	/**
	 * Whether any Tutor courses exist (post-course-migration prerequisite).
	 *
	 * @since 2.5.0
	 *
	 * @return bool
	 */
	private function has_migrated_tutor_courses(): bool {
		global $wpdb;

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_was_lp_course'
				WHERE p.post_type = %s
					AND p.post_status IN ('publish', 'draft', 'private')",
				tutor()->course_post_type
			)
		);

		return $count > 0;
	}

	/**
	 * Mark former LP courses with no user_items activity as enrollment-done.
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	private function mark_courses_without_enrollment_activity() {
		global $wpdb;

		$table = $wpdb->prefix . 'learnpress_user_items';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$course_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} was
					ON p.ID = was.post_id AND was.meta_key = '_was_lp_course'
				LEFT JOIN {$wpdb->postmeta} pm
					ON p.ID = pm.post_id AND pm.meta_key = %s
				LEFT JOIN {$table} ui
					ON ui.item_id = p.ID
					AND ui.item_type = 'lp_course'
					AND ui.user_id > 0
				WHERE p.post_type = %s
					AND p.post_status IN ('publish', 'draft', 'private')
					AND pm.meta_id IS NULL
					AND ui.user_item_id IS NULL",
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
	 * @since 2.5.0
	 *
	 * @return int
	 */
	public function count_pending(): int {
		global $wpdb;

		$table = $wpdb->prefix . 'learnpress_user_items';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (
					SELECT DISTINCT ui.item_id, ui.user_id
					FROM {$table} ui
					INNER JOIN {$wpdb->posts} p ON p.ID = ui.item_id
					INNER JOIN {$wpdb->postmeta} was
						ON p.ID = was.post_id AND was.meta_key = '_was_lp_course'
					LEFT JOIN {$wpdb->postmeta} pm
						ON p.ID = pm.post_id AND pm.meta_key = %s
					LEFT JOIN {$wpdb->usermeta} um
						ON um.user_id = ui.user_id
						AND um.meta_key = CONCAT(%s, ui.item_id)
					WHERE ui.item_type = 'lp_course'
						AND ui.user_id > 0
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
	 * @since 2.5.0
	 *
	 * @param int $course_id Course ID.
	 *
	 * @return int
	 */
	private function count_pending_for_course( int $course_id ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'learnpress_user_items';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (
					SELECT DISTINCT ui.user_id
					FROM {$table} ui
					LEFT JOIN {$wpdb->usermeta} um
						ON um.user_id = ui.user_id
						AND um.meta_key = %s
					WHERE ui.item_id = %d
						AND ui.item_type = 'lp_course'
						AND ui.user_id > 0
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
	 * @since 2.5.0
	 *
	 * @param int $limit Batch size.
	 *
	 * @return array<int, object{course_id: int, user_id: int}>
	 */
	private function get_pending_batch( int $limit ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'learnpress_user_items';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT ui.item_id AS course_id, ui.user_id
				FROM {$table} ui
				INNER JOIN {$wpdb->posts} p ON p.ID = ui.item_id
				INNER JOIN {$wpdb->postmeta} was
					ON p.ID = was.post_id AND was.meta_key = '_was_lp_course'
				LEFT JOIN {$wpdb->postmeta} pm
					ON p.ID = pm.post_id AND pm.meta_key = %s
				LEFT JOIN {$wpdb->usermeta} um
					ON um.user_id = ui.user_id
					AND um.meta_key = CONCAT(%s, ui.item_id)
				WHERE ui.item_type = 'lp_course'
					AND ui.user_id > 0
					AND p.post_type = %s
					AND p.post_status IN ('publish', 'draft', 'private')
					AND pm.meta_id IS NULL
					AND um.umeta_id IS NULL
				ORDER BY ui.item_id ASC, ui.user_id ASC
				LIMIT %d",
				self::MIGRATED_META,
				self::USER_META_PREFIX,
				tutor()->course_post_type,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $results ? $results : array();
	}

	/**
	 * Map LearnPress user-item status to a Tutor enrollment post_status.
	 *
	 * @since 2.5.0
	 *
	 * @param string $lp_status LearnPress status.
	 *
	 * @return string Tutor status, or empty to skip.
	 */
	private function map_lp_enrollment_status( string $lp_status ): string {
		switch ( $lp_status ) {
			case 'cancel':
				return 'cancel';
			case 'purchased':
				return 'pending';
			case 'enrolled':
			case 'finished':
			case 'completed':
				return 'completed';
			default:
				return '';
		}
	}

	/**
	 * Convert a LearnPress GMT MySQL datetime to a Unix timestamp.
	 *
	 * @since 2.5.0
	 *
	 * @param string $mysql_datetime GMT datetime string.
	 *
	 * @return int
	 */
	private function lp_mysql_gmt_to_timestamp( $mysql_datetime ): int {
		if ( empty( $mysql_datetime ) || '0000-00-00 00:00:00' === $mysql_datetime ) {
			return 0;
		}

		$dt = \DateTime::createFromFormat( 'Y-m-d H:i:s', (string) $mysql_datetime, new \DateTimeZone( 'UTC' ) );
		if ( $dt instanceof \DateTime ) {
			return (int) $dt->getTimestamp();
		}

		$timestamp = strtotime( (string) $mysql_datetime . ' UTC' );
		return ( false !== $timestamp && $timestamp > 0 ) ? (int) $timestamp : 0;
	}

	/**
	 * Convert a Unix timestamp to WP local + GMT MySQL datetimes.
	 *
	 * @since 2.5.0
	 *
	 * @param int $timestamp Unix timestamp (0 falls back to now).
	 *
	 * @return array{local: string, gmt: string, unix: int}
	 */
	private function lp_migrate_datetimes( int $timestamp ): array {
		if ( $timestamp <= 0 ) {
			$timestamp = time();
		}

		$gmt   = gmdate( 'Y-m-d H:i:s', $timestamp );
		$local = get_date_from_gmt( $gmt );

		return array(
			'local' => $local,
			'gmt'   => $gmt,
			'unix'  => $timestamp,
		);
	}
}
