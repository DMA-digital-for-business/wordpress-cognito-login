<?php

include_once PLUGIN_PATH . 'includes/utils/options.php';

class Cognito_Login_Profiling
{
    /**
     * Checks if the user profiling is active and if the user has completed the profiling
     * form. If the user has not completed the form and is not on the form page, it will
     * redirect to the form page.
     *
     * @return void
     */
    public static function check_user_profiling()
    {
        if (! current_user_can("administrator") && is_user_logged_in() && Cognito_Login_Options::get_plugin_option('COGNITO_PROFILING_ACTIVE') === 'true') {
            $user_id            = get_current_user_id();
            $profiling_complete = get_user_meta($user_id, 'sso_profiling_complete');

            //Se il profiling non è completo e non siamo già sulla pagina del form
            if (! $profiling_complete && ! is_page(Cognito_Login_Options::get_plugin_option('COGNITO_PROFILING_PATH'))) {
                wp_redirect(home_url('/' . Cognito_Login_Options::get_plugin_option('COGNITO_PROFILING_PATH') . '?from=' . Cognito_Login_Generate_Strings::get_current_path_page()));
                exit;
            }
        }
    }

    /**
     * Verifies if a user is logged in and if the provided nonce is valid.
     *
     * @param WP_REST_Request $request The request object containing the nonce.
     * @return bool True if the user is logged in and the nonce is valid, false otherwise.
     */
    public static function is_user_logged_in_and_verify_nonce($request)
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (! wp_verify_nonce($nonce, 'wp_rest') || ! is_user_logged_in()) {
            return false;
        }
        return true;
    }

    /**
     * Registers a REST endpoint to handle the user profiling form submission.
     * The endpoint is accessible at /wp-json/custom/v1/profile-user and accepts
     * POST requests. The request must contain a valid X-WP-Nonce header and the user
     * must be logged in to access the endpoint.
     *
     * @since 1.0.0
     */

    public static function register_profile_user_endpoint()
    {
        register_rest_route('custom/v1', '/profile-user', [
            'methods'             => 'POST',
            'callback'            => 'Cognito_Login_Profiling::handle_profile_user',
            'permission_callback' => 'Cognito_Login_Profiling::is_user_logged_in_and_verify_nonce',
        ]);
    }

    /**
     * Handles the user profiling form submission.
     *
     * This function is a callback for the /wp-json/custom/v1/profile-user endpoint and
     * is called when the user submits the profiling form. It checks if the user is logged
     * in and if the request contains a valid X-WP-Nonce header. If the check passes,
     * it updates the user meta to mark the profiling as complete.
     *
     * @param WP_REST_Request $request The request object containing the nonce.
     *
     * @return WP_REST_Response The response object containing a success message
     *                          and a status code.
     */
    public static function handle_profile_user(WP_REST_Request $request)
    {
        if (! is_user_logged_in()) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'User not logged in',
            ], 401);
        }

        $user_id = get_current_user_id();
        $updated = update_user_meta($user_id, 'sso_profiling_complete', true);

        if ($updated) {
            return new WP_REST_Response([
                'success' => true,
                'message' => 'User meta updated successfully',
            ], 200);
        } else {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to update user meta',
            ], 500);
        }
    }

    public static function profiling_enqueue_scripts()
    {
        if (is_page(Cognito_Login_Options::get_plugin_option('COGNITO_PROFILING_PATH'))) {
            wp_enqueue_script('sso-cognito-login-js', plugin_dir_url(__FILE__) . '../../public/js/cognito-wp.js');

            wp_localize_script('sso-cognito-login-js', 'profiling_object', [
                'nonce' => wp_create_nonce('wp_rest'),
            ]);
        }
    }

}
