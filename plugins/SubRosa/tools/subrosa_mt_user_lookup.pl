#!/usr/bin/perl -w
use strict;

$| = 1;
use constant DEBUG => 0;

our %__cache;

use lib 'lib';
use MT;
our $mt = MT->new( Config => './mt-config.cgi');

if (scalar @ARGV < 2) {
    require File::Basename;
    printf "USAGE: %s USERNAME URL [SESSION_ID]\n",
        File::Basename::basename($0);
    exit;
}

($ARGV[0] eq '--showsessions') && show_sessions();


if (DEBUG) {
    require Data::Dumper;
    print Data::Dumper::Dumper(\@ARGV);
}

use constant AUTHORIZED       => 0;
use constant UNAUTH_NOSESSION => 1;
use constant UNAUTH_NOPERMS   => 2;
use constant UNAUTH_NOUSER    => 3;

my %message = ( (AUTHORIZED)          => 'AUTHORIZED',
                (UNAUTH_NOPERMS)      => 'NOT AUTHORIZED',
                (UNAUTH_NOSESSION)    => 'NO ACTIVE SESSION',
                (UNAUTH_NOUSER)       => 'USER NOT FOUND');
            
my $rc = user_is_authorized(@ARGV);
print $message{$rc}."\n";
exit($rc);

sub user_is_authorized {
    my ($username, $url, $session_id) = @_;
    my $user = user($username);
    return  (!$user or ref($user) ne 'MT::Author')  ? UNAUTH_NOUSER
          : ! is_active_session($session_id, $user) ? UNAUTH_NOSESSION
          : ! user_has_access($user, $url)          ? UNAUTH_NOPERMS
          :                                           AUTHORIZED;
}


sub is_active_session {
    my ($sid, $user) = @_;
    return 1 unless $sid;
    DEBUG and debug(sprintf('Looking for session %s for user ID %s',
        $sid, $user->id));
    require MT::Session;
    my $session = MT::Session->load({ id => $sid,
                                    kind => 'US' });
    if ($session) {
        my $data = $session->thaw_data();
        if (DEBUG)  {require Data::Dumper; debug(Data::Dumper::Dumper($data))};
        if (($data->{author_id}||0) == $user->id) {
            DEBUG and debug('Found matching session');
            return 1;
        } else {
            DEBUG and debug('Author ID does not match for session ('.$data->{author_id}.')');
        }
    } else {
        DEBUG and debug('Session not found');
    }
}

sub show_sessions {
    require MT::Session;
    my $iter = MT::Session->load_iter({ kind => 'US' });
    my @records;
    while (my $session = $iter->()) {
        my $id = $session->id;
        my $author_id = $session->thaw_data()->{author_id};
        if ($author_id) {
            my $user = MT::Author->load($author_id);
            push(@records, sprintf "%-15s  %s", $user->name, $id);
        }
    }
    @records = sort @records;
    print join("\n", @records)."\n";
    exit;
}

sub user_has_access {
    my ($user, $url) = @_;

    my $blog;

    # Super users go anywhere.  They are that cool...
    return 1 if $user->is_superuser;
    DEBUG and debug('Not a superuser..');
    
    # No blog?  No problem!
    return 1 unless $blog = get_blog_for_url($url);

    DEBUG and debug('Checking for authorization on blog');

    # The final test: Checking for permissions on the blog
    return is_authorized($user, $blog->id);

}

sub is_authorized {
    my ($user, $blog_id) = @_;

    DEBUG and debugf('Checking perms for user %s on blog ID %s', 
        $user->name, $blog_id);

    require MT::Permission;
    my $perms = MT::Permission->load({ blog_id => $blog_id,
                                       author_id => $user->id });
    #require Data::Dumper;
    #debug(Data::Dumper::Dumper($perms));
    return $perms;
}

sub get_blog_for_url {
    my $url = shift;
    $url = simplify($url);       # Removes protocol and trailing slash
    my $blog_map = blog_map();
    my $blog;
    foreach my $blog_url (keys %$blog_map) {
        # blog_url should be a substring of url, otherwise try again
        next if index($url, $blog_url, 0) == -1;
        # Found it!
        $blog = $blog_map->{$blog_url};
        DEBUG and debug(sprintf "In blog context! %s (%s)", 
                        $blog->name, $blog->site_url);
        last;
    }
    return $blog;
    
}

sub user {
    my $username = shift;
    return $__cache{user}{$username} if exists $__cache{user}{$username};
    DEBUG and debug("Retrieving user: $username");
    require MT::Author;
    $__cache{user}{$username} = 
        MT::Author->load({ name => $username }) || {};
}

sub blog_map {
    return $__cache{blog} if exists $__cache{blog};
    require MT::Blog;
    my @blogs = MT::Blog->load();
    foreach (@blogs) {
        my $url = simplify($_->site_url);
        # We skip any blog that has the root of the site as its domain
        # because that's the portal blog which publishes the aggregated
        # content and shared assets.  This should not be considered in 
        # blog context.
        next if $url !~ m!/! and scalar @blogs > 1;
        DEBUG and debug(sprintf "Retrieved: %s (%s)", $_->name, $_->id);
        $__cache{blog}{$url} = $_;
    }
    $__cache{blog};
}

sub simplify {
    my $root = shift;
    $root =~ s!^https?://!!;    # Strip protocol
    $root =~ s!/$!!;            # Strip trailing slash
    $root;
}

sub print_mt_env {
    debug("ENV: $_") foreach grep { /^MT_/ } keys %ENV;
}

sub debug {
    my $var = shift;
    print STDERR ">>> $var\n";
}

sub debugf { 
    my $str = shift;
    debug(sprintf $str, @_)  
}



