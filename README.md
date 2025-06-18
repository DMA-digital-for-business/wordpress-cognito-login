# Cognito Login

Requires at least: 5.4.2

Tested up to: 5.4.2

Requires PHP: 7.2

Stable tag: 1.3.0

License: GPL-3.0

WordPress plugin for integrating with Cognito for User Pools

## Use
Add the `[cognito_login]` shortcode to create a login link that will take the user to a Cognito
login screen

No attributes for the `[cognito_login]` shortcode are required, but the following attributes
are available:
- "text" - The inner html of the login link
- "class" - The class of the login link `<a>` tag

## New User Creation
Using default settings, a new user will be created when logging in for the first time. As part
of the user creation process, a password will be generated for the WordPress user. This password
is not saved

This plugin supports role mapping. The plugin will look for a `custom:role` attribute to
determine what role the user should be added to

## Note on Alias Domains
Login sessions are domain specific. For example, if your server is accessible from "your-site.com"
and "www.your-site.com", and a user logs in at your-site.com, if they go to "www.your-site.com" they
will no longer be considered "logged in"

## Username Matching
To match the user first the email is checked and if it is not there it matches the part before @. 
For example, the user "example-user" will be logged in with the email "example-user@email.com"

# Settings
The following configurations can be found in the settings menu

## Cognito Auth Settings
- "**User Pool ID**" - The Cognito User Pool ID of the pool managing users
- "**App Client ID**" - The Cognito App Client ID for the app client interfacing with this plugin
- "**App Client Secret**" - The Cognito App Client Secret for the app client interfacing with this
                        plugin. When creating the App Client, a secret _must_ be generated
- "**Redirect URL**" - Redirect URL that the Cognito App Client expects
- "**Web Authentication Base**" - Base URL for the Cognito authentication endpoint associated with
                              the Learning Pool
- "**OAuth Scopes**" - OAuth scopes to use. Only 'openid' is required

- "**Force Auth**" - Indicates whether the identity broker requires authentication or, if already logged in, maintains the session.  ("true" | "false")

## Plugin Settings
- "**Homepage**" - A URL to redirect users to once they have successfully logged in. Leave empty
               to not redirect the user
- "**Login Link Text**" - Default inner html for the login link
- "**Login Link Class**" - Default class for the login link `<a>` tag
- "**Disable WP Login**" - If the WordPress login system should be disabled and replaced with a link to the Cognito login page

## New User Settings
- "**Create New User**" - If a new user should be created when first logging in
- "**Username Attribute**" - User Attribute to use as a username
- "**Password Length**" - Length of the WP password generated for new users
- "**Allow Insecure Passwords**" - If a insecure randomizer should be used to generate passwords
                               when a cryptographically secure one is not available. Should
                               be left on the default (No) unless absolutely necessary
- "**Password Characters**" - Possible characters that can be used in the generated password

# User Profiling

The plugin supports user profiling functionality that redirects new users to a profiling page after their first login. This feature allows you to collect additional user information through a HubSpot form.

## Profiling Configuration

To enable user profiling, you need to configure the following settings:

### Environment Variables
- **COGNITO_PROFILING_ACTIVE** - Set to `true` to enable the profiling functionality
- **COGNITO_PROFILING_PATH** - Set the path where the profiling page will be located

**Note**: These environment variables must be defined in your `wp-config.php` file.

### Setup Steps

1. **Enable Profiling**: Set `COGNITO_PROFILING_ACTIVE` to `true` in your environment configuration

2. **Configure Path**: Set the desired path in `COGNITO_PROFILING_PATH` where the profiling page will be accessible

3. **Create HubSpot Form**: Create a HubSpot form that will collect the user profiling information

4. **Create WordPress Profiling Page**: Create a new page in WordPress and add the HubSpot form with the following JavaScript configuration:

```javascript
onFormSubmit: function() {
    console.log("Hubspot onFormSubmit");
    updateUserMeta();
}
```

## How It Works

1. When a user logs in for the first time through Cognito, they will be automatically redirected to the profiling page
2. The profiling page displays the HubSpot form for collecting additional user information
3. When the user submits the form, the `updateUserMeta()` function is called
4. The plugin's `profiling.php` module handles saving the profiling data to the database
5. Once profiling is completed, the user can proceed to the main application

## Profiling Data Storage

The profiling information collected through the HubSpot form is stored in the WordPress database using the plugin's profiling functionality. This data can be used for user segmentation, personalization, and other user experience enhancements.
