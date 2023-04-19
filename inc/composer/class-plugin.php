<?php
/**
 * Altis CMS Installer.
 *
 * @package altis/cms-installer
 */

namespace Altis\CMS\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Altis CMS Installer plugin class.
 */
class Plugin implements PluginInterface, EventSubscriberInterface {

	/**
	 * The composer instance.
	 *
	 * @var Composer
	 */
	protected $composer;

	/**
	 * Activate is not used, but is part of the abstract class.
	 *
	 * @param Composer $composer The composer instance.
	 * @param IOInterface $io The IO instance.
	 */
	public function activate( Composer $composer, IOInterface $io ) {
		$this->composer = $composer;
	}

	/**
	 * Register the composer events we want to run on.
	 *
	 * @return array
	 */
	public static function getSubscribedEvents() : array {
		return [
			'post-update-cmd' => [ 'install_files' ],
			'post-install-cmd' => [ 'install_files' ],
			'post-autoload-dump' => [ 'generate_module_manifest' ],
		];
	}

	/**
	 * Install additional files to the project on update / install
	 */
	public function install_files() {
		$source = $this->composer->getConfig()->get( 'vendor-dir' ) . '/altis/cms';
		$dest   = dirname( $this->composer->getConfig()->get( 'vendor-dir' ) );

		copy( $source . '/index.php', $dest . '/index.php' );
		copy( $source . '/wp-config.php', $dest . '/wp-config.php' );

		// Copy build script file if one doesn't exist.
		if ( ! file_exists( $dest . '/.build-script' ) ) {
			copy( $source . '/.build-script', $dest . '/.build-script' );
		}

		// Update the .gitignore to include the wp-config.php, WordPress, the index.php
		// as these files should not be included in VCS.
		if ( ! file_exists( $dest . '/.gitignore' ) ) {
			$entries = [
				'# Altis',
				'/wordpress',
				'/index.php',
				'/wp-config.php',
				'/chassis',
				'/vendor',
				'/content/uploads',
			];
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			file_put_contents( $dest . '/.gitignore', implode( "\n", $entries ) );
		}

		if ( ! is_dir( $dest . '/content' ) ) {
			mkdir( $dest . '/content' );
		}
		if ( ! is_dir( $dest . '/content/plugins' ) ) {
			mkdir( $dest . '/content/plugins' );
		}
		if ( ! is_dir( $dest . '/content/themes' ) ) {
			mkdir( $dest . '/content/themes' );
		}
	}

	/**
	 * Generate the manifest of module entrypoints to be included automatically.
	 */
	public function generate_module_manifest() {
		$repository = $this->composer->getRepositoryManager()->getLocalRepository();
		$packages = $repository->getCanonicalPackages();
		$vendor_dir = $this->composer->getConfig()->get( 'vendor-dir' );
		$module_loader = "<?php\n/**\n * Altis Module Loader.\n *\n * DO NOT EDIT THIS FILE.\n */\n";

		foreach ( $packages as $package ) {
			$extra = $package->getExtra();

			// Only process Altis packages.
			if ( ! isset( $extra['altis'] ) ) {
				continue;
			}

			// Determine absolute file path.
			// Note: we use / instead of the platform-dependent directory
			// separator as it needs to work cross-platform (e.g. Windows host,
			// Linux VM).
			$default_base = $vendor_dir . '/' . str_replace( DIRECTORY_SEPARATOR, '/', $package->getName() );
			$base = $package->getTargetDir() ?? $default_base;
			$file = $base . '/' . 'load.php';

			if ( ! file_exists( $file ) ) {
				continue;
			}

			// Make the path relative to work across environments.
			$file = str_replace( $vendor_dir, '', $file );

			// Add the require line to the file.
			// ~~DIR~~ is used because composer plugin files are eval'd during an
			// install action and directory and file path constants get replaced.
			$module_loader .= "\n// Load {$package->getName()}.\nrequire_once ~~DIR~~ . '{$file}';";
		}

		// Get custom module entrypoints.
		$root_extra = $this->composer->getPackage()->getExtra();

		if ( isset( $root_extra['altis']['modules'] ) ) {
			foreach ( $root_extra['altis']['modules'] as $module => $config ) {
				if ( ! isset( $config['entrypoint'] ) ) {
					continue;
				}

				$files = array_filter( (array) $config['entrypoint'], 'file_exists' );
				$files = array_map( function ( $file ) {
					return "require_once dirname( ~~DIR~~ ) . DIRECTORY_SEPARATOR . '{$file}';";
				}, $files );
				$files = implode( "\n", $files );

				// Add the require line to the file.
				$module_loader .= "\n// Load {$module}.\n{$files}";
			}
		}

		// Replace ~~DIR~~ with __DIR__.
		$module_loader = str_replace( '~~', '__', $module_loader );

		// Write the loader file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		file_put_contents( $vendor_dir . DIRECTORY_SEPARATOR . 'modules.php', "{$module_loader}\n" );
	}

	/**
	 * Deactivate is not used, but is part of the abstract class.
	 *
	 * @param Composer $composer The composer instance.
	 * @param IOInterface $io The IO instance.
	 */
	public function deactivate( Composer $composer, IOInterface $io ) {
	}

	/**
	 * Uninstall is not used, but is part of the abstract class.
	 *
	 * @param Composer $composer The composer instance.
	 * @param IOInterface $io The IO instance.
	 */
	public function uninstall( Composer $composer, IOInterface $io ) {
	}
}
