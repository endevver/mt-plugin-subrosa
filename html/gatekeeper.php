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

$subrosa_config = init_subrosa_config();
$cfg =& $subrosa_config;

require_once( $cfg['subrosa_path'] );

$mt = new SubRosa( $cfg['mt_cfg'], $_GET['blog_id'] );
print 'After new SubRosa: <pre>';
print_r($mt);
print_r($GLOBALS);
print '</pre>';
$mt->debugging = true;
$mt->bootstrap();


function init_subrosa_config() {
    
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

    $cfg['mt_cfg'] = $cfg['mt_dir'] . DIRECTORY_SEPARATOR . 'mt-config.cgi';

    # Append mt_dir to subrosa_path to create an absolute filepath
    $cfg['subrosa_path'] = $cfg['mt_dir'] 
                         . DIRECTORY_SEPARATOR 
                         . $cfg['subrosa_path'];
    return $cfg;
}

?>
