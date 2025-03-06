<?php

class Cognito_LoginForm
{

    public static function hide_login_form_and_pwd_reset_link()
    {

        if (Cognito_Login_Options::get_plugin_option('COGNITO_DISABLE_WP_LOGIN') !== 'true') {
            return;
        }

        wp_enqueue_style('cognito-login-wp-login', plugin_dir_url(__FILE__) . '../../public/css/cognito-login-wp-login.css');

    }

    public static function append_sso_login_to_login_message($message)
    {
        if (Cognito_Login_Options::get_plugin_option('COGNITO_DISABLE_WP_LOGIN') !== 'true') {
            return;
        }

        $message = $message ? $message . "<BR>" : "";
        $message .= Cognito_login_Generate_Strings::a_tag([]);
        return $message;
    }

}
