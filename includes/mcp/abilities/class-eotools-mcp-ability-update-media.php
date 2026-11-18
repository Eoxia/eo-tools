<?php
/**
 * Ability: update the alternative text, title and caption of media.
 *
 * Only the media library changes: images already placed in a content keep the
 * text of their block (changed with eo-tools/propose-update).
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Update_Media extends Eotools_Mcp_Ability {

	const MAX_ITEMS = 50;

	public function get_slug() {
		return 'update-media';
	}

	public function get_label() {
		return __( 'Modifier des médias', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Modifie le texte alternatif, le titre et la légende de médias de la médiathèque (50 au plus par appel). Trouvez les images sans texte alternatif avec eo-tools/list-seo-issues (type "media_without_alt") et regardez chaque image avant de la décrire : le texte alternatif décrit ce que montre l\'image, en une phrase courte, sans « image de » ni bourrage de mots-clés ; vide pour une image purement décorative. Le texte de la médiathèque sert pour les images mises en avant et les nouvelles insertions ; les images déjà placées dans un contenu gardent le texte de leur bloc (eo-tools/propose-update pour les corriger).', 'eo-tools' );
	}

	public function get_group() {
		return 'media';
	}

	public function get_risk() {
		return self::RISK_WRITE;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'items' => array(
					'type'        => 'array',
					'minItems'    => 1,
					'maxItems'    => self::MAX_ITEMS,
					'description' => __( 'Médias à modifier. Seuls les champs donnés changent.', 'eo-tools' ),
					'items'       => array(
						'type'                 => 'object',
						'properties'           => array(
							'id'      => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'Identifiant du média.', 'eo-tools' ),
							),
							'alt'     => array(
								'type'        => 'string',
								'description' => __( 'Texte alternatif (images seulement).', 'eo-tools' ),
							),
							'title'   => array(
								'type'        => 'string',
								'description' => __( 'Titre du média dans la médiathèque.', 'eo-tools' ),
							),
							'caption' => array(
								'type'        => 'string',
								'description' => __( 'Légende.', 'eo-tools' ),
							),
						),
						'required'             => array( 'id' ),
						'additionalProperties' => false,
					),
				),
			),
			'required'             => array( 'items' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'upload_files' );
	}

	public function execute( $input ) {
		$updated = array();
		$errors  = array();

		foreach ( array_slice( (array) $input['items'], 0, self::MAX_ITEMS ) as $item ) {
			$id    = isset( $item['id'] ) ? (int) $item['id'] : 0;
			$media = get_post( $id );

			if ( ! $media || 'attachment' !== $media->post_type ) {
				/* translators: %d: attachment ID. */
				$errors[] = sprintf( __( 'Média %d introuvable.', 'eo-tools' ), $id );
				continue;
			}
			if ( ! current_user_can( 'edit_post', $id ) ) {
				/* translators: %d: attachment ID. */
				$errors[] = sprintf( __( 'Média %d : vous n\'avez pas le droit de le modifier.', 'eo-tools' ), $id );
				continue;
			}

			$changes = array();

			if ( isset( $item['alt'] ) ) {
				if ( wp_attachment_is_image( $id ) ) {
					$alt = sanitize_text_field( (string) $item['alt'] );
					update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $alt ) );
					$changes['alt'] = $alt;
				} else {
					/* translators: %d: attachment ID. */
					$errors[] = sprintf( __( 'Média %d : ce n\'est pas une image, texte alternatif ignoré.', 'eo-tools' ), $id );
				}
			}

			$data = array();
			if ( isset( $item['title'] ) && '' !== trim( (string) $item['title'] ) ) {
				$data['post_title'] = sanitize_text_field( (string) $item['title'] );
			}
			if ( isset( $item['caption'] ) ) {
				$data['post_excerpt'] = wp_kses_post( (string) $item['caption'] );
			}
			if ( $data ) {
				$result = wp_update_post( wp_slash( array_merge( array( 'ID' => $id ), $data ) ), true );
				if ( is_wp_error( $result ) ) {
					/* translators: 1: attachment ID, 2: error message. */
					$errors[] = sprintf( __( 'Média %1$d : %2$s', 'eo-tools' ), $id, $result->get_error_message() );
				} else {
					$changes = array_merge(
						$changes,
						array_combine(
							str_replace( array( 'post_title', 'post_excerpt' ), array( 'title', 'caption' ), array_keys( $data ) ),
							array_values( $data )
						)
					);
				}
			}

			if ( $changes ) {
				$updated[] = array_merge( array( 'id' => $id ), $changes );
			}
		}

		return array(
			'updated' => $updated,
			'errors'  => $errors,
			'message' => $updated ? __( 'Médias mis à jour. Les images déjà placées dans un contenu gardent le texte alternatif de leur bloc.', 'eo-tools' ) : __( 'Aucun média modifié.', 'eo-tools' ),
		);
	}
}
