package SubRosa::Plugin;
# SubRosa - A plugin for Movable Type
# See README.txt in this package for more details
# Copyright 2007, All rights reserved
# $Id$
use strict; use 5.006; use warnings; use Data::Dumper;

use vars qw($logger);
use MT::Log::Log4perl qw(l4mtdump);
$logger = MT::Log::Log4perl->new();

sub cb_start_session {
    my $cb = shift;
    my ($obj, $orig) = @_;
    $logger->trace();
    $logger->warn('EXTRA ARGUMENTS ENCOUNTERED') if @_ > 2;    
    $logger->debug('OBJ: ', l4mtdump($obj));
    $logger->debug('ORIG: ', l4mtdump($orig));
}

sub cb_end_session {
    my $cb = shift;
    my ($obj, $orig) = @_;
    $logger->trace();
    $logger->warn('EXTRA ARGUMENTS ENCOUNTERED') if @_ > 2;
    $logger->debug('OBJ: ', l4mtdump($obj));
    $logger->debug('ORIG: ', l4mtdump($orig));
}


1;