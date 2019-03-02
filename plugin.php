<?php
/**
 * Base Caching Mechanism
 *
 * This set of files operates as a basic multi-tiered caching mechanism. The
 * first tier is saving only the article part of the article (page or post) to
 * a file--by itself--without the rest of the page. This is because this part
 * is assumed to be the main part of the page that will likely not change or
 * change much over time. In contrast, the remainder of the page (menus,
 * sidebars, headers and footers, etc., may change, or may change a lot over
 * time. Since the page can be assembled from a few components more easily than
 * it can be assembled from many components (building the menu, for example,
 * takes a lot of lines of code just to accomplish that), it makes sense to
 * distill a page down to these fewer components and then assemble those. A
 * minimal framework has been developed to do just that. Then, once the page
 * has been assembled, it can be stored as a whole, which is the custom of most
 * caching plugins encountered so far.
 *
 * Plugin Name: WP Bundle Base Cache
 * Text Domain: base-cache
 * Description: A multi-tiered caching plugin.
 * Author: wp.cbos.ca
 * Author URI: http://wp.cbos.ca
 * License: GPLv3+  GNU General Public License (see directory for license copy).
 * Version: 1.4.0

 * Standards:
 * Coding: https://www.php-fig.org/psr/
 * Documentation: http://docs.phpdoc.org/guides/docblocks.html

 * Exceptions:
 *  Spaces: 2 (not 4) [Githubs standard is 2 spaces]
 *
 * Original Author: KeyCDN (https://www.keycdn.com)
 * Original License: GPLv2 or later
 * Original Version: 1.3.3

 * Copyright (C) 2019 Clarence J. Bos
 * Copyright (C) 2017 KeyCDN
 * Copyright (C) 2015 Sergej Müller

 * File: plugin.php
 * Created: 2019-02-25
 * Updated: 2019-03-02
 * Time: 07:27 EST
*/

/** No direct access. */
defined('ABSPATH') OR exit;

if ( ! defined ('SITE_ROOT_PATH' ) ) {
  /** The path to the root of the site. */
  define( 'SITE_ROOT_PATH', rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) );
}

if ( ! defined ('SITE_CACHE_DIR' ) ) {
  /** The directory in which the static cached files are stored. */
  define( 'SITE_CACHE_DIR', '/a' );
}

if ( ! defined( 'SITE_CACHE_PATH' ) ) {
  define( 'SITE_CACHE_PATH', SITE_ROOT_PATH . SITE_CACHE_DIR );
}

if ( ! defined ('SITE_PAGE_DIR' ) ) {
  /**  Default: '/article'.  Commonly referred to as 'post'. */
  define( 'SITE_PAGE_DIR', '/a' );
}

if ( ! defined ('SITE_ARTICLE_DIR' ) ) {
  /**  Default: '/article'.  Commonly referred to as 'post'. */
  define( 'SITE_ARTICLE_DIR', '/a' );
}

if ( ! defined ('SITE_POST_DIR' ) ) {
  /** The directory in which the cached files are printed. */
  define( 'SITE_POST_DIR', '/news' );
}

if ( ! defined( 'SITE_INDEX_FILE' ) ) {
  define( 'SITE_INDEX_FILE', '/index.html' );
}

if ( ! defined( 'SITE_DEFAULT_FILE' ) ) {
  define( 'SITE_DEFAULT_FILE', '/default.html' );
}

if ( ! defined( 'SITE_ARTICLE_FILE' ) ) {
  define( 'SITE_ARTICLE_FILE', '/article.html' );
}

// constants
define('BC_FILE', __FILE__);
define('BC_PATH', __DIR__);
define('BC_PLUGIN_BASE', plugin_basename(__FILE__));

require_once( __DIR__ . '/inc' . '/base-cache-article.php' );

// hooks
add_action(
    'plugins_loaded',
    array(
        'BaseCacheCore',
        'instance'
    )
);
register_activation_hook(
    __FILE__,
    array(
        'BaseCacheCore',
        'onActivation'
    )
);
register_deactivation_hook(
    __FILE__,
    array(
        'BaseCacheCore',
        'onDeactivation'
    )
);
register_uninstall_hook(
    __FILE__,
    array(
        'BaseCacheCore',
        'onUninstall'
    )
);

// autoload register
spl_autoload_register('base_cache_autoload');

/**
 * Base Cache Autoload
 *
 * @param array $class [description]
 * @return void
 */
function base_cache_autoload($class) {
  if ( in_array($class, array('BaseCacheCore', 'BaseCacheDisk') ) ) {
    $arr = [
      'BaseCacheCore' => 'base-cache-core',
      'BaseCacheDisk' => 'base-cache-disk',
    ];
    $file = sprintf(
      '%s/inc/class-%s.php',
      BC_PATH,
      $arr[$class]
    );
    if( file_exists( $file ) ) {
      require_once( $file );
    }
  }
}
