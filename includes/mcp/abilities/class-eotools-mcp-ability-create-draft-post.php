<?php
/**
 * Ability: create a draft post.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Create_Draft_Post extends Eotools_Mcp_Ability_Create_Draft {

	protected function get_post_type() {
		return 'post';
	}

	public function get_slug() {
		return 'create-draft-post';
	}

	public function get_label() {
		return __( 'Rédiger un article', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Crée un article en brouillon (jamais publié) avec son titre, son contenu en blocs, son extrait, ses catégories et étiquettes. Renvoie le lien d\'édition et le lien d\'aperçu à donner à l\'utilisateur. Images : demandez d\'abord à l\'utilisateur s\'il veut des photos libres Openverse (gratuit) ou des images générées (peut consommer des crédits), voir eo-tools/import-image.', 'eo-tools' );
	}
}
