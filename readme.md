# SubRosa, a privacy-management plugin for Melody and Movable Type

The SubRosa plugin provides authenticated reading of published blogs. 

SubRosa can return three states to help determine what content to display:

* Logged in and authorized to view content
* Logged in and *not* authorized to view content
* Not logged in

A flexible framework allows you to create a privacy policy suited to your
site's specific needs. A privacy policy can enforce authorization in any way
you need it: per blog, user, entry, or any other data (or combination
thereof).

# Installation

To install this plugin follow the instructions found here:

http://tinyurl.com/easy-plugin-install


# Configuration

## Required Templates

Two templates need to be added to your blog's theme:

* Find `plugins/SubRosa/default_templates/subrosa_config.php.tmpl`. This
  template should be published to your blog's root as `subrosa_config.php`.
  Review the variables and update any paths if necessary. 

* Find `plugins/SubRosa/html/gatekeeper.php`. This file should be copied to
  your blog's root; adding it to a theme as a manually-published template is a
  good way to be sure this is included properly

## Update Theme Templates with Authentication

Below is a snippet of code showing how to implement SubRosa to show specific
content. This snippet could be used in a Page template, for example. Notice
the user of Template Modules for each of the three authorization/login states
to keep the code readable.

The variable `$is_authenticated` returns true if a user is logged in;
otherwise false. The variable `$is_authorized` relies upon your privacy policy
to determine true/false status.

    <mt:If name="preview_template">
        <mt:Ignore> 
            If the page is being previewed from within MT, content 
            should always be shown.
        </mt:Ignore>
        <mt:Include module="Page Content (Authorized)">
    <mt:Else>
        <mt:Ignore>
            These variables are used to determine what content to show/hide 
            depending upon the user's logged-in status.
        </mt:Ignore>
        <?php

            $is_authorized    = $mt->policy->is_authorized();
            $is_authenticated = $mt->auth->user();

        if ( $is_authorized ) {  ?>
            <mt:Ignore>
                The user is allowed to see the page content. They 
                are logged in and are authorized to view this content.
            </mt:Ignore>
            <mt:Include module="Page Content (Authorized)">

        <?php } elseif ( $is_authenticated ) { ?>
            <mt:Ignore>
                The user is not allowed to see the page content. They
                are logged in, but are not authorized to view content.
            </mt:Ignore>
            <mt:Include module="Page Content (Unauthorized)">

        <?php } else { ?>
            <mt:Ignore>
                The user is not allowed to see the page content. They
                are not logged in.
            </mt:Ignore>
            <mt:Include module="Page Content (Not Logged In)">

        <?php } ?>
    </mt:if>

### Example: "Page Content (Not Logged In)" Template Module

In the above example, a Template Module named "Page Content (Not Logged In)"
is used. This module might contain an explanation of why the user can't see
the content as well as links to register or log in.

    <div class="not-authenticated">
        <h2>Not Logged In</h2>
        <p>To view this page you are required to be logged in.</p>
        <p>

            <a href="javascript:void(0);" onclick="window.location.href='
                <mt:CGIPath><mt:CommunityScript>?__mode=login
                &amp;blog_id=<mt:BlogID>&amp;return_to=' +
                encodeURIComponent(location.href);">Log in</a> 
            or <a href="javascript:void(0);" onclick="window.location.href='
                <mt:CGIPath><mt:CommunityScript>?__mode=register
            &amp;blog_id=<mt:BlogID>&amp;return_to=' +
            encodeURIComponent(location.href);">sign up</a>.
        </p>
    </div>

Alternatively, this template module could automatically redirect to the login
page with the
[SignInLink](http://www.movabletype.org/documentation/appendices/tags/signinlink.html)
template tag, for example:

    <?php header("Location: <mt:SignInLink>"); ?>

## Creating a Privacy Policy

You'll need to create a privacy policy to manage which users can view which
content. The policy file should be stored in
`[MT_HOME]/plugins/SubRosa/php/plugins/`, and should follow the naming
convention of `policy.[name].php`.

An example policy is included: `policy.loggedin.php`, found in
`pluginsSubRosa/php/plugins/`. This policy is commented to help you understand
better how a custom SubRosa policy might work. As noted above, this policy
also shows the use of a login redirect.

## Include `gatekeeper.php`

The copy of `gatekeeper.php` found at your site root should be included on all
published pages of your site. (More specifically, it must be included in any
published pages you want to protect; it can be included on any page without
any ill-effect.)

There are two ways to include `gatekeeper.php`; either works fine:

* Auto-prepend the file in your `.htaccess`. Example:

        php_value auto_prepend_file <mt:BlogSitePath>gatekeeper.php

* Auto-prepend the file in your `httpd.conf`. Example:

        php_admin_value auto_prepend_file /absolute/path/to/published/site/gatekeeper.php

* Include `gatekeeper.php` in templates. Note that `gatekeeper.php` must be
  the very first item in a template. Example:

        <?php include('<mt:BlogSitePath>gatekeeper.php'); ?>

## Set Environment Variables

SubRosa depends upon a few environment variables being set. If you're using
`.htaccess` and have `.htaccess` published by Movable Type or Melody, it's
easy to set the variables:

    SetEnv MT_HOME <mt:CGIServerPath>
    SetEnv SUBROSA_POLICY MyCustomPolicy
    SetEnv SUBROSA_BLOG_ID <mt:BlogID>

Environment variables can also be set in `httpd.conf` if you prefer, though
because this file likely isn't published through Movable Type or Melody,
template tags can not be used. Your configuration may look like this:

    <Directory /absolute/path/to/published/site/>
        SetEnv MT_HOME /absolute/path/to/mt/
        SetEnv SUBROSA_POLICY MyCustomPolicy
        SetEnv SUBROSA_BLOG_ID 32
    </Directory>

Note that if your privacy policy relies on any additional environment
variables being set, you can set them here. For example:

    SetEnv SUBROSA_PRODUCT "<mt:MyProductName>"

## PHP Configuration and Troubleshooting

The server's PHP configuration may need to be updated to work with SubRosa.

* Published files must be made parseable by PHP
* `safe_mode` should be off
* `open_basedir` should reference the current directory or a parent
* PHP should be compiled with support for your database type.

Use `<?php phpinfo(); ?>` to easily inspect all of the above.


# About Endevver

We design and develop web sites, products and services with a focus on 
simplicity, sound design, ease of use and community. We specialize in 
Movable Type and offer numerous services and packages to help customers 
make the most of this powerful publishing platform.

http://www.endevver.com/

# Copyright

Copyright 2010, Endevver, LLC. All rights reserved.

# License

This plugin is licensed under the same terms as Perl itself.
