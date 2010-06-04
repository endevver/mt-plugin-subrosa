<?php
function create_trac_session(&$user, &$session) {
/* NO LONGER NEEDED */
return;
    global $mt;

    $mt->marker(sprintf('Creating trac session for %s with session ID %s', $user->get('name'), $session->get('id')));

    if (    ! $mt->blog_id
        or  ($mt->blog_id == $mt->controller_blog_id)) {
        return;
    }
        
    $blog = $mt->blog();
    $username = $user->get('name');

    // Calculate blog_key from the site URL
    $host = $blog['blog_site_url'];
    if (!preg_match('!/$!', $host))
        $host .= '/';

    if (preg_match('!^https?://[^/]+/(.*)$!', $host, $matches)) {
        $blog_key = $matches[1];            
        if (substr($blog_key, -1) == '/') {
            $blog_key = substr($blog_key, 0, -1);
        }
    }
    $path = "/$blog_key";

    $val = "$blog_key::$username::".$session->get('id');
    // $val = str_replace(' ','+', $val);
    $cookieval = md5($val);
    $mt->log("Creating cookieval from $val");
    $session->send_cookie('trac_auth', $cookieval, $path);
}


?>
