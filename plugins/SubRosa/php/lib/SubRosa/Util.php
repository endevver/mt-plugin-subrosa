<?php
/**
*  SubRosa_Util class utilties
*/
class SubRosa_Util {

    public static function url_is_entry_preview($u = null){
        isset($u) or $u = $_SERVER['REQUEST_URI'];
        // /2010/06/mt-preview-d1c087f22262e5264c6b57e21ae1c84eded.html?083153
        $preview_regex    = '/\/mt-preview-[A-Za-z0-9]+\.html\?[0-9]+/';
        return preg_match( $preview_regex, $u );
    }

    public static function document_root() {
        if ( ! isset($_SERVER['DOCUMENT_ROOT'] )) {
            $abs_filepath = $_SERVER['SCRIPT_FILENAME'];
            $relative_uri = $_SERVER['PHP_SELF'];
            $_SERVER['DOCUMENT_ROOT'] = str_replace( '\\', '/',
                substr( $abs_filepath, 0, 0-strlen($relative_uri) ) );
        }
        return $_SERVER['DOCUMENT_ROOT'];
    }

    public static function phpsession($key=null, $val=null) {
        global $mt;
        $sess_key = $mt->user_session_key;
        if (isset($key) and isset($val)) {
            if ($val === false) {
                unset( $_SESSION[$sess_key][$key] );
            }
            else {
                $_SESSION[$sess_key][$key] = $val;
            }
        } elseif (isset($key)) {
            if ($key === false) {
                unset( $_SESSION[$sess_key] );
            }
            else {
                return self::hashval( $key, $_SESSION[$sess_key]);
            }
        } else {
            return self::hashval( $sess_key, $_SESSION );
        }
    }

    public static function magic_token() {
        $alpha = array_merge(range('a','z'), range('A','Z'), range(0,9));
        srand((float)microtime() * 1000000);
        $rand_keys = array_rand($alpha, 40);
        foreach ($rand_keys as $key) {
            $tokens[] = $alpha[$key];
        }
        shuffle($tokens);
        $token = join('', $tokens);
        return $token;
    }

    public static function pathinfo_utf( $path ) {
      if (strpos($path, '/') !== false)
          $basename = end(explode('/', $path));
      elseif (strpos($path, '\\') !== false)
          $basename = end(explode('\\', $path));
      else return false;

      if (empty($basename)) return false;

      $dirname = substr($path, 0, strlen($path) - strlen($basename) - 1);

      if (strpos($basename, '.') !== false) {
          $extension = end(explode('.', $path));
          $filename  =
              substr($basename, 0, strlen($basename) - strlen($extension)-1);
      }
      else {
          $extension = '';
          $filename  = $basename;
      }

      return array(
        'dirname'   => $dirname,
        'basename'  => $basename,
        'extension' => $extension,
        'filename'  => $filename
      );
    }

    public static function os_path() {
        $arrrrrgs = func_get_args();
        return str_replace(
            '//', '/', join( DIRECTORY_SEPARATOR, $arrrrrgs ));
    }

    // This function will turn any of the path forms below into a proper URL 
    // with a fully-qualified domain name and proper protocol (https?):
    //
    //      Absolute FS path:                /ABSOLUTE/PATH/TO/index.html
    //      Docroot relative URI:            index.html
    //      Docroot relative absolute URI:   /index.html
    //
    // It throws an exception for invalid or non-existent files
    public static function filepath_to_uri ( $path ) {
        echo "<li>filepath_to_uri( $path )</li>";
        $docroot = $_SERVER['DOCUMENT_ROOT'];
        $docroot = preg_replace('/[\\/]+$/', '', $docroot);
        $dir;
        // session.entropy_file => no value => no value

        if ( preg_match('/^https?:\/\//', $path) ) {
            throw new Exception('filepath_to_uri encountered a URI: '
                                 . $path);
        }

        // Verify the path is real and harvest the bottom directory
        $realpath = realpath( $path );
        if ( empty($realpath) ) {
            // Unless the path starts with DOCROOT, prepend it
            if ( strpos($path, $docroot) !== 0 ) {
                $docrooted = self::os_path( $docroot, $path );
                // echo "<li>\$docrooted = $docrooted</li>";
                if ($realdocrooted = realpath($docrooted)) {
                    $realpath = $realdocrooted;
                }
            }
            if ( empty($realpath) ) {
                throw new Exception(
                    'File does not exist: ' 
                    . $path
                    .( isset($docrooted) ? " (Also tried $docrooted)" : '')
                );
            }
        }

        // Get dirname or current dir if path is a directory
        $dir = is_dir($realpath) ? $realpath : dirname($realpath);

        // Make sure the path is not lower than the server root
        if ( strlen($dir)+1 < strlen($docroot) ) {  // +1 for slash
            throw new Exception("Cannot create URI for path $path because "
                    ." it's outside the server DOCUMENT_ROOT, ".$docroot);
        }

        
        $path = implode(array(
            SubRosa_Util::current_protocol(),       // https?
            '://' ,                                 // Obvious
            $_SERVER['HTTP_HOST'] ,                 // Domain
            substr( $realpath, strlen($docroot) )   // Path after docroot
        ));

        // Convert Windows' backslashes to single forward slashes
        if (DIRECTORY_SEPARATOR == '\\')
            $path = str_replace('\\', '/', $path);

        return $path;
    }

    public static function current_protocol() {
        return (   isset($_SERVER['HTTPS'])
                && strtolower($_SERVER['HTTPS']) != 'off' ) 
                        ? 'https' : 'http';
    }

    /**
     * Assuming a document root of /usr/www, below are examples of this
     * methods arguments and return value. Note that the file MUST exist
     * otherwise the method returns null
     *
     *    http://example.com/docs/tutorials/index.html
     *      /usr/www/docs/tutorials/index.html
     *
     *    /docs/tutorials/index.html
     *      Same as above
     * 
     *    docs/tutorials/index.html
     *      Same as above
     */
    public static function uri_to_filepath( $uri ) {
        if (strpos($uri, 'http') === 0) {
            $relpath = parse_url( $uri, PHP_URL_PATH );
        }
        else {
            # Add a leading slash if needed
            $relpath = ( strpos($uri, '/') === 0 ) ? $uri : '/'.$uri;
        }
        $abspath = realpath(
                        self::os_path( 
                            self::document_root(),
                            $relpath
                        )
                    );
        return $abspath;
    }

    /**
     *    Combine a base URL and a relative URL 
     *      (http://nadeausoftware.com/node/79):
     *
     *      $newUrl = url_to_absolute(
     *          "http://example.com/products/index.htm",
     *          "./product.png" );
     *      print( "$newUrl\n" );
     *
     *    Prints:
     *
     *      http://example.com/products/product.png
     */
    public static function url_to_absolute( $baseUrl, $relativeUrl ) {
        // If relative URL has a scheme, clean path and return.
        $r = parse_url( $relativeUrl );
        if ( $r === FALSE )
            return FALSE;
        if ( ! empty( $r['scheme'] )) {
            if ( ! empty( $r['path'] ) && $r['path'][0] == '/' )
                $r['path'] = url_remove_dot_segments( $r['path'] );
            return join_url( $r );
        }

        // Make sure the base URL is absolute.
        $b = split_url( $baseUrl );
        if ( $b === FALSE || empty( $b['scheme'] ) || empty( $b['host'] ) )
            return FALSE;
        $r['scheme'] = $b['scheme'];

        // If relative URL has an authority, clean path and return.
        if ( isset( $r['host'] ) )
        {
            if ( !empty( $r['path'] ) )
                $r['path'] = url_remove_dot_segments( $r['path'] );
            return join_url( $r );
        }
        unset( $r['port'] );
        unset( $r['user'] );
        unset( $r['pass'] );

        // Copy base authority.
        $r['host'] = $b['host'];
        if ( isset( $b['port'] ) ) $r['port'] = $b['port'];
        if ( isset( $b['user'] ) ) $r['user'] = $b['user'];
        if ( isset( $b['pass'] ) ) $r['pass'] = $b['pass'];

        // If relative URL has no path, use base path
        if ( empty( $r['path'] ) )
        {
            if ( !empty( $b['path'] ) )
                $r['path'] = $b['path'];
            if ( !isset( $r['query'] ) && isset( $b['query'] ) )
                $r['query'] = $b['query'];
            return join_url( $r );
        }

        // If relative URL path doesn't start with /, merge with base path
        if ( $r['path'][0] != '/' )
        {
            $base = mb_strrchr( $b['path'], '/', TRUE, 'UTF-8' );
            if ( $base === FALSE ) $base = '';
            $r['path'] = $base . '/' . $r['path'];
        }
        $r['path'] = url_remove_dot_segments( $r['path'] );
        return join_url( $r );
    }

    public static function url_remove_dot_segments( $path ) {
        // multi-byte character explode
        $inSegs  = preg_split( '!/!u', $path );
        $outSegs = array( );
        foreach ( $inSegs as $seg ) {
            if ( $seg == '' || $seg == '.')
                continue;
            if ( $seg == '..' )
                array_pop( $outSegs );
            else
                array_push( $outSegs, $seg );
        }
        $outPath = implode( '/', $outSegs );
        if ( $path[0] == '/' )
            $outPath = '/' . $outPath;
        // compare last multi-byte character against '/'
        if ( $outPath != '/' &&
            (mb_strlen($path)-1) == mb_strrpos( $path, '/', 'UTF-8' ) )
            $outPath .= '/';
        return $outPath;
    }

    public static function set_if_null( &$var=null, &$val=null ) {
        if ( is_null( $var )) $var = $val;
    }

    public static function set_if_empty( &$var=null, &$val=null ) {
        if ( empty( $var )) $var = $val;
    }

    public static function is_assoc_array($array) {
        if ( ! is_array($array) ) return false; // Not an array
        if ( count($array) == 0 ) return true;  // Empty is assoc
        // See entire discussion thread on
        // http://us.php.net/manual/en/function.is-array.php#98305
        return(
            0 !== count(
                        array_diff_key( $array,
                                        array_keys( array_keys($array) ))
                   )
        );
    }

    public static function unpack_session_data($sdata) {
        global $mt;
        //$mt->marker();
        $mtdb =& $mt->db;
        if (!$mtdb->serializer) {
            require_once($mt->config['phplibdir'].'/MTSerialize.php');
            $serializer = new MTSerialize();
            $mtdb->serializer =& $serializer;
        }
        $session_data = $mtdb->unserialize($sdata);
        return $session_data;
    }

    public static function hashval($key='', $array=array()) {
        if (isset($array) and array_key_exists($key, $array)) {
            return $array[$key];
        }
    }

    public static function is_authorized($url) {
        // print "<p>Here in is_authorized</p>";
        list($cuser, $csid, $cpersist) = self::get_user_cookie();
        // print "<p style='text-align: left'><pre style='text-align: left'>";
        // print_r(Array(cuser => $cuser, csid => $csid, cpersist => $cpersist, SESSION => $_SESSION));
        // print "</pre></p>";
        if ($cuser and $csid and $_SESSION['current_user']) {
            // print "<p>we have cookie and current session</p>";
            error_log('We have cookie and PHP session');
            $phpname = $_SESSION['current_user']['name'];
            $phpid = $_SESSION['current_user']['id'];
            $phpsid = $_SESSION['current_user']['session_id'];
            if ($phpid and ($cuser == $phpname) and ($csid == $phpsid)) {
                // print '<p>Cookie and PHP session match</p>';
                error_log('Cookie and PHP session match');
                if ($url == '/') return true;

                global $subrosa_config;
                $blog_path_cfg = $subrosa_config['blog_path'];
                foreach ($blog_path_cfg as $path => $userarray) {
                    // print "<p>Matching $path with ".$_SERVER['SCRIPT_URL'].'</p>';
                    error_log("Matching $path with ".$_SERVER['SCRIPT_URL']);
                    if (strpos($_SERVER['SCRIPT_URL'], $path) == 0) {
                        // print "<p>We have a match, checking for user ID $phpid in array</p>";
                        error_log("We have a match, checking for user ID $phpid in array");
                        return in_array($phpid, $blog_path_cfg[$path]);
                    }
                }
            }
        }
    }

    public static function get_user_cookie( $cname='mt_user' ) {
        $usercookie = self::hashval($cname, $_COOKIE);
        if ($usercookie) {
            $parts = explode('::', $usercookie);
            return $parts;
        }
        return array(null, null, null);
    }

    public static function get_cmtr_cookie( $cname='mt_commenter' ) {
        return self::get_user_cookie('mt_commenter');
    }

    public static function sysdebug() {
        ob_start();
        $variableSets = array(
            "Post:" => $_POST,
            "Get:" => $_GET,
            "Session:" => $_SESSION,
            "Cookies:" => $_COOKIE,
            "Server:" => $_SERVER,
            "Environment:" => $_ENV
        );

        print '<div style="text-align:left">';
        foreach ( $variableSets as $setName => $variableSet ) {
            if ( isset( $variableSet ) ) {
                print "<br /><br />\n\n<hr size='1'>";
                print "$setName<br />\n";
                array_walk( $variableSet, 'printElementHtml' );
            }
        }

        print '</div>';
        $out = ob_get_contents();
        ob_flush();
        return $out;
    }

    public static function printElementHtml( $value, $key ) {
        ob_start();
        echo $key . " => ";
        print_r( $value );
        print "<br />\n";
        $out = ob_get_contents();
        ob_flush();
        return $out;
    }

    // // Generates a pseudo-random UUID according to RFC 4122
    // // Also see http://www.php.net/manual/en/function.uniqid.php
    // function uuid()
    // {
    //     return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    //         mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
    //         mt_rand( 0, 0x0fff ) | 0x4000,
    //         mt_rand( 0, 0x3fff ) | 0x8000,
    //         mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) );
    // }
    //
    // /*
    // Simple (HA!) function to get current page URL using comman PHP variables
    //
    // Function inputs:
    //     $base if set to true will add the basename to the URL
    //     $www if set to true will add www. to host if not found
    //     $query if set to true will add the query string to the URL
    //     $echo if set to true will echo the URL instead of just returning it
    // */
    // function self_url_complex($base = true, $www = true, $query = true, $echo = false){
    //     $URL = ''; //open return variable
    //     $URL .= (($_SERVER['HTTPS'] != '') ? "https://" : "http://"); //get protocol
    //     $URL .= (($www == true && !preg_match("/^www\./", $_SERVER['HTTP_HOST'])) ? 'www.'.$_SERVER['HTTP_HOST'] : $_SERVER['HTTP_HOST']); //get host
    //     $path = (($_SERVER['REQUEST_URI'] != '') ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF']); //tell the function what path variable to use
    //     $URL .= ((pathinfo($path, PATHINFO_DIRNAME) != '/') ? pathinfo($path, PATHINFO_DIRNAME).'/' : pathinfo($path, PATHINFO_DIRNAME)); //set up directory
    //     $URL .= (($base == true) ? pathinfo($path, PATHINFO_BASENAME) : ""); //add basename
    //     $URL  = preg_replace("/\?".preg_quote($_SERVER['QUERY_STRING'])."/", "", $URL); //remove query string if found in url
    //     $URL .= (($query == true && $_SERVER['QUERY_STRING'] != '') ? "?".$_SERVER['QUERY_STRING'] : ""); //add query string
    //     if($echo == true){
    //         echo $URL;
    //     }else{
    //         return $URL;
    //     }
    // }
    //
    // function self_url() {
    //     return $_SERVER['SCRIPT_URI'];
    // }
    //
    // /**
    //  * source: http://us2.php.net/manual/en/function.parse-url.php#60237
    //  * Edit the Query portion of an url
    //  *
    //  * @param    string    $action    ethier a "+" or a "-" depending on what action you want to perform
    //  * @param    mixed    $var    array (+) or string (-)
    //  * @param    string    $uri    the URL to use. if this is left out, it uses $_SERVER['PHP_SELF']
    //  * @version      1.0.0
    //  */
    // function change_query($action, $var = NULL, $uri = NULL) {
    //
    //            if (($action == "+" && !is_array($var)) || ($action == "-" && $var == "") || $var == NULL) {
    //                    return FALSE;
    //            }
    //
    //            if (is_null($uri)) { //Piece together uri string
    //                    $beginning = $_SERVER['PHP_SELF'];
    //                    $ending = (isset ($_SERVER['QUERY_STRING'])) ? $_SERVER['QUERY_STRING'] : '';
    //            } else {
    //                    $qstart = strpos($uri, '?');
    //                    if ($qstart === false) {
    //                            $beginning = $uri; //$ending is '' anyway
    //                            $ending = "";
    //                    } else {
    //                            $beginning = substr($uri, 0, $qstart);
    //                            $ending = substr($uri, $qstart);
    //                    }
    //            }
    //
    //            $vals = array ();
    //            $ending = str_replace('?', '', $ending);
    //            parse_str($ending, $vals);
    //
    //            switch ($action) {
    //                    case '+' :
    //                            $vals[$var[0]] = $var[1];
    //                            break;
    //                    case '-' :
    //                            if (isset ($vals[$var])) {
    //                                    unset ($vals[$var]);
    //                            }
    //                            break;
    //                    default :
    //                            break;
    //            }
    //
    //            $params = array();
    //            foreach ($vals as $k => $value) {
    //                    $params[] = $k."=".urlencode($value);
    //            }
    //            $result = $beginning . (count($params) ? '?' . implode("&", $params) : '');
    //            return $result;
    //    }

}
?>