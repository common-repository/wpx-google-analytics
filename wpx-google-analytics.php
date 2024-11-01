<?php

/*
Plugin Name: WPX Google Analytics
Description: Super easy method of adding the asynchronous Google Analytics tracking code to your website. Just add your ID, the plugin then adds the correct code on the right spot.
Version: 1.1
Author: Marek Bosman
Author URI: http://marekbosman.com
Text Domain: wpxgoogleanalytics
Doman Path: /languages/
*/

class WpxGoogleAnalytics {
  private $prefix;
  private $settings     = array();
  private $gaSettings   = array();
  // private $title        = 'WPX Google Analytics Settings';
  private $title;

  public function __construct()
  {
    $this->tag = strtolower(__CLASS__);
    $this->prefix = $this->tag . '_';

    // $this->title = __('WPX Google Analytics Settings', $this->tag);

    // Get the settings.
    $allowedSettings = array(
      'ua',
      'domain',
      'multiple_tld',
      'track_logged_users',
    );
    $this->settings = get_option($this->prefix . 'settings');
    foreach ($allowedSettings as $setting) if (!isset($this->settings[$this->prefix . $setting])) $this->settings[$this->prefix . $setting] = FALSE;

    register_deactivation_hook(__FILE__, array(&$this, 'deactivate'));
    
    if (is_admin())
    {
      add_action('admin_init', array(&$this, 'admin_init_settings'));
      add_action('admin_menu', array(&$this, 'add_admin_page'));
      add_filter("plugin_action_links_" . plugin_basename( __FILE__ ), array(&$this, 'set_plugin_actions'));
      add_action('after_setup_theme', array(&$this, 'load_language_files'));

      // If no setting for the domain is found, set it to the domain in the site's url.
      if (empty($this->settings[$this->prefix . 'domain']))
      {
        $this->settings[$this->prefix . 'domain'] = $this->getDefaultDomain();
      }
    }
    else
    {
      // Do not add the javascript code if no valid UA-code is specified.
      // And obviously, there's no need to display the javascript code on the admin :)
      if ($this->settings[$this->prefix . 'ua'])
      {
        $this->gaSettings['_setAccount'] = $this->settings[$this->prefix . 'ua'];
        if ($this->settings[$this->prefix . 'domain'])
        {
          $this->gaSettings['_setDomainName'] = $this->settings[$this->prefix . 'domain'];
          if ($this->settings[$this->prefix . 'multiple_tld']) $this->gaSettings['_setAllowlinker'] = 'true';
        }
        add_action('wp_head', array(&$this, 'add_ga_code'));
      }
    }
  }

  public function deactivate()
  {
      unregister_setting($this->prefix . 'settings', $this->prefix . 'settings', array(&$this, 'validate_settings'));
  }

  public function admin_init_settings()
  {
    register_setting($this->prefix . 'settings', $this->prefix . 'settings', array(&$this, 'validate_settings'));

    add_settings_section($this->prefix . 'settings', __('Settings'), array(&$this, 'settings_section'), $this->prefix . 'page');
    add_settings_field($this->prefix . 'ua', __('Google Analytics ID', $this->tag), array(&$this, 'settings_field_ua'), $this->prefix . 'page', $this->prefix . 'settings');
    add_settings_field($this->prefix . 'domain', __('Domain', $this->tag), array(&$this, 'settings_field_domain'), $this->prefix . 'page', $this->prefix . 'settings');
    add_settings_field($this->prefix . 'multiple_tld', __('Track multiple domains?', $this->tag), array(&$this, 'settings_field_multiple_tld'), $this->prefix . 'page', $this->prefix . 'settings');
    add_settings_field($this->prefix . 'track_logged_users', __('Track users that are logged in?', $this->tag), array(&$this, 'settings_field_track_logged_users'), $this->prefix . 'page', $this->prefix . 'settings');
  }

  /**
   * Validate the data that was provided by the user.
   */
  public function validate_settings($input)
  {
    $valid_input = array();

    // The UA-code should be in the following format: UA-1234-56.
    $ua = strtoupper(trim($input[$this->prefix . 'ua']));
    if (empty($ua) || preg_match('/^(UA\-)?[0-9]+\-[0-9]+$/', $ua, $matches))
    {
      if (!isset($matches[1]) && !empty($ua)) $ua = "UA-" . $ua;
      $valid_input[$this->prefix . 'ua'] = $ua;
    }
    else
    {
      add_settings_error(
        $ua,
        $this->prefix . 'id_error',
        'Invalid Google Analytics ID: ' . $ua . '<br>(Your ID should look something like this: UA-123456-7)',
        'error'
      );
    }

    // The domain should start with an alphanumeric character, and
    // further can only contain a . (dot) or - (dash).
    $domain = strtolower($input[$this->prefix . 'domain']);
    $domain = strip_tags($domain);
    $domain = preg_replace('/[^a-z0-9\.\-]/', '', $domain);
    if (empty($domain)) $domain = $this->getDefaultDomain();

    if (preg_match('/^[a-z0-9]/', $domain))
    {
      $valid_input[$this->prefix . 'domain'] = $domain;
    }
    else
    {
      add_settings_error(
        $domain,
        $this->prefix . 'domain_error',
        'Invalid domain: ' . $domain . '<br>(The domain can only contain letters, numbers and the special characters . (dot) and - (dash). The domain should start with a letter or number.',
        'error'
      );
    }

    $valid_input[$this->prefix . 'multiple_tld'] = $input[$this->prefix . 'multiple_tld'];
    $valid_input[$this->prefix . 'track_logged_users'] = $input[$this->prefix . 'track_logged_users'];

    return $valid_input;
  }

  /**
   * Intro text of the form.
   */
  public function settings_section()
  {
    echo '<p>' . __('Specify your Google Analytics ID (UA-code) to enable visitor tracking on your website. The correct (asynchronous) JavaScript code will automatically be added to the <code>&lt;head&gt;</code> section of your website.', $this->tag) . '</p>';
  }

  /**
   * Create each of the form fields.
   */
  public function settings_field_ua()
  {
    echo '<input type="text" id="' . $this->prefix . 'ua" name="' . $this->prefix . 'settings[' . $this->prefix . 'ua]" class="regular-text" value="' . $this->settings[$this->prefix . 'ua'] . '" />';
  }

  public function settings_field_domain()
  {
    echo '<input type="text" id="' . $this->prefix . 'domain" name="' . $this->prefix . 'settings[' . $this->prefix . 'domain]" class="regular-text" value="' . $this->settings[$this->prefix . 'domain'] . '" />';
  }

  public function settings_field_multiple_tld()
  {
    echo '<input type="checkbox" id="' . $this->prefix . 'multiple_tld" name="' . $this->prefix . 'settings[' . $this->prefix . 'multiple_tld]" value="1"';
    if ($this->settings[$this->prefix . 'multiple_tld']) echo ' checked="checked"';
    echo ' />';
    // echo ' (domain.com, domain.net, etc.)';
  }

  public function settings_field_track_logged_users()
  {
    echo '<input type="checkbox" id="' . $this->prefix . 'track_logged_users" name="' . $this->prefix . 'settings[' . $this->prefix . 'track_logged_users]" value="1"';
    if ($this->settings[$this->prefix . 'track_logged_users']) echo ' checked="checked"';
    echo ' />';
  }

  /**
   * Display the settings page.
   */
  public function show_settings()
  {
    ?>
    <div class="wrap">
      <div id="icon-options-general" class="icon32"></div>
      <h2><?php echo $this->title; ?></h2>

      <div style="float: left; width: 70%;">
        <form method="post" action="options.php">
          <?php settings_fields($this->prefix . 'settings'); ?>
          <?php do_settings_sections($this->prefix . 'page'); ?>
          <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Save Changes'); ?>" />
          </p>
        </form>
      </div>

      <div style="border: 1px solid #ccc; background: #eee; float: right; padding: 12px; width: 25%;">
        <h3><?php _e('Create a Google Analytics account', $this->tag); ?></h3>
        <p>
          <?php _e('If you did not yet create your Google Analytics account, go to Google and <a href="http://www.google.com/analytics/" target="_blank">create a free Analytics account</a>.', $this->tag); ?>
        </p>
      </div>

    </div>
    <?php
  }

  /**
   * Display the settings page in the backend.
   */
  public function add_admin_page()
  {
    // Add a link to the Settings menu.
    add_options_page(
      __('WPX Google Analytics', $this->tag),         // Page title
      __('WPX Google Analytics', $this->tag),         // Menu title
      'manage_options',                               // Capability
      'wpx_google_analytics',                         // Menu slug
      array(&$this, 'show_settings')                  // Callback function
    );
  }

  /**
   * Create the JavaScript code to use Google Analytics
   * on the webpage.
   */
  public function add_ga_code()
  {
    // There's no reason to create the code if there are no settings (i.e. no UA-code).
    if (empty($this->gaSettings)) return;

    // Return if we are logged in and do not wish to track logged in users.
    if (current_user_can('manage_options') && !$this->settings[$this->prefix . 'track_logged_users']) return;

    // Create the JavaScript code.
    $gaJavascript = "<script type=\"text/javascript\">\r\n\tvar _gaq = _gaq || [];\r\n\t";
    foreach ($this->gaSettings as $key => $value) $gaJavascript .= "_gaq.push(['" . $key . "', '" . $value . "']);\r\n\t";
    $gaJavascript .= "_gaq.push(['_trackPageview']);\r\n\t(function() {\r\n\t\tvar ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;\r\n\t\tga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';\r\n\t\tvar s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);\r\n\t})();\r\n</script>\r\n";

    echo $gaJavascript;
  }

  /**
   * Add a link to the Settings page, appearing in the actions
   * section (beneath the plugin's title).
   */
  public function set_plugin_actions($links)
  {
    $link = '<a href="options-general.php?page=wpx_google_analytics">' . __('Settings') . '</a>';
    array_unshift($links, $link);

    return $links;
  }
  
  /**
   * Set the location of the language files.
   */
  public function load_language_files()
  {
    $relativePath = dirname(plugin_basename(__FILE__)) . '/languages/';
    $relativePath = 'wpx-google-analytics/languages/';
    
    // load_plugin_textdomain($this->tag, false, dirname(plugin_basename(__FILE__)) . '/languages/');
    load_plugin_textdomain($this->tag, false, $relativePath);
    
    $this->title = __('WPX Google Analytics Settings', $this->tag);
  }

  private function getDefaultDomain()
  {
    $domain = get_option('siteurl');
    $domain = str_replace('http://', '', $domain);
    $domain = str_replace('www', '', $domain);

    return $domain;
  }

}

new WpxGoogleAnalytics();
