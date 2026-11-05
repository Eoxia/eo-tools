<?php
/**
 * OAuth consent screen for the EO Tools MCP server.
 *
 * Expects: $client, $params, $redirect_host, $user.
 *
 * @package EoTools
 */

use EoTools\Includes\Mcp\Eotools_Mcp_Oauth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

nocache_headers();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php esc_html_e( 'Autoriser l\'accès', 'eo-tools' ); ?> – <?php bloginfo( 'name' ); ?></title>
	<style>
		body {
			margin: 0;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			background: #f1f5f9;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			color: #0f172a;
		}
		.eo-consent {
			width: 100%;
			max-width: 440px;
			margin: 24px;
			padding: 32px;
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 12px;
			box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
		}
		.eo-consent h1 {
			margin: 0 0 8px 0;
			font-size: 20px;
		}
		.eo-consent p {
			margin: 0 0 16px 0;
			line-height: 1.5;
			color: #475569;
		}
		.eo-consent ul {
			margin: 0 0 20px 0;
			padding: 16px 16px 16px 36px;
			background: #f8fafc;
			border-radius: 8px;
			color: #334155;
			line-height: 1.6;
		}
		.eo-consent-meta {
			font-size: 13px;
		}
		.eo-consent-actions {
			display: flex;
			gap: 12px;
		}
		.eo-consent-actions button {
			flex: 1;
			padding: 12px;
			border-radius: 8px;
			font-size: 15px;
			font-weight: 600;
			cursor: pointer;
			border: 1px solid #cbd5e1;
			background: #ffffff;
			color: #334155;
		}
		.eo-consent-actions button[name="approve"] {
			border-color: #6366f1;
			background: #6366f1;
			color: #ffffff;
		}
	</style>
</head>
<body>
	<main class="eo-consent">
		<h1>
			<?php
			/* translators: %s: AI client name. */
			echo esc_html( sprintf( __( 'Autoriser %s ?', 'eo-tools' ), $client['client_name'] ) );
			?>
		</h1>
		<p>
			<?php
			/* translators: 1: AI client name, 2: site name, 3: user display name. */
			echo esc_html( sprintf( __( '%1$s souhaite se connecter à %2$s avec votre compte (%3$s).', 'eo-tools' ), $client['client_name'], get_bloginfo( 'name' ), $user->display_name ) );
			?>
		</p>
		<ul>
			<li><?php esc_html_e( 'Utiliser les abilities EO Tools activées sur ce site', 'eo-tools' ); ?></li>
			<li><?php esc_html_e( 'Agir avec les mêmes droits que votre compte', 'eo-tools' ); ?></li>
		</ul>
		<p class="eo-consent-meta">
			<?php
			/* translators: %s: host name the user will be sent back to. */
			echo esc_html( sprintf( __( 'Après votre choix, vous serez renvoyé vers %s. Vous pourrez révoquer cet accès à tout moment depuis EO Tools > MCP.', 'eo-tools' ), $redirect_host ) );
			?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . Eotools_Mcp_Oauth::AUTHORIZE_PAGE ) ); ?>">
			<?php wp_nonce_field( 'eo_tools_mcp_authorize_' . $client['client_id'] ); ?>
			<?php foreach ( $params as $key => $value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<?php endforeach; ?>
			<div class="eo-consent-actions">
				<button type="submit" name="deny" value="1"><?php esc_html_e( 'Refuser', 'eo-tools' ); ?></button>
				<button type="submit" name="approve" value="1"><?php esc_html_e( 'Autoriser', 'eo-tools' ); ?></button>
			</div>
		</form>
	</main>
</body>
</html>
