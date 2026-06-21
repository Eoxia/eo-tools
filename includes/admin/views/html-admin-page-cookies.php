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
		</div>

	</form>
</div>
