<?php
/**
 * Migrate now section
 *
 * @package TutorLMSMigrationTool\Views
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

?>
<div class="tutor-row tutor-align-center">
	<div class="tutor-col-md-8 tutor-d-flex tutor-flex-wrap">
		<div class="backup-now-subtile tutor-fs-7 tutor-color-warning">
			<i class="tutor-icon-warning"></i>	
			<sapn><?php esc_html_e( 'We recommend that you take a database backup before proceeding.', 'tutor-lms-migration-tool' ); ?></sapn>
		</div>
		<form id="tutor_migration_export_form" method="post">
			<?php tutor_nonce_field(); ?>
			<input type="hidden" name="tutor_action" value="tutor_ld_export_xml">
			<input type="hidden" id="tutor_migration_vendor" name="tutor_migration_vendor" value="ld">
		</form>
	</div>
	<div class="migrate-now-btn-wrapper tutor-col-md-4 tutor-d-flex tutor-justify-end">
		<span id="total_items_migrate_counts" class="tutor-d-none" data-count="<?php echo esc_attr( $items_count ); ?>"> </span>
		<button type="submit" class="migrate-now-btn tutor-btn tutor-btn-primary tutor-btn-lg" <?php echo ! $items_count ? 'disabled' : ''; ?> >
			<?php esc_html_e( 'Migrate Now', 'tutor-lms-migration-tool' ); ?>
		</button>
	</div>
</div>
