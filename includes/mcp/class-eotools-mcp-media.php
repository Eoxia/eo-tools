<?php
/**
 * Images for the AI: Openverse search and import into the media library.
 *
 * Openverse (https://openverse.org) is the free, openly licensed media library
 * of WordPress. Its API needs no key.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Media {

	const OPENVERSE_API = 'https://api.openverse.org/v1/images/';

	const COMMONS_API = 'https://commons.wikimedia.org/w/api.php';

	/**
	 * Timeout of the image library APIs, in seconds: short, so that the AI does not wait.
	 */
	const TIMEOUT = 10;

	const RASTER_MIMES = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif' );

	const MAX_BYTES = 10485760;

	/**
	 * Rule given to the AI before adding images: the user chooses the image source,
	 * because generating images (by the AI or another connector such as Figma) may cost credits.
	 *
	 * @return string
	 */
	public static function get_source_rule() {
		return __( 'AVANT d\'ajouter des images, demandez TOUJOURS à l\'utilisateur quelle source il souhaite : (1) des photos libres et gratuites (eo-tools/search-images : Openverse, puis Wikimedia Commons automatiquement si Openverse ne répond pas), ou (2) des images que vous générez vous-même ou avec un autre outil connecté (Figma, générateur d\'images…), ce qui peut consommer des crédits. N\'utilisez que la source qu\'il a choisie, et redemandez pour chaque nouvelle demande d\'images.', 'eo-tools' );
	}

	/**
	 * User agent sent to Openverse, which asks API clients to identify themselves.
	 *
	 * @return string
	 */
	private static function user_agent() {
		return 'EO-Tools/' . EO_TOOLS_VERSION . ' (WordPress; +' . home_url( '/' ) . ')';
	}

	/**
	 * Searches openly licensed images on Openverse.
	 *
	 * @param array $args { query, per_page, orientation, category, commercial }.
	 * @return array|\WP_Error
	 */
	public static function search( $args ) {
		$openverse = self::search_openverse( $args );
		if ( ! is_wp_error( $openverse ) && ! empty( $openverse['images'] ) ) {
			return $openverse;
		}

		// Openverse failed, or found nothing: Wikimedia Commons takes over, so the AI does not have to.
		$commons = self::search_commons( $args );
		if ( is_wp_error( $commons ) ) {
			if ( ! is_wp_error( $openverse ) ) {
				return $openverse;
			}

			return new \WP_Error(
				'eo_tools_images_unavailable',
				__( 'Ni Openverse ni Wikimedia Commons ne répondent pour le moment (panne de leur côté, ce n\'est pas une limite de recherches). Ne relancez pas la recherche plus d\'une fois : proposez à l\'utilisateur une autre source (image générée, ou image qu\'il fournit : adresse ou médiathèque).', 'eo-tools' )
			);
		}

		$commons['notice'] = is_wp_error( $openverse )
			? __( 'Openverse ne répond pas pour le moment : résultats de Wikimedia Commons à la place, à importer avec leur "commons_id".', 'eo-tools' )
			: __( 'Rien trouvé sur Openverse : résultats de Wikimedia Commons, à importer avec leur "commons_id".', 'eo-tools' );

		return $commons;
	}

	/**
	 * Searches Openverse. Short timeout and no retry: Wikimedia Commons takes over when it fails.
	 *
	 * @param array $args { query, per_page, orientation, category, commercial }.
	 * @return array|\WP_Error
	 */
	private static function search_openverse( $args ) {
		$params = array(
			'q'         => $args['query'],
			'page_size' => max( 1, min( 20, (int) $args['per_page'] ) ),
			'mature'    => 'false',
		);
		if ( ! empty( $args['commercial'] ) ) {
			$params['license_type'] = 'commercial,modification';
		}
		if ( ! empty( $args['orientation'] ) ) {
			$params['aspect_ratio'] = $args['orientation'];
		}
		if ( ! empty( $args['category'] ) ) {
			$params['category'] = $args['category'];
		}

		$response = self::openverse_request( add_query_arg( rawurlencode_deep( $params ), self::OPENVERSE_API ), false );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		$images = array();
		foreach ( isset( $data['results'] ) ? $data['results'] : array() as $item ) {
			$images[] = self::format_openverse( $item );
		}

		return array(
			'source' => 'openverse',
			'total'  => isset( $data['result_count'] ) ? (int) $data['result_count'] : count( $images ),
			'images' => $images,
		);
	}

	/**
	 * Searches Wikimedia Commons (free media of Wikipedia, no key). Every file there
	 * is under a free license that allows commercial use and modification.
	 *
	 * @param array $args { query, per_page, orientation }.
	 * @return array|\WP_Error
	 */
	private static function search_commons( $args ) {
		$per_page = max( 1, min( 20, (int) $args['per_page'] ) );
		$data     = self::commons_request(
			array(
				'generator'    => 'search',
				'gsrsearch'    => $args['query'] . ' filetype:bitmap',
				'gsrnamespace' => 6,
				// More than asked: the orientation and file types are filtered afterwards.
				'gsrlimit'     => min( 50, $per_page * 2 ),
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$pages = isset( $data['query']['pages'] ) ? $data['query']['pages'] : array();
		uasort(
			$pages,
			function ( $a, $b ) {
				return ( isset( $a['index'] ) ? $a['index'] : 0 ) - ( isset( $b['index'] ) ? $b['index'] : 0 );
			}
		);

		$images = array();
		foreach ( $pages as $page ) {
			$image = self::format_commons( $page );
			if ( ! $image || ! self::matches_orientation( $image, isset( $args['orientation'] ) ? $args['orientation'] : '' ) ) {
				continue;
			}
			$images[] = $image;
			if ( count( $images ) >= $per_page ) {
				break;
			}
		}

		return array(
			'source' => 'commons',
			'total'  => count( $images ),
			'images' => $images,
		);
	}

	/**
	 * Details of a Wikimedia Commons image.
	 *
	 * @param int $page_id Commons file page ID.
	 * @return array|\WP_Error
	 */
	public static function get_commons_image( $page_id ) {
		$page_id = absint( $page_id );
		if ( ! $page_id ) {
			return new \WP_Error( 'eo_tools_commons_id', __( 'Identifiant Wikimedia Commons invalide.', 'eo-tools' ) );
		}

		$data = self::commons_request( array( 'pageids' => $page_id ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$image = ! empty( $data['query']['pages'][ $page_id ] ) ? self::format_commons( $data['query']['pages'][ $page_id ] ) : null;
		if ( ! $image ) {
			return new \WP_Error( 'eo_tools_commons_not_found', __( 'Image Wikimedia Commons introuvable, ou pas au format JPEG, PNG, GIF ou WebP.', 'eo-tools' ) );
		}

		return $image;
	}

	/**
	 * Query on the Wikimedia Commons API, with the image details.
	 *
	 * @param array $params Query parameters (generator or pageids).
	 * @return array|\WP_Error Decoded answer.
	 */
	private static function commons_request( $params ) {
		$params = array_merge(
			array(
				'action'              => 'query',
				'format'              => 'json',
				'prop'                => 'imageinfo',
				'iiprop'              => 'url|size|mime|extmetadata',
				// A resized copy: originals can be very large.
				'iiurlwidth'          => 1600,
				'iiextmetadatafilter' => 'Artist|LicenseShortName|ObjectName',
			),
			$params
		);

		$response = wp_remote_get(
			add_query_arg( rawurlencode_deep( $params ), self::COMMONS_API ),
			array(
				'timeout'    => self::TIMEOUT,
				'user-agent' => self::user_agent(),
			)
		);
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new \WP_Error(
				'eo_tools_commons_unavailable',
				$code
					/* translators: %d: HTTP status code. */
					? sprintf( __( 'Wikimedia Commons est indisponible pour le moment (erreur %d).', 'eo-tools' ), $code )
					: __( 'Wikimedia Commons ne répond pas pour le moment.', 'eo-tools' )
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Commons file page to the format of search results, or null if it cannot be imported.
	 *
	 * @param array $page Page from the Commons API.
	 * @return array|null
	 */
	private static function format_commons( $page ) {
		$info = isset( $page['imageinfo'][0] ) ? $page['imageinfo'][0] : null;
		if ( ! $info || empty( $info['mime'] ) || ! in_array( $info['mime'], array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true ) ) {
			return null;
		}

		$meta    = isset( $info['extmetadata'] ) ? $info['extmetadata'] : array();
		$creator = isset( $meta['Artist']['value'] ) ? trim( wp_strip_all_tags( $meta['Artist']['value'] ) ) : '';
		$license = isset( $meta['LicenseShortName']['value'] ) ? trim( wp_strip_all_tags( $meta['LicenseShortName']['value'] ) ) : '';
		$title   = isset( $meta['ObjectName']['value'] ) ? trim( wp_strip_all_tags( $meta['ObjectName']['value'] ) ) : preg_replace( '/^File:|\.[a-z0-9]+$/i', '', $page['title'] );
		$url     = ! empty( $info['thumburl'] ) ? $info['thumburl'] : $info['url'];

		return array(
			'commons_id'  => (int) $page['pageid'],
			'title'       => $title,
			'thumbnail'   => $url,
			'url'         => $url,
			'width'       => ! empty( $info['thumbwidth'] ) ? (int) $info['thumbwidth'] : (int) $info['width'],
			'height'      => ! empty( $info['thumbheight'] ) ? (int) $info['thumbheight'] : (int) $info['height'],
			'creator'     => $creator,
			'license'     => $license,
			'source_page' => isset( $info['descriptionurl'] ) ? $info['descriptionurl'] : '',
			'credit'      => self::credit( array( 'creator' => $creator ), '' !== $license ? $license . ' (Wikimedia Commons)' : 'Wikimedia Commons' ),
		);
	}

	/**
	 * Whether an image has the orientation asked for (wide, tall, square, or none).
	 *
	 * @param array  $image       Image with width and height.
	 * @param string $orientation Orientation.
	 * @return bool
	 */
	private static function matches_orientation( $image, $orientation ) {
		if ( '' === $orientation || ! $image['width'] || ! $image['height'] ) {
			return true;
		}

		$ratio = $image['width'] / $image['height'];
		if ( 'wide' === $orientation ) {
			return $ratio > 1.1;
		}
		if ( 'tall' === $orientation ) {
			return $ratio < 0.9;
		}

		return $ratio >= 0.9 && $ratio <= 1.1;
	}

	/**
	 * Details of an Openverse image.
	 *
	 * @param string $id Openverse image ID.
	 * @return array|\WP_Error
	 */
	public static function get_openverse_image( $id ) {
		if ( ! preg_match( '/^[a-f0-9-]{36}$/', $id ) ) {
			return new \WP_Error( 'eo_tools_openverse_id', __( 'Identifiant Openverse invalide.', 'eo-tools' ) );
		}

		$response = self::openverse_request( self::OPENVERSE_API . $id . '/' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return self::format_openverse( json_decode( wp_remote_retrieve_body( $response ), true ) );
	}

	/**
	 * GET on the Openverse API, retried once when Openverse is momentarily unavailable.
	 *
	 * The error tells the AI what happened (not found, rate limit, service unavailable),
	 * so that it does not take an outage for a limit, and offers the user another source.
	 *
	 * @param string $url   API URL.
	 * @param bool   $retry Retry once on timeout or server error (when no other source takes over).
	 * @return array|\WP_Error Response, or error.
	 */
	private static function openverse_request( $url, $retry = true ) {
		$args = array(
			'timeout'    => self::TIMEOUT,
			'user-agent' => self::user_agent(),
		);

		$response = wp_remote_get( $url, $args );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		// Timeout or server error: Openverse is often only briefly overloaded.
		if ( $retry && ( 0 === $code || $code >= 500 ) ) {
			sleep( 2 );
			$response = wp_remote_get( $url, $args );
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		}

		if ( 200 === $code ) {
			return $response;
		}

		if ( 404 === $code ) {
			return new \WP_Error( 'eo_tools_openverse_not_found', __( 'Image Openverse introuvable.', 'eo-tools' ) );
		}

		$other_source = __( 'Proposez à l\'utilisateur une autre source : une image générée, ou une image qu\'il fournit (adresse ou médiathèque).', 'eo-tools' );

		if ( 429 === $code ) {
			$wait = (int) wp_remote_retrieve_header( $response, 'retry-after' );

			return new \WP_Error(
				'eo_tools_openverse_rate_limit',
				$wait > 0
					/* translators: %d: seconds to wait. */
					? sprintf( __( 'Openverse limite le nombre de recherches : réessayez dans %d secondes.', 'eo-tools' ), $wait ) . ' ' . $other_source
					: __( 'Openverse limite le nombre de recherches : réessayez dans quelques minutes.', 'eo-tools' ) . ' ' . $other_source
			);
		}

		return new \WP_Error(
			'eo_tools_openverse_unavailable',
			( $code
				/* translators: %d: HTTP status code. */
				? sprintf( __( 'Openverse est indisponible pour le moment (erreur %d de son côté, ce n\'est pas une limite de recherches).', 'eo-tools' ), $code )
				: __( 'Openverse ne répond pas pour le moment (ce n\'est pas une limite de recherches).', 'eo-tools' )
			) . ' ' . $other_source
		);
	}

	private static function format_openverse( $item ) {
		$license = strtoupper( 'cc0' === $item['license'] || 'pdm' === $item['license'] ? $item['license'] : 'CC ' . $item['license'] ) . ( ! empty( $item['license_version'] ) ? ' ' . $item['license_version'] : '' );

		return array(
			'openverse_id' => $item['id'],
			'title'        => isset( $item['title'] ) ? $item['title'] : '',
			'thumbnail'    => isset( $item['thumbnail'] ) ? $item['thumbnail'] : '',
			'url'          => $item['url'],
			'width'        => isset( $item['width'] ) ? (int) $item['width'] : 0,
			'height'       => isset( $item['height'] ) ? (int) $item['height'] : 0,
			'creator'      => isset( $item['creator'] ) ? $item['creator'] : '',
			'license'      => $license,
			'source_page'  => isset( $item['foreign_landing_url'] ) ? $item['foreign_landing_url'] : '',
			'credit'       => self::credit( $item, $license ),
		);
	}

	/**
	 * Short credit line for the caption, as required by Creative Commons licenses.
	 */
	private static function credit( $item, $license ) {
		$parts = array();
		if ( ! empty( $item['creator'] ) ) {
			/* translators: %s: author name. */
			$parts[] = sprintf( __( 'Photo : %s', 'eo-tools' ), $item['creator'] );
		}
		$parts[] = $license;

		return implode( ' – ', $parts );
	}

	/* ---------------------------------------------------------------------
	 * Import
	 * ------------------------------------------------------------------ */

	/**
	 * Imports an image into the media library.
	 *
	 * @param array $input { openverse_id | url | svg | data + mime, alt, caption, title, filename }.
	 * @return array|\WP_Error
	 */
	public static function import( $input ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$alt     = isset( $input['alt'] ) ? sanitize_text_field( $input['alt'] ) : '';
		$caption = isset( $input['caption'] ) ? wp_kses_post( $input['caption'] ) : '';
		$title   = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
		$name    = sanitize_file_name( isset( $input['filename'] ) && '' !== $input['filename'] ? $input['filename'] : ( '' !== $title ? $title : 'image-ia' ) );
		$name    = preg_replace( '/\.[a-z0-9]+$/i', '', $name );

		if ( ! empty( $input['openverse_id'] ) ) {
			$image = self::get_openverse_image( $input['openverse_id'] );
			if ( is_wp_error( $image ) ) {
				return $image;
			}
			$input['url'] = $image['url'];
			$caption      = '' !== $caption ? $caption . ' – ' . $image['credit'] : $image['credit'];
			$title        = '' !== $title ? $title : $image['title'];
		} elseif ( ! empty( $input['commons_id'] ) ) {
			$image = self::get_commons_image( $input['commons_id'] );
			if ( is_wp_error( $image ) ) {
				return $image;
			}
			$input['url'] = $image['url'];
			$caption      = '' !== $caption ? $caption . ' – ' . $image['credit'] : $image['credit'];
			$title        = '' !== $title ? $title : $image['title'];
		}

		if ( ! empty( $input['svg'] ) ) {
			$attachment_id = self::import_svg( $input['svg'], $name, $title );
		} elseif ( ! empty( $input['data'] ) ) {
			$attachment_id = self::import_data( $input['data'], $name, $title );
		} elseif ( ! empty( $input['url'] ) ) {
			$attachment_id = self::import_url( $input['url'], $name, $title );
		} else {
			return new \WP_Error( 'eo_tools_image_source', __( 'Indiquez une source : "openverse_id", "commons_id", "url", "svg" ou "data".', 'eo-tools' ) );
		}

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		if ( '' !== $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}
		if ( '' !== $caption ) {
			wp_update_post(
				array(
					'ID'           => $attachment_id,
					'post_excerpt' => $caption,
				)
			);
		}

		return self::describe( $attachment_id );
	}

	/**
	 * Downloads an image from a public URL.
	 */
	private static function import_url( $url, $name, $title ) {
		if ( ! wp_http_validate_url( $url ) ) {
			return new \WP_Error( 'eo_tools_image_url', __( 'Adresse d\'image invalide ou non autorisée.', 'eo-tools' ) );
		}

		// download_url() uses wp_safe_remote_get(): local network addresses are refused.
		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		return self::sideload( $tmp, $name, $title );
	}

	/**
	 * Imports a base64 encoded raster image.
	 */
	private static function import_data( $data, $name, $title ) {
		$data   = preg_replace( '#^data:image/[a-z0-9.+-]+;base64,#i', '', trim( $data ) );
		$binary = base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $binary || strlen( $binary ) > self::MAX_BYTES ) {
			return new \WP_Error( 'eo_tools_image_data', __( 'Données d\'image invalides ou trop volumineuses (10 Mo maximum).', 'eo-tools' ) );
		}

		$tmp = wp_tempnam( $name );
		file_put_contents( $tmp, $binary ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		return self::sideload( $tmp, $name, $title );
	}

	/**
	 * Checks a downloaded file is a raster image, then adds it to the media library.
	 */
	private static function sideload( $tmp, $name, $title ) {
		$info = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $info || ! in_array( $info['mime'], self::RASTER_MIMES, true ) || filesize( $tmp ) > self::MAX_BYTES ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'eo_tools_image_type', __( 'Le fichier n\'est pas une image JPEG, PNG, GIF, WebP ou AVIF (10 Mo maximum).', 'eo-tools' ) );
		}

		$extension = wp_get_default_extension_for_mime_type( $info['mime'] );
		$id        = media_handle_sideload(
			array(
				'name'     => $name . '.' . $extension,
				'tmp_name' => $tmp,
			),
			0,
			$title
		);

		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
		}

		return $id;
	}

	/**
	 * Imports an SVG drawn by the AI, after sanitizing it.
	 */
	private static function import_svg( $svg, $name, $title ) {
		$clean = Eotools_Mcp_Svg::sanitize( $svg );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		// SVG uploads are not allowed by default: allow them for this sanitized file only.
		$allow_svg = function ( $mimes ) {
			$mimes['svg'] = 'image/svg+xml';
			return $mimes;
		};
		add_filter( 'upload_mimes', $allow_svg );
		$upload = wp_upload_bits( $name . '.svg', null, $clean['svg'] );
		remove_filter( 'upload_mimes', $allow_svg );

		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error( 'eo_tools_svg_upload', $upload['error'] );
		}

		$id = wp_insert_attachment(
			array(
				'post_title'     => '' !== $title ? $title : $name,
				'post_mime_type' => 'image/svg+xml',
				'post_status'    => 'inherit',
			),
			$upload['file'],
			0,
			true
		);

		if ( is_wp_error( $id ) ) {
			wp_delete_file( $upload['file'] );
			return $id;
		}

		wp_update_attachment_metadata(
			$id,
			array(
				'width'  => $clean['width'],
				'height' => $clean['height'],
				'file'   => _wp_relative_upload_path( $upload['file'] ),
			)
		);

		return $id;
	}

	/**
	 * Data returned to the AI, with a ready-to-use image block.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	private static function describe( $attachment_id ) {
		$meta  = wp_get_attachment_metadata( $attachment_id );
		$size  = ! empty( $meta['sizes']['large'] ) ? 'large' : 'full';
		$image = wp_get_attachment_image_src( $attachment_id, $size );
		$alt   = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		return array(
			'id'       => $attachment_id,
			'url'      => $image ? $image[0] : wp_get_attachment_url( $attachment_id ),
			'full_url' => wp_get_attachment_url( $attachment_id ),
			'width'    => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
			'height'   => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
			'alt'      => $alt,
			'caption'  => wp_get_attachment_caption( $attachment_id ),
			'block'    => array(
				'name'       => 'core/image',
				'attributes' => array_filter(
					array(
						'id'       => $attachment_id,
						'url'      => $image ? $image[0] : wp_get_attachment_url( $attachment_id ),
						'alt'      => $alt,
						'caption'  => wp_get_attachment_caption( $attachment_id ),
						'sizeSlug' => $size,
					)
				),
			),
		);
	}
}
