<?php
/**
 * Admin page view for 404 Errors.
 *
 * @package EoTools
 */

if (!defined('ABSPATH')) {
	exit;
}

global $wpdb;
$table_404 = $wpdb->prefix . 'eotools_404_logs';

// Handle form submission (saving a redirect)
if ( isset( $_POST['action'] ) && $_POST['action'] === 'eo_tools_save_redirect' && check_admin_referer( 'eo_tools_404_nonce' ) ) {
	$log_id = intval( $_POST['log_id'] );
	$redirect_to = sanitize_text_field( $_POST['redirect_to'] );
	$status = !empty($redirect_to) ? 'redirected' : 'pending';
	
	if ( isset( $_POST['ignore'] ) && $_POST['ignore'] == '1' ) {
		$status = 'ignored';
		$redirect_to = '';
	} elseif ( isset( $_POST['unignore'] ) && $_POST['unignore'] == '1' ) {
		$status = 'pending';
		$redirect_to = '';
	}

	$wpdb->update(
		$table_404,
		array(
			'redirect_to' => $redirect_to,
			'status' => $status
		),
		array( 'id' => $log_id )
	);
	
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Redirection mise à jour.', 'eo-tools' ) . '</p></div>';
}

$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'pending';

// Fetch logs based on tab
if ( $current_tab === 'ignored' ) {
    $where = "WHERE status = 'ignored'";
} elseif ( $current_tab === 'redirected' ) {
    $where = "WHERE status = 'redirected'";
} else {
    $where = "WHERE status = 'pending'";
}

$query = "SELECT * FROM $table_404 $where ORDER BY last_accessed DESC";
$logs = $wpdb->get_results( $query );
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Erreurs 404 et Redirections', 'eo-tools' ); ?></h1>
	<hr class="wp-header-end">

	<h2 class="nav-tab-wrapper">
		<a href="?page=eo-tools-404-errors&tab=pending" class="nav-tab <?php echo $current_tab === 'pending' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'À traiter', 'eo-tools' ); ?></a>
		<a href="?page=eo-tools-404-errors&tab=redirected" class="nav-tab <?php echo $current_tab === 'redirected' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Redirigées', 'eo-tools' ); ?></a>
		<a href="?page=eo-tools-404-errors&tab=ignored" class="nav-tab <?php echo $current_tab === 'ignored' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Ignorées', 'eo-tools' ); ?></a>
	</h2>

	<div class="notice notice-info">
		<p><?php esc_html_e( 'Ce module permet de suivre les pages en erreur 404 et de configurer des redirections (301) pertinentes.', 'eo-tools' ); ?></p>
	</div>

	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th scope="col" class="manage-column column-source" style="width: 35%;"><?php esc_html_e( 'Source', 'eo-tools' ); ?></th>
				<th scope="col" class="manage-column column-actions" style="width: 45%;"><?php esc_html_e( 'Actions / Redirection', 'eo-tools' ); ?></th>
				<th scope="col" class="manage-column column-views" style="width: 10%;"><?php esc_html_e( 'Vues', 'eo-tools' ); ?></th>
				<th scope="col" class="manage-column column-last-access" style="width: 10%;"><?php esc_html_e( 'Dernier accès', 'eo-tools' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
			<tr class="no-items">
				<td class="colspanchange" colspan="4"><?php esc_html_e( 'Aucune erreur 404 trouvée pour le moment.', 'eo-tools' ); ?></td>
			</tr>
			<?php else : ?>
				<?php foreach ( $logs as $log ) : ?>
				<tr>
					<td>
						<strong><a href="<?php echo esc_url( home_url( $log->url ) ); ?>" target="_blank"><?php echo esc_html( home_url( $log->url ) ); ?></a></strong>
						<br>
						<span style="color: #888; font-size: 12px;">Statut : <?php echo esc_html( $log->status ); ?></span>
					</td>
					<td>
						<form method="post" action="" style="display: flex; gap: 10px; align-items: center;">
							<?php wp_nonce_field( 'eo_tools_404_nonce' ); ?>
							<input type="hidden" name="action" value="eo_tools_save_redirect">
							<input type="hidden" name="log_id" value="<?php echo intval( $log->id ); ?>">
							
							<input type="text" name="redirect_to" value="<?php echo esc_attr( $log->redirect_to ); ?>" placeholder="Ex: /nouvelle-page/" style="width: 100%; max-width: 250px;">
							
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Rediriger', 'eo-tools' ); ?></button>
							<?php if ( $log->status === 'ignored' ) : ?>
								<button type="submit" name="unignore" value="1" class="button"><?php esc_html_e( 'Rétablir', 'eo-tools' ); ?></button>
							<?php else : ?>
								<button type="submit" name="ignore" value="1" class="button"><?php esc_html_e( 'Ignorer', 'eo-tools' ); ?></button>
							<?php endif; ?>
						</form>
					</td>
					<td><?php echo intval( $log->hits ); ?></td>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->last_accessed ) ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<th scope="col" class="manage-column column-source"><?php esc_html_e( 'Source', 'eo-tools' ); ?></th>
				<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions / Redirection', 'eo-tools' ); ?></th>
				<th scope="col" class="manage-column column-views"><?php esc_html_e( 'Vues', 'eo-tools' ); ?></th>
				<th scope="col" class="manage-column column-last-access"><?php esc_html_e( 'Dernier accès', 'eo-tools' ); ?></th>
			</tr>
		</tfoot>
	</table>
</div>
