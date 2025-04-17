<?php
/*
  Plugin Name: Cognito Login
  Plugin URI: https://github.com/DMA-digital-for-business/wordpress-cognito-login
  description: WordPress plugin for integrating with Cognito for User Pools
  Version: 1.14
  Author: DMA
  Author URI: https://www.dma.it/
*/

define('PLUGIN_PATH', plugin_dir_path(__FILE__));

// --- Include Utilities ---
include_once PLUGIN_PATH . 'includes/utils/generate-strings.php';
include_once PLUGIN_PATH . 'includes/utils/options.php';
include_once PLUGIN_PATH . 'includes/utils/jwt-utils.php';

// --- Include Units ---
include_once PLUGIN_PATH . 'includes/units/auth.php';
include_once PLUGIN_PATH . 'includes/units/programmatic-login.php';
include_once PLUGIN_PATH . 'includes/units/user.php';
include_once PLUGIN_PATH . 'includes/units/profiling.php';
include_once PLUGIN_PATH . 'includes/units/shortcodes.php';
include_once PLUGIN_PATH . 'includes/units/login-form.php';

include_once ABSPATH . 'wp-includes/pluggable.php';

/**
 * General initialization function container
 */
class Cognito_Login
{

    /**
     * Handler for the "parse_query" action. This is the "main" function that listens for the
     * correct query variable that will trigger a login attempt
     */
    public static function parse_query_handler()
    {
        // Remove this function from the action queue - it should only run once
        remove_action('parse_query', ['Cognito_Login', 'parse_query_handler']);

        // Eccezione nata per consentire alle chiamate api di non passare dall'sso
        if (stripos($_SERVER['REQUEST_URI'], '/wp-json/') !== false) {
            return;
        }

        // Eccezione nata per consentire i login di Main WP su Main WP Child
        // E' possibile configurare un array di referrals che saltano l'sso
        if (isset($_SERVER["HTTP_REFERER"])
            && defined("COGNITO_IGNORED_REFERERS")
            && in_array($_SERVER["HTTP_REFERER"], COGNITO_IGNORED_REFERERS)
        ) {
            return;
        }

        // Attempt to exchange the code for a token, abort if we weren't able to
        $token         = Cognito_Login_Auth::get_id_token();
        $refresh_token = Cognito_Login_Auth::get_refresh_token();
        if ($token === false) {
            return;
        }
        // Se l'utente ha rifatto il login, cmq valido il token anche se era già loggato
        if (is_user_logged_in()) {
            return;
        }

        // Rilevamento manomissione del token
        $tokenIsValidResult = JwtUtils::jwtTokenIsValid($token, $cognito_jwt_keys);

        if ($tokenIsValidResult !== 'OK') {
            die("Login failed, tampered token '$token' - Error code: $tokenIsValidResult");
            return;
        }

        // Parse the token
        $parsed_token = Cognito_Login_Auth::parse_jwt($token);

        // Determine user existence
        if (! in_array(Cognito_Login_Options::get_plugin_option('COGNITO_USERNAME_ATTRIBUTE'), $parsed_token)) {
            error_log("The token doesn't contains field for COGNITO_USERNAME_ATTRIBUTE: '" . Cognito_Login_Options::get_plugin_option('COGNITO_USERNAME_ATTRIBUTE') . "' - Parsed token " . print_r($parsed_token, true));
            return;
        }
        $username = $parsed_token[Cognito_Login_Options::get_plugin_option('COGNITO_USERNAME_ATTRIBUTE')];

        // Get user by email
        $user = get_user_by('email', $username);

        if ($user !== false) {
            $username = $user->user_login;
        }

        if ($user === false) {
            // Create a new user only if the setting is turned on
            if (Cognito_Login_Options::get_plugin_option('COGNITO_NEW_USER') !== 'true') {
                return;
            }

            // Create a new user and abort on failure
            $user = Cognito_Login_User::create_user($parsed_token);
            if ($user === false) {
                return;
            }

        }

        // Add to new blog id if user doesn't exist
        if ($user !== false && Cognito_Login_Options::get_plugin_option('COGNITO_ADD_USER_TO_NEW_BLOG') === 'true') {
            Cognito_Login_User::add_user_to_new_blog($user);
        }

        // Log the user in! Exit if the login fails
        if (Cognito_Login_Programmatic_Login::login($username) === false) {
            return;
        }

        // Login successful
        $expiration = time() + apply_filters('auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user->ID, true);
        setcookie(Cognito_Login_Options::get_plugin_option('COGNITO_COOKIE_NAME'), $refresh_token != false ? $refresh_token : $token, $expiration, "/", Cognito_Login_Options::get_plugin_option('COGNITO_COOKIE_DOMAIN'), true, true);

        Cognito_Login_Auth::redirect_to(Cognito_Login_Generate_Strings::get_current_path_page());
    }

    public static function handleAutoLogin()
    {
        global $current_user;

        // Eccezione nata per consentire alle chiamate api di non passare dall'sso
        if (stripos($_SERVER['REQUEST_URI'], '/wp-json/') !== false) {
            return;
        }

        // Eccezione nata per consentire i login di Main WP su Main WP Child
        // E' possibile configurare un array di referrals che saltano l'sso
        if (isset($_SERVER["HTTP_REFERER"])
            && defined("COGNITO_IGNORED_REFERERS")
            && in_array($_SERVER["HTTP_REFERER"], COGNITO_IGNORED_REFERERS)
        ) {
            return;
        }

        $cookie_name           = Cognito_Login_Options::get_plugin_option('COGNITO_COOKIE_NAME');
        $cognito_cookie_is_set = isset($_COOKIE[$cookie_name]);
        $user_logged_into_wp   = is_user_logged_in();

        if ($cognito_cookie_is_set && ! $user_logged_into_wp) {

            if (wp_redirect(Cognito_Login_Generate_Strings::login_url())) {
                exit;
            }

            return;

        } else if (! $cognito_cookie_is_set && $user_logged_into_wp) {

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
        $url     = Cognito_Login_Options::get_plugin_option('COGNITO_DOMAIN') . '/oauth2/revoke';
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $body = [
            'token'     => $token,
            'client_id' => Cognito_Login_Options::get_plugin_option('COGNITO_APP_CLIENT_ID'),
        ];

        $options = [
            'headers' => $headers,
            'body'    => http_build_query($body),
        ];

        $response = wp_remote_post($url, $options);
        if (is_wp_error($response)) {
            return $response->get_error_message();
        } else {
            return wp_remote_retrieve_body($response);
        }
    }

    public static function json_basic_auth_handler($user)
    {
        global $wp_json_basic_auth_error;

        $wp_json_basic_auth_error = null;

        // Don't authenticate twice
        if (! empty($user)) {
            return $user;
        }

        // Check that we're trying to authenticate
        if (! isset($_SERVER['PHP_AUTH_USER'])) {
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
        remove_filter('determine_current_user', 'json_basic_auth_handler', 20);

        $user = wp_authenticate($username, $password);

        add_filter('determine_current_user', 'json_basic_auth_handler', 20);

        if (is_wp_error($user)) {
            $wp_json_basic_auth_error = $user;
            return null;
        }

        $wp_json_basic_auth_error = true;

        return $user->ID;
    }

    public static function json_basic_auth_error($error)
    {
        // Passthrough other errors
        if (! empty($error)) {
            return $error;
        }

        global $wp_json_basic_auth_error;

        return $wp_json_basic_auth_error;
    }

}

// Basic Auth
add_filter('determine_current_user', ['Cognito_Login', 'json_basic_auth_handler']);

add_filter('rest_authentication_errors', ['Cognito_Login', 'json_basic_auth_error']);

// --- Add Shortcodes ---
add_shortcode('cognito_login', ['Cognito_Shortcodes', 'cognito_login']);

add_shortcode('cognito_login_url', ['Cognito_Shortcodes', 'cognito_login_url']);

// --- Add Actions ---
add_action('parse_query', ['Cognito_Login', 'parse_query_handler'], 10);

add_action('parse_query', ['Cognito_Login', 'handleAutoLogin'], 11);

add_action('wp_logout', ['Cognito_Login', 'handleLogout']);

// HIDE login form and reset password link in wp-login.php
add_action('login_enqueue_scripts', ['Cognito_LoginForm', 'hide_login_form_and_pwd_reset_link']);
// ADD login box in wp-login.php footer
add_filter('login_message', ['Cognito_LoginForm', 'append_sso_login_to_login_message']);

// Profiling
add_action('template_redirect', ['Cognito_Login_Profiling', 'check_user_profiling']);

add_action('wp_enqueue_scripts', ['Cognito_Login_Profiling', 'profiling_enqueue_scripts']);

add_action('rest_api_init', ['Cognito_Login_Profiling', 'register_profile_user_endpoint']);
