<?php
/**
 * Site-level stylesheet pipeline (docs/research/04 — L3).
 *
 * Canonical artifact = CLI-compiled stylesheet from the closed vocabulary.
 * Without exec/CLI the pre-built vocabulary.min.css shipped with the plugin
 * is served: it covers the entire control closed set, so the frontend stays
 * complete for every panel-produced page.
 *
 * @package Blocky\Core\Compiler
 */

declare( strict_types=1 );

namespace Blocky\Core\Compiler;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

/**
 * Builds and serves the single frontend stylesheet.
 */
final class SiteStylesheet {

	/**
	 * Option storing the current built file name.
	 *
	 * @var string
	 */
	public const OPTION_FILE = 'blocky_site_css_file';

	/**
	 * Option storing the current build hash.
	 *
	 * @var string
	 */
	public const OPTION_HASH = 'blocky_site_css_hash';

	/**
	 * Cron hook for debounced rebuilds.
	 *
	 * @var string
	 */
	public const REBUILD_HOOK = 'blocky_rebuild_site_css';

	/**
	 * Directory of compiled vocabulary resources.
	 *
	 * @var string
	 */
	private string $resources_dir;

	/**
	 * Uploads subdirectory holding built stylesheets.
	 *
	 * @var string
	 */
	private string $upload_dir;

	/**
	 * Public base URL for built stylesheets.
	 *
	 * @var string
	 */
	private string $upload_url;

	/**
	 * Managed compiler binary.
	 *
	 * @var TailwindBinary
	 */
	private TailwindBinary $binary;

	/**
	 * Constructor.
	 *
	 * @param string         $resources_dir Vocabulary resources directory.
	 * @param string         $upload_dir    Directory for built stylesheets.
	 * @param string         $upload_url    Public base URL for built files.
	 * @param TailwindBinary $binary        Managed compiler binary.
	 */
	public function __construct( string $resources_dir, string $upload_dir, string $upload_url, TailwindBinary $binary ) {
		$this->resources_dir = $resources_dir;
		$this->upload_dir    = $upload_dir;
		$this->upload_url    = $upload_url;
		$this->binary        = $binary;
	}

	/**
	 * Factory wired to WordPress paths.
	 *
	 * @return self Instance for this installation.
	 */
	public static function from_globals(): self {
		$uploads = \wp_upload_dir();
		$base    = rtrim( (string) $uploads['basedir'], '/\\' ) . '/blocky';

		return new self(
			BLOCKY_CORE_DIR . 'resources/css',
			$base . '/css',
			rtrim( (string) $uploads['baseurl'], '/' ) . '/blocky/css',
			// Compiler cache outside the web-served uploads tree (never publicly accessible).
			new TailwindBinary( \dirname( (string) $uploads['basedir'] ) . '/blocky-engine/bin' )
		);
	}

	/**
	 * Best available stylesheet URL (bundled vocabulary is the floor).
	 *
	 * @return string Absolute URL.
	 */
	public function available_url(): string {
		$built = (string) \get_option( self::OPTION_FILE, '' );

		if ( '' !== $built && is_readable( $this->upload_dir . '/' . $built ) ) {
			return rtrim( $this->upload_url, '/' ) . '/' . $built;
		}

		return BLOCKY_CORE_URL . 'resources/css/vocabulary.min.css';
	}

	/**
	 * Cache-busting version for the served stylesheet.
	 *
	 * @return string Version string.
	 */
	public function available_version(): string {
		$built = (string) \get_option( self::OPTION_FILE, '' );

		if ( '' !== $built && is_readable( $this->upload_dir . '/' . $built ) ) {
			return (string) \get_option( self::OPTION_HASH, '1' );
		}

		$modified = @filemtime( $this->resources_dir . '/vocabulary.min.css' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- optional stat.
		return false !== $modified ? (string) $modified : BLOCKY_CORE_VERSION;
	}

	/**
	 * Union of page-stored class candidates (code-mode extensions).
	 *
	 * @return array<int,string> Sorted candidate list.
	 */
	public function collect_candidates(): array {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- intentional compile-time scan, capped query.
		$posts = \get_posts(
			array(
				'post_type'              => 'any',
				'post_status'            => 'publish',
				'posts_per_page'         => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- compiler scan, capped.
				'fields'                 => 'ids',
				'meta_key'               => PageCompiler::CSS_CANDIDATES_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- capped compile scan.
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$union = array();
		foreach ( (array) $posts as $post_id ) {
			$raw     = (string) \get_post_meta( (int) $post_id, PageCompiler::CSS_CANDIDATES_META_KEY, true );
			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			foreach ( $decoded as $candidate ) {
				$valid_shape = is_string( $candidate ) && '' !== $candidate && 1 === preg_match( '/^[a-zA-Z0-9_:.\/%\[\]()#&+~<>=,!\x27\-]+$/', $candidate );
				if ( $valid_shape ) {
					$union[ $candidate ] = true;
				}
			}
		}

		$keys = array_keys( $union );
		sort( $keys );
		return $keys;
	}

	/**
	 * Compile the site stylesheet when the CLI binary is available.
	 *
	 * @return bool True when a fresh stylesheet was produced.
	 */
	public function rebuild(): bool {
		if ( \function_exists( 'get_option' ) && 'yes' !== \get_option( 'blocky_allow_engine_download', '' ) ) {
			\update_option( 'blocky_engine_status', 'consent_needed' );
			return false;
		}
		\update_option( 'blocky_engine_status', '' );
		$target = TailwindBinary::detect_target();
		if ( null === $target || ! TailwindBinary::exec_available() ) {
			return false;
		}

		$has_dir = is_dir( $this->upload_dir ) || \wp_mkdir_p( $this->upload_dir );
		if ( ! $has_dir ) {
			return false;
		}

		$entry     = $this->resources_dir . '/vocabulary.source.css';
		$entry_tmp = null;
		$extra     = $this->collect_candidates();

		if ( array() !== $extra ) {
			$entry_tmp = $this->upload_dir . '/entry.tmp.css';
			$vocab_ref = str_replace( '\\', '/', $this->resources_dir ) . '/vocabulary.source.css';
			$lines     = '@import "' . $vocab_ref . '";' . chr( 10 );
			foreach ( $extra as $candidate ) {
				$lines .= '@source inline("' . $candidate . '");' . chr( 10 );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged -- temp entry inside uploads.
			$written = @file_put_contents( $entry_tmp, $lines );
			if ( false === $written ) {
				return false;
			}
			$entry = $entry_tmp;
		}

		$fingerprint = $this->source_fingerprint() . implode( ',', $extra );
		$hash        = substr( hash( 'sha256', $fingerprint ), 0, 16 );
		$out         = 'site-' . $hash . '.css';
		$ok          = $this->binary->compile( $target, $entry, $this->upload_dir . '/' . $out );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged -- temp entry cleanup.
		@unlink( (string) $entry_tmp );

		if ( ! $ok ) {
			return false;
		}

		$previous = (string) \get_option( self::OPTION_FILE, '' );
		\update_option( self::OPTION_FILE, $out );
		\update_option( self::OPTION_HASH, $hash );

		if ( '' !== $previous && $previous !== $out && is_readable( $this->upload_dir . '/' . $previous ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged -- superseded build removal.
			@unlink( $this->upload_dir . '/' . $previous );
		}

		return true;
	}

	/**
	 * Debounced rebuild trigger (thirty second window).
	 *
	 * @return void
	 */
	public static function schedule_rebuild(): void {
		if ( ! \wp_next_scheduled( self::REBUILD_HOOK ) ) {
			\wp_schedule_single_event( time() + 30, self::REBUILD_HOOK );
		}
	}

	/**
	 * Fingerprint of vocabulary sources and engine version.
	 *
	 * @return string Stable fingerprint input.
	 */
	private function source_fingerprint(): string {
		$hash    = TailwindBinary::VERSION;
		$sources = array( 'vocabulary.source.css', 'tokens.tailwind.css', 'tokens.css', 'tokens.dark.css' );
		foreach ( $sources as $file ) {
			$path  = $this->resources_dir . '/' . $file;
			$size  = is_readable( $path ) ? (string) filesize( $path ) : 'x';
			$hash .= ':' . $size;
		}
		return $hash;
	}
}
