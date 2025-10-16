<?php
class Cognito_Shortcodes
{

    /**
     * Anchor con stile. Gestisce una variante solo stringa per utenti già loggati 
     */
    public static function cognito_login($atts)
    {
        wp_enqueue_style('cognito-login-wp-login', plugin_dir_url(__FILE__) . '../../public/css/cognito-login-wp-login.css');

        $atts = shortcode_atts(
            [
                'text'  => null,
                'class' => null,
            ], $atts);
        $user = wp_get_current_user();

        if ($user->{'ID'} !== 0) {
            return Cognito_Login_Generate_Strings::already_logged_in($user->{'user_login'});
        }

        return Cognito_Login_Generate_Strings::a_tag($atts);
    }
    
    public static function cognito_login_url() 
    {
        return Cognito_Login_Generate_Strings::login_url();
    }

    public static function cognito_signup_url() 
    {
        return Cognito_Login_Generate_Strings::login_url(true);
    }

    // In attesa dell'implementazione lato frontend
    // public static function cognito_reset_password_url() 
    // {
    //     return Cognito_Login_Generate_Strings::login_url(false, true);
    // }

}
