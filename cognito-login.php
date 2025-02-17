<?php
/*
  Plugin Name: Cognito Login
  Plugin URI: https://github.com/DMA-digital-for-business/wordpress-cognito-login
  description: WordPress plugin for integrating with Cognito for User Pools
  Version: 1.7.2
  Author: DMA
  Author URI: https://www.dma.it/
*/

define('PLUGIN_PATH', plugin_dir_path(__FILE__));
include_once (PLUGIN_PATH . 'settings.php');

// --- Include Utilities ---
include_once (PLUGIN_PATH . 'includes/utils/generate-strings.php');
include_once (PLUGIN_PATH . 'includes/utils/options.php');

// --- Include Units ---
include_once (PLUGIN_PATH . 'includes/units/auth.php');
include_once (PLUGIN_PATH . 'includes/units/programmatic-login.php');
include_once (PLUGIN_PATH . 'includes/units/user.php');
include_once (PLUGIN_PATH . 'includes/units/profiling.php');

include_once (ABSPATH . 'wp-includes/pluggable.php');

/**
 * General initialization function container
 */
class Cognito_Login
{
  /**
   * The default shortcode returns an "a" tag, or a logout link, depending on if the user is
   * logged in
   */
  public static function shortcode_default($atts)
  {
    wp_enqueue_style('cognito-login-wp-login', plugin_dir_url(__FILE__) . 'public/css/cognito-login-wp-login.css');

    $atts = shortcode_atts(
      array(
        'text' => NULL,
        'class' => NULL
      ), $atts);
    $user = wp_get_current_user();

    if ($user->{'ID'} !== 0) {
      return Cognito_Login_Generate_Strings::already_logged_in($user->{'user_login'});
    }

    return Cognito_Login_Generate_Strings::a_tag($atts);
  }

  /**
   * Handler for the "parse_query" action. This is the "main" function that listens for the
   * correct query variable that will trigger a login attempt
   */
  public static function parse_query_handler()
  {
    // Remove this function from the action queue - it should only run once
    remove_action('parse_query', array('Cognito_Login', 'parse_query_handler'));

    if (stripos($_SERVER['REQUEST_URI'], '/wp-json/') !== false) {
      return;
    }

    // Attempt to exchange the code for a token, abort if we weren't able to
    $token = Cognito_Login_Auth::get_id_token();
    $refresh_token = Cognito_Login_Auth::get_refresh_token();
    if ($token === FALSE)
      return;
    if (is_user_logged_in())
      return;

    // Parse the token
    $parsed_token = Cognito_Login_Auth::parse_jwt($token);

    // Determine user existence
    if (!in_array(Cognito_Login_Options::get_plugin_option('COGNITO_USERNAME_ATTRIBUTE'), $parsed_token))
      return;
    $username = $parsed_token[Cognito_Login_Options::get_plugin_option('COGNITO_USERNAME_ATTRIBUTE')];

    $user = get_user_by('login', $username);

    if ($user === FALSE) {
      // Get user by email
      $user = get_user_by('email', $username);

      if ($user !== FALSE)
        $username = $user->user_login;
    }

    if ($user === FALSE) {
      // Also check for a user that only matches the first part of the email
      $non_email_username = substr($username, 0, strpos($username, '@'));
      $user = get_user_by('login', $non_email_username);

      if ($user !== FALSE)
        $username = $non_email_username;
    }

    if ($user === FALSE) {
      // Create a new user only if the setting is turned on
      if (Cognito_Login_Options::get_plugin_option('COGNITO_NEW_USER') !== 'true')
        return;

      // Create a new user and abort on failure
      $user = Cognito_Login_User::create_user($parsed_token);
      if ($user === FALSE)
        return;
    }

    // Add to new blog id if user doesn't exist
    if ($user !== FALSE && Cognito_Login_Options::get_plugin_option('COGNITO_ADD_USER_TO_NEW_BLOG') === 'true') {
      Cognito_Login_User::add_user_to_new_blog($user);
    }

    // Log the user in! Exit if the login fails
    if (Cognito_Login_Programmatic_Login::login($username) === FALSE)
      return;
    
    // Login successful
    $expiration = time() + apply_filters('auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user->ID, true);
    setcookie(Cognito_Login_Options::get_plugin_option('COGNITO_COOKIE_NAME'), $refresh_token != false ? $refresh_token : $token, $expiration, "/", Cognito_Login_Options::get_plugin_option('COGNITO_COOKIE_DOMAIN'), true, true);

    Cognito_Login_Auth::redirect_to( Cognito_Login_Generate_Strings::get_current_path_page());    
  }

  /**
   * Will disable the default WordPress login experience, replacing the login interface with
   * a link to the Cognito login page. Will only activate if the disable_wp_login setting
   * is set to `true`
   * 
   * This method should be added to the `login_head` action
   */
  public static function disable_wp_login()
  {
    if (Cognito_Login_Options::get_plugin_option('COGNITO_DISABLE_WP_LOGIN') !== 'true')
      return;

    wp_enqueue_style('cognito-login-wp-login', plugin_dir_url(__FILE__) . 'public/css/cognito-login-wp-login.css');

    $loginLink = Cognito_Login_Generate_Strings::a_tag(
      array(
        'text' => NULL,
        'class' => NULL
      )
    );
    ?>
    <script>
      window.addEventListener('load', function() {
        /// Get the form
        var loginForm = document.querySelector('body.login div#login form#loginform');

        loginForm.parentNode.removeChild(loginForm);

        // Also get rid of the nav, password resets are not handled by WordPress
        var nav = document.querySelector('#nav');
          
        nav.parentNode.removeChild(nav);
      });
    </script>
    <?php
  }


  public static function handleAutoLogin()
  {
    global $current_user;

    if (stripos($_SERVER['REQUEST_URI'], '/wp-json/') !== false) {
      return;
    }

    $cookie_name = Cognito_Login_Options::get_plugin_option('COGNITO_COOKIE_NAME');
    $cognito_cookie_is_set = isset($_COOKIE[$cookie_name]);
    $user_logged_into_wp = is_user_logged_in();

    if ($cognito_cookie_is_set && !$user_logged_into_wp) {

      if (wp_redirect(Cognito_Login_Generate_Strings::login_url())) {
        exit;
      }

      return;

    } else if (!$cognito_cookie_is_set && $user_logged_into_wp) {

      wp_logout();

      if (wp_redirect(home_url())) {
        exit;
      }
      return;

    }
  }

  public static function handleLogout($userId)
  {
    setcookie(Cognito_Login_Options::get_plugin_option('COGNITO_COOKIE_NAME'), "", time() - 100, "/", Cognito_Login_Options::get_plugin_option('COGNITO_COOKIE_DOMAIN'), true, true);
    try {
      Cognito_Login::revoke_token($_COOKIE[Cognito_Login_Options::get_plugin_option('COGNITO_COOKIE_NAME')]);
    } catch (\Throwable $th) {
      //throw $th;
    }
    wp_redirect(home_url());
    exit();
  }

  private static function revoke_token($token)
  {
    // URL dell'endpoint
    $url = Cognito_Login_Options::get_plugin_option('COGNITO_DOMAIN') . '/oauth2/revoke';
    $headers = array(
      'Content-Type' => 'application/x-www-form-urlencoded',
    );

    $body = array(
      'token' => $token,
      'client_id' => Cognito_Login_Options::get_plugin_option('COGNITO_APP_CLIENT_ID'),
    );

    $options = array(
      'headers' => $headers,
      'body' => http_build_query($body),
    );

    $response = wp_remote_post($url, $options);
    if (is_wp_error($response)) {
      return $response->get_error_message();
    } else {
      return wp_remote_retrieve_body($response);
    }
  }


  static function json_basic_auth_handler( $user ) {
    global $wp_json_basic_auth_error;
  
    $wp_json_basic_auth_error = null;
  
    // Don't authenticate twice
    if ( ! empty( $user ) ) {
      return $user;
    }
  
    // Check that we're trying to authenticate
    if ( !isset( $_SERVER['PHP_AUTH_USER'] ) ) {
      return $user;
    }
  
    $username = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];
  
    /**
     * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
     * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
     * recursion and a stack overflow unless the current function is removed from the determine_current_user
     * filter during authentication.
     */
    remove_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );
  
    $user = wp_authenticate( $username, $password );
  
    add_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );
  
    if ( is_wp_error( $user ) ) {
      $wp_json_basic_auth_error = $user;
      return null;
    }
  
    $wp_json_basic_auth_error = true;
  
    return $user->ID;
  }
  
  static function json_basic_auth_error( $error ) {
    // Passthrough other errors
    if ( ! empty( $error ) ) {
      return $error;
    }
  
    global $wp_json_basic_auth_error;
  
    return $wp_json_basic_auth_error;
  }

}

// Basic Auth
add_filter( 'determine_current_user', array('Cognito_Login', 'json_basic_auth_handler') );

add_filter( 'rest_authentication_errors', array('Cognito_Login', 'json_basic_auth_error') );


// --- Add Shortcodes ---
add_shortcode('cognito_login', array('Cognito_Login', 'shortcode_default'));

// --- Add Actions ---
add_action('parse_query', array('Cognito_Login', 'parse_query_handler'), 10);

add_action('parse_query', array('Cognito_Login', 'handleAutoLogin'), 11);

add_action('wp_logout', array('Cognito_Login', 'handleLogout'));

// Disable login form and reset password link in wp-login.php
add_action( 'login_head', array('Cognito_Login', 'disable_wp_login') );

// Profiling
add_action('template_redirect', array('Cognito_Login_Profiling', 'check_user_profiling'));

add_action( 'wp_enqueue_scripts', array('Cognito_Login_Profiling', 'profiling_enqueue_scripts') );

add_action( 'rest_api_init', array('Cognito_Login_Profiling', 'register_profile_user_endpoint') );