<?php
/**
 * "Propositions" tab of EO Tools > MCP: pending AI change proposals.
 *
 * Expects: $pending_proposals.
 *
 * @package EoTools
 */

use EoTools\Includes\Mcp\Eotools_Mcp_Proposals;

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

<p class="eo-mcp-intro"><?php esc_html_e( 'Les modifications proposées par une IA sur vos contenus publiés attendent ici votre validation. Rien n\'est publié tant que vous ne cliquez pas sur « Appliquer ».', 'eo-tools' ); ?></p>

<?php if ( ! $pending_proposals ) : ?>

	<div class="eo-mcp-card eo-mcp-empty">
		<span class="dashicons dashicons-yes-alt"></span>
		<h2><?php esc_html_e( 'Aucune proposition en attente', 'eo-tools' ); ?></h2>
	</div>

<?php else : ?>

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
