<?php
/**
 * "Brouillons IA" tab of EO Tools > MCP: contents created by an AI, published in bulk by a human.
 *
 * Expects: $ai_drafts.
 *
 * @package EoTools
 */

use EoTools\Includes\Mcp\Eotools_Mcp_Ai_Drafts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display of the result of an action already checked.
$done   = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
$count  = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
$failed = isset( $_GET['failed'] ) ? absint( $_GET['failed'] ) : 0;
// phpcs:enable

$groups      = Eotools_Mcp_Ai_Drafts::group_by_day( $ai_drafts );
$time_format = get_option( 'time_format' );
?>

<?php if ( 'published' === $done && $count ) : ?>
	<div class="notice notice-success is-dismissible"><p>
		<?php
		/* translators: %d: number of published contents. */
		echo esc_html( sprintf( _n( '%d contenu publié.', '%d contenus publiés.', $count, 'eo-tools' ), $count ) );
		?>
	</p></div>
<?php elseif ( 'trashed' === $done && $count ) : ?>
	<div class="notice notice-success is-dismissible"><p>
		<?php
		/* translators: %d: number of trashed contents. */
		echo esc_html( sprintf( _n( '%d brouillon mis à la corbeille. Vous pouvez le restaurer depuis la corbeille.', '%d brouillons mis à la corbeille. Vous pouvez les restaurer depuis la corbeille.', $count, 'eo-tools' ), $count ) );
		?>
	</p></div>
<?php elseif ( 'none' === $done ) : ?>
	<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Cochez au moins un contenu.', 'eo-tools' ); ?></p></div>
<?php endif; ?>
<?php if ( $failed ) : ?>
	<div class="notice notice-error is-dismissible"><p>
		<?php
		/* translators: %d: number of contents not processed. */
		echo esc_html( sprintf( _n( '%d contenu n\'a pas pu être traité (droits insuffisants ou contenu déjà modifié).', '%d contenus n\'ont pas pu être traités (droits insuffisants ou contenus déjà modifiés).', $failed, 'eo-tools' ), $failed ) );
		?>
	</p></div>
<?php endif; ?>

<p class="eo-mcp-intro"><?php esc_html_e( 'Les pages et articles créés par une IA restent en brouillon. Vérifiez-les, cochez ceux qui vous conviennent et publiez-les en une fois.', 'eo-tools' ); ?></p>

<?php if ( ! $ai_drafts ) : ?>

	<div class="eo-mcp-card eo-mcp-empty">
		<span class="dashicons dashicons-yes-alt"></span>
		<h2><?php esc_html_e( 'Aucun brouillon IA en attente', 'eo-tools' ); ?></h2>
	</div>

<?php else : ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eo-mcp-ai-drafts">
		<input type="hidden" name="action" value="eo_tools_ai_drafts">
		<?php wp_nonce_field( Eotools_Mcp_Ai_Drafts::NONCE_ACTION ); ?>

		<div class="eo-mcp-ai-drafts-actions">
			<button type="submit" name="bulk_action" value="publish" class="button button-primary"><?php esc_html_e( 'Publier la sélection', 'eo-tools' ); ?></button>
			<button type="submit" name="bulk_action" value="trash" class="button"><?php esc_html_e( 'Mettre la sélection à la corbeille', 'eo-tools' ); ?></button>
		</div>

		<?php foreach ( $groups as $day => $day_posts ) : ?>
			<?php $day_timestamp = ( new DateTimeImmutable( $day, wp_timezone() ) )->getTimestamp(); ?>
			<h2 class="eo-mcp-group-title">
				<?php
				/* translators: 1: day, 2: number of contents. */
				echo esc_html( sprintf( _n( 'Créé le %1$s – %2$d contenu', 'Créés le %1$s – %2$d contenus', count( $day_posts ), 'eo-tools' ), wp_date( get_option( 'date_format' ), $day_timestamp ), count( $day_posts ) ) );
				?>
			</h2>
			<table class="widefat striped eo-mcp-proposals">
				<thead>
					<tr>
						<td class="check-column">
							<input type="checkbox" id="eo-mcp-select-<?php echo esc_attr( $day ); ?>">
							<label for="eo-mcp-select-<?php echo esc_attr( $day ); ?>" class="screen-reader-text"><?php esc_html_e( 'Tout sélectionner', 'eo-tools' ); ?></label>
						</td>
						<th><?php esc_html_e( 'Contenu', 'eo-tools' ); ?></th>
						<th><?php esc_html_e( 'Type', 'eo-tools' ); ?></th>
						<th><?php esc_html_e( 'Créé à', 'eo-tools' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $day_posts as $draft ) : ?>
						<?php
						$type_object = get_post_type_object( $draft->post_type );
						$parent      = $draft->post_parent ? get_post( $draft->post_parent ) : null;
						$created     = Eotools_Mcp_Ai_Drafts::get_created( $draft );
						?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" name="drafts[]" value="<?php echo esc_attr( $draft->ID ); ?>" id="eo-mcp-draft-<?php echo esc_attr( $draft->ID ); ?>">
								<label for="eo-mcp-draft-<?php echo esc_attr( $draft->ID ); ?>" class="screen-reader-text"><?php echo esc_html( get_the_title( $draft ) ); ?></label>
							</th>
							<td>
								<strong><a href="<?php echo esc_url( get_edit_post_link( $draft->ID ) ); ?>"><?php echo esc_html( get_the_title( $draft ) ? get_the_title( $draft ) : __( '(sans titre)', 'eo-tools' ) ); ?></a></strong>
								<?php if ( $parent ) : ?>
									<br><span class="description">
										<?php
										/* translators: %s: parent page title. */
										echo esc_html( sprintf( __( 'Sous-page de : %s', 'eo-tools' ), get_the_title( $parent ) ) );
										?>
									</span>
								<?php endif; ?>
								<?php if ( 'pending' === $draft->post_status ) : ?>
									<br><span class="eo-mcp-badge"><?php esc_html_e( 'En attente de relecture', 'eo-tools' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $type_object ? $type_object->labels->singular_name : $draft->post_type ); ?></td>
							<td><?php echo esc_html( $created ? wp_date( $time_format, $created ) : '' ); ?></td>
							<td class="eo-mcp-actions">
								<a class="button" href="<?php echo esc_url( get_preview_post_link( $draft ) ); ?>" target="_blank"><?php esc_html_e( 'Aperçu', 'eo-tools' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>

		<div class="eo-mcp-ai-drafts-actions">
			<button type="submit" name="bulk_action" value="publish" class="button button-primary"><?php esc_html_e( 'Publier la sélection', 'eo-tools' ); ?></button>
			<button type="submit" name="bulk_action" value="trash" class="button"><?php esc_html_e( 'Mettre la sélection à la corbeille', 'eo-tools' ); ?></button>
		</div>
	</form>

<?php endif; ?>
