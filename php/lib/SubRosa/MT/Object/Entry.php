<?php
require_once 'MT/Object.php';
/**
* MTEntry - Entry object for dynamic MT
*/
class MT_Object_Entry extends MT_Object
{
    var $class_prefix = 'entry';
    var $properties = array(
        'id', 'blog_id', 'status', 'author_id', 'allow_comments', 'title',
        'excerpt', 'text', 'text_more', 'convert_breaks', 'to_ping_urls',
        'pinged_urls', 'allow_pings', 'keywords', 'tangent_cache', 'basename',
        'atom_id', 'authored_on', 'week_number', 'template_id',
        'comment_count', 'ping_count', 'junk_log'
    );

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
        if ($results = $mt->db->load('entry', $terms)) {
            foreach ($results as $data) {
                $object = new MTEntry($data);
                $objects[] = $object;
            }
            return (count($objects) == 1) ? $objects[0] : $objects;
        }
    }

    function load_by_id($id) {
        $terms = array( id => $id, type => 1);
        if (list($obj) = $this->load($terms)) {
            // $user = parent::init($userdata);
            return $obj;
        }
    }

?>