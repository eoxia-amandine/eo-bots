<?php
/**
 * Code workshop: CSS and PHP snippets written by EO-BOTS, stored and run safely.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Snippets are posts of a private type: the code in post_content, the description in post_excerpt.
 * `publish` means active, `draft` inactive, so revisions, trash and the journal undo work as for pages.
 *
 * Safety net:
 * - PHP is checked for syntax errors before it is saved;
 * - activating PHP first loads the site in a loopback request with the snippet, like the core file editor;
 * - a snippet causing a fatal error later is switched off on the spot and the admins are told;
 * - a secret URL (or the EOBOTS_SAFE_MODE constant) switches every PHP snippet off.
 */
class Snippets {

	const POST_TYPE  = 'eobots_snippet';
	const TYPES      = array( 'css', 'php' );
	const SCOPES     = array( 'everywhere', 'front', 'admin' );
	const CACHE      = 'eobots_snippets';
	const ALERT      = 'eobots_snippet_alert';
	const SAFE_KEY   = 'eobots_safe_key';
	const META_TYPE  = '_eobots_type';
	const META_SCOPE = '_eobots_scope';
	const META_ERROR = '_eobots_error';

	/**
	 * PHP functions that deserve a warning on the confirmation card.
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
	 * Runs the active snippets (we are in plugins_loaded) and registers hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 0 );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'rebuild' ) );
		add_action( 'deleted_post', array( __CLASS__, 'deleted' ), 10, 2 );
		add_filter( 'wp_php_error_message', array( __CLASS__, 'fatal_message' ), 10, 2 );
		register_shutdown_function( array( __CLASS__, 'on_shutdown' ) );

		self::maybe_safe_mode();
		self::maybe_start_test();

		if ( ! ( defined( 'EOBOTS_SAFE_MODE' ) && EOBOTS_SAFE_MODE ) ) {
			self::run_php();
			add_action( 'enqueue_block_assets', array( __CLASS__, 'print_front_css' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'print_admin_css' ) );
		}
	}

	/**
	 * Registers the private post type holding the snippets.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'           => __( 'Snippets', 'eo-bots' ),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title', 'editor', 'excerpt', 'revisions' ),
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

	/* ---------- Rights ---------- */

	/**
	 * Whether the current user may write snippets of this type.
	 * CSS needs the core `edit_css` right (Additional CSS), PHP the right to edit plugin files,
	 * which WordPress removes with DISALLOW_FILE_EDIT.
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
		return (bool) apply_filters( 'eobots_php_snippets', true );
	}

	/* ---------- Reading ---------- */

	/**
	 * Snippet data.
	 *
	 * @param int|\WP_Post $post    Snippet.
	 * @param bool         $with_code Include the code.
	 * @return array<string, mixed>|null
	 */
	public static function get( $post, $with_code = true ) {
		$post = get_post( $post );
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return null;
		}

		$data  = array(
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
		if ( $with_code ) {
			$data['code'] = $post->post_content;
		}
		return $data;
	}

	/**
	 * All snippets, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'modified',
			)
		);
		return array_map( static fn( $post ) => self::get( $post, false ), $posts );
	}

	/**
	 * Snippet type.
	 *
	 * @param int $id Snippet ID.
	 * @return string
	 */
	public static function type( $id ) {
		$type = get_post_meta( $id, self::META_TYPE, true );
		return in_array( $type, self::TYPES, true ) ? $type : 'css';
	}

	/**
	 * Where the snippet runs.
	 *
	 * @param int $id Snippet ID.
	 * @return string
	 */
	public static function scope( $id ) {
		$scope = get_post_meta( $id, self::META_SCOPE, true );
		return in_array( $scope, self::SCOPES, true ) ? $scope : 'everywhere';
	}

	/* ---------- Writing ---------- */

	/**
	 * Creates an inactive snippet or edits one. An active snippet is tested before its new code is saved.
	 *
	 * @param array<string, mixed> $data {
	 *     Snippet fields.
	 *     @type int    $id          Snippet to edit; 0 to create.
	 *     @type string $title       Short name.
	 *     @type string $description What it does, for humans.
	 *     @type string $type        css or php (creation only).
	 *     @type string $scope       everywhere, front or admin.
	 *     @type string $code        Code.
	 * }
	 * @return int|\WP_Error Snippet ID.
	 */
	public static function save( $data ) {
		$id      = (int) ( $data['id'] ?? 0 );
		$current = $id ? get_post( $id ) : null;
		$type    = $current ? self::type( $id ) : ( $data['type'] ?? '' );
		$scope   = $data['scope'] ?? ( $current ? self::scope( $id ) : 'everywhere' );

		if ( ! in_array( $type, self::TYPES, true ) || ! in_array( $scope, self::SCOPES, true ) ) {
			return new \WP_Error( 'eobots_snippet_invalid', 'Type must be css or php, scope everywhere, front or admin.' );
		}

		$post = array(
			'post_type'   => self::POST_TYPE,
			'post_status' => $current ? $current->post_status : 'draft',
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
		if ( isset( $data['code'] ) ) {
			$code  = self::normalize( $data['code'], $type );
			$check = self::check( $code, $type );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
			$post['post_content'] = $code;
		}

		// New code for an active PHP snippet goes live at once: load the site with it first.
		if ( $current && 'publish' === $current->post_status && 'php' === $type && ( isset( $post['post_content'] ) || self::scope( $id ) !== $scope ) ) {
			$test = self::test( $id, $post['post_content'] ?? $current->post_content, $scope );
			if ( is_wp_error( $test ) ) {
				return $test;
			}
		}

		$id = $current ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_post_meta( $id, self::META_TYPE, $type );
		update_post_meta( $id, self::META_SCOPE, $scope );
		self::rebuild();

		return $id;
	}

	/**
	 * Switches a snippet on or off. Switching PHP on runs it in a loopback request first.
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
		if ( $active ) {
			delete_post_meta( $id, self::META_ERROR );
		}
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
			return new \WP_Error( 'eobots_snippet_empty', 'The code is empty.' );
		}

		if ( 'css' === $type ) {
			if ( false !== stripos( $code, '</style' ) || false !== stripos( $code, '<script' ) ) {
				return new \WP_Error( 'eobots_snippet_css', 'CSS only: no HTML tags.' );
			}
			$bare = preg_replace( '#/\*.*?\*/|"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'#s', '', $code );
			if ( substr_count( $bare, '{' ) !== substr_count( $bare, '}' ) ) {
				return new \WP_Error( 'eobots_snippet_css', 'CSS syntax error: the { and } braces do not match.' );
			}
			return true;
		}

		if ( preg_match( '/^\s*namespace\s/m', $code ) ) {
			return new \WP_Error( 'eobots_snippet_php', 'No namespace in a snippet: prefix the function names instead.' );
		}
		try {
			token_get_all( '<?php ' . $code, TOKEN_PARSE );
		} catch ( \ParseError $e ) {
			return new \WP_Error( 'eobots_snippet_php', sprintf( 'PHP syntax error line %d: %s', $e->getLine(), $e->getMessage() ) );
		}
		return true;
	}

	/**
	 * Sensitive PHP functions used by the code, for the confirmation card.
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

	/* ---------- Running ---------- */

	/**
	 * Folder holding the PHP files built from the active snippets.
	 * Running files instead of eval() lets a fatal error be traced back to its snippet.
	 *
	 * @return string
	 */
	public static function dir() {
		return (string) apply_filters( 'eobots_snippets_dir', WP_CONTENT_DIR . '/eo-bots-snippets' );
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
	 * Rebuilds the cache of active snippets: the CSS per scope and the list of PHP files to load.
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
				$css = sprintf( "/* EO-BOTS snippet #%d: %s */\n%s\n", $post->ID, str_replace( '*/', '', $post->post_title ), $post->post_content );
				foreach ( array( 'front', 'admin' ) as $where ) {
					if ( 'everywhere' === $scope || $where === $scope ) {
						$cache['css'][ $where ] .= $css;
					}
				}
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
			if ( ! in_array( basename( $file ), $keep, true ) ) {
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
		$test  = self::$test_key ? get_transient( 'eobots_snippet_test_' . self::$test_key ) : null;
		$side  = is_admin() ? 'admin' : 'front';

		foreach ( (array) ( $cache['php'] ?? array() ) as $snippet ) {
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

	/**
	 * Prints the active CSS of the public side (also loaded in the block editor canvas).
	 */
	public static function print_front_css() {
		self::print_css( 'front' );
	}

	/**
	 * Prints the active CSS of the admin side.
	 */
	public static function print_admin_css() {
		self::print_css( 'admin' );
	}

	/**
	 * Adds the CSS of one side as an inline style.
	 *
	 * @param string $side front or admin.
	 */
	private static function print_css( $side ) {
		$css = get_option( self::CACHE )['css'][ $side ] ?? '';
		if ( '' === $css ) {
			return;
		}
		$handle = 'eobots-snippets-' . $side;
		wp_register_style( $handle, false, array(), EO_BOTS_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );
	}

	/* ---------- Fatal errors ---------- */

	/**
	 * Switches a snippet off after an error and warns the admins.
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
	 * Short error for humans and the bot: no stack trace, no server paths.
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
	 * @param array<string, mixed> $error Error from error_get_last().
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
	 * Switches the guilty snippet off and tells the visitor to reload.
	 *
	 * @param string               $message Error page message.
	 * @param array<string, mixed> $error   Error.
	 * @return string
	 */
	public static function fatal_message( $message, $error ) {
		$id = self::blame( $error );
		if ( $id && ! self::$test_key ) {
			self::fail( $id, sprintf( '%s (line %d)', $error['message'], $error['line'] ) );
			self::$running = 0;
			$message      .= '<p>' . esc_html__( 'Your EO-BOT switched off the snippet that caused this error. Reload the page.', 'eo-bots' ) . '</p>';
		}
		return $message;
	}

	/**
	 * Last chance when the core handler is disabled or did not show its page, and reporting of loopback tests.
	 */
	public static function on_shutdown() {
		$error = error_get_last();

		if ( self::$test_key ) {
			$blamed = $error && self::blame( $error ) ? $error : null;
			if ( ! $blamed && $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
				$blamed = $error;
			}
			$result = $blamed ? str_replace( array( wp_normalize_path( self::dir() ) . '/', ABSPATH ), '', $blamed ) : true;
			if ( true === $result && self::$test_error ) {
				$result = array(
					'message' => self::$test_error,
					'line'    => 0,
				);
			}
			echo "\n###### eobots_test_start:" . esc_html( self::$test_key ) . " ######\n" . wp_json_encode( $result ) . "\n###### eobots_test_end ######\n";
			return;
		}

		$id = self::blame( $error );
		if ( $id ) {
			self::fail( $id, sprintf( '%s (line %d)', $error['message'], $error['line'] ) );
		}
	}

	/* ---------- Loopback test ---------- */

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
			return new \WP_Error( 'eobots_snippet_dir', sprintf( __( 'The folder %s cannot be written, so PHP snippets cannot run.', 'eo-bots' ), str_replace( ABSPATH, '', self::dir() ) ) );
		}

		$key = strtolower( wp_generate_password( 20, false ) ); // Read back with sanitize_key().
		set_transient(
			'eobots_snippet_test_' . $key,
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
			$token   = wp_parse_auth_cookie( $auth, $scheme )['token'] ?? '';
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
				add_query_arg( 'eobots-snippet-test', $key, $url ),
				array(
					'cookies'     => $cookies,
					'headers'     => array( 'Cache-Control' => 'no-cache' ),
					'timeout'     => 60,
					'redirection' => 0,
					/** This filter is documented in wp-includes/class-wp-http-streams.php */
					'sslverify'   => apply_filters( 'https_local_ssl_verify', false, $url ),
				)
			);
			$result = self::read_test( $response, $key );
			if ( is_wp_error( $result ) ) {
				break;
			}
		}

		delete_transient( 'eobots_snippet_test_' . $key );
		if ( $token ) {
			\WP_Session_Tokens::get_instance( $user_id )->destroy( $token );
		}
		return $result;
	}

	/**
	 * Reads the result printed by a loopback test.
	 *
	 * @param array<string, mixed>|\WP_Error $response HTTP response.
	 * @param string                         $key      Test key.
	 * @return true|\WP_Error
	 */
	private static function read_test( $response, $key ) {
		$body  = (string) wp_remote_retrieve_body( $response );
		$start = "###### eobots_test_start:{$key} ######";
		$pos   = strpos( $body, $start );

		if ( is_wp_error( $response ) || false === $pos ) {
			return new \WP_Error( 'eobots_snippet_untested', __( 'I could not load your site to test the snippet, so I did not switch it on. Your server may block requests to itself.', 'eo-bots' ) );
		}

		$json  = substr( $body, $pos + strlen( $start ) );
		$json  = substr( $json, 0, (int) strpos( $json, '###### eobots_test_end' ) );
		$error = json_decode( trim( $json ), true );
		if ( true === $error ) {
			return true;
		}

		$message = self::clean_error( $error['message'] ?? '?' );
		if ( ! empty( $error['line'] ) ) {
			$message .= ' (line ' . (int) $error['line'] . ')';
		}
		/* translators: %s: PHP error. */
		return new \WP_Error( 'eobots_snippet_fatal', sprintf( __( 'The snippet breaks the site, so I changed nothing: %s', 'eo-bots' ), $message ) );
	}

	/**
	 * Recognizes a loopback test request. The core fatal handler is put aside so the error is reported, not shown.
	 */
	private static function maybe_start_test() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The key is a one-time secret stored in a transient.
		if ( empty( $_GET['eobots-snippet-test'] ) ) {
			return;
		}
		$key = sanitize_key( wp_unslash( $_GET['eobots-snippet-test'] ) );
		// phpcs:enable
		if ( ! get_transient( 'eobots_snippet_test_' . $key ) ) {
			return;
		}
		self::$test_key = $key;
		if ( ! defined( 'WP_SANDBOX_SCRAPING' ) ) {
			define( 'WP_SANDBOX_SCRAPING', true );
		}
	}

	/* ---------- Safe mode ---------- */

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
		return add_query_arg( 'eobots-safe', $key, home_url( '/' ) );
	}

	/**
	 * Handles the safe mode URL.
	 */
	private static function maybe_safe_mode() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Secret key compared below.
		if ( empty( $_GET['eobots-safe'] ) ) {
			return;
		}
		$given = sanitize_text_field( wp_unslash( $_GET['eobots-safe'] ) );
		// phpcs:enable
		$key = (string) get_option( self::SAFE_KEY );
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

	/**
	 * Page shown after the safe mode URL, once translations can load.
	 */
	public static function safe_mode_page() {
		wp_die(
			'<p>' . esc_html(
				sprintf(
					/* translators: %d: number of snippets. */
					__( 'Safe mode: %d PHP snippet(s) switched off. Your site works without them now.', 'eo-bots' ),
					self::$safe_count
				)
			) . '</p><p><a href="' . esc_url( Admin::snippets_url() ) . '">' . esc_html__( 'See the snippets', 'eo-bots' ) . '</a></p>',
			esc_html__( 'EO-BOTS safe mode', 'eo-bots' ),
			array( 'response' => 200 )
		);
	}
}
