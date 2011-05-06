<?php

// SubRosa request gatekeeper script
//
// This script bootstraps the SubRosa framework, inspects the
// incoming request and handles it based on available information
// and specified policy.  It can handle requests for both statically
// and dynamically generated content and does one of the following:
//
//    * Facilitates delivery (and compilation, if needed) of the
//      requested resource.
//    * Deny the request outright, with an optional error page.
//    * Modify the request with a redirect (e.g. to a login page)

// TEST CODE ----------------
// if ($_GET['cap']) {
//     print_r($_GET);
//     exit;
//     Array ( [blog_id] => 1 [file] => /var/www/vhosts/calcharters.org/httpdocs/StrategicSnapshot.pdf [uri] => /StrategicSnapshot.pdf [eval] => [cap] => StrategicSnapshot.pdf )
// }
// --------------------------

// Handle mt-preview URLs by not handling them
// /2010/06/mt-preview-d1c087f22262e5264c6b57e21ae1c84edeccd02d.html?083153
if (! preg_match('/\/mt-preview-[A-Za-z0-9]+\.html\?[0-9]+/',
                 $_SERVER['REQUEST_URI'])) {

    // Initialize SubRosa and handle request
    $subrosa_config = init_subrosa_config();
    $cfg            =& $subrosa_config;
    require_once( $cfg['subrosa_path'] );
    handle_request();
}

function handle_request() {
    global $subrosa_config, $cfg, $mt;
    apache_setenv('SUBROSA_EVALUATED', 1);
    apache_note('SUBROSA_EVALUATED',  '1');
    $_SERVER['SUBROSA_EVALUATED']    = 1;

    $mt = new SubRosa( null, $_SERVER['SUBROSA_BLOG_ID'] );
    if (isset($_GET['debug'])) $mt->debugging = true;
    $mt->bootstrap();
}

function init_php_ini() {

  // Even when display_errors is on, errors that occur
  // during PHP's startup sequence are not displayed.
  // It's strongly recommended to keep display_startup_errors
  // off, except for debugging.
  ini_set('display_startup_errors', true); // off

  // This determines whether errors should be printed to the screen as part of
  // the output or if they should be hidden from the user.
  //  Note: Although display_errors may be set at runtime (with ini_set()), it //
  //  won't have any affect if the script has fatal errors. This is because the
  //  desired runtime action does not get executed.
  ini_set('display_errors', isset($_GET['debug']) ? true : false);

  // Tells whether script error messages should be logged to
  // the server's error log or error_log. This option is thus
  // server-specific.
  ini_set('log_errors', true);           // on

  // Enabling this setting prevents attacks involved passing
  // session ids in URLs. Defaults to true in PHP 5.3.0
  ini_set('session.use_only_cookies', true);

}

function init_subrosa_config() {

    init_php_ini();
    require('subrosa_config.php');
    $cfg =& $config;

    if ( ! isset( $cfg['mt_dir'] ))
        $cfg['mt_dir'] = $_SERVER['MT_HOME'];

    if ( ! isset( $cfg['mt_dir'] ))
        die ("Cannot locate MT_HOME at " . __FILE__ . ", line " . __LINE__);

    if ( ! isset( $cfg['subrosa_path'] ))
        $cfg['subrosa_path'] = 'plugins/SubRosa/php/lib/SubRosa.php';
        
    // Do a quick check to see that the required Environment Variables have
    // been set. This should make misconfiguration more obvious.
    if ( ! isset( $_SERVER['SUBROSA_BLOG_ID'] ) )
        print "The environment variable 'SUBROSA_BLOG_ID' appears to not be set; this variable is required for SubRosa.";
    
    if ( ! isset( $_SERVER['SUBROSA_POLICY'] ) )
        print "The environment variable 'SUBROSA_POLICY' appears to not be set; this variable is required for SubRosa.";

    # Append mt_dir to subrosa_path to create an absolute filepath
    $cfg['subrosa_path'] = $cfg['mt_dir']
                         . DIRECTORY_SEPARATOR
                         . $cfg['subrosa_path'];
    return $cfg;
}

?>
