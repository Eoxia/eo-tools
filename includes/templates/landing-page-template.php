<?php
/**
 * Dynamic Template for Public Landing Pages (Coming Soon, Maintenance, Login, 404)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'eo_lp_format_description' ) ) {
	function eo_lp_format_description( $text ) {
		$text = wp_kses_post( $text );

		// 1. Headings (###, ##, #)
		$text = preg_replace( '/^\s*###\s+(.+)$/m', '<h3>$1</h3>', $text );
		$text = preg_replace( '/^\s*##\s+(.+)$/m', '<h2>$1</h2>', $text );
		$text = preg_replace( '/^\s*#\s+(.+)$/m', '<h1>$1</h1>', $text );

		// 2. Bold (**text**)
		$text = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text );

		// 3. Italic (*text*)
		$text = preg_replace( '/\*(.*?)\*/', '<em>$1</em>', $text );

		// 4. Bullet lists
		$lines = explode( "\n", $text );
		$in_list = false;
		$formatted_lines = array();

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( preg_match( '/^[-*]\s+(.+)$/', $trimmed, $matches ) ) {
				if ( ! $in_list ) {
					$formatted_lines[] = '<ul>';
					$in_list = true;
				}
				$formatted_lines[] = '<li>' . $matches[1] . '</li>';
			} else {
				if ( $in_list ) {
					$formatted_lines[] = '</ul>';
					$in_list = false;
				}
				$formatted_lines[] = $line;
			}
		}
		if ( $in_list ) {
			$formatted_lines[] = '</ul>';
		}
		$text = implode( "\n", $formatted_lines );

		// 5. Line breaks for paragraphs (double newline to paragraph, single to <br>)
		$parts = explode( "\n\n", $text );
		foreach ( $parts as &$part ) {
			$trimmed_part = trim( $part );
			if ( empty( $trimmed_part ) ) {
				continue;
			}
			if ( ! preg_match( '/^<(h1|h2|h3|ul|li)/i', $trimmed_part ) ) {
				$part = '<p>' . nl2br( $trimmed_part ) . '</p>';
			}
		}
		$text = implode( "\n", $parts );

		return $text;
	}
}

$title        = $page_settings['title'] ?? '';
$description  = $page_settings['description'] ?? '';
$style        = $page_settings['style'] ?? 'minimalist';
$bg_color     = $page_settings['bg_color'] ?? '#0f172a';
$text_color   = $page_settings['text_color'] ?? '#f8fafc';
$accent_color = $page_settings['accent_color'] ?? '#3b82f6';

// Determine if the text/bg is light or dark for appropriate contrasts
$is_light_bg = ( hexdec( substr( $bg_color, 1, 2 ) ) + hexdec( substr( $bg_color, 3, 2 ) ) + hexdec( substr( $bg_color, 5, 2 ) ) ) > 380;
$card_bg     = $is_light_bg ? 'rgba(255, 255, 255, 0.85)' : 'rgba(15, 23, 42, 0.65)';
$card_border = $is_light_bg ? 'rgba(0, 0, 0, 0.08)' : 'rgba(255, 255, 255, 0.08)';
$input_bg    = $is_light_bg ? 'rgba(0, 0, 0, 0.03)' : 'rgba(255, 255, 255, 0.05)';
$input_border= $is_light_bg ? 'rgba(0, 0, 0, 0.15)' : 'rgba(255, 255, 255, 0.15)';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $title ); ?></title>
	
	<!-- Load Google Fonts -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
	
	<!-- Load WordPress Dashicons -->
	<link rel="stylesheet" href="<?php echo esc_url( includes_url( 'css/dashicons.min.css' ) ); ?>">
	
	<style>
		/* Core Resets */
		* {
			box-sizing: border-box;
			margin: 0;
			padding: 0;
		}
		
		body {
			font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
			background-color: <?php echo esc_html( $bg_color ); ?>;
			color: <?php echo esc_html( $text_color ); ?>;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			overflow-x: hidden;
			position: relative;
			line-height: 1.6;
		}

		/* Typography */
		h1 {
			font-family: 'Outfit', sans-serif;
			font-size: clamp(2rem, 5vw, 3.5rem);
			font-weight: 800;
			line-height: 1.15;
			margin-bottom: 1rem;
			color: <?php echo $is_light_bg ? '#0f172a' : '#ffffff'; ?>;
		}

		.description {
			font-size: clamp(1rem, 2.5vw, 1.25rem);
			color: <?php echo $is_light_bg ? '#475569' : '#94a3b8'; ?>;
			margin-bottom: 2rem;
			max-width: 600px;
			margin-left: auto;
			margin-right: auto;
		}

		.eo-lp-box ul {
			text-align: left;
			margin: 1rem 0;
			padding-left: 1.5rem;
		}

		.eo-lp-box li {
			margin-bottom: 0.5rem;
		}

		/* Base Card Container */
		.eo-lp-container {
			width: 100%;
			max-width: 650px;
			padding: 2.5rem;
			text-align: center;
			z-index: 10;
			position: relative;
		}

		/* --- Theme Style 1: Minimalist --- */
		<?php if ( 'minimalist' === $style ) : ?>
		.eo-lp-box {
			background: <?php echo $card_bg; ?>;
			border: 1px solid <?php echo $card_border; ?>;
			border-radius: 16px;
			padding: 3.5rem 2.5rem;
			box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
		}
		<?php endif; ?>

		/* --- Theme Style 2: Gradient Background --- */
		<?php if ( 'gradient' === $style ) : ?>
		body {
			background: linear-gradient(135deg, <?php echo esc_html( $bg_color ); ?> 0%, <?php echo esc_html( $accent_color ); ?> 100%);
		}
		.eo-lp-box {
			background: rgba(255, 255, 255, 0.1);
			border: 1px solid rgba(255, 255, 255, 0.15);
			backdrop-filter: blur(10px);
			border-radius: 20px;
			padding: 3.5rem 2.5rem;
			box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
		}
		/* Animated Blobs */
		.blob {
			position: absolute;
			border-radius: 50%;
			filter: blur(60px);
			z-index: 1;
			opacity: 0.4;
			animation: float 8s infinite alternate ease-in-out;
		}
		.blob-1 {
			width: 300px;
			height: 300px;
			background: <?php echo esc_html( $accent_color ); ?>;
			top: -100px;
			right: -100px;
		}
		.blob-2 {
			width: 250px;
			height: 250px;
			background: <?php echo esc_html( $bg_color ); ?>;
			bottom: -100px;
			left: -100px;
			animation-delay: -3s;
		}
		@keyframes float {
			0% { transform: translateY(0) scale(1); }
			100% { transform: translateY(20px) scale(1.1); }
		}
		<?php endif; ?>

		/* --- Theme Style 3: Glassmorphism --- */
		<?php if ( 'glassmorphism' === $style ) : ?>
		body {
			background-color: #0b0f19;
			background-image: 
				radial-gradient(at 10% 20%, <?php echo esc_html( $bg_color ); ?> 0px, transparent 50%),
				radial-gradient(at 90% 80%, <?php echo esc_html( $accent_color ); ?> 0px, transparent 50%);
		}
		.eo-lp-box {
			background: rgba(15, 23, 42, 0.55);
			border: 1px solid rgba(255, 255, 255, 0.08);
			backdrop-filter: blur(20px) saturate(180%);
			-webkit-backdrop-filter: blur(20px) saturate(180%);
			border-radius: 24px;
			padding: 4rem 3rem;
			box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
		}
		<?php endif; ?>

		/* Custom Buttons / Actions */
		.eo-lp-btn {
			display: inline-block;
			background-color: <?php echo esc_html( $accent_color ); ?>;
			color: #ffffff !important;
			text-decoration: none;
			padding: 0.75rem 1.75rem;
			border-radius: 8px;
			font-weight: 600;
			font-size: 1rem;
			transition: filter 0.2s, transform 0.1s;
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
			border: none;
			cursor: pointer;
		}
		.eo-lp-btn:hover {
			filter: brightness(1.1);
		}
		.eo-lp-btn:active {
			transform: scale(0.98);
		}

		/* Error notices style */
		.eo-login-error {
			background-color: rgba(239, 68, 68, 0.1);
			color: #ef4444;
			border: 1px solid rgba(239, 68, 68, 0.2);
			border-radius: 8px;
			padding: 10px 14px;
			margin-bottom: 1.5rem;
			font-size: 13px;
			font-weight: 500;
			text-align: left;
		}

		/* WordPress Login Form Styling customization */
		#eo-loginform, #eo-registerform {
			text-align: left;
			margin-top: 1.5rem;
		}
		#eo-loginform p, #eo-registerform p.eo-lp-form-row {
			margin-bottom: 1.25rem;
		}
		#eo-loginform label, #eo-registerform label {
			display: block;
			font-size: 13px;
			font-weight: 600;
			margin-bottom: 6px;
			color: <?php echo $is_light_bg ? '#475569' : '#cbd5e1'; ?>;
		}
		#eo-loginform input[type="text"],
		#eo-loginform input[type="password"],
		#eo-registerform input[type="email"] {
			width: 100%;
			padding: 0.75rem 1rem;
			font-size: 14px;
			background: <?php echo $input_bg; ?>;
			border: 1px solid <?php echo $input_border; ?>;
			border-radius: 8px;
			color: <?php echo $is_light_bg ? '#0f172a' : '#ffffff'; ?>;
			transition: border-color 0.2s, box-shadow 0.2s;
		}
		#eo-loginform input[type="text"]:focus,
		#eo-loginform input[type="password"]:focus,
		#eo-registerform input[type="email"]:focus {
			border-color: <?php echo esc_html( $accent_color ); ?>;
			box-shadow: 0 0 0 3px <?php echo esc_html( $accent_color ); ?>25;
			outline: none;
		}
		#eo-loginform .login-remember {
			display: flex;
			align-items: center;
		}
		#eo-loginform .login-remember label {
			display: flex;
			align-items: center;
			cursor: pointer;
			font-weight: 500;
			user-select: none;
		}
		#eo-loginform .login-remember input[type="checkbox"] {
			margin-right: 8px;
			width: 16px;
			height: 16px;
			cursor: pointer;
		}
		#eo-loginform .login-submit,
		#eo-registerform .login-submit {
			margin-bottom: 0;
			margin-top: 1.5rem;
		}
		#eo-loginform input[type="submit"],
		#eo-registerform input[type="submit"] {
			width: 100%;
			background-color: <?php echo esc_html( $accent_color ); ?>;
			color: #ffffff;
			border: none;
			padding: 0.75rem;
			border-radius: 8px;
			font-size: 15px;
			font-weight: 600;
			cursor: pointer;
			transition: filter 0.2s, transform 0.1s;
			box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
		}
		#eo-loginform input[type="submit"]:hover,
		#eo-registerform input[type="submit"]:hover {
			filter: brightness(1.1);
		}
		#eo-loginform input[type="submit"]:active,
		#eo-registerform input[type="submit"]:active {
			transform: scale(0.98);
		}
	</style>
</head>
<body>

	<?php if ( 'gradient' === $style ) : ?>
		<div class="blob blob-1"></div>
		<div class="blob blob-2"></div>
	<?php endif; ?>

	<div class="eo-lp-container">
		<div class="eo-lp-box">
			<h1><?php echo esc_html( $title ); ?></h1>
			<div class="description"><?php echo eo_lp_format_description( $description ); ?></div>
			<?php
			$success_action = 'none';
			$show_wait_screen = false;
			if ( 'login' === $type && isset( $_GET['checkemail'] ) && 'registered' === $_GET['checkemail'] ) {
				$success_action = $settings_all['register']['success_action'] ?? 'none';
				if ( 'none' !== $success_action ) {
					$show_wait_screen = true;
				}
			}
			?>
			
			<?php if ( 'login' === $type ) : ?>
				
				<?php if ( isset( $_GET['checkemail'] ) && 'registered' === $_GET['checkemail'] ) : ?>
					<div class="eo-login-error" id="eo-lp-register-success-msg" style="background-color: rgba(34, 197, 94, 0.1); color: #16a34a; border-color: rgba(34, 197, 94, 0.2); <?php echo $show_wait_screen ? 'display: none;' : ''; ?>">
						<span class="dashicons dashicons-yes" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Inscription réussie. Veuillez consulter votre boîte de réception pour définir votre mot de passe.', 'eo-tools' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( isset( $_GET['login_error'] ) ) : ?>
					<div class="eo-login-error">
						<span class="dashicons dashicons-warning" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Identifiant ou mot de passe incorrect. Veuillez réessayer.', 'eo-tools' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $show_wait_screen ) {
					require_once plugin_dir_path( __FILE__ ) . 'wait-screen-template.php';
				} ?>

				<div id="eo-lp-login-form-wrapper" style="<?php echo $show_wait_screen ? 'display: none;' : ''; ?>">
					<?php
					$redirect = !empty( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : admin_url();
					wp_login_form( array(
						'echo'           => true,
						'redirect'       => $redirect,
						'form_id'        => 'eo-loginform',
						'label_username' => __( 'Identifiant ou E-mail', 'eo-tools' ),
						'label_password' => __( 'Mot de passe', 'eo-tools' ),
						'label_remember' => __( 'Se souvenir de moi', 'eo-tools' ),
						'label_log_in'   => __( 'Se connecter', 'eo-tools' ),
						'remember'       => true,
						'value_remember' => true,
					) );
					?>

					<?php 
					$register_active = !empty( $settings_all['register']['active'] );
					if ( get_option( 'users_can_register' ) || $register_active ) : ?>
						<p style="margin-top: 20px; font-size: 14px; text-align: center;">
							<a href="<?php echo esc_url( wp_registration_url() ); ?>" style="color: <?php echo esc_attr( $accent_color ); ?>; text-decoration: none; font-weight: 500;">
								<?php esc_html_e( 'Pas encore de compte ? S\'inscrire', 'eo-tools' ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>

			<?php elseif ( 'register' === $type ) : ?>
				
				<?php if ( isset( $_GET['register_error'] ) ) : ?>
					<div class="eo-login-error">
						<span class="dashicons dashicons-warning" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php
						if ( 'invalid_email' === $_GET['register_error'] ) {
							esc_html_e( 'Adresse e-mail invalide.', 'eo-tools' );
						} elseif ( 'email_exists' === $_GET['register_error'] ) {
							esc_html_e( 'Cette adresse e-mail est déjà utilisée.', 'eo-tools' );
						} elseif ( 'email_not_allowed' === $_GET['register_error'] ) {
							esc_html_e( 'Cette adresse e-mail n\'est pas autorisée à s\'inscrire sur ce site.', 'eo-tools' );
						} else {
							esc_html_e( 'Erreur lors de l\'inscription. Veuillez réessayer.', 'eo-tools' );
						}
						?>
					</div>
				<?php endif; ?>

				<form name="registerform" id="eo-registerform" action="<?php echo esc_url( wp_login_url() . '?action=register' ); ?>" method="post">
					<p class="eo-lp-form-row">
						<label for="user_email"><?php esc_html_e( 'Adresse E-mail', 'eo-tools' ); ?></label>
						<input type="email" name="user_email" id="user_email" class="input" value="" size="25" required />
					</p>
					<p id="reg_passmail" style="font-size: 13px; color: <?php echo $is_light_bg ? '#64748b' : '#94a3b8'; ?>; margin-bottom: 15px;">
						<?php esc_html_e( 'La confirmation d\'inscription vous sera envoyée par e-mail.', 'eo-tools' ); ?>
					</p>
					<p class="submit login-submit">
						<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'S\'inscrire', 'eo-tools' ); ?>" />
					</p>
				</form>
				<p style="margin-top: 20px; font-size: 14px;">
					<a href="<?php echo esc_url( wp_login_url() ); ?>" style="color: <?php echo esc_attr( $accent_color ); ?>; text-decoration: none; font-weight: 500;">
						<?php esc_html_e( 'Déjà un compte ? Se connecter', 'eo-tools' ); ?>
					</a>
				</p>

			<?php elseif ( '404' === $type ) : ?>
				<a href="<?php echo esc_url( home_url() ); ?>" class="eo-lp-btn">
					<?php esc_html_e( 'Retour à l\'accueil', 'eo-tools' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( 'login' === $type || 'register' === $type ) : ?>
		<?php
			$email_filtering_active = false;
			$email_rules = '';
			if ( 'login' === $type ) {
				$email_filtering_active = !empty( $page_settings['email_filtering_active'] );
				$email_rules = $page_settings['email_rules'] ?? '';
			} elseif ( 'register' === $type ) {
				$inherit = !empty( $page_settings['inherit_login_rules'] );
				$email_filtering_active = $inherit ? !empty( $settings_all['login']['email_filtering_active'] ) : !empty( $page_settings['email_filtering_active'] );
				$email_rules = $inherit ? ( $settings_all['login']['email_rules'] ?? '' ) : ( $page_settings['email_rules'] ?? '' );
			}
		?>
		<script type="text/javascript">
			window.eoLpLoginSecurity = {
				emailFilteringActive: <?php echo $email_filtering_active ? 'true' : 'false'; ?>,
				emailRules: <?php echo json_encode( $email_rules ); ?>
			};

			document.addEventListener('DOMContentLoaded', function() {
				var form = document.getElementById('eo-loginform') || document.getElementById('eo-registerform');
				if (!form) return;

				var usernameInput = document.getElementById('user_login') || document.getElementById('user_email');
				if (!usernameInput) return;

				var style = document.createElement('style');
				style.innerHTML = `
					@keyframes eoShake {
						0%, 100% { transform: translateX(0); }
						20%, 60% { transform: translateX(-8px); }
						40%, 80% { transform: translateX(8px); }
					}
					.eo-shake {
						animation: eoShake 0.4s ease-in-out;
					}
				`;
				document.head.appendChild(style);

				function ruleToRegexJS(rule) {
					if (rule.indexOf('!') === 0) {
						rule = rule.substring(1);
					}
					rule = rule.trim();

					var escaped = rule.replace(/[-\/\\^$*+?.()|[\]{}]/g, function(match) {
						if (match === '*') return '*';
						return '\\' + match;
					});

					if (rule.indexOf('*') !== -1) {
						var pattern = escaped.replace(/\*/g, '.*');
						return new RegExp('^' + pattern + '$', 'i');
					}

					if (rule.indexOf('@') === 0) {
						var domainRule = rule.substring(1);
						var escapedDomain = domainRule.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
						if (domainRule.indexOf('.') !== -1) {
							return new RegExp('@' + escapedDomain + '$', 'i');
						} else {
							return new RegExp('@' + escapedDomain + '(\\..+)?$', 'i');
						}
					}

					var escapedExact = rule.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
					return new RegExp('^' + escapedExact + '$', 'i');
				}

				function isEmailAllowedJS(email, rulesStr) {
					if (!rulesStr) return true;
					email = email.trim().toLowerCase();
					if (email.indexOf('@') === -1) {
						return false;
					}
					var rules = rulesStr.split(',').map(function(r) { return r.trim(); }).filter(Boolean);
					
					var hasAllowRules = false;
					var emailAllowed = false;

					for (var i = 0; i < rules.length; i++) {
						var rule = rules[i];
						var isBlockRule = (rule.indexOf('!') === 0);
						if (!isBlockRule) {
							hasAllowRules = true;
						}

						var regex = ruleToRegexJS(rule);
						if (regex.test(email)) {
							if (isBlockRule) {
								return false;
							} else {
								emailAllowed = true;
							}
						}
					}

					if (hasAllowRules) {
						return emailAllowed;
					}

					return true;
				}

				form.addEventListener('submit', function(e) {
					if (!window.eoLpLoginSecurity || !window.eoLpLoginSecurity.emailFilteringActive) {
						return;
					}

					var username = usernameInput.value.trim();
					if (!username) return;

					if (!isEmailAllowedJS(username, window.eoLpLoginSecurity.emailRules)) {
						e.preventDefault();

						var errorDiv = document.querySelector('.eo-login-error');
						if (!errorDiv) {
							errorDiv = document.createElement('div');
							errorDiv.className = 'eo-login-error';
							form.parentNode.insertBefore(errorDiv, form);
						}
						errorDiv.innerHTML = '<span class="dashicons dashicons-warning" style="vertical-align: middle; margin-right: 4px;"></span> ' + 
							<?php echo json_encode( __( 'Cette adresse e-mail n\'est pas autorisée à se connecter sur ce site.', 'eo-tools' ) ); ?>;
						
						var container = document.querySelector('.eo-lp-box');
						container.classList.remove('eo-shake');
						void container.offsetWidth;
						container.classList.add('eo-shake');
					}
				});
			});
		</script>
	<?php endif; ?>

</body>
</html>
