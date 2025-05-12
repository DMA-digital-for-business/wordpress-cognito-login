<?php

class Cognito_Login_Js_Helpers
{
    public static function inject_login_url()
    {
        wp_register_script('inject_login_info', '', [], null, true);

        wp_localize_script('inject_login_info', 'loginInfoObject', [
            'signUpUrl'   => Cognito_Login_Generate_Strings::login_url(true), // true = punta a signup e non al login
            'loginUrl'   => Cognito_Login_Generate_Strings::login_url(),
            'isLoggedIn' => is_user_logged_in(),
        ]);

        wp_enqueue_script('inject_login_info');
    }

}
