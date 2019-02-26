<?php
/**
 * File Level Description
 *
 * Standards:
 *  Coding: https://www.php-fig.org/psr/
 *  Documentation: http://docs.phpdoc.org/guides/docblocks.html
 *
 * Exceptions:
 *  Spaces: 2 (not 4) [Githubs standard is 2 spaces]
 *
 * File: class-basecache.php
 * Created: 2019-02-24
 * Updated: 2019-02-25
 * Time: 11:03 EST
 */

/** No direct access. */
defined('ABSPATH') OR exit;

/**
 * Base Cache
 *
 * @since 1.4.0
 */
class BaseCacheCore
{
  /**
   * plugin options
   *
   * @since  1.0.0
   * @var  array
   */
  public static $options;

  /**
   * disk cache object
   *
   * @since  1.0.0
   * @var  object
   */
  private static $disk;

  /**
   * minify default settings
   *
   * @since  1.0.0
   * @var  integer
   */
  const MINIFY_DISABLED = 0;
  const MINIFY_HTML_ONLY = 1;
  const MINIFY_HTML_JS = 2;

  /**
   * constructor wrapper
   *
   * @since   1.0.0
   * @change  1.0.0
   */
  public static function instance()
  {
    new self();
  }

  /**
   * constructor
   *
   * @since   1.0.0
   * @change  1.2.3
   *
   * @param   void
   * @return  void
   */
  public function __construct()
  {
    // set default vars
    self::setDefaultVars();

    // register publish hook
    add_action(
      'init',
      array(
        __CLASS__,
        'registerPublishHooks'
      ),
      99
    );

    // clear cache hooks
    add_action(
      'ce_clear_post_cache',
      array(
        __CLASS__,
        'clearPageCacheByPostId'
      )
    );

    add_action(
      'ce_clear_cache',
      array(
        __CLASS__,
        'clearCacheAll'
      )
    );

    add_action(
      'switch_theme',
      array(
        __CLASS__,
        'clear_total_cache'
      )
    );

    add_action(
      'autoptimize_action_cachepurged',
      array(
        __CLASS__,
        'clearCacheAll'
      )
    );

    add_action(
      'upgrader_process_complete',
      array(
        __CLASS__,
        'onUpgradeHook'
      ), 10, 2);

    // act on woocommerce actions
    add_action(
      'woocommerce_product_set_stock',
      array(
        __CLASS__,
        'woocommerceProductSetStock',
      ),
      10,
      1
    );

    add_action(
      'woocommerce_product_set_stock_status',
      array(
        __CLASS__,
        'woocommerceProductSetStockStatus',
      ),
      10,
      1
    );

    add_action(
      'woocommerce_variation_set_stock',
      array(
        __CLASS__,
        'woocommerceProductSetStock',
      ),
      10,
      1
    );

    add_action(
      'woocommerce_variation_set_stock_status',
      array(
        __CLASS__,
        'woocommerceProductSetStockStatus',
      ),
      10,
      1
    );

    // add admin clear link
    add_action(
      'admin_bar_menu',
      array(
        __CLASS__,
        'addAdminLinks'
      ),
      90
    );

    add_action(
      'init',
      array(
        __CLASS__,
        'processClearRequest'
      )
    );

    if ( !is_admin() ) {
      add_action(
        'admin_bar_menu',
        array(
          __CLASS__,
          'registerTextdomain'
        )
      );
    }

    // admin
    if ( is_admin() ) {

      add_action(
        'admin_init',
        array(
          __CLASS__,
          'registerTextdomain'
        )
      );

      add_action(
        'admin_init',
        array(
          __CLASS__,
          'registerSettings'
        )
      );

      add_action(
        'admin_menu',
        array(
          __CLASS__,
          'addSettingsPage'
        )
      );

      add_action(
        'admin_enqueue_scripts',
        array(
          __CLASS__,
          'addAdminResources'
        )
      );

      add_filter(
        'dashboard_glance_items',
        array(
          __CLASS__,
          'addDashboardCount'
        )
      );

      add_action(
        'post_submitbox_misc_actions',
        array(
          __CLASS__,
          'addClearDropdown'
        )
      );

      add_filter(
        'plugin_row_meta',
        array(
          __CLASS__,
          'rowMeta'
        ),
        10,
        2
      );

      add_filter(
        'plugin_action_links_' . BC_PLUGIN_BASE,
        array(
          __CLASS__,
          'actionLinks'
        )
      );

      // warnings and notices
      add_action(
        'admin_notices',
        array(
          __CLASS__,
          'warningIsPermalink'
        )
      );

      add_action(
        'admin_notices',
        array(
          __CLASS__,
          'requirementsCheck'
        )
      );

    // caching
    } else {

      add_action(
        'template_redirect',
        array(
          __CLASS__,
          'handleCache'
        ),
        0
      );
    }
  }

  /**
   * deactivation hook
   *
   * @since   1.0.0
   * @change  1.1.1
   */
  public static function onDeactivation()
  {
    self::clearCacheAll(true);

    if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
      // unset WP_CACHE
      self::_set_wp_cache(false);
    }

    // delete advanced cache file
    unlink( WP_CONTENT_DIR . '/advanced-cache.php');
  }

  /**
   * Activation Hook
   *
   * @since   1.0.0
   * @change  1.4.0
   */
  public static function onActivation()
  {
    // multisite and network
    if ( is_multisite() && ! empty($_GET['networkwide']) ){
      // blog ids
      $ids = self::_get_blog_ids();

      // switch to blog
      foreach ($ids as $id){
        switch_to_blog($id);
        self::installBackend();
      }

      // restore blog
      restore_current_blog();

    } else {
      self::installBackend();
    }

    if ( ! defined( 'WP_CACHE' ) || ! WP_CACHE ) {
      // set WP_CACHE
      self::_set_wp_cache(true);
    }

    // copy advanced cache file
    copy(CE_DIR . '/advanced-cache.php', WP_CONTENT_DIR . '/advanced-cache.php');
  }
  /**
   * installation options
   *
   * @since   1.0.0
   * @change  1.0.0
   */
  private static function installBackend()
  {
    add_option(
      'base-cache',
      array()
    );

    // clear
    self::clearCacheAll(true);
  }

  /**
   * uninstall
   *
   * @since   1.0.0
   * @change  1.0.0
   */
  private static function uninstallBackend()
  {
    // delete options
    delete_option('base-cache');
  }

  /**
   * set default vars
   *
   * @since   1.0.0
   * @change  1.0.0
   */
  private static function setDefaultVars()
  {
    // get options
    self::$options = self::getOptions();

    // disk cache
    if ( BaseCacheDisk::isPermalink() ) {
      self::$disk = new BaseCacheDisk;
    }
  }

  /**
   * get options
   *
   * @since   1.0.0
   * @change  1.2.3
   *
   * @return  array  options array
   */
  protected static function getOptions()
  {
    // decom
    $ce_leg = get_option('cache');
    if (!empty($ce_leg)) {
      delete_option('cache');
      add_option(
        'base-cache',
        $ce_leg
      );
    }

    return wp_parse_args(
      get_option('base-cache'),
      array(
        'expires'       => 0,
        'new_post'      => 0,
        'new_comment'     => 0,
        'compress'      => 0,
        'webp'        => 0,
        'clear_on_upgrade'  => 0,
        'excl_ids'      => '',
        'excl_regexp'     => '',
        'excl_cookies'    => '',
        'incl_attributes'   => '',
        'minify_html'     => self::MINIFY_DISABLED,
      )
    );
  }

  /**
   * warning if no custom permlinks
   *
   * @since   1.0.0
   * @change  1.0.0
   *
   * @return  array  options array
   */
  public static function warningIsPermalink()
  {
    if ( ! BaseCacheDisk::isPermalink() && current_user_can('manage_options') ) {
    ?>
      <div class="error">
        <p><?php printf( __('The <b>%s</b> plugin requires a custom permalink structure to start caching properly. Please go to <a href="%s">Permalink</a> to enable it.', 'base-cache'), 'Cache Enabler', admin_url( 'options-permalink.php' ) ); ?></p>
      </div>
      <?php
    }
  }

  /**
   * add action links
   *
   * @since   1.0.0
   * @change  1.0.0
   *
   * @param   array  $data  existing links
   * @return  array  $data  appended links
   */
  public static function actionLinks($data)
  {
    // check user role
    if ( ! current_user_can('manage_options') ) {
      return $data;
    }

    return array_merge(
      $data,
      array(
        sprintf(
          '<a href="%s">%s</a>',
          add_query_arg(
            array(
              'page' => 'base-cache'
            ),
            admin_url('options-general.php')
          ),
          esc_html__('Settings')
        )
      )
    );
  }

  /**
   * Base Cache Meta Links
   *
   * @since   1.0.0
   * @change  1.0.0
   *
   * @param   array   $input  existing links
   * @param   string  $page   page
   * @return  array   $data   appended links
   */
  public static function rowMeta($input, $page)
  {
    // check permissions
    if ( $page != BC_PLUGIN_BASE ) {
      return $input;
    }

    return array_merge(
      $input,
      array(
        '<a href="https://www.keycdn.com/support/wordpress-base-cache-plugin/" target="_blank">Support Page</a>',
      )
    );
  }

  /**
   * add dashboard cache size count
   *
   * @since   1.0.0
   * @change  1.1.0
   *
   * @param   array  $items  initial array with dashboard items
   * @return  array  $items  merged array with dashboard items
   */
  public static function addDashboardCount( $items = array() )
  {
    // check user role
    if ( ! current_user_can('manage_options') ) {
      return $items;
    }

    // get cache size
    $size = self::getCacheSize();

    // display items
    $items[] = sprintf(
      '<a href="%s" title="%s">%s %s</a>',
      add_query_arg(
        array(
          'page' => 'base-cache'
        ),
        admin_url('options-general.php')
      ),
      esc_html__('Disk Cache', 'base-cache'),
      ( empty($size) ? esc_html__('Empty', 'base-cache') : size_format($size) ),
      esc_html__('Cache Size', 'base-cache')
    );

    return $items;
  }

  /**
   * get cache size
   *
   * @since   1.0.0
   * @change  1.0.0
   *
   * @param   integer  $size  cache size (bytes)
   */
  public static function getCacheSize()
  {

    if ( ! $size = get_transient('cache_size') ) {

      $size = is_object( self::$disk ) ? (int) self::$disk->cacheSize(SITE_CACHE_PATH) : 0;

      // set transient
      set_transient(
        'cache_size',
        $size,
        60 * 15
      );
    }

    return $size;
  }

  /**
   * add admin links
   *
   * @since   1.0.0
   * @change  1.1.0
   *
   * @hook  mixed
   *
   * @param   object  menu properties
   */
  public static function addAdminLinks($wp_admin_bar)
  {
    // check user role
    if ( ! is_admin_bar_showing() OR ! apply_filters('user_can_clear_cache', current_user_can('manage_options')) ) {
      return;
    }

    // add admin purge link
    $wp_admin_bar->add_menu(
      array(
        'id'    => 'clear-cache',
        'href'   => wp_nonce_url( add_query_arg('_cache', 'clear'), '_cache__clear_nonce'),
        'parent' => 'top-secondary',
        'title'   => '<span class="ab-item">'.esc_html__('Clear Cache', 'base-cache').'</span>',
        'meta'   => array( 'title' => esc_html__('Clear Cache', 'base-cache') )
      )
    );

    if ( ! is_admin() ) {
      // add admin purge link
      $wp_admin_bar->add_menu(
        array(
          'id'    => 'clear-url-cache',
          'href'   => wp_nonce_url( add_query_arg('_cache', 'clearurl'), '_cache__clear_nonce'),
          'parent' => 'top-secondary',
          'title'   => '<span class="ab-item">'.esc_html__('Clear URL Cache', 'base-cache').'</span>',
          'meta'   => array( 'title' => esc_html__('Clear URL Cache', 'base-cache') )
        )
      );
    }
  }

  /**
   * Process Clear Request
   *
   * @since   1.0.0
   * @change  1.1.0
   *
   * @param   array  $data  array of metadata
   */
  public static function processClearRequest($data)
  {
    // check if clear request
    if ( empty($_GET['_cache']) OR ( $_GET['_cache'] !== 'clear' && $_GET['_cache'] !== 'clearurl' ) ) {
      return;
    }

    // validate nonce
    if ( empty($_GET['_wpnonce']) OR ! wp_verify_nonce($_GET['_wpnonce'], '_cache__clear_nonce') ) {
      return;
    }

    // check user role
    if ( ! is_admin_bar_showing() OR ! apply_filters('user_can_clear_cache', current_user_can('manage_options')) ) {
      return;
    }

    // load if network
    if ( ! function_exists('is_plugin_active_for_network') ) {
      require_once( ABSPATH. 'wp-admin/includes/plugin.php' );
    }

    // set clear url w/o query string
    $clear_url = preg_replace('/\?.*/', '', home_url( add_query_arg( NULL, NULL ) ));

    // no multisite.

    if ($_GET['_cache'] == 'clearurl') {
      // clear url cache
      self::clearPageCacheByUrl($clear_url);
    } else {
      // clear cache
      self::clearCacheAll();

      // clear notice
      if ( is_admin() ) {
        add_action(
          'admin_notices',
          array(
            __CLASS__,
            'clear_notice'
          )
        );
      }
    }

    if ( ! is_admin() ) {
      wp_safe_redirect(
        remove_query_arg(
          '_cache',
          wp_get_referer()
        )
      );

      exit();
    }
  }

  /**
   * Notification After Clear Cache
   *
   * @since   1.0.0
   * @change  1.0.0
   *
   * @hook  mixed  user_can_clear_cache
   */
  public static function clearNotice()
  {
    // check if admin
    if ( ! is_admin_bar_showing() OR ! apply_filters('user_can_clear_cache', current_user_can('manage_options')) ) {
      return false;
    }

    echo sprintf(
      '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
      esc_html__('The cache has been cleared.', 'base-cache')
    );
  }

  /**
   * Register Publish Hooks for Custom Post Types
   *
   * @since   1.0.0
   * @since   1.2.3
   *
   * @param   void
   * @return  void
   */
  public static function registerPublishHooks()
  {
    // get post types
    $post_types = get_post_types(
      array('public' => true)
    );

    // check if empty
    if ( empty($post_types) ) {
      return;
    }

    // post type actions
    foreach ( $post_types as $post_type ) {
      add_action(
        'publish_' .$post_type,
        array(
          __CLASS__,
          'publish_post_types'
        ),
        10,
        2
      );
    }
  }

  /**
   * Delete Post Type Cache on Post Updates
   *
   * @since   1.0.0
   * @change  1.0.7
   *
   * @param   integer  $post_ID  Post ID
   */
  public static function publishPostTypes($post_ID, $post)
  {
    // check if post id or post is empty
    if ( empty($post_ID) OR empty($post) ) {
      return;
    }

    // check post status
    if ( ! in_array( $post->post_status, array('publish', 'future') ) ) {
      return;
    }

    // purge cache if clean post on update
    if ( ! isset($_POST['_clear_post_cache_on_update']) )
    {
      // clear complete cache if option enabled
      if ( self::$options['new_post'] )
      {
        if ( 0 )
        {
          /* Do we really want such a blatant clearing
          of the entire cache, just because? */
          return self::clearCacheAll();
        }
      }
      else
      {
      if ( 0 )
      {
        /* This function is clearing the home page cache
        so that it never remains. */
        //return self::clearHomePageCache();
      }
      }
    }

    // validate nonce
    if ( ! isset($_POST['_cache__status_nonce_' .$post_ID]) OR ! wp_verify_nonce($_POST['_cache__status_nonce_' .$post_ID], BC_PLUGIN_BASE) ) {
      return;
    }

    // validate user role
    if ( ! current_user_can('publish_posts') ) {
      return;
    }

    // save as integer
    $clear_post_cache = (int)$_POST['_clear_post_cache_on_update'];

    // save user metadata
    update_user_meta(
      get_current_user_id(),
      '_clear_post_cache_on_update',
      $clear_post_cache
    );

    // purge complete cache or specific post
    if ( $clear_post_cache ) {
      self::clearPageCacheByPostId( $post_ID );
    } else {
      self::clearCacheAll();
    }
  }

  /**
   * Clear Page Cache By Post Id
   *
   * @since   1.0.0
   * @change  1.0.0
   *
   * @param   integer  $post_ID  Post ID
   */
  public static function clearPageCacheByPostId($post_ID)
  {
    // is int
    if ( ! $post_ID = (int)$post_ID ) {
      return;
    }

    // clear cache by URL
    self::clearPageCacheByUrl(
      get_permalink( $post_ID )
    );
  }

  /**
   * clear page cache by url
   *
   * @since   1.0.0
   * @change  1.2.3
   *
   * @param  string  $url  url of a page
   */
  public static function clearPageCacheByUrl($url)
  {
    // validate string
    if ( ! $url = (string)$url ) {
      return;
    }

    call_user_func(
      array(
        self::$disk,
        'delete_asset'
      ),
      $url
    );

    // clear cache by url post hook
    do_action('ce_action_cache_by_url_cleared');
  }

  /**
   * clear home page cache
   *
   * @since   1.0.7
   * @change  1.2.3
   *
   */
  public static function clearHomePageCache()
  {

    call_user_func(
      array(
        self::$disk,
        'clear_home'
      )
    );

    // clear home page cache post hook
    do_action('ce_action_home_page_cache_cleared');
  }

  /**
   * check if index.php
   *
   * @since   1.0.0
   * @change  1.0.0
   *
   * @return  boolean  true if index.php
   */
  private static function isIndex()
  {
    return strtolower(basename($_SERVER['SCRIPT_NAME'])) != 'index.php';
  }

  /**
   * check if logged in
   *
   * @since   1.0.0
   * @change  1.0.0
   *
   * @return  boolean  true if logged in or cookie set
   */
  private static function isLoggedIn()
  {
    // check if logged in
    if ( is_user_logged_in() ) {
      return true;
    }

    // check cookie
    if ( empty($_COOKIE) ) {
      return false;
    }

    // check cookie values
    $options = self::$options;
    if ( !empty($options['excl_cookies']) ) {
      $cookies_regex = $options['excl_cookies'];
    } else {
      $cookies_regex = '/^(wp-postpass|wordpress_logged_in|comment_author)_/';
    }

    foreach ( $_COOKIE as $k => $v) {
      if ( preg_match($cookies_regex, $k) ) {
        return true;
      }
    }
  }

  /**
   * check to bypass the cache
   *
   * @since   1.0.0
   * @change  1.2.3
   *
   * @return  boolean  true if exception
   *
   * @hook  boolean  bypass cache
   */
  private static function bypassCache()
  {
    // bypass cache hook
    if ( apply_filters('bypass_cache', false) ) {
      return true;
    }

    // conditional tags
    if (
      self::isIndex()
      OR is_search()
      OR is_404()
      OR is_feed()
      OR is_trackback()
      OR is_robots()
      OR is_preview()
      OR post_password_required()
    ) {
      return true;
    }

    // Base Cache options
    $options = self::$options;

    // Request method GET
    if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] != 'GET' ) {
      return true;
    }

    // Request with query strings
    if ( ! empty($_GET) && ! isset( $_GET['utm_source'], $_GET['utm_medium'], $_GET['utm_campaign'] ) && get_option('permalink_structure') ) {
      return true;
    }

    // if logged in
    if ( self::isIndex() ) {
      return true;
    }

    // if post id excluded
    if ( $options['excl_ids'] && is_singular() ) {
      if ( in_array( $GLOBALS['wp_query']->get_queried_object_id(), (array)explode(',', $options['excl_ids']) ) ) {
        return true;
      }
    }

    // if post path excluded
    if ( ! empty($options['excl_regexp']) ) {
      $url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

      if ( preg_match($options['excl_regexp'], $url_path) ) {
        return true;
      }
    }

    return false;
  }

  /**
   * clear complete cache
   *
   * @since   1.0.0
   * @change  1.2.3
   */
  public static function clearCacheAll()
  {
    if ( 0 ) {
      // we need this here to update advanced-cache.php for the 1.2.3 upgrade
      self::on_upgrade();

      // clear disk cache
      BaseCacheDisk::clear_cache();

      // delete transient
      delete_transient('cache_size');

      // clear cache post hook
      do_action('ce_action_cache_cleared');
    }
  }

  /**
   * Set Cache
   *
   * This sets the cache if it is empty or expired.
   *
   * @since   1.0.0
   * @change  1.4.0
   *
   * @param   string  $data  content of a page
   * @return  string  $data  content of a page
   */
  public static function setCache($data) {

    // check if empty
    if ( empty($data) ) {
      return '';
    }

    $data = apply_filters('base_cache_before_store', $data);

    /*
    // store as asset
    call_user_func(
      array(
        self::$disk,
        'store_asset'
      ),
      //self::_minify_cachex($data)
    );
    */
    return $data;
  }

  /**
   * handle cache
   *
   * @since   1.0.0
   * @change  1.0.1
   */
  public static function handleCache() {

    // bypass cache
    if ( self::bypassCache() ) {
      return;
    }

    // get asset cache status
    $cached = call_user_func(
      array(
        self::$disk,
        'checkAsset'
      )

      );
    // check if cache empty
    if ( empty($cached) ) {
      ob_start('BaseCacheCore::setCache');
      return;
    }

    // get expiry status
    $expired = call_user_func(
      array(
        self::$disk,
        'checkExpiry'
      )
    );

    // check if expired
    if ( $expired ) {
      ob_start('BaseCacheCore::setCache');
      return;
    }

    // check if we are missing a trailing slash
    if ( self::missingTrailingSlash() ) {
      return;
    }

    // return cached asset
    call_user_func(
      array(
        self::$disk,
        'getAsset'
      )
    );
  }

  /**
   * add clear option dropdown on post publish widget
   *
   * @since   1.0.0
   * @change  1.0.0
   */
  public static function addClearDropdown() {

    // on published post page only
    if ( empty($GLOBALS['pagenow']) OR $GLOBALS['pagenow'] !== 'post.php' OR empty($GLOBALS['post']) OR ! is_object($GLOBALS['post']) OR $GLOBALS['post']->post_status !== 'publish' ) {
      return;
    }

    // check user role
    if ( ! current_user_can('publish_posts') ) {
      return;
    }

    // validate nonce
    wp_nonce_field(BC_PLUGIN_BASE, '_cache__status_nonce_' .$GLOBALS['post']->ID);

    // get current action
    $current_action = (int)get_user_meta(
      get_current_user_id(),
      '_clear_post_cache_on_update',
      true
    );

    // init variables
    $dropdown_options = '';
    $available_options = array(
      esc_html__('Page specific', 'base-cache'),
      esc_html__('Completely', 'base-cache')
    );

    // set dropdown options
    foreach( $available_options as $key => $value ) {
      $dropdown_options .= sprintf(
        '<option value="%1$d" %3$s>%2$s</option>',
        $key,
        $value,
        selected($key, $current_action, false)
      );
    }

    // output drowdown
    echo sprintf(
      '<div class="misc-pub-section" style="border-top:1px solid #eee">
        <label for="cache_action">
          %1$s: <span id="output-cache-action">%2$s</span>
        </label>
        <a href="#" class="edit-cache-action hide-if-no-js">%3$s</a>

        <div class="hide-if-js">
          <select name="_clear_post_cache_on_update" id="cache_action">
            %4$s
          </select>

          <a href="#" class="save-cache-action hide-if-no-js button">%5$s</a>
           <a href="#" class="cancel-cache-action hide-if-no-js button-cancel">%6$s</a>
         </div>
      </div>',
      esc_html__('Clear cache', 'base-cache'),
      $available_options[$current_action],
      esc_html__('Edit'),
      $dropdown_options,
      esc_html__('OK'),
      esc_html__('Cancel')
    );
  }

  /**
   * enqueue scripts
   *
   * @since   1.0.0
   * @change  1.0.0
   */
  public static function addAdminResources($hook) {

    // hook check
    if ( $hook !== 'index.php' && $hook !== 'post.php' ) {
      return;
    }

    // plugin data
    $plugin_data = get_plugin_data(BC_FILE);

    // enqueue scripts
    switch($hook) {

      case 'post.php':
        wp_enqueue_script(
          'cache-post',
          plugins_url('js/post.js', BC_FILE),
          array('jquery'),
          $plugin_data['Version'],
          true
        );
        break;

      default:
        break;
    }
  }

  /**
   * add settings page
   *
   * @since   1.0.0
   * @change  1.0.0
   */
  public static function addSettingsPage() {

    add_options_page(
      'Cache Enabler',
      'Cache Enabler',
      'manage_options',
      'base-cache',
      array(
        'BaseCacheSettings',
        'settingsPage'
      )
    );
  }

  /**
   * Check Plugin Requirements
   *
   * @since   1.1.0
   * @change  1.4.0
   */
  public static function requirementsCheck() {

    // Base Cache options
    $options = self::$options;


    // permission check
    if ( file_exists( SITE_CACHE_PATH ) && !is_writable( SITE_CACHE_PATH ) ) {
      show_message(
        sprintf(
          '<div class="error"><p>%s</p></div>',
          sprintf(
            __('The <strong>%s</strong> requires write permissions %s on %s. Please <a href="%s" target="_blank">change the permissions</a>.', 'base-cache'),
            'Base Cache',
            '<code>755</code>',
            '<code>/a</code>',
            'http://codex.wordpress.org/Changing_File_Permissions'
          )
        )
      );
    }
  }

  /**
   * register textdomain
   *
   * @since   1.0.0
   * @change  1.0.0
   */
  public static function registerTextdomain()
  {
    load_plugin_textdomain(
      'base-cache',
      false,
      'base-cache/lang'
    );
  }

  /**
  * missing trailing slash
  *
  * we only have to really check that in advanced-cache.php
  *
  * @since 1.2.3
  *
  * @return boolean  true if we need to redirct, otherwise false
  */
  public static function missingTrailingSlash()
  {
    if ( ( $permalink_structure = get_option('permalink_structure')) &&
    preg_match("/\/$/", $permalink_structure) ) {

    // record permalink structure for advanced-cache
    BaseCacheDisk::record_advcache_settings(array(
    "permalink_trailing_slash" => true
    ));

    if ( ! preg_match("/\/(|\?.*)$/", $_SERVER["REQUEST_URI"]) ) {
    return true;
    }
    } else {
    BaseCacheDisk::delete_advcache_settings(array(
    "permalink_trailing_slash"));
    }

    return false;
  }

  /**
   * Register Settings
   *
   * @since   1.0.0
   * @change  1.0.0
   */
  public static function registerSettings()
  {
    register_setting(
      'base-cache',
      'base-cache',
      array(
      __CLASS__,
      'validateSettings'
      )
    );
  }

  /**
   * Validate Regex
   *
   * @since   1.2.3
   *
   * @param   string  $regex string containing regex string
   * @return  string string containing regexps or emty string if input invalid
   */
  public static function validateRegex($regex)
  {
    if ( $regex != '' ) {

    if ( ! preg_match('/^\/.*\/$/', $regex) ) {
      $regex = '/'.$regex.'/';
    }

    if ( @preg_match($regex, null) === false ) {
      return '';
    }

    return sanitize_text_field($regex);
    }

    return '';
  }

  /**
   * Validate Settings
   *
   * @since 1.0.0
   * @change 1.2.3
   *
   * @return array array form data valid
   * @param array $data array form data
   */
  public static function validateSettings($data)
  {
    // check if empty
    if ( empty($data) ) {
      return;
  }

  // clear complete cache
  self::clearCacheAll(true);

  // ignore result, but call for settings recording
  self::missingTrailingSlash();

  // record expiry time value for advanced-cache.php
  if ( $data['expires'] > 0 ){
    BaseCacheDisk::record_advcache_settings(array(
    "expires" => $data['expires']));
  } else {
    BaseCacheDisk::delete_advcache_settings(array("expires"));
  }

  // path bypass regex
  if ( strlen($data["excl_regexp"]) > 0 ) {
    BaseCacheDisk::record_advcache_settings(array(
    "excl_regexp" => $data["excl_regexp"]));
    } else {
    BaseCacheDisk::delete_advcache_settings(array("excl_regexp"));
  }

  // custom cookie exceptions
  if ( strlen($data["excl_cookies"]) > 0 ) {
    BaseCacheDisk::record_advcache_settings(array(
    "excl_cookies" => $data["excl_cookies"]));
  } else {
    BaseCacheDisk::delete_advcache_settings(array("excl_cookies"));
  }

  // custom GET attribute exceptions
  if ( strlen($data["incl_attributes"]) > 0 ) {
    BaseCacheDisk::record_advcache_settings(array(
    "incl_attributes" => $data["incl_attributes"]));
  } else {
    BaseCacheDisk::delete_advcache_settings(array("incl_attributes"));
  }

  return array(
    'expires'       => (int)$data['expires'],
    'new_post'      => (int)(!empty($data['new_post'])),
    'new_comment'     => (int)(!empty($data['new_comment'])),
    'webp'        => (int)(!empty($data['webp'])),
    'clear_on_upgrade'  => (int)(!empty($data['clear_on_upgrade'])),
    'compress'      => (int)(!empty($data['compress'])),
    'excl_ids'      => (string)sanitize_text_field(@$data['excl_ids']),
    'excl_regexp'     => (string)self::validateRegex(@$data['excl_regexp']),
    'excl_cookies'    => (string)self::validateRegex(@$data['excl_cookies']),
    'incl_attributes'   => (string)self::validateRegex(@$data['incl_attributes']),
    'minify_html'     => (int)$data['minify_html']
    );
  }
}

/**
 * Base Cache Store
 *
 * Plugin specific settings for running an online store.
 */
class BaseCacheStore extends BaseCacheCore
{
  /**
   * Act on WooCommerce stock changes
   *
   * @since 1.3.0
   */
  public static function woocommerceProductSetStock($product) {
    self::woocommerceProductSetStockStatus($product->get_id());
  }

  public static function woocommerceProductSetStockStatus($product_id) {
    self::clearCacheAll();
  }
}

/**
 * Base Cache Settings
 *
 * Settings Page with HTML
 */
class BaseCacheSettings extends BaseCacheCore
{
  /**
   * Settings Page
   *
   * @since   1.0.0
   * @change  1.4.0
   */
  public static function settingsPage()
  {
    // wp cache check
    if ( !defined('WP_CACHE') || ! WP_CACHE ) {
      echo sprintf(
      '<div class="notice notice-warning"><p>%s</p></div>',
      sprintf(
      __("%s is not set in %s.", 'base-cache'),
      "<code>define('WP_CACHE', true);</code>",
      "wp-config.php"
      )
      );
    }
    ?>
    <div class="wrap" id="cache-settings">
    <h2>
    <?php _e("Base Cache Settings", "base-cache") ?>
    </h2>
    <p><?php
      $size = self::getCacheSize();
      printf( __("Current cache size: <b>%s</b>", "base-cache"),
      ( empty($size) ? esc_html__("Empty", "base-cache") : size_format($size) ) ); ?>
    </p>
    <form method="post" action="options.php">
    <?php settings_fields('base-cache') ?>
    <?php $options = self::getOptions() ?>
    <table class="form-table">
    <tr valign="top">
      <th scope="row">
      <?php _e("Cache Expiry", "base-cache") ?>
      </th>
      <td>
      <fieldset>
      <label for="cache_expires">
      <input type="text" name="base-cache[expires]" id="cache_expires" value="<?php echo esc_attr($options['expires']) ?>" />
      <p class="description"><?php _e("Cache expiry in hours. An expiry time of 0 means that the cache never expires.", "base-cache"); ?></p>
      </label>
      </fieldset>
      </td>
    </tr>
    <tr valign="top" style="display: none;">
      <th scope="row">
      <?php _e("Cache Behavior", "base-cache") ?>
      </th>
      <td>
      <fieldset>
      <label for="cache_clear_on_upgrade">
      <input
        type="checkbox"
        name="base-cache[clear_on_upgrade]"
        id="cache_clear_on_upgrade"
        <?php checked('0', $options['clear_on_upgrade']); ?> />
      <?php _e("Clear the complete cache if any plugin has been upgraded.", "base-cache") ?>
      </label>
      </fieldset>
      </td>
    </tr>
    <tr valign="top">
      <th scope="row">
      <?php _e("Cache Exclusions", "base-cache") ?>
      </th>
      <td>
      <fieldset>
      <label for="cache_excl_ids">
      <input type="text" name="base-cache[excl_ids]" id="cache_excl_ids" value="<?php echo esc_attr($options['excl_ids']) ?>" />
      <p class="description">
      <?php echo sprintf(__("Post or Pages IDs separated by a %s that should not be cached.", "base-cache"), "<code>,</code>"); ?>
      </p>
      </label>
      <br />
      <label for="cache_excl_regexp">
      <input type="text" name="base-cache[excl_regexp]" id="cache_excl_regexp" value="<?php echo esc_attr($options['excl_regexp']) ?>" />
      <p class="description">
      <?php _e("Regex matching page paths that should not be cached.", "base-cache"); ?><br>
      <?php _e("Example:", "base-cache"); ?> <code>/(^\/$|\/robot\/$|^\/2018\/.*\/test\/)/</code>
      </p>
      </label>
      <br />
      <label for="cache_excl_cookies">
      <input type="text" name="base-cache[excl_cookies]" id="cache_excl_cookies" value="<?php echo esc_attr($options['excl_cookies']) ?>" />
      <p class="description">
      <?php _e("Regex matching cookies that should cause the cache to be bypassed.", "base-cache"); ?><br>
      <?php _e("Example:", "base-cache"); ?> <code>/^(wp-postpass|wordpress_logged_in|comment_author|(woocommerce_items_in_cart|wp_woocommerce_session)_?)/</code><br>
      <?php _e("Default if unset:", "base-cache"); ?> <code>/^(wp-postpass|wordpress_logged_in|comment_author)_/</code>
      </p>
      </label>
      </fieldset>
      </td>
    </tr>
    <tr valign="top">
      <th scope="row">
      <?php _e("Cache Inclusions", "base-cache") ?>
      </th>
      <td>
      <fieldset>
      <label for="cache_incl_attributes">
      <input type="text" name="base-cache[incl_attributes]" id="cache_incl_attributes" value="<?php echo esc_attr($options['incl_attributes']) ?>" />
      <p class="description">
      <?php _e("Regex matching campaign tracking GET attributes that should not cause the cache to be bypassed.", "base-cache"); ?><br>
      <?php _e("Example:", "base-cache"); ?> <code>/^pk_(source|medium|campaign|kwd|content)$/</code><br>
      <?php _e("Default if unset:", "base-cache"); ?> <code>/^utm_(source|medium|campaign|term|content)$/</code>
      </p>
      </label>
      </fieldset>
      </td>
    </tr>
    <tr valign="top">
      <th scope="row">
      <?php submit_button(); ?>
      </th>
      <td>
      <p class="description">
        <?php _e("Saving these settings will NOT clear the complete cache.", "base-cache") ?>
      </p>
      </td>
    </tr>
    </table>
    </form>
    <p class="description"><?php _e("It is recommended to enable HTTP/2 on your origin server and use a CDN that supports HTTP/2. Avoid domain sharding and concatenation of your assets to benefit from parallelism of HTTP/2. (Note the number of files that can transmitted in parallel and try to stay within this limit for each page or within a few multiples thereof.)", "base-cache") ?></p>
    </div>
  <?php
  }
}
