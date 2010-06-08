<?php
require_once 'SubRosa/MT/Object.php';
/**
* MTAuthor - Author object for dynamic MT
*/
class SubRosa_MT_Object_Author extends SubRosa_MT_Object
{
    // var $name = '';
    // var $password = '';
    // var $perms = '';

    var $class_prefix = 'author';
    var $properties = array(
        'id','api_password','can_create_blog','can_view_log','email',
        'hint','is_superuser','name','nickname','password',
        'preferred_language','public_key','remote_auth_token',
        'remote_auth_username','type','url','created_on','created_by',
        'modified_on','modified_by','entry_prefs','status','external_id',
        'session_id');

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
        if ($results = $mt->db->load('author', $terms)) {
            foreach ($results as $data) {
                $object = new MTAuthor($data);
                $objects[] = $object;
            }
            return (count($objects) == 1) ? $objects[0] : $objects;
        }
    }

    function session() {
        $session_id = $this->get('session_id');
        if (!$session_id) return;
        $session = MTSession::load($session_id);
        return $session;
    }
    
    function create_session() {
        $id = $this->get('id');
        $name = $this->get('name');
        $session = new MTSession(array('userid' => $id, 'username' =>$name));
        $session->create();
        SubRosa_Util::phpsession('session_id', $session->get('id'));
        return $session;
    }

    function load_by_name($name) {
        $terms = array( name => $name, type => 1);
        if ($user = $this->load($terms)) {
            // $user = parent::init($userdata);
            return $user;
        }
    }

    function load_by_id($id) {
        $terms = array( id => $id, type => 1);
        if (list($user) = $this->load($terms)) {
            // $user = parent::init($userdata);
            return $user;
        }
    }

    function perms($perms=null) {
        $this->mt->marker();
        if (isset($perms)) {
            $this->perms = $perms;
        } elseif (isset($this->perms)) {
            return $this->perms;
        } else {
            $this->perms = $this->load_perms();
            return $this->perms;
        }
    }

    function has_perms($blog_id) {

        $this->log(sprintf(
            'Checking perms for user ID %s on blog ID %s', 
                $this->get('id'), $blog_id
        ));
        global $subrosa_config;
        if (isset($subrosa_config)) {
            $authorized_users = $subrosa_config['blog_id'][$blog_id];
        $this->log("authorized users for blog ID $blog_id");
	$this->log($authorized_users)        ;
            if (in_array($this->get('id'), $authorized_users)) {

                if (isset($this->mt->auth)) {
                    $user =& $this->mt->auth->user();
                    $session =& $this->mt->auth->session();
                } else {
                    return $ctx->error('MTAuth object not set in has_perms()');
                }

                if (isset($user) and isset($session)) {
                    $session_id = $session->get('id');
                    $this->mt->run_callbacks('SubRosaPermCheck', 
                                     array( user        => $user,
                                            session_id  => $session_id ));
/*
    TODO Trac should use the SubRosaPermCheck callback for session creation
*/
/* NO LONGER NEEDED
                    create_trac_session($user, $session);
*/
                    return true;
                }
            }
        }
    }

    function load_perms() {
        $this->mt->marker();
        /*
        sub permissions {
            my $author = shift;
            my ($obj) = @_;

            my $terms = { author_id => $author->id };
            my $cache_key = "__perm_author_" . $author->id;
            if ($obj) {
                my $blog_id;
                if ((ref $obj) && $obj->isa('MT::Blog')) {
                    $blog_id = $obj->id;
                } elsif ($obj) {
                    $blog_id = $obj;
                    require MT::Blog;
                    $obj = MT::Blog->load($blog_id, { cached_ok => 1 });
                }
                $cache_key .= "_blog_$blog_id";
                $terms->{blog_id} = [ 0, $blog_id ];
            } else {
                $terms->{blog_id} = 0;
            }

            require MT::Request;
            my $r = MT::Request->instance;
            my $p = $r->stash($cache_key);
            return $p if $p;

            require MT::Permission;
            my @perm = MT::Permission->load($terms);
            my $perm;
            if ($obj) {
                if (@perm == 2) {
                    if (!$perm[0]->blog_id) {
                        @perm = reverse @perm;
                    }
                    ($perm, my $sys_perm) = @perm;
                    $perm->add_permission($sys_perm);
                } elsif (@perm == 1) {
                    $perm = $perm[0];
                    if (!$perm->blog_id) {
                        $perm->blog_id($obj->id);
                        $perm->id(0);
                    }
                } elsif (@perm) {
                    die "invalid permissions for author " . $author->id;
                }
            } else {
                $perm = $perm[0] if @perm;
            }
            unless (@perm) {
                # Use superclass is_superuser method here to avoid
                # recursive calls.
                if ($author->SUPER::is_superuser) {
                    $perm = new MT::Permission;
                    $perm->author_id($author->id);
                    $perm->set_full_permissions;
                }
            }
            unless ($perm) {
                $perm = new MT::Permission;
                $perm->author_id($author->id);
                $perm->clear_full_permissions;
            }
            $r->stash($cache_key, $perm);
            $perm;
        }


        */        
    }
}

?>