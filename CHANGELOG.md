# Versione 1.11

- Aggiunto lo shortcode `[cognito_login_url]` e la function statica `Cognito_Shortcodes::cognito_login_url()`
  per generare solo il link, usabile liberamente nel tema una volta che il plugin stesso è stato attivato

      Si può usare nei file del tema per esempio usando la sintassi

          (is_plugin_active( 'cognito-login/cognito-login.php' ) ? Cognito_Shortcodes::cognito_login_url() : wp_login_url())

      > NB: in ogni sito il nome della cartella è diverso. Agire di consguenza !!!!

- Rimosso il JS per nascondere il form di login, a favore del css con `display: none;`

- Ora il form di login viene manipolat via PHP + css, quindi NON serve più modificare il wp-login.php
  Anzi: l'eventuale echo dello shortcode nel file `wp-login.php` `[cognito_login]` va proprio rimosso

- E' stata fatta pulizia nel codice raggruppando alcuni metodi in nuove classi

- E' stata rimossa la pagina delle impostazioni, che comunque era in sola lettura ed incompleta
