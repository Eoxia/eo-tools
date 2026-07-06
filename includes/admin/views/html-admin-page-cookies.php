<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'eo-tools' ) );
}

if ( isset( $_POST['submit'] ) && check_admin_referer( 'eo_tools_cookies_settings' ) ) {
	$settings = array();
	$settings['active'] = ! empty( $_POST['eotools_cookies']['active'] );
	$settings['duration'] = isset( $_POST['eotools_cookies']['duration'] ) ? min( 12, max( 1, intval( $_POST['eotools_cookies']['duration'] ) ) ) : 12;
	$settings['icon_full'] = isset( $_POST['eotools_cookies']['icon_full'] ) ? sanitize_url( $_POST['eotools_cookies']['icon_full'] ) : '';
	$settings['icon_partial'] = isset( $_POST['eotools_cookies']['icon_partial'] ) ? sanitize_url( $_POST['eotools_cookies']['icon_partial'] ) : '';

	update_option( 'eo_tools_cookies_settings', $settings );
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Réglages enregistrés.', 'eo-tools' ) . '</p></div>';
}

global $wpdb;
$table_log = $wpdb->prefix . 'eotools_cookie_log';

if ( isset( $_POST['clear_cookie_log'] ) && check_admin_referer( 'eo_tools_clear_log' ) ) {
	$wpdb->query( "TRUNCATE TABLE $table_log" );
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'L\'historique des consentements a été vidé avec succès.', 'eo-tools' ) . '</p></div>';
}

$settings = get_option( 'eo_tools_cookies_settings', array( 'active' => false, 'duration' => 12 ) );
$table_name = $wpdb->prefix . 'eotools_cookie_stats';

// Fetch stats
$today = current_time( 'Y-m-d' );
$stats_today = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE stat_date = %s", $today ) );
$stats_week = $wpdb->get_row( "SELECT SUM(views) as views, SUM(accepts) as accepts, SUM(refusals) as refusals, SUM(customs) as customs FROM $table_name WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)" );
$stats_month = $wpdb->get_row( "SELECT SUM(views) as views, SUM(accepts) as accepts, SUM(refusals) as refusals, SUM(customs) as customs FROM $table_name WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)" );

// Fetch GitHub Release
$release_transient = get_transient( 'eo_tools_latest_release' );
if ( false === $release_transient ) {
	$response = wp_remote_get( 'https://api.github.com/repos/Eoxia/eo-tools/releases/latest', array(
		'headers' => array( 'User-Agent' => 'WordPress/EOTools' )
	) );
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['name'] ) && isset( $body['html_url'] ) ) {
			$release_transient = array(
				'name' => $body['name'],
				'url'  => $body['html_url'],
			);
			set_transient( 'eo_tools_latest_release', $release_transient, DAY_IN_SECONDS );
		}
	} else {
		$release_transient = 'error';
		set_transient( 'eo_tools_latest_release', $release_transient, HOUR_IN_SECONDS );
	}
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
?>
<div class="wrap eo-admin-wrap">
	<h1><?php esc_html_e( 'Gestion des Cookies', 'eo-tools' ); ?></h1>

	<?php if ( is_array( $release_transient ) && ! empty( $release_transient['name'] ) ) : ?>
	<div class="notice notice-info is-dismissible" style="background: #eff6ff; border-left-color: #3b82f6; padding: 10px 15px; margin-bottom: 20px;">
		<p style="margin: 0; font-size: 14px;">
			<strong style="color: #1e3a8a;">🚀 <?php esc_html_e( 'Nouvelle version d\'EO Tools disponible :', 'eo-tools' ); ?> <?php echo esc_html( $release_transient['name'] ); ?> !</strong>
			<br />
			<?php esc_html_e( 'Découvrez les dernières nouveautés et améliorations.', 'eo-tools' ); ?>
		</p>
		<p style="margin: 10px 0 0;">
			<a href="<?php echo esc_url( $release_transient['url'] ); ?>" target="_blank" class="button button-primary"><?php esc_html_e( 'Voir la release', 'eo-tools' ); ?></a>
		</p>
	</div>
	<?php endif; ?>

	<h2 class="nav-tab-wrapper">
		<a href="?page=eo-tools-cookies&tab=dashboard" class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Tableau de bord', 'eo-tools' ); ?></a>
		<a href="?page=eo-tools-cookies&tab=manage" class="nav-tab <?php echo $active_tab === 'manage' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Gérer les cookies', 'eo-tools' ); ?></a>
		<a href="?page=eo-tools-cookies&tab=detailed_scan" class="nav-tab <?php echo $active_tab === 'detailed_scan' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Scan', 'eo-tools' ); ?></a>
		<a href="?page=eo-tools-cookies&tab=statistics" class="nav-tab <?php echo $active_tab === 'statistics' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Statistiques', 'eo-tools' ); ?></a>
		<a href="?page=eo-tools-cookies&tab=report" class="nav-tab <?php echo $active_tab === 'report' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Rapport de consentements', 'eo-tools' ); ?></a>
		<a href="?page=eo-tools-cookies&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Réglages', 'eo-tools' ); ?></a>
	</h2>

	<?php if ( 'dashboard' === $active_tab ) : ?>
	<?php
	$scan_history = get_option( 'eo_tools_scan_history', array() );
	$last_scan_timestamp = 0;
	$last_scan_date = '';
	
	if ( ! empty( $scan_history ) && is_array( $scan_history ) ) {
		foreach ( $scan_history as $scan ) {
			if ( isset( $scan['status'] ) && $scan['status'] === 'COMPLETED' ) {
				if ( isset( $scan['timestamp'] ) ) {
					$last_scan_timestamp = intval( $scan['timestamp'] ) / 1000;
				} elseif ( ! empty( $scan['date'] ) ) {
					$date_str = str_replace( '/', '-', $scan['date'] );
					$last_scan_timestamp = strtotime( $date_str );
				}
				$last_scan_date = $scan['date'] ?? '';
				break;
			}
		}
	}

	$days_since_scan = 9999;
	if ( $last_scan_timestamp > 0 ) {
		$days_since_scan = floor( ( time() - $last_scan_timestamp ) / DAY_IN_SECONDS );
	}
	
	$show_alert = true;
	if ( $days_since_scan > 365 ) {
		$alert_class = 'notice-error';
		$alert_message = esc_html__( 'Attention : Votre dernier scan a plus d\'un an (ou n\'a jamais été effectué).', 'eo-tools' );
	} elseif ( $days_since_scan >= 335 ) { // 30 days remaining
		$alert_class = 'notice-error';
		$alert_message = sprintf( esc_html__( 'Alerte : Votre dernier scan date du %s. Il expire dans moins de 30 jours !', 'eo-tools' ), $last_scan_date );
	} elseif ( $days_since_scan >= 305 ) { // 60 days remaining
		$alert_class = 'notice-warning';
		$alert_message = sprintf( esc_html__( 'Attention : Votre dernier scan date du %s. Pensez à le mettre à jour prochainement.', 'eo-tools' ), $last_scan_date );
	} else {
		$alert_class = 'notice-info';
		$alert_message = sprintf( esc_html__( 'Votre dernier scan date du %s.', 'eo-tools' ), $last_scan_date );
		// Si on veut le masquer totalement quand tout va bien, on peut mettre $show_alert = false; 
		// Mais il est préférable de toujours afficher la date pour rassurer.
	}
	?>
	<?php if ( $show_alert ) : ?>
	<div class="notice <?php echo esc_attr( $alert_class ); ?>" style="background: #fff; padding: 10px 15px; margin-bottom: 20px; display: block !important;">
		<p style="margin: 0; font-size: 14px;">
			<strong><?php echo esc_html( $alert_message ); ?></strong>
			<br />
			<?php esc_html_e( 'Nous vous conseillons de le mettre à jour au moins 1 fois par an. Conformément aux recommandations de la CNIL, le responsable du traitement reste légalement responsable de la conformité de son site.', 'eo-tools' ); ?>
			<a href="https://www.cnil.fr/fr/cookies-et-autres-traceurs" target="_blank"><?php esc_html_e( 'En savoir plus sur le site de la CNIL', 'eo-tools' ); ?></a>.
		</p>
	</div>
	<?php endif; ?>

	<?php elseif ( 'manage' === $active_tab ) : ?>

	<div class="eo-card" style="margin-top: 20px;">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
			<h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
				<?php esc_html_e( 'Liste des cookies', 'eo-tools' ); ?>
				<?php 
				$db_file = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'assets/data/open-cookie-database.csv';
				$db_date = file_exists( $db_file ) ? wp_date( 'd/m/Y', filemtime( $db_file ) ) : '';
				?>
				<span style="font-size: 13px; font-weight: normal; color: #64748b;">
					(<a href="https://github.com/jkwakman/Open-Cookie-Database" target="_blank" rel="noopener noreferrer" style="color: #2271b1; text-decoration: none;">Open Cookie Database</a><?php echo $db_date ? ' - ' . esc_html( sprintf( __( 'mise à jour le %s', 'eo-tools' ), $db_date ) ) : ''; ?>)
				</span>
			</h2>
		</div>

		<?php
		global $wpdb;
		$table_log = $wpdb->prefix . 'eotools_cookie_log';
		$last_admin_log = $wpdb->get_row( "SELECT * FROM $table_log WHERE consent_status = 'ADMIN_VALIDATION' ORDER BY time DESC LIMIT 1" );
		if ( $last_admin_log ) :
		?>
		<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
			<div style="display: flex; gap: 20px; align-items: center; flex: 1;">
				<code style="background: #e2e8f0; padding: 4px 8px; border-radius: 4px; color: #475569; font-size: 12px; word-break: break-all;"><?php echo esc_html( $last_admin_log->consent_id ); ?></code>
				<div style="font-size: 12px; color: #64748b; max-width: 500px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
					<?php 
					// Show the list with + and - (or just the list)
					echo esc_html( $last_admin_log->comments ); 
					?>
				</div>
			</div>
			<div style="display: flex; flex-direction: column; align-items: flex-end; gap: 5px;">
				<div style="font-size: 12px; color: #475569;">
					<?php echo esc_html( wp_date( 'M j, Y H:i:s', strtotime( $last_admin_log->time ) ) ); ?> 
					<span style="color: #16a34a; font-weight: bold; font-size: 14px;">&check;</span>
				</div>
				<span style="color: #1d4ed8; background: #eff6ff; padding: 4px 12px; border-radius: 4px; font-weight: bold; border: 1px solid #bfdbfe; font-size: 11px; letter-spacing: 0.5px;">
					<?php esc_html_e( 'VALIDATION ADMIN', 'eo-tools' ); ?>
				</span>
			</div>
		</div>
		<?php endif; ?>
		
		<script>
			window.eoLastAdminValidationCookies = '<?php echo esc_js( $last_admin_log ? $last_admin_log->comments : "" ); ?>';
		</script>
			
		<div style="display: flex; gap: 15px; align-items: center; margin-bottom: 20px; flex-wrap: wrap;">
			<div style="flex: 1; min-width: 300px; display: flex; align-items: center; border: 1px solid #2271b1; border-radius: 4px; padding: 0 10px; background: #fff;">
				<span class="dashicons dashicons-search" style="color: #2271b1;"></span>
				<input type="text" id="eo-cookie-search-db" placeholder="<?php esc_attr_e( 'Recherchez vos cookies (ex: _ga) pour les pré-remplir...', 'eo-tools' ); ?>" style="border: none; box-shadow: none; flex: 1; padding: 8px; outline: none; background: transparent;">
			</div>
			<button type="button" id="eo-add-cookie-btn" class="button button-primary">
				+ <?php esc_html_e( 'Ajouter un cookie', 'eo-tools' ); ?>
			</button>
		</div>
		
		<div id="eo-active-cookies-summary" style="margin-bottom: 20px; font-size: 13px; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px 15px; display: none;">
			<!-- Rempli par JS -->
		</div>

		<div id="eo-validation-prompt-container" style="display: none; background: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin-bottom: 20px; align-items: center; justify-content: space-between;">
			<div style="font-size: 14px; color: #92400e;">
				<strong><?php esc_html_e( 'Confirmez-vous la mise en place de :', 'eo-tools' ); ?></strong>
				<span id="eo-validation-cookie-list" style="margin-left: 5px;"></span>
			</div>
			<button type="button" id="eo-save-consent-btn" class="button button-primary" style="font-size: 13px;">
				<?php esc_html_e( 'Validation Admin', 'eo-tools' ); ?>
			</button>
		</div>
			
		<div id="eo-cookie-list-container" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; min-height: 300px;">
			<p><?php esc_html_e( 'Chargement...', 'eo-tools' ); ?></p>
		</div>
	</div>

	<!-- Modal Add/Edit Cookie -->
	<div id="eo-cookie-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100000; justify-content: center; align-items: center;">
		<div style="background: #fff; padding: 20px; border-radius: 8px; width: 500px; max-width: 90%;">
			<h3 id="eo-cookie-modal-title" style="margin-top: 0;"><?php esc_html_e( 'Ajouter un cookie', 'eo-tools' ); ?></h3>
			<form id="eo-cookie-form">
				<input type="hidden" id="eo-cookie-id">
				<input type="hidden" id="eo-cookie-old-cat">
				
				<table class="form-table">
					<tr>
						<th scope="row"><label for="eo-cookie-cat"><?php esc_html_e( 'Catégorie', 'eo-tools' ); ?></label></th>
						<td>
							<select id="eo-cookie-cat" required style="width: 100%;">
								<option value="strictly-necessary"><?php esc_html_e( 'Nécessaire', 'eo-tools' ); ?></option>
								<option value="functional"><?php esc_html_e( 'Fonctionnelle', 'eo-tools' ); ?></option>
								<option value="analytics"><?php esc_html_e( 'Analytique', 'eo-tools' ); ?></option>
								<option value="performance"><?php esc_html_e( 'Performance', 'eo-tools' ); ?></option>
								<option value="marketing"><?php esc_html_e( 'Publicité', 'eo-tools' ); ?></option>
								<option value="social"><?php esc_html_e( 'Réseaux sociaux', 'eo-tools' ); ?></option>
								<option value="others"><?php esc_html_e( 'Autres', 'eo-tools' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="eo-cookie-name"><?php esc_html_e( 'Nom du Cookie', 'eo-tools' ); ?></label></th>
						<td>
							<input type="text" id="eo-cookie-name" class="regular-text" required placeholder="_ga" style="width: 100%;" pattern="^[!#$%&'*+\-\.^_`|~a-zA-Z0-9]+$" title="<?php esc_attr_e( 'Uniquement des lettres, chiffres et caractères spéciaux autorisés selon la norme RFC 6265.', 'eo-tools' ); ?>">
							<p class="description"><?php esc_html_e( 'Autorise toutes les lettres (majuscules/minuscules), les chiffres, et une liste très précise de caractères spéciaux autorisés (!, #, $, %, &, \', *, +, -, ., ^, _, `, |, ~).', 'eo-tools' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="eo-cookie-domain"><?php esc_html_e( 'Domaine', 'eo-tools' ); ?></label></th>
						<td><input type="text" id="eo-cookie-domain" class="regular-text" required placeholder=".domaine.com" style="width: 100%;"></td>
					</tr>
					<tr>
						<th scope="row"><label for="eo-cookie-duration"><?php esc_html_e( 'Durée (en jours)', 'eo-tools' ); ?></label></th>
						<td>
							<input type="number" id="eo-cookie-duration" class="regular-text" required placeholder="365" min="1" max="365" style="width: 100%;">
							<p class="description"><?php esc_html_e( 'Maximum 365 jours selon les recommandations de la CNIL.', 'eo-tools' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="eo-cookie-desc"><?php esc_html_e( 'Description', 'eo-tools' ); ?></label></th>
						<td><textarea id="eo-cookie-desc" class="regular-text" rows="3" required style="width: 100%;"></textarea></td>
					</tr>
				</table>
				<div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
					<button type="button" class="button" id="eo-cookie-modal-cancel"><?php esc_html_e( 'Annuler', 'eo-tools' ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'eo-tools' ); ?></button>
				</div>
			</form>
		</div>
	</div>

	<!-- Modal Validation Scan -->
	<div id="eo-scan-validation-modal" style="display: none; position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); align-items: center; justify-content: center;">
		<div style="background-color: #fff; margin: auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
			<h3 style="margin-top: 0; font-size: 16px; color: #1e293b;"><?php esc_html_e( 'Validation des modifications', 'eo-tools' ); ?></h3>
			<p><?php esc_html_e( 'Veuillez vérifier et confirmer l\'ajout ou la suppression des cookies suivants détectés lors du dernier scan :', 'eo-tools' ); ?></p>
			<ul id="eo-scan-validation-list" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px 15px; max-height: 200px; overflow-y: auto; list-style: disc inside; color: #0f172a; font-weight: 500;"></ul>
			<div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
				<button type="button" class="button" id="eo-scan-validation-cancel"><?php esc_html_e( 'Annuler', 'eo-tools' ); ?></button>
				<button type="button" class="button button-primary" id="eo-scan-validation-confirm" style="background: #10b981; border-color: #059669;"><?php esc_html_e( 'Je confirme la validation', 'eo-tools' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Modal Delete Confirmation -->
	<div id="eo-delete-confirm-modal" style="display: none; position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); align-items: center; justify-content: center;">
		<div style="background-color: #fff; margin: auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 400px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
			<h3 style="margin-top: 0; font-size: 16px; color: #1e293b;"><?php esc_html_e( 'Confirmation de suppression', 'eo-tools' ); ?></h3>
			<p><?php esc_html_e( 'Êtes-vous sûr de vouloir supprimer ce cookie ?', 'eo-tools' ); ?></p>
			<p style="font-weight: bold; color: #dc2626;" id="eo-delete-confirm-cookie-name"></p>
			<div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
				<button type="button" class="button" id="eo-delete-confirm-cancel"><?php esc_html_e( 'Annuler', 'eo-tools' ); ?></button>
				<button type="button" class="button button-primary" id="eo-delete-confirm-btn" style="background: #ef4444; border-color: #dc2626;"><?php esc_html_e( 'Supprimer', 'eo-tools' ); ?></button>
			</div>
		</div>
	</div>

	<?php elseif ( 'statistics' === $active_tab ) : ?>
		<div class="eo-card" style="margin-top: 20px;">
			<h2><?php esc_html_e( 'Statistiques de Consentement', 'eo-tools' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Vue d\'ensemble anonymisée des choix de vos visiteurs.', 'eo-tools' ); ?></p>

			<div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px;">
				<!-- Today -->
				<div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; flex: 1; min-width: 200px;">
					<h3><?php esc_html_e( 'Aujourd\'hui', 'eo-tools' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Vues :', 'eo-tools' ); ?> <strong><?php echo $stats_today ? intval( $stats_today->views ) : 0; ?></strong></li>
						<li><?php esc_html_e( 'Tout accepté :', 'eo-tools' ); ?> <strong><?php echo $stats_today ? intval( $stats_today->accepts ) : 0; ?></strong></li>
						<li><?php esc_html_e( 'Tout refusé :', 'eo-tools' ); ?> <strong><?php echo $stats_today ? intval( $stats_today->refusals ) : 0; ?></strong></li>
						<li><?php esc_html_e( 'Personnalisé :', 'eo-tools' ); ?> <strong><?php echo $stats_today ? intval( $stats_today->customs ) : 0; ?></strong></li>
					</ul>
				</div>

				<!-- 7 Days -->
				<div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; flex: 1; min-width: 200px;">
					<h3><?php esc_html_e( '7 derniers jours', 'eo-tools' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Vues :', 'eo-tools' ); ?> <strong><?php echo $stats_week ? intval( $stats_week->views ) : 0; ?></strong></li>
						<li><?php esc_html_e( 'Tout accepté :', 'eo-tools' ); ?> <strong><?php echo $stats_week ? intval( $stats_week->accepts ) : 0; ?></strong></li>
						<li><?php esc_html_e( 'Tout refusé :', 'eo-tools' ); ?> <strong><?php echo $stats_week ? intval( $stats_week->refusals ) : 0; ?></strong></li>
						<li><?php esc_html_e( 'Personnalisé :', 'eo-tools' ); ?> <strong><?php echo $stats_week ? intval( $stats_week->customs ) : 0; ?></strong></li>
					</ul>
				</div>

				<!-- 30 Days -->
				<div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; flex: 1; min-width: 200px;">
					<h3><?php esc_html_e( '30 derniers jours', 'eo-tools' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Vues :', 'eo-tools' ); ?> <strong><?php echo $stats_month ? intval( $stats_month->views ) : 0; ?></strong></li>
						<li><?php esc_html_e( 'Tout accepté :', 'eo-tools' ); ?> <strong><?php echo $stats_month ? intval( $stats_month->accepts ) : 0; ?></strong></li>
						<li><?php esc_html_e( 'Tout refusé :', 'eo-tools' ); ?> <strong><?php echo $stats_month ? intval( $stats_month->refusals ) : 0; ?></strong></li>
						<li><?php esc_html_e( 'Personnalisé :', 'eo-tools' ); ?> <strong><?php echo $stats_month ? intval( $stats_month->customs ) : 0; ?></strong></li>
					</ul>
				</div>
			</div>
			
			<h3 style="margin-top: 30px;"><?php esc_html_e( 'Évolution sur 30 jours', 'eo-tools' ); ?></h3>
			<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; position: relative;">
				<canvas id="eoCookieStatsChart" height="100"></canvas>
				<button type="button" id="eoCookieExportCsv" class="button" style="margin-top: 15px; background: #4b5563; color: white; border-color: #374151;"><?php esc_html_e( 'export (.csv)', 'eo-tools' ); ?></button>
			</div>

			<?php
			$chart_data = $wpdb->get_results( "SELECT stat_date, views, accepts, refusals, customs FROM $table_name WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY stat_date ASC" );
			
			$labels = array();
			$views_data = array();
			$consent_data = array();

			$end_date = new DateTime();
			$start_date = (new DateTime())->modify('-29 days');
			$interval = new DateInterval('P1D');
			$daterange = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));

			$data_map = array();
			foreach ( $chart_data as $row ) {
				$data_map[$row->stat_date] = $row;
			}

			foreach ( $daterange as $date ) {
				$d = $date->format('Y-m-d');
				$labels[] = $date->format('d/m');
				
				if ( isset( $data_map[$d] ) ) {
					$v = intval( $data_map[$d]->views );
					$a = intval( $data_map[$d]->accepts );
					
					$views_data[] = $v;
					$consent_data[] = $v > 0 ? round( ( $a / $v ) * 100 ) : 0;
				} else {
					$views_data[] = 0;
					$consent_data[] = 0;
				}
			}
			?>
			<script>
				window.eoCookieChartData = {
					labels: <?php echo json_encode( $labels ); ?>,
					views: <?php echo json_encode( $views_data ); ?>,
					consent: <?php echo json_encode( $consent_data ); ?>
				};
			</script>
		</div>

	<?php elseif ( 'report' === $active_tab ) : ?>

	<div class="eo-card" style="margin-top: 20px;">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
			<h2 style="margin: 0;"><?php esc_html_e( 'Historique des consentements', 'eo-tools' ); ?></h2>
			<form method="post" action="" onsubmit="return confirm('<?php esc_attr_e( 'Êtes-vous sûr de vouloir supprimer tout l\'historique des consentements ? Cette action est irréversible.', 'eo-tools' ); ?>');">
				<?php wp_nonce_field( 'eo_tools_clear_log' ); ?>
				<button type="submit" name="clear_cookie_log" class="button button-secondary" style="color: #dc3232; border-color: #dc3232;"><?php esc_html_e( 'Vider l\'historique', 'eo-tools' ); ?></button>
			</form>
		</div>
		
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Consent ID', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Commentaires', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Statut', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Date & Heure', 'eo-tools' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$logs = $wpdb->get_results( "SELECT * FROM $table_log ORDER BY time DESC LIMIT 100" );
				if ( ! empty( $logs ) ) {
					foreach ( $logs as $log ) {
						$status_label = esc_html__( 'REFUSÉ', 'eo-tools' );
						$status_style = 'color: #b91c1c; background: #fef2f2; padding: 4px 12px; border-radius: 4px; font-weight: bold; border: 1px solid #fca5a5; font-size: 13px; letter-spacing: 0.5px;';
						
						if ( 'ACCEPTED' === $log->consent_status ) {
							$status_label = esc_html__( 'ACCEPTÉ', 'eo-tools' );
							$status_style = 'color: #047857; background: #f0fdf4; padding: 4px 12px; border-radius: 4px; font-weight: bold; border: 1px solid #86efac; font-size: 13px; letter-spacing: 0.5px;';
						} elseif ( 'PARTIAL' === $log->consent_status ) {
							$status_label = esc_html__( 'PARTIELLEMENT ACCEPTÉ', 'eo-tools' );
							$status_style = 'color: #c2410c; background: #fff7ed; padding: 4px 12px; border-radius: 4px; font-weight: bold; border: 1px solid #fdba74; font-size: 13px; letter-spacing: 0.5px;';
						} elseif ( 'ADMIN_VALIDATION' === $log->consent_status ) {
							$status_label = esc_html__( 'VALIDATION ADMIN', 'eo-tools' );
							$status_style = 'color: #1d4ed8; background: #eff6ff; padding: 4px 12px; border-radius: 4px; font-weight: bold; border: 1px solid #bfdbfe; font-size: 13px; letter-spacing: 0.5px;';
						}
						?>
						<tr>
							<td><code style="word-break: break-all;"><?php echo esc_html( $log->consent_id ); ?></code></td>
							<td style="color: #64748b; font-size: 12px; max-width: 300px; white-space: normal; word-break: break-all;">
								<?php 
								$comments_data = isset( $log->comments ) ? $log->comments : '';
								$decoded_comments = json_decode( $comments_data, true );
								if ( is_array( $decoded_comments ) ) {
									// It's a JSON of categories
									$labels = array(
										'strictly-necessary' => 'Necessary',
										'functional'         => 'Functional',
										'analytics'          => 'Analytics',
										'performance'        => 'Performance',
										'advertisement'      => 'Advertisement',
										'others'             => 'Others'
									);
									echo '<ul style="list-style: none; padding: 0; margin: 0;">';
									foreach ( $decoded_comments as $cat_key => $is_accepted ) {
										$cat_label = isset( $labels[ $cat_key ] ) ? $labels[ $cat_key ] : ucfirst( str_replace( '-', ' ', $cat_key ) );
										$icon = $is_accepted ? '<span style="color: #16a34a; font-weight: bold;">&check;</span>' : '<span style="color: #dc2626; font-weight: bold;">&cross;</span>';
										echo '<li style="margin-bottom: 2px;">' . esc_html( $cat_label ) . ' ' . $icon . '</li>';
									}
									echo '</ul>';
								} else {
									// Just a plain string
									echo esc_html( $comments_data );
								}
								?>
							</td>
							<td><span style="display: inline-block; <?php echo $status_style; ?>"><?php echo $status_label; ?></span></td>
							<td><?php echo esc_html( wp_date( 'M j, Y H:i:s', strtotime( $log->time ) ) ); ?></td>
						</tr>
						<?php
					}
				} else {
					?>
					<tr>
						<td colspan="3"><?php esc_html_e( 'Aucun historique pour le moment.', 'eo-tools' ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Seuls les 100 derniers événements sont affichés ici.', 'eo-tools' ); ?></p>
	</div>

	<?php elseif ( 'detailed_scan' === $active_tab ) : ?>
	
	<?php if ( isset( $_GET['batch_id'] ) ) : ?>
	<script>
		var eo_tools_preload_batch_id = '<?php echo esc_js( sanitize_text_field( $_GET['batch_id'] ) ); ?>';
	</script>
	<?php endif; ?>

	<div class="eo-card" style="margin-top: 20px;">
		<h2><?php esc_html_e( 'Scan des pages', 'eo-tools' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Analysez toutes les pages et articles de votre site pour détecter les scripts et cookies installés (Analytics, Publicités, etc.).', 'eo-tools' ); ?></p>
		
		<div style="margin-top: 20px; display: flex; gap: 15px; align-items: center;">
			<button id="eo-start-detailed-scan" class="button button-primary" style="display: inline-flex; justify-content: center; align-items: center; gap: 5px;" title="<?php esc_attr_e( 'Démarrer le scan complet', 'eo-tools' ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle;"><path d="M8 5v14l11-7z"/></svg>
				<?php esc_html_e( 'Scan base de données', 'eo-tools' ); ?>
			</button>
			<button type="button" id="eo-scan-cookies-btn" class="button button-secondary">
				<?php esc_html_e( 'Scan Automatique', 'eo-tools' ); ?>
			</button>
			<button id="eo-pause-detailed-scan" class="button button-secondary" style="display: none; padding: 0; width: 36px; height: 36px; justify-content: center; align-items: center;" title="<?php esc_attr_e( 'Mettre en pause', 'eo-tools' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle;"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
			</button>
			<button id="eo-resume-detailed-scan" class="button button-secondary" style="display: none; padding: 0; width: 36px; height: 36px; justify-content: center; align-items: center;" title="<?php esc_attr_e( 'Reprendre le scan', 'eo-tools' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-left: 2px;"><path d="M8 5v14l11-7z"/></svg>
			</button>
			
			<div id="eo-detailed-scan-progress-container" style="position: relative; width: 36px; height: 36px; display: none;">
				<svg width="36" height="36" viewBox="0 0 140 140" style="transform: rotate(-90deg);">
					<defs>
						<mask id="eo-progress-mask">
							<circle id="eo-detailed-scan-progress-mask-circle" cx="70" cy="70" r="60" fill="none" stroke="white" stroke-width="16" stroke-dasharray="377" stroke-dashoffset="377" style="transition: stroke-dashoffset 0.3s ease;" />
						</mask>
					</defs>
					<circle cx="70" cy="70" r="60" fill="none" stroke="#e2e8f0" stroke-width="12" stroke-dasharray="22 9.4" />
					<circle cx="70" cy="70" r="60" fill="none" stroke="#0ea5e9" stroke-width="12" stroke-dasharray="22 9.4" mask="url(#eo-progress-mask)" />
				</svg>
				<div id="eo-detailed-scan-progress-text" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: bold; color: #1e293b;">0%</div>
			</div>

			<span id="eo-detailed-scan-timing" style="margin-left: 15px; font-size: 13px; color: #64748b; display: none; align-items: center;">
				Début : <strong id="eo-scan-time-start">-</strong> <span style="margin: 0 5px;">|</span> 
				Fin : <strong id="eo-scan-time-end">-</strong> <span style="margin: 0 5px;">|</span> 
				Durée : <strong id="eo-scan-time-duration">-</strong>
			</span>
		</div>

		<h3 style="margin-top: 40px;"><?php esc_html_e( 'État d\'avancement par type de contenu', 'eo-tools' ); ?></h3>
		<table class="wp-list-table widefat fixed striped" id="eo-detailed-scan-counts-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Type de contenu', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Nombre en BDD', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Nombre scanné', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Pourcentage', 'eo-tools' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr data-type="headers_footers">
					<td>Header / Footer</td>
					<td class="count-total">-</td>
					<td class="count-scanned">-</td>
					<td class="count-percent">-</td>
				</tr>
				<tr data-type="posts">
					<td>Articles</td>
					<td class="count-total">-</td>
					<td class="count-scanned">-</td>
					<td class="count-percent">-</td>
				</tr>
				<tr data-type="pages">
					<td>Pages</td>
					<td class="count-total">-</td>
					<td class="count-scanned">-</td>
					<td class="count-percent">-</td>
				</tr>
				<tr data-type="cpts">
					<td>Custom Post Types</td>
					<td class="count-total">-</td>
					<td class="count-scanned">-</td>
					<td class="count-percent">-</td>
				</tr>
				<tr data-type="attachments">
					<td>Médias (Attachments)</td>
					<td class="count-total">-</td>
					<td class="count-scanned">-</td>
					<td class="count-percent">-</td>
				</tr>
			</tbody>
		</table>

		<h3 style="margin-top: 40px;"><?php esc_html_e( 'URLs Scannées', 'eo-tools' ); ?></h3>
		<table class="wp-list-table widefat fixed striped" id="eo-detailed-scan-results-table">
			<thead>
				<tr>
					<th style="width: 45%;"><?php esc_html_e( 'URL Scannée', 'eo-tools' ); ?></th>
					<th style="width: 9%;"><?php esc_html_e( 'Scan Type', 'eo-tools' ); ?></th>
					<th style="width: 8%; text-align: center;"><?php esc_html_e( 'Total', 'eo-tools' ); ?></th>
					<th style="width: 7%; text-align: center;"><?php esc_html_e( 'Nécessaire', 'eo-tools' ); ?></th>
					<th style="width: 7%; text-align: center;"><?php esc_html_e( 'Analytique', 'eo-tools' ); ?></th>
					<th style="width: 7%; text-align: center;"><?php esc_html_e( 'Publicité', 'eo-tools' ); ?></th>
					<th style="width: 7%; text-align: center;"><?php esc_html_e( 'Social', 'eo-tools' ); ?></th>
					<th style="width: 10%; text-align: center;"><?php esc_html_e( 'Autres', 'eo-tools' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr id="eo-detailed-scan-empty-row">
					<td colspan="8" style="text-align: center; color: #64748b; font-style: italic; padding: 15px;">
						<?php esc_html_e( 'Le scan n\'a pas encore commencé.', 'eo-tools' ); ?>
					</td>
				</tr>
			</tbody>
		</table>

		<div id="eo-detailed-scan-floating-console" style="display: none; position: fixed; bottom: 20px; right: 20px; width: 600px; max-width: 90vw; background-color: #111827; color: #10b981; border-radius: 6px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 100000; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; overflow: hidden; border: 1px solid #374151;">
			<div style="background-color: #1f2937; padding: 10px 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #374151; cursor: pointer; user-select: none;" id="eo-floating-console-header">
				<div style="font-weight: bold; color: #60a5fa; font-size: 13px; display: flex; align-items: center; gap: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 65%;">
					<span style="color: #3b82f6;">>_</span> CONSOLE 
					<span style="color: #9ca3af; font-weight: normal; margin-left: 10px; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="eo-console-last-msg">Dernier : En attente...</span>
				</div>
				<div style="display: flex; align-items: center; gap: 12px; font-size: 11px; color: #9ca3af; flex-shrink: 0;">
					<span id="eo-console-copy" style="cursor: pointer;" title="<?php esc_attr_e( 'Copier les logs', 'eo-tools' ); ?>">Copier</span>
					<span style="color: #4b5563;">|</span>
					<span id="eo-console-clear" style="cursor: pointer;" title="<?php esc_attr_e( 'Vider la console', 'eo-tools' ); ?>">Vider</span>
					<span style="color: #4b5563;">|</span>
					<span id="eo-console-toggle-icon" style="font-size: 10px;">▲</span>
				</div>
			</div>
			<div id="eo-detailed-scan-console" style="padding: 15px; height: 300px; overflow-y: auto; font-size: 13px; line-height: 1.6; display: none; color: #34d399;">
				<div><?php esc_html_e( '[En attente...] Prêt à démarrer le scan.', 'eo-tools' ); ?></div>
			</div>
		</div>

	</div>

	<!-- Scan History Section -->
	<div class="eo-card" style="margin-top: 30px;">
		<h2 style="margin: 0 0 20px 0;"><?php esc_html_e( 'Historique des Scans', 'eo-tools' ); ?></h2>
		<table class="wp-list-table widefat fixed striped" style="border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date du scan', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Statut', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Cookies trouvés', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Listes des Cookies trouvés', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Modifications Cookies', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Listes des Cookies', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Action', 'eo-tools' ); ?></th>
				</tr>
			</thead>
			<tbody id="eo-scan-history-list">
				<tr>
					<td colspan="7" style="text-align: center; color: #64748b; font-style: italic; padding: 15px;">
						<?php esc_html_e( 'Chargement de l\'historique...', 'eo-tools' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<?php elseif ( 'settings' === $active_tab ) : ?>
	
	<form method="post" action="">
		<?php wp_nonce_field( 'eo_tools_cookies_settings' ); ?>

		<div class="eo-card" style="margin-top: 20px;">
			<h2><?php esc_html_e( 'Cookies et bandeau', 'eo-tools' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="eotools_cookies_active"><?php esc_html_e( 'Activer le gestionnaire', 'eo-tools' ); ?></label></th>
					<td>
						<input type="checkbox" id="eotools_cookies_active" name="eotools_cookies[active]" value="1" <?php checked( ! empty( $settings['active'] ) ); ?> />
						<p class="description"><?php esc_html_e( 'Active le bandeau et le blocage des cookies tiers.', 'eo-tools' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="eotools_cookies_duration"><?php esc_html_e( 'Durée de conservation des choix (mois)', 'eo-tools' ); ?></label></th>
					<td>
						<input type="number" id="eotools_cookies_duration" name="eotools_cookies[duration]" value="<?php echo esc_attr( $settings['duration'] ); ?>" min="1" max="12" />
						<p class="description"><?php esc_html_e( 'Conformément aux recommandations de la CNIL, la durée maximale est fixée à 12 mois.', 'eo-tools' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="eotools_cookies_icon_full"><?php esc_html_e( 'Icône d\'acceptation totale (URL)', 'eo-tools' ); ?></label></th>
					<td>
						<input type="url" id="eotools_cookies_icon_full" class="regular-text" name="eotools_cookies[icon_full]" value="<?php echo esc_attr( $settings['icon_full'] ?? '' ); ?>" placeholder="https://..." />
						<p class="description"><?php esc_html_e( 'Laissez vide pour utiliser l\'icône par défaut (cookie plein).', 'eo-tools' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="eotools_cookies_icon_partial"><?php esc_html_e( 'Icône d\'acceptation partielle/refus (URL)', 'eo-tools' ); ?></label></th>
					<td>
						<input type="url" id="eotools_cookies_icon_partial" class="regular-text" name="eotools_cookies[icon_partial]" value="<?php echo esc_attr( $settings['icon_partial'] ?? '' ); ?>" placeholder="https://..." />
						<p class="description"><?php esc_html_e( 'Laissez vide pour utiliser l\'icône par défaut (cookie croqué).', 'eo-tools' ); ?></p>
					</td>
				</tr>
			</table>

			<h2 style="margin-top: 30px;"><?php esc_html_e( 'Scans et logs', 'eo-tools' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="eotools_cookies_duration_logs"><?php esc_html_e( 'Durée de conservation des logs de scan (mois)', 'eo-tools' ); ?></label></th>
					<td>
						<input type="number" id="eotools_cookies_duration_logs" name="eotools_cookies[duration_logs]" value="<?php echo esc_attr( $settings['duration_logs'] ?? 6 ); ?>" min="1" max="24" />
						<p class="description"><?php esc_html_e( 'Les journaux du scanner détaillé plus anciens que cette durée seront supprimés automatiquement pour alléger la base de données.', 'eo-tools' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="eotools_cookies_batch_size"><?php esc_html_e( 'Taille des lots (Batch size)', 'eo-tools' ); ?></label></th>
					<td>
						<input type="number" id="eotools_cookies_batch_size" name="eotools_cookies[batch_size]" value="<?php echo esc_attr( isset($settings['batch_size']) && $settings['batch_size'] !== '' ? $settings['batch_size'] : 5 ); ?>" min="1" max="50" />
						<p class="description"><?php esc_html_e( 'Nombre d\'URLs scannées simultanément par le scanner détaillé. Réduisez si votre serveur s\'essouffle.', 'eo-tools' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="eotools_cookies_batch_delay"><?php esc_html_e( 'Délai entre les lots (ms)', 'eo-tools' ); ?></label></th>
					<td>
						<input type="number" id="eotools_cookies_batch_delay" name="eotools_cookies[batch_delay]" value="<?php echo esc_attr( isset($settings['batch_delay']) && $settings['batch_delay'] !== '' ? $settings['batch_delay'] : 200 ); ?>" min="0" max="10000" />
						<p class="description"><?php esc_html_e( 'Pause en millisecondes entre chaque requête du scanner (ex: 1000 pour 1 seconde). Aide à prévenir les surcharges (Timeout).', 'eo-tools' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</div>
	</form>

	<?php endif; ?>
