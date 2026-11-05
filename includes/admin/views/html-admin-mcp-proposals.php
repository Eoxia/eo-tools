<?php
/**
 * "Propositions" tab of EO Tools > MCP: pending AI change proposals.
 *
 * Expects: $pending_proposals, $pending_templates.
 *
 * @package EoTools
 */

use EoTools\Includes\Mcp\Eotools_Mcp_Proposals;
use EoTools\Includes\Mcp\Eotools_Mcp_Templates;
use EoTools\Includes\Mcp\Eotools_Mcp_Styles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$done = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<?php if ( 'applied' === $done ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'La modification a été appliquée. La version précédente reste disponible dans les révisions.', 'eo-tools' ); ?></p></div>
<?php elseif ( 'rejected' === $done ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'La proposition a été rejetée.', 'eo-tools' ); ?></p></div>
<?php endif; ?>

<p class="eo-mcp-intro"><?php esc_html_e( 'Les modifications proposées par une IA sur vos contenus publiés, vos modèles et vos styles attendent ici votre validation. Rien n\'est publié tant que vous ne cliquez pas sur « Appliquer ».', 'eo-tools' ); ?></p>

<?php if ( ! $pending_proposals && ! $pending_templates && empty( $pending_styles ) ) : ?>

	<div class="eo-mcp-card eo-mcp-empty">
		<span class="dashicons dashicons-yes-alt"></span>
		<h2><?php esc_html_e( 'Aucune proposition en attente', 'eo-tools' ); ?></h2>
	</div>

<?php endif; ?>

<?php if ( ! empty( $pending_styles ) ) : ?>

	<?php
	$styles_summary = get_post_meta( $pending_styles->ID, Eotools_Mcp_Styles::META_SUMMARY, true );
	$styles_nonce   = 'eo_tools_proposal_' . $pending_styles->ID;
	?>
	<h2 class="eo-mcp-group-title"><?php esc_html_e( 'Styles du site', 'eo-tools' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Les styles s\'appliquent à tout le site : vérifiez l\'aperçu avant d\'appliquer. Vous pourrez revenir en arrière depuis l\'historique des styles de l\'éditeur de site.', 'eo-tools' ); ?></p>
	<table class="widefat striped eo-mcp-proposals">
		<tbody>
			<tr>
				<td>
					<strong><?php echo esc_html( $styles_summary ? $styles_summary : __( 'Nouveaux styles', 'eo-tools' ) ); ?></strong>
				</td>
				<td><?php echo esc_html( get_date_from_gmt( $pending_styles->post_modified_gmt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
				<td>
					<?php if ( Eotools_Mcp_Styles::has_conflict( $pending_styles ) ) : ?>
						<span class="eo-mcp-badge is-warning"><?php esc_html_e( 'Styles modifiés depuis', 'eo-tools' ); ?></span>
					<?php else : ?>
						<span class="eo-mcp-badge"><?php esc_html_e( 'En attente', 'eo-tools' ); ?></span>
					<?php endif; ?>
				</td>
				<td class="eo-mcp-actions">
					<a class="button" href="<?php echo esc_url( Eotools_Mcp_Styles::preview_url( $pending_styles ) ); ?>" target="_blank"><?php esc_html_e( 'Aperçu', 'eo-tools' ); ?></a>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="eo_tools_reject_styles_proposal">
						<input type="hidden" name="proposal" value="<?php echo esc_attr( $pending_styles->ID ); ?>">
						<input type="hidden" name="eo_tools_from" value="admin">
						<?php wp_nonce_field( $styles_nonce ); ?>
						<button type="submit" class="button" onclick="return confirm('<?php echo esc_js( __( 'Rejeter cette proposition ? Elle sera supprimée.', 'eo-tools' ) ); ?>');"><?php esc_html_e( 'Rejeter', 'eo-tools' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="eo_tools_apply_styles_proposal">
						<input type="hidden" name="proposal" value="<?php echo esc_attr( $pending_styles->ID ); ?>">
						<input type="hidden" name="eo_tools_from" value="admin">
						<?php wp_nonce_field( $styles_nonce ); ?>
						<button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Appliquer ces styles à tout le site ?', 'eo-tools' ) ); ?>');"><?php esc_html_e( 'Appliquer', 'eo-tools' ); ?></button>
					</form>
				</td>
			</tr>
		</tbody>
	</table>

<?php endif; ?>

<?php if ( $pending_templates ) : ?>

	<h2 class="eo-mcp-group-title"><?php esc_html_e( 'Modèles du site', 'eo-tools' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Un modèle s\'applique à toutes les pages qui l\'utilisent : vérifiez l\'aperçu avant d\'appliquer. Vous pourrez revenir à la version du thème depuis l\'éditeur de site.', 'eo-tools' ); ?></p>
	<table class="widefat striped eo-mcp-proposals">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Modèle', 'eo-tools' ); ?></th>
				<th><?php esc_html_e( 'Proposée le', 'eo-tools' ); ?></th>
				<th><?php esc_html_e( 'État', 'eo-tools' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $pending_templates as $proposal ) : ?>
				<?php
				$template     = Eotools_Mcp_Templates::get_template( $proposal );
				$nonce_action = 'eo_tools_proposal_' . $proposal->ID;
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $template ? $template->title : $proposal->post_title ); ?></strong>
						<br><span class="description"><?php echo esc_html( $template && 'wp_template_part' === $template->type ? __( 'Partie de modèle', 'eo-tools' ) : __( 'Modèle de page', 'eo-tools' ) ); ?></span>
					</td>
					<td><?php echo esc_html( get_date_from_gmt( $proposal->post_modified_gmt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
					<td>
						<?php if ( ! $template ) : ?>
							<span class="eo-mcp-badge is-warning"><?php esc_html_e( 'Modèle supprimé', 'eo-tools' ); ?></span>
						<?php elseif ( Eotools_Mcp_Templates::has_conflict( $proposal, $template ) ) : ?>
							<span class="eo-mcp-badge is-warning"><?php esc_html_e( 'Modèle modifié depuis', 'eo-tools' ); ?></span>
						<?php else : ?>
							<span class="eo-mcp-badge"><?php esc_html_e( 'En attente', 'eo-tools' ); ?></span>
						<?php endif; ?>
					</td>
					<td class="eo-mcp-actions">
						<a class="button" href="<?php echo esc_url( Eotools_Mcp_Templates::preview_url( $proposal ) ); ?>" target="_blank"><?php esc_html_e( 'Aperçu', 'eo-tools' ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="eo_tools_reject_template_proposal">
							<input type="hidden" name="proposal" value="<?php echo esc_attr( $proposal->ID ); ?>">
							<input type="hidden" name="eo_tools_from" value="admin">
							<?php wp_nonce_field( $nonce_action ); ?>
							<button type="submit" class="button" onclick="return confirm('<?php echo esc_js( __( 'Rejeter cette proposition ? Elle sera supprimée.', 'eo-tools' ) ); ?>');"><?php esc_html_e( 'Rejeter', 'eo-tools' ); ?></button>
						</form>
						<?php if ( $template ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="eo_tools_apply_template_proposal">
								<input type="hidden" name="proposal" value="<?php echo esc_attr( $proposal->ID ); ?>">
								<input type="hidden" name="eo_tools_from" value="admin">
								<?php wp_nonce_field( $nonce_action ); ?>
								<button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Appliquer cette modification au modèle ? Toutes les pages qui l\'utilisent changeront.', 'eo-tools' ) ); ?>');"><?php esc_html_e( 'Appliquer', 'eo-tools' ); ?></button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

<?php endif; ?>

<?php if ( $pending_proposals ) : ?>

	<?php if ( $pending_templates || ! empty( $pending_styles ) ) : ?>
		<h2 class="eo-mcp-group-title"><?php esc_html_e( 'Contenus', 'eo-tools' ); ?></h2>
	<?php endif; ?>
	<table class="widefat striped eo-mcp-proposals">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Contenu', 'eo-tools' ); ?></th>
				<th><?php esc_html_e( 'Proposée le', 'eo-tools' ); ?></th>
				<th><?php esc_html_e( 'État', 'eo-tools' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $pending_proposals as $proposal ) : ?>
				<?php
				$source = Eotools_Mcp_Proposals::get_source( $proposal );
				if ( ! $source || ! current_user_can( 'edit_post', $source->ID ) ) {
					continue;
				}
				$nonce_action = 'eo_tools_proposal_' . $proposal->ID;
				?>
				<tr>
					<td>
						<strong><a href="<?php echo esc_url( get_permalink( $source ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $source ) ); ?></a></strong>
						<?php if ( $proposal->post_title !== $source->post_title ) : ?>
							<br><span class="description">
								<?php
								/* translators: %s: proposed title. */
								echo esc_html( sprintf( __( 'Nouveau titre proposé : %s', 'eo-tools' ), $proposal->post_title ) );
								?>
							</span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( get_date_from_gmt( $proposal->post_modified_gmt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
					<td>
						<?php if ( Eotools_Mcp_Proposals::has_conflict( $proposal, $source ) ) : ?>
							<span class="eo-mcp-badge is-warning"><?php esc_html_e( 'Contenu publié modifié depuis', 'eo-tools' ); ?></span>
						<?php else : ?>
							<span class="eo-mcp-badge"><?php esc_html_e( 'En attente', 'eo-tools' ); ?></span>
						<?php endif; ?>
					</td>
					<td class="eo-mcp-actions">
						<a class="button" href="<?php echo esc_url( get_preview_post_link( $proposal ) ); ?>" target="_blank"><?php esc_html_e( 'Aperçu', 'eo-tools' ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="eo_tools_reject_proposal">
							<input type="hidden" name="proposal" value="<?php echo esc_attr( $proposal->ID ); ?>">
							<input type="hidden" name="eo_tools_from" value="admin">
							<?php wp_nonce_field( $nonce_action ); ?>
							<button type="submit" class="button" onclick="return confirm('<?php echo esc_js( __( 'Rejeter cette proposition ? Elle sera supprimée.', 'eo-tools' ) ); ?>');"><?php esc_html_e( 'Rejeter', 'eo-tools' ); ?></button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="eo_tools_apply_proposal">
							<input type="hidden" name="proposal" value="<?php echo esc_attr( $proposal->ID ); ?>">
							<input type="hidden" name="eo_tools_from" value="admin">
							<?php wp_nonce_field( $nonce_action ); ?>
							<button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Appliquer cette modification sur le contenu publié ?', 'eo-tools' ) ); ?>');"><?php esc_html_e( 'Appliquer', 'eo-tools' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

<?php endif; ?>
