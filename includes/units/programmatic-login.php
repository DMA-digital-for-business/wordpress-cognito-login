<?php

// this functions allow autologin of SSO-logged users in a website of same netword
// even if user is never came here
// Requirement: all websites must save LOGGED_IN_COOKIE with same hash and same
// domain (on 2nd level)
//
// NB: this cannot work on sites on different 2nd level domains
// 
// eg:
// 
// Cross-domain login for *.wordpress.local
// define( 'COOKIE_DOMAIN', 	 'wordpress.local');
// define( 'COOKIEHASH', 		   md5(AUTH_SALT));
function cognito_set_current_user(){
  
  $user = wp_get_current_user();
  $cookie_elements = explode( '|', $_COOKIE[LOGGED_IN_COOKIE] );

  if ( count( $cookie_elements ) === 4 ) {

    $username = $cookie_elements[0];
    $user = get_user_by( 'email', $username);
    
    if ( is_a( $user, 'WP_User' ) ) {

      wp_set_current_user( $user->ID, $user->user_login );
      
      if ( is_user_logged_in() ) {
        return true;
      }

    } else {

      // l'utente non esiste in db, lo creo
      $parsed_token = [
        'email' => $username,
      ];  
      $user = Cognito_Login_User::create_user( $parsed_token );
      
      wp_set_current_user( $user->ID, $user->user_login );
      
      if ( is_user_logged_in() ) {
        return true;
      }

    }
  }
}

add_action('set_current_user', 'cognito_set_current_user');

/**
 * Class contains functions for performing automatic logins
 */
class Cognito_Login_Programmatic_Login {
  /**
   * Programmatically logs a user in. Algorithm originally from Ian Dunn
   * https://gist.github.com/iandunn/8162246
   * 
   * @param string $username
   * @return bool True if the login was successful; false if it wasn't
   */
  public static function login( $username ) {
    if ( is_user_logged_in() ) {
      wp_logout();
    }
    
    add_filter( 'authenticate', array( 'Cognito_Login_Programmatic_Login', 'allow_programmatic_login' ), 10, 3 );	// hook in earlier than other callbacks to short-circuit them
    $user = wp_signon( array( 'user_login' => $username ) );
    remove_filter( 'authenticate', array( 'Cognito_Login_Programmatic_Login', 'allow_programmatic_login' ), 10, 3 );
    
    if ( is_a( $user, 'WP_User' ) ) {
      wp_set_current_user( $user->ID, $user->user_login );
      
      if ( is_user_logged_in() ) {
        return true;
      }
    }
  
    return false;
  }

  /**
   * An 'authenticate' filter callback that authenticates the user using only the username.
   *
   * To avoid potential security vulnerabilities, this should only be used in the context of
   * a programmatic login, and unhooked immediately after it fires.
   * 
   * @param WP_User $user
   * @param string $username
   * @param string $password
   * @return bool|WP_User a WP_User object if the username matched an existing user, or false if it didn't
   */
  public static function allow_programmatic_login( $user, $username, $password ) {
    return get_user_by( 'login', $username );
  }
}
