<?php
/**
 * Base Cache Article
 *
 * A simplified caching mechanism that caches just the article element of the
 * page, not the whole page with menus and sidebars. Much easier. Includes
 * wpautop (adds paragraph tags) and do_shortcode to execute the shortcode
 * code, so that the results of the shortcode appear and not just the
 * shortcode.

 * @link http://wp.cbos.ca/plugins/wp-bundle-base-cache-article/
 * @author wp.cbos.ca http://wp.cbos.ca
 * @version 2018.07.01
 * @license GPLv2+
*/

defined( 'ABSPATH' ) || exit;

/** On save post, cache post */
add_action( 'save_post', 'wp_bundle_cache_content' );

/** On save page, cache page. See wp-includes/post.php:3570. */
//add_action( 'save_post_page', 'wp_bundle_cache_content' );

//add_action( 'post_updated', 'wp_bundle_cache_content' );

/** On Trash post, delete cached post|page */
add_action( 'trash_post', 'wp_bundle_cache_trash_post' );

/**
 * Cache the content. Overwrite is assumed.
 * 1. Get the post object.
 * 2. Need any directories.
 * 3. Make sure the directory in which to write exists
 * 4. Wrap the contents in proper HTML and add heading (title) and time stamp.
 * 5. Print the file to disk.
 * @param int $post_id
 * @return --
 */
function wp_bundle_cache_content( $post_id )
{
  if ( ! wp_is_post_revision( $post_id ) && ! wp_is_post_autosave( $post_id ) ) {
    //and not trash
    $post = get_post( $post_id );
    $slug = get_post_field( 'post_name', $post_id );
    if ( $path = wp_bundle_get_cache_path( $post_id, $post -> post_type, $slug ) ) {
      $file = $path . SITE_ARTICLE_FILE;

      $article = wp_bundle_content_wrap( $post );
      if ( $result = wp_bundle_cache_path_check( $path , $file ) ){
        $result = file_put_contents( $file, $article );
      }
    }
  }
}

/**
 * Wrap the content in proper HTML
 * Wrap the title in the h1 element (required).
 * Wrap the post paragraphs in the p element using wpautop.
 * Execute a shortcode (if it exists);
 * Include a time string (hidden) for reference purposes.
 * @since 2018.07.01
 * @param array $post
 * @return string
 */
function wp_bundle_content_wrap( $post )
{
  $str = '<article>' . PHP_EOL;
  $str .= sprintf( '<h1>%s</h1>%s', $post -> post_title, PHP_EOL );
  $html = wpautop( $post -> post_content );
  $str .= do_shortcode( $html, true );
  $str .= sprintf( '<!-- %s -->%s', current_time( 'Y-m-j H:m:s'), PHP_EOL );
  $str .= '</article>' . PHP_EOL;
  return $str;
}

/**
 * Get the cache path.
 *
 * @param str $slug
 * @return str
 */
function wp_bundle_get_cache_path( $post_id, $post_type, $slug )
{

  $path = '';
  if ( strpos( $slug, 'home' ) !== false || strpos( $slug, 'front-page' ) !== false ) {
    $path = SITE_CACHE_PATH;
  } elseif ( 'post' == $post_type ) {
    $page = SITE_CACHE_PATH . SITE_POST_DIR . '/' . $slug;
  } elseif ( 'page' == $post_type ){
    $path = SITE_CACHE_PATH . '/' . $slug;
  } else {}

  return $path;
}

/**
 * If the cache path does not exists, create it.
 * @param str $path
 * @parm str $file
 * @return bool
 */
function wp_bundle_cache_path_check( $path, $file ){
  if( $mkdir = wp_mkdir_p( $path ) ) {
    return true;
  }
  else {
    return false;
  }

}

/**
 * Delete Cached Pages and Posts
 * Needs some work.
 */
function wp_bundle_cache_trash_post( $post_id ) {
  if ( 0 ) {
    $post = get_post( $post_id );
    $slug = get_post_field( 'post_name', $post -> ID );
    if ( $path = wp_bundle_get_cache_path( $post -> ID, $slug ) ) {
      $file = $path . SITE_INDEX_FILE;

      if ( $result = wp_bundle_refresh_cache_path( $path, $file ) ){
        if ( $str = $post->contents ) {
          unlink( $file );
          unlink ( $path );
        }
      }
    }
  }
}

/**
 * A very simple logging function.
 * Assumes the existence of the indicated directory and file.
 */
if ( ! function_exists( 'wpb_log' ) ) {
  function wpb_log( $str ){
    if ( 1 ) {
      file_put_contents( __DIR__ . '/log/log.txt', $str . PHP_EOL );
    }
  }
}
