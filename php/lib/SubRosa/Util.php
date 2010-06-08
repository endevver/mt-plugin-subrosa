<?php

/* SubRosa class utilties */

// FIXME: Bubble some of these utility functions up to the gatekeeper page

function hashval($key='', $array=array()) {
    if (isset($array) and array_key_exists($key, $array)) {
        return $array[$key];
    }
}

function cookie($var=null) {
    if (isset($var)) {
        return hashval($var, $_COOKIE);
    } else {
        return $_COOKIE;
    }
}

function phpsession($key=null, $val=null) {
    global $mt;
    if (isset($key) and isset($val)) {
        if ($val === false) {
            unset($_SESSION[$mt->user_session_key][$key]);
        }
        else {
            $_SESSION[$mt->user_session_key][$key] = $val;
        }
    } elseif (isset($key)) {
        if ($key === false) {
            unset($_SESSION[$mt->user_session_key]);
        }
        else {
            return hashval($key, $_SESSION[$mt->user_session_key]);
        }
    } else {
        return hashval($mt->user_session_key, $_SESSION);
    }
}

function unpack_session_data($sdata) {
    global $mt;
    $mt->marker();
    $mtdb =& $mt->db;
    if (!$mtdb->serializer) {
        require_once('MTSerialize.php');
        $serializer = new MTSerialize();
        $mtdb->serializer =& $serializer;
    }
    $session_data = $mtdb->unserialize($sdata);
    return $session_data;
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

// Generates a pseudo-random UUID according to RFC 4122
// Also see http://www.php.net/manual/en/function.uniqid.php
function uuid()
{
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) );
}

/* 
Simple (HA!) function to get current page URL using comman PHP variables 

Function inputs:
    $base if set to true will add the basename to the URL
    $www if set to true will add www. to host if not found
    $query if set to true will add the query string to the URL
    $echo if set to true will echo the URL instead of just returning it
*/
function self_url_complex($base = true, $www = true, $query = true, $echo = false){
    $URL = ''; //open return variable
    $URL .= (($_SERVER['HTTPS'] != '') ? "https://" : "http://"); //get protocol
    $URL .= (($www == true && !preg_match("/^www\./", $_SERVER['HTTP_HOST'])) ? 'www.'.$_SERVER['HTTP_HOST'] : $_SERVER['HTTP_HOST']); //get host
    $path = (($_SERVER['REQUEST_URI'] != '') ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF']); //tell the function what path variable to use
    $URL .= ((pathinfo($path, PATHINFO_DIRNAME) != '/') ? pathinfo($path, PATHINFO_DIRNAME).'/' : pathinfo($path, PATHINFO_DIRNAME)); //set up directory
    $URL .= (($base == true) ? pathinfo($path, PATHINFO_BASENAME) : ""); //add basename
    $URL  = preg_replace("/\?".preg_quote($_SERVER['QUERY_STRING'])."/", "", $URL); //remove query string if found in url
    $URL .= (($query == true && $_SERVER['QUERY_STRING'] != '') ? "?".$_SERVER['QUERY_STRING'] : ""); //add query string
    if($echo == true){
        echo $URL;
    }else{
        return $URL;
    }
}

function self_url() {
    return $_SERVER['SCRIPT_URI'];
}

/** 
 * source: http://us2.php.net/manual/en/function.parse-url.php#60237 
 * Edit the Query portion of an url 
 * 
 * @param    string    $action    ethier a "+" or a "-" depending on what action you want to perform 
 * @param    mixed    $var    array (+) or string (-) 
 * @param    string    $uri    the URL to use. if this is left out, it uses $_SERVER['PHP_SELF'] 
 * @version      1.0.0 
 */ 
function change_query($action, $var = NULL, $uri = NULL) { 

           if (($action == "+" && !is_array($var)) || ($action == "-" && $var == "") || $var == NULL) { 
                   return FALSE; 
           } 

           if (is_null($uri)) { //Piece together uri string 
                   $beginning = $_SERVER['PHP_SELF']; 
                   $ending = (isset ($_SERVER['QUERY_STRING'])) ? $_SERVER['QUERY_STRING'] : ''; 
           } else { 
                   $qstart = strpos($uri, '?'); 
                   if ($qstart === false) { 
                           $beginning = $uri; //$ending is '' anyway 
                           $ending = ""; 
                   } else { 
                           $beginning = substr($uri, 0, $qstart); 
                           $ending = substr($uri, $qstart); 
                   } 
           } 

           $vals = array (); 
           $ending = str_replace('?', '', $ending); 
           parse_str($ending, $vals); 

           switch ($action) { 
                   case '+' : 
                           $vals[$var[0]] = $var[1]; 
                           break; 
                   case '-' : 
                           if (isset ($vals[$var])) { 
                                   unset ($vals[$var]); 
                           } 
                           break; 
                   default : 
                           break; 
           } 

           $params = array(); 
           foreach ($vals as $k => $value) { 
                   $params[] = $k."=".urlencode($value); 
           } 
           $result = $beginning . (count($params) ? '?' . implode("&", $params) : ''); 
           return $result; 
   }

   function os_path() {
        $args = func_get_args();
        $out = join(DIRECTORY_SEPARATOR, $args);
        $out = str_replace('//', '/', $out);
        return $out;
   }

?>