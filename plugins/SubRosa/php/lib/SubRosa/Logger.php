<?php

// === TextMate error handling ===

/**
* SubRosa_Logger - A logging/debugging class for SubRosa

*
*    $this->logger = new SubRosa_Logger('screen');
*    $this->screen = $logger->screen
*

*/
class SubRosa_Logger
{
    public $log = array();
    public $driver = '';

    function __construct($output='')
    {
        // Log messages to file
        if ( strpos($output, '/') !== false ) {
            $this->driver = new SubRosa_Logger_File($output);
        }
        // Log messages to screen
        elseif ($output == 'screen') {
            $this->driver = new SubRosa_Logger_Screen;
        }
        // Log messages to stderr
        else {
            $this->driver = new SubRosa_Logger_Syslog;
        }
    }

    function current_log() { return $this->driver->log; }

    function log_dump() {
        global $mt;
        if ( ! $mt->debugging ) return;
        if ( count( $this->driver->log )) {
            $this->driver->log_dump();
            unset($this->driver->log);
        }
    }

    function log( $msg = null ) {
        if (is_array($msg) || is_object($msg)) {
            $msg = print_r($msg, true);
        }
        $this->driver->log[] = $msg;
        global $mt;
        if (isset($mt)) {
            if ($mt->log_delay === false) {
                $this->log_dump();
            }
        }
    }

    function debug($msg)  { $this->log($msg); }

    function screen($msg) { $this->log($msg); }

    function console($msg) { $this->log($msg); }

    function marker($msg = 'EOM') {
        $bt = debug_backtrace();

        // get class, function called by caller of caller of caller
        $class = $bt[2]['class'];
        $function = $bt[2]['function'];

        // get file, line where call to caller of caller was made
        $file = $bt[1]['file'];
        $line = $bt[1]['line'];

        // build & return the message
        $this->log("[[$class::$function, $line]]: $msg");
    }

    function fullmarker($msg) {
        $bt = debug_backtrace();

        // get class, function called by caller of caller of caller
        $class = $bt[2]['class'];
        $function = $bt[2]['function'];

        // get file, line where call to caller of caller was made
        $file = $bt[1]['file'];
        $line = $bt[1]['line'];

        // build & return the message
        $this->log("$class::$function: $msg in $file at $line");
    }

    //     $log = $this->console_log;
    //     if (file_exists($log) ) {
    //         error_log("$msg\n", 3, $log);
    //     }
    //     else {
    //         $this->debug("Can't write to console log at $log!");
    //     }
    //
    //     if (is_array($msg) || is_object($msg)) {
    //         $msg = print_r($msg, true);
    //     }
    //     error_log("$msg\n", 0);
    // }

    function notify($msg = null) {
        global $mt;
        if (    is_null($mt->notify_user)
            ||  is_null($mt->notify_pass)) {
            return;
        }
        require_once('extlib/twitter.php');
        $twitter = new Twitter($mt->notify_user, $mt->notify_pass);
        $twitter->send_update($msg);
    }


}

/**
* SubRosa_Logger_Syslog
*/
class SubRosa_Logger_Syslog extends SubRosa_Logger
{
    function __construct() {
    }

    function log_dump()
    {
        $separator = str_repeat("\n",5)
                   . str_repeat((str_repeat("=", 80)."\n"),10);
        array_unshift($this->log, $separator);
        $stderr = fopen('php://stderr', 'w');
        if ($stderr) {
            fwrite($stderr,implode("\n", $this->log)."\n");
            fclose($stderr);
        }
    }
}
/**
* SubRosa_Logger_Screen
*/
class SubRosa_Logger_Screen extends SubRosa_Logger
{

    function __construct($opts='') {
    }

    function log_dump($opts='') {
        if ( $_SERVER['REMOTE_ADDR'] and empty($opts['noscreen']) ) {
            // web view...
            echo "<div class=\"debug\" style=\"border:1px solid red; margin:0.5em; padding: 0 1em; text-align:left; background-color:#ddd; color:#000\"><pre>";
            echo implode("\n", $this->log);
            echo "</pre></div>\n\n";
        } else {
            // Fall back console view...
            // parent::log_dump($this->log);
            $stderr = new SubRosa_Logger_Syslog;
            array_unshift($this->log, "Writing to syslog instead of screen as directed");
            $stderr->log =& $this->log;
            $stderr->log_dump();
        }
    }
}

/**
* SubRosa_Logger_File
*/
class SubRosa_Logger_File extends SubRosa_Logger
{

    var $handle;
    var $file;

    function __construct($file) {
        // Log messages to file
        if (  file_exists($file) ) {
            $this->file = $file;
        }
    }

    function log_dump() {
        if (count($this->log) == 0) return;
        if ($this->file) {
            $handle = fopen($this->file, "a");
            if ($handle) {
                global $mt;
                if ( $mt->log_delay ) {
                    $separator = str_repeat("\n",5)
                               . str_repeat((str_repeat("=", 80)."\n"),10);
                    array_unshift($this->log, $separator);
                }
                fwrite($handle, implode("\n", $this->log)."\n");
                fclose($handle);
                return;
            }
        }

        $screen = new SubRosa_Logger_Screen;
        array_unshift($this->log, "Can't write to log at $log!");
        $screen->log = $this->log;
        $screen->log_dump();
    }
}

?>