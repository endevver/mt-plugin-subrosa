<pre>
MTCGIPath: {{MTCGIPath}}
MTAdminScript = {{MTAdminScript}}
$script_url: {{$script_url}}

Query params:
{{section name=query_string loop=$query_string}}
  {{$query_string[name]}} = {{$query_string[name]}}
{{/section}}

$is_bookmarklet = {{$is_bookmarklet}}
$logged_out = {{$logged_out}}
$delegate_auth = {{$delegate_auth}}
$login_again = {{$login_again}}
$error = {{$error}}

MTErrorMessage = {{MTErrorMessage}}

{{$login_fields}}

$can_recover_password = {{$can_recover_password}}

DEBUG INFO:

{{$debug_output}}

