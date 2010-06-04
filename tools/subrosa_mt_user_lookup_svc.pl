#!/usr/bin/perl -w
use strict;
#
#
# http://httpd.apache.org/docs/1.3/mod/mod_rewrite.html#RewriteLock
# http://www.php.net/features.http-auth
# http://tdi.local/auth.php
# http://tdi.local/subrosa/
#
#

$| = 1;
use constant DEBUG => 1;
use constant COOKIE_NAME => 'mt_user';
use constant LOGIN_URL => 'http://example.com/';

use lib 'lib';
use MT;
use MT::Author;
use MT::Blog;
use MT::Permission;

our $mt = MT->new( Config => './mt-config.cgi');
our %__cache = ();
our $TEST_USER;

svc_user_is_authorized();

sub svc_user_is_authorized {

    $ENV{MT_REWRITEMAP} = 1;

    while (my $input = <STDIN>) {
        my $url = '';
        chomp($input);
        my ($active_user, $has_access, $needs_http_auth) = 0;

        DEBUG and $input = parse_input($input);
        next unless $input;
        
        # Check for active session and, if present
        # authorization for resource
        if ($active_user = session_cookie()) {
            if ($has_access = user_has_access($active_user, $input)) {
                $url = $input;
            } else {
                $url = $input.'?not_authorized=1';
            }
        } else {
            $ENV{MT_NEEDS_LOGIN} = 1;
            $url = LOGIN_URL.'?login=1';
            
            # if (needs_http_auth($url)) {
            #     $ENV{MT_NEEDS_HTTP_AUTH} = 1;
            #     $url = 'http-auth-please';
            # }
        }
            
        DEBUG and debug("Returning $url");
        print "$url\n";
    }
}

sub parse_input {
    DEBUG or return @_;
    my $input = shift;
    if (index($input, '|') >= 0) {
        ($TEST_USER, $input) = split('\|', $input);
        debug("TEST_USER: $TEST_USER, URL: $input");
    } elsif ($input eq 'cache') {
        require Data::Dumper;
        debug(Data::Dumper::Dumper(\%__cache));
        $input = undef;
    }
    return $input;
}

sub session_cookie {
    DEBUG and $TEST_USER and return $TEST_USER;
    require Data::Dumper;
    debug(Data::Dumper::Dumper(\%ENV));
    my $raw_cookie = $ENV{HTTP_COOKIE} || $ENV{COOKIE};
    return unless $raw_cookie;
    my ($username,$session,$remember) = split('::', $raw_cookie);
    return $username;

    my $class = $ENV{MOD_PERL} ? 'Apache::Cookie' : 'CGI::Cookie';
    eval "use $class;";
    DEBUG and debug('Fetching cookies');
    my $cookies = $class->fetch;
    DEBUG and $@ and debug($@);
    require Data::Dumper;
    debug(Data::Dumper::Dumper($cookies));
    return $cookies->{COOKIE_NAME};
}

sub user_has_access {
    my ($username, $url) = @_;
    my $user = user($username);
    return unless ref($user) eq 'MT::Author';
    DEBUG and debug("Testing for access for ".$user->name);

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
    $__cache{user}{$username} = 
        MT::Author->load({ name => $username }) || {};
}

sub blog_map {
    return $__cache{blog} if exists $__cache{blog};
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



__END__

sub test_data {

return qw(    
jay|http://client.texturadesign.com/hellokitty
|http://client.texturadesign.com/hellokitty/
|http://client.texturadesign.com/hellokitty/blog/
|http://client.texturadesign.com/hellokitty/blog/2007
|http://client.texturadesign.com/private/
|http://client.texturadesign.com/NEWPROJECT/
|http://client.texturadesign.com/subride/
|http://client.texturadesign.com/mt4enterprisepack/
|http://tdi.local/subrosa/
|http://texturadesign.com/
|http://texturadesign.com/blog/
|http://texturadesign.com/projects/
|http://client.texturadesign.com/private
|http://client.texturadesign.com/NEWPROJECT
|http://client.texturadesign.com/subride
|http://client.texturadesign.com/mt4enterprisepack
|http://tdi.local/subrosa
|http://texturadesign.com
|http://texturadesign.com/blog
|http://texturadesign.com/projects
|http://client.texturadesign.com/private/blog
|http://client.texturadesign.com/NEWPROJECT/blog
|http://client.texturadesign.com/subride/blog
|http://client.texturadesign.com/mt4enterprisepack/blog
|http://tdi.local/subrosa/blog
|http://texturadesign.com/blog
|http://texturadesign.com/blog/blog
|http://texturadesign.com/projects/blog
|http://client.texturadesign.com/private/blog/
|http://client.texturadesign.com/NEWPROJECT/blog/
|http://client.texturadesign.com/subride/blog/
|http://client.texturadesign.com/mt4enterprisepack/blog/
|http://tdi.local/subrosa/blog/
|http://texturadesign.com/blog/
|http://texturadesign.com/blog/blog/
|http://texturadesign.com/projects/blog/
|http://client.texturadesign.com/private/blog/2007
|http://client.texturadesign.com/NEWPROJECT/blog/2007
|http://client.texturadesign.com/subride/blog/2007
|http://client.texturadesign.com/mt4enterprisepack/blog/2007
|http://tdi.local/subrosa/blog/2007
|http://texturadesign.com/blog/2007
|http://texturadesign.com/blog/blog/2007
|http://texturadesign.com/projects/blog/2007
);
}
