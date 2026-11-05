<?php
/**
 * Code snippets (CSS and PHP) proposed by the AI when the site cannot do a request natively.
 *
 * Ported from EO-BOTS. Same rule as the other proposals: the AI never puts code
 * live. It saves a snippet switched off (or, for an active snippet, a new
 * version waiting for validation); a human switches it on from
 * EO Tools > MCP > Snippets.
 *
 * Snippets are posts of a private type: the code in post_content, the
 * description in post_excerpt, `publish` means active and `draft` inactive.
 *
 * Safety net:
 * - PHP is checked for syntax errors before it is saved;
 * - switching PHP on first loads the site in a loopback request with the snippet, like the core file editor;
 * - PHP runs from files in wp-content/eo-tools-snippets, so a fatal error is traced back to its snippet,
 *   which is switched off on the spot (the administrators are told);
 * - a secret URL (or the EO_TOOLS_SAFE_MODE constant) switches every PHP snippet off.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Snippets {

	const POST_TYPE     = 'eo_tools_snippet';
	const TYPES         = array( 'css', 'php' );
	const SCOPES        = array( 'everywhere', 'front', 'admin' );
	const CACHE         = 'eo_tools_snippets';
	const ALERT         = 'eo_tools_snippet_alert';
	const SAFE_KEY      = 'eo_tools_snippets_safe_key';
	const META_TYPE     = '_eo_tools_snippet_type';
	const META_SCOPE    = '_eo_tools_snippet_scope';
	const META_ERROR    = '_eo_tools_snippet_error';
	const META_PENDING  = '_eo_tools_snippet_pending';
	const META_PROPOSED = '_eo_tools_snippet_proposed';
	const TEST_VAR      = 'eo-tools-snippet-test';
	const SAFE_VAR      = 'eo-tools-safe';
	const PREVIEW_VAR   = 'eo_tools_snippet_preview';

	/**
	 * PHP functions flagged to the user before switching a snippet on.
	 */
	const SENSITIVE = array( 'eval', 'assert', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'pcntl_exec', 'base64_decode', 'file_put_contents', 'fwrite', 'unlink', 'rmdir', 'rename', 'copy', 'move_uploaded_file', 'chmod', 'create_function', 'wp_set_current_user', 'wp_set_auth_cookie', 'wp_insert_user', 'wp_create_user', 'wp_update_user', 'set_role', 'add_cap', 'update_option', 'delete_option', 'wp_delete_post', 'wp_delete_user', 'wp_remote_get', 'wp_remote_post', 'wp_remote_request', 'curl_exec', 'mail', 'wp_mail' );

	/**
	 * Snippet running right now, to blame it for a fatal error.
	 *
	 * @var int
	 */
	private static $running = 0;

	/**
	 * Key of the loopback test running in this request.
	 *
	 * @var string
	 */
	private static $test_key = '';

	/**
	 * Error caught while a loopback test runs.
	 *
	 * @var string
	 */
	private static $test_error = '';

	/**
	 * Number of snippets switched off by the safe mode URL in this request.
	 *
	 * @var int
	 */
	private static $safe_count = 0;

	/**
	 * Registers the hooks. The snippets run once every plugin is loaded.
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 1 );
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 0 );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'rebuild' ) );
		add_action( 'deleted_post', array( __CLASS__, 'deleted' ), 10, 2 );
		add_filter( 'wp_php_error_message', array( __CLASS__, 'fatal_message' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'alert_notice' ) );
		add_action( 'admin_post_eo_tools_snippet', array( __CLASS__, 'handle_action' ) );
		register_shutdown_function( array( __CLASS__, 'on_shutdown' ) );
	}

	/**
	 * Runs the active snippets.
	 */
	public static function boot() {
		self::maybe_safe_mode();
		self::maybe_start_test();

		if ( defined( 'EO_TOOLS_SAFE_MODE' ) && EO_TOOLS_SAFE_MODE ) {
			return;
		}

		self::run_php();
		add_action( 'enqueue_block_assets', array( __CLASS__, 'print_front_css' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'print_admin_css' ) );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'           => __( 'Snippets EO Tools', 'eo-tools' ),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'can_export'      => true,
				'supports'        => array( 'title', 'editor', 'excerpt', 'revisions', 'author' ),
				'map_meta_cap'    => true,
				'capability_type' => self::POST_TYPE,
				// Snippets are code: only administrators touch them; type-specific rights are checked on top.
				'capabilities'    => array_fill_keys( array( 'edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_others_posts', 'delete_private_posts', 'delete_published_posts', 'create_posts', 'read' ), 'manage_options' ),
			)
		);
	}

	/**
	 * Rebuilds the cache when a snippet is deleted for good.
	 *
	 * @param int           $id   Post ID.
	 * @param \WP_Post|null $post Post.
	 */
	public static function deleted( $id, $post = null ) {
		if ( $post && self::POST_TYPE === $post->post_type ) {
			self::rebuild();
		}
	}

	/* ---------------------------------------------------------------------
	 * Rights
	 * ------------------------------------------------------------------ */

	/**
	 * Whether the current user may manage snippets of this type.
	 *
	 * CSS needs the core `edit_css` right (Additional CSS), PHP the right to edit
	 * plugin files, which WordPress removes with DISALLOW_FILE_EDIT.
	 *
	 * @param string $type css or php; empty for any type.
	 * @return bool
	 */
	public static function user_can( $type = '' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( 'php' === $type ) {
			return self::php_enabled() && current_user_can( 'edit_plugins' );
		}
		if ( 'css' === $type ) {
			return current_user_can( 'edit_css' );
		}

		return current_user_can( 'edit_css' ) || ( self::php_enabled() && current_user_can( 'edit_plugins' ) );
	}

	/**
	 * Whether PHP snippets are allowed on this site.
	 *
	 * @return bool
	 */
	public static function php_enabled() {
		/**
		 * Filters whether PHP snippets can be written and run.
		 *
		 * @param bool $enabled Default true.
		 */
		return (bool) apply_filters( 'eo_tools_php_snippets', true );
	}

	/* ---------------------------------------------------------------------
	 * Reading
	 * ------------------------------------------------------------------ */

	/**
	 * Snippet data.
	 *
	 * @param int|\WP_Post $post      Snippet.
	 * @param bool         $with_code Include the code.
	 * @return array|null
	 */
	public static function get( $post, $with_code = true ) {
		$post = get_post( $post );
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return null;
		}

		$data = array(
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'description' => $post->post_excerpt,
			'type'        => self::type( $post->ID ),
			'scope'       => self::scope( $post->ID ),
			'active'      => 'publish' === $post->post_status,
			'modified'    => $post->post_modified,
		);

		$error = get_post_meta( $post->ID, self::META_ERROR, true );
		if ( $error ) {
			$data['error'] = $error;
		}

		$pending = self::get_pending( $post->ID );
		if ( $pending ) {
			$data['pending_version'] = $with_code ? $pending : true;
		}
		if ( ! $data['active'] && get_post_meta( $post->ID, self::META_PROPOSED, true ) ) {
			$data['awaiting_activation'] = true;
		}
		if ( $with_code ) {
			$data['code'] = $post->post_content;
		}

		return $data;
	}

	/**
	 * All snippets, newest first.
	 *
	 * @param bool $with_code Include the code.
	 * @return array[]
	 */
	public static function all( $with_code = false ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 200,
				'orderby'        => 'modified',
			)
		);

		$snippets = array();
		foreach ( $posts as $post ) {
			$snippets[] = self::get( $post, $with_code );
		}

		return $snippets;
	}

	/**
	 * Number of snippets waiting for a human decision (new snippet or new version).
	 *
	 * @return int
	 */
	public static function count_waiting() {
		$count = 0;
		foreach ( self::all() as $snippet ) {
			$count += ( ! empty( $snippet['awaiting_activation'] ) || ! empty( $snippet['pending_version'] ) ) ? 1 : 0;
		}

		return $count;
	}

	public static function type( $id ) {
		$type = get_post_meta( $id, self::META_TYPE, true );

		return in_array( $type, self::TYPES, true ) ? $type : 'css';
	}

	public static function scope( $id ) {
		$scope = get_post_meta( $id, self::META_SCOPE, true );

		return in_array( $scope, self::SCOPES, true ) ? $scope : 'everywhere';
	}

	/**
	 * New version proposed for an active snippet.
	 *
	 * @param int $id Snippet ID.
	 * @return array|null With code, scope, description.
	 */
	public static function get_pending( $id ) {
		$pending = get_post_meta( $id, self::META_PENDING, true );

		return is_array( $pending ) && isset( $pending['code'] ) ? $pending : null;
	}

	/* ---------------------------------------------------------------------
	 * Writing
	 * ------------------------------------------------------------------ */

	/**
	 * Saves a snippet proposed by the AI.
	 *
	 * A new snippet is created switched off. The code of an inactive snippet is
	 * replaced. The code of an active snippet is kept, and the new code waits
	 * as a pending version that a human applies.
	 *
	 * @param array $data {
	 *     @type int    $id          Snippet to edit; 0 to create.
	 *     @type string $title       Short name.
	 *     @type string $description What it does, for humans.
	 *     @type string $type        css or php (creation only).
	 *     @type string $scope       everywhere, front or admin.
	 *     @type string $code        Code.
	 * }
	 * @return int|\WP_Error Snippet ID.
	 */
	public static function propose( $data ) {
		$id      = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$current = $id ? get_post( $id ) : null;

		if ( $id && ( ! $current || self::POST_TYPE !== $current->post_type || 'trash' === $current->post_status ) ) {
			return new \WP_Error( 'eo_tools_snippet_not_found', __( 'Aucun snippet avec cet identifiant.', 'eo-tools' ) );
		}

		$type  = $current ? self::type( $id ) : ( isset( $data['type'] ) ? $data['type'] : '' );
		$scope = isset( $data['scope'] ) ? $data['scope'] : ( $current ? self::scope( $id ) : 'everywhere' );

		if ( ! in_array( $type, self::TYPES, true ) || ! in_array( $scope, self::SCOPES, true ) ) {
			return new \WP_Error( 'eo_tools_snippet_invalid', __( '"type" doit valoir css ou php, "scope" everywhere, front ou admin.', 'eo-tools' ) );
		}
		if ( ! self::user_can( $type ) ) {
			return new \WP_Error( 'eo_tools_snippet_forbidden', 'php' === $type ? __( 'Les snippets PHP sont désactivés sur ce site (modification des fichiers interdite, ou droits insuffisants). Proposez une solution en CSS ou sans code.', 'eo-tools' ) : __( 'Vous n\'avez pas le droit d\'ajouter du CSS.', 'eo-tools' ) );
		}
		if ( ! $current && ( empty( $data['code'] ) || empty( $data['title'] ) ) ) {
			return new \WP_Error( 'eo_tools_snippet_invalid', __( 'Un nouveau snippet demande "title", "description" et "code".', 'eo-tools' ) );
		}

		$code = null;
		if ( isset( $data['code'] ) ) {
			$code  = self::normalize( $data['code'], $type );
			$check = self::check( $code, $type );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}

		// Active snippet: the change waits for a human.
		if ( $current && 'publish' === $current->post_status ) {
			$pending = self::get_pending( $id );
			update_post_meta(
				$id,
				self::META_PENDING,
				wp_slash(
					array(
						'code'        => null !== $code ? $code : ( $pending ? $pending['code'] : $current->post_content ),
						'scope'       => $scope,
						'title'       => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : ( $pending ? $pending['title'] : $current->post_title ),
						'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : ( $pending ? $pending['description'] : $current->post_excerpt ),
					)
				)
			);

			return $id;
		}

		$post = array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'draft',
		);
		if ( $current ) {
			$post['ID'] = $id;
		}
		if ( isset( $data['title'] ) ) {
			$post['post_title'] = sanitize_text_field( $data['title'] );
		}
		if ( isset( $data['description'] ) ) {
			$post['post_excerpt'] = sanitize_textarea_field( $data['description'] );
		}
		if ( null !== $code ) {
			$post['post_content'] = $code;
		}

		$id = $current ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_post_meta( $id, self::META_TYPE, $type );
		update_post_meta( $id, self::META_SCOPE, $scope );
		update_post_meta( $id, self::META_PROPOSED, 1 );
		delete_post_meta( $id, self::META_ERROR );
		self::rebuild();

		return $id;
	}

	/**
	 * Switches a snippet on or off (human action). Switching PHP on runs it in a loopback request first.
	 *
	 * @param int  $id     Snippet ID.
	 * @param bool $active On or off.
	 * @return true|\WP_Error
	 */
	public static function set_active( $id, $active ) {
		$post = get_post( $id );
		if ( $active && 'php' === self::type( $id ) ) {
			$test = self::test( $id, $post->post_content, self::scope( $id ) );
			if ( is_wp_error( $test ) ) {
				update_post_meta( $id, self::META_ERROR, wp_slash( $test->get_error_message() ) );
				return $test;
			}
		}

		$result = wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => $active ? 'publish' : 'draft',
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		delete_post_meta( $id, self::META_PROPOSED );
		if ( $active ) {
			delete_post_meta( $id, self::META_ERROR );
		}
		self::rebuild();

		return true;
	}

	/**
	 * Applies the pending version of an active snippet (human action). PHP is tested first.
	 *
	 * @param int $id Snippet ID.
	 * @return true|\WP_Error
	 */
	public static function apply_pending( $id ) {
		$pending = self::get_pending( $id );
		if ( ! $pending ) {
			return new \WP_Error( 'eo_tools_snippet_no_pending', __( 'Aucune nouvelle version en attente pour ce snippet.', 'eo-tools' ) );
		}

		if ( 'publish' === get_post_status( $id ) && 'php' === self::type( $id ) ) {
			$test = self::test( $id, $pending['code'], $pending['scope'] );
			if ( is_wp_error( $test ) ) {
				return $test;
			}
		}

		$result = wp_update_post(
			wp_slash(
				array(
					'ID'           => $id,
					'post_title'   => $pending['title'],
					'post_excerpt' => $pending['description'],
					'post_content' => $pending['code'],
				)
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_post_meta( $id, self::META_SCOPE, $pending['scope'] );
		delete_post_meta( $id, self::META_PENDING );
		delete_post_meta( $id, self::META_ERROR );
		self::rebuild();

		return true;
	}

	/**
	 * Removes the PHP tags and the line endings mess models sometimes add.
	 *
	 * @param string $code Code.
	 * @param string $type css or php.
	 * @return string
	 */
	public static function normalize( $code, $type ) {
		$code = trim( str_replace( "\r\n", "\n", (string) $code ) );
		if ( 'php' === $type ) {
			$code = preg_replace( '/^<\?php\s*/i', '', $code );
			$code = preg_replace( '/\?>\s*$/', '', $code );
		}

		return trim( $code ) . "\n";
	}

	/**
	 * Static checks before saving.
	 *
	 * @param string $code Normalized code.
	 * @param string $type css or php.
	 * @return true|\WP_Error
	 */
	public static function check( $code, $type ) {
		if ( '' === trim( $code ) ) {
			return new \WP_Error( 'eo_tools_snippet_empty', __( 'Le code est vide.', 'eo-tools' ) );
		}

		if ( 'css' === $type ) {
			if ( false !== stripos( $code, '</style' ) || false !== stripos( $code, '<script' ) ) {
				return new \WP_Error( 'eo_tools_snippet_css', __( 'Uniquement du CSS : pas de balises HTML.', 'eo-tools' ) );
			}
			$bare = preg_replace( '#/\*.*?\*/|"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'#s', '', $code );
			if ( substr_count( $bare, '{' ) !== substr_count( $bare, '}' ) ) {
				return new \WP_Error( 'eo_tools_snippet_css', __( 'Erreur de syntaxe CSS : les accolades { et } ne correspondent pas.', 'eo-tools' ) );
			}
			return true;
		}

		if ( preg_match( '/^\s*namespace\s/m', $code ) ) {
			return new \WP_Error( 'eo_tools_snippet_php', __( 'Pas de namespace dans un snippet : préfixez plutôt les noms de fonctions.', 'eo-tools' ) );
		}
		try {
			token_get_all( '<?php ' . $code, TOKEN_PARSE );
		} catch ( \ParseError $e ) {
			/* translators: 1: line, 2: PHP error. */
			return new \WP_Error( 'eo_tools_snippet_php', sprintf( __( 'Erreur de syntaxe PHP ligne %1$d : %2$s', 'eo-tools' ), $e->getLine(), $e->getMessage() ) );
		}

		return true;
	}

	/**
	 * Sensitive PHP functions used by the code, shown to the user before switching it on.
	 *
	 * @param string $code PHP code.
	 * @return string[]
	 */
	public static function sensitive( $code ) {
		$found = array();
		try {
			$tokens = token_get_all( '<?php ' . $code );
		} catch ( \Throwable $e ) {
			return $found;
		}
		foreach ( $tokens as $token ) {
			if ( is_array( $token ) && ( T_STRING === $token[0] || T_EVAL === $token[0] ) && in_array( strtolower( $token[1] ), self::SENSITIVE, true ) ) {
				$found[] = strtolower( $token[1] );
			}
		}
		if ( preg_match( '/`[^`]+`/', $code ) ) {
			$found[] = 'shell (backticks)';
		}

		return array_values( array_unique( $found ) );
	}

	/* ---------------------------------------------------------------------
	 * Running
	 * ------------------------------------------------------------------ */

	/**
	 * Folder holding the PHP files built from the active snippets.
	 *
	 * @return string
	 */
	public static function dir() {
		return (string) apply_filters( 'eo_tools_snippets_dir', WP_CONTENT_DIR . '/eo-tools-snippets' );
	}

	/**
	 * Whether the snippets folder exists or can be created, and is writable.
	 *
	 * @return bool
	 */
	public static function dir_ready() {
		$dir = self::dir();
		if ( ! is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return false;
			}
			// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Small guard files.
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
			file_put_contents( $dir . '/.htaccess', "Require all denied\n" );
			// phpcs:enable
		}

		return wp_is_writable( $dir );
	}

	/**
	 * Writes the PHP file of a snippet. The ABSPATH guard sits on the first line so line numbers match the code.
	 *
	 * @param string $name File name without extension.
	 * @param string $code PHP code.
	 * @return string|false Path.
	 */
	private static function write( $name, $code ) {
		if ( ! self::dir_ready() ) {
			return false;
		}
		$file = self::dir() . '/' . $name . '.php';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return false !== file_put_contents( $file, "<?php defined( 'ABSPATH' ) || exit; " . $code ) ? $file : false;
	}

	/**
	 * Rebuilds the cache of active snippets: the CSS per side and the list of PHP files to load.
	 */
	public static function rebuild() {
		$cache = array(
			'php' => array(),
			'css' => array(
				'front' => '',
				'admin' => '',
			),
		);

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$keep = array();
		foreach ( $posts as $post ) {
			$scope = self::scope( $post->ID );
			if ( 'css' === self::type( $post->ID ) ) {
				$css = sprintf( "/* EO Tools snippet #%d: %s */\n%s\n", $post->ID, str_replace( '*/', '', $post->post_title ), $post->post_content );
				foreach ( array( 'front', 'admin' ) as $side ) {
					if ( 'everywhere' === $scope || $side === $scope ) {
						$cache['css'][ $side ] .= $css;
					}
				}
				continue;
			}

			if ( ! self::php_enabled() ) {
				continue;
			}

			$name = sprintf( 'snippet-%d-%s', $post->ID, substr( md5( $post->post_content ), 0, 10 ) );
			if ( ! file_exists( self::dir() . '/' . $name . '.php' ) && ! self::write( $name, $post->post_content ) ) {
				continue;
			}
			$keep[]         = $name . '.php';
			$cache['php'][] = array(
				'id'    => $post->ID,
				'scope' => $scope,
				'file'  => $name . '.php',
			);
		}

		// Remove the files of old versions and inactive snippets.
		foreach ( (array) glob( self::dir() . '/snippet-*.php' ) as $file ) {
			if ( $file && ! in_array( basename( $file ), $keep, true ) ) {
				wp_delete_file( $file );
			}
		}

		update_option( self::CACHE, $cache, true );
	}

	/**
	 * Loads the active PHP snippets of the current side of the site.
	 */
	private static function run_php() {
		if ( ! self::php_enabled() ) {
			return;
		}

		$cache = get_option( self::CACHE );
		$test  = self::$test_key ? get_transient( 'eo_tools_snippet_test_' . self::$test_key ) : null;
		$side  = is_admin() ? 'admin' : 'front';
		$php   = is_array( $cache ) && isset( $cache['php'] ) ? (array) $cache['php'] : array();

		foreach ( $php as $snippet ) {
			// The snippet under test replaces its saved version.
			if ( $test && (int) $test['id'] === (int) $snippet['id'] ) {
				continue;
			}
			if ( 'everywhere' === $snippet['scope'] || $side === $snippet['scope'] ) {
				self::load( (int) $snippet['id'], self::dir() . '/' . $snippet['file'] );
			}
		}

		if ( $test && ( 'everywhere' === $test['scope'] || $side === $test['scope'] ) ) {
			$file = self::write( 'test-' . self::$test_key, $test['code'] );
			if ( $file ) {
				self::load( (int) $test['id'], $file );
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Includes one snippet file and switches the snippet off if it throws.
	 *
	 * @param int    $id   Snippet ID.
	 * @param string $file File.
	 */
	private static function load( $id, $file ) {
		if ( ! is_readable( $file ) ) {
			return;
		}
		self::$running = $id;
		try {
			include $file;
		} catch ( \Throwable $e ) {
			self::fail( $id, sprintf( '%s line %d: %s', get_class( $e ), $e->getLine(), $e->getMessage() ) );
		}
		self::$running = 0;
	}

	public static function print_front_css() {
		self::print_css( 'front' );
	}

	public static function print_admin_css() {
		self::print_css( 'admin' );
	}

	/**
	 * Adds the CSS of one side as an inline style, plus the snippet previewed by an administrator.
	 *
	 * @param string $side front or admin.
	 */
	private static function print_css( $side ) {
		$cache = get_option( self::CACHE );
		$css   = is_array( $cache ) && isset( $cache['css'][ $side ] ) ? $cache['css'][ $side ] : '';

		if ( 'front' === $side ) {
			$css .= self::preview_css();
		}
		if ( '' === $css ) {
			return;
		}

		$handle = 'eo-tools-snippets-' . $side;
		wp_register_style( $handle, false, array(), EO_TOOLS_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );
	}

	/* ---------------------------------------------------------------------
	 * CSS preview
	 * ------------------------------------------------------------------ */

	/**
	 * Link showing the site with a CSS snippet (or its pending version) applied, for administrators.
	 *
	 * @param int $id Snippet ID.
	 * @return string Empty for PHP snippets.
	 */
	public static function preview_url( $id ) {
		if ( 'css' !== self::type( $id ) || 'admin' === self::scope( $id ) ) {
			return '';
		}

		return add_query_arg( self::PREVIEW_VAR, (int) $id, home_url( '/' ) );
	}

	/**
	 * CSS of the snippet previewed in this request.
	 *
	 * @return string
	 */
	private static function preview_css() {
		if ( is_admin() || empty( $_GET[ self::PREVIEW_VAR ] ) || ! self::user_can( 'css' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview for administrators.
			return '';
		}

		$id   = absint( $_GET[ self::PREVIEW_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'css' !== self::type( $id ) ) {
			return '';
		}

		$pending = self::get_pending( $id );
		$code    = $pending ? $pending['code'] : $post->post_content;

		return sprintf( "/* EO Tools snippet preview #%d */\n%s\n", $id, $code );
	}

	/* ---------------------------------------------------------------------
	 * Fatal errors
	 * ------------------------------------------------------------------ */

	/**
	 * Switches a snippet off after an error and warns the administrators.
	 *
	 * @param int    $id      Snippet ID.
	 * @param string $message Error.
	 */
	private static function fail( $id, $message ) {
		global $wpdb;

		if ( self::$test_key ) {
			self::$test_error = $message; // A test only reports; nothing is saved yet.
			return;
		}

		$message = self::clean_error( $message );

		// Direct query: other plugins' save hooks must not run while the site is crashing.
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_post_cache( $id );
		update_post_meta( $id, self::META_ERROR, wp_slash( $message ) );
		update_option(
			self::ALERT,
			array(
				'id'      => $id,
				'title'   => get_the_title( $id ),
				'message' => $message,
			),
			false
		);
		self::rebuild();
	}

	/**
	 * Short error for humans and the AI: no stack trace, no server paths.
	 *
	 * @param string $message PHP error message.
	 * @return string
	 */
	public static function clean_error( $message ) {
		$message = preg_replace( '/\s*Stack trace:.*$/s', '', (string) $message );
		$dirs    = array( self::dir(), wp_normalize_path( self::dir() ), str_replace( '/', '\\', self::dir() ), ABSPATH, wp_normalize_path( ABSPATH ), str_replace( '/', '\\', ABSPATH ) );
		foreach ( $dirs as $dir ) {
			$message = str_replace( rtrim( $dir, '/\\' ) . DIRECTORY_SEPARATOR, '', $message );
			$message = str_replace( rtrim( $dir, '/\\' ) . '/', '', $message );
		}

		return trim( $message );
	}

	/**
	 * Snippet blamed for a PHP error: the one running, or the one whose file appears in the error.
	 *
	 * @param array|null $error Error from error_get_last().
	 * @return int
	 */
	private static function blame( $error ) {
		if ( ! $error || ! in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
			return 0;
		}
		if ( self::$running ) {
			return self::$running;
		}
		$where = wp_normalize_path( $error['file'] . ' ' . $error['message'] );
		if ( preg_match( '#' . preg_quote( wp_normalize_path( self::dir() ), '#' ) . '/snippet-(\d+)-#', $where, $match ) ) {
			return (int) $match[1];
		}

		return 0;
	}

	/**
	 * Called by the core fatal error handler before it shows "There has been a critical error".
	 *
	 * @param string $message Error page message.
	 * @param array  $error   Error.
	 * @return string
	 */
	public static function fatal_message( $message, $error ) {
		$id = self::blame( $error );
		if ( $id && ! self::$test_key ) {
			self::fail( $id, sprintf( '%s (line %d)', $error['message'], $error['line'] ) );
			self::$running = 0;
			$message      .= '<p>' . esc_html__( 'EO Tools a désactivé le snippet qui causait cette erreur. Rechargez la page.', 'eo-tools' ) . '</p>';
		}

		return $message;
	}

	/**
	 * Last chance when the core handler is disabled or did not show its page, and reporting of loopback tests.
	 */
	public static function on_shutdown() {
		$error = error_get_last();

		if ( self::$test_key ) {
			$fatal  = $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true );
			$result = $fatal ? str_replace( array( wp_normalize_path( self::dir() ) . '/', ABSPATH ), '', $error ) : true;
			if ( true === $result && self::$test_error ) {
				$result = array(
					'message' => self::$test_error,
					'line'    => 0,
				);
			}
			echo "\n###### eo_tools_test_start:" . esc_html( self::$test_key ) . " ######\n" . wp_json_encode( $result ) . "\n###### eo_tools_test_end ######\n";
			return;
		}

		$id = self::blame( $error );
		if ( $id ) {
			self::fail( $id, sprintf( '%s (line %d)', $error['message'], $error['line'] ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Loopback test
	 * ------------------------------------------------------------------ */

	/**
	 * Loads the site with a snippet, like the core plugin editor does, and reports fatal errors.
	 *
	 * @param int    $id    Snippet ID.
	 * @param string $code  PHP code to test.
	 * @param string $scope Where it runs.
	 * @return true|\WP_Error
	 */
	public static function test( $id, $code, $scope ) {
		if ( ! self::dir_ready() ) {
			/* translators: %s: folder path. */
			return new \WP_Error( 'eo_tools_snippet_dir', sprintf( __( 'Le dossier %s n\'est pas accessible en écriture : les snippets PHP ne peuvent pas fonctionner.', 'eo-tools' ), str_replace( ABSPATH, '', self::dir() ) ) );
		}

		$key = strtolower( wp_generate_password( 20, false ) ); // Read back with sanitize_key().
		set_transient(
			'eo_tools_snippet_test_' . $key,
			array(
				'id'    => (int) $id,
				'code'  => $code,
				'scope' => $scope,
			),
			2 * MINUTE_IN_SECONDS
		);

		$urls = array();
		if ( 'admin' !== $scope ) {
			$urls[] = home_url( '/' );
		}
		if ( 'front' !== $scope ) {
			$urls[] = admin_url( 'index.php' );
		}

		// A short-lived session so the admin side is tested as the logged-in user.
		$cookies = array();
		$token   = '';
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$scheme  = is_ssl() ? 'secure_auth' : 'auth';
			$auth    = wp_generate_auth_cookie( $user_id, time() + 5 * MINUTE_IN_SECONDS, $scheme );
			$parsed  = wp_parse_auth_cookie( $auth, $scheme );
			$token   = isset( $parsed['token'] ) ? $parsed['token'] : '';
			$cookies = array(
				( is_ssl() ? SECURE_AUTH_COOKIE : AUTH_COOKIE ) => $auth,
				LOGGED_IN_COOKIE => wp_generate_auth_cookie( $user_id, time() + 5 * MINUTE_IN_SECONDS, 'logged_in', $token ),
			);
		}

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 5 * MINUTE_IN_SECONDS ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Same as the core file editor loopback.
		}

		$result = true;
		foreach ( $urls as $url ) {
			$response = wp_remote_get(
				add_query_arg( self::TEST_VAR, $key, $url ),
				array(
					'cookies'     => $cookies,
					'headers'     => array( 'Cache-Control' => 'no-cache' ),
					'timeout'     => 60,
					'redirection' => 0,
					/** This filter is documented in wp-includes/class-wp-http-streams.php */
					'sslverify'   => apply_filters( 'https_local_ssl_verify', false, $url ),
				)
			);
			$result   = self::read_test( $response, $key );
			if ( is_wp_error( $result ) ) {
				break;
			}
		}

		delete_transient( 'eo_tools_snippet_test_' . $key );
		if ( $token ) {
			\WP_Session_Tokens::get_instance( $user_id )->destroy( $token );
		}

		return $result;
	}

	/**
	 * Reads the result printed by a loopback test.
	 *
	 * @param array|\WP_Error $response HTTP response.
	 * @param string          $key      Test key.
	 * @return true|\WP_Error
	 */
	private static function read_test( $response, $key ) {
		$body  = (string) wp_remote_retrieve_body( $response );
		$start = "###### eo_tools_test_start:{$key} ######";
		$pos   = strpos( $body, $start );

		if ( is_wp_error( $response ) || false === $pos ) {
			return new \WP_Error( 'eo_tools_snippet_untested', __( 'Impossible de charger le site pour tester le snippet : il n\'a pas été activé. Votre serveur bloque peut-être les requêtes vers lui-même.', 'eo-tools' ) );
		}

		$json  = substr( $body, $pos + strlen( $start ) );
		$json  = substr( $json, 0, (int) strpos( $json, '###### eo_tools_test_end' ) );
		$error = json_decode( trim( $json ), true );
		if ( true === $error ) {
			return true;
		}

		$message = self::clean_error( isset( $error['message'] ) ? $error['message'] : '?' );
		if ( ! empty( $error['line'] ) ) {
			/* translators: %d: line number. */
			$message .= ' ' . sprintf( __( '(ligne %d)', 'eo-tools' ), (int) $error['line'] );
		}

		/* translators: %s: PHP error. */
		return new \WP_Error( 'eo_tools_snippet_fatal', sprintf( __( 'Le snippet casse le site, rien n\'a été changé : %s', 'eo-tools' ), $message ) );
	}

	/**
	 * Recognizes a loopback test request. The core fatal handler is put aside so the error is reported, not shown.
	 */
	private static function maybe_start_test() {
		if ( empty( $_GET[ self::TEST_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- One-time secret stored in a transient.
			return;
		}
		$key = sanitize_key( wp_unslash( $_GET[ self::TEST_VAR ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! get_transient( 'eo_tools_snippet_test_' . $key ) ) {
			return;
		}
		self::$test_key = $key;
		if ( ! defined( 'WP_SANDBOX_SCRAPING' ) ) {
			define( 'WP_SANDBOX_SCRAPING', true );
		}
	}

	/* ---------------------------------------------------------------------
	 * Safe mode
	 * ------------------------------------------------------------------ */

	/**
	 * Secret URL that switches every PHP snippet off, usable when the site is broken.
	 *
	 * @return string
	 */
	public static function safe_url() {
		$key = get_option( self::SAFE_KEY );
		if ( ! $key ) {
			$key = wp_generate_password( 24, false );
			update_option( self::SAFE_KEY, $key, false );
		}

		return add_query_arg( self::SAFE_VAR, $key, home_url( '/' ) );
	}

	private static function maybe_safe_mode() {
		if ( empty( $_GET[ self::SAFE_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Secret key compared below.
			return;
		}
		$given = sanitize_text_field( wp_unslash( $_GET[ self::SAFE_VAR ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key   = (string) get_option( self::SAFE_KEY );
		if ( '' === $key || ! hash_equals( $key, $given ) ) {
			return;
		}

		global $wpdb;
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::META_TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => 'php', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $ids as $id ) {
			$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( $id );
		}
		self::rebuild();
		self::$safe_count = count( $ids );
		add_action( 'init', array( __CLASS__, 'safe_mode_page' ), 1 );
	}

	public static function safe_mode_page() {
		wp_die(
			'<p>' . esc_html(
				sprintf(
					/* translators: %d: number of snippets. */
					__( 'Mode secours : %d snippet(s) PHP désactivé(s). Votre site fonctionne maintenant sans eux.', 'eo-tools' ),
					self::$safe_count
				)
			) . '</p><p><a href="' . esc_url( self::admin_url() ) . '">' . esc_html__( 'Voir les snippets', 'eo-tools' ) . '</a></p>',
			esc_html__( 'EO Tools – mode secours', 'eo-tools' ),
			array( 'response' => 200 )
		);
	}

	/* ---------------------------------------------------------------------
	 * Admin: human actions
	 * ------------------------------------------------------------------ */

	/**
	 * URL of the Snippets tab.
	 *
	 * @param int    $id   Snippet to scroll to.
	 * @param string $done Result of the last action.
	 * @return string
	 */
	public static function admin_url( $id = 0, $done = '' ) {
		$url = add_query_arg( array_filter( array( 'done' => $done ) ), admin_url( 'admin.php?page=eo-tools-mcp&tab=snippets' ) );

		return $url . ( $id ? '#snippet-' . (int) $id : '' );
	}

	/**
	 * Nonce-protected URL of a human action on a snippet.
	 *
	 * @param int    $id     Snippet ID.
	 * @param string $action on, off, apply, reject or delete.
	 * @return string
	 */
	public static function action_url( $id, $action ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=eo_tools_snippet&do=' . $action . '&id=' . (int) $id ), 'eo_tools_snippet_' . (int) $id );
	}

	/**
	 * Switch on, switch off, apply or reject a new version, or delete a snippet.
	 */
	public static function handle_action() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'eo_tools_snippet_' . $id );

		$action  = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		$snippet = self::get( $id, false );
		if ( ! $snippet || ! self::user_can( $snippet['type'] ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas le droit de modifier ce snippet.', 'eo-tools' ), '', array( 'response' => 403 ) );
		}

		switch ( $action ) {
			case 'on':
			case 'off':
				$result = self::set_active( $id, 'on' === $action );
				break;
			case 'apply':
				$result = self::apply_pending( $id );
				break;
			case 'reject':
				$result = delete_post_meta( $id, self::META_PENDING ) ? true : new \WP_Error( 'eo_tools_snippet_no_pending', __( 'Aucune nouvelle version en attente pour ce snippet.', 'eo-tools' ) );
				break;
			case 'delete':
				$result = wp_trash_post( $id ) ? true : new \WP_Error( 'eo_tools_snippet_trash', __( 'Le snippet n\'a pas pu être supprimé.', 'eo-tools' ) );
				self::rebuild();
				break;
			default:
				$result = new \WP_Error( 'eo_tools_snippet_action', __( 'Action inconnue.', 'eo-tools' ) );
		}

		if ( is_wp_error( $result ) ) {
			set_transient( 'eo_tools_snippet_notice_' . get_current_user_id(), $result->get_error_message(), MINUTE_IN_SECONDS );
			wp_safe_redirect( self::admin_url( $id, 'error' ) );
		} else {
			wp_safe_redirect( self::admin_url( 'delete' === $action ? 0 : $id, $action ) );
		}
		exit;
	}

	/**
	 * Tells administrators that a snippet was switched off after an error.
	 */
	public static function alert_notice() {
		$alert = get_option( self::ALERT );
		if ( ! $alert || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: 1: snippet title, 2: PHP error. */
					__( 'EO Tools a désactivé le snippet « %1$s » car il provoquait une erreur : %2$s', 'eo-tools' ),
					$alert['title'],
					$alert['message']
				)
			),
			esc_url( self::admin_url( (int) $alert['id'] ) ),
			esc_html__( 'Voir les snippets', 'eo-tools' )
		);
	}
}
