<?php
require_once( 'SubRosa/Env.php' );

/**
 * SubRosa_Env_Debug
 *
 * @package default
 **/
class SubRosa_Env_Debug extends SubRosa_Env {

    function __construct() {
        global $mt;

        parent::__construct();

        /*================================================================*\
         *                    PHP INI DIRECTIVES                          *
         *  http://www.php.net/manual/en/errorfunc.configuration.php      *
        \*================================================================*/

        // Even when display_errors is on, errors that occur
        // during PHP's startup sequence are not displayed.
        // It's strongly recommended to keep display_startup_errors
        // off, except for debugging.
        ini_set('display_startup_errors', 'On');

        # This determines whether errors should be printed to the screen as 
        # part of the output or if they should be hidden from the user.
        # Note: Although display_errors may be set at runtime (with 
        # ini_set()), it won't have any affect if the script has fatal errors. 
        # This is because the desired runtime action does not get executed.
        ini_set('display_errors', 'On');

        // Tells whether script error messages should be logged to
        // the server's error log or error_log. This option is thus
        // server-specific.
        ini_set('log_errors', 'On' );

        // ini_set('error_log', '/PATH/TO/php-errors.log'); // Log to file
        // ini_set('error_log', 'syslog');                  // Log to syslog
        // UNSET                                            // Log to STDERR

        # html_errors boolean
        # Turn off HTML tags in error messages. The new format for HTML errors
        # produces clickable messages that direct the user to a page 
        # describing the error or function in causing the error. These 
        # references are affected by docref_root and docref_ext.
        ini_set('html_errors', 'On');


        /*===============================================================*\
         *                   XDEBUG INI DIRECTIVES                       *
         *            http://xdebug.org/docs/all_settings                *
        \*===============================================================*/

        # xdebug.overload_var_dump
        # Type: boolean, Default value: 1, Introduced in Xdebug 2.1
        # By default Xdebug overloads var_dump() with its own improved 
        # version for displaying variables when the html_errors php.ini 
        # setting is set to 1. In case you do not want that, you can set this 
        # setting to 0, but check first if it's not smarter to turn off 
        # html_errors.
        ini_set('xdebug.overload_var_dump', 1);

        # xdebug.trace_output_dir
        # Type: string, Default value: /tmp
        # The directory where the tracing files will be written to, make sure 
        # that the user who the PHP will be running as has write permissions 
        # to that directory.
        ini_set('xdebug.trace_output_dir', '/tmp');

        # xdebug.trace_output_name
        # Type: string, Default value: trace.%c
        # This setting determines the name of the file that is used to dump 
        # traces into. The setting specifies the format with format 
        # specifiers, very similar to sprintf() and strftime(). There are 
        # several format specifiers that can be used to format the file name. 
        # The '.xt' extension is always added automatically.
        // ini_set('xdebug.trace_output_name', 'trace.%c');

        # xdebug.default_enable
        # Type: boolean, Default value: 1
        # If this setting is 1, then stacktraces will be shown by default on 
        # an error event. You can disable showing stacktraces from your code 
        # with xdebug_disable(). As this is one of the basic functions of 
        # Xdebug, it is advisable to leave this setting set to 1.
        ini_set('xdebug.default_enable', true);

        # xdebug.max_nesting_level
        # Type: integer, Default value: 100
        # Controls the protection mechanism for infinite recursion protection. 
        # The value of this setting is the maximum level of nested functions 
        # that are allowed before the script will be aborted.
        ini_set('xdebug.max_nesting_level', 100);

        # xdebug.scream
        # Type: boolean, Default value: 0, Introduced in Xdebug 2.1
        # If this setting is 1, then Xdebug will disable the @ (shut-up) 
        # operator so that notices, warnings and errors are no longer hidden.
        ini_set('xdebug.scream', true);

        # xdebug.auto_trace
        # Type: boolean, Default value: 0
        # When this setting is set to on, the tracing of function calls will 
        # be enabled just before the script is run. This makes it possible to 
        # trace code in the auto_prepend_file.
        ini_set('xdebug.auto_trace', true);

        # Human-readable traces
        ini_set('xdebug.trace_format', 0);

        # xdebug.collect_assignments
        # Type: boolean, Default value: 0, Introduced in Xdebug 2.1
        # This setting, defaulting to 0, controls whether Xdebug should add 
        # variable assignments to function traces.
        ini_set('xdebug.collect_assignments', true);

        # xdebug.collect_includes
        # Type: boolean, Default value: 1
        # This setting, defaulting to 1, controls whether Xdebug should write 
        # the filename used in include(), include_once(), require() or 
        # require_once() to the trace files.
        ini_set('xdebug.collect_includes', true);

        # xdebug.collect_params
        # Type: integer, Default value: 0
        # This setting, defaulting to 0, controls whether Xdebug should 
        # collect the parameters passed to functions when a function call is 
        # recorded in either the function trace or the stack trace.
        #
        # The setting defaults to 0 because for very large scripts it may use
        # huge amounts of memory and therefore make it impossible for the huge
        # script to run. You can most safely turn this setting on, but you can
        # expect some problems in scripts with a lot of function calls and/or
        # huge data structures as parameters. 
        #
        # Xdebug 2 will not have this problem with increased memory usage, as
        # it will never store this information in memory. Instead it will only
        # be written to disk. This means that you need to have a look at the
        # disk usage though.
        #
        # This setting can have four different values. For each of the values 
        # a different amount of information is shown. Below you will see what
        # information each of the values provides. See also the introduction 
        # of the feature Stack Traces for a few screenshots.
        #
        #   VALUE    ARGUMENT INFORMATION SHOWN
        #     0      None.
        #     1      Type and number of elements (f.e. string(6), array(8)).
        #     2      Type and number of elements, with a tool tip for the full
        #            information 1.
        #     3      Full variable contents (with the limits respected as set 
        #                by  xdebug.var_display_max_children, 
        #                    xdebug.var_display_max_data 
        #                and xdebug.var_display_max_depth)
        #     4      Full variable contents and variable name.
        ini_set('xdebug.collect_params', 4);

        # xdebug.collect_return
        # Type: boolean, Default value: 0
        # This setting, defaulting to 0, controls whether Xdebug should write 
        # the return value of function calls to the trace files.
        ini_set('xdebug.collect_return', true);

        # xdebug.collect_vars
        # Type: boolean, Default value: 0
        # 
        # This setting tells Xdebug to gather information about which 
        # variables are used in a certain scope. This analysis can be quite 
        # slow as Xdebug has to reverse engineer PHP's opcode arrays. This 
        # setting will not record which values the different variables have, 
        # for that use xdebug.collect_params. This setting needs to be enabled 
        # only if you wish to use xdebug_get_declared_vars().
        ini_set('xdebug.collect_vars', true);

        # xdebug.trace_options
        # Type: integer, Default value: 0
        # When set to '1' the trace files will be appended to, instead of 
        # being overwritten in subsequent requests.
        ini_set('xdebug.trace_options', 1);

        # xdebug.show_exception_trace
        # Type: integer, Default value: 0
        # When this setting is set to 1, Xdebug will show a stack trace 
        # whenever an exception is raised - even if this exception is actually 
        # caught.
        ini_set('xdebug.show_exception_trace', 1);

        ini_set( 'xdebug.dump.COOKIE',    true);
        ini_set( 'xdebug.dump.ENV',       true);
        ini_set( 'xdebug.dump.FILES',     true);
        ini_set( 'xdebug.dump.GET',       true);
        ini_set( 'xdebug.dump.POST',      true);
        ini_set( 'xdebug.dump.REQUEST',   true);
        ini_set( 'xdebug.dump.SERVER',    true);
        ini_set( 'xdebug.dump.SESSION',   true);
        ini_set( 'xdebug.dump_globals',   true);
        ini_set( 'xdebug.dump_undefined', true);

        print "Starting up XDebug monitoring\n";

        // (void) xdebug_enable
        // Enables display of stack traces on error conditions
        xdebug_enable();

        // (void) xdebug_start_trace( string trace_file [, integer options] )
        // Starts a new function trace
        // 
        // Start tracing function calls from this point to the file in the 
        // trace_file parameter. If no filename is given, then the trace
        // file will be placed in the directory as configured by the
        // xdebug.trace_output_dir setting.
        // 
        // In case a file name is given as first parameter, the name is
        // relative to the current working directory. This current working
        // directory might be different than you expect it to be, so please
        // use an absolute path in case you specify a file name. Use the PHP
        // function getcwd() to figure out what the current working directory
        // is.
        // 
        // The name of the trace file is "{trace_file}.xt". If 
        // xdebug.auto_trace is enabled, then the format of the filename is
        // "{filename}.xt" where the "{filename}" part depends on the
        // xdebug.trace_output_name setting. The options parameter is a
        // bitfield; currently there are three options:
        // 
        //      XDEBUG_TRACE_APPEND (1)
        //      Makes the trace file open in append mode, not overwrite mode
        // 
        //      XDEBUG_TRACE_COMPUTERIZED (2)
        //      Creates a trace file with the format as described under 1
        //     "xdebug.trace_format".
        // 
        //      XDEBUG_TRACE_HTML (4)
        //      Creates a trace file as an HTML table
        // 
        // Unlike Xdebug 1, Xdebug 2 will not store function calls in memory,
        // but always only write to disk to relieve the pressure on used
        // memory. The settings xdebug.collect_includes, xdebug.collect_params
        // and xdebug.collect_return influence what information is logged to
        // the trace file and the setting xdebug.trace_format influences the
        // format of the trace file.
        xdebug_start_trace(null, XDEBUG_TRACE_APPEND & XDEBUG_TRACE_HTML);

        // (void) xdebug_start_code_coverage( [int options] )
        // Starts code coverage
        // 
        // This function starts gathering the information for code coverage.
        // The information that is collected consists of an two dimensional
        // array with as primary index the executed filename and as secondary
        // key the line number. The value in the elements represents the
        // total number of execution units on this line have been executed.
        // 
        // Options to this function are: 
        //
        //      XDEBUG_CC_UNUSED
        //      Enables source scanning to find lines with executable code.
        //
        //      XDEBUG_CC_DEAD_CODE
        //      Enables branch analysis to determine if code can be executed.
        xdebug_start_code_coverage();
    }

    function __destruct() {
        print "Shutting down XDebug monitoring\n";

        // (void) xdebug_stop_trace()
        // Stops the current function trace
        // Stop tracing function calls and closes the trace file.
        xdebug_stop_trace();

        // (void) xdebug_dump_superglobals
        // Displays information about super globals
        // This function dumps the values of the elements of the super 
        // globals as specified with the xdebug.dump.* php.ini settings.
        xdebug_dump_superglobals();

        // (array) xdebug_get_declared_vars
        // Returns declared variables
        // Returns an array where each element is a variable name defined in
        // the current scope. Requires that xdebug.collect_vars is enabled. .
        var_dump(xdebug_get_declared_vars());

        // (array) xdebug_get_code_coverage( )
        // Returns code coverage information
        // Returns a structure which contains information about which
        // lines were executed in your script (including include files).
        var_dump(xdebug_get_code_coverage());

        parent::__destruct();
    }
} // END class SubRosa_DebuggingEnv

/*=====================================================================*\
 *                         XDEBUG FUNCTIONS                            *
 *                http://xdebug.org/docs/all_functions                 *
\*=====================================================================*/

/**
 *      OTHER HIGHLY USEFUL XDEBUG FUNCTIONS
 */
// (array) xdebug_get_function_stack
// Returns information about the stack
// Returns an array which resembles the stack trace up to this point.
#       var_dump(xdebug_get_function_stack());

// (string) xdebug_get_tracefile_name( )
// Returns the name of the function trace file
// Returns the name of the file which is used to trace the output of this
// script too. This is useful when xdebug.auto_trace is enabled.
//      var_dump(xdebug_get_tracefile_name());

/*=========================================================================*\
 *                      XDEBUG DIRECTIVE REFERENCE                         *
 *                 http://xdebug.org/docs/all_settings                    *
\*=========================================================================*/

/*
Directive           Local val   Master val
xdebug.auto_trace    On    Off
xdebug.collect_includes    On    On
xdebug.collect_params    0    0
xdebug.collect_return    Off    Off
xdebug.collect_vars    Off    Off
xdebug.default_enable    On    On
xdebug.dump.COOKIE    no value    no value
xdebug.dump.ENV    no value    no value
xdebug.dump.FILES    no value    no value
xdebug.dump.GET    no value    no value
xdebug.dump.POST    no value    no value
xdebug.dump.REQUEST    no value    no value
xdebug.dump.SERVER    no value    no value
xdebug.dump.SESSION    no value    no value
xdebug.dump_globals    On    On
xdebug.dump_once    On    On
xdebug.dump_undefined    Off    Off
xdebug.extended_info    On    On
xdebug.idekey    no value    no value
xdebug.manual_url    http:#www.php.net    http:#www.php.net
xdebug.max_nesting_level    100    100
xdebug.profiler_aggregate    Off    Off
xdebug.profiler_append    Off    Off
xdebug.profiler_enable    Off    Off
xdebug.profiler_enable_trigger    Off    Off
xdebug.profiler_output_dir    /tmp    /tmp
xdebug.profiler_output_name    cachegrind.out.%p    cachegrind.out.%p
xdebug.remote_autostart    Off    Off
xdebug.remote_enable    Off    Off
xdebug.remote_handler    dbgp    dbgp
xdebug.remote_host    localhost    localhost
xdebug.remote_log    no value    no value
xdebug.remote_mode    req    req
xdebug.remote_port    9000    9000
xdebug.show_exception_trace    Off    Off
xdebug.show_local_vars    Off    Off
xdebug.show_mem_delta    Off    Off
xdebug.trace_format    0    0
xdebug.trace_options    0    0
xdebug.trace_output_dir    /tmp    /tmp
xdebug.trace_output_name    trace.%c    trace.%c
xdebug.var_display_max_children    128    128
xdebug.var_display_max_data    512    512
xdebug.var_display_max_depth    3    3

*/

?>