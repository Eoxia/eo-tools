<?php
/**
 * Ability: propose SEO changes of taxonomy terms (categories, tags…), applied by a human.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Proposals;
use EoTools\Includes\Mcp\Eotools_Mcp_Seo;
use EoTools\Includes\Mcp\Eotools_Mcp_Seo_Changes;
use EoTools\Includes\Mcp\Seo\Eotools_Mcp_Seo_Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Propose_Term_Seo extends Eotools_Mcp_Ability {

	const MAX_ITEMS = 50;

	public function get_slug() {
		return 'propose-term-seo';
	}

	public function get_label() {
		return __( 'Proposer le SEO d\'archives', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Propose les métadonnées SEO (titre, méta description, noindex… selon l\'extension SEO active) et le texte de présentation d\'archives de catégories, d\'étiquettes, de catégories de produits… Partez de la section "terms" de eo-tools/audit-site-seo et de eo-tools/list-seo-issues (types "term_*"), qui donnent les valeurs actuelles ("seo_fields"). Les modifications attendent la validation de l\'utilisateur dans EO Tools > MCP > À valider : vous ne pouvez pas les appliquer. Proposer à nouveau pour la même archive complète la proposition en attente.', 'eo-tools' );
	}

	public function get_group() {
		return 'seo';
	}

	public function get_risk() {
		return self::RISK_SENSITIVE;
	}

	/**
	 * @return array Schema of the "seo" field: the term fields of the active SEO plugin. Empty without one.
	 */
	private function get_seo_schema() {
		$provider = Eotools_Mcp_Seo::get_provider();
		$fields   = $provider->get_term_seo_fields();
		if ( ! $fields ) {
			return array();
		}

		return array(
			'seo' => array(
				'type'                 => 'object',
				/* translators: %s: SEO plugin name. */
				'description'          => sprintf( __( 'Métadonnées SEO de l\'archive, écrites dans %s. Seuls les champs donnés changent ; une valeur vide retire le champ.', 'eo-tools' ), $provider->get_name() ),
				'properties'           => array_intersect_key( Eotools_Mcp_Seo_Provider::get_field_definitions(), array_flip( $fields ) ),
				'additionalProperties' => false,
			),
		);
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'terms' => array(
					'type'     => 'array',
					'minItems' => 1,
					'maxItems' => self::MAX_ITEMS,
					'items'    => array(
						'type'                 => 'object',
						'properties'           => array_merge(
							array(
								'taxonomy'     => array(
									'type'        => 'string',
									'description' => __( 'Taxonomie ("category", "post_tag", "product_cat"…).', 'eo-tools' ),
								),
								'term_id'      => array(
									'type'    => 'integer',
									'minimum' => 1,
								),
								'archive_text' => array(
									'type'        => 'string',
									'description' => __( 'Texte de présentation affiché en haut de l\'archive (description du terme) : 2 à 4 phrases utiles avec la requête visée. Liens et mise en forme simple autorisés.', 'eo-tools' ),
								),
							),
							$this->get_seo_schema()
						),
						'required'             => array( 'taxonomy', 'term_id' ),
						'additionalProperties' => false,
					),
				),
			),
			'required'             => array( 'terms' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'manage_categories' );
	}

	public function execute( $input ) {
		$provider = Eotools_Mcp_Seo::get_provider();
		$proposed = array();
		$errors   = array();

		foreach ( array_slice( (array) $input['terms'], 0, self::MAX_ITEMS ) as $change ) {
			$taxonomy = isset( $change['taxonomy'] ) ? sanitize_key( $change['taxonomy'] ) : '';
			$term     = in_array( $taxonomy, Eotools_Mcp_Seo::get_taxonomies(), true ) ? get_term( (int) $change['term_id'], $taxonomy ) : null;
			$label    = $taxonomy . ':' . ( isset( $change['term_id'] ) ? (int) $change['term_id'] : 0 );

			if ( ! $term instanceof \WP_Term ) {
				$errors[] = array(
					'term'  => $label,
					'error' => __( 'Terme introuvable dans une taxonomie publique.', 'eo-tools' ),
				);
				continue;
			}
			if ( ! current_user_can( 'edit_term', $term->term_id ) ) {
				$errors[] = array(
					'term'  => $label,
					'error' => __( 'Vous n\'avez pas le droit de modifier ce terme.', 'eo-tools' ),
				);
				continue;
			}

			// Checked now so that the AI hears about wrong values; written again at apply time.
			$warnings = array();
			$seo      = array();
			if ( ! empty( $change['seo'] ) && is_array( $change['seo'] ) ) {
				foreach ( $change['seo'] as $field => $value ) {
					if ( ! in_array( $field, $provider->get_term_seo_fields(), true ) ) {
						/* translators: 1: SEO field, 2: SEO plugin name. */
						$warnings[] = sprintf( __( 'Champ SEO « %1$s » ignoré : %2$s ne le gère pas pour les archives.', 'eo-tools' ), $field, $provider->get_name() );
						continue;
					}
					$seo[ $field ] = $value;
				}
			}
			$archive_text = isset( $change['archive_text'] ) ? wp_kses_post( (string) $change['archive_text'] ) : null;

			if ( ! $seo && null === $archive_text ) {
				$errors[] = array(
					'term'  => $label,
					'error' => __( 'Aucune modification demandée.', 'eo-tools' ),
				);
				continue;
			}

			// Completes the pending change of the same term.
			$key     = $term->taxonomy . ':' . $term->term_id;
			$pending = Eotools_Mcp_Seo_Changes::find( Eotools_Mcp_Seo_Changes::KIND_TERM, $key );
			$data    = $pending ? $pending['data'] : array(
				'taxonomy' => $term->taxonomy,
				'term_id'  => $term->term_id,
				'name'     => $term->name,
				'seo'      => array(),
				'before'   => Eotools_Mcp_Seo_Changes::term_snapshot( $term ),
			);
			$data['seo'] = array_merge( $data['seo'], $seo );
			if ( null !== $archive_text ) {
				$data['archive_text'] = $archive_text;
			}

			$item = Eotools_Mcp_Seo_Changes::add( Eotools_Mcp_Seo_Changes::KIND_TERM, $key, $data );
			if ( is_wp_error( $item ) ) {
				$errors[] = array(
					'term'  => $label,
					'error' => $item->get_error_message(),
				);
				break;
			}

			$link       = get_term_link( $term );
			$proposed[] = array(
				'taxonomy' => $term->taxonomy,
				'term_id'  => $term->term_id,
				'name'     => $term->name,
				'link'     => is_wp_error( $link ) ? '' : $link,
				'seo'      => $data['seo'],
				'warnings' => $warnings,
			);
		}

		return array(
			'proposed'       => $proposed,
			'errors'         => $errors,
			'validation_url' => Eotools_Mcp_Proposals::admin_url(),
			'message'        => $proposed ? __( 'Modifications proposées, rien n\'a encore changé sur le site. Donnez le lien de validation à l\'utilisateur : il les applique ou les rejette depuis EO Tools > MCP > À valider.', 'eo-tools' ) : __( 'Aucune modification proposée.', 'eo-tools' ),
		);
	}
}
