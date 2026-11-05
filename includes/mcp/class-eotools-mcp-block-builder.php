<?php
/**
 * Builds valid block markup from the simplified block tree sent by the AI.
 *
 * The block editor flags a block as invalid when its saved HTML differs from what
 * its JavaScript save() function produces. The AI therefore only sends block
 * names and attributes; this class generates the exact HTML:
 * - common core blocks have a dedicated builder (paragraph, heading, list…),
 * - dynamic blocks (all EO Blocks, latest posts…) only need their comment and inner blocks,
 * - other blocks require the "html" the AI provides (e.g. copied from eo-tools/get-post).
 *
 * The markup of every builder is checked against the real editor in the tests.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Block_Builder {

	/**
	 * Attributes handled by every builder that renders block supports on its wrapper.
	 */
	const WRAPPER_ATTRIBUTES = array( 'className', 'anchor', 'align', 'textColor', 'backgroundColor', 'gradient', 'fontSize', 'style' );

	/**
	 * Core blocks fully rendered by the server: only their comment is saved.
	 *
	 * Checked against the block editor of WordPress 7.1. Having a render callback is not enough:
	 * core/cover, core/gallery or core/heading are rendered by the server but also save HTML.
	 * Core blocks missing from this list and from the builders require their HTML.
	 */
	const SERVER_RENDERED_BLOCKS = array(
		'core/archives', 'core/calendar', 'core/categories', 'core/latest-comments', 'core/latest-posts', 'core/page-list',
		'core/page-list-item', 'core/pattern', 'core/playlist-track', 'core/block', 'core/rss', 'core/search', 'core/social-link',
		'core/tag-cloud', 'core/footnotes', 'core/navigation', 'core/navigation-link', 'core/navigation-submenu', 'core/site-logo',
		'core/site-title', 'core/site-tagline', 'core/template-part', 'core/avatar', 'core/post-title', 'core/post-excerpt',
		'core/post-featured-image', 'core/post-content', 'core/post-author', 'core/post-author-name', 'core/post-comments-count',
		'core/post-comments-link', 'core/post-date', 'core/post-terms', 'core/post-navigation-link', 'core/post-template',
		'core/post-time-to-read', 'core/query-pagination', 'core/query-pagination-next', 'core/query-pagination-numbers',
		'core/query-pagination-previous', 'core/query-no-results', 'core/query-total', 'core/read-more', 'core/comment-author-name',
		'core/comment-content', 'core/comment-date', 'core/comment-edit-link', 'core/comment-reply-link', 'core/comment-template',
		'core/comments-title', 'core/comments-pagination', 'core/comments-pagination-next', 'core/comments-pagination-numbers',
		'core/comments-pagination-previous', 'core/post-comments-form', 'core/home-link', 'core/icon', 'core/loginout',
		'core/navigation-overlay-close', 'core/term-count', 'core/term-description', 'core/term-name', 'core/term-template',
		'core/query-title', 'core/post-author-biography', 'core/breadcrumbs',
	);

	/**
	 * @var string[] Non-blocking remarks for the AI (dropped attributes…).
	 */
	private $warnings = array();

	/**
	 * Attributes supported by each builder, besides the wrapper ones when "wrapper" is true.
	 *
	 * @return array[]
	 */
	public static function get_builders() {
		return array(
			'core/paragraph' => array(
				'attributes' => array( 'content', 'dropCap' ),
				'wrapper'    => true,
			),
			'core/heading'   => array(
				'attributes' => array( 'content', 'level' ),
				'wrapper'    => true,
			),
			'core/list'      => array(
				'attributes' => array( 'ordered', 'start', 'reversed', 'type' ),
				'wrapper'    => true,
			),
			'core/list-item' => array(
				'attributes' => array( 'content' ),
				'wrapper'    => true,
			),
			'core/quote'     => array(
				'attributes' => array( 'citation' ),
				'wrapper'    => true,
			),
			'core/image'     => array(
				'attributes' => array( 'id', 'url', 'alt', 'caption', 'sizeSlug', 'href', 'linkTarget', 'linkDestination', 'align', 'className', 'anchor' ),
				'wrapper'    => false,
			),
			'core/buttons'   => array(
				'attributes' => array( 'layout' ),
				'wrapper'    => true,
			),
			'core/button'    => array(
				'attributes' => array( 'text', 'url', 'linkTarget', 'rel', 'title', 'className', 'anchor', 'textColor', 'backgroundColor', 'gradient', 'fontSize', 'style' ),
				'wrapper'    => false,
			),
			'core/separator' => array(
				'attributes' => array( 'className', 'anchor', 'align' ),
				'wrapper'    => false,
			),
			'core/spacer'    => array(
				'attributes' => array( 'height', 'anchor' ),
				'wrapper'    => false,
			),
			'core/columns'   => array(
				'attributes' => array( 'verticalAlignment', 'isStackedOnMobile' ),
				'wrapper'    => true,
			),
			'core/column'    => array(
				'attributes' => array( 'verticalAlignment', 'width' ),
				'wrapper'    => true,
			),
			'core/group'     => array(
				'attributes' => array( 'tagName', 'layout' ),
				'wrapper'    => true,
			),
			'core/details'   => array(
				'attributes' => array( 'summary', 'showContent' ),
				'wrapper'    => true,
			),
			'core/code'      => array(
				'attributes' => array( 'content', 'className', 'anchor' ),
				'wrapper'    => false,
			),
			'core/embed'     => array(
				'attributes' => array( 'url', 'caption', 'className' ),
				'wrapper'    => false,
			),
			'core/table'     => array(
				'attributes' => array( 'head', 'body', 'caption', 'hasFixedLayout', 'className', 'anchor' ),
				'wrapper'    => false,
			),
		);
	}

	/**
	 * Builds serialized block markup from a block tree.
	 *
	 * @param array[] $nodes Block tree: { name, attributes, inner_blocks, html }.
	 * @return string|\WP_Error Serialized blocks.
	 */
	public function to_markup( $nodes ) {
		$blocks = $this->build_nodes( $nodes, null, '' );

		return is_wp_error( $blocks ) ? $blocks : serialize_blocks( $blocks );
	}

	/**
	 * Builds parsed blocks (same structure as parse_blocks()) from a block tree.
	 *
	 * @param array[]     $nodes  Block tree.
	 * @param string|null $parent Parent block name.
	 * @param string      $path   Path of the parent, for error messages.
	 * @return array[]|\WP_Error
	 */
	public function build_nodes( $nodes, $parent = null, $path = '' ) {
		if ( ! is_array( $nodes ) ) {
			return new \WP_Error( 'eo_tools_invalid_blocks', __( 'La liste de blocs est invalide.', 'eo-tools' ) );
		}

		$blocks = array();
		foreach ( array_values( $nodes ) as $index => $node ) {
			$block = $this->build_block( $node, $parent, '' === $path ? (string) $index : $path . '.' . $index );
			if ( is_wp_error( $block ) ) {
				return $block;
			}
			$blocks[] = $block;
		}

		return $blocks;
	}

	/**
	 * @return string[]
	 */
	public function get_warnings() {
		return array_values( array_unique( $this->warnings ) );
	}

	/**
	 * @param array       $node   Block node.
	 * @param string|null $parent Parent block name.
	 * @param string      $path   Path of the block in the tree.
	 * @return array|\WP_Error Parsed block.
	 */
	private function build_block( $node, $parent, $path ) {
		$name  = is_array( $node ) && isset( $node['name'] ) && is_string( $node['name'] ) ? $node['name'] : '';
		$types = Eotools_Mcp_Blocks::get_block_types();

		if ( ! isset( $types[ $name ] ) ) {
			/* translators: 1: block path, 2: block name. */
			return new \WP_Error( 'eo_tools_unknown_block', sprintf( __( 'Bloc %1$s : « %2$s » est inconnu ou non disponible. Utilisez eo-tools/list-blocks.', 'eo-tools' ), $path, $name ) );
		}

		$type = $types[ $name ];

		if ( ! empty( $type->parent ) && ! in_array( $parent, $type->parent, true ) ) {
			/* translators: 1: block path, 2: block name, 3: allowed parents. */
			return new \WP_Error( 'eo_tools_block_parent', sprintf( __( 'Bloc %1$s : « %2$s » doit être placé dans %3$s.', 'eo-tools' ), $path, $name, implode( ', ', $type->parent ) ) );
		}

		$attributes = isset( $node['attributes'] ) && is_array( $node['attributes'] ) ? $node['attributes'] : array();
		$inner      = array();

		if ( ! empty( $node['inner_blocks'] ) ) {
			$inner = $this->build_nodes( $node['inner_blocks'], $name, $path );
			if ( is_wp_error( $inner ) ) {
				return $inner;
			}

			if ( ! empty( $type->allowed_blocks ) ) {
				foreach ( $inner as $index => $child ) {
					if ( ! in_array( $child['blockName'], $type->allowed_blocks, true ) ) {
						/* translators: 1: block path, 2: block name, 3: allowed blocks. */
						return new \WP_Error( 'eo_tools_block_children', sprintf( __( 'Bloc %1$s : « %2$s » n\'accepte que %3$s.', 'eo-tools' ), $path . '.' . $index, $name, implode( ', ', $type->allowed_blocks ) ) );
					}
				}
			}
		}

		$builders = self::get_builders();

		// The AI sent the HTML itself (typically an untouched block copied from get-post).
		if ( isset( $node['html'] ) && is_string( $node['html'] ) && empty( $inner ) ) {
			return $this->block( $name, $this->comment_attributes( $type, $attributes ), $node['html'] );
		}

		if ( isset( $builders[ $name ] ) ) {
			$allowed = $builders[ $name ]['attributes'];
			if ( $builders[ $name ]['wrapper'] ) {
				$allowed = array_merge( $allowed, self::WRAPPER_ATTRIBUTES );
			}

			$dropped = array_diff( array_keys( $attributes ), $allowed );
			if ( $dropped ) {
				/* translators: 1: block path, 2: block name, 3: attribute names. */
				$this->warnings[] = sprintf( __( 'Bloc %1$s (%2$s) : attributs non pris en charge ignorés : %3$s.', 'eo-tools' ), $path, $name, implode( ', ', $dropped ) );
				$attributes = array_intersect_key( $attributes, array_flip( $allowed ) );
			}

			$method = 'build_' . str_replace( '-', '_', substr( $name, 5 ) );

			return $this->$method( $type, $attributes, $inner );
		}

		// Blocks rendered by the server (all EO Blocks): only their comment and inner blocks are saved.
		if ( 0 === strpos( $name, 'eo-blocks/' ) || in_array( $name, self::SERVER_RENDERED_BLOCKS, true ) ) {
			return $this->container( $name, $this->comment_attributes( $type, $attributes ), '', '', $inner );
		}

		/* translators: 1: block path, 2: block name. */
		return new \WP_Error( 'eo_tools_block_needs_html', sprintf( __( 'Bloc %1$s : « %2$s » nécessite son HTML (champ "html"). Copiez-le depuis eo-tools/get-post ou utilisez un bloc plus simple.', 'eo-tools' ), $path, $name ) );
	}

	/* ---------------------------------------------------------------------
	 * Parsed block helpers
	 * ------------------------------------------------------------------ */

	/**
	 * A block without inner blocks.
	 */
	private function block( $name, $attributes, $html ) {
		return array(
			'blockName'    => $name,
			'attrs'        => $attributes,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => '' === $html ? array() : array( $html ),
		);
	}

	/**
	 * A block whose inner blocks sit between an opening and a closing HTML fragment.
	 */
	private function container( $name, $attributes, $open, $close, $inner ) {
		$content = array();
		if ( '' !== $open ) {
			$content[] = $open;
		}
		foreach ( $inner as $index => $child ) {
			$content[] = null;
		}
		if ( '' !== $close ) {
			$content[] = $close;
		}

		return array(
			'blockName'    => $name,
			'attrs'        => $attributes,
			'innerBlocks'  => $inner,
			'innerHTML'    => $open . $close,
			'innerContent' => $content,
		);
	}

	/**
	 * Removes the attributes stored in the HTML (rich text, URLs…): they must not be in the block comment.
	 *
	 * @param \WP_Block_Type $type       Block type.
	 * @param array          $attributes Attributes.
	 * @return array
	 */
	private function comment_attributes( $type, $attributes ) {
		foreach ( (array) $type->attributes as $key => $definition ) {
			if ( isset( $definition['source'] ) ) {
				unset( $attributes[ $key ] );
			}
		}

		return $attributes;
	}

	/* ---------------------------------------------------------------------
	 * Block supports on the wrapper (classes, inline styles, anchor)
	 * ------------------------------------------------------------------ */

	/**
	 * Computes the classes, styles and id produced by block supports.
	 *
	 * @param array    $attributes Attributes.
	 * @param string[] $classes    Base classes of the block.
	 * @return array { class: string[], style: string, id: string }
	 */
	private function supports( $attributes, $classes = array() ) {
		$style = isset( $attributes['style'] ) && is_array( $attributes['style'] ) ? $attributes['style'] : array();

		if ( ! empty( $attributes['className'] ) ) {
			$classes = array_merge( $classes, preg_split( '/\s+/', trim( $attributes['className'] ) ) );
		}
		if ( ! empty( $attributes['align'] ) ) {
			$classes[] = 'align' . $attributes['align'];
		}
		if ( ! empty( $attributes['textColor'] ) ) {
			$classes[] = 'has-' . $attributes['textColor'] . '-color';
		}
		if ( ! empty( $attributes['textColor'] ) || ! empty( $style['color']['text'] ) ) {
			$classes[] = 'has-text-color';
		}
		if ( ! empty( $attributes['backgroundColor'] ) ) {
			$classes[] = 'has-' . $attributes['backgroundColor'] . '-background-color';
		}
		if ( ! empty( $attributes['gradient'] ) ) {
			$classes[] = 'has-' . $attributes['gradient'] . '-gradient-background';
		}
		if ( ! empty( $attributes['backgroundColor'] ) || ! empty( $attributes['gradient'] ) || ! empty( $style['color']['background'] ) || ! empty( $style['color']['gradient'] ) ) {
			$classes[] = 'has-background';
		}
		if ( ! empty( $style['elements']['link']['color']['text'] ) ) {
			$classes[] = 'has-link-color';
		}
		if ( ! empty( $attributes['fontSize'] ) ) {
			$classes[] = 'has-' . $attributes['fontSize'] . '-font-size';
		}
		if ( ! empty( $style['typography']['textAlign'] ) ) {
			$classes[] = 'has-text-align-' . $style['typography']['textAlign'];
		}

		// Text alignment and element styles are not inline styles.
		unset( $style['typography']['textAlign'], $style['elements'] );

		$css = '';
		if ( $style && function_exists( 'wp_style_engine_get_styles' ) ) {
			$styles = wp_style_engine_get_styles( $style );
			$css    = isset( $styles['css'] ) ? $styles['css'] : '';
			if ( ! empty( $styles['classnames'] ) ) {
				$classes = array_merge( $classes, explode( ' ', $styles['classnames'] ) );
			}
		}

		return array(
			'class' => array_values( array_unique( array_filter( $classes ) ) ),
			'style' => $css,
			'id'    => isset( $attributes['anchor'] ) ? (string) $attributes['anchor'] : '',
		);
	}

	/**
	 * Renders HTML attributes. Empty values are skipped, except the ones listed in $keep_empty.
	 *
	 * @param array    $attributes  Attribute values keyed by name.
	 * @param string[] $keep_empty  Attributes rendered even when empty (e.g. alt).
	 * @return string
	 */
	private function html_attributes( $attributes, $keep_empty = array() ) {
		$html = '';
		foreach ( $attributes as $name => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ' ', $value );
			}
			if ( ( null === $value || '' === $value || false === $value ) && ! in_array( $name, $keep_empty, true ) ) {
				continue;
			}
			$html .= true === $value ? ' ' . $name : ' ' . $name . '="' . esc_attr( $value ) . '"';
		}

		return $html;
	}

	/**
	 * Wrapper attributes from block supports, plus extra attributes.
	 */
	private function wrapper( $attributes, $classes, $extra = array() ) {
		$supports = $this->supports( $attributes, $classes );

		return $this->html_attributes(
			array_merge(
				array(
					'class' => $supports['class'],
					'style' => $supports['style'],
					'id'    => $supports['id'],
				),
				$extra
			)
		);
	}

	private function rich_text( $attributes, $key ) {
		return isset( $attributes[ $key ] ) ? (string) $attributes[ $key ] : '';
	}

	/* ---------------------------------------------------------------------
	 * Builders
	 * ------------------------------------------------------------------ */

	private function build_paragraph( $type, $attributes, $inner ) {
		$classes = ! empty( $attributes['dropCap'] ) ? array( 'has-drop-cap' ) : array();
		$html    = '<p' . $this->wrapper( $attributes, $classes ) . '>' . $this->rich_text( $attributes, 'content' ) . '</p>';

		return $this->block( 'core/paragraph', $this->comment_attributes( $type, $attributes ), $html );
	}

	private function build_heading( $type, $attributes, $inner ) {
		$level = isset( $attributes['level'] ) ? max( 1, min( 6, (int) $attributes['level'] ) ) : 2;
		if ( 2 === $level ) {
			unset( $attributes['level'] );
		} else {
			$attributes['level'] = $level;
		}

		$html = '<h' . $level . $this->wrapper( $attributes, array( 'wp-block-heading' ) ) . '>' . $this->rich_text( $attributes, 'content' ) . '</h' . $level . '>';

		return $this->block( 'core/heading', $this->comment_attributes( $type, $attributes ), $html );
	}

	private function build_list( $type, $attributes, $inner ) {
		$tag  = ! empty( $attributes['ordered'] ) ? 'ol' : 'ul';
		$open = '<' . $tag . $this->wrapper(
			$attributes,
			array( 'wp-block-list' ),
			array(
				'reversed' => 'ol' === $tag && ! empty( $attributes['reversed'] ),
				'start'    => 'ol' === $tag && isset( $attributes['start'] ) ? (string) (int) $attributes['start'] : '',
				'type'     => 'ol' === $tag && isset( $attributes['type'] ) ? (string) $attributes['type'] : '',
			)
		) . '>';

		return $this->container( 'core/list', $this->comment_attributes( $type, $attributes ), $open, '</' . $tag . '>', $inner );
	}

	private function build_list_item( $type, $attributes, $inner ) {
		$open = '<li' . $this->wrapper( $attributes, array() ) . '>' . $this->rich_text( $attributes, 'content' );

		return $this->container( 'core/list-item', $this->comment_attributes( $type, $attributes ), $open, '</li>', $inner );
	}

	private function build_quote( $type, $attributes, $inner ) {
		$citation = $this->rich_text( $attributes, 'citation' );
		$close    = ( '' !== $citation ? '<cite>' . $citation . '</cite>' : '' ) . '</blockquote>';

		return $this->container( 'core/quote', $this->comment_attributes( $type, $attributes ), '<blockquote' . $this->wrapper( $attributes, array( 'wp-block-quote' ) ) . '>', $close, $inner );
	}

	private function build_image( $type, $attributes, $inner ) {
		$classes = array( 'wp-block-image' );
		if ( ! empty( $attributes['align'] ) ) {
			$classes[] = 'align' . $attributes['align'];
		}
		if ( ! empty( $attributes['sizeSlug'] ) ) {
			$classes[] = 'size-' . $attributes['sizeSlug'];
		}
		if ( ! empty( $attributes['className'] ) ) {
			$classes[] = $attributes['className'];
		}

		$image = '<img' . $this->html_attributes(
			array(
				'src'   => isset( $attributes['url'] ) ? $attributes['url'] : '',
				'alt'   => isset( $attributes['alt'] ) ? $attributes['alt'] : '',
				'class' => ! empty( $attributes['id'] ) ? 'wp-image-' . (int) $attributes['id'] : '',
			),
			array( 'alt' )
		) . '/>';

		if ( ! empty( $attributes['href'] ) ) {
			$image = '<a' . $this->html_attributes(
				array(
					'href'   => $attributes['href'],
					'target' => isset( $attributes['linkTarget'] ) ? $attributes['linkTarget'] : '',
				)
			) . '>' . $image . '</a>';
		}

		$caption = $this->rich_text( $attributes, 'caption' );
		$html    = '<figure' . $this->html_attributes(
			array(
				'class' => $classes,
				'id'    => isset( $attributes['anchor'] ) ? $attributes['anchor'] : '',
			)
		) . '>' . $image . ( '' !== $caption ? '<figcaption class="wp-element-caption">' . $caption . '</figcaption>' : '' ) . '</figure>';

		return $this->block( 'core/image', $this->comment_attributes( $type, $attributes ), $html );
	}

	private function build_buttons( $type, $attributes, $inner ) {
		return $this->container( 'core/buttons', $this->comment_attributes( $type, $attributes ), '<div' . $this->wrapper( $attributes, array( 'wp-block-buttons' ) ) . '>', '</div>', $inner );
	}

	private function build_button( $type, $attributes, $inner ) {
		$wrapper = array( 'wp-block-button' );
		if ( ! empty( $attributes['className'] ) ) {
			$wrapper[] = $attributes['className'];
		}

		// Colors, typography and spacing apply to the link, not to the wrapper.
		$link_supports = $this->supports( array_diff_key( $attributes, array_flip( array( 'className', 'anchor' ) ) ), array( 'wp-block-button__link', 'wp-element-button' ) );

		$link = '<a' . $this->html_attributes(
			array(
				'class'  => $link_supports['class'],
				'href'   => isset( $attributes['url'] ) ? $attributes['url'] : '',
				'title'  => isset( $attributes['title'] ) ? $attributes['title'] : '',
				'style'  => $link_supports['style'],
				'target' => isset( $attributes['linkTarget'] ) ? $attributes['linkTarget'] : '',
				'rel'    => isset( $attributes['rel'] ) ? $attributes['rel'] : '',
			)
		) . '>' . $this->rich_text( $attributes, 'text' ) . '</a>';

		$html = '<div' . $this->html_attributes(
			array(
				'class' => $wrapper,
				'id'    => isset( $attributes['anchor'] ) ? $attributes['anchor'] : '',
			)
		) . '>' . $link . '</div>';

		return $this->block( 'core/button', $this->comment_attributes( $type, $attributes ), $html );
	}

	private function build_separator( $type, $attributes, $inner ) {
		$classes = array( 'wp-block-separator', 'has-alpha-channel-opacity' );
		if ( ! empty( $attributes['align'] ) ) {
			$classes[] = 'align' . $attributes['align'];
		}
		if ( ! empty( $attributes['className'] ) ) {
			$classes[] = $attributes['className'];
		}

		$html = '<hr' . $this->html_attributes(
			array(
				'class' => $classes,
				'id'    => isset( $attributes['anchor'] ) ? $attributes['anchor'] : '',
			)
		) . '/>';

		return $this->block( 'core/separator', $this->comment_attributes( $type, $attributes ), $html );
	}

	private function build_spacer( $type, $attributes, $inner ) {
		$height = isset( $attributes['height'] ) ? (string) $attributes['height'] : '100px';
		if ( is_numeric( $height ) ) {
			$height .= 'px';
		}
		if ( '100px' === $height ) {
			unset( $attributes['height'] );
		} else {
			$attributes['height'] = $height;
		}

		$html = '<div' . $this->html_attributes(
			array(
				'style'       => 'height:' . $height,
				'aria-hidden' => 'true',
				'class'       => 'wp-block-spacer',
				'id'          => isset( $attributes['anchor'] ) ? $attributes['anchor'] : '',
			)
		) . '></div>';

		return $this->block( 'core/spacer', $this->comment_attributes( $type, $attributes ), $html );
	}

	private function build_columns( $type, $attributes, $inner ) {
		$classes = array( 'wp-block-columns' );
		if ( ! empty( $attributes['verticalAlignment'] ) ) {
			$classes[] = 'are-vertically-aligned-' . $attributes['verticalAlignment'];
		}
		if ( isset( $attributes['isStackedOnMobile'] ) && false === $attributes['isStackedOnMobile'] ) {
			$classes[] = 'is-not-stacked-on-mobile';
		}

		return $this->container( 'core/columns', $this->comment_attributes( $type, $attributes ), '<div' . $this->wrapper( $attributes, $classes ) . '>', '</div>', $inner );
	}

	private function build_column( $type, $attributes, $inner ) {
		$classes = array( 'wp-block-column' );
		if ( ! empty( $attributes['verticalAlignment'] ) ) {
			$classes[] = 'is-vertically-aligned-' . $attributes['verticalAlignment'];
		}

		$supports = $this->supports( $attributes, $classes );
		$style    = $supports['style'];
		if ( ! empty( $attributes['width'] ) ) {
			$style = 'flex-basis:' . $attributes['width'] . ( '' !== $style ? ';' . $style : '' );
		}

		$open = '<div' . $this->html_attributes(
			array(
				'class' => $supports['class'],
				'style' => $style,
				'id'    => $supports['id'],
			)
		) . '>';

		return $this->container( 'core/column', $this->comment_attributes( $type, $attributes ), $open, '</div>', $inner );
	}

	private function build_group( $type, $attributes, $inner ) {
		$tag = isset( $attributes['tagName'] ) && in_array( $attributes['tagName'], array( 'div', 'header', 'main', 'section', 'article', 'aside', 'footer' ), true ) ? $attributes['tagName'] : 'div';
		if ( 'div' === $tag ) {
			unset( $attributes['tagName'] );
		}

		return $this->container( 'core/group', $this->comment_attributes( $type, $attributes ), '<' . $tag . $this->wrapper( $attributes, array( 'wp-block-group' ) ) . '>', '</' . $tag . '>', $inner );
	}

	private function build_details( $type, $attributes, $inner ) {
		$open = '<details' . $this->wrapper( $attributes, array( 'wp-block-details' ), array( 'open' => ! empty( $attributes['showContent'] ) ) ) . '><summary>' . $this->rich_text( $attributes, 'summary' ) . '</summary>';

		return $this->container( 'core/details', $this->comment_attributes( $type, $attributes ), $open, '</details>', $inner );
	}

	private function build_code( $type, $attributes, $inner ) {
		$classes = array( 'wp-block-code' );
		if ( ! empty( $attributes['className'] ) ) {
			$classes[] = $attributes['className'];
		}

		$html = '<pre' . $this->html_attributes(
			array(
				'class' => $classes,
				'id'    => isset( $attributes['anchor'] ) ? $attributes['anchor'] : '',
			)
		) . '><code>' . esc_html( $this->rich_text( $attributes, 'content' ) ) . '</code></pre>';

		return $this->block( 'core/code', $this->comment_attributes( $type, $attributes ), $html );
	}

	private function build_embed( $type, $attributes, $inner ) {
		$url = isset( $attributes['url'] ) ? (string) $attributes['url'] : '';
		if ( '' === $url ) {
			return new \WP_Error( 'eo_tools_embed_url', __( 'Le bloc core/embed nécessite une adresse "url".', 'eo-tools' ) );
		}

		$classes = array( 'wp-block-embed' );
		if ( ! empty( $attributes['className'] ) ) {
			$classes[] = $attributes['className'];
		}

		$caption = $this->rich_text( $attributes, 'caption' );
		$html    = '<figure' . $this->html_attributes( array( 'class' => $classes ) ) . '><div class="wp-block-embed__wrapper">' . "\n" . esc_url( $url ) . "\n" . '</div>' . ( '' !== $caption ? '<figcaption class="wp-element-caption">' . $caption . '</figcaption>' : '' ) . '</figure>';

		// The URL is stored in the comment: the editor fetches the provider details itself.
		$comment = $this->comment_attributes( $type, $attributes );
		$comment = array( 'url' => $url ) + $comment;

		return $this->block( 'core/embed', $comment, $html );
	}

	/**
	 * Tables accept a simple format: "head" and "body" are arrays of rows, each row an array of cell contents.
	 */
	private function build_table( $type, $attributes, $inner ) {
		$section = function ( $tag, $cell_tag, $rows ) {
			if ( empty( $rows ) || ! is_array( $rows ) ) {
				return '';
			}

			$html = '<' . $tag . '>';
			foreach ( $rows as $row ) {
				$html .= '<tr>';
				foreach ( (array) $row as $cell ) {
					$html .= '<' . $cell_tag . '>' . ( is_array( $cell ) ? ( isset( $cell['content'] ) ? $cell['content'] : '' ) : $cell ) . '</' . $cell_tag . '>';
				}
				$html .= '</tr>';
			}

			return $html . '</' . $tag . '>';
		};

		$classes = array( 'wp-block-table' );
		if ( ! empty( $attributes['className'] ) ) {
			$classes[] = $attributes['className'];
		}

		$caption = $this->rich_text( $attributes, 'caption' );
		$html    = '<figure' . $this->html_attributes(
			array(
				'class' => $classes,
				'id'    => isset( $attributes['anchor'] ) ? $attributes['anchor'] : '',
			)
		) . '><table' . $this->html_attributes( array( 'class' => ! empty( $attributes['hasFixedLayout'] ) ? 'has-fixed-layout' : '' ) ) . '>'
			. $section( 'thead', 'th', isset( $attributes['head'] ) ? $attributes['head'] : array() )
			. $section( 'tbody', 'td', isset( $attributes['body'] ) ? $attributes['body'] : array() )
			. '</table>' . ( '' !== $caption ? '<figcaption class="wp-element-caption">' . $caption . '</figcaption>' : '' ) . '</figure>';

		unset( $attributes['head'], $attributes['body'] );

		return $this->block( 'core/table', $this->comment_attributes( $type, $attributes ), $html );
	}
}
