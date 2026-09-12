<?php
/**
 * Managed standalone tailwindcss binary (canonical CSS engine).
 *
 * Pinned version; downloads verified against official release checksums.
 *
 * @package Blocky\Core\Compiler
 */

declare( strict_types=1 );

namespace Blocky\Core\Compiler;

/**
 * Locates, downloads, verifies and executes the pinned tailwindcss CLI.
 */
final class TailwindBinary {

	/**
	 * Engine version pinned across studio, preview and frontend builds.
	 *
	 * @var string
	 */
	public const VERSION = '4.3.3';

	/**
	 * Official release download base.
	 *
	 * @var string
	 */
	private const RELEASE_BASE = 'https://github.com/tailwindlabs/tailwindcss/releases/download';

	/**
	 * Official release asset checksums (sha256sums.txt of the pinned tag).
	 *
	 * @var array<string,string>
	 */
	private const CHECKSUMS = array(
		'tailwindcss-linux-arm64'      => '55fd0b241214eff3de1e8ee4f22796662f2d2e7a49bcfca7477cfd0bac398195',
		'tailwindcss-linux-arm64-musl' => '71ea4be79c9de9827545682df3e040053fb535d37c71ed2cfdedf9385a0868e0',
		'tailwindcss-linux-x64'        => 'dc61b3ac6b8c9ca874c0cc4c57b2409791a64c5540404ca5f5367360babc313a',
		'tailwindcss-linux-x64-musl'   => 'a04d34ceacc8f52cbe8920ad846cdeb61d3d0021dba32db0d1f77c9d9fad7a6c',
		'tailwindcss-macos-arm64'      => 'cdf646702987a743464dff4d9c60fd4480d1c1e73dd819a9a67f1078815dce9d',
		'tailwindcss-macos-x64'        => '7922e0953f2110c05976e3bf58f14e643d90427575e766b7d433f5f80cbee7e1',
		'tailwindcss-windows-x64.exe'  => 'e0e260ce048014e9268f6237ff18f8ccf02cef521cbd0ae04e82c2cdf7aa3955',
	);

	/**
	 * Shell runner seam (tests inject a fake).
	 *
	 * @var callable( string, array<int,string> ):int
	 */
	private $runner;

	/**
	 * Directory holding managed binaries.
	 *
	 * @var string
	 */
	private string $bin_dir;

	/**
	 * Constructor.
	 *
	 * @param string        $bin_dir Directory where the binary is cached.
	 * @param callable|null $runner  Optional shell runner: fn( string $cmd, array $out ): int.
	 */
	public function __construct( string $bin_dir, ?callable $runner = null ) {
		$this->bin_dir = $bin_dir;
		$this->runner  = $runner ?? static function ( string $cmd, array &$out ): int {
			$status = 1;
			@exec( $cmd, $out, $status ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- CSS compiler runtime, availability checked via exec_available().
			return $status;
		};
	}

	/**
	 * Official checksum for a release asset.
	 *
	 * @param string $target Asset name.
	 * @return string|null Hex sha256 or null when the asset is unknown.
	 */
	public static function expected_hash( string $target ): ?string {
		return self::CHECKSUMS[ $target ] ?? null;
	}

	/**
	 * Download URL for a release asset.
	 *
	 * @param string $target Asset name.
	 * @return string Absolute URL pinned to VERSION.
	 */
	public static function download_url( string $target ): string {
		return self::RELEASE_BASE . '/v' . self::VERSION . '/' . $target;
	}

	/**
	 * Release asset name for the current OS/architecture.
	 *
	 * @return string|null Asset name or null on unsupported platforms.
	 */
	public static function detect_target(): ?string {
		$os    = strtolower( PHP_OS_FAMILY );
		$uname = strtolower( (string) php_uname( 'm' ) );
		$arch  = '' === $uname ? '' : $uname;

		$arch = match ( true ) {
			in_array( $arch, array( 'x86_64', 'amd64' ), true )  => 'x64',
			in_array( $arch, array( 'aarch64', 'arm64' ), true ) => 'arm64',
			default                                              => $arch,
		};

		return match ( true ) {
			'linux' === $os && 'x64' === $arch    => 'tailwindcss-linux-x64',
			'linux' === $os && 'arm64' === $arch  => 'tailwindcss-linux-arm64',
			'darwin' === $os && 'x64' === $arch   => 'tailwindcss-macos-x64',
			'darwin' === $os && 'arm64' === $arch => 'tailwindcss-macos-arm64',
			'windows' === $os && 'x64' === $arch  => 'tailwindcss-windows-x64.exe',
			default                              => null,
		};
	}

	/**
	 * Make sure a verified binary exists locally, downloading when needed.
	 *
	 * @param string                          $target  Release asset name.
	 * @param callable( string, string ):bool $fetcher Downloads url into path; true on success.
	 * @return bool True when a checksum-verified binary is present afterwards.
	 */
	public function ensure( string $target, callable $fetcher ): bool {
		if ( $this->is_verified( $target ) ) {
			return true;
		}

		$url  = self::download_url( $target );
		$path = $this->binary_path( $target );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- compiler cache dir in uploads.
		$made_dir = is_dir( $this->bin_dir ) || mkdir( $this->bin_dir, 0777, true ) || is_dir( $this->bin_dir );
		if ( ! $made_dir ) {
			return false;
		}

		$fetched  = $fetcher( $url, $path );
		$verified = $this->is_verified( $target );
		if ( false === $fetched || false === $verified ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- managed cache cleanup.
			return false;
		}

		@chmod( $path, 0755 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- best-effort executable bit.
		return true;
	}

	/**
	 * Local path of the managed binary (may not exist yet).
	 *
	 * @param string $target Release asset name.
	 * @return string Absolute path.
	 */
	public function binary_path( string $target ): string {
		return rtrim( $this->bin_dir, '/\\' ) . DIRECTORY_SEPARATOR . $target;
	}

	/**
	 * Whether the cached binary exists and matches the official checksum.
	 *
	 * @param string $target Release asset name.
	 * @return bool Verification state.
	 *
	 * @phpstan-impure
	 */
	public function is_verified( string $target ): bool {
		$path     = $this->binary_path( $target );
		$expected = self::expected_hash( $target );

		if ( null === $expected || ! is_readable( $path ) ) {
			return false;
		}

		$actual = hash_file( 'sha256', $path );
		return is_string( $actual ) && hash_equals( $expected, $actual );
	}

	/**
	 * Whether exec() is usable in this SAPI.
	 *
	 * @return bool Availability flag.
	 */
	public static function exec_available(): bool {
		if ( ! function_exists( 'exec' ) || ! is_callable( 'exec' ) ) {
			return false;
		}

		$disabled_raw = (string) ini_get( 'disable_functions' );
		$disabled     = array_map( 'trim', explode( ',', $disabled_raw ) );
		return ! in_array( 'exec', $disabled, true );
	}

	/**
	 * Compile an entry file to a minified output file.
	 *
	 * @param string $target      Release asset name of the cached binary.
	 * @param string $entry_file  Absolute path of the CSS entry.
	 * @param string $output_file Absolute path of the compiled artifact.
	 * @return bool True when the compile succeeded and produced output.
	 */
	public function compile( string $target, string $entry_file, string $output_file ): bool {
		if ( ! self::exec_available() || ! $this->is_verified( $target ) ) {
			return false;
		}

		$debug   = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$windows = defined( 'PHP_WINDOWS_VERSION_MAJOR' );
		$stderr  = $debug ? 'php://stderr' : ( $windows ? 'NUL' : '/dev/null' );
		$cmd     = sprintf(
			'%s -i %s -o %s --minify 2> %s',
			escapeshellarg( $this->binary_path( $target ) ),
			escapeshellarg( $entry_file ),
			escapeshellarg( $output_file ),
			$stderr
		);

		$out      = array();
		$status   = ( $this->runner )( $cmd, $out );
		$produced = is_readable( $output_file );
		$size     = $produced ? (int) filesize( $output_file ) : 0;

		return 0 === $status && $produced && $size > 0;
	}
}
