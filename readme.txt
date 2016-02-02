=== WP Encrypt ===

Plugin Name:       WP Encrypt
Plugin URI:        https://wordpress.org/plugins/wp-encrypt/
Author URI:        http://leaves-and-love.net
Author:            Felix Arntz
Donate link:       http://leaves-and-love.net/wordpress-plugins/
Contributors:      flixos90
Requires at least: 4.0 
Tested up to:      4.4.1
Stable tag:        0.5.0
Version:           0.5.0
License:           GPL v3
License URI:       http://www.gnu.org/licenses/gpl-3.0.html
Tags:              wordpress, plugin, lets encrypt, ssl, https, free ssl

Generate and manage SSL certificates for your WordPress sites for free with this Let's Encrypt client.

== Description ==

_WP Encrypt_ is an easy-to-use client for the new [Let's Encrypt](https://letsencrypt.org/) service which provides free SSL certificates for everyone. No reason to have an unprotected WordPress site any longer!

Using the plugin, you can quickly acquire a new certificate for your site. Once you have registered and received a certificate, you can switch your site to HTTPS immediately. Note that the plugin will not handle this for you in the current version, so you have to take care of it yourself - it is a fairly simple process though (outlined in this [WP Beginner tutorial](http://www.wpbeginner.com/wp-tutorials/how-to-add-ssl-and-https-in-wordpress/) for example).

The Let's Encrypt service only provides certificates that are valid for 90 days. However, you can always renew them - no limitations there. However, you don't even need to do that yourself, the plugin will automatically renew existing certificates before they expire.

The plugin is also fully compatible with multisite.

= Requirements =

This plugin requires you to run at least PHP 5.3 on your server. You also need to have the `cURL` and `OpenSSL` extensions active. Please check with your hosting provider if you're not sure whether your server meets these requirements or how to set them up.

== Installation ==

1. Upload the entire `wp-encrypt` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Add all the post types you like, for example in your plugin or theme.

== Frequently Asked Questions ==

= How do I use the plugin? =

After plugin activation you will find a new admin page in the Settings section where you can register, generate, renew and revoke certificates for your WordPress site (or sites in your multisite).

= Where should I submit my support request? =

I preferably take support requests as [issues on Github](https://github.com/felixarntz/wp-encrypt/issues), so I would appreciate if you created an issue for your request there. However, if you don't have an account there and do not want to sign up, you can of course use the [wordpress.org support forums](https://wordpress.org/support/plugin/wp-encrypt) as well.

= How can I contribute to the plugin? =

If you're a developer and you have some ideas to improve the plugin or to solve a bug, feel free to raise an issue or submit a pull request in the [Github repository for the plugin](https://github.com/felixarntz/wp-encrypt).

You can also contribute to the plugin by translating it by using the "Translate" button on the right hand side of this page.

== Changelog ==

= 0.5.0 =
* First stable version

== Additional Credit ==

The core of this plugin is a rewrite of [analogic/lescript](https://github.com/analogic/lescript/blob/master/Lescript.php), a PHP client for Let's Encrypt. The plugin's implementation includes fixes to work properly in WordPress, plus it provides some enhancements over the original client, like a reusable class hierarchy.
