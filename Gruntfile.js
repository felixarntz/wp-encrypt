'use strict';
module.exports = function(grunt) {
	grunt.initConfig({
		pkg:			grunt.file.readJSON('package.json'),
		banner:			'/*!\n' +
						' * <%= pkg.name %> <%= pkg.version %>\n' +
						' * \n' +
						' * <%= pkg.author.name %> <<%= pkg.author.email %>>\n' +
						' */',
		pluginheader:	'/*\n' +
						'Plugin Name: WP Encrypt\n' +
						'Plugin URI: <%= pkg.homepage %>\n' +
						'Description: <%= pkg.description %>\n' +
						'Version: <%= pkg.version %>\n' +
						'Author: <%= pkg.author.name %>\n' +
						'Author URI: <%= pkg.author.url %>\n' +
						'License: <%= pkg.license.name %>\n' +
						'License URI: <%= pkg.license.url %>\n' +
						'Text Domain: wp-encrypt\n' +
						'Domain Path: /languages/\n' +
						'Tags: <%= pkg.keywords.join(", ") %>\n' +
						'*/',
		fileheader:		'/**\n' +
						' * @package WPENC\n' +
						' * @version <%= pkg.version %>\n' +
						' * @author <%= pkg.author.name %> <<%= pkg.author.email %>>\n' +
						' */',

		clean: {
			scripts: [
				'assets/scripts.min.js'
			]
		},

		jshint: {
			options: {
				jshintrc: 'assets/.jshintrc'
			},
			scripts: {
				src: [
					'assets/scripts.js'
				]
			}
		},

		uglify: {
			options: {
				preserveComments: 'some',
				report: 'min'
			},
			scripts: {
				src: 'assets/scripts.js',
				dest: 'assets/scripts.min.js'
			}
		},

		usebanner: {
			options: {
				position: 'top',
				banner: '<%= banner %>'
			},
			scripts: {
				src: [
					'assets/scripts.min.js'
				]
			}
		},

		replace: {
			header: {
				src: [
					'wp-encrypt.php'
				],
				overwrite: true,
				replacements: [{
					from: /((?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:\/\/.*))/,
					to: '<%= pluginheader %>'
				}]
			},
			version: {
				src: [
					'wp-encrypt.php',
					'inc/**/*.php'
				],
				overwrite: true,
				replacements: [{
					from: /\/\*\*\s+\*\s@package\s[^*]+\s+\*\s@version\s[^*]+\s+\*\s@author\s[^*]+\s\*\//,
					to: '<%= fileheader %>'
				}]
			}
		}

 	});

	grunt.loadNpmTasks('grunt-contrib-clean');
	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-banner');
	grunt.loadNpmTasks('grunt-text-replace');

	grunt.registerTask('scripts', [
		'clean:scripts',
		'jshint:scripts',
		'uglify:scripts'
	]);

	grunt.registerTask('plugin', [
		'replace:version',
		'replace:header'
	]);

	grunt.registerTask('default', [
		'scripts'
	]);

	grunt.registerTask('build', [
		'scripts',
		'plugin'
	]);
};
