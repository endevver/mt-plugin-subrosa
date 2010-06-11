package SubRosa::Template::ContextHandlers;
# SubRosa - A plugin for Movable Type
# See README.txt in this package for more details
# Copyright 2007, All rights reserved
# $Id$
use strict; use 5.006; use warnings; use Data::Dumper;

use vars qw($logger);
use MT::Log::Log4perl qw(l4mtdump);
$logger = MT::Log::Log4perl->new();

=head1 NAME

SubRosa::Template::ContextHandlers - SubRosa template tags

=head2 Template Tags

=head3 SubRosa

A variable tag that provides protection to one or more pages produced by the
containing template.  The C<<$mt:SubRosa$>> tag (with optional attributes
described below) should be the first content inside of any template you wish
to protect.

=head4 Arguments

=over 4

=item * usernames

When used in the absence of other attributes, this attribute limits the set of users allowed to the comma-delimted set of specified usernames.

When used in conjunction with other attributes, it has an additive effect allowing the specified usernames access I<in addition to> and regardless of the result of the other attributes.

=back

=over 4

=item * roles

This attribute specifies one or more roles (comma-delimited) to allow.  If you want to allow users with any role on the blog, simply use C<roles="ANY">.

=back

=over 4

=item * mimetype

Specify the MIME type of the page being protected. The default is PHP.  This is only needed for templates that produce non-PHP content such as XML feeds.

=back

=head4 Examples

Below are a few examples of usage of the SubRosa tag and which users each will allow to view the page(s) produced by the template containing it:

    <$mt:SubRosa$>

This allows any logged in, active user.

    <$mt:SubRosa roles="ANY"$>

This allows any logged in, active user with any role on the blog in context.

    <$mt:SubRosa roles="Writer, blog administrator, designer"$>

The above allows any logged-in user with a writer, blog administrator or designer role on the blog in context.

    <$mt:SubRosa usernames="joe, jay, jim, jerry"$>

Only four users with the usernames joe, jay, jim and jerry.

    <$mt:SubRosa roles="ANY" usernames="joe, jay, jim, jerry"$>

Because of the additive nature of C< usernames> when used with C< roles>, this allows any logged in, active user with a role on the blog in context I<as well as> the users joe, jay, jim and jerry (additive effect).


=cut
sub _hdlr_subrosa {
    my $ctx = shift;
    my $args = shift;

    my $plugin = MT->component('subrosa');
    my $blog_id = $ctx->stash('blog_id');
    
    my $template = _subrosa_template();

    my @roles     = split /\s*,\s*/, ($args->{roles} || $args->{role} || '');
    my @usernames = split /\s*,\s*/,
        ($args->{usernames} || $args->{username} || '');

    my %param;
    $param{blog_id} = $blog_id;
    ($param{cgipath} = $ctx->{config}->CGIPath) =~ s!/*$!/!;
    $param{communityscript} = $ctx->{config}->CommunityScript;
    
    require File::Spec;
    $param{subrosa_lib} = File::Spec->catfile( 
        $ENV{MT_HOME}, $plugin->envelope,'php','lib','Util.php');
    
    $param{role_var} = '$roles = Array("'. join('","', @roles) .'");'
        if @roles;

    $param{username_var} = '$usernames = Array("'. join('","', @usernames) .'");'
        if @usernames;

    $param{mimetype_var} = sprintf('$mimetype = "%s";', $args->{mimetype})
        if $args->{mimetype};

    foreach (qw(SUBROSA_LIB ROLE_VAR USERNAME_VAR MIMETYPE_VAR
                BLOG_ID CGIPATH COMMUNITYSCRIPT)) {
        my $key = lc($_);
        $param{$key} ||= '';
        $template =~ s!$_!$param{$key}!g;
    }
    $template;
}

sub _subrosa_template {
    my $template =<<'END';
<?php
include("SUBROSA_LIB");
ROLE_VAR
USERNAME_VAR
MIMETYPE_VAR

$blog_id = BLOG_ID;
$cgipath = 'CGIPATH';
$communityscript = 'COMMUNITYSCRIPT';

list($cname, $csid, $cpersist) = user_cookie('mt_user');
if (! ($cname && $csid)) {
    // No user, redirect to login
    header(sprintf('Location: %s%s?__mode=login&blog_id=%s&return_to=%s',
        $cgipath,
        $communityscript,
        $blog_id,
        urlencode($_SERVER['PHP_SELF'])));
}

function user_cookie($name=null) {
    $usercookie = cookie($name);
    if ($usercookie) {
        $parts = explode('::', $usercookie);
        return $parts;
    }
    return array(null, null, null);
}

?>
END
  $template;  
}

1;