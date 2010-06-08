<?php
require_once 'MT/Object.php';
/**
* MTSession - Session handling for the dynamic publishing engine
*/
class MT_Object_Session extends MT_Object
{

    var $class_prefix    = 'session';
    var $properties      = array('id', 'userid', 'username');
    var $kind            = 'US';
    var $cookie_path     = '/';
    var $session_timeout = 315360000;

    function load() {
        if ($fnargs = func_get_args()) {
            if (is_array($fnargs[0])) {
                $terms = $fnargs[0];
            }
            elseif (is_string($fnargs[0])) {
                $terms = array( id => $fnargs[0]);
            }
        }
        global $mt;
        if ($results = $mt->db->load('session', $terms)) {

            foreach ($results as $data) {
                $sessdata = unpack_session_data($data['session_data']);
                $object = new MTSession(array(
                    id => $data['session_id'],
                    userid => $sessdata['author_id']));
                $objects[] = $object;
            }
            return (count($objects) == 1) ? $objects[0] : $objects;
        }
    }

    function user() {
        $this->mt->marker();
        $userid = $this->get('userid');
        if (!$userid) return;
        $this->log("Loading user $userid from MTAuthor");
        $user = MTAuthor::load($userid);
        return $user;
    }
    
    function create($user=null) {
        $this->mt->marker();

        if (empty($user)) {
            if (isset($this->mt->auth)) {
                $user =& $this->mt->auth->user();
            }
            else {
                return $ctx->error(
                    'MTAuth object not set in MTSession::create()');
            }
        }

        if (empty($user)) {
            $this->mt->log_dump();
            die('Can\'t create session for a null user.');
            $ctx =& $this->mt->context();
            return $ctx->error('Can\'t create session for a null user.');
        }
        // $this->user($user);
        $this->save();

        return $this->get('id');
    }

    function save() {
        $this->mt->marker();

        if (isset($this->mt->auth)) {
            $user =& $this->mt->auth->user();
        }
        else {
            return $ctx->error(
                'MTAuth object not set in MTSession::save()');
        }

        if (empty($user)) return;
        
        $mt =& $this->mt;
        $mtdb =& $mt->db;
        
        // Initialize serializer if it's needed
        if (!$mtdb->serializer) {
            require_once($mt->config['PHPLibDir'].'/MTSerialize.php');
            $mtdb_serializer = new MTSerialize();
            $mtdb->serializer =& $mtdb_serializer;
        }

        // Compile session row data, serializing the session_data field
        require_once('lib/Utils.inc');
        $session_id = magic_token();
        $time = time();
        $data = $mtdb->serializer->serialize(
                array('author_id' => $user->get('id')));

        // Create the SQL for the session row insert
        $sql = 'INSERT INTO mt_session '.
               '(session_data, session_id, session_kind, session_start) '.
               "VALUES ('".
                join("','", array($data, $session_id, $this->kind, $time)).
                "')";
        // $this->log($sql);

        // Easily run the query and check the result. Thank you MT/EzSQL!
        $mtdb->query($sql);
        if ($mtdb->rows_affected < 1) {
            $ctx =& $mt->context();
            return $ctx->error(
                'Session could not be created for Movable Type.'
                .'SQL error: '.mysql_error($mtdb));
        }

        // Create (and send) the user cookie
        $this->create_cookie($user->get('name'), $session_id);
        
        $this->set('id', $session_id);
        phpsession('session_id', $session_id);

        return $session_id;
    }

    function kill($session_id=null)
    {
        $this->mt->marker();

        $username = $this->get('username');
        $id = $this->get('id');
        
        $this->kill_cookie();

        $this->mt->run_callbacks('SubRosaSessionEnded', 
                                 array( user        => $username,
                                        session_id  => $id) );
        $this->set('username', null);
        $this->set('id', null);
        $this->set('userid', null);
        phpsession('session_id', false);
    }


    function create_cookie($username, $sid) {

        $this->mt->marker();
        $this->send_cookie($this->mt->user_cookie, "$username::$sid::1");
    }

    function kill_cookie() {
        $this->mt->marker();
        $this->send_cookie($this->mt->user_cookie);
    }

    function send_cookie($key, $val=null, $path=null) {

        $this->mt->marker();
        $this->mt->set_cookie_defaults();
        
        isset($path) or $path = $this->mt->cookie_path;
        isset($path) or $path = $this->cookie_path;

        $timeout = $this->mt->session_timeout;
        if (is_null($timeout)) $timeout = $this->session_timeout;

        $domain  = $this->mt->cookie_domain;
        if (is_null($domain)) $domain = $_SERVER['HTTP_HOST'];
        $domain = preg_replace('/^\.?/', '.', $domain);

        $time   = time();
        $expire = empty($val) ? $time-$timeout : $time+$timeout;

        $this->log(array($key, $val, $expire, $path, $domain)); 
        setcookie($key, $val, $expire, $path, $domain); 
    }
}


?>