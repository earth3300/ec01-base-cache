<?php
/*
Plugin Name: WP Bundle Base Cache
Text Domain: base-cache
Description: This has been modified from the Cache Enabler plugin to cache files in a directory only one level down from the root. It currently retains all other functionality. Requires bundle configuration constants to work.
Author: wp.cbos.ca
Author URI: http://wp.cbos.ca
License: GPLv3+
Version: 1.4.0

Standards:
 Coding: https://www.php-fig.org/psr/
 Documentation: http://docs.phpdoc.org/guides/docblocks.html

Exceptions:
 Spaces: 2 (not 4) [Githubs standard is 2 spaces]

Note: credits and text may need to be updated to conform to standards.
*/

/*
Original Author: KeyCDN
Original Author URI: https://www.keycdn.com
Original License: GPLv2 or later
Original Version: 1.3.3
*/

/*
Copyright (C) 2019 Clarence J. Bos
Copyright (C) 2017 KeyCDN
Copyright (C) 2015 Sergej Müller

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

/** No direct access. */
defined('ABSPATH') OR exit;

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
