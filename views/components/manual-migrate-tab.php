<?php
/**
 * Manual migrate tab
 *
 * @package TutorLMSMigrationTool\Views
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

?>
<div id="tutor-manual-migrate-tab" class="tutor-tab-item">
	<div class="tutor-tab-item-wrap tutor-p-48">
		<div class="tutor-migration-upload-area tutor-migration-drag-drop-zone flex-center tutor-px-48 tutor-py-68">
			<div class="tutor-migration-upload-circle tutor-mb-16 flex-center">
				<span class="tutor-fs-3 tutor-fw-medium tutor-color-primary tutor-icon-import"></span>
			</div>
			<form id="tutor-manual-migrate-form" method="post" enctype="multipart/form-data">
				<?php tutor_nonce_field(); ?>
				<input type="hidden" name="tutor_action" value="tutor_import_from_ld">
				<div id="tutor-migration-browse-file-link" class="tutor-fs-5 tutor-fw-medium"> 
					<div class="tutor-color-black"> <?php esc_html_e( 'Drag & Drop XML file here', 'tutor-lms-migration-tool' ); ?> </div>
					or <a href="" class="tutor-color-primary"> <?php esc_html_e( 'Browse File', 'tutor-lms-migration-tool' ); ?> </a>
				</div>
				<input id="tutor-migration-browse-file" name="tutor_import_file" hidden type="file" accept=".xml" required>
				<span class="file-info tutor-fw-medium backup-now-subtile tutor-fs-6"></span>
			</form>
		</div>
	</div>
			
	<div class="tutor-backup-area tutor-px-48 tutor-py-36 tutor-border-top">
		<div class="tutor-row tutor-align-center">
			<div class="tutor-col-md-8 tutor-d-flex tutor-flex-wrap">
				<sapn class="backup-now-subtile tutor-fs-7"><?php esc_html_e( 'Please take a complete a backup for safety.', 'tutor-lms-migration-tool' ); ?></sapn>
				<form id="tutor_migration_export_form" method="post">
					<?php tutor_nonce_field(); ?>
					<input type="hidden" id="tutor_migration_vendor" name="tutor_migration_vendor" value="ld">
					<input type="hidden" name="tutor_action" value="tutor_ld_export_xml">
					<button <?php echo ! $items_count ? 'disabled' : ''; ?> type="submit" class="backup-now-btn tutor-fs-7 tutor-fw-medium tutor-color-black">
						<?php esc_html_e( 'Backup Now', 'tutor-lms-migration-tool' ); ?>
					</button>
				</form>
			</div>
			<div class="migrate-now-btn-wrapper tutor-col-md-4 tutor-d-flex tutor-justify-end">
				<button type="submit" id="manual-migrate-now-btn" class="tutor-btn tutor-btn-primary tutor-btn-lg" disabled>
					<?php esc_html_e( 'Migrate Now', 'tutor-lms-migration-tool' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
