<?php
/**
* SubRosa_Viewer - Our MTViewer stuff...
*/
class SubRosa_Viewer {

    $is_virtual_request = 0;

    // Requests for directories and non-existent files are virtual and must be
    // run through SubRosa.
    global $is_virtual_request;
    $req_path           = $_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_URL'];
    $is_virtual_request = (   ! is_file( $req_path )
                        or ! file_exists( $req_path ) ) ? 1 : 0;


    if ( ! $mt->policy->is_protected() ) {

        if ( ! $mt->policy->resolve_dynamic || ! $is_virtual_request ) {
            error_log($_SERVER['SCRIPT_URL'].': Serving request');
            apache_setenv( 'SUBROSA_OK', 1 );
            virtual( $_SERVER['SCRIPT_URL'] );
        }
    }



    // abstract public function is_authorized ( );
    // abstract public function is_protected  ( );
    // abstract public function login_page    ( $params            );
    // abstract public function handle_login  ( $fileinfo          );
    // abstract public function handle_auth   ( $fileinfo          );
    // abstract public function handle_logout ( $fileinfo          );
    // abstract public function login_page    ( $params            );
    // abstract public function error_handler ( $errno, $errstr,   
    //                                             $errfile, $errline );


    if ( $_SERVER['SUBROSA_PASSTHRU'] == 1 ) {

        error_log($_SERVER['SCRIPT_URL'].': Inspect and passthrough');
        $mt = new SubRosa($cfg['mt_dir']."/mt-config.cgi", $_GET['blog_id']);

    }
    elseif (    isset($_REQUEST['redirect'])
        and is_authorized(isset($_REQUEST['redirect']))) {

        error_log($_SERVER['SCRIPT_URL'].
            ': Authorized redirect for '.$_REQUEST['redirect']);
        $mt = new SubRosa($cfg['mt_dir']."/mt-config.cgi", $_GET['blog_id']);

        $mt->redirect($_REQUEST['redirect']);
        $mt->view();
        exit;

    }
    elseif ( ! $virtual_request ) {
        if ( unprotected_request() or is_authorized( $_SERVER['SCRIPT_URL'] ) ) {
            error_log($_SERVER['SCRIPT_URL'].': Serving request');
            apache_setenv( 'SUBROSA_OK', 1 );
            virtual( $_SERVER['SCRIPT_URL'] );
            // serve_request();
            // exit;
        }

    }
    elseif (   $virtual_request
            or isset($_POST['login'])
            or isset($_COOKIE['mt_user']) ) {
        error_log($_SERVER['SCRIPT_URL'].
            ': Have session information available in gateway script. Loading '.$cfg['mt_dir']."/$subrosa_path");
        $mt = new SubRosa($cfg['mt_dir']."/mt-config.cgi", $_GET['blog_id']);

        if (isset($_POST['login']) and isset($_REQUEST['redirect'])) {
            $mt->redirect($_REQUEST['redirect']);        
        }

        $mt->view();
        exit;

    }
    else {
        error_log($_SERVER['SCRIPT_URL'].
            ': No session information available for gateway script');
        if (is_page_request()) {
            error_log('Kicking to login');
            require('login.php');
        }
        exit;
    }



    function unprotected_request() {
        $url = $_SERVER['SCRIPT_URL'];
        $begin_with = array('/favicon.ico',  '/css',     '/images',
                            '/mte-static',  '/scripts',  '/yui');
        $end_with = array('.css', '.js');
        foreach ($begin_with as $key) {
            if (strpos($url, $key, 0)) return true;
        }
        foreach ($end_with as $key) {
            if (strstr($url, $key) == $key) return true;
        }
    }

    function get_user_cookie() {
        if (array_key_exists('mt_user', $_COOKIE)) {
            $usercookie = $_COOKIE['mt_user'];
            $parts = explode('::', $usercookie);
            return $parts;
        }
        return array(null, null, null);
    }

    function is_authorized($url) {
        // print "<p>Here in is_authorized</p>";
        list($cuser, $csid, $cpersist) = get_user_cookie();
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

    function is_page_request() {
        $req = $_SERVER['SCRIPT_URL'];
        if (substr($req, -1) == '/') return 1;
        $path_parts = pathinfo($req);
        print_r($path_parts);
        $ext = $path_parts['extension'];
        $basename = $path_parts['basename'];
        if (empty($basename)) return 1;
        if (in_array($ext, array('html', 'php'))) return 1;
    }


    // serve_request();
    // info();
    // fileinfo();

    function serve_request() {
        global $cfg;
        $name = $cfg['site_path'].$_SERVER['SCRIPT_URL'];

        $path_parts = pathinfo($name);
        $ext = $path_parts['extension'];
        $mimetype = mimetype($ext);
        $fp = fopen($name, "r");
        // send the right headers
        error_log("Sending $name with mime type $mimetype");
        if ( ! empty($mimetype) ) header("Content-Type: ".$mimetype);
        header("Content-Length: " . filesize($name));

        // dump the picture and stop the script
        fpassthru($fp);
        exit;

    }

    function info() {
        print phpinfo();
        exit;    
    }

    function fileinfo() {
        $request = $_SERVER['SCRIPT_URL'];
        global $cfg;
        $finfo = new finfo(FILEINFO_MIME, '/usr/share/file/magic'); // return mime type ala mimetype extension

        if (!$finfo) {
            echo "Opening fileinfo database failed";
            exit();
        }

        /* get mime-type for a specific file */
        $filename = $cfg['site_path'] . $request;
        echo $finfo->file($filename);

        /* close connection */
        $finfo->close();
        exit;
    }

    function reqinfo() {
        print h1('SESSION');
        print_table($_SESSION);    
        print h1('COOKIES');
        print_table($_COOKIE);
        print h1('GET');
        print_table($_GET);
        print h1('POST');
        print_table($_POST);
        print h1('REQUEST');
        print_table($_REQUEST);
        print h1('SERVER');
        print_table($_SERVER);    
        exit;
    }

    // function trac_cookie_test() {
    //     
    //     if ($_GET['traccookie']) {
    //         $key = 'trac_auth';
    //         $project = 'private';
    //         $usercookie = str_replace(' ','+',$_COOKIE['mt_user']);
    //         $val_nomd5 = "$project::$usercookie";
    //         $val = md5('private::'.$_COOKIE['mt_user']);
    //         $expire = 315360000;
    //         $path = '/private';
    //         $domain = '.extranet.tdi.local';
    // 
    //         setcookie($key, $val, (time()+$expire), $path, $domain); 
    //         print "<p>Just set cookie val $val</p>";
    //         print "<p>No MD5: $val_nomd5</p>";
    //         error_log("No MD5: $val_nomd5");
    //         print "<p>MD5: $val</p>";
    //         error_log("MD5: $val");
    //         exit;
    //     }
    //     
    // }
    function print_table($array = null, $return_output = false) {
        if (empty($array)) return;
        foreach ($array as $key => $val) {
            $out .= tr(td($key).td($val));
        }
        $out = table($out, array(border => 1, style => 'width:100%'));
        if ($return_output) {
            return $out;
        } else {
            print $out;
        }
    }

    function table() { $args=func_get_args(); return tag_wrap(__FUNCTION__, $args); }
    function tr() { $args=func_get_args(); return tag_wrap(__FUNCTION__, $args); }
    function th() { $args=func_get_args(); return tag_wrap(__FUNCTION__, $args); }
    function p() { $args=func_get_args(); return tag_wrap(__FUNCTION__, $args); }
    function td() { $args=func_get_args(); return tag_wrap(__FUNCTION__, $args); }
    function h1() { $args=func_get_args(); return tag_wrap(__FUNCTION__, $args); }
    function h2() { $args=func_get_args(); return tag_wrap(__FUNCTION__, $args); }
    function h3() { $args=func_get_args(); return tag_wrap(__FUNCTION__, $args); }
    function h4() { $args=func_get_args(); return tag_wrap(__FUNCTION__, $args); }
    function h5() { $args=func_get_args(); return tag_wrap(__FUNCTION__, $args); }
    function h6() { $args=func_get_args(); return tag_wrap(__FUNCTION__, $args); }

    function tag_wrap($tag, $arr = null) { 
        $var = $arr[0];
        $args = $arr[1];
        $argstring = '';
        if (isset($args)) {
            foreach ($args as $key => $val) {
            $argstring .= " $key=\"$val\"";
            }
        }
        return "<$tag$argstring>$var</$tag>"; 
    }

    function mimetype($ext) {
        global $mimetype_array;
        if (empty($mimetype_array)) { 
            $mimetype_array = init_mimetype();
        }
        return $mimetype_array[$ext];
    }
    function init_mimetype() {

        return array('ez' => 'application/andrew-inset',
        'atom' => 'application/atom+xml',
        'hqx' => 'application/mac-binhex40',
        'cpt' => 'application/mac-compactpro',
        'mathml' => 'application/mathml+xml',
        'doc' => 'application/msword',
        'bin' => 'application/octet-stream',
        'dms' => 'application/octet-stream',
        'lha' => 'application/octet-stream',
        'lzh' => 'application/octet-stream',
        'exe' => 'application/octet-stream',
        'class' => 'application/octet-stream',
        'so' => 'application/octet-stream',
        'dll' => 'application/octet-stream',
        'dmg' => 'application/octet-stream',
        'oda' => 'application/oda',
        'ogg' => 'application/ogg',
        'pdf' => 'application/pdf',
        'ai' => 'application/postscript',
        'eps' => 'application/postscript',
        'ps' => 'application/postscript',
        'rdf' => 'application/rdf+xml',
        'smi' => 'application/smil',
        'smil' => 'application/smil',
        'gram' => 'application/srgs',
        'grxml' => 'application/srgs+xml',
        'mif' => 'application/vnd.mif',
        'xul' => 'application/vnd.mozilla.xul+xml',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',
        'wbxml' => 'application/vnd.wap.wbxml',
        'wmlc' => 'application/vnd.wap.wmlc',
        'wmlsc' => 'application/vnd.wap.wmlscriptc',
        'vxml' => 'application/voicexml+xml',
        'bcpio' => 'application/x-bcpio',
        'vcd' => 'application/x-cdlink',
        'pgn' => 'application/x-chess-pgn',
        'cpio' => 'application/x-cpio',
        'csh' => 'application/x-csh',
        'dcr' => 'application/x-director',
        'dir' => 'application/x-director',
        'dxr' => 'application/x-director',
        'dvi' => 'application/x-dvi',
        'spl' => 'application/x-futuresplash',
        'gtar' => 'application/x-gtar',
        'hdf' => 'application/x-hdf',
    //    'js' => 'application/x-javascript',
        'js' => 'text/javascript',
        'skp' => 'application/x-koan',
        'skd' => 'application/x-koan',
        'skt' => 'application/x-koan',
        'skm' => 'application/x-koan',
        'latex' => 'application/x-latex',
        'nc' => 'application/x-netcdf',
        'cdf' => 'application/x-netcdf',
        'sh' => 'application/x-sh',
        'shar' => 'application/x-shar',
        'swf' => 'application/x-shockwave-flash',
        'sit' => 'application/x-stuffit',
        'sv4cpio' => 'application/x-sv4cpio',
        'sv4crc' => 'application/x-sv4crc',
        'tar' => 'application/x-tar',
        'tcl' => 'application/x-tcl',
        'tex' => 'application/x-tex',
        'texinfo' => 'application/x-texinfo',
        'texi' => 'application/x-texinfo',
        't' => 'application/x-troff',
        'tr' => 'application/x-troff',
        'roff' => 'application/x-troff',
        'man' => 'application/x-troff-man',
        'me' => 'application/x-troff-me',
        'ms' => 'application/x-troff-ms',
        'ustar' => 'application/x-ustar',
        'src' => 'application/x-wais-source',
        'xhtml' => 'application/xhtml+xml',
        'xht' => 'application/xhtml+xml',
        'xslt' => 'application/xslt+xml',
        'xml' => 'application/xml',
        'xsl' => 'application/xml',
        'dtd' => 'application/xml-dtd',
        'zip' => 'application/zip',
        'au' => 'audio/basic',
        'snd' => 'audio/basic',
        'mid' => 'audio/midi',
        'midi' => 'audio/midi',
        'kar' => 'audio/midi',
        'mpga' => 'audio/mpeg',
        'mp2' => 'audio/mpeg',
        'mp3' => 'audio/mpeg',
        'aif' => 'audio/x-aiff',
        'aiff' => 'audio/x-aiff',
        'aifc' => 'audio/x-aiff',
        'm3u' => 'audio/x-mpegurl',
        'ram' => 'audio/x-pn-realaudio',
        'ra' => 'audio/x-pn-realaudio',
        'rm' => 'application/vnd.rn-realmedia',
        'wav' => 'audio/x-wav',
        'pdb' => 'chemical/x-pdb',
        'xyz' => 'chemical/x-xyz',
        'bmp' => 'image/bmp',
        'cgm' => 'image/cgm',
        'gif' => 'image/gif',
        'ief' => 'image/ief',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'jpe' => 'image/jpeg',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'djvu' => 'image/vnd.djvu',
        'djv' => 'image/vnd.djvu',
        'wbmp' => 'image/vnd.wap.wbmp',
        'ras' => 'image/x-cmu-raster',
        'ico' => 'image/x-icon',
        'pnm' => 'image/x-portable-anymap',
        'pbm' => 'image/x-portable-bitmap',
        'pgm' => 'image/x-portable-graymap',
        'ppm' => 'image/x-portable-pixmap',
        'rgb' => 'image/x-rgb',
        'xbm' => 'image/x-xbitmap',
        'xpm' => 'image/x-xpixmap',
        'xwd' => 'image/x-xwindowdump',
        'igs' => 'model/iges',
        'iges' => 'model/iges',
        'msh' => 'model/mesh',
        'mesh' => 'model/mesh',
        'silo' => 'model/mesh',
        'wrl' => 'model/vrml',
        'vrml' => 'model/vrml',
        'ics' => 'text/calendar',
        'ifb' => 'text/calendar',
        'css' => 'text/css',
        'html' => 'text/html',
        'htm' => 'text/html',
        'asc' => 'text/plain',
        'txt' => 'text/plain',
        'rtx' => 'text/richtext',
        'rtf' => 'text/rtf',
        'sgml' => 'text/sgml',
        'sgm' => 'text/sgml',
        'tsv' => 'text/tab-separated-values',
        'wml' => 'text/vnd.wap.wml',
        'wmls' => 'text/vnd.wap.wmlscript',
        'etx' => 'text/x-setext',
        'mpeg' => 'video/mpeg',
        'mpg' => 'video/mpeg',
        'mpe' => 'video/mpeg',
        'qt' => 'video/quicktime',
        'mov' => 'video/quicktime',
        'mxu' => 'video/vnd.mpegurl',
        'm4u' => 'video/vnd.mpegurl',
        'avi' => 'video/x-msvideo',
        'movie' => 'video/x-sgi-movie',
        'ice' => 'x-conference/x-cooltalk');

    }

    function kill_php_current_session() {
        session_name('SubRosa');
        session_start();
        unset($_SESSION['current_user']);
        print 'Your PHP session has been killt...';
        exit;
    }
    function show_current_request_info() {
        reqinfo();
        exit;    
    }



}


?>