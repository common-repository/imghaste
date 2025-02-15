<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.imghaste.com/
 * @since      1.0.0
 *
 * @package    Imghaste
 * @subpackage Imghaste/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Imghaste
 * @subpackage Imghaste/includes
 * @author     IMGHaste <dev@imghaste.com>
 */
class Imghaste
{

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Imghaste_Loader $loader Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $plugin_name The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $version The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		$this->version = '1.2.0';
		if (defined('IMGHASTE_VERSION')) {
			$this->version = IMGHASTE_VERSION;
		}

		$this->plugin_name = 'imghaste';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Imghaste_Loader. Orchestrates the hooks of the plugin.
	 * - Imghaste_i18n. Defines internationalization functionality.
	 * - Imghaste_Admin. Defines all hooks for the admin area.
	 * - Imghaste_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies()
	{

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path(__FILE__) . 'includes/class-imghaste-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path(__FILE__) . 'includes/class-imghaste-i18n.php';


		/**
		 * This class includes general helpers
		 */
		require_once plugin_dir_path(__FILE__) . 'includes/class-imghaste-helpers.php';


		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path(__FILE__) . 'admin/class-imghaste-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path(__FILE__) . 'public/class-imghaste-public.php';


		/**
		 * The class responsible for Buffering using DOMDocument PHP
		 */
		require_once plugin_dir_path(__FILE__) . 'public/inc/class-imghaste-buffer.php';

		/**
		 * The class responsible to overwrite core funtions
		 */
		require_once plugin_dir_path(__FILE__) . 'public/inc/class-imghaste-overwrite.php';


		/**
		 * The class responsible for implementing Slim CSS
		 */
		require_once plugin_dir_path(__FILE__) . 'public/slimcss/class-imghaste-slimcss.php';

		/**
		 * The class responsible for implementing PWA
		 */
		require_once plugin_dir_path(__FILE__) . 'public/pwa/class-imghaste-pwa.php';

		$this->loader = new Imghaste_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Imghaste_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale()
	{

		$plugin_i18n = new Imghaste_i18n();

		$this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks()
	{

		$plugin_admin = new Imghaste_Admin($this->get_plugin_name(), $this->get_version());

		//Add Admin Scripts
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'imghaste_admin_enqueue_scripts');
		//Add Settings
		$this->loader->add_action('admin_init', $plugin_admin, 'imghaste_settings_init');
		//Add Options Page
		$this->loader->add_action('admin_menu', $plugin_admin, 'imghaste_options_page');
		//Add notice if the settings are incomplete
		$this->loader->add_action('admin_notices', $plugin_admin, 'imghaste_incomplete_settings_notice');

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks()
	{

		$supported_extensions = 'jpg|jpeg|png|svg|webp';
		$service_worker = 'image-service.ih.js';

		$plugin_public = new Imghaste_Public($this->get_plugin_name(), $this->get_version(), $supported_extensions, $service_worker);

		//Check for Admin Pages
		if (!is_admin()) {

			// Check for CDN Url
			$options = get_option('imghaste_options');
			if (isset($options['imghaste_field_cdn_url'])) {

				/*
				* Implement Core ImgHaste functionality with Service Worker
				*/

				// Add Service Worker Rewrite Rule
				$this->loader->add_action('init', $plugin_public, 'imghaste_sw_rewrite');

				// Generate Sevice Worker
				$this->loader->add_action('parse_request', $plugin_public, 'imghaste_sw_generate');

				// Add Feature Policy Header
				$this->loader->add_action('parse_request', $plugin_public, 'imghaste_feature_policy_header');

				// Register Service Worker
				$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'imghaste_sw_register');

				// Accept CH meta tag
				$this->loader->add_action('wp_head', $plugin_public, 'imghaste_accept_ch');

				//Replase src and srcset in img tags
				//$this->loader->add_filter('template_redirect', $plugin_public, 'imghaste_imgsrc_buffer_start');
				//$this->loader->add_filter('shutdown', $plugin_public, 'imghaste_imgsrc_buffer_end');

				/*
				* Implement Slim CSS functionality
				*/

				//Check for SlimCSS enabled
				/*
				if (isset($options['imghaste_field_slimcss'])) {
					if ($options['imghaste_field_slimcss'] == 1) {
						$plugin_slimcss = new Imghaste_Slimcss($this->get_plugin_name(), $this->get_version(), $supported_extensions, $service_worker);
						//Run SlimCSS
						$this->loader->add_action('init', $plugin_slimcss, 'imghaste_slimcss');
					}
				}
				*/
				
				/*
				* Implement Buffer rewrite functionality
				*/

				//Check for Rewrite Enabled
				if (isset($options['imghaste_field_rewrite'])) {
					if ($options['imghaste_field_rewrite'] == 1) {
						/* Core Overwrites */
						$plugin_overwrite = new Imghaste_Overwrite($this->get_plugin_name(), $this->get_version(), $supported_extensions, $service_worker);
						//Change Attachment Url
						$this->loader->add_filter('wp_get_attachment_url', $plugin_overwrite, 'imghaste_get_attachment_url');
						// Change Attachment Src
						$this->loader->add_filter('wp_get_attachment_image_src', $plugin_overwrite, 'imghaste_get_attachment_image_src');
						// Change Attachment SrcSet
						$this->loader->add_filter('wp_calculate_image_srcset', $plugin_overwrite, 'imghaste_calculate_image_srcset');
						// Change Image Url in Content - We do not use this for now
						$this->loader->add_filter('the_content', $plugin_overwrite, 'imghaste_get_the_content');
						/* Buffer Rewrites */
						$plugin_buffer = new Imghaste_Buffer($this->get_plugin_name(), $this->get_version(), $supported_extensions, $service_worker);
						// Initiate Buffer
						$this->loader->add_filter('template_redirect', $plugin_buffer, 'imghaste_buffer_start');
						// End Buffer
						$this->loader->add_filter('shutdown', $plugin_buffer, 'imghaste_buffer_end');
					}
				}

				//Check for Disabling Image Sizes
				if (isset($options['imghaste_field_disable_srcset_sizes'])) {
					if ($options['imghaste_field_disable_srcset_sizes'] == 1) {
						$this->loader->add_filter('max_srcset_image_width', $plugin_public, 'imghaste_disable_wp_responsive_images');
						$this->loader->add_filter('intermediate_image_sizes_advanced', $plugin_public, 'disable_wp_responsive_image_sizes');
					}
				}

				/*
				* Implement PWA funtionality
				*/

				//Check for PWA enabled
				if (isset($options['imghaste_field_pwa'])) {
					if ($options['imghaste_field_pwa'] == 1) {
						if (isset($options['imghaste_field_pwa_appname'])) {
							$plugin_pwa = new Imghaste_PWA();
							// Add PWA Manifest Rewrite Rule
							$this->loader->add_action('init', $plugin_pwa, 'imghaste_pwa_rewrite');
							// Generate Manifest File
							$this->loader->add_action('parse_request', $plugin_pwa, 'imghaste_pwa_generate');
							//Add manifest link to header
							$this->loader->add_action('wp_head', $plugin_pwa, 'imghaste_add_manifest_to_wp_head', 0);
						}
					}
				}
			}
		}
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run()
	{
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @return    string    The name of the plugin.
	 * @since     1.0.0
	 */
	public function get_plugin_name()
	{
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return    Imghaste_Loader    Orchestrates the hooks of the plugin.
	 * @since     1.0.0
	 */
	public function get_loader()
	{
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return    string    The version number of the plugin.
	 * @since     1.0.0
	 */
	public function get_version()
	{
		return $this->version;
	}

}
