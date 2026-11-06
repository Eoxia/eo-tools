<?php
/**
 * MCP admin view.
 *
 * @package EoTools
 */

use EoTools\Includes\Admin\Eotools_Mcp_Page;
use EoTools\Includes\Mcp\Eotools_Mcp_Abilities;
use EoTools\Includes\Mcp\Eotools_Mcp_Adapter;
use EoTools\Includes\Mcp\Eotools_Mcp_Ai_Drafts;
use EoTools\Includes\Mcp\Eotools_Mcp_Proposals;
use EoTools\Includes\Mcp\Eotools_Mcp_Templates;
use EoTools\Includes\Mcp\Eotools_Mcp_Snippets;
use EoTools\Includes\Mcp\Eotools_Mcp_Styles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_tab         = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'setup';
$adapter_status     = Eotools_Mcp_Adapter::get_status();
$adapter_version    = Eotools_Mcp_Adapter::get_version();
$adapter_ready      = Eotools_Mcp_Adapter::STATUS_ACTIVE === $adapter_status;
$can_proceed        = Eotools_Mcp_Adapter::STATUS_NOT_INSTALLED === $adapter_status ? Eotools_Mcp_Adapter::can_install() : Eotools_Mcp_Adapter::can_activate();
$clients            = Eotools_Mcp_Page::get_clients();
$risk_levels        = Eotools_Mcp_Abilities::get_risk_levels();
$pending_proposals  = Eotools_Mcp_Proposals::get_all_pending();
$pending_templates  = current_user_can( Eotools_Mcp_Templates::CAPABILITY ) ? Eotools_Mcp_Templates::get_all_pending() : array();
$pending_styles     = current_user_can( Eotools_Mcp_Styles::CAPABILITY ) ? Eotools_Mcp_Styles::get_pending() : null;
$pending_count      = count( $pending_proposals ) + count( $pending_templates ) + ( $pending_styles ? 1 : 0 );
$waiting_snippets   = Eotools_Mcp_Snippets::user_can() ? Eotools_Mcp_Snippets::count_waiting() : 0;
$ai_drafts          = Eotools_Mcp_Ai_Drafts::get_waiting();
$manual_url         = is_multisite() ? network_admin_url( 'plugin-install.php?s=mcp-adapter&tab=search&type=term' ) : admin_url( 'plugin-install.php?s=mcp-adapter&tab=search&type=term' );
?>
<div class="wrap eo-mcp-admin-wrapper">
	<h1><?php esc_html_e( 'MCP – Connecter votre IA', 'eo-tools' ); ?></h1>
	<p class="eo-mcp-intro"><?php esc_html_e( 'Connectez votre assistant IA (Claude, ChatGPT, Cursor…) à votre site WordPress pour qu\'il puisse lire, rédiger et auditer vos contenus.', 'eo-tools' ); ?></p>

	<h2 class="nav-tab-wrapper">
		<a href="?page=eo-tools-mcp&tab=setup" class="nav-tab <?php echo 'setup' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Mise en place', 'eo-tools' ); ?></a>
		<a href="?page=eo-tools-mcp&tab=abilities" class="nav-tab <?php echo 'abilities' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Abilities', 'eo-tools' ); ?></a>
		<a href="?page=eo-tools-mcp&tab=snippets" class="nav-tab <?php echo 'snippets' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Snippets', 'eo-tools' ); ?>
			<?php if ( $waiting_snippets ) : ?>
				<span class="eo-mcp-count"><?php echo esc_html( $waiting_snippets ); ?></span>
			<?php endif; ?>
		</a>
		<a href="?page=eo-tools-mcp&tab=proposals" class="nav-tab <?php echo 'proposals' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Propositions', 'eo-tools' ); ?>
			<?php if ( $pending_count ) : ?>
				<span class="eo-mcp-count"><?php echo esc_html( $pending_count ); ?></span>
			<?php endif; ?>
		</a>
		<a href="?page=eo-tools-mcp&tab=drafts" class="nav-tab <?php echo 'drafts' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Brouillons IA', 'eo-tools' ); ?>
			<?php if ( $ai_drafts ) : ?>
				<span class="eo-mcp-count"><?php echo esc_html( count( $ai_drafts ) ); ?></span>
			<?php endif; ?>
		</a>
	</h2>

	<?php if ( 'proposals' === $active_tab ) : ?>

		<?php include EO_TOOLS_PATH . 'includes/admin/views/html-admin-mcp-proposals.php'; ?>

	<?php elseif ( 'drafts' === $active_tab ) : ?>

		<?php include EO_TOOLS_PATH . 'includes/admin/views/html-admin-mcp-ai-drafts.php'; ?>

	<?php elseif ( 'snippets' === $active_tab ) : ?>

		<?php include EO_TOOLS_PATH . 'includes/admin/views/html-admin-mcp-snippets.php'; ?>

	<?php elseif ( 'abilities' === $active_tab ) : ?>

		<div class="eo-mcp-abilities-toolbar">
			<p><?php esc_html_e( 'Choisissez ce que votre IA a le droit de faire sur ce site. Tout est actif par défaut ; les abilities désactivées sont invisibles pour toutes les IA connectées.', 'eo-tools' ); ?></p>
			<div class="eo-mcp-abilities-bulk">
				<button type="button" class="button" data-eo-mcp-bulk="1"><?php esc_html_e( 'Tout activer', 'eo-tools' ); ?></button>
				<button type="button" class="button" data-eo-mcp-bulk="0"><?php esc_html_e( 'Tout désactiver', 'eo-tools' ); ?></button>
			</div>
		</div>
		<?php if ( ! $adapter_ready ) : ?>
			<p class="eo-mcp-status is-warning">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'MCP Adapter n\'est pas actif : aucune IA ne peut encore utiliser ces abilities. Terminez la mise en place.', 'eo-tools' ); ?>
			</p>
		<?php endif; ?>
		<div class="eo-mcp-levels">
			<?php foreach ( $risk_levels as $level => $level_data ) : ?>
				<?php
				$level_abilities = Eotools_Mcp_Abilities::get_by_risk( $level );
				if ( empty( $level_abilities ) ) {
					continue;
				}
				$level_enabled = count( array_filter( $level_abilities, array( Eotools_Mcp_Abilities::class, 'is_enabled' ) ) );
				?>
				<div class="eo-mcp-card eo-mcp-level is-<?php echo esc_attr( $level ); ?>">
					<span class="eo-mcp-risk is-<?php echo esc_attr( $level ); ?>"><?php echo esc_html( $level_data['label'] ); ?></span>
					<p class="eo-mcp-level-description"><?php echo esc_html( $level_data['description'] ); ?></p>
					<p class="eo-mcp-level-count" data-eo-mcp-level-count="<?php echo esc_attr( $level ); ?>">
						<?php
						/* translators: 1: enabled abilities, 2: abilities of the level. */
						echo esc_html( sprintf( __( '%1$d / %2$d actives', 'eo-tools' ), $level_enabled, count( $level_abilities ) ) );
						?>
					</p>
					<button type="button" class="button" data-eo-mcp-level="<?php echo esc_attr( $level ); ?>"
						data-label-on="<?php /* translators: %s: risk level. */ echo esc_attr( sprintf( __( 'Activer « %s »', 'eo-tools' ), $level_data['label'] ) ); ?>"
						data-label-off="<?php /* translators: %s: risk level. */ echo esc_attr( sprintf( __( 'Désactiver « %s »', 'eo-tools' ), $level_data['label'] ) ); ?>">
						<?php
						/* translators: %s: risk level. */
						echo esc_html( sprintf( count( $level_abilities ) === $level_enabled ? __( 'Désactiver « %s »', 'eo-tools' ) : __( 'Activer « %s »', 'eo-tools' ), $level_data['label'] ) );
						?>
					</button>
				</div>
			<?php endforeach; ?>
		</div>

		<p class="eo-mcp-feedback" id="eo-mcp-abilities-feedback" role="status" aria-live="polite"></p>

		<?php foreach ( Eotools_Mcp_Abilities::get_groups() as $group_slug => $group_label ) : ?>
			<?php
			$group_abilities = array_filter(
				Eotools_Mcp_Abilities::get_all(),
				function ( $ability ) use ( $group_slug ) {
					return $ability->get_group() === $group_slug;
				}
			);
			if ( empty( $group_abilities ) ) {
				continue;
			}
			?>
			<h2 class="eo-mcp-group-title"><?php echo esc_html( $group_label ); ?></h2>
			<div class="eo-mcp-abilities">
				<?php foreach ( $group_abilities as $ability ) : ?>
					<?php $risk = $ability->get_risk(); ?>
					<label class="eo-mcp-card eo-mcp-ability">
						<span class="eo-mcp-ability-header">
							<span class="eo-mcp-ability-title"><?php echo esc_html( $ability->get_label() ); ?></span>
							<span class="eo-mcp-switch">
								<input type="checkbox" class="eo-mcp-ability-toggle" value="<?php echo esc_attr( $ability->get_name() ); ?>" data-risk="<?php echo esc_attr( $risk ); ?>" <?php checked( Eotools_Mcp_Abilities::is_enabled( $ability ) ); ?>>
								<span class="eo-mcp-switch-slider" aria-hidden="true"></span>
							</span>
						</span>
						<span class="eo-mcp-ability-description">
							<span class="eo-mcp-ability-summary"><?php echo esc_html( $ability->get_summary() ); ?></span>
							<span class="eo-mcp-ability-details" id="eo-mcp-details-<?php echo esc_attr( $ability->get_slug() ); ?>">
								<span class="eo-mcp-ability-details-inner"><?php echo esc_html( $ability->get_description() ); ?></span>
							</span>
							<button type="button" class="button-link eo-mcp-more" aria-expanded="false" aria-controls="eo-mcp-details-<?php echo esc_attr( $ability->get_slug() ); ?>"
								data-label-more="<?php esc_attr_e( 'Voir plus', 'eo-tools' ); ?>" data-label-less="<?php esc_attr_e( 'Voir moins', 'eo-tools' ); ?>"><?php esc_html_e( 'Voir plus', 'eo-tools' ); ?></button>
						</span>
						<span class="eo-mcp-ability-meta">
							<span class="eo-mcp-risk is-<?php echo esc_attr( $risk ); ?>" title="<?php echo esc_attr( $risk_levels[ $risk ]['description'] ); ?>"><?php echo esc_html( $risk_levels[ $risk ]['label'] ); ?></span>
							<code><?php echo esc_html( $ability->get_name() ); ?></code>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>

	<?php else : ?>

		<ol class="eo-mcp-steps">

			<li class="eo-mcp-card eo-mcp-step <?php echo $adapter_ready ? 'is-done' : 'is-current'; ?>">
				<div class="eo-mcp-step-header">
					<span class="eo-mcp-step-number">1</span>
					<div>
						<h2><?php esc_html_e( 'Installer le connecteur MCP', 'eo-tools' ); ?></h2>
						<p><?php esc_html_e( 'EO Tools utilise MCP Adapter, l\'extension officielle de WordPress, pour dialoguer avec votre IA.', 'eo-tools' ); ?></p>
					</div>
				</div>

				<div class="eo-mcp-step-body">
					<?php if ( $adapter_ready ) : ?>
						<p class="eo-mcp-status is-success">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php
							/* translators: %s: MCP Adapter version. */
							echo esc_html( sprintf( __( 'MCP Adapter est installé et actif (version %s).', 'eo-tools' ), $adapter_version ) );
							?>
						</p>
					<?php elseif ( $can_proceed ) : ?>
						<p class="eo-mcp-status is-warning">
							<span class="dashicons dashicons-warning"></span>
							<?php
							echo Eotools_Mcp_Adapter::STATUS_INACTIVE === $adapter_status
								? esc_html__( 'MCP Adapter est installé mais pas activé.', 'eo-tools' )
								: esc_html__( 'MCP Adapter n\'est pas encore installé.', 'eo-tools' );
							?>
						</p>
						<button type="button" class="button button-primary button-hero" id="eo-mcp-install-adapter">
							<?php
							echo Eotools_Mcp_Adapter::STATUS_INACTIVE === $adapter_status
								? esc_html__( 'Activer MCP Adapter', 'eo-tools' )
								: esc_html__( 'Installer et activer MCP Adapter', 'eo-tools' );
							?>
						</button>
						<p class="eo-mcp-feedback" id="eo-mcp-install-feedback" role="status" aria-live="polite"></p>
					<?php else : ?>
						<p class="eo-mcp-status is-error">
							<span class="dashicons dashicons-dismiss"></span>
							<?php esc_html_e( 'L\'installation automatique n\'est pas autorisée sur ce site. Demandez à votre administrateur réseau ou hébergeur d\'installer l\'extension MCP Adapter.', 'eo-tools' ); ?>
						</p>
						<?php if ( current_user_can( 'install_plugins' ) ) : ?>
							<a class="button" href="<?php echo esc_url( $manual_url ); ?>"><?php esc_html_e( 'Installer manuellement', 'eo-tools' ); ?></a>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</li>

			<li class="eo-mcp-card eo-mcp-step <?php echo $adapter_ready ? 'is-current' : 'is-locked'; ?>">
				<div class="eo-mcp-step-header">
					<span class="eo-mcp-step-number">2</span>
					<div>
						<h2><?php esc_html_e( 'Connecter votre IA', 'eo-tools' ); ?></h2>
						<p><?php esc_html_e( 'Choisissez votre assistant et suivez les 3 étapes.', 'eo-tools' ); ?></p>
					</div>
				</div>
				<div class="eo-mcp-step-body">
					<?php if ( ! $adapter_ready ) : ?>
						<p class="eo-mcp-soon"><?php esc_html_e( 'Terminez d\'abord l\'étape 1.', 'eo-tools' ); ?></p>
					<?php else : ?>
						<div class="eo-mcp-clients" role="radiogroup" aria-label="<?php esc_attr_e( 'Votre assistant IA', 'eo-tools' ); ?>">
							<?php foreach ( $clients as $client_id => $client ) : ?>
								<label class="eo-mcp-client">
									<input type="radio" name="eo-mcp-client" value="<?php echo esc_attr( $client_id ); ?>" <?php checked( Eotools_Mcp_Page::get_recommended_client(), $client_id ); ?>>
									<span class="eo-mcp-client-text">
										<span class="eo-mcp-client-label"><?php echo esc_html( $client['label'] ); ?></span>
										<span class="eo-mcp-client-subtitle"><?php echo esc_html( $client['subtitle'] ); ?></span>
									</span>
								</label>
							<?php endforeach; ?>
						</div>

						<div class="eo-mcp-client-panel">
							<p class="eo-mcp-status is-warning" id="eo-mcp-client-warning" hidden>
								<span class="dashicons dashicons-warning"></span>
								<span class="eo-mcp-status-text"></span>
							</p>

							<ol class="eo-mcp-client-steps" id="eo-mcp-client-steps"></ol>
							<div id="eo-mcp-client-snippets"></div>

							<div class="eo-mcp-client-actions">
								<a class="button button-primary" id="eo-mcp-client-link" href="#" target="_blank" rel="noopener noreferrer" hidden></a>
								<button type="button" class="button" id="eo-mcp-create-connection" hidden></button>
							</div>

							<p class="eo-mcp-status is-error" id="eo-mcp-client-unavailable" hidden>
								<span class="dashicons dashicons-dismiss"></span>
								<span class="eo-mcp-status-text"></span>
							</p>
							<p class="eo-mcp-feedback" id="eo-mcp-connection-feedback" role="status" aria-live="polite"></p>

							<div class="eo-mcp-connection-result" id="eo-mcp-connection-result" hidden>
								<p class="eo-mcp-status" id="eo-mcp-connection-test"></p>
								<div id="eo-mcp-connection-snippets"></div>
								<p class="eo-mcp-once">
									<span class="dashicons dashicons-lock"></span>
									<?php esc_html_e( 'Copiez cette configuration maintenant : le mot de passe ne sera plus jamais affiché. En cas de perte, générez simplement une nouvelle connexion.', 'eo-tools' ); ?>
								</p>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</li>

			<li class="eo-mcp-card eo-mcp-step <?php echo $adapter_ready ? 'is-current' : 'is-locked'; ?>">
				<div class="eo-mcp-step-header">
					<span class="eo-mcp-step-number">3</span>
					<div>
						<h2><?php esc_html_e( 'Vérifier la connexion', 'eo-tools' ); ?></h2>
						<p><?php esc_html_e( 'Dès que votre IA utilise la connexion, elle apparaît ici automatiquement.', 'eo-tools' ); ?></p>
					</div>
				</div>
				<div class="eo-mcp-step-body">
					<?php if ( $adapter_ready ) : ?>
						<p class="eo-mcp-status is-success eo-mcp-connected" id="eo-mcp-connected" hidden>
							<span class="dashicons dashicons-yes-alt"></span>
							<span class="eo-mcp-status-text"></span>
						</p>
						<table class="widefat striped eo-mcp-connections">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Connexion', 'eo-tools' ); ?></th>
									<th><?php esc_html_e( 'Créée le', 'eo-tools' ); ?></th>
									<th><?php esc_html_e( 'Statut', 'eo-tools' ); ?></th>
									<th></th>
								</tr>
							</thead>
							<tbody id="eo-mcp-connections-list"></tbody>
						</table>
						<p class="eo-mcp-auto-refresh">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Mise à jour automatique', 'eo-tools' ); ?>
						</p>
					<?php else : ?>
						<p class="eo-mcp-soon"><?php esc_html_e( 'Terminez d\'abord les étapes précédentes.', 'eo-tools' ); ?></p>
					<?php endif; ?>
				</div>
			</li>

		</ol>

	<?php endif; ?>
</div>
