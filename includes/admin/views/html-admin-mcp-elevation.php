<?php
/**
 * "Autorisations" tab of EO Tools > MCP: temporary rights asked by the AI,
 * approved by an administrator.
 *
 * @package EoTools
 */

use EoTools\Includes\Mcp\Eotools_Mcp_Elevation;
use EoTools\Includes\Mcp\Eotools_Mcp_Plugin_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$done     = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$messages = array(
	'approve' => __( 'Autorisation accordée. Elle s\'arrêtera d\'elle-même à la fin du délai.', 'eo-tools' ),
	'reject'  => __( 'Demande refusée.', 'eo-tools' ),
	'revoke'  => __( 'Autorisation révoquée : l\'IA ne peut plus modifier ces extensions.', 'eo-tools' ),
	'undo'    => __( 'Les changements de l\'IA ont été annulés : les extensions sont revenues à leur état d\'origine.', 'eo-tools' ),
	'created' => __( 'Compte créé. Le nouvel utilisateur a reçu un e-mail pour choisir son mot de passe.', 'eo-tools' ),
	'secret'  => __( 'Clé enregistrée. Elle n\'est jamais transmise à l\'IA.', 'eo-tools' ),
);
$roles = wp_roles()->get_names();
$plugin_actions = array(
	'activate'   => __( 'À activer', 'eo-tools' ),
	'deactivate' => __( 'À désactiver', 'eo-tools' ),
);
$statuses = array(
	Eotools_Mcp_Elevation::STATUS_PENDING  => __( 'En attente', 'eo-tools' ),
	Eotools_Mcp_Elevation::STATUS_ACTIVE   => __( 'Active', 'eo-tools' ),
	Eotools_Mcp_Elevation::STATUS_REJECTED => __( 'Refusée', 'eo-tools' ),
	Eotools_Mcp_Elevation::STATUS_REVOKED  => __( 'Révoquée', 'eo-tools' ),
	Eotools_Mcp_Elevation::STATUS_EXPIRED  => __( 'Expirée', 'eo-tools' ),
	Eotools_Mcp_Elevation::STATUS_DONE     => __( 'Effectuée', 'eo-tools' ),
);
$durations = array( 15, 30, 60, 120 );
$date_fmt  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
$all       = Eotools_Mcp_Elevation::get_all();

$user_name = function ( $user_id ) {
	$user = get_userdata( (int) $user_id );
	return $user ? $user->display_name : __( 'Utilisateur supprimé', 'eo-tools' );
};

$plugin_name = function ( $request, $file ) {
	return isset( $request['plugins'][ $file ]['name'] ) ? $request['plugins'][ $file ]['name'] : $file;
};

$role_name = function ( $role ) use ( $roles ) {
	return isset( $roles[ $role ] ) ? translate_user_role( $roles[ $role ] ) : $role;
};

// What a request is about, in a few words (history table).
$request_subject = function ( $request ) use ( $role_name ) {
	if ( Eotools_Mcp_Elevation::SCOPE_CREATE_USER === Eotools_Mcp_Elevation::scope_of( $request ) ) {
		/* translators: 1: e-mail, 2: role */
		return sprintf( __( 'Compte %1$s (%2$s)', 'eo-tools' ), $request['user_data']['email'], $role_name( $request['user_data']['role'] ) );
	}

	return implode( ', ', wp_list_pluck( $request['plugins'], 'name' ) );
};

$secrets = Eotools_Mcp_Plugin_Settings::get_all_secrets();
?>

<?php if ( 'error' === $done ) : ?>
	<?php
	$error = get_transient( 'eo_tools_elevation_notice_' . get_current_user_id() );
	delete_transient( 'eo_tools_elevation_notice_' . get_current_user_id() );
	?>
	<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ? $error : __( 'L\'action a échoué.', 'eo-tools' ) ); ?></p></div>
<?php elseif ( isset( $messages[ $done ] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $messages[ $done ] ); ?></p></div>
<?php endif; ?>

<p class="eo-mcp-intro"><?php esc_html_e( 'Votre IA peut demander le droit d\'activer ou de désactiver des extensions pendant un temps limité, ou la création d\'un compte utilisateur. Rien ne se passe sans votre accord : vous validez ici, en retapant votre mot de passe. L\'autorisation ne vaut que pour la connexion IA qui l\'a demandée et pour les extensions listées, puis s\'arrête d\'elle-même.', 'eo-tools' ); ?></p>
<p class="eo-mcp-status is-warning"><span class="dashicons dashicons-shield"></span><?php esc_html_e( 'Ne donnez jamais de mot de passe ou de code à votre IA dans la conversation : la validation se fait uniquement sur cette page.', 'eo-tools' ); ?></p>

<?php if ( ! $all ) : ?>
	<p class="eo-mcp-soon"><?php esc_html_e( 'Aucune demande pour le moment.', 'eo-tools' ); ?></p>
<?php endif; ?>

<div class="eo-mcp-elevations">
	<?php foreach ( $all as $request ) : ?>
		<?php
		$status   = $request['status'];
		$is_user  = Eotools_Mcp_Elevation::SCOPE_CREATE_USER === Eotools_Mcp_Elevation::scope_of( $request );
		$open     = in_array( $status, array( Eotools_Mcp_Elevation::STATUS_PENDING, Eotools_Mcp_Elevation::STATUS_ACTIVE ), true );
		$undoable = Eotools_Mcp_Elevation::get_undoable( $request );
		if ( ! $open && ! $undoable ) {
			continue;
		}
		$form_start = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eo-mcp-elevation-form">'
			. '<input type="hidden" name="action" value="' . esc_attr( Eotools_Mcp_Elevation::ACTION ) . '">'
			. '<input type="hidden" name="id" value="' . esc_attr( $request['id'] ) . '">'
			. wp_nonce_field( Eotools_Mcp_Elevation::ACTION . '_' . $request['id'], '_wpnonce', true, false );
		?>
		<div class="eo-mcp-card eo-mcp-elevation is-<?php echo esc_attr( $status ); ?>" id="elevation-<?php echo esc_attr( $request['id'] ); ?>">
			<div class="eo-mcp-elevation-header">
				<h3><?php echo esc_html( $is_user ? __( 'Créer un compte utilisateur', 'eo-tools' ) : __( 'Modifier des extensions', 'eo-tools' ) ); ?></h3>
				<span class="eo-mcp-elevation-status is-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $statuses[ $status ] ); ?></span>
			</div>

			<p class="eo-mcp-elevation-meta">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: connection name, 2: user, 3: date */
						__( 'Demandé par %1$s (compte de %2$s), le %3$s', 'eo-tools' ),
						$request['label'],
						$user_name( $request['user'] ),
						wp_date( $date_fmt, $request['created'] )
					)
				);
				?>
			</p>

			<?php if ( '' !== $request['reason'] ) : ?>
				<blockquote class="eo-mcp-elevation-reason"><?php echo esc_html( $request['reason'] ); ?></blockquote>
			<?php endif; ?>

			<?php if ( $is_user ) : ?>
				<ul class="eo-mcp-elevation-plugins">
					<li><strong><?php esc_html_e( 'E-mail :', 'eo-tools' ); ?></strong> <?php echo esc_html( $request['user_data']['email'] ); ?></li>
					<li><strong><?php esc_html_e( 'Rôle :', 'eo-tools' ); ?></strong> <?php echo esc_html( $role_name( $request['user_data']['role'] ) ); ?></li>
					<?php if ( '' !== trim( $request['user_data']['first_name'] . $request['user_data']['last_name'] ) ) : ?>
						<li><strong><?php esc_html_e( 'Nom :', 'eo-tools' ); ?></strong> <?php echo esc_html( trim( $request['user_data']['first_name'] . ' ' . $request['user_data']['last_name'] ) ); ?></li>
					<?php endif; ?>
				</ul>
				<p class="eo-mcp-elevation-meta"><?php esc_html_e( 'En validant, le compte est créé tout de suite et WordPress envoie à cette adresse un lien pour choisir le mot de passe. Personne d\'autre ne le connaît, pas même l\'IA.', 'eo-tools' ); ?></p>
			<?php endif; ?>

			<ul class="eo-mcp-elevation-plugins">
				<?php foreach ( $request['plugins'] as $plugin ) : ?>
					<?php $plugin_action = Eotools_Mcp_Elevation::plugin_action( $plugin ); ?>
					<li>
						<span class="eo-mcp-elevation-action is-<?php echo esc_attr( $plugin_action ); ?>"><?php echo esc_html( $plugin_actions[ $plugin_action ] ); ?></span>
						<strong><?php echo esc_html( $plugin['name'] ); ?></strong>
						<?php
						/* translators: 1: version, 2: author */
						echo esc_html( sprintf( __( 'version %1$s, par %2$s', 'eo-tools' ), $plugin['version'], $plugin['author'] ) );
						?>
						<code><?php echo esc_html( $plugin['slug'] ); ?></code>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( Eotools_Mcp_Elevation::STATUS_PENDING === $status ) : ?>

				<?php echo $form_start; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
					<input type="hidden" name="do" value="approve">
					<p class="eo-mcp-elevation-fields">
						<?php if ( ! $is_user ) : ?>
						<label>
							<?php esc_html_e( 'Durée', 'eo-tools' ); ?>
							<select name="duration">
								<?php
								// The requested duration, or the closest longer choice.
								$default_duration = Eotools_Mcp_Elevation::MAX_DURATION;
								foreach ( $durations as $minutes ) {
									if ( $minutes >= $request['duration'] ) {
										$default_duration = $minutes;
										break;
									}
								}
								?>
								<?php foreach ( $durations as $minutes ) : ?>
									<option value="<?php echo esc_attr( $minutes ); ?>" <?php selected( $minutes, $default_duration ); ?>>
										<?php
										/* translators: %d: minutes */
										echo esc_html( sprintf( _n( '%d minute', '%d minutes', $minutes, 'eo-tools' ), $minutes ) );
										?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
						<?php endif; ?>
						<label>
							<?php esc_html_e( 'Votre mot de passe', 'eo-tools' ); ?>
							<input type="password" name="password" autocomplete="current-password" required>
						</label>
						<button type="submit" class="button button-primary"><?php echo esc_html( $is_user ? __( 'Créer le compte', 'eo-tools' ) : __( 'Autoriser', 'eo-tools' ) ); ?></button>
					</p>
				</form>
				<?php echo $form_start; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
					<input type="hidden" name="do" value="reject">
					<button type="submit" class="button-link eo-mcp-elevation-reject"><?php esc_html_e( 'Refuser la demande', 'eo-tools' ); ?></button>
				</form>

			<?php else : ?>

				<?php if ( Eotools_Mcp_Elevation::STATUS_ACTIVE === $status ) : ?>
					<p class="eo-mcp-status is-success">
						<span class="dashicons dashicons-clock"></span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: user, 2: remaining time, 3: end time */
								__( 'Autorisée par %1$s. Encore %2$s (jusqu\'à %3$s).', 'eo-tools' ),
								$user_name( $request['approver'] ),
								human_time_diff( time(), $request['expires'] ),
								wp_date( get_option( 'time_format' ), $request['expires'] )
							)
						);
						?>
					</p>
				<?php endif; ?>

				<?php if ( $request['actions'] ) : ?>
					<h4><?php esc_html_e( 'Journal', 'eo-tools' ); ?></h4>
					<ul class="eo-mcp-elevation-log">
						<?php foreach ( $request['actions'] as $action ) : ?>
							<li>
								<?php echo esc_html( wp_date( $date_fmt, $action['time'] ) ); ?> –
								<?php
								$name     = $plugin_name( $request, $action['file'] );
								$by_human = isset( $action['user'] );
								$activate = 'activate' === $action['type'];
								if ( 'ok' !== $action['result'] ) {
									echo esc_html(
										$activate
											/* translators: 1: plugin name, 2: error */
											? sprintf( __( 'Échec de l\'activation de %1$s : %2$s', 'eo-tools' ), $name, $action['result'] )
											/* translators: 1: plugin name, 2: error */
											: sprintf( __( 'Échec de la désactivation de %1$s : %2$s', 'eo-tools' ), $name, $action['result'] )
									);
								} elseif ( $by_human ) {
									echo esc_html(
										$activate
											/* translators: 1: plugin name, 2: user */
											? sprintf( __( '%1$s réactivée par %2$s (annulation)', 'eo-tools' ), $name, $user_name( $action['user'] ) )
											/* translators: 1: plugin name, 2: user */
											: sprintf( __( '%1$s désactivée par %2$s (annulation)', 'eo-tools' ), $name, $user_name( $action['user'] ) )
									);
								} else {
									echo esc_html(
										$activate
											/* translators: %s: plugin name */
											? sprintf( __( '%s activée par l\'IA', 'eo-tools' ), $name )
											/* translators: %s: plugin name */
											: sprintf( __( '%s désactivée par l\'IA', 'eo-tools' ), $name )
									);
								}
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<div class="eo-mcp-elevation-actions">
					<?php if ( Eotools_Mcp_Elevation::STATUS_ACTIVE === $status ) : ?>
						<?php echo $form_start; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
							<input type="hidden" name="do" value="revoke">
							<button type="submit" class="button"><?php esc_html_e( 'Révoquer maintenant', 'eo-tools' ); ?></button>
						</form>
					<?php endif; ?>
					<?php if ( $undoable ) : ?>
						<?php echo $form_start; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
							<input type="hidden" name="do" value="undo">
							<button type="submit" class="button">
								<?php
								/* translators: %d: number of plugins */
								echo esc_html( sprintf( _n( 'Annuler les changements de l\'IA (%d extension)', 'Annuler les changements de l\'IA (%d extensions)', count( $undoable ), 'eo-tools' ), count( $undoable ) ) );
								?>
							</button>
						</form>
					<?php endif; ?>
				</div>

			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>

<?php if ( $secrets ) : ?>
	<h2 class="eo-mcp-group-title"><?php esc_html_e( 'Clés secrètes des extensions', 'eo-tools' ); ?></h2>
	<p><?php esc_html_e( 'Votre IA configure les extensions, mais les clés secrètes et mots de passe ne passent jamais par la conversation : saisissez-les ici. Une clé enregistrée n\'est plus jamais affichée, ni à vous ni à l\'IA.', 'eo-tools' ); ?></p>
	<div class="eo-mcp-elevations">
		<?php foreach ( $secrets as $secret ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eo-mcp-card eo-mcp-secret" id="secret-<?php echo esc_attr( $secret['plugin'] . '-' . $secret['name'] ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( Eotools_Mcp_Plugin_Settings::SECRET_ACTION ); ?>">
				<input type="hidden" name="plugin" value="<?php echo esc_attr( $secret['plugin'] ); ?>">
				<input type="hidden" name="name" value="<?php echo esc_attr( $secret['name'] ); ?>">
				<?php wp_nonce_field( Eotools_Mcp_Plugin_Settings::SECRET_ACTION . '_' . $secret['plugin'] . '_' . $secret['name'] ); ?>
				<div class="eo-mcp-elevation-header">
					<h3><?php echo esc_html( $secret['plugin_label'] . ' – ' . $secret['label'] ); ?></h3>
					<span class="eo-mcp-elevation-status <?php echo $secret['filled'] ? 'is-active' : 'is-pending'; ?>">
						<?php echo esc_html( $secret['filled'] ? __( 'Renseignée', 'eo-tools' ) : __( 'À saisir', 'eo-tools' ) ); ?>
					</span>
				</div>
				<?php if ( $secret['help'] ) : ?>
					<p class="eo-mcp-elevation-meta"><?php echo esc_html( $secret['help'] ); ?></p>
				<?php endif; ?>
				<p class="eo-mcp-elevation-fields">
					<label>
						<?php echo esc_html( $secret['filled'] ? __( 'Nouvelle clé (remplace l\'actuelle)', 'eo-tools' ) : __( 'Clé', 'eo-tools' ) ); ?>
						<input type="password" name="secret" autocomplete="off" required>
					</label>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'eo-tools' ); ?></button>
				</p>
			</form>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php
$history = array_filter(
	$all,
	function ( $request ) {
		return ! in_array( $request['status'], array( Eotools_Mcp_Elevation::STATUS_PENDING, Eotools_Mcp_Elevation::STATUS_ACTIVE ), true );
	}
);
?>
<?php if ( $history ) : ?>
	<h2 class="eo-mcp-group-title"><?php esc_html_e( 'Historique', 'eo-tools' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'eo-tools' ); ?></th>
				<th><?php esc_html_e( 'Connexion', 'eo-tools' ); ?></th>
				<th><?php esc_html_e( 'Demande', 'eo-tools' ); ?></th>
				<th><?php esc_html_e( 'Statut', 'eo-tools' ); ?></th>
				<th><?php esc_html_e( 'Changements de l\'IA', 'eo-tools' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $history as $request ) : ?>
				<?php
				$changes = array();
				foreach ( $request['actions'] as $action ) {
					if ( 'create_user' === $action['type'] ) {
						$changes[] = get_userdata( $action['user_id'] ) ? __( 'Compte créé', 'eo-tools' ) : __( 'Compte créé, supprimé depuis', 'eo-tools' );
					} elseif ( 'ok' === $action['result'] && ! isset( $action['user'] ) ) {
						$changes[] = sprintf(
							'activate' === $action['type']
								/* translators: %s: plugin name */
								? __( '%s activée', 'eo-tools' )
								/* translators: %s: plugin name */
								: __( '%s désactivée', 'eo-tools' ),
							$plugin_name( $request, $action['file'] )
						);
					}
				}
				?>
				<tr>
					<td><?php echo esc_html( wp_date( $date_fmt, $request['created'] ) ); ?></td>
					<td><?php echo esc_html( $request['label'] ); ?></td>
					<td><?php echo esc_html( $request_subject( $request ) ); ?></td>
					<td><?php echo esc_html( $statuses[ $request['status'] ] ); ?></td>
					<td><?php echo esc_html( $changes ? implode( ', ', array_unique( $changes ) ) : '–' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
