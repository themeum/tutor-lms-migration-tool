<?php
/**
 * Migration consent block for the confirm modal.
 *
 * Expected variables:
 * - string   $source_lms      Display name of the source LMS (e.g. LearnPress).
 * - string[] $deletion_items  User-facing list of data that will be transferred.
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
		<span class="migration-deletion-consent-icon" aria-hidden="true">
			<i class="tutor-icon-warning"></i>
		</span>
		<div class="migration-deletion-consent-copy">
			<p class="migration-deletion-consent-intro tutor-fs-6 tutor-fw-medium tutor-color-warning">
				<?php
				printf(
					/* translators: %s: Source LMS name */
					esc_html__( 'Once migration is complete, the following data will be transferred to Tutor LMS and will no longer be accessible in %s:', 'tutor-lms-migration-tool' ),
					esc_html( $source_lms )
				);
				?>
			</p>
			<ul class="migration-deletion-consent-list tutor-fs-6">
				<?php foreach ( $deletion_items as $item ) : ?>
					<li><?php echo esc_html( $item ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p class="migration-deletion-consent-note tutor-fs-6">
				<?php esc_html_e( 'We highly recommend backing up your site before proceeding.', 'tutor-lms-migration-tool' ); ?>
			</p>
		</div>
	</div>
	<label class="migration-deletion-consent-check tutor-fs-6 tutor-color-black">
		<span class="migration-deletion-consent-check-control">
			<input type="checkbox" class="migration-consent-checkbox tutor-form-check-input" value="1" />
		</span>
		<span class="migration-deletion-consent-check-label">
			<?php esc_html_e( "I have backed up my site and I'm ready to proceed with migration.", 'tutor-lms-migration-tool' ); ?>
		</span>
	</label>
</div>
