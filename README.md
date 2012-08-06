Passwordless
============

Passwordless allows users to sign up and log in using only email addresses, removing the need for them to remember yet another password.

I have created this plugin because it is of great use to me and I hope it will be of use to others but it might break things. Please use it with caution and at your own risk. If you find bugs please get in touch with me or better yet help fix it on [GitHub](https://github.com/iclanzan/Passwordless "Passwordless on GitHub").

**Login process**

Passwordless allows your site's users to log in using only their email addresses. After submitting the login form with the email address filled in, the user will receive an email containing a login link which can only be used once. Clicking that link results in the user getting logged in for one year or until he logs out. Unused login links expire after one day.

**New users**

New users will have accounts created for them automatically when they try to log in for the first time. In this case their email will also act as the username. Their visible name will be set to 'Annonymous' but can obviously be changed on the profile page.

**Login page**

The plugin changes the login page URI to `example.com/login` from the default `example.com/wp-login.php`. If permalinks are disabled the login page will instead be `example.com/?login`.

The page can be customized using some of the same filters and action hooks that the original login page used. If a total overhaul of the page is desired, placing a `login.php` template file in the currently active theme directory will override the plugin's login page.

**Filters**

I have included some new filter hooks to make it easy to change important aspects of the plugin's inner workings.

`passwordless_login_page` applied to the login page URI. Filter function argument: string containing the login page URI.

`passwordless_pass_length` applied to the length of the generated passwords/keys. Filter function argument: integer representing the number of characters.

`passwordless_key_expire` applied to the number of seconds a login key is valid for. Filter function argument: integer number of seconds.

`passwordless_login_redirect` applied to the URI where a user is sent after logging in. Filter function arguments: string URI, integer user id.

`passwordless_email_subject` applied to the subject of the email that gets sent to users logging in. Filter function arguments: string email subject, string email address.

`passwordless_email_body` applied to the body of the email that gets sent to users logging in. Filter function arguments: string email body, string email address.

##Installation

**Requirements**

The plugin requires WordPress 3.4 or higher.

**Installation**

- Upload the plugin to the `/wp-contents/plugins/` folder.
- Activate the plugin from the 'Plugins' menu in WordPress.

## Changelog

**1.0**
- Initial release