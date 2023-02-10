<?php
class Cognito_Login_Options {
    public static function get_plugin_option($key) {
      if(defined($key)) {
          return constant($key);
      }
      else {
          return false;
      }
    }
}
?>