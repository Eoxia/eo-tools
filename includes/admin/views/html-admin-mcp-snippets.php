<?php
/**
 * "Snippets" tab of EO Tools > MCP: code proposed by the AI, switched on or off by a human.
 *
 * @package EoTools
 */

use EoTools\Includes\Mcp\Eotools_Mcp_Snippets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$done     = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$messages = array(
	'on'     => __( 'Snippet activé.', 'eo-tools' ),
	'off'    => __( 'Snippet désactivé.', 'eo-tools' ),
	'apply'  => __( 'Nouvelle version appliquée. L\'ancienne reste disponible dans les révisions.', 'eo-tools' ),
	'reject' => __( 'Nouvelle version rejetée : le snippet garde son code actuel.', 'eo-tools' ),
	'delete' => __( 'Snippet supprimé.', 'eo-tools' ),
);
$types    = array(
	'css' => 'CSS',
	'js'  => 'JS',
	'php' => 'PHP',
);
$scopes   = array(
	'everywhere' => __( 'Partout', 'eo-tools' ),
	'front'      => __( 'Site public', 'eo-tools' ),
	'admin'      => __( 'Administration', 'eo-tools' ),
);

delete_option( Eotools_Mcp_Snippets::ALERT );
?>

<?php if ( 'error' === $done ) : ?>
	<?php
	$error = get_transient( 'eo_tools_snippet_notice_' . get_current_user_id() );
	delete_transient( 'eo_tools_snippet_notice_' . get_current_user_id() );
	?>
	<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ? $error : __( 'L\'action a échoué.', 'eo-tools' ) ); ?></p></div>
<?php elseif ( isset( $messages[ $done ] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $messages[ $done ] ); ?></p></div>
<?php endif; ?>

<p class="eo-mcp-intro"><?php esc_html_e( 'Quand une demande est impossible avec les réglages, les blocs ou les modèles, votre IA peut proposer un petit morceau de code (CSS, JS ou PHP). Il reste désactivé tant que vous ne l\'activez pas ici, et vous pouvez le désactiver à tout moment. Il est conservé lors des mises à jour du thème et des extensions.', 'eo-tools' ); ?></p>

<?php if ( ! Eotools_Mcp_Snippets::user_can( 'php' ) ) : ?>
	<p class="eo-mcp-status is-warning"><span class="dashicons dashicons-info"></span><?php esc_html_e( 'Les snippets PHP sont désactivés sur ce site (modification des fichiers interdite, ou votre compte ne peut pas modifier les extensions). Les snippets CSS et JS fonctionnent.', 'eo-tools' ); ?></p>
<?php elseif ( ! Eotools_Mcp_Snippets::dir_ready() ) : ?>
	<p class="eo-mcp-status is-warning"><span class="dashicons dashicons-warning"></span>
		<?php
		/* translators: %s: folder path. */
		echo esc_html( sprintf( __( 'Le dossier %s n\'est pas accessible en écriture : les snippets PHP ne peuvent pas fonctionner.', 'eo-tools' ), str_replace( ABSPATH, '', Eotools_Mcp_Snippets::dir() ) ) );
		?>
	</p>
<?php endif; ?>

<?php $snippets = Eotools_Mcp_Snippets::all( true ); ?>

<?php if ( ! $snippets ) : ?>

	<div class="eo-mcp-card eo-mcp-empty">
		<span class="dashicons dashicons-editor-code"></span>
		<h2><?php esc_html_e( 'Aucun snippet pour le moment', 'eo-tools' ); ?></h2>
		<p><?php esc_html_e( 'Demandez par exemple à votre IA : « Masque le nom de l\'auteur sur les articles » ou « Ajoute un shortcode qui affiche l\'année en cours ».', 'eo-tools' ); ?></p>
	</div>

<?php else : ?>

	<div class="eo-mcp-snippets">
		<?php foreach ( $snippets as $snippet ) : ?>
			<?php
			$can       = Eotools_Mcp_Snippets::user_can( $snippet['type'] );
			$pending   = isset( $snippet['pending_version'] ) && is_array( $snippet['pending_version'] ) ? $snippet['pending_version'] : null;
			$sensitive = Eotools_Mcp_Snippets::sensitive( $pending ? $pending['code'] : $snippet['code'], $snippet['type'] );
			$preview   = Eotools_Mcp_Snippets::preview_url( $snippet['id'] );
			$state     = $snippet['active'] ? 'is-active' : ( ! empty( $snippet['awaiting_activation'] ) ? 'is-waiting' : 'is-inactive' );
			$confirms  = array(
				'php' => __( 'Activer ce code PHP ? Le site est d\'abord testé avec : en cas d\'erreur, il reste désactivé.', 'eo-tools' ),
				'js'  => __( 'Activer ce JavaScript ? Il s\'exécutera dans le navigateur de vos visiteurs (ou des administrateurs, selon sa portée).', 'eo-tools' ),
				'css' => __( 'Activer ce CSS sur le site ?', 'eo-tools' ),
			);
			$confirm   = $confirms[ $snippet['type'] ];
			if ( $sensitive ) {
				/* translators: %s: list of PHP functions. */
				$confirm .= ' ' . sprintf( __( 'Attention : ce code utilise des fonctions sensibles (%s).', 'eo-tools' ), implode( ', ', $sensitive ) );
			}
			?>
			<div class="eo-mcp-card eo-mcp-snippet <?php echo esc_attr( $state ); ?>" id="snippet-<?php echo esc_attr( $snippet['id'] ); ?>">
				<div class="eo-mcp-snippet-head">
					<div>
						<h2><?php echo esc_html( $snippet['title'] ); ?></h2>
						<p class="eo-mcp-snippet-meta">
							<span class="eo-mcp-badge"><?php echo esc_html( $types[ $snippet['type'] ] ); ?></span>
							<span class="eo-mcp-badge"><?php echo esc_html( $scopes[ $snippet['scope'] ] ); ?></span>
							<?php if ( $snippet['active'] ) : ?>
								<span class="eo-mcp-badge is-success"><?php esc_html_e( 'Actif', 'eo-tools' ); ?></span>
							<?php elseif ( ! empty( $snippet['awaiting_activation'] ) ) : ?>
								<span class="eo-mcp-badge is-warning"><?php esc_html_e( 'Proposé par l\'IA, à activer', 'eo-tools' ); ?></span>
							<?php else : ?>
								<span class="eo-mcp-badge"><?php esc_html_e( 'Désactivé', 'eo-tools' ); ?></span>
							<?php endif; ?>
							<?php if ( $pending ) : ?>
								<span class="eo-mcp-badge is-warning"><?php esc_html_e( 'Nouvelle version à valider', 'eo-tools' ); ?></span>
							<?php endif; ?>
						</p>
					</div>
					<?php if ( $can ) : ?>
						<div class="eo-mcp-actions">
							<?php if ( $preview ) : ?>
								<a class="button" href="<?php echo esc_url( $preview ); ?>" target="_blank"><?php esc_html_e( 'Aperçu', 'eo-tools' ); ?></a>
							<?php endif; ?>
							<?php if ( $snippet['active'] ) : ?>
								<a class="button" href="<?php echo esc_url( Eotools_Mcp_Snippets::action_url( $snippet['id'], 'off' ) ); ?>"><?php esc_html_e( 'Désactiver', 'eo-tools' ); ?></a>
							<?php else : ?>
								<a class="button button-primary" href="<?php echo esc_url( Eotools_Mcp_Snippets::action_url( $snippet['id'], 'on' ) ); ?>" onclick="return confirm('<?php echo esc_js( $confirm ); ?>');"><?php esc_html_e( 'Activer', 'eo-tools' ); ?></a>
							<?php endif; ?>
							<a class="button-link eo-mcp-link-danger" href="<?php echo esc_url( Eotools_Mcp_Snippets::action_url( $snippet['id'], 'delete' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Supprimer ce snippet ? Il est mis à la corbeille et cesse de s\'exécuter.', 'eo-tools' ) ); ?>');"><?php esc_html_e( 'Supprimer', 'eo-tools' ); ?></a>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( '' !== $snippet['description'] ) : ?>
					<p><?php echo esc_html( $snippet['description'] ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $snippet['error'] ) ) : ?>
					<p class="eo-mcp-status is-error"><span class="dashicons dashicons-dismiss"></span>
						<?php
						/* translators: %s: PHP error. */
						echo esc_html( sprintf( __( 'Dernière erreur : %s', 'eo-tools' ), $snippet['error'] ) );
						?>
					</p>
				<?php endif; ?>

				<?php if ( $sensitive ) : ?>
					<p class="eo-mcp-status is-warning"><span class="dashicons dashicons-shield"></span>
						<?php
						/* translators: %s: list of PHP functions. */
						echo esc_html( sprintf( __( 'Ce code utilise des fonctions sensibles (%s). Ne l\'activez que si vous comprenez ce qu\'il fait.', 'eo-tools' ), implode( ', ', $sensitive ) ) );
						?>
					</p>
				<?php endif; ?>

				<?php if ( $pending ) : ?>
					<div class="eo-mcp-snippet-pending">
						<p><strong><?php esc_html_e( 'Nouvelle version proposée par l\'IA', 'eo-tools' ); ?></strong> — <?php esc_html_e( 'le code actuel continue de tourner tant que vous ne l\'appliquez pas.', 'eo-tools' ); ?></p>
						<?php if ( $pending['description'] !== $snippet['description'] ) : ?>
							<p><?php echo esc_html( $pending['description'] ); ?></p>
						<?php endif; ?>
						<pre><code><?php echo esc_html( $pending['code'] ); ?></code></pre>
						<?php if ( $can ) : ?>
							<p class="eo-mcp-actions">
								<a class="button button-primary" href="<?php echo esc_url( Eotools_Mcp_Snippets::action_url( $snippet['id'], 'apply' ) ); ?>" onclick="return confirm('<?php echo esc_js( 'php' === $snippet['type'] ? __( 'Appliquer la nouvelle version ? Le site est d\'abord testé avec : en cas d\'erreur, rien ne change.', 'eo-tools' ) : __( 'Appliquer la nouvelle version ?', 'eo-tools' ) ); ?>');"><?php esc_html_e( 'Appliquer la nouvelle version', 'eo-tools' ); ?></a>
								<a class="button" href="<?php echo esc_url( Eotools_Mcp_Snippets::action_url( $snippet['id'], 'reject' ) ); ?>"><?php esc_html_e( 'Rejeter', 'eo-tools' ); ?></a>
							</p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<details <?php echo ( $pending || $snippet['active'] ) ? '' : 'open'; ?>>
					<summary><?php echo esc_html( $pending ? __( 'Voir le code actuel', 'eo-tools' ) : __( 'Voir le code', 'eo-tools' ) ); ?></summary>
					<pre><code><?php echo esc_html( $snippet['code'] ); ?></code></pre>
				</details>
			</div>
		<?php endforeach; ?>
	</div>

<?php endif; ?>

<?php if ( Eotools_Mcp_Snippets::user_can( 'php' ) ) : ?>
	<div class="eo-mcp-card eo-mcp-safe">
		<h2><span class="dashicons dashicons-sos"></span> <?php esc_html_e( 'Adresse de secours', 'eo-tools' ); ?></h2>
		<p><?php esc_html_e( 'Chaque snippet PHP est testé avant son activation, et un snippet qui provoque une erreur est désactivé automatiquement. Si votre site affiche malgré tout une erreur, ouvrez cette adresse secrète : elle désactive tous les snippets PHP. Gardez-la en lieu sûr et ne la partagez pas.', 'eo-tools' ); ?></p>
		<p><input type="text" class="large-text code" readonly value="<?php echo esc_attr( Eotools_Mcp_Snippets::safe_url() ); ?>" onclick="this.select();"></p>
	</div>
<?php endif; ?>
