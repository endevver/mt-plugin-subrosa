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

$cfg = init_subrosa_config();

require( $cfg['mt_dir'] . DIRECTORY_SEPARATOR . $cfg['subrosa_path'] );

$mt = new SubRosa($cfg['mt_dir']."/mt-config.cgi", $_GET['blog_id']);

$mt->bootstrap();


function init_subrosa_config() {
    
    //kill_php_current_session();
    // show_current_request_info();

    ini_set('session.use_only_cookies', true); 
    session_name('SubRosa');
    session_start();

    require('subrosa_config.php');
    $cfg =& $config;

    if ( ! isset( $cfg['site_path'] ))
        $cfg['site_path'] = $_SERVER['DOCUMENT_ROOT'];

    if ( ! isset( $cfg['subrosa_path'] ))
        $cfg['subrosa_path'] = 'plugins/SubRosa/php/lib/SubRosa.php';

    if ( ! isset( $cfg['mt_dir'] ))
        $cfg['mt_dir'] = $_SERVER['MT_HOME'];

    if ( ! isset( $cfg['mt_dir'] ))
        die ("Cannot locate MT_HOME at " . __FILE__ . ", line " . __LINE__);

    return $cfg;
}



?>
