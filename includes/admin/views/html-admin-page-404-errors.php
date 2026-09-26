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
$table_redirections = $wpdb->prefix . 'eotools_redirections';

// Handle form submission (saving a redirect)
if ( isset( $_POST['action'] ) && $_POST['action'] === 'eo_tools_save_redirect' && check_admin_referer( 'eo_tools_404_nonce' ) ) {
	$source_url = esc_url_raw( wp_unslash( $_POST['source_url'] ) );
	$redirect_to = sanitize_text_field( wp_unslash( $_POST['redirect_to'] ) );
	
	$status = !empty($redirect_to) ? 'redirected' : 'pending';
	
	if ( isset( $_POST['ignore'] ) && $_POST['ignore'] == '1' ) {
		$status = 'ignored';
		$redirect_to = '';
	} elseif ( isset( $_POST['unignore'] ) && $_POST['unignore'] == '1' ) {
		$status = 'pending';
		$redirect_to = '';
	}

	if ( $status === 'pending' ) {
		$wpdb->delete( $table_redirections, array( 'url' => $source_url ) );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Règle supprimée, l\'URL est de nouveau à traiter.', 'eo-tools' ) . '</p></div>';
	} else {
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO $table_redirections (url, redirect_to, status, created_at) VALUES (%s, %s, %s, %s) ON DUPLICATE KEY UPDATE redirect_to = VALUES(redirect_to), status = VALUES(status)",
			$source_url, $redirect_to, $status, current_time('mysql')
		) );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Règle mise à jour.', 'eo-tools' ) . '</p></div>';
	}
}

$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'pending';
$groupby = isset( $_GET['groupby'] ) ? sanitize_text_field( $_GET['groupby'] ) : '';

// Fetch logs based on tab
$logs = array();

if ( $current_tab === 'ignored' || $current_tab === 'redirected' ) {
	$status_filter = ( $current_tab === 'ignored' ) ? 'ignored' : 'redirected';
	$query = $wpdb->prepare( "SELECT * FROM $table_redirections WHERE status = %s ORDER BY created_at DESC", $status_filter );
	$logs = $wpdb->get_results( $query );
} else {
	// À traiter (Pending) - from 404 logs, excluding those already in redirections
	$select_clause = "l.url, l.ip, l.user_agent, l.method, l.http_code, MAX(l.created_at) as last_date, COUNT(*) as hits";
	
	if ( $groupby === 'url' ) {
		$group_clause = "GROUP BY l.url";
	} elseif ( $groupby === 'ip' ) {
		$group_clause = "GROUP BY l.ip";
	} elseif ( $groupby === 'user_agent' ) {
		$group_clause = "GROUP BY l.user_agent";
	} else {
		// No grouping
		$select_clause = "l.url, l.ip, l.user_agent, l.method, l.http_code, l.created_at as last_date, 1 as hits";
		$group_clause = "";
	}

	$methods = isset($_GET['methods']) ? array_map('sanitize_text_field', (array)$_GET['methods']) : array();
	$codes = isset($_GET['codes']) ? array_map('intval', (array)$_GET['codes']) : array();

	$where_clauses = array("r.id IS NULL");
	if (!empty($methods)) {
		$methods_in = "'" . implode("','", array_map('esc_sql', $methods)) . "'";
		$where_clauses[] = "l.method IN ($methods_in)";
	}
	if (!empty($codes)) {
		$codes_in = implode(",", array_map('intval', $codes));
		$where_clauses[] = "l.http_code IN ($codes_in)";
	}
	$where_sql = implode(" AND ", $where_clauses);

	$query = "SELECT $select_clause 
			  FROM $table_404 l 
			  LEFT JOIN $table_redirections r ON l.url = r.url 
			  WHERE $where_sql 
			  $group_clause 
			  ORDER BY last_date DESC";
			  
			  
	$logs = $wpdb->get_results( $query );
}
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

	<?php if ( $current_tab === 'pending' ) : ?>
	<form method="get" action="">
		<input type="hidden" name="page" value="eo-tools-404-errors">
		<input type="hidden" name="tab" value="pending">
		<div class="tablenav top">
			<div class="alignleft actions">
				<select name="groupby">
					<option value="" <?php selected( $groupby, '' ); ?>><?php esc_html_e( 'Aucun regroupement', 'eo-tools' ); ?></option>
					<option value="url" <?php selected( $groupby, 'url' ); ?>><?php esc_html_e( 'Grouper par URL', 'eo-tools' ); ?></option>
					<option value="user_agent" <?php selected( $groupby, 'user_agent' ); ?>><?php esc_html_e( 'Grouper par agent utilisateur', 'eo-tools' ); ?></option>
					<option value="ip" <?php selected( $groupby, 'ip' ); ?>><?php esc_html_e( 'Grouper par IP', 'eo-tools' ); ?></option>
				</select>
				<input type="submit" class="button" value="<?php esc_attr_e( 'Filtrer', 'eo-tools' ); ?>">
				<button type="button" class="button" onclick="document.getElementById('eo-tools-filters').toggleAttribute('hidden')"><?php esc_html_e( 'Filtres', 'eo-tools' ); ?> <span>&#x25BC;</span></button>
				
				<div id="eo-tools-filters" hidden style="position:absolute; background:#fff; border:1px solid #ccc; padding:15px; margin-top:5px; box-shadow:0 3px 6px rgba(0,0,0,0.1); z-index:100; max-height: 400px; overflow-y: auto;">
					<h4 style="margin-top:0;">Méthode</h4>
					<label><input type="checkbox" name="methods[]" value="GET" <?php checked(in_array('GET', $methods)); ?>> GET</label><br>
					<label><input type="checkbox" name="methods[]" value="POST" <?php checked(in_array('POST', $methods)); ?>> POST</label><br>
					<label><input type="checkbox" name="methods[]" value="HEAD" <?php checked(in_array('HEAD', $methods)); ?>> HEAD</label><br>
					
					<h4>Code d'état HTTP</h4>
					<label><input type="checkbox" name="codes[]" value="400" <?php checked(in_array(400, $codes)); ?>> 400 - Bad Request (Mauvaise requête)</label><br>
					<label><input type="checkbox" name="codes[]" value="401" <?php checked(in_array(401, $codes)); ?>> 401 - Unauthorized (Non-autorisé)</label><br>
					<label><input type="checkbox" name="codes[]" value="403" <?php checked(in_array(403, $codes)); ?>> 403 - Forbidden (Interdit)</label><br>
					<label><input type="checkbox" name="codes[]" value="404" <?php checked(in_array(404, $codes)); ?>> 404 - Not Found (Introuvable)</label><br>
					<label><input type="checkbox" name="codes[]" value="410" <?php checked(in_array(410, $codes)); ?>> 410 - Gone (N'existera plus jamais)</label><br>
					<label><input type="checkbox" name="codes[]" value="418" <?php checked(in_array(418, $codes)); ?>> 418 - I'm a teapot (Je suis une théière)</label><br>
					<label><input type="checkbox" name="codes[]" value="451" <?php checked(in_array(451, $codes)); ?>> 451 - Unavailable For Legal Reasons</label><br>
				</div>
			</div>
		</div>
	</form>
	<?php endif; ?>

	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th scope="col" class="manage-column check-column"><input type="checkbox"></th>
				<th scope="col" class="manage-column column-date" style="width: 10%;"><?php esc_html_e( 'Date', 'eo-tools' ); ?></th>
				<th scope="col" class="manage-column column-source" style="width: 25%;"><?php esc_html_e( 'URL source', 'eo-tools' ); ?></th>
				<?php if ( $current_tab === 'pending' ) : ?>
					<th scope="col" class="manage-column column-user-agent" style="width: 20%;"><?php esc_html_e( 'Agent utilisateur', 'eo-tools' ); ?></th>
					<th scope="col" class="manage-column column-ip" style="width: 10%;"><?php esc_html_e( 'IP', 'eo-tools' ); ?></th>
					<th scope="col" class="manage-column column-type" style="width: 5%;"><?php esc_html_e( 'Type', 'eo-tools' ); ?></th>
					<?php if ( ! empty( $groupby ) ) : ?>
						<th scope="col" class="manage-column column-views" style="width: 5%;"><?php esc_html_e( 'Vues', 'eo-tools' ); ?></th>
					<?php endif; ?>
				<?php endif; ?>
				<th scope="col" class="manage-column column-actions" style="width: 30%;"><?php esc_html_e( 'Actions / Redirection', 'eo-tools' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
			<tr class="no-items">
				<td class="colspanchange" colspan="7"><?php esc_html_e( 'Aucune donnée trouvée.', 'eo-tools' ); ?></td>
			</tr>
			<?php else : ?>
				<?php foreach ( $logs as $log ) : 
					$display_date = isset( $log->last_date ) ? $log->last_date : $log->created_at;
				?>
				<tr>
					<th scope="row" class="check-column"><input type="checkbox" name="log_id[]" value=""></th>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $display_date ) ) ); ?></td>
					<td>
						<strong><a href="<?php echo esc_url( home_url( $log->url ) ); ?>" target="_blank"><?php echo esc_html( $log->url ); ?></a></strong>
						<?php if ( $current_tab !== 'pending' ) : ?>
							<br><span style="color: #888; font-size: 12px;">Statut : <?php echo esc_html( $log->status ); ?></span>
						<?php endif; ?>
					</td>
					
					<?php if ( $current_tab === 'pending' ) : ?>
						<td><?php echo ( $groupby === 'ip' || $groupby === 'url' ) && $log->hits > 1 ? '<span style="color:#aaa;">(Multiples)</span>' : esc_html( $log->user_agent ); ?></td>
						<td><?php echo ( $groupby === 'user_agent' || $groupby === 'url' ) && $log->hits > 1 ? '<span style="color:#aaa;">(Multiples)</span>' : esc_html( $log->ip ); ?></td>
						<td><?php echo esc_html( (isset($log->http_code) ? $log->http_code : '404') . ' - ' . (isset($log->method) ? $log->method : 'GET') ); ?></td>
						<?php if ( ! empty( $groupby ) ) : ?>
							<td><strong><?php echo intval( $log->hits ); ?></strong></td>
						<?php endif; ?>
					<?php endif; ?>
					
					<td>
						<form method="post" action="" style="display: flex; gap: 10px; align-items: center;">
							<?php wp_nonce_field( 'eo_tools_404_nonce' ); ?>
							<input type="hidden" name="action" value="eo_tools_save_redirect">
							<input type="hidden" name="source_url" value="<?php echo esc_attr( $log->url ); ?>">
							
							<?php if ( $current_tab === 'pending' || $current_tab === 'redirected' ) : ?>
								<input type="text" name="redirect_to" value="<?php echo isset( $log->redirect_to ) ? esc_attr( $log->redirect_to ) : ''; ?>" placeholder="Ex: /nouvelle-page/" style="width: 100%; max-width: 180px;">
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Rediriger', 'eo-tools' ); ?></button>
							<?php endif; ?>
							
							<?php if ( $current_tab === 'ignored' ) : ?>
								<button type="submit" name="unignore" value="1" class="button"><?php esc_html_e( 'Rétablir', 'eo-tools' ); ?></button>
							<?php elseif ( $current_tab === 'redirected' ) : ?>
								<button type="submit" name="unignore" value="1" class="button"><?php esc_html_e( 'Supprimer', 'eo-tools' ); ?></button>
							<?php else : ?>
								<button type="submit" name="ignore" value="1" class="button"><?php esc_html_e( 'Ignorer', 'eo-tools' ); ?></button>
							<?php endif; ?>
						</form>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<th scope="col" class="manage-column check-column"><input type="checkbox"></th>
				<th scope="col" class="manage-column column-date"><?php esc_html_e( 'Date', 'eo-tools' ); ?></th>
				<th scope="col" class="manage-column column-source"><?php esc_html_e( 'URL source', 'eo-tools' ); ?></th>
				<?php if ( $current_tab === 'pending' ) : ?>
					<th scope="col" class="manage-column column-user-agent"><?php esc_html_e( 'Agent utilisateur', 'eo-tools' ); ?></th>
					<th scope="col" class="manage-column column-ip"><?php esc_html_e( 'IP', 'eo-tools' ); ?></th>
					<th scope="col" class="manage-column column-type"><?php esc_html_e( 'Type', 'eo-tools' ); ?></th>
					<?php if ( ! empty( $groupby ) ) : ?>
						<th scope="col" class="manage-column column-views"><?php esc_html_e( 'Vues', 'eo-tools' ); ?></th>
					<?php endif; ?>
				<?php endif; ?>
				<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions / Redirection', 'eo-tools' ); ?></th>
			</tr>
		</tfoot>
	</table>
</div>
