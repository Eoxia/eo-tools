<?php
/**
 * Dynamic template for the public landing pages (Coming Soon, Maintenance, 404).
 *
 * Expects $page_settings to be defined by the caller.
 *
 * @package EoTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'eo_lp_format_description' ) ) {
	/**
	 * Lightweight Markdown-ish formatter for the description field.
	 *
	 * @param string $text Raw description.
	 * @return string Safe HTML.
	 */
	function eo_lp_format_description( $text ) {
		$text = wp_kses_post( $text );

		// Headings (###, ##, #).
		$text = preg_replace( '/^\s*###\s+(.+)$/m', '<h3>$1</h3>', $text );
		$text = preg_replace( '/^\s*##\s+(.+)$/m', '<h2>$1</h2>', $text );
		$text = preg_replace( '/^\s*#\s+(.+)$/m', '<h1>$1</h1>', $text );

		// Bold and italic.
		$text = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/\*(.*?)\*/', '<em>$1</em>', $text );

		// Bullet lists.
		$lines           = explode( "\n", $text );
		$in_list         = false;
		$formatted_lines = array();

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( preg_match( '/^[-*]\s+(.+)$/', $trimmed, $matches ) ) {
				if ( ! $in_list ) {
					$formatted_lines[] = '<ul>';
					$in_list           = true;
				}
				$formatted_lines[] = '<li>' . $matches[1] . '</li>';
			} else {
				if ( $in_list ) {
					$formatted_lines[] = '</ul>';
					$in_list           = false;
				}
				$formatted_lines[] = $line;
			}
		}
		if ( $in_list ) {
			$formatted_lines[] = '</ul>';
		}
		$text = implode( "\n", $formatted_lines );

		// Paragraphs.
		$parts = explode( "\n\n", $text );
		foreach ( $parts as &$part ) {
			$trimmed_part = trim( $part );
			if ( '' === $trimmed_part ) {
				continue;
			}
			if ( ! preg_match( '/^<(h1|h2|h3|ul|li)/i', $trimmed_part ) ) {
				$part = '<p>' . nl2br( $trimmed_part ) . '</p>';
			}
		}
		unset( $part );

		return implode( "\n", $parts );
	}
}

$title        = $page_settings['title'] ?? '';
$description  = $page_settings['description'] ?? '';
$style        = $page_settings['style'] ?? 'minimalist';
$bg_color     = $page_settings['bg_color'] ? $page_settings['bg_color'] : '#0f172a';
$text_color   = $page_settings['text_color'] ? $page_settings['text_color'] : '#f8fafc';
$accent_color = $page_settings['accent_color'] ? $page_settings['accent_color'] : '#3b82f6';

// Determine whether the background is light or dark for proper contrast.
$is_light_bg = ( hexdec( substr( $bg_color, 1, 2 ) ) + hexdec( substr( $bg_color, 3, 2 ) ) + hexdec( substr( $bg_color, 5, 2 ) ) ) > 380;
$card_bg     = $is_light_bg ? 'rgba(255, 255, 255, 0.85)' : 'rgba(15, 23, 42, 0.65)';
$card_border = $is_light_bg ? 'rgba(0, 0, 0, 0.08)' : 'rgba(255, 255, 255, 0.08)';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $title ); ?></title>
	<style>
		* {
			box-sizing: border-box;
			margin: 0;
			padding: 0;
		}

		body {
			font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
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

		h1 {
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

		.eo-lp-container {
			width: 100%;
			max-width: 650px;
			padding: 2.5rem;
			text-align: center;
			z-index: 10;
			position: relative;
		}

		<?php if ( 'minimalist' === $style ) : ?>
		.eo-lp-box {
			background: <?php echo esc_html( $card_bg ); ?>;
			border: 1px solid <?php echo esc_html( $card_border ); ?>;
			border-radius: 16px;
			padding: 3.5rem 2.5rem;
			box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
		}
		<?php endif; ?>

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
			<div class="description"><?php echo wp_kses_post( eo_lp_format_description( $description ) ); ?></div>

			<?php if ( '404' === $type ) : ?>
				<a href="<?php echo esc_url( home_url() ); ?>" class="eo-lp-btn">
					<?php esc_html_e( 'Retour à l\'accueil', 'eo-tools' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

</body>
</html>
