<?php
/**
 * WordPress settings: read and change the options of the Settings menu.
 *
 * Only a known list of core options can be changed (General, Writing, Reading,
 * Discussion, Media, Permalinks, Privacy). Options that could lock the
 * administrator out or hand the site over are refused or go through the core
 * safeguards:
 * - the site and WordPress addresses (siteurl, home) are read-only;
 * - a new administration e-mail is only applied once confirmed from the e-mail
 *   WordPress sends to that address;
 * - the default role of new users can never be a role able to manage the site.
 *
 * Every change is logged with its previous value, so it can be undone.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Settings {

	/**
	 * Changes made through EO Tools, most recent last.
	 */
	const LOG_OPTION = 'eo_tools_mcp_settings_log';
	const LOG_SIZE   = 200;

	/**
	 * Capabilities a default role must not have.
	 */
	const PRIVILEGED_CAPS = array(
		'manage_options',
		'promote_users',
		'create_users',
		'edit_users',
		'delete_users',
		'install_plugins',
		'activate_plugins',
		'edit_plugins',
		'edit_themes',
		'switch_themes',
		'edit_theme_options',
		'update_core',
		'unfiltered_html',
		'unfiltered_upload',
		'manage_network',
	);

	/**
	 * Settings screens, in the order of the Settings menu.
	 *
	 * @return string[] Labels keyed by section slug.
	 */
	public static function get_sections() {
		return array(
			'general'    => __( 'Général', 'eo-tools' ),
			'writing'    => __( 'Écriture', 'eo-tools' ),
			'reading'    => __( 'Lecture', 'eo-tools' ),
			'discussion' => __( 'Commentaires', 'eo-tools' ),
			'media'      => __( 'Médias', 'eo-tools' ),
			'permalinks' => __( 'Permaliens', 'eo-tools' ),
			'privacy'    => __( 'Confidentialité', 'eo-tools' ),
		);
	}

	/**
	 * Options that can be changed.
	 *
	 * Types: string, text (several lines), bool, int (min/max), enum (choices),
	 * page, category, email, role, locale, timezone, permalink.
	 *
	 * @return array[] Definitions keyed by option name.
	 */
	public static function get_definitions() {
		$definitions = array(
			// General.
			'blogname'                     => array( 'general', 'string', __( 'Titre du site', 'eo-tools' ) ),
			'blogdescription'              => array( 'general', 'string', __( 'Slogan', 'eo-tools' ) ),
			'admin_email'                  => array( 'general', 'email', __( 'Adresse e-mail d\'administration (appliquée après confirmation par e-mail)', 'eo-tools' ) ),
			'users_can_register'           => array( 'general', 'bool', __( 'Tout le monde peut s\'inscrire', 'eo-tools' ) ),
			'default_role'                 => array( 'general', 'role', __( 'Rôle par défaut des nouveaux utilisateurs', 'eo-tools' ) ),
			'WPLANG'                       => array( 'general', 'locale', __( 'Langue du site (vide = anglais)', 'eo-tools' ) ),
			'timezone_string'              => array( 'general', 'timezone', __( 'Fuseau horaire (ex. "Europe/Paris" ou "UTC+2")', 'eo-tools' ) ),
			'date_format'                  => array( 'general', 'string', __( 'Format de date (ex. "j F Y")', 'eo-tools' ) ),
			'time_format'                  => array( 'general', 'string', __( 'Format d\'heure (ex. "G\hi")', 'eo-tools' ) ),
			'start_of_week'                => array( 'general', 'int', __( 'La semaine débute le (0 = dimanche, 1 = lundi…)', 'eo-tools' ), array( 'min' => 0, 'max' => 6 ) ),

			// Writing.
			'default_category'             => array( 'writing', 'category', __( 'Catégorie par défaut des articles', 'eo-tools' ) ),
			'default_post_format'          => array( 'writing', 'enum', __( 'Format d\'article par défaut', 'eo-tools' ), array( 'choices' => self::post_format_choices() ) ),
			'ping_sites'                   => array( 'writing', 'text', __( 'Services de mise à jour (une adresse par ligne)', 'eo-tools' ) ),

			// Reading.
			'show_on_front'                => array( 'reading', 'enum', __( 'La page d\'accueil affiche : derniers articles ("posts") ou une page statique ("page")', 'eo-tools' ), array( 'choices' => array( 'posts', 'page' ) ) ),
			'page_on_front'                => array( 'reading', 'page', __( 'Page d\'accueil (page statique)', 'eo-tools' ) ),
			'page_for_posts'               => array( 'reading', 'page', __( 'Page des articles', 'eo-tools' ) ),
			'posts_per_page'               => array( 'reading', 'int', __( 'Nombre d\'articles par page', 'eo-tools' ), array( 'min' => 1, 'max' => 100 ) ),
			'posts_per_rss'                => array( 'reading', 'int', __( 'Nombre d\'éléments dans le flux RSS', 'eo-tools' ), array( 'min' => 1, 'max' => 100 ) ),
			'rss_use_excerpt'              => array( 'reading', 'bool', __( 'Flux RSS : extrait seulement (sinon texte complet)', 'eo-tools' ) ),
			'blog_public'                  => array( 'reading', 'bool', __( 'Visible par les moteurs de recherche (false = demande de ne pas indexer le site)', 'eo-tools' ) ),

			// Discussion.
			'default_pingback_flag'        => array( 'discussion', 'bool', __( 'Notifier les sites liés depuis l\'article', 'eo-tools' ) ),
			'default_ping_status'          => array( 'discussion', 'enum', __( 'Autoriser les rétroliens et pings sur les nouveaux articles', 'eo-tools' ), array( 'choices' => array( 'open', 'closed' ) ) ),
			'default_comment_status'       => array( 'discussion', 'enum', __( 'Autoriser les commentaires sur les nouveaux articles', 'eo-tools' ), array( 'choices' => array( 'open', 'closed' ) ) ),
			'require_name_email'           => array( 'discussion', 'bool', __( 'L\'auteur d\'un commentaire doit indiquer son nom et son e-mail', 'eo-tools' ) ),
			'comment_registration'         => array( 'discussion', 'bool', __( 'Il faut être inscrit et connecté pour commenter', 'eo-tools' ) ),
			'close_comments_for_old_posts' => array( 'discussion', 'bool', __( 'Fermer automatiquement les commentaires des anciens articles', 'eo-tools' ) ),
			'close_comments_days_old'      => array( 'discussion', 'int', __( 'Âge (en jours) à partir duquel les commentaires sont fermés', 'eo-tools' ), array( 'min' => 1, 'max' => 3650 ) ),
			'show_comments_cookies_opt_in' => array( 'discussion', 'bool', __( 'Case de consentement aux cookies des commentaires', 'eo-tools' ) ),
			'thread_comments'              => array( 'discussion', 'bool', __( 'Commentaires imbriqués', 'eo-tools' ) ),
			'thread_comments_depth'        => array( 'discussion', 'int', __( 'Niveaux d\'imbrication', 'eo-tools' ), array( 'min' => 2, 'max' => 10 ) ),
			'page_comments'                => array( 'discussion', 'bool', __( 'Diviser les commentaires en pages', 'eo-tools' ) ),
			'comments_per_page'            => array( 'discussion', 'int', __( 'Commentaires par page', 'eo-tools' ), array( 'min' => 1, 'max' => 500 ) ),
			'default_comments_page'        => array( 'discussion', 'enum', __( 'Page de commentaires affichée par défaut', 'eo-tools' ), array( 'choices' => array( 'newest', 'oldest' ) ) ),
			'comment_order'                => array( 'discussion', 'enum', __( 'Ordre des commentaires', 'eo-tools' ), array( 'choices' => array( 'asc', 'desc' ) ) ),
			'comments_notify'              => array( 'discussion', 'bool', __( 'M\'avertir par e-mail d\'un nouveau commentaire', 'eo-tools' ) ),
			'moderation_notify'            => array( 'discussion', 'bool', __( 'M\'avertir par e-mail d\'un commentaire en attente de modération', 'eo-tools' ) ),
			'comment_moderation'           => array( 'discussion', 'bool', __( 'Tout commentaire doit être approuvé manuellement', 'eo-tools' ) ),
			'comment_previously_approved'  => array( 'discussion', 'bool', __( 'L\'auteur doit avoir déjà un commentaire approuvé', 'eo-tools' ) ),
			'comment_max_links'            => array( 'discussion', 'int', __( 'Nombre de liens mettant un commentaire en attente', 'eo-tools' ), array( 'min' => 0, 'max' => 100 ) ),
			'moderation_keys'              => array( 'discussion', 'text', __( 'Mots mettant un commentaire en modération (un par ligne)', 'eo-tools' ) ),
			'disallowed_keys'              => array( 'discussion', 'text', __( 'Mots envoyant un commentaire à la corbeille (un par ligne)', 'eo-tools' ) ),
			'show_avatars'                 => array( 'discussion', 'bool', __( 'Afficher les avatars', 'eo-tools' ) ),
			'avatar_rating'                => array( 'discussion', 'enum', __( 'Classification maximale des avatars', 'eo-tools' ), array( 'choices' => array( 'G', 'PG', 'R', 'X' ) ) ),
			'avatar_default'               => array( 'discussion', 'enum', __( 'Avatar par défaut', 'eo-tools' ), array( 'choices' => array( 'mystery', 'blank', 'gravatar_default', 'identicon', 'wavatar', 'monsterid', 'retro', 'robohash' ) ) ),

			// Media.
			'thumbnail_size_w'             => array( 'media', 'int', __( 'Miniature : largeur (px)', 'eo-tools' ), array( 'min' => 0, 'max' => 10000 ) ),
			'thumbnail_size_h'             => array( 'media', 'int', __( 'Miniature : hauteur (px)', 'eo-tools' ), array( 'min' => 0, 'max' => 10000 ) ),
			'thumbnail_crop'               => array( 'media', 'bool', __( 'Recadrer la miniature aux dimensions exactes', 'eo-tools' ) ),
			'medium_size_w'                => array( 'media', 'int', __( 'Taille moyenne : largeur max (px)', 'eo-tools' ), array( 'min' => 0, 'max' => 10000 ) ),
			'medium_size_h'                => array( 'media', 'int', __( 'Taille moyenne : hauteur max (px)', 'eo-tools' ), array( 'min' => 0, 'max' => 10000 ) ),
			'large_size_w'                 => array( 'media', 'int', __( 'Grande taille : largeur max (px)', 'eo-tools' ), array( 'min' => 0, 'max' => 10000 ) ),
			'large_size_h'                 => array( 'media', 'int', __( 'Grande taille : hauteur max (px)', 'eo-tools' ), array( 'min' => 0, 'max' => 10000 ) ),
			'uploads_use_yearmonth_folders' => array( 'media', 'bool', __( 'Ranger les fichiers envoyés dans des dossiers mois/année', 'eo-tools' ) ),

			// Permalinks.
			'permalink_structure'          => array( 'permalinks', 'permalink', __( 'Structure des permaliens (ex. "/%postname%/", vide = liens simples ?p=123)', 'eo-tools' ) ),
			'category_base'                => array( 'permalinks', 'string', __( 'Préfixe des catégories (vide = "category")', 'eo-tools' ) ),
			'tag_base'                     => array( 'permalinks', 'string', __( 'Préfixe des étiquettes (vide = "tag")', 'eo-tools' ) ),

			// Privacy.
			'wp_page_for_privacy_policy'   => array( 'privacy', 'page', __( 'Page de politique de confidentialité', 'eo-tools' ) ),
		);

		// On a network, registrations are managed by the network administrator.
		if ( is_multisite() ) {
			unset( $definitions['users_can_register'], $definitions['default_role'] );
		}

		$list = array();
		foreach ( $definitions as $name => $definition ) {
			$list[ $name ] = array_merge(
				array(
					'section' => $definition[0],
					'type'    => $definition[1],
					'label'   => $definition[2],
				),
				isset( $definition[3] ) ? $definition[3] : array()
			);
		}

		/**
		 * Filters the WordPress options the AI may change.
		 *
		 * @param array[] $list Definitions keyed by option name (section, type, label, min, max, choices).
		 */
		return (array) apply_filters( 'eo_tools_mcp_settings', $list );
	}

	/**
	 * Options the AI may not change, with the reason.
	 *
	 * @return string[]
	 */
	public static function get_locked() {
		return array(
			'siteurl' => __( 'L\'adresse web de WordPress ne peut pas être modifiée par l\'IA : une erreur rendrait le site et l\'administration inaccessibles. Elle se change dans Réglages > Général.', 'eo-tools' ),
			'home'    => __( 'L\'adresse web du site ne peut pas être modifiée par l\'IA : une erreur rendrait le site inaccessible. Elle se change dans Réglages > Général.', 'eo-tools' ),
		);
	}

	/**
	 * @return string[]
	 */
	private static function post_format_choices() {
		return array_keys( get_post_format_strings() );
	}

	/* ---------------------------------------------------------------------
	 * Reading
	 * ------------------------------------------------------------------ */

	/**
	 * Current settings.
	 *
	 * @param string $section Section slug, empty for every section.
	 * @return array|\WP_Error
	 */
	public static function get( $section = '' ) {
		$sections = self::get_sections();
		if ( '' !== $section && ! isset( $sections[ $section ] ) ) {
			return new \WP_Error( 'eo_tools_unknown_section', __( 'Section de réglages inconnue.', 'eo-tools' ) );
		}

		$result = array();
		foreach ( self::get_definitions() as $name => $definition ) {
			if ( '' !== $section && $definition['section'] !== $section ) {
				continue;
			}

			$setting = array(
				'name'  => $name,
				'label' => $definition['label'],
				'type'  => $definition['type'],
				'value' => self::get_value( $name, $definition ),
			);
			foreach ( array( 'min', 'max' ) as $key ) {
				if ( isset( $definition[ $key ] ) ) {
					$setting[ $key ] = $definition[ $key ];
				}
			}
			$choices = self::get_choices( $definition );
			if ( null !== $choices ) {
				$setting['choices'] = $choices;
			}
			if ( 'page' === $definition['type'] && $setting['value'] ) {
				$setting['page_title'] = get_the_title( $setting['value'] );
			}
			if ( 'category' === $definition['type'] && $setting['value'] ) {
				$term = get_term( $setting['value'], 'category' );
				if ( $term instanceof \WP_Term ) {
					$setting['category_name'] = $term->name;
				}
			}

			$key = $definition['section'];
			if ( ! isset( $result[ $key ] ) ) {
				$result[ $key ] = array(
					'label'    => isset( $sections[ $key ] ) ? $sections[ $key ] : $key,
					'settings' => array(),
				);
			}
			$result[ $key ]['settings'][] = $setting;
		}

		$info = array(
			'siteurl' => get_option( 'siteurl' ),
			'home'    => get_option( 'home' ),
			'https'   => 0 === strpos( get_option( 'home' ), 'https://' ) && 0 === strpos( get_option( 'siteurl' ), 'https://' ),
			// Set in wp-config.php: read-only, a human changes them on the server.
			'debug'   => array(
				'WP_DEBUG'         => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'WP_DEBUG_DISPLAY' => defined( 'WP_DEBUG_DISPLAY' ) ? (bool) WP_DEBUG_DISPLAY : true,
				'WP_DEBUG_LOG'     => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
				'environment'      => wp_get_environment_type(),
			),
			'locked'  => self::get_locked(),
		);
		$pending_email = get_option( 'new_admin_email' );
		if ( $pending_email && get_option( 'admin_email' ) !== $pending_email ) {
			$info['admin_email_pending_confirmation'] = $pending_email;
		}

		return array(
			'sections' => $result,
			'info'     => $info,
		);
	}

	/**
	 * Current value of an option, as exposed to the AI.
	 *
	 * @param string $name       Option name.
	 * @param array  $definition Definition.
	 * @return mixed
	 */
	private static function get_value( $name, $definition ) {
		$value = get_option( $name );

		if ( 'default_post_format' === $name ) {
			return in_array( (string) $value, array( '', '0' ), true ) ? 'standard' : (string) $value;
		}

		switch ( $definition['type'] ) {
			case 'bool':
				return (bool) $value;
			case 'int':
			case 'page':
			case 'category':
				return (int) $value;
			case 'locale':
				return '' === (string) $value ? 'en_US' : (string) $value;
			case 'timezone':
				if ( '' !== (string) $value ) {
					return (string) $value;
				}
				$offset = (float) get_option( 'gmt_offset' );
				return 'UTC' . ( $offset >= 0 ? '+' : '' ) . $offset;
			default:
				// Core stores some texts (site title, tagline) HTML-escaped.
				return is_scalar( $value ) ? html_entity_decode( (string) $value, ENT_QUOTES, get_bloginfo( 'charset' ) ) : '';
		}
	}

	/**
	 * Allowed values of an option, when the list is short.
	 *
	 * @param array $definition Definition.
	 * @return array|null
	 */
	private static function get_choices( $definition ) {
		switch ( $definition['type'] ) {
			case 'enum':
				return array_values( $definition['choices'] );
			case 'role':
				return self::get_allowed_roles();
			case 'locale':
				return array_values( array_unique( array_merge( array( 'en_US' ), get_available_languages() ) ) );
			default:
				return null;
		}
	}

	/**
	 * Roles that can be given by default to new users.
	 *
	 * @return string[]
	 */
	private static function get_allowed_roles() {
		$allowed = array();
		foreach ( wp_roles()->role_objects as $slug => $role ) {
			$privileged = false;
			foreach ( self::PRIVILEGED_CAPS as $cap ) {
				if ( $role->has_cap( $cap ) ) {
					$privileged = true;
					break;
				}
			}
			if ( ! $privileged ) {
				$allowed[] = $slug;
			}
		}

		return $allowed;
	}

	/* ---------------------------------------------------------------------
	 * Writing
	 * ------------------------------------------------------------------ */

	/**
	 * Changes settings. Every value is checked first: when one is invalid,
	 * nothing is changed.
	 *
	 * @param array $settings New values keyed by option name.
	 * @return array|\WP_Error
	 */
	public static function update( $settings ) {
		if ( ! is_array( $settings ) || ! $settings ) {
			return new \WP_Error( 'eo_tools_no_settings', __( 'Aucun réglage à modifier.', 'eo-tools' ) );
		}

		$definitions = self::get_definitions();
		$locked      = self::get_locked();
		$errors      = array();
		$values      = array();

		foreach ( $settings as $name => $value ) {
			$name = (string) $name;
			if ( isset( $locked[ $name ] ) ) {
				$errors[ $name ] = $locked[ $name ];
				continue;
			}
			if ( ! isset( $definitions[ $name ] ) ) {
				$errors[ $name ] = __( 'Réglage inconnu ou non modifiable par l\'IA. Utilisez eo-tools/get-settings pour la liste des réglages.', 'eo-tools' );
				continue;
			}

			$checked = self::validate( $name, $value, $definitions[ $name ] );
			if ( is_wp_error( $checked ) ) {
				$errors[ $name ] = $checked->get_error_message();
				continue;
			}
			$values[ $name ] = $checked;
		}

		// A static front page needs a page.
		$show_on_front = isset( $values['show_on_front'] ) ? $values['show_on_front'] : get_option( 'show_on_front' );
		$page_on_front = isset( $values['page_on_front'] ) ? $values['page_on_front'] : (int) get_option( 'page_on_front' );
		if ( 'page' === $show_on_front && ! $page_on_front && ! isset( $errors['page_on_front'] ) ) {
			$errors['page_on_front'] = __( 'Une page d\'accueil statique demande de choisir la page (page_on_front).', 'eo-tools' );
		}
		$page_for_posts = isset( $values['page_for_posts'] ) ? $values['page_for_posts'] : (int) get_option( 'page_for_posts' );
		if ( 'page' === $show_on_front && $page_on_front && $page_on_front === $page_for_posts ) {
			$errors['page_for_posts'] = __( 'La page d\'accueil et la page des articles doivent être différentes.', 'eo-tools' );
		}

		if ( $errors ) {
			$lines = array();
			foreach ( $errors as $name => $reason ) {
				$lines[] = $name . ' : ' . $reason;
			}

			return new \WP_Error(
				'eo_tools_invalid_settings',
				__( 'Aucun réglage n\'a été modifié, corrigez les valeurs refusées :', 'eo-tools' ) . "\n" . implode( "\n", $lines ),
				array( 'errors' => $errors )
			);
		}

		$changed   = array();
		$unchanged = array();
		$notes     = array();
		$rewrite   = false;

		foreach ( $values as $name => $value ) {
			$definition = $definitions[ $name ];
			$before     = self::get_value( $name, $definition );

			if ( 'admin_email' === $name ) {
				if ( get_option( 'admin_email' ) === $value ) {
					$unchanged[] = $name;
					continue;
				}
				self::request_admin_email( $value );
				$notes[] = sprintf(
					/* translators: %s: e-mail address */
					__( 'Un e-mail de confirmation a été envoyé à %s : l\'adresse d\'administration ne change qu\'une fois le lien cliqué par un administrateur connecté.', 'eo-tools' ),
					$value
				);
				self::log( $name, $before, $value . ' (en attente de confirmation)' );
				$changed[] = array(
					'name'    => $name,
					'before'  => $before,
					'after'   => $before,
					'pending' => $value,
				);
				continue;
			}

			self::write( $name, $value, $definition );
			$after = self::get_value( $name, $definition );

			if ( $after === $before ) {
				$unchanged[] = $name;
				continue;
			}

			if ( 'permalinks' === $definition['section'] ) {
				$rewrite = true;
			}
			self::log( $name, $before, $after );
			$changed[] = array(
				'name'   => $name,
				'before' => $before,
				'after'  => $after,
			);
		}

		if ( $rewrite ) {
			flush_rewrite_rules( false );
		}

		$changed_names = wp_list_pluck( $changed, 'name' );
		if ( in_array( 'blog_public', $changed_names, true ) ) {
			$notes[] = get_option( 'blog_public' )
				? __( 'Le site est désormais ouvert aux moteurs de recherche.', 'eo-tools' )
				: __( 'Le site demande maintenant aux moteurs de recherche de ne pas l\'indexer.', 'eo-tools' );
		}
		if ( in_array( 'users_can_register', $changed_names, true ) && get_option( 'users_can_register' ) ) {
			$notes[] = sprintf(
				/* translators: %s: role slug */
				__( 'Les inscriptions sont ouvertes : chaque nouvel inscrit reçoit le rôle « %s ».', 'eo-tools' ),
				get_option( 'default_role' )
			);
		}
		if ( $rewrite ) {
			$notes[] = __( 'Les adresses des contenus ont changé : vérifiez les liens écrits en dur dans les menus et les contenus.', 'eo-tools' );
		}

		return array(
			'changed'   => $changed,
			'unchanged' => $unchanged,
			'notes'     => $notes,
		);
	}

	/**
	 * Checks and normalizes a value.
	 *
	 * @param string $name       Option name.
	 * @param mixed  $value      Value sent by the AI.
	 * @param array  $definition Definition.
	 * @return mixed|\WP_Error
	 */
	private static function validate( $name, $value, $definition ) {
		$type = $definition['type'];

		if ( 'bool' === $type ) {
			if ( is_bool( $value ) ) {
				return $value ? 1 : 0;
			}
			if ( in_array( $value, array( 0, 1, '0', '1' ), true ) ) {
				return (int) $value;
			}
			return new \WP_Error( 'invalid', __( 'Valeur attendue : true ou false.', 'eo-tools' ) );
		}

		if ( ! is_scalar( $value ) ) {
			return new \WP_Error( 'invalid', __( 'Valeur invalide.', 'eo-tools' ) );
		}

		switch ( $type ) {
			case 'int':
				if ( ! is_numeric( $value ) || (int) $value != $value ) { // phpcs:ignore Universal.Operators.StrictComparisons
					return new \WP_Error( 'invalid', __( 'Nombre entier attendu.', 'eo-tools' ) );
				}
				$value = (int) $value;
				if ( ( isset( $definition['min'] ) && $value < $definition['min'] ) || ( isset( $definition['max'] ) && $value > $definition['max'] ) ) {
					/* translators: 1: minimum, 2: maximum */
					return new \WP_Error( 'invalid', sprintf( __( 'Valeur attendue entre %1$d et %2$d.', 'eo-tools' ), $definition['min'], $definition['max'] ) );
				}
				return $value;

			case 'enum':
				if ( ! in_array( (string) $value, $definition['choices'], true ) ) {
					/* translators: %s: allowed values */
					return new \WP_Error( 'invalid', sprintf( __( 'Valeurs possibles : %s.', 'eo-tools' ), implode( ', ', $definition['choices'] ) ) );
				}
				return (string) $value;

			case 'page':
				$value = (int) $value;
				if ( 0 === $value ) {
					return 0;
				}
				$page = get_post( $value );
				if ( ! $page || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
					return new \WP_Error( 'invalid', __( 'Aucune page avec cet identifiant.', 'eo-tools' ) );
				}
				if ( 'wp_page_for_privacy_policy' !== $name && 'publish' !== $page->post_status ) {
					return new \WP_Error( 'invalid', __( 'La page doit être publiée.', 'eo-tools' ) );
				}
				return $value;

			case 'category':
				$term = get_term( (int) $value, 'category' );
				if ( ! $term instanceof \WP_Term ) {
					return new \WP_Error( 'invalid', __( 'Aucune catégorie avec cet identifiant.', 'eo-tools' ) );
				}
				return (int) $term->term_id;

			case 'email':
				$value = sanitize_email( $value );
				if ( ! is_email( $value ) ) {
					return new \WP_Error( 'invalid', __( 'Adresse e-mail invalide.', 'eo-tools' ) );
				}
				return $value;

			case 'role':
				$value = (string) $value;
				if ( ! in_array( $value, self::get_allowed_roles(), true ) ) {
					return new \WP_Error( 'invalid', __( 'Ce rôle n\'existe pas ou donne trop de droits pour être attribué automatiquement aux nouveaux inscrits.', 'eo-tools' ) );
				}
				return $value;

			case 'locale':
				$value = (string) $value;
				if ( 'en_US' === $value || '' === $value ) {
					return '';
				}
				if ( ! in_array( $value, get_available_languages(), true ) ) {
					return new \WP_Error( 'invalid', __( 'Cette langue n\'est pas installée sur le site (Réglages > Général permet de l\'installer).', 'eo-tools' ) );
				}
				return $value;

			case 'timezone':
				$value = trim( (string) $value );
				if ( preg_match( '/^UTC([+-]\d{1,2}(?:\.\d{1,2})?)?$/', $value, $match ) ) {
					$offset = isset( $match[1] ) ? (float) $match[1] : 0.0;
					if ( abs( $offset ) > 14 ) {
						return new \WP_Error( 'invalid', __( 'Décalage horaire invalide.', 'eo-tools' ) );
					}
					return array( 'offset' => $offset );
				}
				if ( ! in_array( $value, timezone_identifiers_list(), true ) ) {
					return new \WP_Error( 'invalid', __( 'Fuseau horaire inconnu (ex. "Europe/Paris").', 'eo-tools' ) );
				}
				return $value;

			case 'permalink':
				$value = trim( (string) $value );
				if ( '' === $value ) {
					return '';
				}
				if ( '/' !== $value[0] || ! preg_match( '/%[a-z_]+%/', $value ) ) {
					return new \WP_Error( 'invalid', __( 'La structure doit commencer par "/" et contenir au moins une étiquette, ex. "/%postname%/".', 'eo-tools' ) );
				}
				return $value;

			case 'text':
				return sanitize_textarea_field( (string) $value );

			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Saves a checked value.
	 *
	 * @param string $name       Option name.
	 * @param mixed  $value      Checked value.
	 * @param array  $definition Definition.
	 */
	private static function write( $name, $value, $definition ) {
		global $wp_rewrite;

		switch ( $name ) {
			case 'permalink_structure':
				$wp_rewrite->set_permalink_structure( $value );
				return;
			case 'category_base':
				$wp_rewrite->set_category_base( $value );
				return;
			case 'tag_base':
				$wp_rewrite->set_tag_base( $value );
				return;
		}

		if ( 'default_post_format' === $name && 'standard' === $value ) {
			$value = '0';
		}

		if ( 'timezone' === $definition['type'] ) {
			if ( is_array( $value ) ) {
				update_option( 'timezone_string', '' );
				update_option( 'gmt_offset', $value['offset'] );
			} else {
				update_option( 'timezone_string', $value );
			}
			return;
		}

		// update_option() runs sanitize_option(), the same checks as the Settings screens.
		update_option( $name, $value );
	}

	/**
	 * Asks for a new administration e-mail, confirmed by the core e-mail.
	 *
	 * @param string $email New address.
	 */
	private static function request_admin_email( $email ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		$old = get_option( 'new_admin_email' );
		update_option( 'new_admin_email', $email );

		// The core hook is only added in the admin, and does not run when the value is the same.
		$hooked = has_action( 'update_option_new_admin_email', 'update_option_new_admin_email' );
		if ( ( ! $hooked || $old === $email ) && function_exists( 'update_option_new_admin_email' ) ) {
			update_option_new_admin_email( $old, $email );
		}
	}

	/* ---------------------------------------------------------------------
	 * Log
	 * ------------------------------------------------------------------ */

	/**
	 * Records a change.
	 *
	 * @param string $name   Option name.
	 * @param mixed  $before Previous value.
	 * @param mixed  $after  New value.
	 */
	private static function log( $name, $before, $after ) {
		$log   = (array) get_option( self::LOG_OPTION, array() );
		$log[] = array(
			'option' => $name,
			'before' => $before,
			'after'  => $after,
			'user'   => get_current_user_id(),
			'time'   => time(),
		);

		update_option( self::LOG_OPTION, array_slice( $log, -self::LOG_SIZE ), false );
	}

	/**
	 * Changes made through EO Tools, most recent first.
	 *
	 * @param int $limit Number of entries.
	 * @return array[]
	 */
	public static function get_log( $limit = 50 ) {
		$log = array_reverse( (array) get_option( self::LOG_OPTION, array() ) );
		$log = array_slice( $log, 0, max( 1, (int) $limit ) );

		foreach ( $log as &$entry ) {
			$user          = get_userdata( $entry['user'] );
			$entry['user'] = $user ? $user->display_name : '';
			$entry['time'] = gmdate( 'c', (int) $entry['time'] );
		}

		return $log;
	}
}
