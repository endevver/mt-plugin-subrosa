<?php

/**
* SubRosa_Logger_Twitter - A logging class for SubRosa

*
*    $this->logger = new SubRosa_Logger('screen');
*    $this->screen = $logger->screen
*

*/
class SubRosa_Logger_Twitter
{
    var $user = '';
    var $pass = '';
    var $update_api = 'http://twitter.com/statuses/update.xml';
    
    function __construct($user='', $pass='')
    {
        $this->user = $user;
        $this->pass = $pass;
    }

    function send_update($msg = null) {
        global $mt;
        if (    is_null($mt->notify_user)
            ||  is_null($mt->notify_pass)) {
            return;
            $mt->logger->log('Could not log to Twitter. Missing credentials');
        }
        $msg = $this->shorten_urls($msg);
        $mt->logger->log("NOTIFY MESSAGE: $msg");
        $msg = substr($msg, 0, 159); // 160 is the limit

        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $this->update_api);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_handle, CURLOPT_POST, 1);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, "status=$msg");
        curl_setopt($curl_handle, CURLOPT_USERPWD, 
            implode(':', array($this->user, $this->pass)));

        $buffer = curl_exec($curl_handle);
        curl_close($curl_handle);
        $mt->logger->log("NOTIFY RESPONSE: $buffer");
        //if (empty($buffer)){echo '<br/>message';}else{echo '<br/>success';}
    }

    function shorten_urls($msg = null) {

        if (is_null($msg)) return;

        $urlpattern = '/((http|https|ftp):\/\/|www)'
           .'[a-z0-9\-\._]+\/?[a-z0-9_\.\-\?\+\/~=&#;,]*'
           .'[a-z0-9\/]{1}/si';

        return preg_replace_callback(
            $urlpattern,
            'short_url',
            $msg
        );
    }

}

/* This is placed outside the class on purpose because
   $this->short_url was not a valid callback.
   Probably should use create_function to call short URL
   properly. */
function short_url($matches=null) {
    $url = $matches[0];
    if (is_null($url)) return;
    $curl_handle = curl_init();
    curl_setopt($curl_handle, CURLOPT_URL, 'http://metamark.net/api/rest/simple');
    curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl_handle, CURLOPT_POST, 1);
    curl_setopt($curl_handle, CURLOPT_POSTFIELDS, "long_url=$$url");
    // print "<p>Calling metamark</p>";
    $buffer = curl_exec($curl_handle);
    curl_close($curl_handle);
    return $buffer;
}

?>