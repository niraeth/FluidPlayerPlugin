<?php
include_once __DIR__ . "/FluidPlayerLoader.php";
use FluidPlayerPlugin\File\MimeType;
use FluidPlayerPlugin\Network\Http;
use FluidPlayerPlugin\Wordpress\Wordpress;


// For easy troubleshooting, set these variables accordingly.
define ("AUTO_SKIP_INVIDEO_ADS", true);
define ("USE_SETTINGS_FROM_FILE", false);
define ("ALLOW_DOWNLOADS", false);


if( file_exists( __DIR__ . "/FluidPlayerExtra.php" ) ) {
	include_once __DIR__ . "/FluidPlayerExtra.php";
	$extra_code_exists = true;
} else {
	$extra_code_exists = false;
}




class FluidPlayerCore
{
	public static $index = 0;
	public static $useBlob = false;
	public static $logoPath = "";
	
	private static $config;		// we can have multiple configs.
	private $settings;	// but only 1 global setting for the plugin.
	
	const CONFIG_FOLDER 	= __DIR__ . "/../fluidplayer-configs"; 	// __DIR__ . "/configs";
	const SETTINGS_FOLDER 	= self::CONFIG_FOLDER; 
	const SETTINGS_PATH 	= self::SETTINGS_FOLDER . "/fluidplayer.st";		// __DIR__ . "/fluidplayer.st";
	
	//Paths
	const JQUERY_CDN_URL = "https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js";
	const FP_CDN_ROOT_URL = 'https://cdn.fluidplayer.com/v2/current';
	
	
	public $errors = [];
	
	
	public function __construct()
	{
		$fp_admin_menu = new FluidPlayerAdminMenu($this);
		$fp_admin_menu->init();
	}
	/*
	* Return settings as array 
	*
	* 	[
			'config_name' => 'abc'
		],
	*/
	public function load_settings()
	{
		if (!file_exists(self::SETTINGS_FOLDER)) {
			mkdir(self::SETTINGS_FOLDER, 0777, true);
		}
		// Create default settings if it doesn't exist.
		if( !file_exists(self::SETTINGS_PATH) ) {
			$this->save_settings([
				"config_name" => "default",
				//"disable_ads" => 
			]);
		}
	
		$serialized_settings = file_get_contents(self::SETTINGS_PATH);
		$settings = unserialize($serialized_settings);
		$this->settings = $settings;
		
		return $settings;
	}
	
	/*
	* Save settings.
	*/
	public function save_settings(array $args)
	{
		if( !isset($args['config_name']) ) $this->errors[] = "[save_settings] config_name field is not set in parameter 'args'";
	
		$str_settings = serialize($args);
		file_put_contents(self::SETTINGS_PATH, $str_settings);
	}
	
	/*
	*
	*/
	public function display_errors()
	{
		$errors_html = implode("<br/>", $this->errors);
		echo "<div style='color:red;'>" . $errors_html . "</div>";
		
		return $errors_html;
	}
	
	
	/*
	*
	*/
	public static function display_message_from_server()
	{
		// In case we want to pump down messages. 
		$url = "https://niraeth.com/tools/fluidplayer/messages.txt";
		if( Http::url_exists($url) ) {
			$messages = file_get_contents($url);
			if( !empty($messages) )
			{
				$messages = explode("\n", $messages);
				
				$messages_html = "";
				foreach($messages as $message)
					$messages_html .= "<div class='alert alert-info'><i class='fa fa-info-circle'></i>{$message}</div>";
				
				echo "<div>" . $messages_html ."</div>";
				return $messages_html;		
			}		
		}

		return "";
	}
	
	/**
	*
	*/
	public static function display_footer_message()
	{
		$url = "https://niraeth.com/tools/fluidplayer/footer_message.txt";
		if( Http::url_exists($url) ) {
			$html_content = file_get_contents($url);
			
			echo "<div>{$html_content}</div>";
			return $messages_html;	
		}
		return "";
	}
	
	/*
	* Look at our default.php for a sample of how a config file should look.
	*
	*/
	public function create_config_file(array $config, string $config_name)
	{
		$has_missing_parameter = false;
		if( !isset($config['vast_tag']) ) $has_missing_parameter = true;
		if( !isset($config['in_video']) ) $has_missing_parameter = true;
		//if( !isset($config['in_video']['login']) ) $has_missing_parameter = true;
		if( !isset($config['in_video']['idzone_300x250']) ) $has_missing_parameter = true;
		//if( !isset($config['in_video']['idsite']) ) $has_missing_parameter = true;
		
		if( $has_missing_parameter ) {
			$this->errors[] = "[create_config_file] Failed to create config file due to missing paramters.";
			return $this->errors;
		} 
	
		if ( !preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $config_name) ) {
			$this->errors[] = "[create_config_file] config_name={$config_name} is not allowed. Failed regex check.";
			return $this->errors;
		}
	
		$php_config_code = var_export($config, true);
		$php_config_code = "$"."{$config_name}"." = {$php_config_code};";
		
		$config_file_code = $php_config_code . "\n" . "return $"."{$config_name};";
		$config_file_code = "<?php\n" . $config_file_code . "\n?>";
		file_put_contents( self::CONFIG_FOLDER . "/" . $config_name . ".php", $config_file_code, LOCK_EX );
	}
	
	/*
	**
	*/
	public function load_config_file($config_name)
	{
		$config_file = $config_name . ".php";
		$config_path = self::CONFIG_FOLDER . "/" . $config_file;
		
		if( !file_exists($config_path) ) {
			$this->errors[] = "[init] Failed to load config file as the path={$config_path} doesn't exist.";
			$this->display_errors();
			
			return false; 
		} else {
			FluidPlayerCore::$config = include $config_path;
			return FluidPlayerCore::$config;
		}
	}
	
	/*
	* Return all config files in the config directory (check for .php extension),
	* Will automatically remove the '.php' extension as well.
	*/
	public function get_all_config_files()
	{
		$config_file  = $config_name . ".php";
		$config_dir   = self::CONFIG_FOLDER . "/";
		$config_files = [];
		
		if( file_exists($config_dir) ) {
			$config_files = array();
			foreach (glob($config_dir . "*.php") as $config_file) {
				$config_file	= basename($config_file); // glob returns the full path
				$config_file 	= str_replace(".php", "", $config_file);
				$config_files[] = $config_file;
			}
		}
		if( empty($config_files) ) {
			//$config_files[] = "No config files available";
			// probably dont need to add as it is intuitive
		}
		return $config_files;
	}
	
	
	private static function loadAssets()
	{
		wp_enqueue_script(
			'jquery-min-js',
			self::JQUERY_CDN_URL,
			[],
			false,
			true
		);
		
		wp_enqueue_script(
            'fluid-player-js',
            self::FP_CDN_ROOT_URL . '/fluidplayer.min.js',
            [],
            false,
            true
        );
		
        wp_enqueue_style('fluid-player-css', self::FP_CDN_ROOT_URL . '/fluidplayer.min.css');
	}
	
	private static function loadJSFiles()
	{
		echo '<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>';
		echo '<link rel="stylesheet" href="https://cdn.fluidplayer.com/v2/current/fluidplayer.min.css" type="text/css"/>';
		echo '<script src="https://cdn.fluidplayer.com/v2/current/fluidplayer.min.js"></script>';
	}
	
	public function init()
	{
		$settings = $this->load_settings();
	
		$config_name = $settings['config_name']; 
		$config_file = $config_name . ".php";
		$config_path = self::CONFIG_FOLDER . "/" . $config_file;
		
		// Create default automatically if it doesn't exist 
		if( $config_name === 'default' ) {
			if( !file_exists($config_path) ) {
				$config = array(
					'vast_tag' => '',
					'in_video' => array(
						'idzone_300x250' 	=> '',
						'vast_tag'			=> '',
					),
					'logo' => array(
						'imageUrl' => '',
						'position' => '',
						'opacity'  => '',
					),
				);
		
				$this->create_config_file($config, $config_name);
			}
		}
		
		if( !file_exists($config_path) ) {
			$this->errors[] = "[init] Failed to load config file as the path={$config_path} doesn't exist.";
			//return $this->errors;
			$this->display_errors();
		} else {
			FluidPlayerCore::$config = include $config_path;
			FluidPlayerCore::loadAssets();
			
			add_shortcode("fluidplayer", array($this, "attach_fluidplayer"));
			add_shortcode("fluid-player", array($this, "attach_fluidplayer"));

			// Disabling smart quotes filter for shortcode content
			add_filter( 'no_texturize_shortcodes', function () {
				$shortcodes[] = 'fluidplayer';
				$shortcodes[] = 'fluid-player';
				return $shortcodes;
			});
			
			// Disabling line breaks and paragraphs from shortcode content. Could have side effects!
			remove_filter( 'the_content', 'wpautop' );
			remove_filter( 'the_excerpt', 'wpautop' );		
		}
	}
	
	/*
	* Generate exoOpts array. 
	* Refer to a zone in admin.exoclick.com to see if there are any changes to required parameters.
	*
	* login and idsite are deprecated.
	*/
	public static function generate_options($idzone_300x250)
	{
		$options = <<<OPTIONS
		"var exoOpts = {                          " +
		"	cat: '2', 				        	  " +
		"	idzone_300x250: '{$idzone_300x250}',  " +
		"	preroll: {},                          " +
		"	pause: {},                            " +
		"	postroll: {},                         " +
		"	show_thumb: '1'                       " +
		"};                                       " ;
OPTIONS;
	
		return $options;
	}
	
	// Generate Exo-Click In-video Ad Script using JS.
	// To change scripts, replace script at options.innerHTML = 
	public static function attach_invideo_script_generator(array $invideo_args)
	{	
		$options = FluidPlayerCore::generate_options($invideo_args["idzone_300x250"]);

		$js_script_loader = <<<JS_SCRIPT_LOADER
		<script type='text/javascript'>
			var loadAds = function(parentElement) {
				var options = document.createElement('script');
				options.innerHTML = {$options}            //Quotes, and semi-colon is not necessary here ***
				
				var script = document.createElement('script');
				script.setAttribute('type', 'text/javascript');
				script.setAttribute('src', 'https://a.exosrv.com/invideo.js');
				
				parentElement.appendChild(options);
				parentElement.appendChild(script);			
			};
		</script>
JS_SCRIPT_LOADER;
		
		return $js_script_loader;
	}
	
	public function generate_logo_options(array $logo_args)
	{
		if( !isset($logo_args['imageUrl']) ) {
			$this->errors[] = "[generate_logo_options] the field 'imageUrl' is not set in logo_args parameter";
		}
		if( !isset($logo_args['position']) ) {
			$this->errors[] = "[generate_logo_options] the field 'position' is not set in logo_args parameter";
		}
		if( !isset($logo_args['position']) ) {
			$this->errors[] = "[generate_logo_options] the field 'opacity' is not set in logo_args parameter";
		}
				
		$imageUrl 	= $logo_args['imageUrl'];
		$position 	= $logo_args['position'];
		$opacity 	= $logo_args['opacity'];
	
		$logo = <<<LOGO
		logo: {
			imageUrl: '{$imageUrl}', // Default null
			position: '{$position}',  // Default 'top left'
			clickUrl: null,
			opacity: '{$opacity}', // Default 1
			mouseOverImageUrl: null, // Default null
			imageMargin: '10px', // Default '2px'
			hideWithControls: true, // Default false
			showOverAds: 'false', // Default false
		},	
LOGO;
		return $logo;
	}
	
	
	
	
	public static function generate_html_src($src, $mimetype, $base64)
	{
		if( $base64 )
		{
			// Get contents from url
			$contents = Http::get_url_contents($src);
			$base64_contents = base64_encode($contents);
			
			$html_src = "<source type='{$mimetype}' src='data:{$mimetype};base64,{$base64_contents}'/> \r\n";
		}
		else
		{
			$html_src = "<source src='{$src}' type='{$mimetype}' /> \r\n";
		}		
		return $html_src;
	}
	
	
	public static function generate_video_src($html_src, $options)
	{
		
	}
	

	
	public function attach_fluidplayer($attributes, $content)
	{
		$disable_ads 			= $this->settings['disable_ads'];
		$disable_fluidplayer 	= $this->settings['disable_fluidplayer'];
	
		$videoId = FluidPlayerCore::$index;
		$width = "100%"; // "640px";
		$height = "360px";// "360px";
		$has_logo_defined = false;
		
		$vast_file 					= FluidPlayerCore::$config["vast_tag"]; 
		$logo_args 					= FluidPlayerCore::$config['logo'];
		$in_video_args 				= FluidPlayerCore::$config['in_video'];
		$invideo_banner_vast_file 	= $in_video_args['vast_tag'];

		if( array_key_exists("show", $attributes) ) {
			if( strcasecmp($attributes['show'], 'no') == 0 ||
				strcasecmp($attributes['show'], 'false') == 0 ) {
					return "";
			}
		}
		
		$src = $attributes["src"];
		if( array_key_exists("video", $attributes) ) {	$src = $attributes["video"]; }
		if( array_key_exists("width", $attributes) ) {	$width = $attributes["width"]; }
		if( array_key_exists("height", $attributes) ) {	$height = $attributes["height"]; }
		if( array_key_exists("responsive", $attributes) ) {	$responsive = $attributes["responsive"]; }
		if( array_key_exists("vast_file", $attributes) ) {	$vast_file = $attributes["vast_file"]; }
		if( array_key_exists("base64", $attributes) ) { $base64 = $attributes["base64"]; }
		if( array_key_exists("controls", $attributes) ) { $controls = "controls"; } else { $controls = ""; }
		
		if( array_key_exists("logo", $attributes) ) {
			$logo = $attributes['logo'];
			$has_logo_defined = true; // not implemented
		}
		
		if( empty($width) ) {
			$width = "width: 100%;";
		} else {
			$width = "width: {$width};";
		}
		if( empty($height) ) {
			$height = "";
		} else { 
			$height = "height: {$height};";
		}

		
		$disable_vast_tag = false;
		if( empty($vast_file) ||
			$vast_file === 'null' ) {
			$disable_vast_tag = true;
		}
		
		$disabble_invideo_ads = false;
		if( empty($in_video_args) ) {
			$disable_invideo_ads = true;
		}
		
	
		$mimetype = MimeType::get_mime_type_by_url($src);
		if( MimeType::is_mimetype_video($mimetype) == FALSE &&		//e.g video/mp4
			MimeType::is_mimetype_application($mimetype) == FALSE ) //e.g application/x-mpegURL
		{
			// failed to get mime type, lets try the backup method
			$extension = pathinfo($src, PATHINFO_EXTENSION);
			$mimetype = MimeType::get_mime_type_by_extension($extension);
			
			if( MimeType::is_mimetype_video($mimetype) == FALSE &&
			MimeType::is_mimetype_application($mimetype) == FALSE ) {
				$mimetype = 'video/mp4'; //default to video/mp4
			}
		}
		
		$html_src = FluidPlayerCore::generate_html_src($src, $mimetype, $base64);
		
		
		// Make options 
		$fluidplayer_options = "";
			
		// Make content	
		$fluidplayer_content = "";
		if( !$disable_ads ) {
			$fluidplayer_content .= FluidPlayerCore::attach_invideo_script_generator($in_video_args); // in-video banner ads
		}
		
		
		
		$fluidplayer_content .= "<div id='fp-core-div-{$videoId}'> \r\n";
		$fluidplayer_content .= "<video id='fp-core-video-{$videoId}' style='{$height} {$width} min-width:360px; min-height: 360px;' {$controls} controlsList='nodownload' oncontextmenu='return false;'> \r\n";
		$fluidplayer_content .= 	$html_src;
		$fluidplayer_content .= "</video>\r\n";
		
		if( $extra_code_exists && ALLOW_DOWNLOADS ) 
		{
			$fluidplayer_content .= \FluidPlayerExtra::generate_download_html($download_src, $videoId);
		}
		
		// start of <script> tag 
		$fluidplayer_content .= "<script id='fp-core-script-{$videoId}' type='text/javascript'>\r\n";

		if( !empty($invideo_banner_vast_file) ) {
			$invideo_banner_vast_file_html = <<<INVIDEO_BANNER
				{
					"vAlign" : "bottom",
					"roll" : "preRoll",
					"vastTag" : "{$invideo_banner_vast_file}"
				},		
INVIDEO_BANNER;
		} else {
			$invideo_banner_vast_file_html = "";
		}

		
		$logo_options = $this->generate_logo_options($logo_args);
		$vast_file_html = <<<VAST_FILE
{
	vastOptions: {
		"adList": [
			{$invideo_banner_vast_file_html}
			{
				"roll": "preRoll",
				"vastTag": "{$vast_file}"
			},
			{
				"roll": "midRoll",
				"vastTag": "{$vast_file}",
				"timer": 8
			},
			{
				"roll": "midRoll",
				"vastTag": "{$vast_file}",
				"timer": 10
			},
			{
				"roll": "postRoll",
				"vastTag": "{$vast_file}"
			}
		],
		adText:                     'Skipping ad in 5 seconds',
		adTextPosition:             'top left',
		adCTAText:                  false,
		adCTATextPosition:          'bottom right',	
		maxAllowedVastTagRedirects: 1,
		adClickable: false, // Default true
		skipButtonCaption: 'Wait [seconds] more second(s)',
		skipButtonClickCaption: 'Skip Ad<span class="skip_button_icon"></span>',
		
		vastAdvanced: {
			vastLoadedCallback:       (function() { skiplater(); }),
			noVastVideoCallback:      (function() { console.log("no vast") }),
			vastVideoSkippedCallback: (function() { console.log("skipped ad") }),
			vastVideoEndedCallback:   (function() { console.log("vast ended") })
	    },
    },	
	
	layoutControls: {
		{$logo_options}
	},	
}
VAST_FILE;
		
		
		$should_load_video_ads = $disable_ads ? "//" : "";
		$should_load_invideo_ads = $disable_ads ? "" : ", {$vast_file_html}";
				
		$call_skipad_js = "";
		if( $extra_code_exists && AUTO_SKIP_INVIDEO_ADS ) {
			$call_skipad_js = \FluidPlayerExtra::generate_auto_skipads_html(6000);
		} 
		$should_load_fluidplayer = $disable_fluidplayer ? "false" : "true";
		
		
		$fluidplayer_content .= <<<FP_PLUGIN
		function skipad() {
			fluidPlayerClass.getInstanceById('fp-core-video-{$videoId}').pressSkipButton();
		}
		function skiplater() {
			{$call_skipad_js}
		}
		var fluidPlayerPlugin{$videoId} = function() { 
			var fpVideo{$videoId} = fluidPlayer('fp-core-video-{$videoId}' {$should_load_invideo_ads}
			);
		};
		
		(function defer() { 
			var should_load_fluidplayer = {$should_load_fluidplayer};
			if (typeof(fluidPlayer) != 'undefined' && should_load_fluidplayer) { 
				fluidPlayerPlugin{$videoId}(); 
				{$should_load_video_ads}loadAds(document.getElementById('fp-core-div-{$videoId}')); 
			} else { 
				setTimeout(defer, 50); 
			} 
		})();
		
FP_PLUGIN;
		
		$fluidplayer_content .= "</script>\r\n";
		$fluidplayer_content .= "</div>";

		//Output for debug if necessary
		//echo $fluidplayer_content;
		
		FluidPlayerCore::$index++;
		return $fluidplayer_content;
	}
}



class FP_Singleton
{
    private static $instances = array();
    protected function __construct() {}
    protected function __clone() {}
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }

    public static function get_instance()
    {
        $cls = get_called_class(); // late-static-bound class name
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static;
        }
        return self::$instances[$cls];
    }
}

class FluidPlayerAdminMenu extends FP_Singleton
{
	public $fluidplayer_core;

	/*
	* This is meant for the database performs an action on a row (e.g logging queries)
	*/
	public static function log($message)
	{
		date_default_timezone_set('America/Los_Angeles');
		
		$class_name = __CLASS__;
		$file_path = __DIR__ . '/logasd.txt';
		
		$date = new \DateTime();
		$datetime_string = $date->format('Y-m-d H:i:s');
		$prefix = "[{$class_name}][{$datetime_string}]";
		$message = $prefix . $message . PHP_EOL;

		$myfile = file_put_contents( $file_path, $message , FILE_APPEND | LOCK_EX);
	}

	
	public function __construct($fpcore)
	{
		$fluidplayer_core = $fpcore;
		$this->fluidplayer_core = $fluidplayer_core;
	}
	
	
	public function init() {
		add_action( 'admin_menu', array( $this, 'create_plugin_settings_page' ) );
		
	
		//initial_scrape hook
		add_action( 'admin_post_nopriv_save_settings'	, array( $this, 'save_settings' ) );
		add_action( 'admin_post_save_settings'			, array( $this, 'save_settings' ) );
						
		add_action( 'admin_post_nopriv_create_config'	, array( $this, 'create_config' ) );
		add_action( 'admin_post_create_config'			, array( $this, 'create_config' ) );

		add_action( 'admin_post_nopriv_load_config'		, array( $this, 'load_config' ) );
		add_action( 'admin_post_load_config'			, array( $this, 'load_config' ) );		
	}
	public function create_plugin_settings_page() {
		// Add the menu item and page
		$page_title = 'FluidPlayer Plugin';
		$menu_title = 'FluidPlayer';
		$capability = 'manage_options';
		$slug = 'fluidplayer';
		$callback = array( $this, 'plugin_settings_page_content' );
		$icon = 'dashicons-admin-plugins';
		$position = 100;

		add_menu_page( $page_title, $menu_title, $capability, $slug, $callback, $icon, $position );
	}
	
	public function plugin_settings_page_content() 
	{
		if( isset($_GET['message'] ) ) {
			$message = $_GET['message'];
			echo "<script>alert('{$message}');</script>";
		}
		$settings = $this->fluidplayer_core->load_settings();
		$config_name 					= $settings['config_name']; 
		$disabled_ads_checked 			= $settings['disable_ads'] ? 'checked' : '';
		$disabled_fluidplayer_chekced 	= $settings['disable_fluidplayer'] ? 'checked' : '';
		
		// if specified, this should take priority over the one loaded from settings.
		if( isset($_GET['config_name']) ) { 
			$config_name = $_GET['config_name'];
		}
		
		
		$config = $this->fluidplayer_core->load_config_file($config_name);
		
		$config_files = $this->fluidplayer_core->get_all_config_files();
		
		?>
		
		<!-- Latest compiled and minified CSS -->
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
		<!-- Latest compiled and minified JavaScript -->
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
		<style>
		@import url('//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css');
		</style>

		<div class="container">
			<div class="row">
				<h2>FluidPlayer Settings</h2>
			</div>
			<div class="row" id="fluidplayer_plugin_info">
				<div class="alert alert-info">
					<i class="fa fa-info-circle"></i>
					How this plugin works is that you have settings and configuration.
					You can only have 1 settings, but multiple configurations.
					
					Multiple configurations is so that you are able to switch between multiple vast tags etc (from different ad provider)
					easily.
				</div>
				<div class="alert alert-info">
					<i class="fa fa-info-circle"></i>
					Should you require help, go to <a href='https://niraeth.com/FluidPlayerPlugin/'>https://niraeth.com/FluidPlayerPlugin/</a>
					and I will try my best to assist you.
				</div>
				<?php 
				\FluidplayerCore::display_message_from_server();
				?>
				<br/>
			</div>
			
			<div class="row" id="fluidplayer-settings">
				<div style='display:inline-block'>
					<form method="post" action="<?php echo admin_url( 'admin-post.php' )?>">
						<fieldset>
							<legend><h4>Save Settings</h4></legend>
							Config Name : 
							<select id="config_files" name="settings_config_name">
								<option>Choose one</option>
								<?php
								// Iterating through the product array
								foreach($config_files as $config_file){
									$is_selected = ($config_file === $config_name) ? "selected='selected'" : "";
								?>
								<option <?php echo $is_selected; ?> value="<?php echo strtolower($config_file); ?>"><?php echo $config_file; ?></option>
								<?php
								}
								?>
							</select>
							<!--<input type='text' name='settings_config_name' value='default'/>-->
							<span style='font-style:bold;font-size:0.8em'>config name should be the same as the one created below</span><br/>
							
							<input type="checkbox" id="cbDisableAds" name="disable_ads" value="yes" <?php echo $disabled_ads_checked;?> >
							<label for="disable_ads">Disable Ads</label><br/>
							
							<input type="checkbox" id="cbDisableFluidPlayer" name="disable_fluidplayer" value="yes" <?php echo $disabled_fluidplayer_chekced;?>>
							<label for="disable_ads">Disable FluidPlayer (use normal html)</label><br/>
							
							<br/>
							<input type="hidden" name="action" value="save_settings"/>
							<input type="submit" name="submit" value="Save Settings"/>
						</fieldset>
					</form>	
				</div>			
			</div>
			
			<br/>
			<br/>
			<br/>
			
			<div id="fluidplayer-config" class="row">
				<div class="col-sm-4">
					<form method="post" action="<?php echo admin_url( 'admin-post.php' )?>">
						<fieldset>
							<legend><h4>Create / Update Config File</h4></legend>
					
							Name : <input type='text' name='config_name' value='<?php echo $config_name;?>' placeholder='default' /><br/>
							Vast Tag Filepath / URL : <input type='text' name='config_vast_tag' value='<?php echo $config['vast_tag'];?>' placeholder='e.g someurl'/><br/>
							<!-- Invideo_login : <input type='text' name='config_invideo_login' value='login username'/><br/> -->
							Invideo_idzone_300x250 : <input type='text' name='config_invideo_idzone' value='<?php echo $config['in_video']['idzone_300x250']; ?>' placeholder='e.g 2845654'/><br/>
							<!-- Invideo_idsite : <input type='text' name='config_invideo_idsite' value='705180'/><br/> -->
							Invideo_vast_tag : <input type='text' name='config_invideo_vast_tag' value='<?php echo $config['in_video']['vast_tag']; ?>' placeholder='e.g someurl'/><br/>

							
							Logo Path / URL<input type='text' name='config_logo_imageUrl' value='<?php echo $config['logo']['imageUrl']; ?>' placeholder='e.g https://niraeth.com/tools/fluidplayer/doglogo.png'/><br/>
							Logo Position <input type='text' name='config_logo_position' value='<?php echo $config['logo']['position']; ?>' placeholder='e.g top right'/><br/>
							Logo Opacity <input type='text' name='config_logo_opacity' value='<?php echo $config['logo']['opacity']; ?>' placeholder='e.g 0.8'/><br/>
											
							<br/>
							<input type="hidden" name="action" value="create_config"/>
							<input type="submit" name="submit" value="Create / Update Config File"/>
						</fieldset>
					</form>	
				</div>							
				<div class="col-sm-4">
					<form method="post" action="<?php echo admin_url( 'admin-post.php' )?>">
						<fieldset>
							<legend><h4>Load Config File</h4></legend>		
							Config Name : 
							<select id="config_files" name="config_name">
								<option >Choose one</option>
								<?php
								// Iterating through the product array
								foreach($config_files as $config_file){
									$is_selected = ($config_file === $config_name) ? "selected='selected'" : "";
								?>
								<option <?php echo $is_selected; ?> value="<?php echo strtolower($config_file); ?>"><?php echo $config_file; ?></option>
								<?php
								}
								?>
							</select>
							<br/>
							<input type="hidden" name="action" value="load_config"/>
							<input type="submit" name="submit" value="Load Config File"/>
						</fieldset>
					</form>	
				</div>					
			</div>
			
			
			
		</div> 
		<?php
	}
	/**
	 * @param string $url
	 * @param $query string|array
	 * @return string
	 */
	public function append_query_string_to_url(string $url, $query): string
	{
		// the query is empty, return the original url straightaway
		if (empty($query)) {
			return $url;
		}

		$parsedUrl = parse_url($url);
		if (empty($parsedUrl['path'])) {
			$url .= '/';
		}

		// if the query is array convert it to string
		$queryString = is_array($query) ? http_build_query($query) : $query;

		// check if there is already any query string in the URL
		if (empty($parsedUrl['query'])) {
			// remove duplications
			parse_str($queryString, $queryStringArray);
			$url .= '?' . http_build_query($queryStringArray);
		} else {
			$queryString = $parsedUrl['query'] . '&' . $queryString;

			// remove duplications
			parse_str($queryString, $queryStringArray);

			// place the updated query in the original query position
			$url = substr_replace($url, http_build_query($queryStringArray), strpos($url, $parsedUrl['query']), strlen($parsedUrl['query']));
		}

		return $url;
	}
	
	public function return_to_previous_page_with_message($message)
	{
		
	}
	
	public function save_settings()
	{
		$config_name	 		= $_POST['settings_config_name'];
		$disable_ads			= $_POST['disable_ads'];
		$disable_fluidplayer	= $_POST['disable_fluidplayer'];
		
		$disable_ads 			= ($disable_ads === 'yes') ? true : false;
		$disable_fluidplayer 	= ($disable_fluidplayer === 'yes') ? true : false;
		
		
		$this->fluidplayer_core->save_settings([
			'config_name' => $config_name,
			'disable_ads' => $disable_ads,
			'disable_fluidplayer' => $disable_fluidplayer,
		]);
		$message = "Successfully saved settings file.";
	
		$return_url = $this->append_query_string_to_url($_SERVER['HTTP_REFERER'], "message={$message}");
		header('Location: ' . $return_url);
		exit;
	}
	
	public function create_config()
	{
		$config_name = $_POST['config_name'];
		$config_vast_tag = $_POST['config_vast_tag'];
		
		$config_invideo_login = $_POST['config_invideo_login'];
		$config_invideo_idzone = $_POST['config_invideo_idzone'];
		$config_invideo_idsite = $_POST['config_invideo_idsite'];
		$config_invideo_vast_tag = $_POST['config_invideo_vast_tag'];
		
		$config_logo_imageUrl = $_POST['config_logo_imageUrl'];
		$config_logo_position = $_POST['config_logo_position'];
		$config_logo_opacity = $_POST['config_logo_opacity'];
		
		$config = array(
			'vast_tag' => $config_vast_tag,
			'in_video' => array(
				//'login' => $config_invideo_login,
				'idzone_300x250' 	=> $config_invideo_idzone,
				'vast_tag'			=> $config_invideo_vast_tag,
				//'idsite' => $config_invideo_idsite,
			),
			'logo' => array(
				'imageUrl' => $config_logo_imageUrl,
				'position' => $config_logo_position,
				'opacity'  => $config_logo_opacity,
			),
		);
		$this->fluidplayer_core->create_config_file($config, $config_name);
		
		$message = "Successfully saved config file {$config_name}";
	
		$return_url = $this->append_query_string_to_url($_SERVER['HTTP_REFERER'], "message={$message}");
		header('Location: ' . $return_url);
		exit;
	}
	
	public function load_config()
	{
		$config_name = $_POST['config_name'];
		
		$return_url = $this->append_query_string_to_url($_SERVER['HTTP_REFERER'], "config_name={$config_name}");
		header('Location: ' . $return_url);
		exit;		
	}
}


add_action('wp_footer', 'fluidplayer_footer_hook'); 
function fluidplayer_footer_hook() { 
	FluidPlayerCore::display_footer_message();
}








/*
*
*
*
*/
?>