{{include file="$plugin_template_dir/header.tpl"}}

<script type="text/javascript">
<!--
// if this loads within our modal dialog iframe, force the user to
// login from the 'top' of the browser.
if (window.top && (window.top.location != window.location)) {
    // strip any parameters to return them to the main menu,
    // since leaving them will display the modal dialog in the
    // full window.
    var loc = window.location.href;
    loc = loc.replace(/\?.+/, '');
    window.top.location.href = loc;
}

function init() {
    var u = getByID("username");
    if (u.value != '') {
        var p = getByID("password");
        p.focus();
    } else {
        u.focus();
    }
}
TC.attachLoadEvent(init);
//-->
</script>

<form method="post" action="{{$script_url}}">
    <input type="hidden" name="login" value="1" />

{{section name=query_param loop=$query_param}}
    <input type="hidden" name="{{$escaped_name}}" value="{{$escaped_value}}" />
{{/section}}


{{if $is_bookmarklet}}
    <input type="hidden" name="is_bm" value="1" />
{{/if}}


{{if $logged_out}}
    {{if $delegate_auth}}
        <h4 class="message">Your Movable Type session has ended.</h4>
    {{else}}
        <h4 class="message">Your Movable Type session has ended. If you wish to log in again, you can do so below.</h4>
    {{/if}}
{{else}}
    {{if $login_again}}
        <h4 class="message">Your Movable Type session has ended. Please login again to continue this action.</h4>
    {{else}}
        {{if $error}}
            <div class="error-message">{{MTErrorMessage}}</div>
        {{/if}}
    {{/if}}
{{/if}}

{{if ! $delegate_auth}}

    {{include file="$plugin_template_dir/$login_fields"}}
    <p><input type="submit" name="submit" value="Log In" /></p>

{{/if}}

{{if $can_recover_password}}<p><a href="#" onclick="window.open('{{MTCGIPath}}{{MTAdminScript}}?__mode=start_recover', 'recover', 'width=370,height=250'); return false">Forgot your password?</a></p>{{/if}}

</form>

{{debug}}

{{if $debug_output}}<pre>{{$debug_output}}</pre>{{/if}}

{{include file="$plugin_template_dir/footer.tpl"}}
