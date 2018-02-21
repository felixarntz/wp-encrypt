[![WordPress plugin](https://img.shields.io/wordpress/plugin/v/wp-encrypt.svg?maxAge=2592000)](https://wordpress.org/plugins/wp-encrypt/)
[![WordPress](https://img.shields.io/wordpress/v/wp-encrypt.svg?maxAge=2592000)](https://wordpress.org/plugins/wp-encrypt/)
[![Code Climate](https://codeclimate.com/github/felixarntz/wp-encrypt/badges/gpa.svg)](https://codeclimate.com/github/felixarntz/wp-encrypt)
[![Latest Stable Version](https://poser.pugx.org/felixarntz/wp-encrypt/version)](https://packagist.org/packages/felixarntz/wp-encrypt)
[![License](https://poser.pugx.org/felixarntz/wp-encrypt/license)](https://packagist.org/packages/felixarntz/wp-encrypt)

WP Encrypt
==========

**This plugin is no longer maintained.**

Generate and manage SSL certificates for your WordPress sites (or networks) for free with this Let's Encrypt client.

You can download the latest version from the [WordPress plugin repository](http://wordpress.org/plugins/wp-encrypt/). If you prefer to install it from Github, make sure to run `composer install` before using it in order to download the necessary dependencies.

Prerequisites
-------------

Your WordPress site needs to have the `curl` and `openssl` PHP extensions installed. You also need to ensure that WordPress may write the following locations:

* the directory that will contain the keys and certificates; by default this will be `../letsencrypt` (relative to the site's root directory); this location can be overwritten to any other location by using the constant `WP_ENCRYPT_SSL_CERTIFICATES_DIR_PATH`
* the directory that will contain challenges to verify ownership of a domain; this will be `/.well-known` (relative to the site's root directory); furthermore you need to ensure that this directory is publicly readable

Contributions and Bugs
----------------------

If you have ideas on how to improve the plugin or if you discover a bug, I would appreciate if you shared them with me, right here on Github. In either case, please open a new issue [here](https://github.com/felixarntz/wp-encrypt/issues/new) - or create a pull-request to provide a fix for it yourself.

You can also contribute to the plugin by translating it. Simply visit [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/wp-encrypt) to get started.
