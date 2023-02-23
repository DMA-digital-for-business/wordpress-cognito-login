<?php
/**
 * This class is based off these wonderful instructions by Matthew Ray:
 * https://www.smashingmagazine.com/2016/04/three-approaches-to-adding-configurable-fields-to-your-plugin/#top
 */
include_once( PLUGIN_PATH . 'includes/utils/options.php' );

class Cognito_Login_Settings {
  public function __construct() {
    // Hook into the admin menu
    add_action( 'admin_menu', array( $this, 'create_plugin_settings_page' ) );
    add_action( 'admin_init', array( $this, 'setup_sections' ) );
    add_action( 'admin_init', array( $this, 'setup_fields' ) );
  }

  public function create_plugin_settings_page() {
    // Add the menu item and page
    $page_title = 'Cognito Login Settings';
    $menu_title = 'Cognito Login';
    $capability = 'manage_options';
    $slug = 'cognito_login_fields';
    $callback = array( $this, 'plugin_settings_page_content' );

    add_submenu_page( 'options-general.php', $page_title, $menu_title, $capability, $slug, $callback );
  }

  public function plugin_settings_page_content() { ?>
    <div class="wrap">
      <h2>Cognito Login Settings</h2>
      <p>Documentation: <a href="https://github.com/DMA-digital-for-business/wordpress-cognito-login">https://github.com/DMA-digital-for-business/wordpress-cognito-login</a></p>
      <form method="post" action="options.php">
        <?php
          settings_fields( 'cognito_login_fields' );
          do_settings_sections( 'cognito_login_fields' );
        ?>
      </form>
    </div> <?php
  }

  public function setup_sections() {
    add_settings_section( 'cognito_auth_settings', 'Cognito Auth Settings', false, 'cognito_login_fields' );
    add_settings_section( 'plugin_settings', 'Plugin Settings', false, 'cognito_login_fields' );
    add_settings_section( 'new_user_settings', 'New User Settings', false, 'cognito_login_fields' );
  }

  public function setup_fields() {
    $fields = array(
      // Cognito Auth Settings
      array(
        'uid' => 'COGNITO_USER_POOL_ID',
        'label' => 'User Pool ID',
        'section' => 'cognito_auth_settings',
        'type' => 'text',
        'options' => false,
        'placeholder' => 'us-east-1_yourId',
        'helper' => '',
        'supplemental' => ''
      ),
      array(
        'uid' => 'COGNITO_APP_CLIENT_ID',
        'label' => 'App Client ID',
        'section' => 'cognito_auth_settings',
        'type' => 'text',
        'options' => false,
        'placeholder' => 'yourAppClientId',
        'helper' => '',
        'supplemental' => ''
      ),
      array(
        'uid' => 'COGNITO_APP_CLIENT_SECRET',
        'label' => 'App Client Secret',
        'section' => 'cognito_auth_settings',
        'type' => 'text',
        'options' => false,
        'placeholder' => 'yourAppClientSecret',
        'helper' => '',
        'supplemental' => ''
      ),
      array(
        'uid' => 'COGNITO_REDIRECT_URL',
        'label' => 'Redirect URL',
        'section' => 'cognito_auth_settings',
        'type' => 'text',
        'options' => false,
        'placeholder' => 'https://yourredirecturl.com',
        'helper' => '',
        'supplemental' => ''
      ),
      array(
        'uid' => 'COGNITO_APP_AUTH_URL',
        'label' => 'Web Authentication Base',
        'section' => 'cognito_auth_settings',
        'type' => 'text',
        'options' => false,
        'placeholder' => 'https://auth.yourdomain.com',
        'helper' => '',
        'supplemental' => 'Base URL of the Cognito authentication endpoint'
      ),
      array(
        'uid' => 'COGNITO_OAUTH_SCOPES',
        'label' => 'OAuth Scopes',
        'section' => 'cognito_auth_settings',
        'type' => 'text',
        'options' => false,
        'placeholder' => 'openid',
        'helper' => '',
        'supplemental' => 'List of OAuth Scopes. Separate scopes with "+"'
      ),

      // Plugin Settings
      array(
        'uid' => 'COGNITO_HOMEPAGE',
        'label' => 'Homepage',
        'section' => 'plugin_settings',
        'type' => 'text',
        'options' => false,
        'placeholder' => 'https://yourdomain.com/welcome',
        'helper' => '',
        'supplemental' => 'The domain to send a newly logged in user. Leave empty to not redirect'
      ),
      array(
        'uid' => 'COGNITO_LOGIN_LINK_TEXT',
        'label' => 'Login Link Text',
        'section' => 'plugin_settings',
        'type' => 'text',
        'options' => false,
        'placeholder' => 'Login',
        'helper' => '',
        'supplemental' => 'Text to include in the login link. HTML compatible'
      ),
      array(
        'uid' => 'COGNITO_LOGIN_LINK_CLASS',
        'label' => 'Login Link Class',
        'section' => 'plugin_settings',
        'type' => 'text',
        'options' => false,
        'placeholder' => 'cognito-login-link',
        'helper' => '',
        'supplemental' => 'Class to add to the login <a> tag'
      ),
      array(
        'uid' => 'COGNITO_DISABLE_WP_LOGIN',
        'label' => 'Disable WP Login',
        'section' => 'plugin_settings',
        'type' => 'select',
        'options' => array(
          'true' => 'Yes',
          'false' => 'No'
        ),
        'placeholder' => '',
        'helper' => '',
        'supplemental' => 'Should the default WP login system be disabled?'
      ),
      array(
        'uid' => 'COGNITO_FORCE_AUTH',
        'label' => 'Force User Login',
        'section' => 'plugin_settings',
        'type' => 'select',
        'options' => array(
          'true' => 'Yes',
          'false' => 'No'
        ),
        'placeholder' => '',
        'helper' => '',
        'supplemental' => 'Force user to login'
      ),

      // New user settings
      array(
        'uid' => 'COGNITO_NEW_USER',
        'label' => 'Create New User',
        'section' => 'new_user_settings',
        'type' => 'select',
        'options' => array(
          'true' => 'Yes',
          'false' => 'No'
        ),
        'placeholder' => '',
        'helper' => '',
        'supplemental' => 'Should a new user be created if they don\'t yet exist?'
      ),
      array(
        'uid' => 'COGNITO_ADD_USER_TO_NEW_BLOG',
        'label' => 'Add user to new blog',
        'section' => 'new_user_settings',
        'type' => 'select',
        'options' => array(
          'true' => 'Yes',
          'false' => 'No'
        ),
        'placeholder' => '',
        'helper' => '',
        'supplemental' => 'Should a user be linked to the new blog if it is not there?'
      ),
      array(
        'uid' => 'COGNITO_USERNAME_ATTRIBUTE',
        'label' => 'Username Attribute',
        'section' => 'new_user_settings',
        'type' => 'text',
        'options' => false,
        'placeholder' => 'email',
        'helper' => '',
        'supplemental' => 'The attribute to use as a WordPress "Username"'
      ),
      array(
        'uid' => 'COGNITO_PASSWORD_LENGTH',
        'label' => 'Password Length',
        'section' => 'new_user_settings',
        'type' => 'text',
        'options' => false,
        'placeholder' => '18',
        'helper' => '',
        'supplemental' => 'Length of randomly generated user passwords'
      ),
      array(
        'uid' => 'COGNITO_ALLOW_INSECURE_PASSWORD',
        'label' => 'Allow Insecure Passwords',
        'section' => 'new_user_settings',
        'type' => 'select',
        'options' => array(
          'true' => 'Yes',
          'false' => 'No'
        ),
        'placeholder' => '',
        'helper' => '',
        'supplemental' => 'Use insecure password generation if a cryptographically secure method isn\'t available'
      ),
      array(
        'uid' => 'COGNITO_PASSWORD_CHARS',
        'label' => 'Password Characters',
        'section' => 'new_user_settings',
        'type' => 'text',
        'options' => false,
        'placeholder' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'helper' => '',
        'supplemental' => 'The characters that will be used for random password generation'
      )
    );
    foreach( $fields as $field ) {
      add_settings_field( $field['uid'], $field['label'], array( $this, 'field_callback' ), 'cognito_login_fields', $field['section'], $field );
      register_setting( 'cognito_login_fields', $field['uid'] );
    }
  }

  public function field_callback( $arguments ) {
    $value = Cognito_Login_Options::get_plugin_option( $arguments['uid'] ); // Get the current value, if there is one
    if( $value === FALSE || $value === '' ) { // If no value exists
      $value = $arguments['default']; // Set to our default
      update_option($arguments['uid'], $value);
    }

    // Check which type of field we want
    switch( $arguments['type'] ){
      case 'text': // If it is a text field
        printf( '<input style="width: 40em;" name="%1$s" id="%1$s" type="%2$s" placeholder="%3$s" value="%4$s" />', $arguments['uid'], $arguments['type'], $arguments['placeholder'], $value );
        break;
      case 'textarea': // If it is a textarea
        printf( '<textarea style="width: 40em;" name="%1$s" id="%1$s" placeholder="%2$s" rows="5" cols="50">%3$s</textarea>', $arguments['uid'], $arguments['placeholder'], $value );
        break;
      case 'select': // If it is a select dropdown
        if( ! empty ( $arguments['options'] ) && is_array( $arguments['options'] ) ){
          $options_markup = '';
          foreach( $arguments['options'] as $key => $label ){
            $options_markup .= sprintf( '<option value="%s" %s>%s</option>', $key, selected( $value, $key, false ), $label );
          }
          printf( '<select style="width: 40em;" name="%1$s" id="%1$s">%2$s</select>', $arguments['uid'], $options_markup );
        }
        break;
    }

    // If there is help text
    if( $helper = $arguments['helper'] ){
      printf( '<span class="helper"> %s</span>', $helper ); // Show it
    }

    // If there is supplemental text
    if( $supplemental = $arguments['supplemental'] ){
      printf( '<p class="description">%s</p>', $supplemental ); // Show it
    }
  }
}
new Cognito_Login_Settings();