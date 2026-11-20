<?php
/**
 * "À valider" tab of EO Tools > MCP: everything an AI prepared and that waits for a human.
 *
 * New drafts, changes of published contents, templates and styles. Every item
 * has a checkbox linked to a single bulk form; changes also keep their own
 * Apply / Reject buttons.
 *
 * Expects: $ai_drafts, $pending_proposals, $pending_templates, $pending_styles, $pending_seo, $pending_ext.
 *
 * @package EoTools
 */

use EoTools\Includes\Mcp\Eotools_Mcp_Ai_Drafts;
use EoTools\Includes\Mcp\Eotools_Mcp_Proposal_Types;
use EoTools\Includes\Mcp\Eotools_Mcp_Proposals;
use EoTools\Includes\Mcp\Eotools_Mcp_Redirections;
use EoTools\Includes\Mcp\Eotools_Mcp_Seo;
use EoTools\Includes\Mcp\Eotools_Mcp_Seo_Changes;
use EoTools\Includes\Mcp\Seo\Eotools_Mcp_Seo_Provider;
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
$has_items   = $ai_drafts || $pending_proposals || $pending_templates || ! empty( $pending_styles ) || ! empty( $pending_seo ) || ! empty( $pending_ext );
$ext_errors  = Eotools_Mcp_Proposal_Types::pop_errors();

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
	$field_id = 'eo-mcp-' . sanitize_html_class( str_replace( array( '[', ']' ), '-', $name . '-' . $id ) );
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
<?php elseif ( 'ext-applied' === $done ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'La proposition a été validée.', 'eo-tools' ); ?></p></div>
<?php elseif ( 'seo-applied' === $done ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'La modification SEO a été appliquée.', 'eo-tools' ); ?></p></div>
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
	</p>
	<?php if ( $ext_errors ) : ?>
		<ul class="eo-mcp-errors">
			<?php foreach ( $ext_errors as $ext_error ) : ?>
				<li><?php echo esc_html( $ext_error ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	</div>
<?php endif; ?>

<p class="eo-mcp-intro"><?php esc_html_e( 'Tout ce que l\'IA a préparé attend ici votre validation : nouveaux contenus, modifications de contenus publiés, modèles, styles, redirections et SEO des catégories. Rien n\'est publié tant que vous ne validez pas.', 'eo-tools' ); ?></p>

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

<?php
if ( ! empty( $pending_seo ) ) :
	$seo_redirections = wp_list_filter( $pending_seo, array( 'kind' => Eotools_Mcp_Seo_Changes::KIND_REDIRECTION ) );
	$seo_terms        = wp_list_filter( $pending_seo, array( 'kind' => Eotools_Mcp_Seo_Changes::KIND_TERM ) );
	$field_labels     = Eotools_Mcp_Seo_Provider::get_field_labels();

	/**
	 * Apply / Reject buttons of one SEO change.
	 *
	 * @param array $change SEO change.
	 */
	$seo_buttons = function ( $change ) {
		$nonce_action = 'eo_tools_seo_change_' . $change['id'];
		foreach ( array( 'reject' => __( 'Rejeter', 'eo-tools' ), 'apply' => __( 'Appliquer', 'eo-tools' ) ) as $button => $label ) :
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( 'eo_tools_' . $button . '_seo_change' ); ?>">
				<input type="hidden" name="change" value="<?php echo esc_attr( $change['id'] ); ?>">
				<?php wp_nonce_field( $nonce_action ); ?>
				<button type="submit" class="button<?php echo 'apply' === $button ? ' button-primary' : ''; ?>"><?php echo esc_html( $label ); ?></button>
			</form>
			<?php
		endforeach;
	};
	?>

	<?php if ( $seo_redirections ) : ?>
		<h2 class="eo-mcp-group-title"><?php esc_html_e( 'Redirections (301)', 'eo-tools' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Les visiteurs et les moteurs qui arrivent sur l\'ancienne adresse sont envoyés vers la nouvelle. Vous pourrez les modifier ou les retirer dans EO Tools > 404-Erreur, onglet « Redirigées ».', 'eo-tools' ); ?></p>
		<table class="widefat striped eo-mcp-proposals">
			<thead>
				<tr>
					<?php $select_all( 'eo-mcp-select-redirections' ); ?>
					<th><?php esc_html_e( 'Ancienne adresse', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Redirigée vers', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Proposée le', 'eo-tools' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $seo_redirections as $change ) : ?>
					<?php
					$from_url = Eotools_Mcp_Redirections::source_url( $change['data']['from'] );
					$to_url   = Eotools_Mcp_Redirections::target_url( $change['data']['to'] );
					?>
					<tr>
						<?php $select_row( 'seo_changes', $change['id'], $change['data']['from'] ); ?>
						<td>
							<code><?php echo esc_html( rawurldecode( $change['data']['from'] ) ); ?></code>
							<?php if ( ! empty( $change['data']['reason'] ) ) : ?>
								<br><span class="description"><?php echo esc_html( $change['data']['reason'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $change['data']['replaces'] ) ) : ?>
								<br><span class="eo-mcp-badge is-warning">
									<?php
									/* translators: %s: current target. */
									echo esc_html( sprintf( __( 'Remplace la redirection vers %s', 'eo-tools' ), rawurldecode( $change['data']['replaces'] ) ) );
									?>
								</span>
							<?php endif; ?>
						</td>
						<td><a href="<?php echo esc_url( $to_url ); ?>" target="_blank"><?php echo esc_html( rawurldecode( $change['data']['to'] ) ); ?></a></td>
						<td><?php echo esc_html( wp_date( $date_format, $change['created'] ) ); ?></td>
						<td class="eo-mcp-actions">
							<a class="button" href="<?php echo esc_url( $from_url ); ?>" target="_blank"><?php esc_html_e( 'Tester l\'ancienne adresse', 'eo-tools' ); ?></a>
							<?php $seo_buttons( $change ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( $seo_terms ) : ?>
		<h2 class="eo-mcp-group-title"><?php esc_html_e( 'SEO des catégories et étiquettes', 'eo-tools' ); ?></h2>
		<p class="description">
			<?php
			/* translators: %s: SEO plugin name. */
			echo esc_html( sprintf( __( 'Métadonnées écrites dans %s et texte de présentation des archives.', 'eo-tools' ), Eotools_Mcp_Seo::get_provider()->get_name() ) );
			?>
		</p>
		<table class="widefat striped eo-mcp-proposals">
			<thead>
				<tr>
					<?php $select_all( 'eo-mcp-select-terms' ); ?>
					<th><?php esc_html_e( 'Archive', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'Modifications (avant → après)', 'eo-tools' ); ?></th>
					<th><?php esc_html_e( 'État', 'eo-tools' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $seo_terms as $change ) : ?>
					<?php
					$change_data = $change['data'];
					$term        = get_term( (int) $change_data['term_id'], $change_data['taxonomy'] );
					$term_link   = $term instanceof WP_Term ? get_term_link( $term ) : '';
					$tax         = get_taxonomy( $change_data['taxonomy'] );
					?>
					<tr>
						<?php $select_row( 'seo_changes', $change['id'], $change_data['name'] ); ?>
						<td>
							<strong>
								<?php if ( $term_link && ! is_wp_error( $term_link ) ) : ?>
									<a href="<?php echo esc_url( $term_link ); ?>" target="_blank"><?php echo esc_html( $change_data['name'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $change_data['name'] ); ?>
								<?php endif; ?>
							</strong>
							<br><span class="description"><?php echo esc_html( $tax ? $tax->labels->singular_name : $change_data['taxonomy'] ); ?></span>
						</td>
						<td>
							<ul class="eo-mcp-changes">
								<?php foreach ( $change_data['seo'] as $field => $value ) : ?>
									<li>
										<strong><?php echo esc_html( isset( $field_labels[ $field ] ) ? $field_labels[ $field ] : $field ); ?></strong> :
										<span class="description"><?php echo esc_html( Eotools_Mcp_Seo_Provider::format_value( $field, isset( $change_data['before']['seo'][ $field ] ) ? $change_data['before']['seo'][ $field ] : '' ) ); ?></span>
										→ <?php echo esc_html( Eotools_Mcp_Seo_Provider::format_value( $field, $value ) ); ?>
									</li>
								<?php endforeach; ?>
								<?php if ( isset( $change_data['archive_text'] ) ) : ?>
									<li>
										<strong><?php esc_html_e( 'Texte de présentation', 'eo-tools' ); ?></strong> :
										<span class="description"><?php echo esc_html( '' !== $change_data['before']['archive_text'] ? wp_trim_words( wp_strip_all_tags( $change_data['before']['archive_text'] ), 20 ) : '—' ); ?></span>
										→ <?php echo esc_html( wp_strip_all_tags( $change_data['archive_text'] ) ); ?>
									</li>
								<?php endif; ?>
							</ul>
						</td>
						<td>
							<?php if ( Eotools_Mcp_Seo_Changes::has_conflict( $change ) ) : ?>
								<span class="eo-mcp-badge is-warning"><?php esc_html_e( 'Archive modifiée depuis', 'eo-tools' ); ?></span>
							<?php else : ?>
								<span class="eo-mcp-badge"><?php esc_html_e( 'En attente', 'eo-tools' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="eo-mcp-actions">
							<?php $seo_buttons( $change ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

<?php endif; ?>

<?php foreach ( $pending_ext as $ext_type_id => $ext_group ) : ?>
	<?php $ext_type = $ext_group['type']; ?>
	<h2 class="eo-mcp-group-title">
		<?php echo esc_html( $ext_type['label'] ); ?>
		<?php if ( $ext_type['provider'] ) : ?>
			<span class="eo-mcp-badge">
				<?php
				/* translators: %s: name of the plugin that made the proposals. */
				echo esc_html( sprintf( __( 'Proposé via %s', 'eo-tools' ), $ext_type['provider'] ) );
				?>
			</span>
		<?php endif; ?>
	</h2>
	<?php if ( $ext_type['description'] ) : ?>
		<p class="description"><?php echo esc_html( $ext_type['description'] ); ?></p>
	<?php endif; ?>
	<table class="widefat striped eo-mcp-proposals">
		<thead>
			<tr>
				<?php $select_all( 'eo-mcp-select-ext-' . $ext_type_id ); ?>
				<th><?php esc_html_e( 'Proposition', 'eo-tools' ); ?></th>
				<th><?php esc_html_e( 'Détails', 'eo-tools' ); ?></th>
				<th><?php esc_html_e( 'Proposée le', 'eo-tools' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $ext_group['items'] as $ext_item ) : ?>
				<?php $ext_nonce = Eotools_Mcp_Proposal_Types::nonce_action( $ext_type_id, $ext_item['id'] ); ?>
				<tr>
					<?php $select_row( Eotools_Mcp_Proposal_Types::FIELD . '[' . $ext_type_id . ']', $ext_item['id'], $ext_item['title'] ); ?>
					<td>
						<strong><?php echo esc_html( $ext_item['title'] ); ?></strong>
						<?php if ( $ext_item['warning'] ) : ?>
							<br><span class="eo-mcp-badge is-warning"><?php echo esc_html( $ext_item['warning'] ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo wp_kses_post( $ext_item['details'] ); ?></td>
					<td><?php echo esc_html( $ext_item['created'] ? wp_date( $date_format, $ext_item['created'] ) : '' ); ?></td>
					<td class="eo-mcp-actions">
						<?php if ( $ext_item['link'] ) : ?>
							<a class="button" href="<?php echo esc_url( $ext_item['link'] ); ?>" target="_blank"><?php echo esc_html( $ext_item['link_label'] ? $ext_item['link_label'] : __( 'Voir', 'eo-tools' ) ); ?></a>
						<?php endif; ?>
						<?php foreach ( array( 'reject' => __( 'Rejeter', 'eo-tools' ), 'apply' => __( 'Valider', 'eo-tools' ) ) as $ext_button => $ext_button_label ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="<?php echo esc_attr( 'eo_tools_' . $ext_button . '_ext_proposal' ); ?>">
								<input type="hidden" name="type" value="<?php echo esc_attr( $ext_type_id ); ?>">
								<input type="hidden" name="item" value="<?php echo esc_attr( $ext_item['id'] ); ?>">
								<?php wp_nonce_field( $ext_nonce ); ?>
								<button type="submit" class="button<?php echo 'apply' === $ext_button ? ' button-primary' : ''; ?>"><?php echo esc_html( $ext_button_label ); ?></button>
							</form>
						<?php endforeach; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endforeach; ?>

<?php if ( $has_items ) : ?>
	<div class="eo-mcp-validate-bar">
		<button type="submit" form="<?php echo esc_attr( $form_id ); ?>" name="bulk_action" value="apply" class="button button-primary"><?php esc_html_e( 'Valider la sélection', 'eo-tools' ); ?></button>
		<button type="submit" form="<?php echo esc_attr( $form_id ); ?>" name="bulk_action" value="reject" class="button"><?php esc_html_e( 'Rejeter la sélection', 'eo-tools' ); ?></button>
	</div>
<?php endif; ?>
