# Installazione in ambiente di PRODUZIONE

## NB: Questa guida NON vale per il circuito Edagricole

Assicurarsi di avere la versione più recente di questo plugin

    git checkout main
    git pull

Creare dentro alla cartella `plugins` di Wordpress una sottocartella `cognito-login`.

Copiare l'intero _contenuto_ di questo progetto all'interno della cartella `cognito-login`

> NB: Assicurarsi di \_non copiare la cartella `.git` (su alcuni sistemi è nascosta)

Copiare nel file `wp-config.production.php` il seguente snippet

```php
/* COGNITO LOGIN */
define('COGNITO_USER_POOL_ID', 'eu-central-1_E4wrPfeRN');
define('COGNITO_APP_CLIENT_ID', '421g03s20a86s3ni5dp6ph0k5c');
define('COGNITO_REDIRECT_URL', '[[ indirizzo home page ]]');
define('COGNITO_APP_AUTH_URL', 'https://sso.tecnichenuove.it');
define('COGNITO_OAUTH_SCOPES', 'email+openid+phone');
define('COGNITO_HOMEPAGE', '[[ indirizzo home page ]]');
define('COGNITO_LOGIN_LINK_TEXT', "<span>Accedi con</span> <img src='https://static.tecnichenuove.it/common/gruppo_tn.webp'/>");
define('COGNITO_LOGIN_LINK_CLASS', 'cognito-login-link-tecnichenuove');
define('COGNITO_DISABLE_WP_LOGIN', 'true');
define('COGNITO_FORCE_AUTH', 'false');
define('COGNITO_NEW_USER', 'true');
define('COGNITO_ADD_USER_TO_NEW_BLOG', 'true');
define('COGNITO_USERNAME_ATTRIBUTE', 'email');
define('COGNITO_PASSWORD_LENGTH', '18');
define('COGNITO_ALLOW_INSECURE_PASSWORD', 'false');
define('COGNITO_PASSWORD_CHARS', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!$%&?^-_');
define('COGNITO_DOMAIN', 'https://amplifybroker-tn-prod.auth.eu-central-1.amazoncognito.com');
define('COGNITO_COOKIE_NAME', 'tn_cognito_production');
define('COGNITO_COOKIE_DOMAIN', '[[ dominio ]]');
define('COGNITO_IGNORED_REFERERS', ['http://management.tn.diemmea.com', 'http://18.192.100.203']);
define("COGNITO_JWT_KEYS", "{\"keys\":[{\"alg\":\"RS256\",\"e\":\"AQAB\",\"kid\":\"y51RK2j+\/MZpdY0twanXnyY\/e6D2SYqyBdAWHzIlRRI=\",\"kty\":\"RSA\",\"n\":\"wiLI8ktBIa9wyb7NBROLBAOFxc8D0md--SEQW8SFlaxwuScPrlnj5DRqfiJB2-njVyPOHvHZTQbm5bAatEKZueYp9O4wznYbpu3kSYP2Brsi8MGFovOSIUqr-fuSj6eD6qkeb9w0QkuLdbeROD6mFXEgR3dAiaNdrBzpvuYc7alm-o_CYhnoNb9Pe4KSwaDvID-CgqpAjnKwInFzyvLBkMgkYysX53tznJ-KPbUl4GjdXT5yQOKOdrF68QOwOPFw4WlH_QEYuTG4JgRU3_1lwGrEZAA5CdHmdQ1GBBzMpGWkN18aqLpaQocb3B1ArI5C9W4tmN3kZc4a4EuiHf9izQ\",\"use\":\"sig\"},{\"alg\":\"RS256\",\"e\":\"AQAB\",\"kid\":\"+9yh+OXZry9RPWQsL\/b6NzpxPI+yRQQxfPzewCo\/M0E=\",\"kty\":\"RSA\",\"n\":\"zHVVeNJrqeKTzqZ6v4rFXjcFdXg84vnNrR2ena0KkwLau0PD9MV4re0cGQ2DGDC5n2ZcffotGa3CQXigw1poQ04AEv_w1z7hEn2VWDo10AXcjct8SMkwifdcbMoWmj6d_oUlS4HNajndx2xoXfBb8pOmMea5N7-OZss4binAqZwdCm3L3ku9cjwBZNhSyw_Cm83V2RBZEOchJgbPAEt21F3rY1cl0mW5CA6mdvgUI6EmYRS-xPjfS1NBfzP7UGPxpmEmnrgWIe9Y9kgcnNixKyTQ5Fj8I7v3iDdbXRr0QNLor4nvf7-b_fD9UPyvWl7-HxYxpM3M4-aS0lkQGgU7_Q\",\"use\":\"sig\"}]}");
/* END COGNITO LOGIN */
```

Questo va copiato prima delle ultime righe, cioè deve essere copiato prima di questo blocco di codice

```php
/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
```

Sostitutire

- `[[ indirizzo home page ]]` con l'indirizzo completo della homepage, senza slash (`/`) finale.

  Per esempio: `https://www.transizioneenergeticanews.it`

- `[[ dominio ]]` con il solo dominio del sito, senza `www`.

  Per esempio: `transizioneenergeticanews`

Rilasciare

    git push

Attendere il completamento del deploy automatico di Github

## Sostituzione dei plugin che toccano il login a favore dell'SSO

Accedere a Wordpress

Disattivare, se presenti, i plugin che personalizzano il login

- `join my multisite`
- `custom login`

Attivare il plugin `Cognito Login`

Questo fa si che nella pagina `/wp-login.php` scompaia il form di login a favore del link verso l'accesso in SSO, già stilizzato.

## Strumenti messi a disposizione dal plugin

**Shortcode**

- `[cognito_login]` => pulsante di redirect verso il login (mostra il logout se già loggati); è già pre-stilizzato e non personalizzabile
- `[cognito_login_url]` => solo link, da usare negli href

> Lato SSO, la pagina di login ha comunque un link alla registrazione e viceversa

**Variabili Js**

- `loginInfoObject.loginUrl`: string
- `loginInfoObject.signupUrl`: string
- `loginInfoObject.isLoggedIn`: boolean

**Function PHP**

- `Cognito_Shortcodes::cognito_login_url()` => restituisce solo il link alla pagina di **login**; restituisce una stringa
- `Cognito_Shortcodes::cognito_login_url(true)` => restituisce il link alla pagina di **registrazione**

**Logout**

Il normale form di logout di Wordpress continuerà a funzionare.

> In tecnichese: Per intenderci, il link `Esci` di Wordpress, nella barra di
> navigazione, è in realtà
> un `FORM` HTML con una `action` e metodo `POST` per utilizzare un `wpnonce` per
> motivi di sicurezza.
> Se si utilizza il link alla `wp-login.php?action=logout` in GET e senza `wpnonce`,
> Wordpress chiede conferma all'utente se vuole davvero uscire; comunque sia
> il presente plugin non altera questo funzionamento

## Dopo l'installazione, customizzare l'aspetto del sito Wordpress

### Shortcode di `join-my-multisite`

> Questa sostituzione va eseguita nei _contenuti_ delle pagine e degli articoli,
> accedendo al backend di Wordpress.

Dovunque sia presente lo shortcode `[join-my-multisite]`, sostituirlo con `[cognito_login]`
per visualizzare `Accedi con Tecniche Nuove` pre-stilizzato

In alternativa, per avere maggiore controllo, inserire html e css a piacere ed utilizzare
lo shortcode `[cognito_login_url]` per renderizzare solo l'indirizzo della pagina di
login tramite SSO; per esempio inserendolo nell'attributo `href` di un tag HTML `A`

### `Elementor`

Se si usa questo plugin per personalizzare il tema, accedere a `Template -> Theme builder` e cercare, per esempio negli `Header` i punti dov'è presente il link a `login` / `registrati` o al `logout`.

Si consiglia di sostituire gli short code con un link html usando come `href` lo shortcode `[cognito_login_url]`.

Ladove serve usare del codice PHP, utilizzare `Cognito_Shortcodes::cognito_login_url()`; questa chiamata restituisce una stringa, che quindi può essere accodata ad altre stringhe o mandata in echo al bisogno

### Link nel tema

> Queste modifiche vanno eseguite nel _sorgente_ del sito

In tutti i punti del tema (sia mobile che desktop), cercare dove sono presenti i link alla
pagina di login, logout e registrazione

---

### Ricordarsi del tema mobile

Spesso i link al login sono presenti in più file e/o in più widget del tema, per coprire anche le varianti mobile dei menù. Ricordarsi di sostituire anche lì i link.

---