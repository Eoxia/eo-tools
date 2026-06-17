<?php
/**
 * Landing Pages management admin view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'eo_tools_landing_pages_settings', array() );

$defaults = array(
	'coming_soon' => array(
		'active'       => false,
		'title'        => __( 'Bientôt disponible', 'eo-tools' ),
		'description' => __( 'Notre nouveau site est en cours de création. Restez à l\'écoute !', 'eo-tools' ),
		'style'       => 'minimalist',
		'bg_color'    => '#0f172a',
		'text_color'  => '#f8fafc',
		'accent_color'=> '#f59e0b',
	),
	'maintenance' => array(
		'active'       => false,
		'title'        => __( 'Site en maintenance', 'eo-tools' ),
		'description' => __( 'Nous effectuons actuellement des opérations de maintenance. Nous serons de retour très rapidement.', 'eo-tools' ),
		'style'       => 'gradient',
		'bg_color'    => '#1e1b4b',
		'text_color'  => '#f8fafc',
		'accent_color'=> '#6366f1',
	),
	'login' => array(
		'active'                 => false,
		'title'                  => __( 'Connexion', 'eo-tools' ),
		'description'            => __( 'Veuillez vous connecter pour accéder au site.', 'eo-tools' ),
		'style'                  => 'glassmorphism',
		'bg_color'               => '#0f172a',
		'text_color'             => '#f8fafc',
		'accent_color'           => '#06b6d4',
		'email_filtering_active' => false,
		'email_rules'            => '',
		'ip_rules'               => array(),
		'log_limit'              => 1000,
	),
	'register' => array(
		'active'                 => false,
		'title'                  => __( 'Inscription', 'eo-tools' ),
		'description'            => __( 'Créez votre compte pour accéder à nos services.', 'eo-tools' ),
		'style'                  => 'glassmorphism',
		'bg_color'               => '#0f172a',
		'text_color'             => '#f8fafc',
		'accent_color'           => '#10b981',
		'inherit_login_rules'    => true,
		'email_filtering_active' => false,
		'email_rules'            => '',
		'ip_rules'               => array(),
	),
	'404' => array(
		'active'       => false,
		'title'        => __( 'Page non trouvée', 'eo-tools' ),
		'description' => __( 'Désolé, la page que vous recherchez n\'existe pas ou a été déplacée.', 'eo-tools' ),
		'style'       => 'minimalist',
		'bg_color'    => '#0f172a',
		'text_color'  => '#f8fafc',
		'accent_color'=> '#3b82f6',
	),
);

$coming_soon = isset( $settings['coming_soon'] ) ? array_merge( $defaults['coming_soon'], $settings['coming_soon'] ) : $defaults['coming_soon'];
$maintenance = isset( $settings['maintenance'] ) ? array_merge( $defaults['maintenance'], $settings['maintenance'] ) : $defaults['maintenance'];
$login       = isset( $settings['login'] ) ? array_merge( $defaults['login'], $settings['login'] ) : $defaults['login'];
$register    = isset( $settings['register'] ) ? array_merge( $defaults['register'], $settings['register'] ) : $defaults['register'];
$status_404  = isset( $settings['404'] ) ? array_merge( $defaults['404'], $settings['404'] ) : $defaults['404'];

$pages_data = array(
	'coming_soon' => array(
		'title'       => __( 'Mode Prochainement', 'eo-tools' ),
		'desc'        => __( 'La page Prochainement sera accessible aux visiteurs à la place du site en cours de construction.', 'eo-tools' ),
		'icon'        => 'dashicons-clock',
		'config'      => $coming_soon,
		'color_class' => 'eo-status-coming-soon',
	),
	'maintenance' => array(
		'title'       => __( 'Mode Maintenance', 'eo-tools' ),
		'desc'        => __( 'La page de Maintenance informera les visiteurs et les moteurs de recherche que le site est temporairement indisponible.', 'eo-tools' ),
		'icon'        => 'dashicons-admin-tools',
		'config'      => $maintenance,
		'color_class' => 'eo-status-maintenance',
	),
	'login' => array(
		'title'       => __( 'Page de connexion', 'eo-tools' ),
		'desc'        => __( 'Remplacez la page de connexion par défaut (wp-login.php) par un écran de connexion moderne et personnalisé.', 'eo-tools' ),
		'icon'        => 'dashicons-lock',
		'config'      => $login,
		'color_class' => 'eo-status-login',
	),
	'register' => array(
		'title'       => __( 'Page d\'inscription', 'eo-tools' ),
		'desc'        => __( 'Remplacez la page d\'inscription par défaut par un formulaire simplifié (sans identifiant, e-mail uniquement).', 'eo-tools' ),
		'icon'        => 'dashicons-admin-users',
		'config'      => $register,
		'color_class' => 'eo-status-register',
	),
	'404' => array(
		'title'       => __( 'Page 404', 'eo-tools' ),
		'desc'        => __( 'Personnalisez la page d\'erreur 404 (Page non trouvée) affichée lorsque les visiteurs tentent d\'accéder à une URL brisée.', 'eo-tools' ),
		'icon'        => 'dashicons-warning',
		'config'      => $status_404,
		'color_class' => 'eo-status-404',
	),
);
?>

<div class="wrap eo-landing-pages-admin-wrapper">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'EO Blocks - Pages d\'atterrissage', 'eo-tools' ); ?></h1>
	<p class="description" style="margin: 8px 0 20px 0; font-size: 14px;">
		<?php esc_html_e( 'Contrôlez ce que voient vos visiteurs lorsque votre site est en construction, en maintenance, ou lorsqu\'ils rencontrent des erreurs.', 'eo-tools' ); ?>
	</p>
	<hr class="wp-header-end">

	<!-- Grille des modes de pages -->
	<div class="eo-lp-grid">
		<?php foreach ( $pages_data as $key => $page ) : ?>
			<?php 
				$is_active = !empty( $page['config']['active'] ); 
				$preview_url = add_query_arg( 'eo_preview_landing_page', $key, home_url() );
			?>
			<div class="eo-lp-card <?php echo esc_attr( $page['color_class'] ); ?> <?php echo $is_active ? 'active' : ''; ?>" data-type="<?php echo esc_attr( $key ); ?>">
				<div class="eo-lp-card-header">
					<span class="dashicons <?php echo esc_attr( $page['icon'] ); ?> eo-lp-card-icon"></span>
					<div class="eo-lp-toggle-wrapper">
						<label class="eo-lp-switch">
							<input type="checkbox" class="eo-lp-toggle-checkbox" <?php checked( $is_active ); ?> />
							<span class="eo-lp-slider"></span>
						</label>
						<span class="eo-lp-toggle-label <?php echo $is_active ? 'active' : ''; ?>">
							<?php echo $is_active ? esc_html__( 'ACTIF', 'eo-tools' ) : esc_html__( 'INACTIF', 'eo-tools' ); ?>
						</span>
					</div>
				</div>
				<div class="eo-lp-card-body">
					<h3 class="eo-lp-card-title"><?php echo esc_html( $page['title'] ); ?></h3>
					<p class="eo-lp-card-desc"><?php echo esc_html( $page['desc'] ); ?></p>
					<?php if ( 'login' === $key || 'register' === $key ) : ?>
						<div class="eo-lp-card-sub-toggle" style="margin-top: 15px; display: flex; align-items: center; gap: 8px; padding-top: 10px; border-top: 1px dashed #e2e8f0;">
							<label class="eo-lp-switch" style="width: 34px; height: 18px;">
								<input type="checkbox" class="eo-lp-email-filter-toggle" <?php checked( !empty( $page['config']['email_filtering_active'] ) ); ?> style="width:0; height:0; opacity:0;" />
								<span class="eo-lp-slider" style="border-radius: 18px;"></span>
							</label>
							<span style="font-size: 11px; font-weight: 600; color: #64748b;">
								<?php esc_html_e( 'Filtrage e-mails', 'eo-tools' ); ?>
							</span>
						</div>
					<?php endif; ?>
				</div>
				<div class="eo-lp-card-footer">
					<button type="button" class="button button-primary eo-lp-edit-btn" data-type="<?php echo esc_attr( $key ); ?>">
						<?php esc_html_e( 'Modifier la page', 'eo-tools' ); ?>
					</button>
					<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" class="button button-secondary eo-lp-preview-btn">
						<?php esc_html_e( 'Prévisualisation', 'eo-tools' ); ?>
					</a>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- Panneau d'édition des pages -->
	<div class="eo-lp-editor-panel" style="display: none;">
		<div class="eo-lp-editor-header">
			<h2 class="eo-lp-editor-title">
				<span class="dashicons eo-lp-editor-icon"></span>
				<span class="eo-lp-editor-title-text"><?php esc_html_e( 'Configuration', 'eo-tools' ); ?></span>
			</h2>
			<button type="button" class="eo-lp-editor-close-btn">&times;</button>
		</div>
		
		<form id="eo-lp-editor-form" method="post">
			<input type="hidden" name="type" id="eo-lp-form-type" value="" />
			
			<div class="eo-lp-editor-grid">
				<!-- Colonne de gauche : contenu -->
				<div class="eo-lp-editor-col-left">
					<div class="eo-lp-form-group">
						<label for="eo-lp-form-title"><?php esc_html_e( 'Titre principal', 'eo-tools' ); ?></label>
						<input type="text" id="eo-lp-form-title" name="title" class="regular-text" style="width: 100%;" required />
						<p class="description"><?php esc_html_e( 'S\'affiche comme titre majeur sur la page.', 'eo-tools' ); ?></p>
					</div>

					<div class="eo-lp-form-group">
						<label for="eo-lp-form-description"><?php esc_html_e( 'Texte / Description', 'eo-tools' ); ?></label>
						<textarea id="eo-lp-form-description" name="description" rows="6" style="width: 100%; font-family: sans-serif;" required></textarea>
						<p class="description"><?php esc_html_e( 'Description ou informations affichées sous le titre (accepte le code HTML basique).', 'eo-tools' ); ?></p>
					</div>
					
					<div class="eo-lp-form-group eo-lp-form-login-hint" style="display: none; background: #e0f2fe; color: #0369a1; padding: 12px; border-radius: 6px; border-left: 4px solid #0284c7;">
						<span class="dashicons dashicons-info" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Le formulaire de connexion standard WordPress sécurisé sera automatiquement intégré en dessous du texte.', 'eo-tools' ); ?>
					</div>

					<div class="eo-lp-form-group eo-lp-form-404-hint" style="display: none; background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 6px; border-left: 4px solid #ef4444;">
						<span class="dashicons dashicons-info" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Un bouton "Retour à l\'accueil" redirigeant vers le site sera automatiquement affiché en dessous du texte.', 'eo-tools' ); ?>
					</div>

					<div class="eo-lp-form-group eo-lp-form-register-hint" style="display: none; background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; border-left: 4px solid #22c55e;">
						<span class="dashicons dashicons-info" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Un formulaire d\'inscription simplifié (E-mail uniquement) sera automatiquement affiché. L\'identifiant sera généré à partir de l\'e-mail.', 'eo-tools' ); ?>
					</div>

					<div id="eo-lp-register-success-action-group" style="display: none; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
						<div class="eo-lp-form-group">
							<label for="eo-lp-form-success-action" style="font-weight: 600; display: block; margin-bottom: 8px;"><?php esc_html_e( 'Action après l\'inscription (Page d\'attente)', 'eo-tools' ); ?></label>
							<select id="eo-lp-form-success-action" name="success_action" style="width: 100%; max-width: 400px; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
								<option value="none"><?php esc_html_e( 'Formulaire de connexion (Classique)', 'eo-tools' ); ?></option>
								<option value="timer"><?php esc_html_e( 'Minuteur simple', 'eo-tools' ); ?></option>
								<option value="tictactoe"><?php esc_html_e( 'Mini-jeu : Morpion (Tic-Tac-Toe)', 'eo-tools' ); ?></option>
								<option value="flappybird"><?php esc_html_e( 'Mini-jeu : Flappy Bird', 'eo-tools' ); ?></option>
							</select>
							<p class="description" style="margin-top: 6px;"><?php esc_html_e( 'Détermine ce qui est affiché à l\'utilisateur juste après son inscription, pendant qu\'il attend son e-mail de confirmation.', 'eo-tools' ); ?></p>
						</div>
					</div>

					<div id="eo-lp-login-security-section" style="display: none; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
						<h3 style="margin-top: 0; font-size: 16px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-shield"></span>
							<?php esc_html_e( 'Sécurité & Filtrage', 'eo-tools' ); ?>
						</h3>
						
						<div id="eo-lp-register-inherit-group" style="display: none; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
							<div style="display: flex; align-items: center; justify-content: space-between;">
								<div style="flex: 1;">
									<span style="font-weight: 600; font-size: 13px; color: #92400e;">
										<?php esc_html_e( 'Hériter des règles de sécurité de la Connexion', 'eo-tools' ); ?>
									</span>
									<p class="description" style="margin-top: 4px; margin-bottom: 0; color: #b45309;">
										<?php esc_html_e( 'Si activé, l\'inscription utilisera exactement les mêmes règles d\'e-mail et d\'IP configurées pour la page de connexion. Désactivez pour personnaliser.', 'eo-tools' ); ?>
									</p>
								</div>
								<label class="eo-lp-switch" style="margin-left: 15px;">
									<input type="checkbox" id="eo-lp-inherit-login-rules" name="inherit_login_rules" value="1" />
									<span class="eo-lp-slider"></span>
								</label>
							</div>
						</div>

						<div id="eo-lp-security-settings-wrapper">
							<div id="eo-lp-register-inherit-banner" style="display: none; background: #e0f2fe; border: 1px solid #bae6fd; border-radius: 8px; padding: 12px; margin-bottom: 20px; color: #0369a1; font-size: 13px;">
								<span class="dashicons dashicons-lock" style="vertical-align: middle; margin-right: 4px;"></span>
								Paramètres en mode lecture seule (hérités). 
								<a href="#" id="eo-lp-link-to-login" style="color: #0284c7; font-weight: 600; text-decoration: underline;">Modifier les paramètres de la Connexion</a>
							</div>
							
							<!-- Filtrage E-mails -->
							<div class="eo-lp-security-box" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
								<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
									<span style="font-weight: 600; font-size: 13px; color: #334155;">
										<?php esc_html_e( 'Activer le filtrage par adresse e-mail', 'eo-tools' ); ?>
									</span>
									<label class="eo-lp-switch">
										<input type="checkbox" id="eo-lp-email-filtering-active" name="email_filtering_active" value="1" />
										<span class="eo-lp-slider"></span>
									</label>
								</div>
							<div class="eo-lp-form-group eo-lp-email-rules-group" style="display: none;">
								<div style="display:flex; gap:15px;">
									<div style="flex:1;">
										<label for="eo-lp-email-rules-blocked" style="font-weight:600; color:#b91c1c; display:block; margin-bottom:4px;"><?php esc_html_e( 'Interdits', 'eo-tools' ); ?></label>
										<textarea id="eo-lp-email-rules-blocked" rows="3" style="width: 100%; font-family: monospace; border-color: #fecaca; background-color: #fef2f2;" placeholder="*.ru, *.ovh, spammer@gmail.com"></textarea>
										<p class="description" style="font-size:11px; line-height: 1.3;">
											<?php esc_html_e( 'Séparez par des virgules. Ces correspondances seront bloquées (inutile d\'ajouter "!").', 'eo-tools' ); ?>
										</p>
									</div>
									<div style="flex:1;">
										<label for="eo-lp-email-rules-allowed" style="font-weight:600; color:#15803d; display:block; margin-bottom:4px;"><?php esc_html_e( 'Autorisés uniquement', 'eo-tools' ); ?></label>
										<textarea id="eo-lp-email-rules-allowed" rows="3" style="width: 100%; font-family: monospace; border-color: #bbf7d0; background-color: #f0fdf4;" placeholder="@eoxia.com, admin@monsite.fr, @lenomdomaine"></textarea>
										<p class="description" style="font-size:11px; line-height: 1.3;">
											<?php esc_html_e( 'Séparez par des virgules. Si rempli, seuls ces e-mails/domaines pourront se connecter.', 'eo-tools' ); ?>
										</p>
									</div>
								</div>
								<input type="hidden" id="eo-lp-email-rules" name="email_rules" value="" />

								<div class="eo-lp-email-test-wrapper" style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #e2e8f0; display: flex; align-items: center; gap: 10px;">
									<span style="font-size: 12px; font-weight: 600; color: #475569; min-width: 120px;">
										<?php esc_html_e( 'Tester une adresse :', 'eo-tools' ); ?>
									</span>
									<div style="position: relative; flex: 1; display: flex; align-items: center; gap: 10px;">
										<input type="text" id="eo-lp-email-test-input" placeholder="ex: user@eoxia.com" style="flex: 1; height: 32px; font-size: 12px;" />
										<span id="eo-lp-email-test-result" style="font-size: 11px; font-weight: bold; border-radius: 4px; padding: 4px 10px; display: none;"></span>
									</div>
								</div>
							</div>
						</div>

						<!-- Filtrage IP -->
						<div class="eo-lp-security-box" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
							<h4 style="margin-top: 0; margin-bottom: 12px; font-size: 13px; font-weight: 600; color: #334155;">
								<?php esc_html_e( 'Contrôle d\'accès par adresses IP / CIDR', 'eo-tools' ); ?>
							</h4>
							<p class="description" style="margin-bottom: 12px;">
								<?php esc_html_e( 'Définissez des règles d\'autorisation ou de blocage d\'adresses IP. Si des règles d\'autorisation existent, seules ces IP pourront se connecter.', 'eo-tools' ); ?>
							</p>
							
							<div class="eo-lp-ip-rules-container">
								<table class="wp-list-table widefat fixed striped eo-lp-ip-rules-table" style="margin-bottom: 12px; border-radius: 6px; overflow: hidden; border: 1px solid #cbd5e1;">
									<thead>
										<tr>
											<th style="width: 50%; font-weight: 600; padding: 8px 10px;"><?php esc_html_e( 'Adresse IP / CIDR', 'eo-tools' ); ?></th>
											<th style="width: 30%; font-weight: 600; padding: 8px 10px;"><?php esc_html_e( 'Action', 'eo-tools' ); ?></th>
											<th style="width: 20%; text-align: right; font-weight: 600; padding: 8px 10px;"><?php esc_html_e( 'Actions', 'eo-tools' ); ?></th>
										</tr>
									</thead>
									<tbody id="eo-lp-ip-rules-tbody">
										<!-- IP rules will be loaded dynamically here -->
									</tbody>
								</table>
								
								<div class="eo-lp-ip-add-controls" style="display: flex; gap: 10px; align-items: flex-end;">
									<div style="flex: 2; display: flex; flex-direction: column;">
										<label for="eo-lp-new-ip-val" style="font-size: 11px; font-weight: 600; margin-bottom: 4px;"><?php esc_html_e( 'IP ou CIDR (ex: 192.168.1.0/24)', 'eo-tools' ); ?></label>
										<input type="text" id="eo-lp-new-ip-val" placeholder="192.168.1.1" style="height: 32px; font-size: 12px;" />
									</div>
									<div style="flex: 1.5; display: flex; flex-direction: column;">
										<label for="eo-lp-new-ip-action" style="font-size: 11px; font-weight: 600; margin-bottom: 4px;"><?php esc_html_e( 'Règle', 'eo-tools' ); ?></label>
										<select id="eo-lp-new-ip-action" style="height: 32px; font-size: 12px; padding: 0 6px;">
											<option value="allow"><?php esc_html_e( 'Autoriser (Allow)', 'eo-tools' ); ?></option>
											<option value="block"><?php esc_html_e( 'Bloquer (Block)', 'eo-tools' ); ?></option>
										</select>
									</div>
									<button type="button" id="eo-lp-add-ip-rule-btn" class="button button-secondary" style="height: 32px; line-height: 30px;">
										<?php esc_html_e( 'Ajouter', 'eo-tools' ); ?>
									</button>
								</div>
								
								<input type="hidden" id="eo-lp-ip-rules-hidden" name="ip_rules" value="[]" />
							</div>
						</div>
						</div> <!-- End security settings wrapper -->

						<!-- Limite des logs -->
						<div class="eo-lp-security-box eo-lp-logs-box" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
							<div style="display: flex; align-items: center; justify-content: space-between;">
								<span style="font-weight: 600; font-size: 13px; color: #334155;">
									<?php esc_html_e( 'Seuil de purge automatique des logs', 'eo-tools' ); ?>
								</span>
								<input type="number" id="eo-lp-log-limit" name="log_limit" min="1" max="100000" style="width: 100px; height: 32px;" value="1000" />
							</div>
							<p class="description" style="margin-top: 8px; margin-bottom: 0;">
								<?php esc_html_e( 'Nombre maximum de tentatives de connexions à conserver dans le journal (par défaut 1000).', 'eo-tools' ); ?>
							</p>
						</div>

						<!-- Journal de connexion -->
						<div class="eo-lp-security-box eo-lp-logs-box" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px;">
							<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
								<h4 style="margin: 0; font-size: 14px; font-weight: 600; color: #1e293b;">
									<?php esc_html_e( 'Journal des tentatives de connexion', 'eo-tools' ); ?>
								</h4>
								<button type="button" id="eo-lp-clear-logs-btn" class="button button-link-delete" style="color: #d63638; text-decoration: none;">
									<span class="dashicons dashicons-trash" style="vertical-align: middle; font-size: 16px;"></span>
									<?php esc_html_e( 'Vider le journal', 'eo-tools' ); ?>
								</button>
							</div>
							
							<div class="eo-lp-logs-table-wrapper" style="max-height: 300px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px;">
								<table class="wp-list-table widefat fixed striped eo-lp-logs-table" style="border: none;">
									<thead>
										<tr>
											<th style="font-weight: 600; font-size: 11px; padding: 8px;"><?php esc_html_e( 'Date', 'eo-tools' ); ?></th>
											<th style="font-weight: 600; font-size: 11px; padding: 8px;"><?php esc_html_e( 'IP', 'eo-tools' ); ?></th>
											<th style="font-weight: 600; font-size: 11px; padding: 8px;"><?php esc_html_e( 'Identifiant', 'eo-tools' ); ?></th>
											<th style="font-weight: 600; font-size: 11px; padding: 8px;"><?php esc_html_e( 'Statut', 'eo-tools' ); ?></th>
											<th style="font-weight: 600; font-size: 11px; padding: 8px;"><?php esc_html_e( 'Navigateur', 'eo-tools' ); ?></th>
										</tr>
									</thead>
									<tbody id="eo-lp-logs-tbody">
										<tr>
											<td colspan="5" style="text-align: center; padding: 20px; color: #64748b;">
												<?php esc_html_e( 'Chargement des données...', 'eo-tools' ); ?>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>

				<!-- Colonne de droite : Design & Style -->
				<div class="eo-lp-editor-col-right">
					<div class="eo-lp-form-group">
						<label for="eo-lp-form-style"><?php esc_html_e( 'Style esthétique', 'eo-tools' ); ?></label>
						<select id="eo-lp-form-style" name="style" style="width: 100%; height: 35px;">
							<option value="minimalist"><?php esc_html_e( 'Minimaliste Moderne', 'eo-tools' ); ?></option>
							<option value="gradient"><?php esc_html_e( 'Dégradé Premium', 'eo-tools' ); ?></option>
							<option value="glassmorphism"><?php esc_html_e( 'Effet Verre (Glassmorphism)', 'eo-tools' ); ?></option>
						</select>
					</div>

					<div class="eo-lp-form-group">
						<label for="eo-lp-form-bg-color"><?php esc_html_e( 'Couleur d\'arrière-plan', 'eo-tools' ); ?></label>
						<div class="eo-lp-color-picker-wrapper">
							<input type="color" id="eo-lp-form-bg-color" name="bg_color" />
							<input type="text" id="eo-lp-form-bg-color-text" class="small-text" />
						</div>
					</div>

					<div class="eo-lp-form-group">
						<label for="eo-lp-form-text-color"><?php esc_html_e( 'Couleur du texte', 'eo-tools' ); ?></label>
						<div class="eo-lp-color-picker-wrapper">
							<input type="color" id="eo-lp-form-text-color" name="text_color" />
							<input type="text" id="eo-lp-form-text-color-text" class="small-text" />
						</div>
					</div>

					<div class="eo-lp-form-group">
						<label for="eo-lp-form-accent-color"><?php esc_html_e( 'Couleur d\'accentuation (Boutons / Détails)', 'eo-tools' ); ?></label>
						<div class="eo-lp-color-picker-wrapper">
							<input type="color" id="eo-lp-form-accent-color" name="accent_color" />
							<input type="text" id="eo-lp-form-accent-color-text" class="small-text" />
						</div>
					</div>
				</div>
			</div>

			<!-- Pied de formulaire -->
			<div class="eo-lp-editor-footer">
				<div class="eo-lp-editor-status-text"></div>
				<button type="submit" id="eo-lp-save-btn" class="button button-primary button-large">
					<?php esc_html_e( 'Enregistrer les paramètres', 'eo-tools' ); ?>
				</button>
				<button type="button" class="button button-secondary button-large eo-lp-form-preview-btn" target="_blank">
					<?php esc_html_e( 'Prévisualiser', 'eo-tools' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>

<script type="text/javascript">
	// Local data configuration passed to JS
	window.eoLandingPagesConfig = <?php echo json_encode( array(
		'coming_soon' => $coming_soon,
		'maintenance' => $maintenance,
		'login'       => $login,
		'register'    => $register,
		'404'         => $status_404,
		'homeUrl'     => home_url(),
	) ); ?>;
</script>
