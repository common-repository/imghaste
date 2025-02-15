<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.imghaste.com/
 * @since      1.0.0
 *
 * @package    Imghaste
 * @subpackage Imghaste/public
 * @author     ImgHaste <dev@imghaste.com>
 */

class Imghaste_Public
{

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	protected $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	protected $version;

	/**
	 * The file extensions supported by the CDN
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $file_extensions The file extensions supported by the CDN
	 */
	protected $file_extensions;

	/**
	 * The Name of service worker
	 * @since    1.0.5
	 * @access   private
	 * @var      string $sw_name The Name of service worker
	 */
	protected $sw_name;

	/**
	 * Initialize the class and set its properties.
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version The version of this plugin.
	 * @param $file_extensions
	 * @param $sw_name
	 * @since    1.0.0
	 */
	public function __construct($plugin_name, $version, $file_extensions, $sw_name)
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->file_extensions = $file_extensions;
		$this->sw_name = $sw_name;
	}

	/**
	 * Rewrite Rule for Service Worker
	 *
	 * @since    1.0.0
	 */

	public function imghaste_sw_rewrite()
	{

		add_rewrite_rule('^/' . $this->sw_name . '$',
			'index.php?' . $this->sw_name . '=1',
			'top'
		);

	}

	/**
	 * Generate the Service Worker on the Fly
	 *
	 * @since    1.0.0
	 */
	public function imghaste_sw_generate($query)
	{

		if (!property_exists($query, 'query_vars') || !is_array($query->query_vars)) {
			return;
		}

		$query_vars_as_string = implode(',', $query->query_vars);

		//Check if sw_name in Query
		if (strpos($query_vars_as_string, $this->sw_name) !== false) {
			header('Content-Type: application/javascript');
			header('Service-Worker-Allowed: /');
			header('Cache-Control: max-age=3600');
			//Return SW as a JS file
			echo $this->imghaste_sw_template();
			exit();
		}

	}

	/**
	 * Generate a Client Hints Feature Policy
	 *
	 * @since    1.0.12
	 */
	public function imghaste_feature_policy_header($query)
	{

		$options = get_option('imghaste_options');
		$domain = $this->get_top_level_domain(get_site_url());
		$cdn_url = parse_url($options['imghaste_field_cdn_url'])['host'];


		$domains = ['imghaste.com', $domain, $cdn_url];
		$domains = array_unique($domains);
		$policies = ['width', 'downlink',];

		$feature_policy = "ch-width *;";

		header('Service-Worker-Allowed: /');
		header("Feature-Policy: {$feature_policy}");

	}

	protected function get_top_level_domain($domain)
	{
		$tld = preg_replace('/.*\.([a-zA-Z]+)$/', '$1', $domain);
		return trim(preg_replace('/.*(([\.\/][a-zA-Z]{2,}){' . ((substr_count($domain, '.') <= 2 && mb_strlen($tld) != 2) ? '2,3' : '3,4') . '})/im', '$1', $domain), './');
	}

	/**
	 * Template to Create the Service Worker
	 *
	 * @since    1.0.0
	 */

	public function imghaste_sw_template()
	{
		//Get CDN from Settings
		$options = get_option('imghaste_options');
		//Print Service Worker
		ob_start();
		$IH_SW_URL = "{$options['imghaste_field_cdn_url']}service-worker.js";
		echo "self.importScripts('{$IH_SW_URL}');";
		return apply_filters('imghaste_sw_template', ob_get_clean());
	}


	/**
	 * Register the Service Worker
	 *
	 * @since    1.0.0
	 */

	public function imghaste_sw_register()
	{
		$i = date('i');
		$i = intval($i / 10);
		$d = date('Ymd-H-') . $i;
		$options = get_option('imghaste_options');

		wp_enqueue_script('imghaste-register-sw', "{$options['imghaste_field_cdn_url']}sw/sdk.js?f={$this->sw_name}&pv=v{$this->version}-{$d}", [], null, true);
		//This is for local host Developement but you need SSL
		/*wp_enqueue_script( 'imghaste-register-sw', 'https://cdn.imghaste.com/sw/sdk.js?f='.$this->imghaste_get_base_folder().$this->sw_name.'&s='.$this->imghaste_get_base_folder(),array(), null, true );*/
	}


	/**
	 * Add meta in head for testing
	 *
	 * @since    1.0.0
	 */

	public function imghaste_accept_ch()
	{
		?>
		<meta http-equiv="Accept-CH" content="Width, Viewport-Width, DPR, Downlink, Save-Data, Device-Memory, RTT, ECT">
		<?php
	}


	/**
	 * Small Buffer to replace src and srcset in images with data-src και data-srcset
	 *  to optimize the image load from the Service Worker
	 *
	 * @since    1.1.2
	 */

	//Start Buffer
	function imghaste_imgsrc_buffer_start()
	{
		ob_start(array($this, 'imghaste_imgsrc_buffer_replace'));
	}

	//End Buffer
	function imghaste_imgsrc_buffer_end()
	{
		if (ob_get_length()) {
			ob_end_flush();
		}
	}

	//Run Buffer
	function imghaste_imgsrc_buffer_replace($content)
	{

		if (is_admin() || empty($content)) {
			return $content;
		}
		if (!class_exists('DOMDocument', false)) {
			return $content;
		}

		//Load HTML to php
		$doc = new DOMDocument(null, 'UTF-8');
		@$doc->loadHtml($content);

		//Replace Src in Image Tag
		$images = $doc->getElementsByTagName('img');
		foreach ($images as $img) {
			//Replace Img Src
			$url = $img->getAttribute('src');
			$img->setAttribute('data-src', $url);
			$img->removeAttribute('src');
			//Replace Img Srcset
			$srcset = $img->getAttribute('srcset');
			$img->setAttribute('data-srcset', $url);
			$img->removeAttribute('srcset');
		}

		//Replace Src in Picture Tag
		$pictures = $doc->getElementsByTagName('picture');
		foreach ($pictures as $picture) {
			//Replace Src in Sources
			$sources = $picture->getElementsByTagName('source');
			foreach ($sources as $source) {
				$srcset = $source->getAttribute('srcset');
				$source->setAttribute('data-srcset', $srcset);
				$source->removeAttribute('srcset');
			}
		}
		$doc->normalizeDocument();
		$buffered_content = @$doc->saveHTML($doc->documentElement);
		return '<!doctype html>' . $buffered_content;
	}

	/**
	 * Asset function to get wordpress folder
	 *
	 * @since    1.0.0
	 */
	public function imghaste_get_base_folder()
	{
		$url = $this->imghaste_get_site_url();
		$base_url = str_replace($_SERVER['SERVER_NAME'], '', $url);
		$base_url = str_replace('https://', '', $base_url);
		$base_url = str_replace('http://', '', $base_url);
		return $base_url;
	}


	/**
	 * Asset function to get site url
	 *
	 * @since    1.0.0
	 */

	public function imghaste_get_site_url()
	{
		return get_site_url() . '/';
	}


	/**
	 * Asset function for Image Haste Url
	 *
	 * @since    1.0.0
	 */
	public function imghaste_get_remote_image_url($image_url)
	{

		$root_site_url = $this->imghaste_get_site_url();
		$file_extensions = explode('|', $this->file_extensions);

		//Check if image is not hosted in the this domain
		$image_url_parsed = parse_url($image_url);
		$root_site_url_parsed = parse_url($root_site_url);

		if (isset($image_url_parsed['host']) && isset($root_site_url_parsed['host'])) {
			if (!$image_url_parsed['host'] == $root_site_url_parsed['host']) {
				return $image_url;
			}
		}

		//Check image if is accepted extensions
		$ext = pathinfo(
			parse_url($image_url, PHP_URL_PATH),
			PATHINFO_EXTENSION
		);
		if (!in_array($ext, $file_extensions)) {
			return $image_url;
		}

		//Get Options
		$options = get_option('imghaste_options');

		//Return Remote Url
		$new_image_url = str_replace($root_site_url, $options['imghaste_field_cdn_url'], $image_url);

		return $new_image_url;

	}


}
