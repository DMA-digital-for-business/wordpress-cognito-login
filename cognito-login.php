<?php
/*
  Plugin Name: Cognito Login for Multisite
  Plugin URI: https://github.com/DMA-digital-for-business/wordpress-cognito-login
  description: WordPress plugin for integrating with Cognito for User Pools
  Version: 1.4.3
  Author: Matteo Collina
  Author URI: https://github.com/matteocollina
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

    // Try to get a code from the url query and abort if we don't find one, or the user is already logged in
    // $code = Cognito_Login_Auth::get_code();
    // if ( $code === FALSE ) return;
    // if ( is_user_logged_in() ) return;

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
    setcookie(Cognito_Login_Options::get_plugin_option('COGNITO_COOKIE_NAME'), $refresh_token, $expiration, "/", Cognito_Login_Options::get_plugin_option('COGNITO_COOKIE_DOMAIN'), false, false);

    // Redirect the user to the "homepage", if it is set (this will hide all `print` statements)
    $homepage = Cognito_Login_Options::get_plugin_option('COGNITO_HOMEPAGE');
    if (!empty($homepage)) {
      Cognito_Login_Auth::redirect_to($homepage);
    }
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
      window.addEventListener('load', function () {
        // Get the form
        var loginForm = document.querySelector('body.login div#login form#loginform');

        // Fully disable the form
        loginForm.action = '/';

        // Modify the inner HTML, adding the login link and removing everything else
        loginForm.innerHTML = '<?php echo $loginLink ?>';

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
      exit();

    }
  }

  public static function handleLogout($userId)
  {
    setcookie(Cognito_Login_Options::get_plugin_option('COGNITO_COOKIE_NAME'), "", time() - 100, "/", Cognito_Login_Options::get_plugin_option('COGNITO_COOKIE_DOMAIN'), false, false);
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

}



// --- Add Shortcodes ---
add_shortcode('cognito_login', array('Cognito_Login', 'shortcode_default'));

// --- Add Actions ---
add_action('parse_query', array('Cognito_Login', 'parse_query_handler'), 10);

add_action('parse_query', array('Cognito_Login', 'handleAutoLogin'), 11);

add_action('wp_logout', array('Cognito_Login', 'handleLogout'));