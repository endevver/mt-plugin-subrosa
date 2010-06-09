<?php

// === TextMate error handling ===
include_once '/Applications/TextMate.app/Contents/SharedSupport/Bundles/PHP.tmbundle/Support/textmate.php';

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
ini_set('display_errors', true); // off

// Tells whether script error messages should be logged to
// the server's error log or error_log. This option is thus
// server-specific.
ini_set('log_errors', true);           // on

// Enabling this setting prevents attacks involved passing
// session ids in URLs. Defaults to true in PHP 5.3.0
ini_set('session.use_only_cookies', true); 

//Name of the file where script errors should be logged.
//ini_set('error_log', 1);                              //NULL
//ini_set('error_log', '/home/tdi/JAY/boo-php.log');    //NULL

require_once( 'SubRosa/Util.php' );

// Derive the paths to the SubRosa and MT PHP libs directory
$base_libdir = dirname( __FILE__ );
$mt_libdir   = SubRosa_Util::os_path( $cfg['mt_dir'], 'php', 'lib' );

// include_path: Prepend SubRosa and MT PHP lib and extlib directories
$include_path = join( ':',
    array(
        $base_libdir,                                 // Our lib
        str_replace( 'lib', 'extlib', $base_libdir ), 
        $mt_libdir,                                   // MT lib
        str_replace( 'lib', 'extlib', $mt_libdir ),   
        ini_get('include_path'),                      // Current value
    ));
// print "<p>include_path: $include_path</p>";
ini_set('include_path', $include_path);

// Include the main MT dynamic libraries if they are 
// not already so that we can extend the MT class...
require_once( SubRosa_Util::os_path( dirname( $mt_libdir ), 'mt.php' ));

require_once('log4php/Logger.php');

/**
* MT-SubRosa
*/
class SubRosa extends MT
{

    //define('VERSION', 0.1); // Should be done in the class

    var $debugging           = false;
    var $logger              = NULL;
    var $log_output;
    var $log_delay           = true;
    var $log_queries         = false;
    var $controller_blog_id;
    var $plugins_initialized = false;
    var $user_cookie         = 'mt_user';
    var $user_session_key    = 'current_user';
    var $notify_user;
    var $notify_password;
    
    function __construct($cfg_file, $blog_id = null)
    {
        $this->error_level = E_ALL ^ E_NOTICE;
        global $subrosa_config;
        $this->controller_blog_id = $subrosa_config['controller_blog_id'];
        $this->log_output         = $subrosa_config['log_output'];
        $this->exclude_blogs      = $subrosa_config['exclude_blogs'];
        $this->site_path          = $subrosa_config['site_path'];
        $this->notify_user        = $subrosa_config['notify_user'];
        $this->notify_pass        = $subrosa_config['notify_pass'];

        $this->init_logger();
        $this->marker('Initializing SubRosa class for request to '
                      .$_SERVER['SCRIPT_URL']);

        if ($this->debugging) {
            $this->log('Debugging is on');
            @setcookie('SMARTY_DEBUG', true);
        }
        $this->log('Calling MT base class');

        $old_error_level = $this->error_level;
        $this->error_level = E_ALL ^ E_DEPRECATED;
        parent::MT($blog_id, $cfg_file);
        $this->error_level = $old_error_level;

        // Initialize database and store in $ctx->mt->db
        $db =& $this->db();
    }

    function init($blog_id = null, $cfg_file = null) {
        $this->marker('Calling MT base init method');

        parent::init($blog_id, $cfg_file);
        $this->log('Current blog ID: '.$this->blog_id);

        date_default_timezone_set('America/Los_Angeles');

        $this->template_dir
            = SubRosa_Util::os_path( dirname($base_libdir), '/tmpl' );
        $this->template['debug'] = 'debug-jay.tpl';
        $this->template['login'] = 'login.tpl';

        // Set up custom pages
        // URGENT: Default site root is not the correct site root.
        // TODO: Should set site root in controller blog configuration
        $site_root = $this->site_path;
        
        $this->log("Site root: $site_root");
        
        $this->page['login'] = SubRosa_Util::os_path($site_root, 'login.php');
        $this->log('Default login page: '.$this->page['login']);
        $this->page['error'] = SubRosa_Util::os_path($site_root, 'error.php');
        $this->log('Default error page: '.$this->page['error']);
        foreach ($this->page as $type => $file) {
            if ( ! file_exists($file) ) {
                $this->log("Custom $type page not found.");
                unset($this->page[$type]);
            } else {
                $this->log("Custom $type page found at ".$this->page[$type]);
            }
        }
    }

    function init_logger() {
        if (isset($this->logger)) return;
        require_once('SubRosa/Logger.php');
        $this->logger = new SubRosa_Logger( $this->log_output );
    }

    function init_plugins() {
        $this->marker('Initializing MT plugins');
        if (!$this->plugins_initialized) {
            parent::init_plugins();
            $this->init_subrosa_plugins();
            $this->plugins_initialized = 1;
        }
    }

    // There are three types of SubRosa plugins which we differentiate by
    // naming convention for usability.
    //
    //  * init - Loaded by both SubRosa and MT's dynamic publishing engine.
    //           Used for defining MT initialization data (e.g. 
    //           template tags, callbacks, etc) and executing anything that
    //           should be run early in the execution process (e.g. defining
    //           functions other code depends on, pre-modifying the
    //           environment or request, etc)
    //
    //  * policy - (SubRosa only) SubRosa access policies which
    //             define what content can be accessed by who how.
    //
    //  * module - (SubRosa only) Plugins which extend and/or modify the
    //             functionality of but do not fit one of the above.
    //
    function init_subrosa_plugins() {
        global $base_libdir;
        $plugin_dir = SubRosa_Util::os_path( 
                          dirname($base_libdir), 'plugins'
                      );
        $this->marker("Initalizing subrosa plugins from $plugin_dir");

        if ( isset( $_SERVER['SUBROSA_POLICY'] ) ) {
            $request_policy = strtolower( $_SERVER['SUBROSA_POLICY'] );
        }

        if (is_dir($plugin_dir) and ($dh = opendir($plugin_dir))) {
            while (($file = readdir($dh)) !== false) {

                // Only process plugin files starting with
                // a valid type (see above) and ending in ".php".
                if ( preg_match('/^(init|policy|module)\.(.+?)\.php$/',
                                    $file, $matches)) {
                    $type = ucfirst(    $matches[1] );
                    $base = strtolower( $matches[2] );

                    // If a policy is defined for this request, skip
                    // any other policies since they are unneeded.
                    if (   isset( $request_policy ) 
                        && ( $type == 'Policy' )
                        && ( $base != $request_policy )) {
                        continue;
                    }

                    $this->log( "$type: $base");
                    require_once("$plugin_dir/$file");
                }
            }
            closedir($dh);
        }

        // Check that any requested policy was properly loaded. 
        // The PHP constant SUBROSA_POLICY should be defined in 
        // the policy plugin file and contains the PHP class name.
        if ( defined( 'SUBROSA_POLICY' )) {
            $SUBROSA_POLICY = SUBROSA_POLICY; // new CONSTANT() doesn't work
            $this->policy = new $SUBROSA_POLICY();
        }
        elseif ( isset( $request_policy )) {
            die ( 'ERROR: The requested SubRosa policy, '
                .  SUBROSA_POLICY
                . ', could not be loaded');
        }

    }

    function init_auth() {
        $this->marker('Initializing authentication');
        require_once('SubRosa/MT/Auth.php');
        $auth = new SubRosa_MT_Auth();
        $this->auth =& $auth;
        $auth->init();
        return $auth;
    }

    function bootstrap() {
        $this->marker('Bootstrapping SubRosa');

        //kill_php_current_session();
        // show_current_request_info();

        session_name('SubRosa');
        session_start();

        $this->init_plugins();

        $policy_class = SUBROSA_POLICY;
        $policy       = new $policy_class();
        if ( ! $policy->is_protected( $_SERVER['REQUEST_URI'] ) ) {
            $this->log_dump(array(noscreen => 1));
            return;
        }
        

    }











    /* *********************************************************************
     *  VIEWER METHODS
     *  The following methods are only used when a protected 
     *  page is dynamically rendered
     ********************************************************************** */
    function init_viewer() {

        ob_start(); 

        $this->marker('Initializing viewer');

        $ctx =& $this->context();
        $ctx->template_dir
            = SubRosa_Util::os_path( $this->config['PHPDir'], 'tmpl' );
        $ctx->stash('plugin_template_dir',  $this->template_dir);
        $ctx->stash('mt_template_dir',      $ctx->template_dir);

        // Set up Smarty defaults
        $ctx->caching = $this->caching;
        $ctx->debugging = $this->debugging;
        if ($ctx->debugging) {
            $ctx->compile_check   =  true;
            $ctx->force_compile   =  true;
            $ctx->debugging_ctrl = '';
            $ctx->debug_tpl = SubRosa_Util::os_path($this->config['MTDir'],
                                    '/php/extlib/smarty/libs/debug.tpl');
        }

        // Set up our customer error handler
        set_error_handler(array(&$this, 'error_handler'));

        $this->request = $this->fix_request_path($this->request);
        if (preg_match('/\.(\w+)$/', $this->request, $matches)) {
            $ctx->stash('request_extenstion', strtolower($matches[1]));
        }

        $this->log('REQUEST VARS: '
                    . ($_REQUEST ? print_r($_REQUEST, true) : '(None)'));
        $this->log('POST VARS: '
                    . ($_POST ? print_r($_POST, true) : '(None)'));
        $this->log('COOKIE VARS: '
                    . ($_COOKIE ? print_r($_COOKIE, true) : '(None)'));
        $this->log('SESSION VARS: '
                    . ($_SESSION ? print_r($_SESSION, true) : '(None)'));
    }

    /***
     * Mainline handler function.
     */
    function view($blog_id=null)
    {
        $this->init_viewer();
        $this->marker('Starting viewer');

        $ctx     =& $this->context();
        $path    =  $this->request;
        $blog_id =  $this->blog_id;
        $blog    =  $this->blog();

        $this->log(sprintf('Looking up request %s for blog ID %s ', 
            $this->request, $this->blog_id));
        $data =& $this->resolve_url($this->request);
        $absolute_request = str_replace('//','/',($this->site_path.$this->request));
        $this->log('Full request path: '.$absolute_request);
        $this->log('Fileinfo data: '.print_r($data, true));

        switch (TRUE) {

            // Unlikely error -- but what the hell..
            case $this->errstr():
                $meth = 'handle_error';
                break;

            // Logout request
            case $_REQUEST['logout']:
                $meth = 'handle_logout';
                break;

            // Login request
            case $_POST['login']:
                $meth = 'handle_login';
                break;

            // Login request
            case ($_REQUEST['debug'] and $this->debugging):
                $meth = 'handle_debug';
                break;

            // Access to authenticated page
            default:
                $meth = 'handle_auth';
        }

        // Run the selected method and capture output
        $this->log("Running method $meth");
        $output = $this->$meth($data);
        $this->log(sprintf("Method '%s' complete; Output was%s produced.", $meth, (isset($output) ? '' : ' not')));

        // First we take care of the two conditions that short-circuit
        // printing of the requested page: a redirect or actual 404
        if ($this->redirect()) {
            $this->log("Redirecting client to ".$this->redirect());
            $this->log_dump(array(noscreen => 1));
            ob_end_clean();
            header('Location: '.$this->redirect());
            exit;
        }

        /*
         # At this point, we either have:
         #
         #   * Compiled page output
         #       (indicates login page or random error page)
         #       Nothing more needed but to print
         #
         #   * Neither
         #       (indicates the requested page should be compiled for an
         #        authorized user of the system)
         #       See the page exists (in $data)
         #       Set params
         #       ctx->fetch
         */
        
         // Page not found
         if (!$data) {

         if (is_file($absolute_request) and is_readable($absolute_request)) {
// THIS WAS AN ABORTION.  TRIED TO DO THIS TO TAKE CARE OF
// a problem where static files not known by MT but needing protection would 404.
// Finally dealt with this on wellpoint's site installation by simply removing
// protection but this is still a problem with PDFs and the like...
// Need to figure this out...
        readfile($absolute_request);
         } else {

                $this->log('Page not found; returning 404');

                $this->http_error = 404;
                // This actually exits.
                $output = $ctx->error(
                    sprintf("Page not found - %s", $this->request));
        }
         }
         elseif (empty($output))    {
            $this->log('No output produced by mode handler; double-checking user auth before serving the page');

            if (isset($this->is_authorized)) {
                $this->log('User is authorized...');
                $output = $this->compile_blog_page($data);
                if (is_null($output)) 
                $this->log('Came back with no output!');
            } else {
                $this->log("Houston we have a problem: Unauthorized user.");
            }
        }

        if (empty($this->http_error)) $this->http_headers();
        $this->log('Printing $output');
        print $output;
        $this->cleanup_viewer();
    }

    function cleanup_viewer() {
        $this->marker('Cleaning up viewer');

        $this->log_dump(array('noscreen' => 1));

        // FIXME Double check output_buffering routines and make sure that they are called everywhere they are supposed to be and are correct.
        ob_flush(); //questionable...  Shouldn't flush if we don't want it seen
        restore_error_handler();
    }

    function compile_blog_page($data) {
        $this->marker('Compiling blog page');

        $ctx =& $this->context();
        $mtdb =& $this->db();

        /*
         *
         *  GET INFO ON REQUEST URI
         *
         */

        $info =& $data['fileinfo'];
        $fi_path = $info['fileinfo_url'];
        $fid = $info['fileinfo_id'];
        $at = $info['fileinfo_archive_type'];
        $ts = $info['fileinfo_startdate'];
        $tpl_id = $info['fileinfo_template_id'];
        $cat = $info['fileinfo_category_id'];
        $entry_id = $info['fileinfo_entry_id'];
        $blog_id = $info['fileinfo_blog_id'];
        $blog =& $data['blog'];
        if ($at == 'index') {
            $at = null;
        }
        $tts = $data['template']['template_modified_on'];
        $tts = offset_time(datetime_to_timestamp($tts), $blog);
        $ctx->stash('template_timestamp', $tts);

        $this->configure_paths($blog['blog_site_path']);

        // start populating our stash
        $ctx->stash('blog_id', $blog_id);
        $ctx->stash('blog', $blog);


        /*
         *
         *  SET REQUEST VARIABLES
         *
         */

        // conditional get support...
        if ($this->caching) {
            $this->cache_modified_check = true;
        }
        if ($this->conditional) {
            $last_ts = $blog['blog_children_modified_on'];
            $last_modified = $ctx->_hdlr_date(array('ts' => $last_ts, 'format' => '%a, %d %b %Y %H:%M:%S GMT', 'language' => 'en', 'utc' => 1), $ctx);
            $this->doConditionalGet($last_modified);
        }

        /*
         *
         *  SET UP ARCHIVE TYPE VARIABLES
         *
         */

        $cache_id = $blog_id.';'.$fi_path;
        if (!$ctx->is_cached('mt:'.$tpl_id, $cache_id)) {
            if ($cat) {
                $archive_category = $mtdb->fetch_category($cat);
                $ctx->stash('category', $archive_category);
                $ctx->stash('archive_category', $archive_category);
            }
            if (isset($ts)) {
                if ($at == 'Yearly') {
                    $ts = substr($ts, 0, 4);
                } elseif ($at == 'Monthly') {
                    $ts = substr($ts, 0, 6);
                } elseif ($at == 'Daily') {
                    $ts = substr($ts, 0, 8);
                }
                if ($at == 'Weekly') {
                    list($ts_start, $ts_end) = start_end_week($ts);
                } else {
                    list($ts_start, $ts_end) = start_end_ts($ts);
                }
                $ctx->stash('current_timestamp', $ts_start);
                $ctx->stash('current_timestamp_end', $ts_end);
            }
            if (isset($at)) {
                $ctx->stash('current_archive_type', $at);
            }

            if (isset($entry_id) && ($entry_id) && ($at == 'Individual')) {
                $entry =& $mtdb->fetch_entry($entry_id);
                $ctx->stash('entry', $entry);
                $ctx->stash('current_timestamp', $entry['entry_created_on']);
            }
        }

        /*
         *
         *  GET TEMPLATE
         *
         */

        $this->log("Calling fetch with mt:$tpl_id and \$cache_id of $cache_id");
        $output = $ctx->fetch('mt:'.$tpl_id, $cache_id);

        // finally, issue output
        return $output;

    }

    function fix_request_path($path='')
    {

        // Apache request
        if (!$path && $_SERVER['REQUEST_URI']) {
            $path = $_SERVER['REQUEST_URI'];
            // strip off any query string...
            $path = preg_replace('/\?.*/', '', $path);
            // strip any duplicated slashes...
            $path = preg_replace('!/+!', '/', $path);
        }

        // IIS request by error document...
        if (preg_match('/IIS/', $_SERVER['SERVER_SOFTWARE'])) {
            // assume 404 handler
            if (preg_match('/^\d+;(.*)$/', $_SERVER['QUERY_STRING'], $matches)) {
                $path = $matches[1];
                $path = preg_replace('!^http://[^/]+!', '', $path);
                if (preg_match('/\?(.+)?/', $path, $matches)) {
                    $_SERVER['QUERY_STRING'] = $matches[1];
                    $path = preg_replace('/\?.*$/', '', $path);
                }
            }
        }

        // When we are invoked as an ErrorDocument, the parameters are
        // in the environment variables REDIRECT_*
        if (isset($_SERVER['REDIRECT_QUERY_STRING'])) {
            // TODO: populate $_GET and QUERY_STRING with REDIRECT_QUERY_STRING
            $_SERVER['QUERY_STRING'] = getenv('REDIRECT_QUERY_STRING');
        }

        return $path;
    }




    /*
    *  handle_login()
    *
    *  This method is responsible for checking authentication
    *  details submitted through the login form, creating
    *  sessions for valid users and redirecting the user
    *  to another page if needed.
    */        
    function handle_login($fileinfo = null) {
        $this->marker();
        
        // Look up the user in the database by username and password
        // and start a session if found.
        require_once('SubRosa/MT/Auth.php');
        $auth = new SubRosa_MT_Auth($_POST['username'], $_POST['password']);
        $this->auth =& $auth;
        $user = $auth->login();

        // Check for error conditions or a forced login and
        // return the appropriate page if so.
        if ($this->errstr()) {
            $this->log("Unspecified error in login for $user");
            $out = $this->build_page('error');
        }
        elseif (empty($user)) {
            $this->log("Invalid login for $user");
            $params = array(error_message => 'Invalid login.');
            $out = $this->login_page($params);
        }
        else {
            // The login was successful.  Now let's figure out
            // where to send them...

            if ($url = $this->redirect()) {
                $this->log("App redirect set to $url");
                $out = $this->redirect($url);
            }
            // If the redirect parameter is set to one, send 
            // them back where they came from
            elseif ($_POST['redirect'] and $_POST['redirect'] == 1) {
                $this->log("Post redirect set for referrer ".$_SERVER['HTTP_REFERER']);
                $out = $this->redirect($_SERVER['HTTP_REFERER']);
            }
            // Otherwise, it should contain a URL which we shall use
            elseif ($url = $_POST['redirect']) {
                $this->log("Post redirectset to $url");
                $out = $this->redirect($url);
            }
            // If neither of these, then we will just send them back
            // to the page they were trying to access. A redirect is 
            // necessary to get the cookie values to take hold.
            else {
                $out = $this->redirect(SubRosa_Util::self_url());
            }
            $this->log('Login successful, redirecting to '.$this->redirect());
        }
        return $out;
    }


    function handle_auth($fileinfo = null) {
        $this->marker();
        
        require_once('SubRosa/MT/Auth.php');
        $auth = new SubRosa_MT_Auth();
        $this->auth =& $auth;
        $auth->init();
        
        // If no active, valid session is found,
        // we give them the login page.
        if ( ! $auth->session() ) {
            $this->log('No active session for user.');
            $out = $this->login_page();
        }
        // If the user has permission to view the blog or
        // we are in a blog context other than the 
        // controller blog, we return the authorized flag.
        elseif (($this->blog_id == $this->controller_blog_id)
                or $auth->has_perms()) {
            $this->log('User is authorized.');
            return $this->is_authorized();
        }
        // Otherwise we force the return of a 404 page
        // regardless of whether or not the content exists
        // in order to maintain data security.
        else {
            $this->log('User not authorized. Returning 404 page');
            $ctx =& $this->context();
            $this->blog_id = $this->controller_blog_id;
            $this->http_error = 404;
            $out = $ctx->error(
                sprintf("Page not found - %s", $this->request));
        }
        return $out;
    }

    /*
    *  handle_logout()
    *
    *  This method is responsible for destroying all login
    *  sessions and cookies and redirecting the user back
    *  to the root URL
    */        
    function handle_logout($fileinfo = null) {
        $this->marker();

        require_once('SubRosa/MT/Auth.php');
        $auth = new SubRosa_MT_Auth();
        $this->auth =& $auth;
        $auth->init();
        
        if ($auth->session()) {
            $auth->logout();
        }
        
        // After logout, we redirect back to the main site_url
        // and display the login screen. Unless there's an error
        if ($this->blog_id) {
            $blog = $this->blog();
            $url = $blog['blog_site_url'];
        } else {
            $url = SubRosa_Util::self_url();
        }
        return $this->redirect($url.'?__mode=logout');
    }

    function is_authorized() {
        $this->is_authorized = 1;
        return;
    }

    function login_page($params=null) {
        $this->marker();
        // If we have a custom login page,
        // read it in and return it
        if (isset($this->page['login'])) {
            $this->log('Using custom login page: '. $this->page['login']);
            ob_start();
            require_once($this->page['login']);
            $contents = ob_get_contents();
            ob_end_clean();
            return $contents;
        }

        // Otherwise, we fall back to the built in page
        $tpl = SubRosa_Util::os_path( $this->template_dir,
                                      $this->template['login'] );

        $this->set_default_template_params();

        // TODO: Move debugging output template parameter to the end of view() like the others
        // ob_start();
        // $this->debugging and $this->log_dump();
        // $ctx->stash('debug_output', ob_get_clean());

        // Parse the query string...
        parse_str($_SERVER['QUERY_STRING'], $query_string);
        foreach ($query_string as $key => $value) {
            $query_param[] = array(
                    escaped_name => encode_html($key),
                    escaped_value => encode_html($value)
                );
        }
        $ctx =& $this->context();
        $ctx->stash('query_param', $query_param);


        // FIXME: Hardcoded logged_out parameter
        $ctx->stash('logged_out', 0);       // Set here if logout
        // FIXME: Hardcoded login_again parameter
        $ctx->stash('login_again', 0);      // $app->{login_again}
        // FIXME: Hardcoded error parameter
        $ctx->stash('error', 0);            // $app->errstr
        $ctx->stash('mode', 'list_blogs');

/*
TODO:   Integrate with MT::Auth to determine the correct login form values
        To make this "right", I need to integrate with MT auth
        Need method to integrating with MT::Auth
*/
        $auth_type = 'MT';

        // Currently all Auth types use this
        $ctx->stash('login_fields', 'login_mt.tpl');

        if ($auth_type == 'BasicAuth') {
            $ctx->stash('can_recover_password', '0');
            $ctx->stash('delegate_auth', '1');
        }
        elseif ($auth_type == 'MT') {
            $ctx->stash('can_recover_password', '1');
            $ctx->stash('delegate_auth', '0');
        }
        elseif ($auth_type == 'LDAP') {
            $ctx->stash('can_recover_password', '0');
        }
        else {
            // Dunno
        }


        $blog = $this->blog();
        restore_error_handler();
        // $result .= $ctx->mt->translate_templatized($tmpl);

        print $ctx->fetch($tpl);
        
    }

    function error_handler($errno, $errstr, $errfile, $errline) {

        if ($errno & (E_ALL ^ E_NOTICE)) {
            $mtphpdir = $this->config['PHPDir'];
            $ctx =& $this->context();
            $ctx->stash('blog_id', $this->blog_id);
            $ctx->stash('blog', $this->db->fetch_blog($this->blog_id));
            $ctx->stash('error_message', $errstr."<!-- file: $errfile; line: $errline; code: $errno -->");
            $ctx->stash('error_code', $errno);
            $http_error = $this->http_error;
            if (!$http_error) {
                $http_error = 500;
            }
            $ctx->stash('http_error', $http_error);
            $ctx->stash('error_file', $errfile);
            $ctx->stash('error_line', $errline);
            $ctx->template_dir = SubRosa_Util::os_path( $mtphpdir, 'tmpl' );
            $ctx->caching = 0;
            $ctx->stash('StaticWebPath', $this->config['StaticWebPath']);
            $ctx->stash('PublishCharset', $this->config['PublishCharset']);
            $charset = $this->config['PublishCharset'];
            $out = $ctx->tag('Include', array('identifier' => 'dynamic_error'));
            if (isset($out)) {
                header("Content-type: text/html; charset=".$charset);
                echo $out;
            } else {
                header("HTTP/1.1 500 Server Error");
                header("Content-type: text/plain");
                echo "Error executing error template.";
            }
            if ($this->debugging) {
                $log = $this->logger->current_log();
                $error_console = "<div class=\"debug\" style=\"border:1px solid red; margin:0.5em; padding: 0 1em; text-align:left; background-color:#ddd; color:#000\"><pre>";
                $error_console .= implode("\n", $log);
                $error_console .= "</pre></div>\n\n";
                echo $error_console;
            }
            exit;
        }
    }

    function ickyerror_handler($errno, $errstr, $errfile, $errline) {
        // $this->marker("[$errfile, $errline:] $errstr");
        // RADAR: 12282
        $this->log_dump();
        parent::error_handler($errno, $errstr, $errfile, $errline);
        return;
        if ( ! ($errno & $this->error_level) ) return;

        $charset = $this->config['PublishCharset'];
        $mtphpdir = $this->config['PHPDir'];
        $ctx =& $this->context();
        $ctx->stash('blog_id', $this->blog_id);
        $blog =& $this->blog();
        // $ctx->stash('blog', $this->db->fetch_blog($this->blog_id));
        $http_error = $this->http_error;
        if (!$http_error) {
            $http_error = $this->http_error = 500;
        }

        // If we have a custom error page,
        // read it in and return it
        $out = null;
        if (isset($this->page['error'])) {
            $this->log('Using custom error page: '. $this->page['error']);
            ob_start();
            require_once($this->page['error']);
            $out = ob_get_contents();
            ob_end_clean();
        }
        
        if (is_null($out)) {
            
            // Use the default error page
            $ctx->caching = 0;
            $ctx->stash('blog_id', $this->blog_id);
            $ctx->stash('blog', $this->db->fetch_blog($this->blog_id));
            $ctx->stash('error_message', $errstr.
                "<!-- file: $errfile; line: $errline; code: $errno -->");
            $ctx->stash('error_code', $errno);
            $ctx->stash('http_error', $http_error);
            $ctx->stash('error_file', $errfile);
            $ctx->stash('error_line', $errline);
            $ctx->stash('StaticWebPath', $this->config['StaticWebPath']);
            $ctx->stash('PublishCharset', $this->config['PublishCharset']);
            $out = $ctx->tag('Include', array('type' => 'dynamic_error'));
        }

        if (isset($out)) {
            header("Content-type: text/html; charset=".$charset);
        } else {
            header("HTTP/1.1 500 Server Error");
            header("Content-type: text/plain");
            $out = "Error executing error template.";
        }
        return $out;
    }

    function http_headers() {
        $this->marker('Sending HTTP headers');
        header("HTTP/1.1 200 OK");
        // content-type header-- need to supplement with charset
        $ctx =& $this->context();
        $content_type = $ctx->stash('content_type');

        if (!isset($content_type)) {
            $content_type = $this->mime_types['__default__'];
            $req_ext = $ctx->stash('request_extenstion');
            if ($req_ext && (isset($this->mime_types[$req_ext]))) {
                $content_type = $this->mime_types[$req_ext];
            }
        }
        if (isset($config['PublishCharset'])) {
            if (!preg_match('/charset=/', $content_type))
                $content_type .= '; charset=' . $config['PublishCharset'];
        }
        header("Content-Type: $content_type");
    }

    function set_cookie_defaults() {

        $this->marker();

        // Set cookie/session defaults in lieu of
        // specification in of directives in mt.config.cgi
        $this->session_timeout = $this->config['UserSessionTimeout'];
        $this->cookie_path = $this->config['CookiePath'];
        $this->cookie_domain = $this->config['CookieDomain'];

        /*
    
        if (! ($this->cookie_domain = $this->config['CookieDomain'])) {
            $urls[parse_url($this->site_url, PHP_URL_HOST)] = 1;
            $urls[parse_url($this->trac->url, PHP_URL_HOST)] = 1;
            $urls[parse_url($this->mt->url, PHP_URL_HOST)] = 1;
            if (count($urls) == 1) {
                $this->cookie_domain = $urls[0];            
            } else {
                return $this->error(
                     'Please set the CookieDomain configuration variable '
                    .'in mt-config.cgi to a common domain between MT, '
                    .'Trac and your website.  Please make sure to start '
                    .'it with a period (.).');
            }
        }    
        */
    }

    function set_default_template_params() {

        $ctx =& $this->context();
        $ctx->stash('script_url', $_SERVER['SCRIPT_URL']);
        // FIXME: Hardcoded is_bookmarklet parameter
        $ctx->stash('is_bookmarklet', 0);    // $app->param('is_bm')
        $ctx->stash('help_url', 'http://www.sixapart.com/movabletype/docs/enterprise/1.5/');
        $ctx->stash('language_encoding', $this->config['PublishCharset']); 

        // FIXME: Hardcoded mt_url parameter
        // Perl is: $tmpl->param(mt_url => $app->mt_uri);
        $ctx->stash('mt_url', '/cgi/mt/mt.fcgi');
        $ctx->stash('mt_product_name',
            encode_html(PRODUCT_NAME));
        $ctx->stash('mt_version',
            encode_html(PRODUCT_VERSION));
        $ctx->stash('script_base_url', '');

        $ctx->stash('static_uri', 
            rtrim($this->config['StaticWebPath'], '/').'/');

        $lang = $this->config['DefaultLanguage'];
        if (strtolower($curr) == 'en_us') $lang = 'en-us';
        if (strtolower($curr) == 'jp') $lang = 'ja';
        $ctx->stash('language_tag', $lang);

        // $tmpl->param(script_path => $app->path);
        // $tmpl->param(script_full_url => $app->base . $app->uri);
        // $tmpl->param(mt_product_code => MT->product_code);

    }
    

    function run_callbacks($cb_sig) {

        $this->marker();

        // Get the rest of the function arguments and go to town...

        // See if there are any plugins registered for the hookpoint:
        //      If not, return now.
        // Cycle through the array of the coderefs that are registered
        //      Execute each
        //      Keep track of cb->errstr's
        //      Otherwise ignore the return values
        //  After all are done, report (activity log?) on the errors    

        /*
          OTHER MT CALLBACK ROUTINES 

            MT->register_callbacks([...])
            MT->add_callback($meth, $priority, $plugin, $code)
            MT->remove_callback($callback)
            MT->register_callbacks([...])
            MT->run_callbacks($meth[, $arg1, $arg2, ...])
            MT->run_callback($cb[, $arg1, $arg2, ...])
            callback_error($str)
            callback_errstr        
        */

    }

    function blog() {
        $ctx =& $this->context();
        $blog =& $ctx->stash('blog');
        if (!$blog) {
            $db =& $this->db();
            $ctx->mt->db =& $db;
            $blog =& $db->fetch_blog($this->blog_id);
            $ctx->stash('blog', $blog);
            $ctx->stash('blog_id', $this->blog_id);
            $this->configure_paths($blog['blog_site_path']);
        }
        return $blog;
    }

    function no_errors() { return is_null($this->errstr); }

    function redirect($url='') {
        static $redirect_to = '';
        if ($url) {
            $this->log("Setting app redirect to $url");
            $redirect_to = $url;
        } else {
            return $redirect_to;
        }
    }

    function errstr() { }

    function mtlog($msg) {
        $this->marker();
        require_once('SubRosa/MT/Object/Log.php');
        if (is_array($msg)) {
            $log = new MT_Log($msg);
        } else {
            $log = new MT_Log();
            $log->set('message', $msg);
        }
        $log->save();
    }

    function log($msg='') {
        $this->init_logger();
        $this->logger->log($msg);
    }

    function notify($msg='') {
        $this->init_logger();
        $this->logger->notify($msg);
    }

    function marker($msg='') {
        $this->init_logger();
        $this->logger->marker($msg);
    }

    function log_dump($opts='') {
        if ($this->debugging !== true) return;
        $this->init_logger();
        if ($this->log_queries and ! $opts['noqueries']) {
            print "<h1>Logging queries</h1>";
            $this->debug_queries();
        }
        $this->logger->log_dump($opts);
    }

    function debug_queries() {
        $this->log("Queries: ".$mtdb->num_queries);
        $this->log("Queries executed:");
        $queries = $this->db->savedqueries;
        foreach ($queries as $q) {
            $this->log($q);
        }
    }
}

/**
 *    

*
* Error Handling and Logging Functions
* http://us2.php.net/manual/en/ref.errorfunc.php
*
* Function Handling Functions
* http://us2.php.net/manual/en/ref.funchand.php
*
* Session Handling Functions
* http://us2.php.net/manual/en/ref.session.php
*
* URL Functions
* http://us2.php.net/manual/en/ref.url.php
*
*
*
*
*
*
*

MAGIC=90Pz8WcBN1sFCZ4nytlVRd2AbDvOrXeLaSJkj6iI
UTIME=1182138925

echo 'delete from session; select count(*) from session; delete from auth_cookie;select count(*) from auth_cookie;' | sqlite3 ~/Sites/trac.local/data/personal/db/trac.db | xargs

# The auth_cookie insert statement
echo "INSERT INTO 'auth_cookie' VALUES ('$MAGIC','jay','127.0.0.1', $UTIME); "  | sqlite3 ~/Sites/trac.local/data/personal/db/trac.db 

# The session insert statement
echo "INSERT INTO 'session' VALUES ('jay','1', $UTIME); "  | sqlite3 ~/Sites/trac.local/data/personal/db/trac.db 

# Join on both auth_cookie and session tables
echo "select sid, cookie, authenticated, last_visit, time from session join auth_cookie on sid = name;" | sqlite3 ~/Sites/trac.local/data/personal/db/trac.db

open -a Firefox "http://localhost/jay.php?c=$MAGIC"
 
 */

 // textmate_backtrace();
 // die("Exiting at ". __FILE__ . ', line ' .__LINE__);

?>
