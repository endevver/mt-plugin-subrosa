<?php
require_once 'SubRosa/MT/Object/Author.php';
require_once 'SubRosa/MT/Object/Session.php';

/**
* SubRosa_MT_Auth - handles authentication for dynamic MT
*/
class SubRosa_MT_Auth
{
    var $username;
    var $password;

    function __construct($username=null, $pass=null) {
        global $mt;
        $this->mt =& $mt;
        $this->mt->marker('Initializing SubRosa_Auth');
        
        if (isset($username) and isset($pass)) {
            $this->username = $username;
            $this->password = $pass;
        }
        return $this;
    }

    function init() {
        // Get auth info from PHP session
        list( $phpname, $phpsid ) = $this->php_session_auth();

        // Get session info from commenter cookie
        $cmtr_session = $this->cmtr_session();
        if (isset($cmtr_session)) $csid = $cmtr_session->get('id');

        // Compare commenter cookie and PHP session info.
        // If no PHP session exists or the session IDs match,
        // load user and return $this auth
        if ( isset($csid) && ( ! isset($phpsid) || ($csid == $phpsid) )) {
            $this->mt->marker('PHP session data matches mt_commenter cookie');
            $user = $cmtr_session->user();
            if ( is_object($user) ) {
                $this->session( $cmtr_session );
                $this->user( $user );
                $this->mt->marker("PHP session/Authentication OK. Commenter: "
                                  .$user->get('name'));
                return $this;
            }
        }
        // Commenter cookie session retrieval failed

        // Check MT cookie information instead
        list( $ucname, $ucsid, $ucpersist ) = SubRosa_Util::get_user_cookie();
        if ($ucname)
            $this->mt->marker("Found mt_user cookie for $ucname with session $ucsid");

        // Compare user cookie and PHP session info.
        // If no PHP session exists or the session IDs match,
        // load session/user and return $this auth
        if ( isset($ucsid) && ( ! isset($phpsid) || ($ucsid == $phpsid) ) ) {
            $this->log('PHP session data matches mt_user cookie');
            $user = SubRosa_MT_Object_Author::load(array('name' => $ucname));
            if (is_object($user)) {
                $session = SubRosa_MT_Object_Session::load($ucsid);
                if (is_object($session)) {
                    $this->user($user);
                    $this->session($session);
                    $this->mt->marker("PHP session/Authentication OK. User: "
                                      .$user->get('name'));
                    return $this;
                }
            }
        }


        // If, at this point, we have a PHP session, it's a stale session.
        
        // Fall back to commenter cookie session info if available
        if ( isset($csid) ) {
            $user = $cmtr_session->user();
            if ( is_object($user) ) {
                $this->session( $cmtr_session );
                $this->user( $user );
                $this->mt->marker("Authentication OK. Commenter: "
                                  .$user->get('name'));
                return $this;
            }
        }
        // Fall back to user cookie session info if available
        elseif ( isset($ucsid) ) {
            $user = SubRosa_MT_Object_Author::load(array('name' => $ucname));
            $session = SubRosa_MT_Object_Session::load($ucsid);
            if (is_object($user) && is_object($session)) {
                $this->user($user);
                $this->session($session);
                $this->mt->marker("Authentication OK. User: "
                                  .$user->get('name'));
                return $this;
            }
        }

        // Give up -- We have no active sessions or auth data
        $this->mt->marker('No auth information available');
        $this->no_auth_info = 1;
    }

    function php_session_auth() {
        // Get PHP session information
        $phpname = SubRosa_Util::phpsession('name');
        $phpsid  = SubRosa_Util::phpsession('session_id');
        $this->mt->marker("PHP Session info: name: $phpname, sid: $phpsid");
        return (array($phpname, $phpsid));
    }

    function cmtr_session() {
        // Get commenter session information
        list( $cmtr_cookie_sid ) = SubRosa_Util::get_cmtr_cookie();

        if ( isset($cmtr_cookie_sid)) {
            $this->mt->marker('Found mt_commenter cookie: '.$cmtr_cookie_sid);
            $cmtr_session = SubRosa_MT_Object_Session::load($cmtr_cookie_sid);
	    $this->mt->log(print_r($cmtr_session,true));
            return $cmtr_session;
        }
    }

    function &user($data=null) {
        static $user;

        if ($data === false) { unset($user); return; }

        if (isset($user)) return $user;

        if (is_null($data) and $this->no_auth_info) return;
        
        $this->mt->marker('Initializing SubRosa_MT_Auth user');

        if (isset($data) and is_object($data)) {
            $user = $data;
        }
        elseif (isset($data)) {
            $user = new SubRosa_MT_Object_Author($data);
        }

        if (isset($user) and is_object($user)) {
            $user_hash =  $user->property_hash();
            $meta      =  $this->mt->db->get_meta( 'author', 
                                                   $user->get( 'id' ));

            $this->log('$user_hash: '.print_r( $user_hash, true ));
            $this->log('$meta: '.print_r( $meta, true ));

            # Merge user and user meta data and put into SESSION
            # The merge method below ensures that all keys will be present
            # even if their values are null.
            $keys = array_merge(    array_keys( $user_hash ),
                                    array_keys( $meta )          );
            foreach ( $keys as $key ) {
                if ( isset( $user_hash[$key] )) {
                    $val = $user_hash[$key];
                }
                elseif ( isset( $meta[$key] )) {
                    $val = $meta[$key];
                }
                else {
                    $val = '';
                }
                SubRosa_Util::phpsession( $key, $val );
            }
            return $user;
        }
    }

    function &session($data=null) {
        static $session;

        if ($data === false) { unset($session); return; }

        if (isset($session)) return $session;

        if (is_null($data) and $this->no_auth_info) return;

        $this->mt->marker('Initializing SubRosa_MT_Auth session');

        if (isset($data) and is_object($data)) {
            $session = $data;
        }
        elseif (isset($data)) {
            $session = new SubRosa_MT_Object_Session($data);
        }

        if (isset($session) and is_object($session)) {
	  $this->mt->log(print_r($session, true));
	  $this->mt->log('About to set the PHP session_id from '.$session->get('id'));
            SubRosa_Util::phpsession('session_id', $session->get('id'));
            $this->mt->log(print_r($_SESSION, true));
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
        
        SubRosa_Util::phpsession(false);
    }

    function authenticate($name, $pass, $crypted=0) {

        $this->mt->marker("Loading information for user: $name");
        
        // If we do not have both, bail now...
        if (empty($name) || empty($pass)) return false;

        // Load user by username, return if none found
        $user = SubRosa_MT_Object_Author::load(array('name' => $name));
        if (! is_object($user)) {
            require_once('SubRosa/MT/Object/Log.php');
            $msg = sprintf("Failed login attempt by unknown user '%s'.", $name);
            $this->mt->mtlog(
                array('message' => $msg, 'level' => MT_Log::level('WARNING')));
            $this->log("$msg No user object returned from SubRosa_MT_Object_Author::load");
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
        $session = new SubRosa_MT_Object_Session();
        return $session->session_user();
    }

    function log($msg) { $this->mt->log($msg); }

}

?>