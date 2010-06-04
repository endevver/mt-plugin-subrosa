<?php

global $mt;
$ctx = &$mt->context();

# Check to see we're disabled...
if (isset($mt->config['PluginSwitch']['SubRosa/SubRosa.pl'])) {
    if (!$mt->config['PluginSwitch']['SubRosa/SubRosa.pl']) {
        define('SUBROSA_ENABLED', 0);
        return;
    }
}

define('SUBROSA_ENABLED', 1);

$ctx->add_tag('MTSubRosaUserName', 'smarty_function_MTSubRosaUserName');
$ctx->add_tag(
    'MTSubRosaUserDisplayName', 'smarty_function_MTSubRosaUserDisplayName');
$ctx->add_tag('MTSubRosaUserID', 'smarty_function_MTSubRosaUserID');

$ctx->add_tag('MTSubRosaUserEmail', 'smarty_function_MTSubRosaUserEmail');
$ctx->add_tag('MTSubRosaUserURL', 'smarty_function_MTSubRosaUserURL');
$ctx->add_tag(
    'MTSubRosaUserProfileURL','smarty_function_MTSubRosaUserProfileURL');
$ctx->add_conditional_tag(
    'MTSubRosaIfAuthorized','smarty_block_MTSubRosaIfAuthorized');
$ctx->add_container_tag('MTSubRosaUpdates', 'smarty_block_MTSubRosaUpdates');
$ctx->add_conditional_tag(
    'MTSubRosaIfUpdateIsComment', 'smarty_block_MTSubRosaIfUpdateIsComment');
$ctx->add_conditional_tag(
    'MTSubRosaIfUpdateIsEntry', 'smarty_block_MTSubRosaIfUpdateIsEntry');
$ctx->add_tag(
    'MTSubRosaUpdatePermalink', 'smarty_function_MTSubRosaUpdatePermalink');
$ctx->add_tag(
    'MTSubRosaUpdateDate', 'smarty_function_MTSubRosaUpdateDate');
$ctx->add_tag(
    'MTSubRosaUpdateEntryTitle', 'smarty_function_MTSubRosaUpdateEntryTitle');
$ctx->add_tag(
    'MTSubRosaUpdateAuthor', 'smarty_function_MTSubRosaUpdateAuthor');
$ctx->add_tag(
    'MTSubRosaUpdateBlogName', 'smarty_function_MTSubRosaUpdateBlogName');
$ctx->add_tag(
    'MTSubRosaUpdateBlogURL', 'smarty_function_MTSubRosaUpdateBlogURL');



// $ctx->add_container_tag('MyBlogs', 'smarty_block_MTMyBlogs');

function subrosa_user_lookup($ctx, $var) {

    if (isset($ctx->mt->auth)) {
        $user =& $ctx->mt->auth->user();
    }
    else {
        return $ctx->error(
            'MTAuth object not set in subrosa_user_lookup');
    }
    if (isset($user)) return $user->get($var);
}

function user_is_authorized($ctx, $blog_id=null) {

    $blog_id or $blog_id = $ctx->stash('blog_id');
    $auth_stash = $ctx->stash('subrosa_authorized');
    if ($blog_id == $ctx->mt->controller_blog_id) {
        $authorized = false;
    }
    elseif (    empty($auth_stash)
            or  ! array_key_exists($blog_id, $auth_stash)) {
        if (isset($ctx->mt->auth)) {
            $authorized = $auth_stash[$blog_id]
                = $ctx->mt->auth->has_perms($blog_id);
        }
        else {
            return $ctx->error(
                'MTAuth object not set in init.SubRosa.php user_is_authorized()');
        }
    }
    return $authorized;
}

function smarty_function_MTSubRosaUserName($args, &$ctx)
{
    return subrosa_user_lookup($ctx, 'name');
}

function smarty_function_MTSubRosaUserDisplayName($args, &$ctx)
{
    $name = subrosa_user_lookup($ctx, 'nickname');
    $name or $name = subrosa_user_lookup($ctx, 'name');
    return $name;
}

function smarty_function_MTSubRosaUserID($args, &$ctx)
{
    return subrosa_user_lookup($ctx, 'id');
}

function smarty_function_MTSubRosaUserEmail($args, &$ctx)
{
    return subrosa_user_lookup($ctx, 'email');
}

function smarty_function_MTSubRosaUserURL($args, &$ctx)
{
    return subrosa_user_lookup($ctx, 'url');
}

function smarty_function_MTSubRosaUserProfileURL($args, &$ctx)
{
    if (isset($ctx->mt->auth)) {
        $user =& $ctx->mt->auth->user();
    }
    else {
        return $ctx->error(
            'MTAuth object not set in MTSubRosaUserProfileURL');
    }
    if (isset($user)) {
        $user_id = $user->get('id');
        $cgi_path = $ctx->mt->config['CGIPath'];
        if (substr($cgi_path, strlen($cgi_path) - 1, 1) != '/')
            $cgi_path .= '/';
        return implode('',  array(  $cgi_path,
                                    $ctx->mt->config['AdminScript'],
                                    '?__mode=view&_type=author&id=',
                                    $user_id));
    }
}

function smarty_block_MTSubRosaIfAuthorized($args, $content, &$ctx, &$repeat) {
    if (!isset($content)) {
        return $ctx->_hdlr_if($args, $content, $ctx, $repeat,
             user_is_authorized($ctx));
    }
    else {
        return $ctx->_hdlr_if($args, $content, $ctx, $repeat);
    }        
}

function smarty_block_MTSubRosaUpdates($args, $content, &$ctx, &$repeat) {
    $localvars = array('updates', 'update', '_updates_counter', 'entry', 'comment','current_timestamp','_updates_lastn', 'current_timestamp_end', 'DateHeader', 'DateFooter', '_updates_glue', 'blog', 'blog_id');
    if (!isset($content)) {
        $counter = 0;
        $args['lastn'] or $args['days'] or $args['lastn'] = 50;
        $lastn = $args['days'] ? -1 : $args['lastn'];
        $ctx->stash('_updates_lastn', $lastn);
    }
    else {
        $lastn = $ctx->stash('_updates_lastn');
        $counter = $ctx->stash('_updates_counter');        
        $ctx->__stash['update'] = null;
        $ctx->__stash['comment'] = null;
        $ctx->__stash['entry'] = null;
    }
    $updates = $ctx->stash('updates');
    if (!isset($updates)) {
        unset($args['include_blogs']);
        unset($args['exclude_blogs']);
        unset($args['blog_id']);
        unset($args['blog_ids']);
        
        foreach ($ctx->mt->db->fetch_blogs(array()) as $blog) {
            $ctx->mt->log('BLOG LOADED: '.$blog['blog_id']);
            if ($ctx->mt->exclude_blogs
                and in_array($blog['blog_id'], $ctx->mt->exclude_blogs)) {
                continue;
            }
            if (! user_is_authorized($ctx, $blog['blog_id'])) continue;
            $blogs[$blog['blog_id']] = $blog;
        }

        $args['include_blogs'] = join(',',array_keys($blogs));

        $entries =& $ctx->mt->db->fetch_entries($args);
        foreach ($entries as $entry) {
            $entry['update_created_on'] = $entry['entry_created_on'];
            $entry['update_type'] = 'entry';
            $entry['update_blog_id'] = $entry['entry_blog_id'];
            $updates[] = $entry;
        }

        $sql = $ctx->mt->db->include_exclude_blogs($args);
        if ($sql != '') {
            $blog_filter = 'and comment_blog_id ' . $sql;
            if (isset($args['blog_id']))
                $blog =& $ctx->mt->db->fetch_blog($args['blog_id']);
        } elseif ($args['blog_id']) {
            $blog =& $ctx->mt->db->fetch_blog($args['blog_id']);
            $blog_filter = ' and comment_blog_id = ' . $blog['blog_id'];
        }
        # load comments

        if (isset($args['days'])) {
            $day_filter = 'and ' . $ctx->mt->db->limit_by_day_sql('comment_created_on', intval($args['days']));
	}
        $order = 'desc';
        $sql = "
            select *
              from mt_comment
                join mt_entry on entry_id = comment_entry_id and entry_status = 2
             where 1 = 1
                   $blog_filter
               and comment_visible = 1
               $day_filter
             order by comment_created_on $order
                   <LIMIT>";

        if (isset($args['lastn'])) {
	    $sql = $ctx->mt->db->apply_limit_sql($sql, $args['lastn'], $args['offset']);
        } else {
	  $sql = preg_replace('/<LIMIT>/', '', $sql);
	}
	//	print "<!-- SQL USED: $sql -->";
        $comments =& $ctx->mt->db->get_results($sql, ARRAY_A);

	//        $comments =& $ctx->mt->db->fetch_comments($args);
        foreach ($comments as $comment) {
            $comment['update_created_on'] = $comment['comment_created_on'];
            $comment['update_created_on'] = preg_replace('/[-:\s]+/', '', $comment['update_created_on']);
            $comment['update_type'] = 'comment';
            $comment['update_blog_id'] = $comment['comment_blog_id'];
            $updates[] = $comment;
        }

        foreach ($updates as $key => $row) {
            $created_on[$key]  = $row['update_created_on'];
        }

        array_multisort($created_on, SORT_DESC, $updates);
        $ctx->stash('updates', $updates);        
        // printf("<p>Blogs: %s</p>", count($blogs));
        // printf("<p>Entries: %s</p>", count($entries));
        // printf("<p>Comments: %s</p>", count($comments));
        // printf("<p>Updates: %s</p>", count($updates));
    }

    $ctx->stash('_updates_glue', $args['glue']);
    if (($lastn > count($updates)) || ($lastn == -1)) {
        $lastn = count($updates);
        $ctx->stash('_updates_lastn', $lastn);
    }
    if ($lastn ? ($counter < $lastn) : ($counter < count($updates))) {
        $update = $updates[$counter];

        $blog_id = $update['update_blog_id'];
        $ctx->stash('blog_id', $blog_id);
        $ctx->stash('blog', $ctx->mt->db->fetch_blog($blog_id));

        $ctx->stash($update['update_type'], $update);
        
        if ($counter > 0) {
            $last_update_created_on = $updates[$counter-1]['update_created_on'];
        } else {
            $last_update_created_on = '';
        }
        if ($counter < count($updates)-1) {
            $next_update_created_on = $updates[$counter+1]['update_created_on'];
        } else {
            $next_update_created_on = '';
        }
        $ctx->stash('DateHeader', !(substr($update['update_created_on'], 0, 8) == substr($last_update_created_on, 0, 8)));
        $ctx->stash('DateFooter', (substr($update['update_created_on'], 0, 8) != substr($next_update_created_on, 0, 8)));
        $ctx->stash('update', $update);
        $ctx->stash('current_timestamp', $update['update_created_on']);
        $ctx->stash('current_timestamp_end', null);
        // $ctx->stash('modification_timestamp', $update['entry_modified_on']);
        $ctx->stash('_updates_counter', $counter + 1);
        $glue = $ctx->stash('_updates_glue');
        if ($glue != '') $content = $content . $glue;
        $repeat = true;
    } else {
        $ctx->restore($localvars);
        $repeat = false;
    }
    return $content;
}

function smarty_block_MTSubRosaIfUpdateIsComment($args, $content, &$ctx, &$repeat) {
    if (!isset($content)) {
        return $ctx->_hdlr_if($args, $content, $ctx, $repeat, 
            $ctx->stash('comment') ? 1 : 0);
    }
    else {
        return $ctx->_hdlr_if($args, $content, $ctx, $repeat);
    }
}

function smarty_block_MTSubRosaIfUpdateIsEntry($args, $content, &$ctx, &$repeat) {
    if (!isset($content)) {
        return $ctx->_hdlr_if($args, $content, $ctx, $repeat, 
            $ctx->stash('entry') ? 1 : 0);
    }
    else {
        return $ctx->_hdlr_if($args, $content, $ctx, $repeat);
    }
}

function smarty_function_MTSubRosaUpdateBlogName($args, &$ctx) {
    $blog = $ctx->stash('blog');
    return $blog['blog_name'];
}

function smarty_function_MTSubRosaUpdateBlogURL($args, &$ctx) {
    $blog = $ctx->stash('blog');
    return $blog['blog_site_url'];
}

function smarty_function_MTSubRosaUpdatePermalink($args, &$ctx) {
    $anchor = null;
    $entry = $ctx->stash('entry');
    if (empty($entry)) {
        $comment = $ctx->stash('comment');
        $entry = $ctx->mt->db->fetch_entry($comment['comment_entry_id']);
        $anchor = '#comment-'.$comment['comment_id'];
    }
    $blog = $ctx->stash('blog');
    $at = $args['archive_type'];
    $at or $at = $blog['blog_archive_type_preferred'];
    if (!$at) {
        $at = $blog['blog_archive_type'];
        # strip off any extra archive types...
        $at = preg_replace('/,.*/', '', $at);
    }
    $link = $ctx->mt->db->entry_link($entry['entry_id'], $at, $args);
    return $link.$anchor;
}
function smarty_function_MTSubRosaUpdateAuthor($args, &$ctx) {
    $update = $ctx->stash('update');
    if ($update['update_type'] == 'entry') {
        $author = $ctx->mt->db->fetch_author($update['entry_author_id']);
        return $author['author_nickname'];
    } elseif ($update['update_type'] == 'comment') {
        return $update['comment_author'];
    }
}
function smarty_function_MTSubRosaUpdateEntryTitle($args, &$ctx) {
    $update = $ctx->stash('update');
    if ($update['update_type'] == 'entry') {
        return $update['entry_title'];
    } elseif ($update['update_type'] == 'comment') {
        return $update['entry_title'];
    }
}
function smarty_function_MTSubRosaUpdateDate($args, &$ctx) {
    $update = $ctx->stash('update');
    $args['ts'] = $update[$update['update_type'].'_created_on'];
    return $ctx->_hdlr_date($args, $ctx);
}
?>