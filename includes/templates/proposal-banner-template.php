<?php
/**
 * Banner displayed on the preview of an AI change proposal.
 *
 * Expects: $proposal, $source, $conflict.
 *
 * @package EoTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nonce_action = 'eo_tools_proposal_' . $proposal->ID;
?>
<div class="eo-tools-proposal-banner" role="region" aria-label="<?php esc_attr_e( 'Proposition de modification', 'eo-tools' ); ?>">
	<style>
		.eo-tools-proposal-banner {
			position: fixed;
			left: 50%;
			bottom: 20px;
			z-index: 999999;
			transform: translateX(-50%);
			width: calc(100% - 40px);
			max-width: 760px;
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 12px 20px;
			padding: 16px 20px;
			border-radius: 12px;
			background: #0f172a;
			color: #f8fafc;
			box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.4);
			font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		}
		.eo-tools-proposal-banner p {
			flex: 1 1 260px;
			margin: 0;
			color: inherit;
		}
		.eo-tools-proposal-banner strong {
			display: block;
			color: #ffffff;
		}
		.eo-tools-proposal-banner a {
			color: #a5b4fc;
		}
		.eo-tools-proposal-banner .eo-tools-proposal-conflict {
			display: block;
			margin-top: 4px;
			color: #fcd34d;
		}
		.eo-tools-proposal-banner form {
			margin: 0;
		}
		.eo-tools-proposal-banner button {
			padding: 10px 18px;
			border: 1px solid #475569;
			border-radius: 8px;
			background: transparent;
			color: #f8fafc;
			font: 600 14px/1 inherit;
			cursor: pointer;
		}
		.eo-tools-proposal-banner button.is-primary {
			border-color: #6366f1;
			background: #6366f1;
		}
	</style>
	<p>
		<strong><?php esc_html_e( 'Aperçu d\'une modification proposée par l\'IA', 'eo-tools' ); ?></strong>
		<?php
		/* translators: %s: link to the published content. */
		printf( esc_html__( 'Rien n\'est encore publié. %s', 'eo-tools' ), '<a href="' . esc_url( get_permalink( $source ) ) . '" target="_blank">' . esc_html__( 'Voir la version actuelle', 'eo-tools' ) . '</a>' );
		?>
		<?php if ( $conflict ) : ?>
			<span class="eo-tools-proposal-conflict"><?php esc_html_e( 'Attention : le contenu publié a été modifié depuis cette proposition. L\'appliquer remplacera ces changements.', 'eo-tools' ); ?></span>
		<?php endif; ?>
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="eo_tools_reject_proposal">
		<input type="hidden" name="proposal" value="<?php echo esc_attr( $proposal->ID ); ?>">
		<?php wp_nonce_field( $nonce_action ); ?>
		<button type="submit" onclick="return confirm('<?php echo esc_js( __( 'Rejeter cette proposition ? Elle sera supprimée.', 'eo-tools' ) ); ?>');"><?php esc_html_e( 'Rejeter', 'eo-tools' ); ?></button>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="eo_tools_apply_proposal">
		<input type="hidden" name="proposal" value="<?php echo esc_attr( $proposal->ID ); ?>">
		<?php wp_nonce_field( $nonce_action ); ?>
		<button type="submit" class="is-primary" <?php echo $conflict ? 'onclick="return confirm(\'' . esc_js( __( 'Le contenu publié a changé depuis la proposition. Appliquer quand même ?', 'eo-tools' ) ) . '\');"' : ''; ?>><?php esc_html_e( 'Appliquer la modification', 'eo-tools' ); ?></button>
	</form>
</div>
