<?php
require_once 'SubRosa/MT/Object/Author.php';
require_once 'SubRosa/MT/Object/Session.php';

/**
* MTAuth - handles authentication for dynamic MT
*/
class SubRosa_MT_Auth
{
    var $username;
    var $password;

    function __construct($username=null, $pass=null) {
        global $mt;
        $this->mt =& $mt;
        $this->mt->marker();
        
        if (isset($username) and isset($pass)) {
            $this->username = $username;
            $this->password = $pass;
        }
        return $this;
    }

    function init() {
        $this->mt->marker();

        // Get PHP session information
        $phpname = phpsession('name');
        $phpsid = phpsession('session_id');

        // Get MT cookie information
        list($cname, $csid, $cpersist) = $this->mt->user_cookie();
        if ($cname) $this->log("Found cookie for $cname with session $csid");

        if (empty($phpname) and empty($cname)) {
            $this->log('No auth information available');
            $this->no_auth_info = 1;
            return;
        }
        
        // PHP session information verification
        if (    ($cname == $phpname)
            and ($csid == $phpsid)) {
            $this->log('PHP session data matches cookie');
            $user = MTAuthor::load(array('name' => $cname));
            if (is_object($user)) $this->user($user);
            $session = MTSession::load($csid);
            if (is_object($session)) $this->session($session);
        }
        else {
            
            // Otherwise, discard the PHP session information
            phpsession(false);

            // If we have a user cookie, load data from there.
            if (isset($cname) and isset($csid)) {
                $this->log('Initializing PHP session from cookie');
                $session = MTSession::load($csid);
                if (is_object($session)) {
                    $this->log('We loaded the session object');
                    $this->session($session);
                    $user = $session->user();
                    if (is_object($user)) {
                        $this->log('We loaded the user object');
                        $this->user($user);
                    } else {
                        $this->mt->log_dump();
                        die ('No user object returned from MTSession::user');
                    }
                }
            }    
        }
    }

    function &user($data=null) {
        static $user;

        if ($data === false) { unset($user); return; }

        if (isset($user)) return $user;

        if (is_null($data) and $this->no_auth_info) return;
        
        $this->mt->marker('Initializing MTAuth user');

        if (isset($data) and is_object($data)) {
            $user = $data;
        }
        elseif (isset($data)) {
            $user = new MTAuthor($data);
        }

        if (isset($user) and is_object($user)) {
            $hash = $user->property_hash();
            foreach ($hash as $key => $val) {
                phpsession($key, $val);
            }
            return $user;
        }
    }

    function &session($data=null) {
        static $session;

        if ($data === false) { unset($session); return; }

        if (isset($session)) return $session;

        if (is_null($data) and $this->no_auth_info) return;

        $this->mt->marker('Initializing MTAuth session');

        if (isset($data) and is_object($data)) {
            $session = $data;
        }
        elseif (isset($data)) {
            $session = new MTSession($data);
        }

        if (isset($session) and is_object($session)) {
            phpsession('session_id', $session->get('id'));
            return $session;
        }
    }

    function login() {
        $this->mt->marker();

        $username = $this->username;
        $password = $this->password;
        // $this->log("Logging in with $username/$password");

        if (empty($username) or empty($password)) return;
        
        if ($user = $this->authenticate($username, $password)) {

            $this->user($user);

            $session = $user->create_session();
            if ($id = $session->get('id')) {

                $this->session($session);

                $msg = sprintf(
                    "User '%s' (ID:%s) logged in successfully", 
                    $user->get('name'), $user->get('id'));
                $this->mt->mtlog(array( message => $msg, 
                                        author_id => $user->get('id')));

                $msg = str_replace(" successfully", '', $msg);
                $this->mt->notify(sprintf("$msg (IP: %s)",
                    $_SERVER['REMOTE_ADDR']));

                $this->mt->run_callbacks('SubRosaSessionCreated', 
                                         array( user        => $user,
                                                session_id  => $session_id ));
                return $user;
            }
        }
    }

    function logout() {
        $this->mt->marker();

        $session =& $this->session();

        if ($user = $session->user()) {
            $name = $user->get('name');
            $id   = $user->get('id');
            $msg  = sprintf(
                "User '%s' (ID:%s) logged out", $name, $id);
            $this->mt->mtlog(array( message => $msg, 
                                    author_id => $id));

            $this->mt->notify(sprintf("$msg (IP: %s)",
                $_SERVER['REMOTE_ADDR']));
        }
        $session->kill();
        $this->session(false);
        
        phpsession(false);
    }

    function authenticate($name, $pass, $crypted=0) {

        $this->mt->marker("Loading information for user: $name");
        
        // If we do not have both, bail now...
        if (empty($name) || empty($pass)) return false;

        // Load user by username, return if none found
        $user = MTAuthor::load(array('name' => $name));
        if (! is_object($user)) {
            require_once('SubRosa/MT/Object/Log.php');
            $msg = sprintf("Failed login attempt by unknown user '%s'.", $name);
            $this->mt->mtlog(
                array('message' => $msg, 'level' => MT_Log::level('WARNING')));
            $this->log("$msg No user object returned from MTAuthor::load");
            $this->mt->log_dump();
            return;            
        }
        
        $this->log('Userdata:');
        $this->log($user->property_hash());

        // Encrypt the user-supplied password if it needs it
        $dbpass = $user->get('password');
        $enc_pass = $pass;
        if (!$crypted) {
            // They now say to pass the whole encrypted string
            // in for the salt instead of just the first two
            // for compatibility reasons.  <shrug>
            // $this->log("Encrypting password $pass");
            $enc_pass = crypt($pass, $dbpass);
            // $this->log("Encrypted password: $enc_pass");
        }

        // Compare the encoded passwords. If no match, then false.
        // Otherwise, return the user array.
        if ($enc_pass == $dbpass) {
            $this->log('Passwords match');
            return $user;
        } else {
            require_once('SubRosa/MT/Object/Log.php');
            $msg = sprintf("Invalid login attempt from user '%s'.", $name);
            $this->mt->mtlog(
                array('message' => $msg, 'level' => MT_Log::level('WARNING')));
            $this->log("$msg Password mismatch");
            // $this->mt->log_dump();
            // return;
        }
    }

    function has_perms($blog_id=null) {
        $blog_id or $blog_id = $this->mt->blog_id;
        # Checks if user has perms on blog, returns results
        $user =& $this->user();
        return $user->has_perms($blog_id);
    }

    function has_active_session() {
        $this->mt->marker();
        $session = new MTSession();
        return $session->session_user();
    }

    function log($msg) { $this->mt->log($msg); }

}

?>