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

$settings = get_option( 'eo_tools_cookies_settings', array( 'active' => false, 'duration' => 12 ) );

global $wpdb;
$table_name = $wpdb->prefix . 'eotools_cookie_stats';

// Fetch stats
$today = current_time( 'Y-m-d' );
$stats_today = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE stat_date = %s", $today ) );

$stats_week = $wpdb->get_row( "SELECT SUM(views) as views, SUM(accepts) as accepts, SUM(refusals) as refusals, SUM(customs) as customs FROM $table_name WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)" );

$stats_month = $wpdb->get_row( "SELECT SUM(views) as views, SUM(accepts) as accepts, SUM(refusals) as refusals, SUM(customs) as customs FROM $table_name WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)" );
?>
<div class="wrap eo-admin-wrap">
	<h1><?php esc_html_e( 'Gestion des Cookies', 'eo-tools' ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'eo_tools_cookies_settings' ); ?>

		<div class="eo-card">
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
			// Prepare data for Chart.js
			$chart_data = $wpdb->get_results( "SELECT stat_date, views, accepts, refusals, customs FROM $table_name WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY stat_date ASC" );
			
			$labels = array();
			$views_data = array();
			$consent_data = array(); // % of consent

			// Fill missing days with 0
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
					$c = intval( $data_map[$d]->customs );
					
					$views_data[] = $v;
					
					// Calculate consent % : (accepts + custom) / views
					// We can just use accepts / views as purely "Consentement" or accepts+customs. Let's use accepts.
					if ( $v > 0 ) {
						$percent = round( ( $a / $v ) * 100 );
					} else {
						$percent = 0;
					}
					$consent_data[] = $percent;
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

	</form>
</div>
