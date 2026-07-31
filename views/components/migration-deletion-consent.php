<?php
/**
 * Migration deletion consent block for the confirm modal.
 *
 * Expected variables:
 * - string   $source_lms      Display name of the source LMS (e.g. LearnPress).
 * - string[] $deletion_items  User-facing list of data that will be permanently deleted.
 *
 * @package TutorLMSMigrationTool\Views
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $deletion_items ) || ! is_array( $deletion_items ) ) {
	return;
}

$source_lms = isset( $source_lms ) ? (string) $source_lms : '';
?>
<div class="migration-deletion-consent tutor-text-left">
	<div class="migration-deletion-consent-body">
		<div class="migration-deletion-consent-warning tutor-fs-6 tutor-fw-medium tutor-color-warning">
			<i class="tutor-icon-warning" aria-hidden="true"></i>
			<span>
				<?php
				printf(
					/* translators: %s: Source LMS name */
					esc_html__( 'Once migration finishes, the following data will no longer be available in %s and cannot be recovered without a backup:', 'tutor-lms-migration-tool' ),
					esc_html( $source_lms )
				);
				?>
			</span>
		</div>
		<ul class="migration-deletion-consent-list tutor-fs-6 tutor-color-black">
			<?php foreach ( $deletion_items as $item ) : ?>
				<li><?php echo esc_html( $item ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<label class="migration-deletion-consent-check tutor-d-flex tutor-align-start tutor-fs-6 tutor-color-black">
		<input type="checkbox" class="migration-consent-checkbox tutor-form-check-input" value="1" />
		<span>
			<?php esc_html_e( 'I understand this data will no longer be available in the source LMS, and I have taken a complete backup.', 'tutor-lms-migration-tool' ); ?>
		</span>
	</label>
</div>
