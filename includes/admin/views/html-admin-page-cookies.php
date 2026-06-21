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
		<a href="?page=eo-tools-cookies&tab=statistics" class="nav-tab <?php echo $active_tab === 'statistics' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Statistiques', 'eo-tools' ); ?></a>
		<a href="?page=eo-tools-cookies&tab=report" class="nav-tab <?php echo $active_tab === 'report' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Rapport de consentements', 'eo-tools' ); ?></a>
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
	
	if ( $days_since_scan > 365 ) {
		$alert_class = 'notice-error';
		$alert_message = esc_html__( 'Attention : Votre dernier scan a plus d\'un an (ou n\'a jamais été effectué).', 'eo-tools' );
	} else {
		$alert_class = 'notice-info';
		$alert_message = sprintf( esc_html__( 'Votre dernier scan date du %s.', 'eo-tools' ), $last_scan_date );
	}
	?>
	<div class="notice <?php echo esc_attr( $alert_class ); ?>" style="background: #fff; padding: 10px 15px; margin-bottom: 20px; display: block !important;">
		<p style="margin: 0; font-size: 14px;">
			<strong><?php echo esc_html( $alert_message ); ?></strong>
			<br />
			<?php esc_html_e( 'Nous vous conseillons de le mettre à jour au moins 1 fois par an. Conformément aux recommandations de la CNIL, le responsable du traitement reste légalement responsable de la conformité de son site.', 'eo-tools' ); ?>
			<a href="https://www.cnil.fr/fr/cookies-et-autres-traceurs" target="_blank"><?php esc_html_e( 'En savoir plus sur le site de la CNIL', 'eo-tools' ); ?></a>.
		</p>
	</div>

	<form method="post" action="">
		<?php wp_nonce_field( 'eo_tools_cookies_settings' ); ?>

		<div class="eo-card" style="margin-top: 20px;">
			<h2><?php esc_html_e( 'Réglages Principaux', 'eo-tools' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="eotools_cookies_active"><?php esc_html_e( 'Activer le gestionnaire', 'eo-tools' ); ?></label></th>
					<td>
						<input type="checkbox" id="eotools_cookies_active" name="eotools_cookies[active]" value="1" <?php checked( ! empty( $settings['active'] ) ); ?> />
						<p class="description"><?php esc_html_e( 'Active le bandeau et le blocage des cookies tiers.', 'eo-tools' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="eotools_cookies_duration"><?php esc_html_e( 'Durée de conservation (mois)', 'eo-tools' ); ?></label></th>
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
			<?php submit_button(); ?>
		</div>
	</form>

	<?php elseif ( 'manage' === $active_tab ) : ?>

	<div class="eo-card" style="margin-top: 20px;">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
			<h2 style="margin: 0;"><?php esc_html_e( 'Liste des cookies', 'eo-tools' ); ?></h2>
		</div>
			
		<div style="display: flex; gap: 15px; align-items: center; margin-bottom: 20px; flex-wrap: wrap;">
			<div style="flex: 1; min-width: 300px; display: flex; align-items: center; border: 1px solid #2271b1; border-radius: 4px; padding: 0 10px; background: #fff;">
				<span class="dashicons dashicons-search" style="color: #2271b1;"></span>
				<input type="text" id="eo-cookie-search-db" placeholder="<?php esc_attr_e( 'Recherchez vos cookies (ex: _ga) pour les pré-remplir...', 'eo-tools' ); ?>" style="border: none; box-shadow: none; flex: 1; padding: 8px; outline: none; background: transparent;">
			</div>
			<button type="button" id="eo-scan-cookies-btn" class="button button-secondary">
				<span class="dashicons dashicons-search" style="margin-top: 3px;"></span> <?php esc_html_e( 'Scanner automatique', 'eo-tools' ); ?>
			</button>
			<button type="button" id="eo-add-cookie-btn" class="button button-primary">
				+ <?php esc_html_e( 'Ajouter un cookie', 'eo-tools' ); ?>
			</button>
		</div>
			
		<div style="display: flex; gap: 20px;">
			<!-- Sidebar Categories -->
			<div style="width: 250px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
				<ul id="eo-cookie-categories-list" style="margin: 0; padding: 0; list-style: none;">
					<li class="eo-cookie-cat-item active" data-cat="strictly-necessary" style="padding: 15px; border-bottom: 1px solid #ccd0d4; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
						<span><?php esc_html_e( 'Nécessaire', 'eo-tools' ); ?></span>
						<span class="count" style="color: #3b82f6; font-weight: bold;">(0)</span>
					</li>
					<li class="eo-cookie-cat-item" data-cat="functional" style="padding: 15px; border-bottom: 1px solid #ccd0d4; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
						<span><?php esc_html_e( 'Fonctionnelle', 'eo-tools' ); ?></span>
						<span class="count" style="color: #3b82f6; font-weight: bold;">(0)</span>
					</li>
					<li class="eo-cookie-cat-item" data-cat="analytics" style="padding: 15px; border-bottom: 1px solid #ccd0d4; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
						<span><?php esc_html_e( 'Analytique', 'eo-tools' ); ?></span>
						<span class="count" style="color: #3b82f6; font-weight: bold;">(0)</span>
					</li>
					<li class="eo-cookie-cat-item" data-cat="performance" style="padding: 15px; border-bottom: 1px solid #ccd0d4; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
						<span><?php esc_html_e( 'Performance', 'eo-tools' ); ?></span>
						<span class="count" style="color: #3b82f6; font-weight: bold;">(0)</span>
					</li>
					<li class="eo-cookie-cat-item" data-cat="marketing" style="padding: 15px; border-bottom: 1px solid #ccd0d4; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
						<span><?php esc_html_e( 'Publicité', 'eo-tools' ); ?></span>
						<span class="count" style="color: #3b82f6; font-weight: bold;">(0)</span>
					</li>
					<li class="eo-cookie-cat-item" data-cat="social" style="padding: 15px; border-bottom: 1px solid #ccd0d4; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
						<span><?php esc_html_e( 'Réseaux sociaux', 'eo-tools' ); ?></span>
						<span class="count" style="color: #3b82f6; font-weight: bold;">(0)</span>
					</li>
					<li class="eo-cookie-cat-item" data-cat="others" style="padding: 15px; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
						<span><?php esc_html_e( 'Autres', 'eo-tools' ); ?></span>
						<span class="count" style="color: #3b82f6; font-weight: bold;">(0)</span>
					</li>
				</ul>
			</div>

			<!-- Main Content -->
			<div style="flex: 1;">
				<div id="eo-cookie-list-container" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; min-height: 300px;">
					<h3 id="eo-cookie-current-cat-title" style="margin-top: 0; font-size: 1.2rem;"><?php esc_html_e( 'Nécessaire', 'eo-tools' ); ?></h3>
					<p id="eo-cookie-current-cat-desc" class="description" style="margin-bottom: 20px;"><?php esc_html_e( 'Ces cookies sont indispensables au bon fonctionnement du site.', 'eo-tools' ); ?></p>
					
					<div id="eo-cookie-items" style="display: flex; flex-direction: column; gap: 15px;">
						<!-- Filled via JS -->
						<p><?php esc_html_e( 'Chargement...', 'eo-tools' ); ?></p>
					</div>
				</div>
			</div>
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
						<th scope="row"><label for="eo-cookie-active"><?php esc_html_e( 'Statut', 'eo-tools' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" id="eo-cookie-active" checked>
								<?php esc_html_e( 'Actif (affiché sur le site et soumis au consentement)', 'eo-tools' ); ?>
							</label>
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

	<!-- Scan History Section -->
	<div class="eo-card" style="margin-top: 30px;">
		<h2 style="margin: 0 0 20px 0;"><?php esc_html_e( 'Historique des Scans', 'eo-tools' ); ?></h2>
		<table class="wp-list-table widefat fixed striped" style="border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date du scan', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Statut', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Cookies trouvés', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Nouveaux ajoutés', 'eo-tools' ); ?></th>
				</tr>
			</thead>
			<tbody id="eo-scan-history-list">
				<tr>
					<td colspan="4" style="text-align: center; color: #64748b; font-style: italic; padding: 15px;">
						<?php esc_html_e( 'Chargement de l\'historique...', 'eo-tools' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
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
							$status_label = esc_html__( 'PARTIEL', 'eo-tools' );
							$status_style = 'color: #c2410c; background: #fff7ed; padding: 4px 12px; border-radius: 4px; font-weight: bold; border: 1px solid #fdba74; font-size: 13px; letter-spacing: 0.5px;';
						}
						?>
						<tr>
							<td><code><?php echo esc_html( $log->consent_id ); ?></code></td>
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

	<?php endif; ?>
