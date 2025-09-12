# Versione 1.16

- In abbinamento a `Cognito_Shortcodes::cognito_login_url()` è ora disponibile anche `Cognito_Shortcodes::cognito_signup_url()`

# Versione 1.15

- Lato JS sono ora disponibili `loginInfoObject.loginUrl`, `loginInfoObject.signupUrl` e `loginInfoObject.isLoggedIn`
- Lato PHP ora la function `login_url` accetta un boolean, di default `false`, che permette, se `true`, di ottenere il link
  alla pagina di signup invece che a quella di login

# Versione 1.14

- Hofix per matchare sempre e solo l'indirizzo email, campo `user_email`, invece di tentare col nickname e/o usando la prima parte dell'indirizzo email.

# Versione 1.13

- Correzioni relativi alla compatibilità con php 8

# Versione 1.12

- Corretto path relativo a `cognito-login-wp-login.css` per lo shortcode `[cognito_login]`

# Versione 1.11

- Aggiunto lo shortcode `[cognito_login_url]` e la function statica `Cognito_Shortcodes::cognito_login_url()`
  per generare solo il link, usabile liberamente nel tema una volta che il plugin stesso è stato attivato

      Si può usare nei file del tema per esempio usando la sintassi

          (is_plugin_active( 'cognito-login/cognito-login.php' ) ? Cognito_Shortcodes::cognito_login_url() : wp_login_url())

      > NB: in ogni sito il nome della cartella è diverso. Agire di conseguenza !!!!

- Rimosso il JS per nascondere il form di login, a favore del css con `display: none;`

- Ora il form di login viene manipolat via PHP + css, quindi NON serve più modificare il wp-login.php
  Anzi: l'eventuale echo dello shortcode `[cognito_login]` nel file `wp-login.php` va proprio rimosso durante l'upgrade alla v1.11

- E' stata fatta pulizia nel codice raggruppando alcuni metodi in nuove classi

- E' stata rimossa la pagina delle impostazioni, che comunque era in sola lettura ed incompleta
