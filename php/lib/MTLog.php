<?php
require_once 'MTObject.php';
/**
* MTLog - Activity log object for the dynamic publishing engine
*/
class MTLog extends MTObject
{

    var $class_prefix = 'log';
    var $properties   = array('id', 'message', 'ip', 'blog_id', 'author_id',
                                'level', 'class', 'category', 'metadata',
                                'created_on', 'modified_on');
    var $INFO         = 1;
    var $WARNING      = 2;
    var $ERROR        = 4;
    var $SECURITY     = 8;
    var $DEBUG        = 16;
    function level($level) { return $this->$level; }
    function init($data=null) {
        $this->mt->marker();
        $user = parent::init($data);
        // DEFAULTS
        $this->set_defaults(
            array(
                'blog_id'   => 0,
                'author_id' => 0,
                'class'     => 'system',
                'level'     => $this->INFO,
            )
        );
        // if (my $blog = $app->blog) {
        //     $log->set('blog_id', $blog->id);
        // }
        $log;
    }

    function save() {
        $this->mt->marker();

        $mt =& $this->mt;
        $mtdb =& $mt->db;
        $table = 'mt_'.$this->class_prefix;

        //
        // Set real-data defaults for this object
        //
        // created_on and modified_on dates
        $ts = gmdate('YmdHis');
        foreach (array('created_on','modified_on') as $key) {
            if (! $this->get($key)) {
                $this->set($key, $ts);
            }
        }
        // Active user for log message
        if (! $this->get('author_id')) {
            if (isset($mt->user) and is_object($mt->user)) {
                $this->set('author_id', $mt->user->id);
            }
        }
        // IP address (if available) of remote user
        if ($_SERVER['REMOTE_ADDR']) {
            $this->set('ip', $_SERVER['REMOTE_ADDR']);
        }

        //
        // Fill in and escape values from object
        //
        foreach ($this->property_hash() as $key => $val) {
            if (isset($val)) {
                $keys[] = join('', array($this->class_prefix, '_', $key));
                $vals[] = $mt->db->escape($val);
            }
        }

        // $this->mt->log(print_r($keys), true);
        // $this->mt->log(print_r($vals), true);
        $key_str = join(",", $keys);
        $val_str = "'".join("','", $vals)."'";

        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)',
                        $table, $key_str, $val_str);

        $this->mt->log('SQL: '.$sql);
        // Easily run the query and check the result. Thank you MT/EzSQL!
        $mtdb->query($sql);
        if ($mtdb->rows_affected < 1) {
            $ctx =& $mt->context();
            return $ctx->error(
                'Could not save '.$this->class_prefix.' record: '
                .mysql_error($mtdb));
        }
    }
}