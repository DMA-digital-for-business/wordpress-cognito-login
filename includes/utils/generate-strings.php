<?php
include_once PLUGIN_PATH . 'includes/utils/options.php';

/**
 * Class contains functions used to generate strings
 */
class Cognito_Login_Generate_Strings
{

    public static function get_current_path_page()
    {
        $protocol   = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];
        $path       = $_SERVER['REQUEST_URI'];
        return $protocol . $domainName . $path;
    }

    /**
     * URL to use in the login link href
     */
    public static function login_url($signup = false, $lost_password = false)
    {
        $app_auth_url  = Cognito_Login_Options::get_plugin_option('COGNITO_APP_AUTH_URL');
        $app_client_id = Cognito_Login_Options::get_plugin_option('COGNITO_APP_CLIENT_ID');
        $oauth_scopes  = Cognito_Login_Options::get_plugin_option('COGNITO_OAUTH_SCOPES');
        $migration_uri = Cognito_Login_Options::get_plugin_option('COGNITO_MIGRATION_URI');

        $redirect_url = Cognito_Login_Generate_Strings::get_current_path_page();
        if (mb_stripos($redirect_url, "wp-login.php") !== false) {
            $redirect_url = Cognito_Login_Options::get_plugin_option('COGNITO_HOMEPAGE');
        }

        $force_auth = Cognito_Login_Options::get_plugin_option('COGNITO_FORCE_AUTH') === 'true' ? "true" : "false";

        $app_auth_url = $app_auth_url . '/' ;
        if ($signup) {
            $app_auth_url .= 'signup';
        } 
        // In attesa dell'implementazione lato frontend
        // elseif ($lost_password) {
        //     $app_auth_url .= 'lost-password';
        // }
        

        $query_parameters = '?client_id=' . $app_client_id 
            . '&response_type=code&scope=' . $oauth_scopes 
            . '&redirect_uri=' . $redirect_url 
            . '&forceAuth=' . $force_auth 
            . (! empty($migration_uri) ? '&migration_uri=' . $migration_uri : '');

        return $app_auth_url . $query_parameters;
    }

    /**
     * URL to use when getting tokens
     */
    public static function token_url()
    {
        $app_auth_url = Cognito_Login_Options::get_plugin_option('COGNITO_APP_AUTH_URL');

        return $app_auth_url . '/oauth2/token';
    }

    /**
     * Authorization header used when communicating with cognito
     */
    public static function authorization_header()
    {
        $app_client_id     = Cognito_Login_Options::get_plugin_option('COGNITO_APP_CLIENT_ID');
        $app_client_secret = Cognito_Login_Options::get_plugin_option('COGNITO_APP_CLIENT_SECRET');

        return 'Basic ' . base64_encode($app_client_id . ':' . $app_client_secret);
    }

    /**
     * "a" tag for the login link
     *
     * @param array $atts Possible attributes, text and class
     */
    public static function a_tag($atts)
    {
        $url   = Cognito_Login_Generate_Strings::login_url();
        $text  = $atts['text'] ?: Cognito_Login_Options::get_plugin_option('COGNITO_LOGIN_LINK_TEXT') ?: 'Login';
        $class = $atts['class'] ?: Cognito_Login_Options::get_plugin_option('COGNITO_LOGIN_LINK_CLASS') ?: 'cognito-login-link';

        return '<a class="' . $class . '" href="' . $url . '">' . $text . '</a>';
    }

    /**
     * String shown to the user instead of a login link when they are already logged in
     */
    public static function already_logged_in($username)
    {
        return 'You are logged in as ' . $username . ' | <a href="' . wp_logout_url(home_url()) . '">Log Out</a>';
    }

    /**
     * Generates a cryptographically secure string of the requested length
     *
     * @param int The length of the string to generate
     *
     * @return string|boolean Randomly generated string or FALSE if generation failed
     */
    public static function password($length)
    {
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $random_character = Cognito_Login_Generate_Strings::password_char();

            // If the character is FALSE, there was an error
            if ($random_character === false) {
                return false;
            }

            $password .= $random_character;
        }

        return $password;
    }

    public static function password_char()
    {
        $password_chars = Cognito_Login_Options::get_plugin_option('COGNITO_PASSWORD_CHARS');
        try {
            return $password_chars[random_int(0, strlen($password_chars) - 1)];
        } catch (Exception $e) {
            // An exception means a secure random byte generator is unavailable. Generate an insecure
            // character or return FALSE
            if (Cognito_Login_Options::get_plugin_option('COGNITO_ALLOW_INSECURE_PASSWORD') === 'true') {
                return $password_chars[mt_rand(0, strlen($password_chars) - 1)];
            }

            return false;
        }
    }
}
