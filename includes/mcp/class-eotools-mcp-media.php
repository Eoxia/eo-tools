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

	const RASTER_MIMES = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif' );

	const MAX_BYTES = 10485760;

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

		$response = wp_remote_get(
			add_query_arg( rawurlencode_deep( $params ), self::OPENVERSE_API ),
			array(
				'timeout'    => 20,
				'user-agent' => self::user_agent(),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'eo_tools_openverse', __( 'La recherche Openverse a échoué. Réessayez plus tard.', 'eo-tools' ) );
		}

		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		$images = array();
		foreach ( isset( $data['results'] ) ? $data['results'] : array() as $item ) {
			$images[] = self::format_openverse( $item );
		}

		return array(
			'total'  => isset( $data['result_count'] ) ? (int) $data['result_count'] : count( $images ),
			'images' => $images,
		);
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

		$response = wp_remote_get(
			self::OPENVERSE_API . $id . '/',
			array(
				'timeout'    => 20,
				'user-agent' => self::user_agent(),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'eo_tools_openverse', __( 'Image Openverse introuvable.', 'eo-tools' ) );
		}

		return self::format_openverse( json_decode( wp_remote_retrieve_body( $response ), true ) );
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
		}

		if ( ! empty( $input['svg'] ) ) {
			$attachment_id = self::import_svg( $input['svg'], $name, $title );
		} elseif ( ! empty( $input['data'] ) ) {
			$attachment_id = self::import_data( $input['data'], $name, $title );
		} elseif ( ! empty( $input['url'] ) ) {
			$attachment_id = self::import_url( $input['url'], $name, $title );
		} else {
			return new \WP_Error( 'eo_tools_image_source', __( 'Indiquez une source : "openverse_id", "url", "svg" ou "data".', 'eo-tools' ) );
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
