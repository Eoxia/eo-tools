<?php
/**
 * "À valider" tab of EO Tools > MCP: everything an AI prepared and that waits for a human.
 *
 * New drafts, changes of published contents, templates and styles. Every item
 * has a checkbox linked to a single bulk form; changes also keep their own
 * Apply / Reject buttons.
 *
 * Expects: $ai_drafts, $pending_proposals, $pending_templates, $pending_styles.
 *
 * @package EoTools
 */

use EoTools\Includes\Mcp\Eotools_Mcp_Ai_Drafts;
use EoTools\Includes\Mcp\Eotools_Mcp_Proposals;
use EoTools\Includes\Mcp\Eotools_Mcp_Templates;
use EoTools\Includes\Mcp\Eotools_Mcp_Styles;
use EoTools\Includes\Mcp\Eotools_Mcp_Validation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display of the result of an action already checked.
$done   = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
$count  = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
$failed = isset( $_GET['failed'] ) ? absint( $_GET['failed'] ) : 0;
// phpcs:enable

$form_id     = Eotools_Mcp_Validation::FORM_ID;
$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
$has_items   = $ai_drafts || $pending_proposals || $pending_templates || ! empty( $pending_styles );

/**
 * Header cell that (un)checks every row of its table (handled by WordPress' common.js).
 *
 * @param string $id Unique ID.
 */
$select_all = function ( $id ) {
	?>
	<td class="check-column">
		<input type="checkbox" id="<?php echo esc_attr( $id ); ?>">
		<label for="<?php echo esc_attr( $id ); ?>" class="screen-reader-text"><?php esc_html_e( 'Tout sélectionner', 'eo-tools' ); ?></label>
	</td>
	<?php
};

/**
 * Row checkbox, attached to the bulk form.
 *
 * @param string $name  Field name (drafts, contents, templates or styles).
 * @param int    $id    Item ID.
 * @param string $label Accessible label.
 */
$select_row = function ( $name, $id, $label ) use ( $form_id ) {
	$field_id = 'eo-mcp-' . $name . '-' . $id;
	?>
	<th scope="row" class="check-column">
		<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $id ); ?>" id="<?php echo esc_attr( $field_id ); ?>" form="<?php echo esc_attr( $form_id ); ?>">
		<label for="<?php echo esc_attr( $field_id ); ?>" class="screen-reader-text"><?php echo esc_html( $label ); ?></label>
	</th>
	<?php
};
?>

<?php if ( 'applied' === $done ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'La modification a été appliquée. La version précédente reste disponible dans les révisions.', 'eo-tools' ); ?></p></div>
<?php elseif ( 'rejected' === $done ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'La proposition a été rejetée.', 'eo-tools' ); ?></p></div>
<?php elseif ( 'validated' === $done && $count ) : ?>
	<div class="notice notice-success is-dismissible"><p>
		<?php
		/* translators: %d: number of validated items. */
		echo esc_html( sprintf( _n( '%d élément validé.', '%d éléments validés.', $count, 'eo-tools' ), $count ) );
		?>
	</p></div>
<?php elseif ( 'bulk-rejected' === $done && $count ) : ?>
	<div class="notice notice-success is-dismissible"><p>
		<?php
		/* translators: %d: number of rejected items. */
		echo esc_html( sprintf( _n( '%d élément rejeté. Les brouillons rejetés restent récupérables dans la corbeille.', '%d éléments rejetés. Les brouillons rejetés restent récupérables dans la corbeille.', $count, 'eo-tools' ), $count ) );
		?>
	</p></div>
<?php elseif ( 'none' === $done ) : ?>
	<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Cochez au moins un élément.', 'eo-tools' ); ?></p></div>
<?php endif; ?>
<?php if ( $failed ) : ?>
	<div class="notice notice-error is-dismissible"><p>
		<?php
		/* translators: %d: number of items not processed. */
		echo esc_html( sprintf( _n( '%d élément n\'a pas pu être traité (droits insuffisants ou élément déjà traité).', '%d éléments n\'ont pas pu être traités (droits insuffisants ou éléments déjà traités).', $failed, 'eo-tools' ), $failed ) );
		?>
	</p></div>
<?php endif; ?>

<p class="eo-mcp-intro"><?php esc_html_e( 'Tout ce que l\'IA a préparé attend ici votre validation : nouveaux contenus, modifications de contenus publiés, modèles et styles. Rien n\'est publié tant que vous ne validez pas.', 'eo-tools' ); ?></p>

<?php if ( ! $has_items ) : ?>

	<div class="eo-mcp-card eo-mcp-empty">
		<span class="dashicons dashicons-yes-alt"></span>
		<h2><?php esc_html_e( 'Rien à valider pour le moment', 'eo-tools' ); ?></h2>
	</div>

<?php else : ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="<?php echo esc_attr( $form_id ); ?>" class="eo-mcp-validate-bar">
		<input type="hidden" name="action" value="eo_tools_bulk_validate">
		<?php wp_nonce_field( Eotools_Mcp_Validation::NONCE_ACTION ); ?>
		<button type="submit" name="bulk_action" value="apply" class="button button-primary"><?php esc_html_e( 'Valider la sélection', 'eo-tools' ); ?></button>
		<button type="submit" name="bulk_action" value="reject" class="button"><?php esc_html_e( 'Rejeter la sélection', 'eo-tools' ); ?></button>
		<span class="description"><?php esc_html_e( 'Valider publie les nouveaux contenus et applique les modifications cochées. Rejeter met les brouillons à la corbeille et supprime les modifications.', 'eo-tools' ); ?></span>
	</form>

<?php endif; ?>

<?php if ( $ai_drafts ) : ?>

	<h2 class="eo-mcp-group-title"><?php esc_html_e( 'Nouveaux contenus', 'eo-tools' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Pages et articles créés par l\'IA, encore en brouillon. Les pages parentes sont publiées avant leurs sous-pages.', 'eo-tools' ); ?></p>

	<?php foreach ( Eotools_Mcp_Ai_Drafts::group_by_day( $ai_drafts ) as $day => $day_posts ) : ?>
		<?php $day_timestamp = ( new DateTimeImmutable( $day, wp_timezone() ) )->getTimestamp(); ?>
		<h3 class="eo-mcp-day-title">
			<?php
			/* translators: 1: day, 2: number of contents. */
			echo esc_html( sprintf( _n( 'Créé le %1$s – %2$d contenu', 'Créés le %1$s – %2$d contenus', count( $day_posts ), 'eo-tools' ), wp_date( get_option( 'date_format' ), $day_timestamp ), count( $day_posts ) ) );
			?>
		</h3>
		<table class="widefat striped eo-mcp-proposals">
			<thead>
				<tr>
					<?php $select_all( 'eo-mcp-select-drafts-' . $day ); ?>
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
					$draft_title = get_the_title( $draft ) ? get_the_title( $draft ) : __( '(sans titre)', 'eo-tools' );
					?>
					<tr>
						<?php $select_row( 'drafts', $draft->ID, $draft_title ); ?>
						<td>
							<strong><a href="<?php echo esc_url( get_edit_post_link( $draft->ID ) ); ?>"><?php echo esc_html( $draft_title ); ?></a></strong>
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
						<td><?php echo esc_html( $created ? wp_date( get_option( 'time_format' ), $created ) : '' ); ?></td>
						<td class="eo-mcp-actions">
							<a class="button" href="<?php echo esc_url( get_preview_post_link( $draft ) ); ?>" target="_blank"><?php esc_html_e( 'Aperçu', 'eo-tools' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endforeach; ?>

<?php endif; ?>

<?php if ( $pending_proposals ) : ?>

	<h2 class="eo-mcp-group-title"><?php esc_html_e( 'Modifications de contenus publiés', 'eo-tools' ); ?></h2>
	<p class="description"><?php esc_html_e( 'La version précédente de chaque contenu reste disponible dans ses révisions.', 'eo-tools' ); ?></p>
	<table class="widefat striped eo-mcp-proposals">
		<thead>
			<tr>
				<?php $select_all( 'eo-mcp-select-contents' ); ?>
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
					<?php $select_row( 'contents', $proposal->ID, get_the_title( $source ) ); ?>
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
					<td><?php echo esc_html( get_date_from_gmt( $proposal->post_modified_gmt, $date_format ) ); ?></td>
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
							<button type="submit" class="button"><?php esc_html_e( 'Rejeter', 'eo-tools' ); ?></button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="eo_tools_apply_proposal">
							<input type="hidden" name="proposal" value="<?php echo esc_attr( $proposal->ID ); ?>">
							<input type="hidden" name="eo_tools_from" value="admin">
							<?php wp_nonce_field( $nonce_action ); ?>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Appliquer', 'eo-tools' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

<?php endif; ?>

<?php if ( $pending_templates ) : ?>

	<h2 class="eo-mcp-group-title"><?php esc_html_e( 'Modèles du site', 'eo-tools' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Un modèle s\'applique à toutes les pages qui l\'utilisent : vérifiez l\'aperçu avant d\'appliquer. Vous pourrez revenir à la version du thème depuis l\'éditeur de site.', 'eo-tools' ); ?></p>
	<table class="widefat striped eo-mcp-proposals">
		<thead>
			<tr>
				<?php $select_all( 'eo-mcp-select-templates' ); ?>
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
					<?php if ( $template ) : ?>
						<?php $select_row( 'templates', $proposal->ID, $template->title ); ?>
					<?php else : ?>
						<th scope="row" class="check-column"></th>
					<?php endif; ?>
					<td>
						<strong><?php echo esc_html( $template ? $template->title : $proposal->post_title ); ?></strong>
						<br><span class="description"><?php echo esc_html( $template && 'wp_template_part' === $template->type ? __( 'Partie de modèle', 'eo-tools' ) : __( 'Modèle de page', 'eo-tools' ) ); ?></span>
					</td>
					<td><?php echo esc_html( get_date_from_gmt( $proposal->post_modified_gmt, $date_format ) ); ?></td>
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
							<button type="submit" class="button"><?php esc_html_e( 'Rejeter', 'eo-tools' ); ?></button>
						</form>
						<?php if ( $template ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="eo_tools_apply_template_proposal">
								<input type="hidden" name="proposal" value="<?php echo esc_attr( $proposal->ID ); ?>">
								<input type="hidden" name="eo_tools_from" value="admin">
								<?php wp_nonce_field( $nonce_action ); ?>
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Appliquer', 'eo-tools' ); ?></button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

<?php endif; ?>

<?php if ( ! empty( $pending_styles ) ) : ?>

	<?php
	$styles_summary = get_post_meta( $pending_styles->ID, Eotools_Mcp_Styles::META_SUMMARY, true );
	$styles_label   = $styles_summary ? $styles_summary : __( 'Nouveaux styles', 'eo-tools' );
	$styles_nonce   = 'eo_tools_proposal_' . $pending_styles->ID;
	?>
	<h2 class="eo-mcp-group-title"><?php esc_html_e( 'Styles du site', 'eo-tools' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Les styles s\'appliquent à tout le site : vérifiez l\'aperçu avant d\'appliquer. Vous pourrez revenir en arrière depuis l\'historique des styles de l\'éditeur de site.', 'eo-tools' ); ?></p>
	<table class="widefat striped eo-mcp-proposals">
		<tbody>
			<tr>
				<?php $select_row( 'styles', $pending_styles->ID, $styles_label ); ?>
				<td><strong><?php echo esc_html( $styles_label ); ?></strong></td>
				<td><?php echo esc_html( get_date_from_gmt( $pending_styles->post_modified_gmt, $date_format ) ); ?></td>
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
						<button type="submit" class="button"><?php esc_html_e( 'Rejeter', 'eo-tools' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="eo_tools_apply_styles_proposal">
						<input type="hidden" name="proposal" value="<?php echo esc_attr( $pending_styles->ID ); ?>">
						<input type="hidden" name="eo_tools_from" value="admin">
						<?php wp_nonce_field( $styles_nonce ); ?>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Appliquer', 'eo-tools' ); ?></button>
					</form>
				</td>
			</tr>
		</tbody>
	</table>

<?php endif; ?>

<?php if ( $has_items ) : ?>
	<div class="eo-mcp-validate-bar">
		<button type="submit" form="<?php echo esc_attr( $form_id ); ?>" name="bulk_action" value="apply" class="button button-primary"><?php esc_html_e( 'Valider la sélection', 'eo-tools' ); ?></button>
		<button type="submit" form="<?php echo esc_attr( $form_id ); ?>" name="bulk_action" value="reject" class="button"><?php esc_html_e( 'Rejeter la sélection', 'eo-tools' ); ?></button>
	</div>
<?php endif; ?>
