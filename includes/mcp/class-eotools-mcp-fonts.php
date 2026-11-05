<?php
/**
 * Fonts: search the Google Fonts collection of WordPress and install fonts on the site.
 *
 * Installed fonts go into the WordPress font library (Site editor > Styles >
 * Typography): the font files are downloaded into wp-content/uploads/fonts and
 * served by the site, never loaded from Google (GDPR). Installing a font does
 * not change the site: the AI then proposes styles that use it.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Fonts {

	const COLLECTION = 'google-fonts';

	/**
	 * Font families of the Google Fonts collection.
	 *
	 * @return array[]|\WP_Error
	 */
	private static function get_collection() {
		if ( ! class_exists( 'WP_Font_Library' ) ) {
			return new \WP_Error( 'eo_tools_fonts_unsupported', __( 'La bibliothèque de polices n\'est pas disponible sur ce site.', 'eo-tools' ) );
		}

		$collection = \WP_Font_Library::get_instance()->get_font_collection( self::COLLECTION );
		if ( ! $collection ) {
			return new \WP_Error( 'eo_tools_fonts_collection', __( 'La collection Google Fonts n\'est pas disponible sur ce site.', 'eo-tools' ) );
		}

		$data = $collection->get_data();
		if ( is_wp_error( $data ) ) {
			return new \WP_Error( 'eo_tools_fonts_collection', __( 'Impossible de charger la collection Google Fonts. Réessayez plus tard.', 'eo-tools' ) );
		}

		return $data['font_families'];
	}

	/**
	 * Searches the Google Fonts collection.
	 *
	 * @param array $input Query and category.
	 * @return array|\WP_Error
	 */
	public static function search( $input ) {
		$families = self::get_collection();
		if ( is_wp_error( $families ) ) {
			return $families;
		}

		$query     = isset( $input['query'] ) ? strtolower( trim( (string) $input['query'] ) ) : '';
		$category  = isset( $input['category'] ) ? (string) $input['category'] : '';
		$installed = wp_list_pluck( self::get_installed(), 'slug' );
		$results   = array();

		foreach ( $families as $family ) {
			$settings = $family['font_family_settings'];
			if ( '' !== $query && false === strpos( strtolower( $settings['name'] ), $query ) ) {
				continue;
			}
			if ( '' !== $category && ! in_array( $category, isset( $family['categories'] ) ? (array) $family['categories'] : array(), true ) ) {
				continue;
			}

			$weights = array();
			$italic  = false;
			foreach ( (array) $settings['fontFace'] as $face ) {
				$weights[] = (string) $face['fontWeight'];
				$italic    = $italic || 'italic' === $face['fontStyle'];
			}

			$results[] = array(
				'name'       => $settings['name'],
				'slug'       => $settings['slug'],
				'category'   => isset( $family['categories'][0] ) ? $family['categories'][0] : '',
				'weights'    => array_values( array_unique( $weights ) ),
				'has_italic' => $italic,
				'installed'  => in_array( $settings['slug'], $installed, true ),
			);
			if ( count( $results ) >= 20 ) {
				break;
			}
		}

		return array(
			'total'   => count( $results ),
			'fonts'   => $results,
			'message' => __( 'Les polices les plus connues sont les plus sûres (Inter, Roboto, Open Sans, Montserrat, Lato, Poppins, Playfair Display, Merriweather…). Installez-en au plus deux (titres et texte).', 'eo-tools' ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Installed fonts
	 * ------------------------------------------------------------------ */

	/**
	 * Fonts of the WordPress font library, as theme.json font families.
	 *
	 * @return array[]
	 */
	public static function get_installed() {
		$families = get_posts(
			array(
				'post_type'      => 'wp_font_family',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
			)
		);

		$list = array();
		foreach ( $families as $post ) {
			$settings = json_decode( $post->post_content, true );
			$entry    = array(
				'name'       => $post->post_title,
				'slug'       => $post->post_name,
				'fontFamily' => isset( $settings['fontFamily'] ) ? $settings['fontFamily'] : $post->post_title,
				'fontFace'   => array(),
			);

			$faces = get_posts(
				array(
					'post_type'      => 'wp_font_face',
					'post_parent'    => $post->ID,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);
			foreach ( $faces as $face ) {
				$face_settings = json_decode( $face->post_content, true );
				if ( is_array( $face_settings ) ) {
					$face_settings['src'] = (array) $face_settings['src'];
					$entry['fontFace'][]  = $face_settings;
				}
			}

			$entry['_id'] = $post->ID;
			$list[]       = $entry;
		}

		return $list;
	}

	/**
	 * Short list of the installed fonts, and whether the styles use them.
	 *
	 * @return array[]
	 */
	public static function get_installed_summary() {
		$active = array();
		foreach ( (array) wp_get_global_settings( array( 'typography', 'fontFamilies' ) ) as $items ) {
			$active = array_merge( $active, wp_list_pluck( (array) $items, 'slug' ) );
		}

		$summary = array();
		foreach ( self::get_installed() as $font ) {
			$summary[] = array(
				'slug'      => $font['slug'],
				'name'      => $font['name'],
				'variants'  => array_map(
					function ( $face ) {
						return $face['fontWeight'] . ( 'italic' === $face['fontStyle'] ? ' italic' : '' );
					},
					$font['fontFace']
				),
				'available' => in_array( $font['slug'], $active, true ),
			);
		}

		return $summary;
	}

	/**
	 * Adds installed fonts to a user styles config, so the styles can use them.
	 *
	 * @param array    $config User config.
	 * @param string[] $slugs  Font slugs.
	 * @return array|\WP_Error Config.
	 */
	public static function add_to_config( $config, $slugs ) {
		$installed = array();
		foreach ( self::get_installed() as $font ) {
			unset( $font['_id'] );
			$installed[ $font['slug'] ] = $font;
		}

		$custom = isset( $config['settings']['typography']['fontFamilies']['custom'] ) ? (array) $config['settings']['typography']['fontFamilies']['custom'] : array();
		foreach ( $slugs as $slug ) {
			if ( ! isset( $installed[ $slug ] ) ) {
				/* translators: %s: font slug. */
				return new \WP_Error( 'eo_tools_font_not_installed', sprintf( __( 'La police « %s » n\'est pas installée : installez-la d\'abord avec eo-tools/install-font.', 'eo-tools' ), $slug ) );
			}
			$custom = array_values(
				array_filter(
					$custom,
					function ( $item ) use ( $slug ) {
						return ! isset( $item['slug'] ) || $item['slug'] !== $slug;
					}
				)
			);
			$custom[] = $installed[ $slug ];
		}

		$config['settings']['typography']['fontFamilies']['custom'] = $custom;

		return $config;
	}

	/* ---------------------------------------------------------------------
	 * Install
	 * ------------------------------------------------------------------ */

	/**
	 * Installs a Google font in the font library, with its files hosted on the site.
	 *
	 * @param array $input Family name or slug, weights, italic.
	 * @return array|\WP_Error
	 */
	public static function install( $input ) {
		$families = self::get_collection();
		if ( is_wp_error( $families ) ) {
			return $families;
		}

		$wanted = isset( $input['family'] ) ? strtolower( trim( (string) $input['family'] ) ) : '';
		$family = null;
		foreach ( $families as $item ) {
			if ( strtolower( $item['font_family_settings']['name'] ) === $wanted || $item['font_family_settings']['slug'] === $wanted ) {
				$family = $item['font_family_settings'];
				break;
			}
		}
		if ( ! $family ) {
			return new \WP_Error( 'eo_tools_font_not_found', __( 'Police introuvable dans Google Fonts : cherchez-la d\'abord avec eo-tools/search-fonts.', 'eo-tools' ) );
		}

		$weights = isset( $input['weights'] ) ? array_map( 'strval', (array) $input['weights'] ) : array( '400', '700' );
		$styles  = empty( $input['italic'] ) ? array( 'normal' ) : array( 'normal', 'italic' );
		$faces   = array_values(
			array_filter(
				(array) $family['fontFace'],
				function ( $face ) use ( $weights, $styles ) {
					return in_array( (string) $face['fontWeight'], $weights, true ) && in_array( $face['fontStyle'], $styles, true );
				}
			)
		);
		if ( ! $faces ) {
			return new \WP_Error( 'eo_tools_font_variants', __( 'Aucune des graisses demandées n\'existe pour cette police : regardez "weights" dans eo-tools/search-fonts.', 'eo-tools' ) );
		}

		// Family: reuse the installed one, or create it.
		$existing = wp_list_filter( self::get_installed(), array( 'slug' => $family['slug'] ) );
		$existing = $existing ? reset( $existing ) : null;
		if ( $existing ) {
			$family_id = $existing['_id'];
			$have      = array_map(
				function ( $face ) {
					return $face['fontWeight'] . '-' . $face['fontStyle'];
				},
				$existing['fontFace']
			);
		} else {
			$request = new \WP_REST_Request( 'POST', '/wp/v2/font-families' );
			$request->set_param(
				'font_family_settings',
				wp_json_encode(
					array(
						'name'       => $family['name'],
						'slug'       => $family['slug'],
						'fontFamily' => $family['fontFamily'],
					)
				)
			);
			$response = rest_do_request( $request );
			if ( $response->is_error() ) {
				return $response->as_error();
			}
			$family_id = (int) $response->get_data()['id'];
			$have      = array();
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$installed = array();
		$errors    = array();

		foreach ( $faces as $face ) {
			$key = $face['fontWeight'] . '-' . $face['fontStyle'];
			if ( in_array( $key, $have, true ) ) {
				continue;
			}

			$remote   = is_array( $face['src'] ) ? $face['src'][0] : $face['src'];
			$settings = array(
				'fontFamily' => $family['fontFamily'],
				'fontWeight' => (string) $face['fontWeight'],
				'fontStyle'  => $face['fontStyle'],
			);
			$request  = new \WP_REST_Request( 'POST', '/wp/v2/font-families/' . $family_id . '/font-faces' );

			// One file per face, as the font library does: deleting a face deletes its own file only.
			$local = self::download( $remote, $family['slug'] . '-' . $key );
			if ( is_wp_error( $local ) ) {
				$errors[] = $key . ' : ' . $local->get_error_message();
				continue;
			}
			$settings['src'] = $local['url'];

			$request->set_param( 'font_face_settings', wp_json_encode( $settings ) );
			$response = rest_do_request( $request );
			if ( $response->is_error() ) {
				wp_delete_file( $local['path'] );
				$errors[] = $key . ' : ' . $response->as_error()->get_error_message();
				continue;
			}

			// Lets WordPress delete the file with the font.
			add_post_meta( (int) $response->get_data()['id'], '_wp_font_face_file', $local['relative'] );
			$installed[] = $key;
		}

		// Nothing installed for a new family: remove it.
		if ( ! $existing && ! $installed ) {
			wp_delete_post( $family_id, true );
			return new \WP_Error( 'eo_tools_font_install', implode( ' ', $errors ) );
		}

		$result = array(
			'slug'      => $family['slug'],
			'name'      => $family['name'],
			'installed' => $installed,
			'errors'    => $errors,
			'message'   => __( 'Police installée dans la bibliothèque de polices du site (fichiers hébergés sur le site). Le site n\'a pas changé : pour l\'utiliser, proposez des styles avec eo-tools/propose-styles-update, "add_font_families": ["slug"], puis la police dans "changes" (ex. styles.typography.fontFamily = "var(--wp--preset--font-family--slug)").', 'eo-tools' ),
		);

		return $result;
	}

	/**
	 * Downloads a font file into the fonts folder of the site, like the font library does.
	 *
	 * @param string $url  Remote font file.
	 * @param string $name File name without extension.
	 * @return array|\WP_Error With url, path and relative (path inside the fonts folder).
	 */
	private static function download( $url, $name ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! in_array( $host, array( 'fonts.gstatic.com', 's.w.org' ), true ) ) {
			return new \WP_Error( 'eo_tools_font_source', __( 'Source de police non autorisée.', 'eo-tools' ) );
		}

		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			return new \WP_Error( 'eo_tools_font_download', __( 'Téléchargement impossible.', 'eo-tools' ) );
		}

		$file = array(
			'name'     => sanitize_file_name( $name . '.' . strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) ) ),
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => filesize( $tmp ),
		);

		// Same rules as the font library: font types only, stored in wp-content/uploads/fonts.
		add_filter( 'upload_mimes', array( 'WP_Font_Utils', 'get_allowed_font_mime_types' ) );
		add_filter( 'upload_dir', '_wp_filter_font_directory' );
		$moved = wp_handle_sideload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => \WP_Font_Utils::get_allowed_font_mime_types(),
			)
		);
		remove_filter( 'upload_dir', '_wp_filter_font_directory' );
		remove_filter( 'upload_mimes', array( 'WP_Font_Utils', 'get_allowed_font_mime_types' ) );

		if ( file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}
		if ( isset( $moved['error'] ) ) {
			return new \WP_Error( 'eo_tools_font_upload', $moved['error'] );
		}

		$base = trailingslashit( wp_normalize_path( wp_get_font_dir()['basedir'] ) );

		return array(
			'url'      => $moved['url'],
			'path'     => $moved['file'],
			'relative' => ltrim( str_replace( $base, '', wp_normalize_path( $moved['file'] ) ), '/' ),
		);
	}
}
