<?php
/**
* SubRosa_Response
*/
class SubRosa_Response {

    private $headers          = array();
    private $error_code       = '200 OK';
    private $virtual_requests = array();
    private $target_resolved  = 0;
    public  $mime_types       = array();
    private $content_type;
    private $target;
    private $buffer;
    private $redirect;
    private  $mime_magic_file;
    private $finfo_obj;

    function __construct() {
        ob_start();
        $this->init_finfo_obj();
    }

    // function output_callback( $buffer ) {
        // error_log('In SubRosa_Response::output_callback');
        // return false;
    // }

    function __destruct() {
        // error_log('In SubRosa_Response::__destruct');
        flush();
        ob_end_flush();
    }

    // To set a header:
    //          $response->header( 'Content-type', 'text/html' );
    //
    // To get a header:
    //          my $content_type = $response->header( 'Content-type' );
    //
    // To unset a header:
    //          $response->header( 'Content-type', false ); # OR
    //          $response->header( 'Content-type', '' );
    function header( $h, $v = null ) {
        global $mt;
        if (is_null( $v )) {                    // Get header
            $mt->marker("Returning header query for '$h': "
                        .$this->headers[$h] );
            return $this->headers[$h];
        }
        if ( ($v === false) || ($v == '') ) {       // Unset header
            $mt->marker("Unsetting header '$h'. Was " .$this->headers[$h] );
            unset( $this->headers[$h] );
            return;
        }
        $mt->marker("Setting header '$h' to " .$this->headers[$h] );
        $this->headers[$h] = $v;                // Set header
    }

    // This clears all previous headers
    function clear_headers() { $this->headers = array(); }

    // Dispatch request to one of the following:
    //     * The current SCRIPT_NAME or SCRIPT URI (input is null)
    //     * An External URL (forces redirect)
    //     * An Internal URL (with curl)
    //     * Any kind of filesystem path (return depends on content )
    public function dispatch() {
        global $mt;
        $mt->marker("Dispatch requested for ".$this->target);
        // var_dump($_SERVER); var_dump($_REQUEST); //exit;
        $mt->log_dump();
        if ( strpos( $this->target, 'http' ) === 0 ) {
            $this->dispatch_url();
        }
        else {
            $this->dispatch_file();
        }
    }

    public function dispatch_url() {
        $url = isset($this->target) ? $this->target : $_SERVER['SCRIPT_URI'];
        $url_info = parse_url( $url );
        if ( ! isset($url_info) || ! isset($url_info['host']) ) {
            throw new Exception('Non-URL detected in dispatch_url: '.$url);
        }
        return ( $url_info['host'] != $_SERVER['HTTP_HOST'] )
            ? $this->_dispatch_redirect( $file )
            : $this->_dispatch_curl( $file );
    }

    public function dispatch_file() {
        // Use target if it's set or fall back to SCRIPT_NAME
        $file = isset($this->target) ? $this->target 
                                     : $_SERVER['SCRIPT_NAME'];
            // 'SCRIPT_URL', 'REQUEST_URI', 'SCRIPT_NAME', 'PHP_SELF'
            //  all have '/docroot/relative/path/to/file.html'

        // Derive absolute path to file, appending the document root for URIs
        $realfile = realpath($file);
        if ( empty($realfile) ) {
            $realfile = realpath( SubRosa_Util::document_root() . $file );
        }
        // Gather information about the resolved filepath
        if ( isset( $realfile ) && is_readable( $realfile )) {
            $pinfo     = pathinfo($realfile);
            $mimetype  = SubRosa_Util::get_mime_type($realfile);
            $is_parsed = in_array( $pinfo['extension'], array('php', 'html'));
            $is_text   = ( strpos($mimetype, 'text/') === 0 );
            // var_dump(array('pinfo' => $pinfo, 'mime' => $mimetype, 'is_parsed' => $is_parsed, 'is_text' => $is_text));
            $this->target_resolved = 1; // Set to return right after
        }

        // Use include_once to return PHP-parsed files // FIXME Hardcoded php/html)
        if ( $is_parsed ) {
            $this->_dispatch_parsed( $realfile );
        }
        // Use readfile to return files with text mime types
        elseif ( $is_text ) {
            $this->header('Content-Type', $mimetype );
            $this->_dispatch_text( $realfile );
        }
        // Otherwise, we use Apache to serve the request
        else {
            if ( isset($realfile) ) {
                $uri = substr_replace($realfile, '', 0, 
                                      strlen(SubRosa_Util::document_root()) );
                $this->target = DIRECTORY_SEPARATOR . ltrim($uri, '/\\');
            }
            else {
                // FIXME This should be a redirect for Apache Error doc
                $mt->marker("Bad file given: $file. Returning apache request URI: "
                            .$_SERVER['SCRIPT_NAME']);
                unset($this->target);
            }
            $this->dispatch_url(); // Redirects to self
        }
    }

    private function _dispatch_curl( $fdata ) {
        $qs = $_SERVER['QUERY_STRING'];
        // ### PHP or html ###
        // ob_start()
        // require_once( $filepath .( isset($qs) ? '?'.$qs : ''));
        // $out = ob_get_contents();
        // $content_length = strlen( $out );
        require_once("libcurlemu.inc.php");

        // create a new CURL resource
        $ch = curl_init();

        // set URL and other appropriate options
        curl_setopt($ch, CURLOPT_URL, "http://www.example.com/");
        curl_setopt($ch, CURLOPT_HEADER, false);

        // grab URL and pass it to the browser
        curl_exec($ch);

        // close CURL resource, and free up system resources
        curl_close($ch);
    }

    private function _dispatch_parsed( $filepath ) {
        $qs = $_SERVER['QUERY_STRING'];
        if (empty($qs)) unset($qs);
        require_once( $filepath . (isset($qs) ? '?'.$qs : '' ));
    }

    private function _dispatch_text( $fdata ){
        // Clean out and discard current buffer and keep buffering
        readfile( $realfile );
    }

    function output() {
        global $mt;
        $mt->marker("Output flush triggered");
        $buffer_length = ob_get_length();
        $redirect = $this->header('Location');
        if ( $buffer_length > 0 || isset($redirect) ) {
            // First flush all headers
            $this->flush_http_headers();
            // Then flush the content (if any)
            ob_end_flush();
            flush();
        }
        else {
            throw new Exception('The dispatcher returned no output');
        }
    }

    function flush_http_headers() {
        global $mt;
        $mt->marker('Sending HTTP headers');
        $mt->marker('Previously sent headers: '.join(', ', headers_list()));
        $head =& $this->headers;

        // Send the initial HTTP error code
        $error_code = $this->error_code ? $this->error_code : '200 OK';
        header("HTTP/1.1 $error_code");

        // Derive the content type if needed
        if ( empty( $head['Content-Type'] )) {
            $content_type = $this->lookup_mime_type();
            $content_type or $content_type = 'text/html';
        }

        // Content-type header-- need to supplement with charset
        if (isset($config['PublishCharset'])) {
            if (!preg_match('/charset=/', $content_type))
                $content_type .= '; charset=' . $config['PublishCharset'];
        }
        header("Content-Type: $content_type");

        foreach ( $this->headers as $key => $val ) {
            header( $key, $val );
        }

        // Get content length of final buffer and send in header
        header("Content-Length: ".ob_get_length());
        $mt->marker('Headers sent thus far: '.join(', ', headers_list()));
    }

    function redirect( $uri = null ) {
        if (empty( $uri )) return isset( $this->redirect );
        ob_clean();
        $this->header( 'Location', $this->redirect = $uri );
    }
    function _dispatch_redirect( $uri = null ) { $this->redirect( $uri ); }

    function reload() {
        $qs = $_SERVER['QUERY_STRING'];
        if (empty($qs)) unset($qs);
        $this->redirect( $_SERVER['PHP_SELF'] 
                                    . (isset($qs) ? '?'.$qs : '' ));
    }

    function login_page($params = null) {
        trigger_error('NOT IMPLEMENTED', E_USER_ERROR );
    }

    function error_page($params = null) {
        trigger_error('NOT IMPLEMENTED', E_USER_ERROR );
    }

    function die_well( $msg = null, $errcode = 1 ) {
        global $mt;
        $mt->log_dump();
        ob_end_flush();
        fwrite(STDERR,
            "An error occurred: ".(isset($msg) ? $msg : 'Unknown error')."\n"
        );
        exit( $errcode ); // A response code other than 0 is a failure
    }

    function lookup_mime_type( $ext = null ) {
        $content_type = $this->mime_types['__default__'];
        if (empty($ext)) {
            $ext = pathinfo( $_SERVER['SCRIPT_NAME'], PATHINFO_EXTENSION );
        }
        if ($ext && (isset($this->mime_types[$ext]))) {
            $content_type = $this->mime_types[$ext];
        }
        else {
            $content_type = $this->server_mime_type(
                $_SERVER['SCRIPT_NAME'],
                $this->mime_magic_file
            );
        }
        return $content_type;
    }

    public function mime_magic_file( $file = null ) {
        if ( isset($file) or empty($this->mime_magic_file) ) {
            foreach (array( $file, $_ENV['MIME_MAGIC_FILE'] ) as $magic ) {
                $magic = realpath( $magic );
                if (empty( $magic )) continue;
                $this->mime_magic_file = $magic;
            }
        }
        return $this->mime_magic_file;
    }

    public function init_finfo_obj() {
        if (isset( $this->finfo_obj )) return $this->finfo_obj;
        /**
         * Locate MIME type magic file
         */
        // Mime type deducation ala mimetype extension
        // If there's any trouble, read the notes on
        // http://www.php.net/manual/en/fileinfo.configuration.php
        // APACHE's file can usually be found in conf/mime.types
        $magicfiles = array( 
            $this->mime_magic_file(),
            '/usr/share/misc/magic',
            '/usr/share/misc/magic.mgc',
            '/usr/share/file/magic',
        );
        foreach ($magicfiles as $magic) {
            if ( file_exists($magic) && is_readable($magic) ) {
                $finfo = new finfo( FILEINFO_MIME | FILEINFO_SYMLINK, $magic);
                if ( isset($finfo) ) break;
            }
        }
        isset( $finfo ) and $this->finfo_obj =& $finfo;
    }

    public static function server_mime_type( $filepath ) {
        $realpath = realpath( $filepath );
        if (empty($realpath)) throw new Exception("Cannot get$filepath is ");

        if ( isset($filepath) ) {

            if ( $finfo ) {
                $mime_type = $finfo->file( $filepath );
                if (isset($mime_type)) return $mime_type;
            }

            // If we had no luck, we try an Apache lookup
            if (strpos($filepath, $_SERVER['DOCUMENT_ROOT']) === 0) {
                $uri = substr_replace(
                    $filepath, '', 0, strlen($_SERVER['DOCUMENT_ROOT']) );
                global $mt;
                $uri   = DIRECTORY_SEPARATOR . ltrim($uri, '/\\');
                $mt->marker("Performing an apache_lookup_uri derived from filepath: $uri");
                $mt->log_dump();

                $passthru = apache_getenv( 'SUBROSA_PASSTHRU' );
                apache_setenv('SUBROSA_PASSTHRU', 1 );
                $mt->fullmarker(  'SUBROSA_PASSTHRU temporarily enabled '
                                . "(was $passthru) for virtual() request for "
                                . $_SERVER['REQUEST_URI'] );
                $apacheres = apache_lookup_uri($uri);
                apache_setenv('SUBROSA_PASSTHRU', $passthru );
                $mt->fullmarker(
                      "SUBROSA_PASSTHRU restored to $passthru after "
                    . 'virtual() request for '.$_SERVER['REQUEST_URI'] );

                $mime_type = $apacheres->content_type;
                if (isset($mime_type)) return $mime_type;
            }
        }

        trigger_error(
            'Error opening fileinfo database. Could not determine mime type',
            E_USER_WARNING
        );
        $mt->marker('Back from trigger_error with no mime type warning');
    }



}

?>