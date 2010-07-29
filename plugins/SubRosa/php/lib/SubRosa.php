<?php
// === TextMate error handling ===
// include_once '/Applications/TextMate.app/Contents/SharedSupport/Bundles/PHP.tmbundle/Support/textmate.php';

// include_path: Prepend SubRosa and MT PHP lib and extlib directories
ini_set('include_path', join( ':', array(   dirname( __FILE__ ),
                                            ini_get('include_path') )));

require_once( 'SubRosa/Env/Debug.php' );
$subrosa_debugenv = new SubRosa_Env_Debug();
// require_once( 'SubRosa/Env.php' );
// $subrosa_env = new SubRosa_Env();
// require_once('log4php/Logger.php');
require_once( 'SubRosa/Util.php' );
require_once( 'SubRosa/Logger.php' );
require_once(
    SubRosa_Util::os_path( $subrosa_config['mt_dir'], 'php', 'mt.php' ) );

/**
* MT-SubRosa
*/
class SubRosa extends MT {

    const VERSION = '3.0';    // SubRosa version

    // Declare properties and set defaults
    public $logger              = NULL;
    public $log_delay           = true;
    public $log_queries         = false;
    public $plugins_initialized = false;
    public $user_cookie         = 'mt_user';
    public $user_session_key    = 'current_user';
    public $session             = array();
    public $suppress_mt_error   = E_DEPRECATED;
    public $request_info        = array();
    public $debugging           = false;
    public $request;
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

      /**
       * Assign values for object properties from config 
       */
        global $subrosa_config;
        foreach ( array_keys($subrosa_config) as $key ) {

            // Matches namespaced keys like session.cookie_domain
            if ( preg_match('/^([a-z]+)\.([a-z_]+)$/', $key, $matches )) {

                // which becomes ${ $this->session }['cookie_domain']
                // i.e. $this->session is an associative array
                $property   =  $matches[1]; // The "session" in "session.foo"
                $prop_hash  =& $this->$property; // Must be a reference!

                // $matches[2] is the subproperty, i.e. "foo" in "session.foo"
                // $matches[0] is the original config key, i.e. session.foo
                $prop_hash[ $matches[2] ] =  $subrosa_config[ $matches[0] ];
            }
            // Matches normal config directives (e.g. error_log)
            else {
                $this->$key = $subrosa_config[$key];
            }
        }

        // Initialize the logging system for debugging
        $this->init_logger();

        // Initialize the MT base class ( this also calls our init() method )
        $this->init_mt( $cfg_file, $blog_id );

        // Post-merge of mime types to allow for override
        $this->mime_types = array_merge( $this->mime_types, 
                                         $subrosa_config['mime_types'] );

        $this->init_session();
        $this->init_response();

        // Set up our customer error handler -- Moved to SubRosa old
        // FIXME set_error_handler(array( &$this, 'error_handler' ));
    }

    function init_logger() {
        if ( isset($this->logger) ) return;
        $this->logger = new SubRosa_Logger( $this->log_output );
        if ( isset($_REQUEST['debug']) ) $this->debugging = true;
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
        $this->init_viewer();
        $this->init_plugins();
        $this->init_policy();
        if ($this->response->redirect()) return $this->redirect_request();

        // Handle mt-preview URLs by not handling them
        if ( SubRosa_Util::url_is_entry_preview() ) {
            $this->marker(  'Is MT preview: '. $_SERVER['REQUEST_URI'] );
            $this->allow_request( $entry_id );
        }
        else {
            $this->u_is_approved = $this->policy->check_request( $entry_id );
        }
        if ($this->response->redirect()) return $this->redirect_request();

        //// Testing direct access by Jay
        // if (   ($_SERVER['REMOTE_ADDR'] == '24.130.173.174')
        //     && ($req_check === true)) {
        if ( $this->u_is_approved ) {
            $this->allow_request( $entry_id );
        }
        else {
            $this->deny_request( $entry_id );
        }
        $this->cleanup_viewer();
        exit();
    }

    function redirect_request() {
        $this->u_is_approved = true;
        $this->handle_response( true );
        $this->cleanup_viewer();
        exit();
    }

    function allow_request( $entry_id = null ) {
        if (   ( $_SERVER['HTTP_HOST'] == 'ccsa.local' )
            && ( isset($_GET['deny']) )) {
            return $this->deny_request( $entry_id );
        }
        // $_SESSION['authorized_token'] = 1;
        $this->u_is_approved = true;
        apache_setenv('SUBROSA_PASSTHRU', 1 );
        $this->fullmarker('SUBROSA_PASSTHRU enabled for authorized request: '
                        . $_SERVER['REQUEST_URI'] );
        $this->handle_response( true, $entry_id );
    }

    function deny_request( $entry_id = null ) {
        if (   ( $_SERVER['HTTP_HOST'] == 'ccsa.local' )
            && ( isset($_GET['allow']) )) {
            return $this->allow_request( $entry_id );
        }
        $this->u_is_approved = false;
        apache_setenv('SUBROSA_PASSTHRU', 0 );
        $this->fullmarker('SUBROSA_PASSTHRU disabled for unauthorized request: '
                        . $_SERVER['REQUEST_URI'] );
        $this->handle_response( false, $entry_id );
    }

    function handle_response( $authorized, $entry_id = null ) {
        $this->marker( sprintf( 'ACCESS REQUEST %s: <pre>%s</pre>', 
                                ( $authorized ? 'ALLOWED' : 'DENIED' ),
                                print_r($_SESSION, true)    ));
        $response =& $this->response;

        if ( ! $response->redirect() ) {
            if ( $authorized ) {
                $response->dispatch( $_SERVER['SCRIPT_NAME'] );
            }
            else {
                print "YOU ARE NOT AUTHORIZED.";
            }
        }
        $response->output();
    }

    function init_session() {
        //kill_php_current_session();
        // show_current_request_info();
        session_name('SubRosa');
        session_start();
        if ( ! isset($_SESSION['initiated']) ) {
            session_regenerate_id();    // Hampers session fixation
            $_SESSION['initiated'] = true;
        }
        $ua      = $_SERVER['HTTP_USER_AGENT'];
        $ua_sess = $_SESSION['HTTP_USER_AGENT'];
        if ( isset($ua_sess) ) {
            if ( $ua_sess != md5($ua) ) {
                $_SESSION = array();
                
                // If it's desired to kill the session, also delete the 
                // session cookie. Note: This will destroy the session, and 
                // not just the session data!
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }

                // Finally, destroy the session.
                session_destroy();

                $this->response->reload();
                exit;
            }
        }
        else {
            $ua_sess = md5($ua);
        }
    }

    function init_response() {
        $this->marker('Initializing response');
        if ( empty( $this->response ) ) {
            require_once('SubRosa/Response.php');
            $response             =  new SubRosa_Response();
            $response->mime_types =& $this->mime_types;
            $this->response       =& $response;
        }
        else {
            trigger_error('SubRosa_Response object re-initialization.');
        }
        return $this->response;
    }
    
    // FIXME - Most of this should be moved to our viewer
    function init_viewer() {
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
        // $this->marker(sprintf('<pre>%s</pre>',
        //     print_r(
        //         array(
        //             'request' => $_REQUEST,
        //             'post'    => $_POST,
        //             'cookie'  => $_COOKIE,
        //             'session' => $_SESSION
        //         ),
        //         true
        //     )
        // ));

        $this->request = $this->fix_request_path($this->request);
        $ctx->stash(
            'request_extension', 
            strtolower(pathinfo( $this->request, PATHINFO_EXTENSION ))
        );
        // Superceded by native functionality above
        // if (preg_match('/\.(\w+)$/', $this->request, $matches)) {
        //     $ctx->stash('request_extension', strtolower($matches[1]));
        // }
    }

    // FIXME - This should be moved to our viewer
    function cleanup_viewer() {
        $this->marker('Cleaning up viewer');
        $this->log_dump(array('noscreen' => 1));
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
        if ( defined( 'SUBROSA_POLICY' )) { // FIXME Maybe out of scope?
            $policy_class  =  SUBROSA_POLICY;
            $policy        =  new $policy_class();
            $this->policy  =& $policy;
            $this->marker('Policy initialized: '
                            .print_r($this->policy, true));
        }
        elseif ( isset( $request_policy )) { // FIXME Out of scope!
            die ( 'ERROR: The requested SubRosa policy, '
                .  SUBROSA_POLICY
                . ', could not be loaded');
        }
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
        $this->marker("In fix_request_path with "
                     .print_r(array(
                         'path'    => $path,
                         'req_uri' => $_SERVER['REQUEST_URI'],
                         'server'  => $_SERVER['SERVER_SOFTWARE'],
                         'query'   => $_SERVER['QUERY_STRING']
                      ), true)
        );

        // Apache request
        if (!$path && $_SERVER['REQUEST_URI']) {
            $path = $_SERVER['REQUEST_URI'];
            // strip off any query string...
            $path = preg_replace('/\?.*/', '', $path);
            // strip any duplicated slashes...
            $path = preg_replace('!/+!', '/', $path);
        }
        $this->marker("Path manipulated: $path");

        // IIS request by error document...
        if (preg_match('/IIS/', $_SERVER['SERVER_SOFTWARE'])) {
            // assume 404 handler
            if (preg_match( '/^\d+;(.*)$/', 
                            $_SERVER['QUERY_STRING'], $matches)) {
                $path = $matches[1];
                $path = preg_replace('!^http://[^/]+!', '', $path);
                if (preg_match('/\?(.+)?/', $path, $matches)) {
                    $_SERVER['QUERY_STRING'] = $matches[1];
                    $path = preg_replace('/\?.*$/', '', $path);
                }
            }
        }
        $this->marker("Path fixed: $path");

        // We no longer use ErrorDocument
        // // When we are invoked as an ErrorDocument, the parameters are
        // // in the environment variables REDIRECT_*
        // if (isset($_SERVER['REDIRECT_QUERY_STRING'])) {
        //     // TODO: populate $_GET and QUERY_STRING with REDIRECT_QUERY_STRING
        //     $_SERVER['QUERY_STRING'] = getenv('REDIRECT_QUERY_STRING');
        // }

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

    function &resolve_url($path) {
        // FIXME NEED BLOG ID IN $this
        $data =& parent::resolve_url( $path );
        print '<h1>resolve_url results for '.$path.'</h1>';
        var_dump($data);
        $this->response->output();
        $this->cleanup_viewer();
        exit();
    }

    // function &resolve_url($path) {
    //     $data =& $this->db->resolve_url($path, $this->blog_id);
    //     if ( isset($data)
    //         && isset($data['fileinfo']['fileinfo_entry_id'])
    //         && is_numeric($data['fileinfo']['fileinfo_entry_id'])
    //     ) {
    //         if (strtolower($data['templatemap']['templatemap_archive_type']) == 'page') {
    //             $entry = $this->db->fetch_page($data['fileinfo']['fileinfo_entry_id']);
    //         } else {
    //             $entry = $this->db->fetch_entry($data['fileinfo']['fileinfo_entry_id']);
    //         }
    //         require_once('function.mtentrystatus.php');
    //         if (!isset($entry) || $entry['entry_status'] != STATUS_RELEASE)
    //             return;
    //     }
    //     return $data;
    // }

    function set_cookie_defaults() {
        $this->marker();
        // Set cookie/session defaults in lieu of
        // specification in of directives in mt.config.cgi
        $this->session_timeout = $this->config['UserSessionTimeout'];
        $this->cookie_path     = $this->config['CookiePath'];
        $this->cookie_domain   = $this->config['CookieDomain'];

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
        $ctx->stash('help_url',
            'http://www.sixapart.com/movabletype/docs/enterprise/1.5/');
        $ctx->stash('language_encoding', $this->config['PublishCharset']);
        $ctx->stash('mt_url',   
            SubRosa_Util::os_path(
                $this->config['AdminCGIPath'],
                $this->config['AdminScript']
            )
        );
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

    function log($msg='')    { $this->logger->log($msg);    }

    function notify($msg='') { $this->logger->notify($msg); }

    function marker($msg='') { $this->logger->marker($msg); }

    function fullmarker($msg='') { $this->logger->fullmarker($msg); }

    function log_dump($opts='') {
        if ($this->debugging != true) return;
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
