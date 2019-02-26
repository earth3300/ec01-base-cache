<?php
/**
 * Base Cache Disk
 *
 * Standards:
 * Coding: https://www.php-fig.org/psr/
 * Documentation: http://docs.phpdoc.org/guides/docblocks.html
 *
 * Exceptions:
 * Spaces: 2 (not 4) [Github standard is 2 spaces]
 *
 * File: class-basecachedisk.php
 * Created: 2019-02-24
 * Updated: 2019-02-25
 * Time: 08:27 EST
 */
// namespace Vendor\Package;

/** No direct access. */
defined('ABSPATH') OR exit;

/**
 * Cache Enabler Disk
 *
 * Description here.
 *
 * @since 1.0.0
 */
final class BaseCacheDisk
{
  /**
   * Cached Filename Settings
   *
   * @since  1.0.7
   * @change 1.0.7
   *
   * @var    string
   */
  const FILE_HTML = 'index.html';

  /**
   * Permalink Check
   *
   * @since   1.0.0
   * @change  1.0.0
   *
   * @return  boolean  true if installed
   */
  public static function isPermalink()
  {
      return get_option('permalink_structure');
  }

  /**
   * Store Asset
   *
   * @since   1.0.0
   * @change  1.0.0
   *
   * @param   string   $data    content of the asset
   */
  public static function storeAsset($data)
  {
    // check if empty
    if ( empty($data) ) {
      wp_die('Asset is empty.');
    }

    // save asset
    self::_create_files(
      $data
    );
  }

  /**
   * Check Asset
   *
   * @since   1.0.0
   * @change  1.0.0
   *
   * @return  boolean  true if asset exists
   */
  public static function checkAsset()
  {
    return is_readable(
      self::fileHtml()
    );
  }

  /**
   * Check Expiry
   *
   * @since   1.0.1
   * @change  1.0.1
   *
   * @return  boolean  true if asset expired
   */
  public static function checkExpiry()
  {
    // cache enabler options
    $options = BaseCacheCore::$options;

    // check if expires is active
    if ($options['expires'] == 0) {
      return false;
    }

    $now = time();
    $expires_seconds = 3600*$options['expires'];

    // check if asset has expired
    if ( ( filemtime(self::_file_html()) + $expires_seconds ) <= $now ) {
      return true;
    }

    return false;
  }

  /**
   * Delete Asset
   *
   * @since   1.0.0
   * @change  1.0.0
   *
   * @param   string   $url   url of cached asset
   */
  public static function deleteAsset($url)
  {
      // check if url empty
      if ( empty($url) ) {
          wp_die('URL is empty.');
      }

      // delete
      self::_clear_dir(
          self::_file_path($url)
      );
  }

  /**
   * Clear Cache
   *
   * @since   1.0.0
   * @change  1.0.0
   */
  public static function clearCache()
  {
    if ( 0 ) {
      self::_clear_dir( CE_CACHE_DIR );
    }
  }

  /**
   * Clear Home Cache
   *
   * @since   1.0.7
   * @change  1.4.0
   */
  public static function clearHome()
  {
    if ( 0 ) {
      $path = SITE_CACHE_PATH;
      @unlink($path.self::FILE_HTML);
    }
  }

  /**
   * Get Asset
   *
   * @since   1.0.0
   * @change  1.0.9
   */
  public static function getAsset()
  {
    // set cache handler header
    header('x-cache-handler: php');

    // get if-modified request headers
    if ( function_exists( 'apache_request_headers' ) ) {
      $headers = apache_request_headers();
      $http_if_modified_since = ( isset( $headers[ 'If-Modified-Since' ] ) ) ? $headers[ 'If-Modified-Since' ] : '';
      $http_accept = ( isset( $headers[ 'Accept' ] ) ) ? $headers[ 'Accept' ] : '';
      $http_accept_encoding = ( isset( $headers[ 'Accept-Encoding' ] ) ) ? $headers[ 'Accept-Encoding' ] : '';
    } else {
      $http_if_modified_since = ( isset( $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] ) ) ? $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] : '';
      $http_accept = ( isset( $_SERVER[ 'HTTP_ACCEPT' ] ) ) ? $_SERVER[ 'HTTP_ACCEPT' ] : '';
      $http_accept_encoding = ( isset( $_SERVER[ 'HTTP_ACCEPT_ENCODING' ] ) ) ? $_SERVER[ 'HTTP_ACCEPT_ENCODING' ] : '';
    }

    // check modified since with cached file and return 304 if no difference
    if ( $http_if_modified_since && ( strtotime( $http_if_modified_since ) >= filemtime( self::_file_html() ) ) ) {
      header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304 );
      exit;
    }

    // check webp and deliver gzip webp file if support
    if ( $http_accept && ( strpos($http_accept, 'webp') !== false ) ) {
      if ( is_readable( self::_file_webp_gzip() ) ) {
        header('Content-Encoding: gzip');
        readfile( self::_file_webp_gzip() );
        exit;
    } elseif ( is_readable( self::_file_webp_html() ) ) {
        readfile( self::_file_webp_html() );
        exit;
      }
    }

    // check encoding and deliver gzip file if support
    if ( $http_accept_encoding && ( strpos($http_accept_encoding, 'gzip') !== false ) && is_readable( self::_file_gzip() )  ) {
      header('Content-Encoding: gzip');
      readfile( self::_file_gzip() );
      exit;
    }

    // deliver cached file (default)
    readfile( self::_file_html() );
    exit;
  }

  /**
   * Create Files
   *
   * @since   1.0.0
   * @change  1.1.1
   *
   * @param   string  $data  html content
   */
  private static function createFiles($data)
  {
    // create folder
    if ( ! wp_mkdir_p( self::_file_path() ) ) {
        wp_die('Unable to create directory.');
    }

    // cache enabler options
    $options = BaseCacheCore::$options;
    self::_create_file( self::_file_html(), $data );
    /*
    // create files
    self::_create_filex( self::_file_html(), $data.$cache_signature." (html) -->" );
    */
  }

  /**
   * Create File
   *
   * @since   1.0.0
   * @change  1.0.0
   *
   * @param   string  $file  file path
   * @param   string  $data  content of the html
   */
  private static function createFile($file, $data)
  {
    // open file handler
    if ( ! $handle = @fopen($file, 'wb') ) {
      wp_die('Can not write to file.');
    }

    // write
    @fwrite($handle, $data);
    fclose($handle);
    clearstatcache();

    // set permissions
    $stat = @stat( dirname($file) );
    $perms = $stat['mode'] & 0007777;
    $perms = $perms & 0000666;
    @chmod($file, $perms);
    clearstatcache();
  }

  /**
   * Clear Directory
   *
   * @since   1.0.0
   * @change  1.4.0
   *
   * @param   string  $dir  directory
   */
  private static function clearDir($dir)
  {
    // remove slashes
    $dir = untrailingslashit($dir);

    // check if dir
    if ( ! is_dir($dir) ) {
      return;
    }

    // get dir data
    $objects = array_diff(
      scandir($dir),
      array('..', '.')
    );

    if ( empty($objects) ) {
      return;
    }

    foreach ( $objects as $object ) {
      // full path
      $object = $dir. DIRECTORY_SEPARATOR .$object;

      // check if directory
      if ( is_dir($object) ) {
        self::_clear_dir($object);
      } else {
        unlink($object);
      }
    }

    // delete
    @rmdir($dir);

    // clears file status cache
    clearstatcache();
  }

  /**
   * Get Cache Size
   *
   * @since   1.0.0
   * @change  1.0.0
   *
   * @param   string  $dir   folder path
   * @return  mixed   $size  size in bytes
   */
  public static function cacheSize($dir = '.')
  {
    // check if not dir
    if ( ! is_dir($dir) ) {
      return;
    }

    // get dir data
    $objects = array_diff(
      scandir($dir),
      array('..', '.')
    );

    if ( empty($objects) ) {
      return;
    }

    $size = 0;

    foreach ( $objects as $object ) {
      // full path
      $object = $dir. DIRECTORY_SEPARATOR .$object;

      // check if dir
      if ( is_dir($object) ) {
        $size += self::cacheSize($object);
      } else {
        $size += filesize($object);
      }
  }
    return $size;
  }

  /**
   * Cache Path
   *
   * Adjusted file path generator. Slightly different from the one
   * in advanced-cache.php
   *
   * @since   1.0.0
   * @change  1.4.0
   *
   * @param   string  $path  uri or permlink
   * @return  string  $diff  path to cached asset
   */
  private static function filePath($path = NULL)
  {
    $url = parse_url( $_SERVER['REQUEST_URI'] );

    $page = isset( $url['path'] ) ? $url['path'] : '';

    $path = sprintf( '%s/%s', SITE_CACHE_PATH, $page );

    if ( is_file($path) > 0 ) {
      header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404 );
      exit;
    }

    //remove double slashes
    $path = str_replace( '//', '/', $path );

    //remove double /a/a
    $path = str_replace( '/a/a', '/a', $path );

    if ( is_file($path) > 0 ) {
      wp_die('Path is not valid.');
    }

    // add trailing slash
    $path = rtrim( $path, '/\\' ) . '/';

    return $path;
  }

  /**
   * Get the Path to the File
   *
   * @since   1.0.0
   * @change  1.0.7
   *
   * @return string path to the html file
   */
  private static function fileHtml()
  {
      return self::filePath(). self::FILE_HTML;
  }
}
