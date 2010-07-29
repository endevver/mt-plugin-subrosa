<?php
exit;

    /***
     * Mainline handler function.
     */
    function view( $blog_id = null )
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
            $this->log_dump(array('noscreen' => 1));
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

    function TEMP_error_handler($errno, $errstr, $errfile, $errline) {

        $out = $ctx->tag('Include', 
                         array('identifier' => 'dynamic_error'));

        if ($this->debugging) {
            $log = $this->logger->current_log();
            $error_console = "<div class=\"debug\" style=\"border:1px solid red; margin:0.5em; padding: 0 1em; text-align:left; background-color:#ddd; color:#000\"><pre>";
            if ($log) $error_console .= implode("\n", $log);
            $error_console .= "</pre></div>\n\n";
            echo $error_console;
        }
        exit;
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
        $out = $this->error_page();

        if ( is_null( $out )) {

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

    // FIXME Reconcile with SubRosa_Response::error_page()
    function error_page() {
        $out = null;
        if (isset($this->page['error'])) {
            $this->log('Using custom error page: '. $this->page['error']);
            ob_start();
            require_once( $this->page['error'] );
            $out = ob_get_contents();
            ob_end_clean();
        }
        return $out;
    }

    function error_handler($errno, $errstr, $errfile, $errline) {

        // First, check to see whether we're suppressing the display of the 
        // error locally since MT throws way too many warnings.
        // Immediately return true for suppressed MT errors making it a no-op
        if ($this->error_is_suppressed( $errno, $errstr, $errfile, $errline)){
            return true;
        }

        $this->logger->fullmarker('SUBROSA ERROR HANDLER ENCOUNTERED: '
            .print_r(array(
                'errno'   => $errno, 
                'errstr'  => $errstr, 
                'errfile' => $errfile, 
                'errline' => $errline
            ), true)
        );

        if ( ! ($errno & $this->error_level )) return;

        switch ($errno) {
            case E_USER_ERROR:
                echo "<b>My ERROR</b> [$errno] $errstr<br />\n";
                echo "  Fatal error on line $errline in file $errfile";
                echo ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
                echo "Aborting...<br />\n";
                // exit(1);
                break;

            case E_USER_WARNING:
                echo "<b>My WARNING</b> [$errno] $errstr in $errfile line $errline<br />\n";
                break;

            case E_USER_NOTICE:
                echo "<b>My USER NOTICE</b> [$errno] $errstr in $errfile line $errline<br />\n";
                break;

            case E_NOTICE:
                echo "<b>PHP NOTICE</b> [$errno] $errstr in $errfile line $errline<br />\n";
                break;

            // case E_NOTICE:
            //     // SubRosa_Util::os_path( 
            //     //     $subrosa_config['mt_dir'], 'php', 'lib' )
            //     if (strpos( $errfile, $this->config('PHPLibDir') === false )){
            //         echo "<b>My E_NOTICE</b> [$errno] $errstr in $errfile line $errline<br />\n";
            //     }
            //     break;

            default:
                ob_start();
                var_dump( func_get_args() );
                $this->marker( ob_get_contents() );
                ob_end_clean();
                $this->log_dump();
                return true;
                parent::error_handler( $errno, $errstr, $errfile, $errline );
                // echo "Unknown error type: [$errno] $errstr in $errfile line $errline<br />\n";
                break;
        }

        /* Don't execute PHP internal error handler */
        return true;
    }

 //    return;
 // }

    function error_is_suppressed($errno, $errstr, $errfile, $errline) {
        // Check to see if 

        // Check to see whether the file with the error is in the MT PHP dir
        $mtphpdir = $this->config( 'PHPDir' );
        $is_mt    = ( strpos( $errfile, $mtphpdir ) === 0 );            

        // NOT an MT_HOME/php script
        if ( ! $is_mt ) {
            // Check to see whether it's in one of the MT addons packs
            $addons   = Subrosa_Util::os_path( $this->mt_dir, 'addons' );
            $packs    = array('Commercial', 'Community', 'Enterprise');
            foreach ( $packs as $pack ) {
                $packlib = Subrosa_Util::os_path( $addons, $pack.'.pack' );
                if ( strpos( $errfile, $packlib ) === 0 ) $is_mt = 1;
            }
        }

        if ( $is_mt ) {
            // Check config to see if this MT error level should be suppressed
            if ( $errno & $this->suppress_mt_error ) {
                return true;
            }

            // Annoying warnings, frequently and purposefully coded
            foreach (array('property', 'index') as $key) {
                if ( strpos( $errstr , "Undefined $key" ) === 0 ) {
                    return true;
                }
            }
        }

        // Not supressed
        return false;
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
        $auth =& $this->init_auth( $_POST['username'], $_POST['password'] );
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
        $auth =& $this->init_auth();

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
        $auth =& $this->init_auth();

        if ($auth->session()) $auth->logout();

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

/*
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
?>