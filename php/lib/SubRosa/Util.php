<?php
/**
*  SubRosa_Util class utilties
*/
class SubRosa_Util
{
    function phpsession($key=null, $val=null) {
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

    function magic_token() {
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

    function os_path() {
        return str_replace(
            '//', '/', join( DIRECTORY_SEPARATOR, func_get_args() )
        );
    }

    function unpack_session_data($sdata) {
        global $mt;
        $mt->marker();
        $mtdb =& $mt->db;
        if (!$mtdb->serializer) {
            require_once($mt->config['PHPLibDir'].'/MTSerialize.php');
            $serializer = new MTSerialize();
            $mtdb->serializer =& $serializer;
        }
        $session_data = $mtdb->unserialize($sdata);
        return $session_data;
    }

    function hashval($key='', $array=array()) {
        if (isset($array) and array_key_exists($key, $array)) {
            return $array[$key];
        }
    }

    function is_authorized($url) {
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

    function get_user_cookie( $cname='mt_user' ) {
        $usercookie = self::hashval($cname, $_COOKIE);
        if ($usercookie) {
            $parts = explode('::', $usercookie);
            return $parts;
        }
        return array(null, null, null);
    }

    function sysdebug() {
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

    function printElementHtml( $value, $key ) { 
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