<?php

class SubRosa_Env {

    function __construct() {
        global $mt;

        // Derive the paths to the SubRosa and MT PHP libs directory
        //    plugins/SubRosa/php/lib/SubRosa.php
        $base_libdir = dirname( __FILE__ );
        $mt_libdir   = SubRosa_Util::os_path( $mt_dir, 'php', 'lib' );

        // include_path: Prepend SubRosa and MT PHP lib and extlib directories
        ini_set('include_path', join( ':', array(
                $base_libdir,                                 // Our lib
                str_replace( 'lib', 'extlib', $base_libdir ), 
                $mt_libdir,                                   // MT lib
                str_replace( 'lib', 'extlib', $mt_libdir ),
                str_replace( 'lib', 'plugins', $mt_libdir ),
                ini_get('include_path'),                      // Current value
            ))
        );
        print "<p>include_path: $include_path</p>";
exit;
        // session.use_only_cookies
        // Enabling this setting prevents attacks involved passing
        // session ids in URLs. Defaults to true in PHP 5.3.0
        ini_set('session.use_only_cookies', true);

        // display_errors
        // This determines whether errors should be printed to the screen
        // as part of the output or if they should be hidden from the user.
        // Note: Although display_errors may be set at runtime (with
        // ini_set()), it won't have any affect if the script has fatal
        // errors. This is because the desired runtime action does not get
        // executed.
        ini_set('display_errors', isset($_GET['debug']) ? true : false);

        // display_startup_errors
        // Even when display_errors is on, errors that occur
        // during PHP's startup sequence are not displayed.
        // It's strongly recommended to keep display_startup_errors
        // off, except for debugging.
        ini_set('display_startup_errors', true); // off

        // log_errors
        // Tells whether script error messages should be logged to
        // the server's error log or error_log. This option is thus
        // server-specific.
        ini_set('log_errors', true);           // on
    }

    function __destruct() {
    }
}

?>