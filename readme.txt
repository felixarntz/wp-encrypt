=== WP Encrypt ===

Plugin Name:       WP Encrypt
Plugin URI:        https://wordpress.org/plugins/wp-encrypt/
Author:            Felix Arntz
Author URI:        https://leaves-and-love.net
Contributors:      flixos90
Donate link:       https://leaves-and-love.net/wordpress-plugins/
Requires at least: 4.2
Tested up to:      4.6
Stable tag:        1.0.0-beta.7
Version:           1.0.0-beta.7
License:           GNU General Public License v3
License URI:       http://www.gnu.org/licenses/gpl-3.0.html
Tags:              lets encrypt, ssl, certificates, https, free ssl

Generate and manage SSL certificates for your WordPress sites for free with this Let's Encrypt client.

== Description ==

_WP Encrypt_ is an easy-to-use client for the new [Let's Encrypt](https://letsencrypt.org/) service which provides free SSL certificates for everyone. No reason to have an unprotected WordPress site any longer! [1]

Using the plugin, you can quickly acquire a new certificate for your site. Once you have registered and received a certificate, you can switch your site to HTTPS. [2]

The Let's Encrypt service only provides certificates that are valid for 90 days. However, you can always renew them - no limitations there. And with this plugin you don't even need to worry about that, the plugin will automatically renew existing certificates before they expire (as long as you want it to).

The plugin is fully compatible with Multisite and Multinetwork. In a Multisite it will take care of generating the certificate for all sites in the network. In a Multinetwork you will additionally have the option to generate the certificate for all sites in all networks. [3]

= Requirements =

This plugin requires you to run at least PHP 5.3 on your server. You also need to have the `cURL` and `OpenSSL` extensions active. Please check with your hosting provider if you're not sure whether your server meets these requirements or how to set them up. You also need to be able to adjust the server configuration to use the certificate the plugin obtains.

If you don't have permissions to modify the server configuration, you might be able to still use the certificates if your host provides an interface to upload your own SSL certificates. In that case you can simply upload the generated certificate files there.

= Notes =

[1] Almost no reason. You still need to be able to access and modify your server configuration to set up SSL and use the certificate the plugin obtained for you.

[2] The plugin does not automatically change your site to HTTPS. It obtains and the SSL certificate, but you still need to adjust your server configuration and change your site's URL setting to use HTTPS. As a guide, you can follow this [WP Beginner tutorial](http://www.wpbeginner.com/wp-tutorials/how-to-add-ssl-and-https-in-wordpress/) for example.

[3] The plugin currently generates the certificate for the entire setup in one step. Therefore it is most likely to fail on large setups with a huge amount of sites. This is the first thing on the list to be improved in a later version though.

== Installation ==

= As a regular plugin =

1. Upload the entire `wp-encrypt` folder to the `/wp-content/plugins/` directory or download it through the WordPress backend.
2. Activate the plugin through the 'Plugins' menu in WordPress (in a Multisite it can only be network-activated).

= As a must-use plugin =

If you don't know what a must-use plugin is, you might wanna read its [introduction in the WordPress Codex](https://codex.wordpress.org/Must_Use_Plugins) - don't worry, that's nothing purely for developers.

1. Upload the entire `wp-encrypt` folder to the `/wp-content/mu-plugins/` directory (create the directory if it doesn't exist).
2. Move the file `/wp-content/mu-plugins/wp-encrypt/wp-encrypt.php` out of its directory to `/wp-content/mu-plugins/wp-encrypt.php`.

Note that, while must-use plugins have the advantage that they cannot be disabled from the admin area, they cannot be updated through WordPress, so you're recommended to keep them up to date manually.

== Frequently Asked Questions ==

= How do I use the plugin? =

After plugin activation you will find a new admin page in the Settings menu where you can register, generate, renew and revoke certificates for your WordPress site. In a Multisite, this menu is not located in the regular admin, but in the network admin, and it will work for all sites in the network. On the admin page you will find a help tab on top which provides further information on how to get started.

= Why can't I save the certificate? =

The problem might be that WordPress is unable to write the certificate and save it on your server. By default WordPress needs to be able to write to the directories `../letsencrypt` and `.well-known` (both paths are relative to the site's root directory). If WordPress cannot write to these locations, it will show a warning on the plugin's settings page, and you will be asked to enter your filesystem credentials when necessary. However, note that in this case automatically renewing the certificate is not possible (you will have to do it manually then).

= How can I change the location where the keys and certificates are being stored? =

To change the directories where the certificates are being stored, please define a constant called `WP_ENCRYPT_SSL_CERTIFICATES_DIR_PATH` containing the desired path in your `wp-config.php`. This will override the default location. Note that if you change this after you have already registered a Let's Encrypt account / generated a certificate, you need to start over with this process.

= I have obtained my certificate, but my site is still regular HTTP! =

The plugin only acts as a connection between your WordPress site and Let's Encrypt - it is used to obtain the certificate. WordPress cannot modify your server configuration to use it, that's why you need to take care of it yourself. However, you will find basic instructions in the plugin. After adjusting your server configuration, you also need to switch your site to HTTPS.

= Something seems wrong and I would like to reset. How can I do that? =

The plugin allows you to completely reset it. This will delete all certificates and keys created by the plugin. You must not reset the plugin while your server is using any of those files - if you need to reset, first unassign the certificates in your server configuration. Because the reset functionality is a critical area, it is hidden by default. You can enable it by defining a constant `WP_ENCRYPT_ENABLE_DANGER_ZONE` and set it to true. After having done so, you will see a new section called "Danger Zone" on the settings page.

= Where should I submit my support request? =

I preferably take support requests as [issues on Github](https://github.com/felixarntz/wp-encrypt/issues), so I would appreciate if you created an issue for your request there. However, if you don't have an account there and do not want to sign up, you can of course use the [wordpress.org support forums](https://wordpress.org/support/plugin/wp-encrypt) as well.

= How can I contribute to the plugin? =

If you're a developer and you have some ideas to improve the plugin or to solve a bug, feel free to raise an issue or submit a pull request in the [Github repository for the plugin](https://github.com/felixarntz/wp-encrypt).

You can also contribute to the plugin by translating it. Simply visit [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/wp-encrypt) to get started.

== Screenshots ==

1. Configuring the plugin
2. Registering an account with Let's Encrypt
3. Generating a certificate with Let's Encrypt

== Changelog ==

= 1.0.0-beta.7 =
* Added: New filter `wpenc_addon_domains` allows filtering the domains to generate the certificate for
* Enhanced: If an account has already been registered, the plugin will now fetch the details instead of failing
* Enhanced: Let's Encrypt API errors are now being printed out for more transparent errors

= 1.0.0-beta.6 =
* Fixed: `cURL error 3: malformed` does not happen anymore on WordPress 4.6

= 1.0.0-beta.5 =
* Fixed: use new Multisite functions for WordPress 4.6

= 1.0.0-beta.4 =
* Added: a link to the Let's Encrypt Subscriber Agreement is now displayed in the admin interface
* Fixed: plugin now supports using the latest Let's Encrypt Subscriber Agreement from August 1st, 2016

= 1.0.0-beta.3 =
* Added: plugin main class instance can now easily be accessed via a `wpenc()` function
* Enhanced: the admin class instance is now publicly accessible through a `WPENC\App::admin()` method
* Enhanced: the admin screen and form POST urls are now managed by single dedicated methods respectively and can be filtered
* Fixed: use `wp_remote_get()` for challenge self check to be more error-prone

= 1.0.0-beta.2 =
* Added: a reset functionality has been introduced including UI (hidden by default)
* Enhanced: error messages provide more detail about what exactly went wrong
* Tweaked: updated the plugin initialization library to be compatible with WordPress 4.6
* Fixed: fixed an error where the filesystem credentials form was posting to the wrong location

= 1.0.0-beta.1 =
* First official beta

== Additional Credit ==

The core of this plugin is mostly a rewrite of [analogic/lescript](https://github.com/analogic/lescript/blob/master/Lescript.php), a PHP client for Let's Encrypt. The plugin's implementation includes fixes to work properly in WordPress, plus it provides some enhancements over the original client, like a reusable class hierarchy.
