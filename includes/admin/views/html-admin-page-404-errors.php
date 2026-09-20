<?php
/**
 * Admin page view for 404 Errors.
 *
 * @package EoTools
 */

if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Erreurs 404', 'eo-tools' ); ?></h1>
	<hr class="wp-header-end">

	<div class="notice notice-info">
		<p><?php esc_html_e( 'Ce module permet de suivre les pages en erreur 404 et de configurer des redirections pertinentes.', 'eo-tools' ); ?></p>
	</div>

	<!-- Future table here -->
	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th scope="col" class="manage-column column-source"><?php esc_html_e( 'Source', 'eo-tools' ); ?></th>
				<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'eo-tools' ); ?></th>
				<th scope="col" class="manage-column column-views"><?php esc_html_e( 'Vues', 'eo-tools' ); ?></th>
				<th scope="col" class="manage-column column-last-access"><?php esc_html_e( 'Dernier accès', 'eo-tools' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr class="no-items">
				<td class="colspanchange" colspan="4"><?php esc_html_e( 'Aucune erreur 404 trouvée pour le moment.', 'eo-tools' ); ?></td>
			</tr>
		</tbody>
		<tfoot>
			<tr>
				<th scope="col" class="manage-column column-source"><?php esc_html_e( 'Source', 'eo-tools' ); ?></th>
				<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'eo-tools' ); ?></th>
				<th scope="col" class="manage-column column-views"><?php esc_html_e( 'Vues', 'eo-tools' ); ?></th>
				<th scope="col" class="manage-column column-last-access"><?php esc_html_e( 'Dernier accès', 'eo-tools' ); ?></th>
			</tr>
		</tfoot>
	</table>
</div>
