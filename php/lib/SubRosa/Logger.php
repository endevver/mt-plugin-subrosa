<?php

// === TextMate error handling ===

/**
* Logger - A logging/debugging class for SubRosa

*
*    $this->logger = new Logger('screen');
*    $this->screen = $logger->screen
*

*/
class Logger
{
    var $log = array();
    var $driver = '';

    function __construct($output='')
    {
        // Log messages to file
        if (strpos($output, '/') !== false) {
            $this->driver = new FileLogger($output);
        }
        // Log messages to screen
        elseif ($output == 'screen') {
            $this->driver = new ScreenLogger;
        }
        // Log messages to stderr
        else {
            $this->driver = new SysLogger;
        }
    }

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

    function log($msg = null) {
        if (is_array($msg) || is_object($msg)) {
            $msg = print_r($msg, true);
        }
        $this->driver->log[] = $msg;
        global $mt;
        if ($mt->log_delay === false) {
            $this->log_dump();
        }
    }

    function current_log() { return $this->driver->log; }
    function log_dump() {
        global $mt;
        if ($mt->debugging !== true) return;
        if (count($this->driver->log) == 0)  return;
        $this->driver->log_dump();
        unset($this->driver->log);
    }

    function debug($msg) {  $this->log($msg); }
    function screen($msg) { $this->log($msg); }
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


    function console($msg) { $this->log($msg); }

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

}

/**
* SysLogger
*/
class SysLogger extends Logger
{
    function SysLogger() { 
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
* ScreenLogger
*/
class ScreenLogger extends Logger
{

    function ScreenLogger($opts='') { 
    }

    function log_dump($opts='') {
        if ($_SERVER['REMOTE_ADDR'] and empty($opts['noscreen'])) {
            // web view...
            echo "<div class=\"debug\" style=\"border:1px solid red; margin:0.5em; padding: 0 1em; text-align:left; background-color:#ddd; color:#000\"><pre>";
            echo implode("\n", $this->log);
            echo "</pre></div>\n\n";
        } else {
            // Fall back console view...
            // parent::log_dump($this->log);
            $stderr = new SysLogger;
            array_unshift($this->log, "Writing to syslog instead of screen as directed");
            $stderr->log =& $this->log;
            $stderr->log_dump();
        }
    }
}

/**
* FileLogger
*/
class FileLogger extends Logger
{

    var $handle = NULL;
    var $file;
    
    function FileLogger($file) {
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
                if ($mt->log_delay === true) {
                    $separator = str_repeat("\n",5)
                               . str_repeat((str_repeat("=", 80)."\n"),10);
                    array_unshift($this->log, $separator);
                }
                fwrite($handle, implode("\n", $this->log)."\n"); 
                fclose($handle);
                return;
            }
        }

        $screen = new ScreenLogger;
        array_unshift($this->log, "Can't write to log at $log!");
        $screen->log = $this->log;
        $screen->log_dump();
    }
}



?>