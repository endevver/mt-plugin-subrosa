<?php
// === TextMate error handling ===
// include_once '/Applications/TextMate.app/Contents/SharedSupport/Bundles/PHP.tmbundle/Support/textmate.php';

// include_path: Prepend SubRosa and MT PHP lib and extlib directories
ini_set('include_path', join( ':', array(   dirname( __FILE__ ),
                                            ini_get('include_path') )));

require_once( 'SubRosa/Env/Debug.php' );
// require_once('log4php/Logger.php');
require_once( 'SubRosa/Util.php' );
require_once( 'SubRosa/Logger.php' );
require_once(
    SubRosa_Util::os_path( $subrosa_config['mt_dir'], 'php', 'mt.php' ) );

// Handle mt-preview URLs by not handling them
if ( SubRosa_Util::url_is_entry_preview() ) {
    $_GLOBAL['SUBROSA_PASSTHRU'] = true;
}

/**
* MT-SubRosa
*/
class SubRosa extends MT {

    const VERSION = '3.0';    // SubRosa version

    // Declare properties and set defaults
    public $debugging           = false;
    public $logger              = NULL;
    public $log_delay           = true;
    public $log_queries         = false;
    public $plugins_initialized = false;
    public $user_cookie         = 'mt_user';
    public $user_session_key    = 'current_user';
    public $error_level;
    public $libdir;
    public $mt_dir;
    public $log_output;
    public $blog_id;
    public $cfg_file;
    public $controller_blog_id;
    public $notify_user;
    public $notify_password;

    function __construct( $cfg_file = null, $blog_id = null ) {

        // Assign values for object properties from config
        global $subrosa_config;
        foreach ( array_keys($subrosa_config) as $key ) {
            $this->$key = $subrosa_config[$key];
        }

        // Initialize the logging system for debugging
        $this->init_logger();

        // Initialize the MT base class ( this also calls our init() method )
        $this->init_mt( $cfg_file, $blog_id );

        // Set up our customer error handler
        set_error_handler(array( &$this, 'error_handler' ));
    }

    function init_logger() {
        if ( isset($this->logger) ) return;
        $this->logger = new SubRosa_Logger( $this->log_output );
        if ( $this->debugging ) @setcookie('SMARTY_DEBUG', true);
        $this->marker(
            sprintf('Initializing SubRosa for %s (Debug: %s)',
                      $_SERVER['SCRIPT_URL'],
                      $this->debugging ? 'on' : 'off')
        );
    }

    function init_mt( $cfg_file = null, $blog_id = null ) {
        error_reporting( E_ALL ^ E_DEPRECATED );  // Squelch MT warnings
        isset($this->error_level) or $this->error_level = E_ALL & ~E_NOTICE;
        
        // Instantiate ourself from base class.
        // The constructors calls OUR init() method
        $this->marker('Instantiating MT base class');
        parent::MT( $blog_id, $cfg_file, $this->error_level );

        $this->marker('MT base class instantiated');
        error_reporting( $this->error_level );   // Restore error level
    }

    // Our init function is called directly from MT's constructor
    // so we call MT's init() to complete the initialization.  During
    // MT's init method execution, the following happens:
    //
    //  * $this->blog_id is set if supplied
    //  * $this->config is set from the mt-config.cgi
    //  * Extra config values set (all LOWERCASE!):
    //      - phpdir        Directory containing mt.php (MT_HOME/php)
    //      - mtdir         Path to MT directory (MT_HOME)
    //      - phplibdir     Path to handlers    phpdir + lib
    //      - dbdriver      Type of database driver
    //  * Prepends the following to the include_path:
    //      - phpdir/lib
    //      - phpdir/extlib
    //      - phpdir/extlib/smarty/libs
    //  * Inits addons' plugins from (addons/AddonName/php)
    //      - Bootstraps $this->ctx (viewer)
    //  * Runs configure_from_db() which initializes DB into $this->db
    //  * Sets up default language
    //  * Sets up multibyte string stuff
    //  WHEW!
    function init( $blog_id = null, $cfg_file = null ) { // Switched args!

        date_default_timezone_set('America/Los_Angeles'); // Shut up PHP...

        $this->marker('Calling MT base init method');
        parent::init( $blog_id, $cfg_file );

        // Set up custom SubRosa pages
        $this->init_subrosa_pages();

        $this->log( print_r(
            array(
                'Current blog ID'       => $this->blog_id,
                "Site root path"        => $this->site_path,
                'Default login page'    => $this->page['login'],
                'Default error page'    => $this->page['error']
            ),
            true
        ) );
    }

    // Initialize SubRosa internal templates and login/error/custom app pages 
    function init_subrosa_pages() {
        isset($this->libdir) or $this->libdir = dirname( __FILE__ );

        $this->template_dir
            = SubRosa_Util::os_path( dirname( $this->libdir ), 'tmpl' );
        $this->template['debug'] = 'debug-jay.tpl';
        $this->template['login'] = 'login.tpl';

        SubRosa_Util::set_if_empty(
            $this->site_path, SubRosa_Util::document_root() );

        foreach ( array('login','error') as $p) {
            $this->page[$p]
                = SubRosa_Util::os_path($this->site_path, $p.'.php');
        }

        foreach ( $this->page as $type => $file ) {
            if ( file_exists($file) ) {
                $this->log("Custom $type page found at ".$this->page[$type]);
            } else {
                $this->log("Custom $type page not found.");
                unset($this->page[$type]);
            }
        }
    }

    /***
     * Mainline handler function.
     */
    function bootstrap( $entry_id = null ) {
        $this->marker('Bootstrapping SubRosa');

        //kill_php_current_session();
        // show_current_request_info();

        session_name('SubRosa');
        session_start();

        $this->init_viewer();
        $this->init_plugins();
        $this->log_dump();
        // $this->log_dump(array('noscreen' => 1));
        $this->init_policy();

        //// Testing direct access by Jay
        // if (   ($_SERVER['REMOTE_ADDR'] == '24.130.173.174')
        //     && ($req_check === true)) {
        if ( $this->policy->check_request( $entry_id ) === true ) {
            $this->handle_request( $entry_id );
        }
        apache_setenv('SUBROSA_EVALUATED',   1 );
        apache_note(  'SUBROSA_EVALUATED',  '1');
        $_SERVER['SUBROSA_EVALUATED']       = 1;
        $_SESSION['SUBROSA_EVALUATED']      = 1;
    }

    function init_viewer() {

        ob_start();

        $this->marker('Initializing viewer');
        $ctx =& $this->context();
        $ctx->template_dir
            = SubRosa_Util::os_path( $this->config['phpdir'], 'tmpl' );
        $ctx->stash('plugin_template_dir',  $this->template_dir);
        $ctx->stash('mt_template_dir',      $ctx->template_dir);

        // Set up Smarty defaults
        $ctx->caching   = $this->caching;
        $ctx->debugging = $this->debugging;
        if ($ctx->debugging) {
            $ctx->compile_check   =  true;
            $ctx->force_compile   =  true;
            $ctx->debugging_ctrl  = 'URL';
            $ctx->debug_tpl = SubRosa_Util::os_path( 
                $this->config['mtdir'], 'php', 'extlib', 'smarty', 
                                            'libs', 'debug.tpl' );
        }

        $this->log('REQUEST VARS: '
                    . ($_REQUEST ? print_r($_REQUEST, true) : '(None)'));
        $this->log('POST VARS: '
                    . ($_POST ? print_r($_POST, true) : '(None)'));
        $this->log('COOKIE VARS: '
                    . ($_COOKIE ? print_r($_COOKIE, true) : '(None)'));
        $this->log('SESSION VARS: '
                    . ($_SESSION ? print_r($_SESSION, true) : '(None)'));

        $this->request = $this->fix_request_path($this->request);
        if (preg_match('/\.(\w+)$/', $this->request, $matches)) {
            $ctx->stash('request_extension', strtolower($matches[1]));
        }
    }

    // Calls $ctx->add_plugin_dir for each directory found in:
    //      - plugins/PluginName/php
    //      - MT_HOME/php/plugins
    // $ctx->add_plugin_dir simply appends the found dir to include_path
    // and appends to the $this->plugins_dir array
    // Then it calls load_plugin foreach dir in $ctx->plugins_dir array
    //      - Evals 'modifier.' plugins with add_global_filter
    //      - Evals 'init.' plugins via require
    function init_plugins() {
        $this->marker('Initializing MT plugins');
        if ( ! $this->plugins_initialized ) {
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
        $plugin_dir = SubRosa_Util::os_path( dirname($this->libdir), 
                                             'plugins' );
        $this->marker("Initalizing subrosa plugins from $plugin_dir");

        if ( isset( $_SERVER['SUBROSA_POLICY'] ) ) {
            $request_policy = strtolower( $_SERVER['SUBROSA_POLICY'] );
        }

        if (is_dir($plugin_dir) and ($dh = opendir($plugin_dir))) {
            while (($file = readdir($dh)) !== false) {

                // Only process plugin files starting with
                // a valid type (see above) and ending in ".php".
                if ( preg_match('/^(policy|module)\.(.+?)\.php$/',
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
                    print "<h1>$plugin_dir/$file</h1>";
                    require_once("$plugin_dir/$file");
                }
            }
            closedir($dh);
        }
    }

    function init_policy() {
        // Check that any requested policy was properly loaded.
        // The PHP constant SUBROSA_POLICY should be defined in
        // the policy plugin file and contains the PHP class name.
        if ( defined( 'SUBROSA_POLICY' )) {
            $policy_class  =  SUBROSA_POLICY;
            $policy        =  new $policy_class();
            $this->policy  =& $policy;
        }
        elseif ( isset( $request_policy )) {
            die ( 'ERROR: The requested SubRosa policy, '
                .  SUBROSA_POLICY
                . ', could not be loaded');
        }
    }

    function handle_request() {
        $file      = $_SERVER['REQUEST_URI'];
        $file_info = apache_lookup_uri( $file );
        # FIXME Hardcoded extensions
        if ( ! preg_match( '/\.(php|html)$/', $file_info->uri )) { 
            header('content-type: ' . $file_info->content_type);
            $this->marker(print_r(array(
                'REQ_URI'      => $file,
                'file_info'    => $file_info,
                'content_type' => $file_info->content_type,
            ), true));
            $this->log_dump();
            // $this->log_dump(array('noscreen' => 1));
            virtual( $file_info->uri );
            exit( 0 );
        }
        $this->log_dump(array('noscreen' => 1));
    }

    function &init_auth( $username=null, $password=null ) {
        if ( $this->auth ) return $this->auth;
        $this->marker('Initializing authentication');

        # Load user and user meta data
        require_once('SubRosa/MT/Auth.php');
        $auth       =  new SubRosa_MT_Auth( $username, $password );
        $this->auth =& $auth;
        $auth->init();
        return $auth;
    }

    function fix_request_path( $path='' ) {

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

    /***
     * Retrieves a context and rendering object.
     * This is a precise copy of parent::context() EXCEPT
     * that we are using SubRosa/MT/Viewer.php
     */
    function &context() {
        static $ctx;
        if (isset($ctx)) return $ctx;

        require_once('SubRosa/MT/Viewer.php');
        $ctx = new SubRosa_MT_Viewer($this);
        $ctx->mt =& $this;
        $mtphpdir = $this->config('PHPDir');
        $mtlibdir = $this->config('PHPLibDir');
        $ctx->compile_check = 1;
        $ctx->caching = false;
        $ctx->plugins_dir[] = $mtlibdir;
        $ctx->plugins_dir[] = $mtphpdir . DIRECTORY_SEPARATOR . "plugins";
        if ($this->debugging) {
            $ctx->debugging_ctrl = 'URL';
            $ctx->debug_tpl = $mtphpdir . DIRECTORY_SEPARATOR .
                'extlib' . DIRECTORY_SEPARATOR .
                'smarty' . DIRECTORY_SEPARATOR . "libs" . DIRECTORY_SEPARATOR .
                'debug.tpl';
        }

        #if (isset($this->config('SafeMode')) && ($this->config('SafeMode'))) {
        #    // disable PHP support
        #    $ctx->php_handling = SMARTY_PHP_REMOVE;
        #}
        return $ctx;
    }

// ERROR HANDLER function from PHP manual
//    function error_handler($errno, $errstr, $errfile, $errline) {
//      switch ($errno) {
//        case E_USER_ERROR:
//    echo "<b>My ERROR</b> [$errno] $errstr<br />\n";
//    echo "  Fatal error on line $errline in file $errfile";
//    echo ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
//    echo "Aborting...<br />\n";
//    exit(1);
//    break;
//
//  case E_USER_WARNING:
//    echo "<b>My WARNING</b> [$errno] $errstr<br />\n";
//    break;
//
//  case E_USER_NOTICE:
//    echo "<b>My NOTICE</b> [$errno] $errstr<br />\n";
//    break;
//
//  default:
//    echo "Unknown error type: [$errno] $errstr<br />\n";
//    break;
//  }
//
//  /* Don't execute PHP internal error handler */
//  return true;
//      }
//
//    return;
//  }

    function error_handler($errno, $errstr, $errfile, $errline) {

        if ($errno & (E_ALL ^ E_NOTICE)) {
            $mtphpdir = $this->config['PHPDir'];
            $ctx =& $this->context();
            $ctx->stash( 'blog_id', $this->blog_id );
            $ctx->stash( 'blog',    $this->db->fetch_blog($this->blog_id));
            $ctx->stash( 'error_message', $errstr."<!-- file: $errfile; line: $errline; code: $errno -->");
            $ctx->stash( 'error_code', $errno );
            $http_error = $this->http_error;
            empty( $http_error ) and $http_error = 500;
            $ctx->stash('http_error', $http_error);
            $ctx->stash('error_file', $errfile);
            $ctx->stash('error_line', $errline);
            $ctx->template_dir = SubRosa_Util::os_path( $mtphpdir, 'tmpl' );
            $ctx->caching = 0;
            $ctx->stash('StaticWebPath',   $this->config['StaticWebPath']);
            $ctx->stash('PublishCharset',  $this->config['PublishCharset']);
            $charset = $this->config['PublishCharset'];
            $out = $ctx->tag('Include', 
                             array('identifier' => 'dynamic_error'));
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
                if ($log) $error_console .= implode("\n", $log);
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
            $req_ext = $ctx->stash('request_extension');
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


    function blog() {
        $ctx =& $this->context();
        $blog =& $ctx->stash('blog');
        if (!$blog) {
            $db          =& $this->db();
            $ctx->mt->db =& $db;
            $blog        =& $db->fetch_blog($this->blog_id);
            $ctx->stash( 'blog', $blog );
            $ctx->stash( 'blog_id', $this->blog_id );
            $this->configure_paths( $blog['blog_site_path'] );
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


    /********************************
     *   LOGGING UTILITY FUNCTIONS  *
     ********************************/
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

    /********************************
     * DEBUGGING UTILITY FUNCTIONS  *
     ********************************/
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
 */

 // textmate_backtrace();
 // die("Exiting at ". __FILE__ . ', line ' .__LINE__);

?>
