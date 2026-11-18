<?php
/**
 * Detail of the SEO plugin's own analysis of a content.
 *
 * Yoast SEO runs its analysis (keyphrase in title, links, readability…) in the browser
 * and only stores the overall scores. When a content is saved in the editor, EO Tools
 * reads the detailed results from Yoast's editor store and keeps them in a post meta,
 * so that eo-tools/audit-post-seo can tell the AI which criteria fail.
 *
 * Nothing is sent outside the site, and Yoast SEO itself is not modified.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Seo;

use EoTools\Includes\Mcp\Eotools_Mcp_Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Seo_Analysis {

	/**
	 * Post meta holding the last captured analysis.
	 */
	const META = '_eo_tools_seo_analysis';

	/**
	 * Nonce action of the classic editor field.
	 */
	const NONCE = 'eo_tools_seo_analysis';

	/**
	 * Ratings Yoast gives to a criterion (its "upsell" items are left out).
	 */
	const RATINGS = array( 'bad', 'ok', 'good', 'feedback', 'error' );

	/**
	 * Maximum number of criteria kept per analysis.
	 */
	const MAX_RESULTS = 80;

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		// After Yoast SEO saved its own fields (priority 10), so that the fingerprint matches what was analysed.
		add_action( 'wp_insert_post', array( __CLASS__, 'save_from_classic_editor' ), 99, 2 );
	}

	/**
	 * Whether the analysis can be captured on this site (Yoast SEO is the active SEO plugin).
	 *
	 * @return bool
	 */
	public static function is_available() {
		return 'yoast' === Eotools_Mcp_Seo::get_provider()->get_id();
	}

	/**
	 * Loads the capture script on the content editor (block and classic editors).
	 *
	 * @param string $hook Admin page.
	 */
	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! self::is_available() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, Eotools_Mcp_Seo::get_post_types(), true ) ) {
			return;
		}

		wp_enqueue_script( 'eo-tools-seo-analysis', EO_TOOLS_URL . 'assets/js/seo-analysis.js', array( 'wp-data', 'wp-api-fetch' ), EO_TOOLS_VERSION, true );
		wp_localize_script(
			'eo-tools-seo-analysis',
			'eoToolsSeoAnalysis',
			array(
				'blockEditor' => method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor(),
				'path'        => '/eo-tools/v1/seo-analysis/',
				'nonce'       => wp_create_nonce( self::NONCE ),
			)
		);
	}

	public static function register_routes() {
		register_rest_route(
			'eo-tools/v1',
			'/seo-analysis/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_rest' ),
				'permission_callback' => function ( $request ) {
					return current_user_can( 'edit_post', (int) $request['id'] );
				},
			)
		);
	}

	/**
	 * Block editor: the script sends the analysis once the content and Yoast's fields are saved.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_rest( $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || ! self::is_available() ) {
			return new \WP_Error( 'eo_tools_seo_analysis', __( 'Analyse non enregistrée.', 'eo-tools' ), array( 'status' => 400 ) );
		}

		self::save( $post, (array) $request->get_json_params() );

		return rest_ensure_response( array( 'saved' => true ) );
	}

	/**
	 * Classic editor: the script puts the analysis in a hidden field of the form.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function save_from_classic_editor( $post_id, $post ) {
		if ( empty( $_POST['eo_tools_seo_analysis'] ) || empty( $_POST['eo_tools_seo_analysis_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below.
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eo_tools_seo_analysis_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! self::is_available() ) {
			return;
		}
		// Only the content of the submitted form.
		if ( ! isset( $_POST['post_ID'] ) || (int) $_POST['post_ID'] !== (int) $post_id ) {
			return;
		}

		$data = json_decode( wp_unslash( $_POST['eo_tools_seo_analysis'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, sanitized field by field in save().
		if ( is_array( $data ) ) {
			self::save( $post, $data );
		}
	}

	/**
	 * Stores an analysis sent by the editor.
	 *
	 * @param \WP_Post $post Content.
	 * @param array    $data Raw analysis: seo_score, readability_score, keyword, seo_results, readability_results.
	 */
	public static function save( $post, $data ) {
		$analysis = array(
			'plugin'              => 'yoast',
			'analyzed_at'         => gmdate( 'c' ),
			'fingerprint'         => self::fingerprint( $post ),
			'focus_keyword'       => isset( $data['keyword'] ) ? sanitize_text_field( (string) $data['keyword'] ) : '',
			'seo_score'           => self::score( $data, 'seo_score' ),
			'readability_score'   => self::score( $data, 'readability_score' ),
			'seo_results'         => self::clean_results( isset( $data['seo_results'] ) ? $data['seo_results'] : array() ),
			'readability_results' => self::clean_results( isset( $data['readability_results'] ) ? $data['readability_results'] : array() ),
		);

		update_post_meta( $post->ID, self::META, wp_slash( $analysis ) );
	}

	/**
	 * Detail of the last analysis, for eo-tools/audit-post-seo.
	 *
	 * @param \WP_Post $post Content.
	 * @return array
	 */
	public static function get( $post ) {
		$analysis = get_post_meta( $post->ID, self::META, true );

		if ( ! is_array( $analysis ) || empty( $analysis['analyzed_at'] ) ) {
			return array(
				'available' => false,
				'note'      => __( 'Le détail des critères Yoast SEO n\'est pas encore connu : Yoast ne l\'enregistre pas, EO Tools le relève quand le contenu est enregistré dans l\'éditeur WordPress. Demandez à l\'utilisateur d\'ouvrir le contenu dans l\'éditeur et de l\'enregistrer, puis relancez eo-tools/audit-post-seo.', 'eo-tools' ),
			);
		}

		$stale = self::fingerprint( $post ) !== $analysis['fingerprint'];

		return array(
			'available'           => true,
			'source'              => __( 'Analyse Yoast SEO de l\'éditeur, relevée au dernier enregistrement', 'eo-tools' ),
			'analyzed_at'         => $analysis['analyzed_at'],
			'stale'               => $stale,
			'focus_keyword'       => $analysis['focus_keyword'],
			'seo_score'           => $analysis['seo_score'],
			'readability_score'   => $analysis['readability_score'],
			'seo_results'         => self::sort_results( $analysis['seo_results'] ),
			'readability_results' => self::sort_results( $analysis['readability_results'] ),
			'note'                => $stale
				? __( 'Périmée : le contenu ou ses métadonnées SEO ont changé depuis cette analyse (par exemple une proposition appliquée). Les scores et les critères seront recalculés quand le contenu sera de nouveau enregistré dans l\'éditeur.', 'eo-tools' )
				: __( 'Critères classés du plus grave au meilleur (rating : bad, ok, good ; feedback = non évalué). "text" est le message de Yoast.', 'eo-tools' ),
		);
	}

	/**
	 * What Yoast analyses: when it changes, the stored analysis is out of date.
	 *
	 * @param \WP_Post $post Content.
	 * @return string
	 */
	public static function fingerprint( $post ) {
		$seo = array_intersect_key(
			Eotools_Mcp_Seo::get_provider()->get_seo_values( $post->ID ),
			array_flip( array( 'title', 'description', 'focus_keyword' ) )
		);

		return md5( wp_json_encode( array( $post->post_title, $post->post_content, $post->post_excerpt, $post->post_name, $seo ) ) );
	}

	/**
	 * Criteria sorted from the worst to the best.
	 *
	 * @param array[] $results Criteria.
	 * @return array[]
	 */
	public static function sort_results( $results ) {
		$order = array_flip( array( 'error', 'bad', 'ok', 'feedback', 'good' ) );
		usort(
			$results,
			function ( $a, $b ) use ( $order ) {
				$rank_a = isset( $order[ $a['rating'] ] ) ? $order[ $a['rating'] ] : 9;
				$rank_b = isset( $order[ $b['rating'] ] ) ? $order[ $b['rating'] ] : 9;

				return $rank_a - $rank_b;
			}
		);

		return $results;
	}

	/**
	 * @param array  $data Raw analysis.
	 * @param string $key  Score key.
	 * @return int|null Score from 0 to 100.
	 */
	private static function score( $data, $key ) {
		if ( ! isset( $data[ $key ] ) || ! is_numeric( $data[ $key ] ) ) {
			return null;
		}

		return max( 0, min( 100, (int) $data[ $key ] ) );
	}

	/**
	 * @param mixed $results Raw criteria from Yoast's store.
	 * @return array[] Each with id, rating, score and text (plain text).
	 */
	private static function clean_results( $results ) {
		$clean = array();
		foreach ( array_slice( (array) $results, 0, self::MAX_RESULTS ) as $result ) {
			if ( ! is_array( $result ) || empty( $result['id'] ) ) {
				continue;
			}
			$rating = isset( $result['rating'] ) ? sanitize_key( $result['rating'] ) : '';
			if ( ! in_array( $rating, self::RATINGS, true ) ) {
				continue;
			}
			$clean[] = array(
				'id'     => substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $result['id'] ), 0, 64 ),
				'rating' => $rating,
				'score'  => isset( $result['score'] ) && is_numeric( $result['score'] ) ? (int) $result['score'] : null,
				'text'   => wp_html_excerpt( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( html_entity_decode( (string) ( isset( $result['text'] ) ? $result['text'] : '' ), ENT_QUOTES, 'UTF-8' ) ) ) ), 500 ),
			);
		}

		return $clean;
	}
}
