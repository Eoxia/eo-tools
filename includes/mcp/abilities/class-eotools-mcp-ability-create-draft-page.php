<?php
/**
 * Ability: create a draft page.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Create_Draft_Page extends Eotools_Mcp_Ability_Create_Draft {

	protected function get_post_type() {
		return 'page';
	}

	public function get_slug() {
		return 'create-draft-page';
	}

	public function get_label() {
		return __( 'Rédiger une page', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Crée une page en brouillon (jamais publiée) avec son titre, son contenu en blocs et sa page parente. Privilégiez les blocs EO Blocks pour la mise en page. Renvoie le lien d\'édition et le lien d\'aperçu à donner à l\'utilisateur.', 'eo-tools' );
	}
}
