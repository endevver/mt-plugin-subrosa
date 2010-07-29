<?php
/**
 * SubRosa request gatekeeper script
 *
 * This template/script serves two purposes:
 *
 *     - Since it is published by Movable Type (as an index template) it
 *       acts as the primary SubRosa configuration interface for an
 *       individual blog.
 *
 *     - With the configuration and path information provided by Movable 
 *       Type it is both capable of and responsible for bootstrapping the 
 *       SubRosa framework to evaluate all incoming requests which are 
 *       directed to it by the webserver
 *
 */

/**
 * mt_dir - Absolute filesystem path to your Melody/MT directory (MT_HOME).
 *          This is the directory containing mt-config.cgi.
 */
$config['mt_dir']               = '/Users/jay/Sites/jay.local/html/ccsa/mt';

/** 
 *  subrosa_path - Filesystem path to the main SubRosa class file
 *                 relative to your Melody/MT directory. You shouldn't
 *                 need to change this unless you install SubRosa in a
 *                 non-standard way.
 */
$config['subrosa_path']         = 'plugins/SubRosa/php/lib/SubRosa.php';

/** 
 *  site_path - Absolute filesystem path to your site. The default is almost
 *              always correct and you should not change this unless you know
 *              what you're doing.
 */ 
$config['site_path']            = $_SERVER['DOCUMENT_ROOT'];

/** 
 *  log_output - Absolute filesystem path to an optional logfile
 */ 
$config['log_output']       = $config['site_path']
                            . DIRECTORY_SEPARATOR
                            . 'subrosa_debug.log';


/************************************************************************
 *  DO NOT EDIT BELOW THIS COMMENT UNLESS YOU KNOW WHAT YOU'RE DOING    *
 ************************************************************************/ 
require_once(
    $config['mt_dir'] . DIRECTORY_SEPARATOR . $config['subrosa_path']
);

$mt = new SubRosa( null, $_SERVER['SUBROSA_BLOG_ID'] );
if (isset($_GET['debug'])) $mt->debugging = true;
$mt->bootstrap();

?>