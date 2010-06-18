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

# http://www.calcharters.org/2010/06/mt-preview-d1c087f22262e5264c6b57e21ae1c84edeccd02d.html?083153"
if (! preg_match('/\/mt-preview-[A-Za-z0-9]+\.html\?[0-9]+/', $_SERVER['REQUEST_URI'])) {

    $subrosa_config = init_subrosa_config();
    $cfg            =& $subrosa_config;

    require_once( $cfg['subrosa_path'] );

    apache_setenv('SUBROSA_EVALUATED', 1);
    $_SERVER['SUBROSA_EVALUATED'] = 1;

    $mt = new SubRosa( null, $_SERVER['SUBROSA_BLOG_ID'] );
    if (isset($_GET['debug'])) $mt->debugging = true;
    $mt->bootstrap();
    $mt->log_dump(array('noscreen' => 1));

    if ($_GET['blog_id']) {
      //    if ( preg_match ( '/\.(php|html)/', $mt->request) ) {
      //        include(SubRosa_Util::os_path($mt->site_path, $mt->request));
      //    }
      //    else {
      //        $file_info = apache_lookup_uri($mt->request."?contenttype=1");
      //        header('Content-Type: ' . $file_info->content_type);
      //        virtual($mt->request);
      //        exit(0);
      //    }
    }
 }

function init_subrosa_config() {
    
    require('subrosa_config.php');
    $cfg =& $config;

    if ( ! isset( $cfg['mt_dir'] ))
        $cfg['mt_dir'] = $_SERVER['MT_HOME'];

    if ( ! isset( $cfg['mt_dir'] ))
        die ("Cannot locate MT_HOME at " . __FILE__ . ", line " . __LINE__);

    if ( ! isset( $cfg['subrosa_path'] ))
        $cfg['subrosa_path'] = 'plugins/SubRosa/php/lib/SubRosa.php';

    # Append mt_dir to subrosa_path to create an absolute filepath
    $cfg['subrosa_path'] = $cfg['mt_dir'] 
                         . DIRECTORY_SEPARATOR 
                         . $cfg['subrosa_path'];
    return $cfg;
}

?>
