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
$cfg['mt_dir']          = '/Users/jay/Sites/ccsa.local/html/mt';

/**
 *  subrosa_path - Filesystem path to the main SubRosa class file
 *                 relative to your Melody/MT directory. You shouldn't
 *                 need to change this unless you install SubRosa in a
 *                 non-standard way.
 */
$cfg['subrosa_path']    = 'plugins/SubRosa/php/lib/SubRosa.php';

/**
 *  site_path - Absolute filesystem path to your site. The default is almost
 *              always correct and you should not change this unless you know
 *              what you're doing.
 */
// $cfg['site_path']       = $_SERVER['DOCUMENT_ROOT'];

/**
 *  log_output - Name of the logfile to capture debugging output.
 */
$cfg['log_output']      = 'subrosa_debug.log';

// $cfg['session.cookie_domain'] = '<$mt:Var name="config.cookiedomain"$>';
$cfg['session.cookie_domain'] = 'ccsa.local';

$cfg['debugging'] = true;

$cfg['mime_types'] = array(
    /**
     * The following are already defined. Uncomment to 
     * override or add common ones to speed up lookups
     */
    // '__default__' => 'text/html',
    // 'css' => 'text/css',
    // 'txt' => 'text/plain',
    // 'rdf' => 'text/xml',
    // 'rss' => 'text/xml',
    // 'xml' => 'text/xml',
    'js' => 'text/javascript'
);

if ( apache_note('SUBROSA_PASSTHRU') ) {
    echo 'apache_note SUBROSA_PASSTHRU set on request!!!';
    die();
}

if ( apache_getenv('SUBROSA_PASSTHRU') ) {
    echo 'apache_env SUBROSA_PASSTHRU set on request!!!';
    die();
}

/************************************************************************
 *  DO NOT EDIT BELOW THIS COMMENT UNLESS YOU KNOW WHAT YOU'RE DOING    *
 ************************************************************************/
$subrosa_config = $cfg;

require_once(
    $cfg['mt_dir'] . DIRECTORY_SEPARATOR . $cfg['subrosa_path'] );

$mt = new SubRosa( null, $_SERVER['SUBROSA_BLOG_ID'] );

$mt->resolve_url('index.html');

$mt->bootstrap();

?>