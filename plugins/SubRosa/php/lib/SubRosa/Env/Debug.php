<?php
require_once( 'SubRosa/Env.php' );
/*

    ______XDebug functions for use within the code______
    
    #################################################
    # SECTION:  BASIC FEATURES
    # URL:      http://www.xdebug.org/docs/basic
    #################################################

string xdebug_call_class( )
    Returns the calling class
    This function returns the name of the class from which the current 
    function/method was called from.

string xdebug_call_file( )
    Returns the calling file
    This function returns the filename that contains the function/method 
    that called the current function/method.

string xdebug_call_function( )
    Returns the calling function/method
    This function returns the name of the function/method from which the
    current function/method was called from.

int xdebug_call_line( )
    Returns the calling line
    This function returns the line number that contains the function/method 
    that called the current function/method.

void xdebug_disable( )
    Disables stack traces
    Disable showing stack traces on error conditions.

void xdebug_enable( )
    Enables stack traces
    Enable showing stack traces on error conditions.

array xdebug_get_headers( )
    Returns all the headers as set by calls to PHP's header() function
    Returns all the headers that are set with PHP's header() function, or any 
    other header set internally within PHP (such as through setcookie()), as 
    an array.

    Example:

        <?php
        header( "X-Test", "Testing" );
        setcookie( "TestCookie", "test-value" );
        var_dump( xdebug_get_headers() );
        ?>

    Returns:

        array(2) {
          [0]=>
          string(6) "X-Test"
          [1]=>
          string(33) "Set-Cookie: TestCookie=test-value"
        }

    This function is introduced with Xdebug 2.1.

bool xdebug_is_enabled( )
    Returns whether stack traces are enabled
    Return whether stack traces would be shown in case of an error or not.

int xdebug_memory_usage( )
    Returns the current memory usage
    Returns the current amount of memory the script uses. Before PHP 5.2.1, 
    this only works if PHP is compiled with --enable-memory-limit. From PHP 
    5.2.1 and later this function is always available.

int xdebug_peak_memory_usage( )
    Returns the peak memory usage
    Returns the maximum amount of memory the script used until now. Before PHP 
    5.2.1, this only works if PHP is compiled with --enable-memory-limit. From 
    PHP 5.2.1 and later this function is always available.

float xdebug_time_index( )
    Returns the current time index
    Returns the current time index since the starting of the script in 
    seconds.

    Example:

        <?php
        echo xdebug_time_index(), "\n";
        for ($i = 0; $i < 250000; $i++)
        {
            // do nothing
        }
        echo xdebug_time_index(), "\n";
        ?>

    Returns:

        0.00038003921508789
        0.76580691337585


    #################################################
    # SECTION:  VARIABLE DISPLAY FEATURES
    # URL:      http://www.xdebug.org/docs/display
    #################################################

void var_dump( [mixed var [, ...]] )
    Displays detailed information about a variable
    This function is overloaded by Xdebug, see the description for 
    xdebug_var_dump().

void xdebug_debug_zval( [string varname [, ...]] )
    Displays information about a variable
    This function displays structured information about one or more variables
    that includes its type, value and refcount information. Arrays are
    explored recursively with values. This function is implemented differently
    from PHP's debug_zval_dump() function in order to work around the problems
    that that function has because the variable itself is actually passed to
    the function. Xdebug's version is better as it uses the variable name to
    lookup the variable in the internal symbol table and accesses all the
    properties directly without having to deal with actually passing a
    variable to a function. The result is that the information that this
    function returns is much more accurate than PHP's own function for showing
    zval information.

    Example:

        <?php
            $a = array(1, 2, 3);
            $b =& $a;
            $c =& $a[2];

            xdebug_debug_zval('a');
        ?>

    Returns:

        a: (refcount=2, is_ref=1)=array (
        	0 => (refcount=1, is_ref=0)=1, 
        	1 => (refcount=1, is_ref=0)=2, 
        	2 => (refcount=2, is_ref=1)=3)

void xdebug_debug_zval_stdout( [string varname [, ...]] )
    Returns information about variables to stdout.
    This function displays structured information about one or more variables
    that includes its type, value and refcount information. Arrays are
    explored recursively with values. The difference with xdebug_debug_zval()
    is that the information is not displayed through a web server API layer,
    but directly shown on stdout (so that when you run it with apache in
    single process mode it ends up on the console).

    Example:

        <?php
            $a = array(1, 2, 3);
            $b =& $a;
            $c =& $a[2];

            xdebug_debug_zval_stdout('a');

    Returns:

        a: (refcount=2, is_ref=1)=array (
        	0 => (refcount=1, is_ref=0)=1, 
        	1 => (refcount=1, is_ref=0)=2, 
        	2 => (refcount=2, is_ref=1)=3)

void xdebug_dump_superglobals( )
    Displays information about super globals
    This function dumps the values of the elements of the super globals as
    specified with the xdebug.dump.* php.ini settings. For the example below
    the settings in php.ini are:

    Example:

        xdebug.dump.GET=*
        xdebug.dump.SERVER=REMOTE_ADDR

        Query string:
        ?var=fourty%20two&array[a]=a&array[9]=b

    Returns:

        Dump $_SERVER
        $_SERVER['REMOTE_ADDR'] =
        string '127.0.0.1' (length=9)
        Dump $_GET
        $_GET['var'] =
        string 'fourty two' (length=10)
        $_GET['array'] =
        array
          'a' => string 'a' (length=1)
          9 => string 'b' (length=1)

void xdebug_var_dump( [mixed var [, ...]] )
    Displays detailed information about a variable
    This function displays structured information about one or more
    expressions that includes its type and value. Arrays are explored
    recursively with values. See the introduction of Variable Display Features
    on which php.ini settings affect this function.

    Example:

        <?php
        ini_set('xdebug.var_display_max_children', 3 );
        $c = new stdClass;
        $c->foo = 'bar';
        $c->file = fopen( '/etc/passwd', 'r' );
        var_dump(
            array(
                array(TRUE, 2, 3.14, 'foo'),
                'object' => $c
            )
        );
        ?>  

    Returns:

        array
          0 => 
            array
              0 => boolean true
              1 => int 2
              2 => float 3.14
              more elements...
          'object' => 
            object(stdClass)[1]
              public 'foo' => string 'bar' (length=3)
              public 'file' => resource(3, stream)


    #################################################
    # SECTION:  STACK TRACES
    # URL:      http://www.xdebug.org/docs/stack_trace
    #################################################

array xdebug_get_declared_vars( )
    Returns declared variables
    Returns an array where each element is a variable name which is defined in 
    the current scope. The setting xdebug.collect_vars needs to be enabled

    Example:

        var_dump(xdebug_get_declared_vars());

array xdebug_get_function_stack( )
    Returns information about the stack
    Returns an array which resembles the stack trace up to this point. The 
    example script:

    Example:

        var_dump(xdebug_get_function_stack());

integer xdebug_get_stack_depth( )
    Returns the current stack depth level
    Returns the stack depth level. The main body of a script is level 0 and 
    each include and/or function call adds one to the stack depth level.

none xdebug_print_function_stack( [ string message ] )
    Displays the current function stack.
    Displays the current function stack, in a similar way as what Xdebug would 
    display in an error situation.

    The "message" argument was introduced in Xdebug 2.1.

    Example:

        xdebug_print_function_stack( 'Your own message' );

    #################################################
    # SECTION:  EXECUTION TRACE
    # URL:      http://www.xdebug.org/docs/execution_trace
    #################################################

string xdebug_get_tracefile_name( )
    Returns the name of the function trace file
    Returns the name of the file which is used to trace the output of this 
    script too. This is useful when xdebug.auto_trace is enabled.

void xdebug_start_trace( string trace_file [, integer options] )
    Starts a new function trace
    Start tracing function calls from this point to the file in the trace_file
    parameter. If no filename is given, then the trace file will be placed in
    the directory as configured by the xdebug.trace_output_dir setting. In
    case a file name is given as first parameter, the name is relative to the
    current working directory. This current working directory might be
    different than you expect it to be, so please use an absolute path in case
    you specify a file name. Use the PHP function getcwd() to figure out what
    the current working directory is.

    The name of the trace file is "{trace_file}.xt". If xdebug.auto_trace is
    enabled, then the format of the filename is "{filename}.xt" where the
    "{filename}" part depends on the xdebug.trace_output_name setting. The
    options parameter is a bitfield; currently there are three options:

        XDEBUG_TRACE_APPEND (1)
        makes the trace file open in append mode rather than overwrite mode

        XDEBUG_TRACE_COMPUTERIZED (2)
        creates a trace file with the format as described under 1
        "xdebug.trace_format".

        XDEBUG_TRACE_HTML (4)
        creates a trace file as an HTML table

    Unlike Xdebug 1, Xdebug 2 will not store function calls in memory, but
    always only write to disk to relieve the pressure on used memory. The
    settings xdebug.collect_includes, xdebug.collect_params and
    xdebug.collect_return influence what information is logged to the trace
    file and the setting xdebug.trace_format influences the format of the
    trace file.

void xdebug_stop_trace( )
    Stops the current function trace
    Stop tracing function calls and closes the trace file.


*/

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

        #################################################
        # SECTION:  VARIABLE DISPLAY FEATURES
        # URL:      http://www.xdebug.org/docs/display
        #################################################

        # xdebug.overload_var_dump
        # Type: boolean, Default value: 1, Introduced in Xdebug 2.1
        # By default Xdebug overloads var_dump() with its own improved 
        # version for displaying variables when the html_errors php.ini 
        # setting is set to 1. In case you do not want that, you can set this 
        # setting to 0, but check first if it's not smarter to turn off 
        # html_errors.
        // ini_set('xdebug.overload_var_dump', 1);

        # xdebug.trace_output_dir
        # Type: string, Default value: /tmp
        # The directory where the tracing files will be written to, make sure 
        # that the user who the PHP will be running as has write permissions 
        # to that directory.
        // ini_set('xdebug.trace_output_dir', '/tmp');

        # xdebug.trace_output_name
        # Type: string, Default value: trace.%c
        # This setting determines the name of the file that is used to dump 
        # traces into. The setting specifies the format with format 
        # specifiers, very similar to sprintf() and strftime(). There are 
        # several format specifiers that can be used to format the file name. 
        # The '.xt' extension is always added automatically.
        // ini_set('xdebug.trace_output_name', 'trace.%c');

        #################################################
        # SECTION:  BASIC FEATURES
        # URL:      http://www.xdebug.org/docs/basic
        #################################################
        
        # xdebug.default_enable
        # Type: boolean, Default value: 1
        # If this setting is 1, then stacktraces will be shown by default on 
        # an error event. You can disable showing stacktraces from your code 
        # with xdebug_disable(). As this is one of the basic functions of 
        # Xdebug, it is advisable to leave this setting set to 1.
        // ini_set('xdebug.default_enable', 1);

        # xdebug.max_nesting_level
        # Type: integer, Default value: 100
        # Controls the protection mechanism for infinite recursion protection. 
        # The value of this setting is the maximum level of nested functions 
        # that are allowed before the script will be aborted.
        // ini_set('xdebug.max_nesting_level', 100);

        # xdebug.scream
        # Type: boolean, Default value: 0, Introduced in Xdebug 2.1
        # If this setting is 1, then Xdebug will disable the @ (shut-up) 
        # operator so that notices, warnings and errors are no longer hidden.
        // ini_set('xdebug.scream', 1);

        #################################################
        # SECTION:  STACK TRACES
        # URL:      http://www.xdebug.org/docs/stack_trace
        #################################################

        # xdebug.collect_includes
        # Type: boolean, Default value: 1
        # This setting, defaulting to 1, controls whether Xdebug should write 
        # the filename used in include(), include_once(), require() or 
        # require_once() to the trace files.
        // ini_set('xdebug.collect_includes', true);

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
        // ini_set('xdebug.collect_params', 4);

        # xdebug.collect_vars
        # Type: boolean, Default value: 0
        # 
        # This setting tells Xdebug to gather information about which 
        # variables are used in a certain scope. This analysis can be quite 
        # slow as Xdebug has to reverse engineer PHP's opcode arrays. This 
        # setting will not record which values the different variables have, 
        # for that use xdebug.collect_params. This setting needs to be enabled 
        # only if you wish to use xdebug_get_declared_vars().
        // ini_set('xdebug.collect_vars', true);

        # xdebug.dump.*
        # Type: string, Default value: Empty
        # 
        # * = COOKIE, FILES, GET, POST, REQUEST, SERVER, SESSION. These seven
        #   settings control which data from the superglobals is shown when 
        #   an error situation occurs. Each php.ini setting can consist of a 
        #   comma seperated list of variables from this superglobal to dump, 
        #   but make sure you do not add spaces in this setting. In order to 
        #   dump the REMOTE_ADDR and the REQUEST_METHOD when an error occurs, 
        #   add this setting:
        #
        #       xdebug.dump.SERVER = REMOTE_ADDR,REQUEST_METHOD
        // ini_set( 'xdebug.dump.COOKIE',    'SubRosa,mt_user');
        // ini_set( 'xdebug.dump.ENV',       'SUBROSA_PASTHRU');
        // // ini_set( 'xdebug.dump.FILES',     true);
        // // ini_set( 'xdebug.dump.GET',       true);
        // // ini_set( 'xdebug.dump.POST',      true);
        // ini_set( 'xdebug.dump.REQUEST',   'blog_id,uri');
        // ini_set( 'xdebug.dump.SERVER',    'SUBROSA_POLICY,SUBROSA_CCSAAuth,REQUEST_URI,QUERY_STRING,SUBROSA_PASTHRU');
        // ini_set( 'xdebug.dump.SESSION',   'current_user');

        # xdebug.dump_globals
        # Type: boolean, Default value: 1
        #
        # Controls whether the values of the superglobals as defined by the 
        # xdebug.dump.* settings whould be shown or not.
        // ini_set( 'xdebug.dump_globals',   true);

        # xdebug.dump_once
        # Type: boolean, Default value: 1
        #
        # Controls whether the values of the superglobals should be dumped on 
        # all error situations (set to 0) or only on the first (set to 1).
        // ini_set( 'xdebug.dump_once',   1);

        # xdebug.dump_undefined
        # Type: boolean, Default value: 0
        #
        # If you want to dump undefined values from the superglobals you 
        # should set this setting to 1, otherwise leave it set to 0.
        // ini_set( 'xdebug.dump_undefined', true);

        # xdebug.file_link_format
        # Type: string, Default value: , Introduced in Xdebug 2.1
        #
        # This setting determines the format of the links that are made in the 
        # display of stack traces where file names are used. This allows IDEs 
        # to set up a link-protocol that makes it possible to go directly to a 
        # line and file by clicking on the filenames that Xdebug shows in 
        # stack traces. An example format might look like:
        # 
        #   myide://%f@%l
        #   The possible format specifiers are:
        # 
        # Specifier	Meaning
        #   %f	the filename
        #   %l	the line number
        #
        # To make file/line links work with FireFox (Linux), use the following 
        # steps:
        # 
        #   1. Open about:config
        #   2. Add a new boolean setting 
        #     "network.protocol-handler.expose.xdebug"
        #   3. Add the following into a shell script "~/bin/ff-xdebug.sh":
        #       #! /bin/sh
        #       
        #       f=`echo $1 | cut -d @ -f 1 | sed 's/xdebug:\/\///'`
        #       l=`echo $1 | cut -d @ -f 2`
        #       Add to that one of (depending whether you have komodo/gvim):
        #       komodo $f -l $l
        #       gvim --remote-tab +$l $f
        #     Make the script executable with chmod +x ~/bin/ff-xdebug.sh
        #     Set the xdebug.file_link_format to xdebug://%f@%l

        # xdebug.manual_url
        # Type: string, Default value: http://www.php.net
        # This is the base url for the links from the function traces and 
        # error message to the manual pages of the function from the message. 
        # It is advisable to set this setting to use the closest mirror.

        # xdebug.show_exception_trace
        # Type: integer, Default value: 0
        # When this setting is set to 1, Xdebug will show a stack trace 
        # whenever an exception is raised - even if this exception is actually 
        # caught.
        // ini_set('xdebug.show_exception_trace', 1);

        # xdebug.show_local_vars
        # Type: integer, Default value: 0
        # When this setting is set to something != 0 Xdebug's generated stack 
        # dumps in error situations will also show all variables in the 
        # top-most scope. Beware that this might generate a lot of 
        # information, and is therefore turned off by default.

        # xdebug.show_mem_delta
        # Type: integer, Default value: 0
        # When this setting is set to something != 0 Xdebug's human-readable 
        # generated trace files will show the difference in memory usage 
        # between function calls. If Xdebug is configured to generate 
        # computer-readable trace files then they will always show this 
        # information.

        # xdebug.var_display_max_children
        # Type: integer, Default value: 128
        # Controls the amount of array children and object's properties are 
        # shown when variables are displayed with either xdebug_var_dump(), 
        # xdebug.show_local_vars or through Function Traces. This setting does 
        # not have any influence on the number of children that is send to the 
        # client through the Remote Debugging feature.

        # xdebug.var_display_max_data
        # Type: integer, Default value: 512
        # Controls the maximum string length that is shown when variables are 
        # displayed with either xdebug_var_dump(), xdebug.show_local_vars or 
        # through Function Traces. This setting does not have any influence on 
        # the amount of data that is send to the client through the Remote 
        # Debugging feature.

        # xdebug.var_display_max_depth
        # Type: integer, Default value: 3
        # Controls how many nested levels of array elements and object 
        # properties are when variables are displayed with either 
        # xdebug_var_dump(), xdebug.show_local_vars or through Function 
        # Traces. This setting does not have any influence on the depth of 
        # children that is send to the client through the Remote Debugging 
        # feature.
        # 

        #################################################
        # SECTION:  EXECUTION TRACE
        # URL:      http://www.xdebug.org/docs/execution_trace
        #################################################

        # xdebug.auto_trace
        # Type: boolean, Default value: 0
        # When this setting is set to on, the tracing of function calls will 
        # be enabled just before the script is run. This makes it possible to 
        # trace code in the auto_prepend_file.
        // ini_set('xdebug.auto_trace', true);

        # xdebug.collect_assignments
        # Type: boolean, Default value: 0, Introduced in Xdebug 2.1
        # This setting, defaulting to 0, controls whether Xdebug should add 
        # variable assignments to function traces.
        // ini_set('xdebug.collect_assignments', true);

        # xdebug.collect_return
        # Type: boolean, Default value: 0
        # This setting, defaulting to 0, controls whether Xdebug should write 
        # the return value of function calls to the trace files.
        // ini_set('xdebug.collect_return', true);

        # xdebug.trace_format
        # Human-readable traces. See documentation on site
        // ini_set('xdebug.trace_format', 0);

        # xdebug.trace_options
        # Type: integer, Default value: 0
        # When set to '1' the trace files will be appended to, instead of 
        # being overwritten in subsequent requests.
        // ini_set('xdebug.trace_options', 0 );

        # See documentation and setting in previous sections
        #   xdebug.collect_includes
        #   xdebug.collect_params
        #   xdebug.show_mem_delta
        #   xdebug.trace_output_dir
        #   xdebug.trace_output_name
        #   xdebug.var_display_max_children
        #   xdebug.var_display_max_data
        #   xdebug.var_display_max_depth



        error_log( "Starting up XDebug monitoring", E_USER_NOTICE );

        // (void) xdebug_enable
        // Enables display of stack traces on error conditions
        // xdebug_enable();

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
        // xdebug_start_trace(null, XDEBUG_TRACE_APPEND & XDEBUG_TRACE_HTML);

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
        // xdebug_start_code_coverage();

        if (   isset($_GET['debugphpinfo']) 
            && ( $_SERVER['HTTP_HOST'] == 'ccsa.local' )) {
            print phpinfo();
            exit;
        }
    }

    function __destruct() {
        error_log( "Shutting down XDebug monitoring", E_USER_NOTICE );

        // (void) xdebug_stop_trace()
        // Stops the current function trace
        // Stop tracing function calls and closes the trace file.
        // xdebug_stop_trace();

        // (void) xdebug_dump_superglobals
        // Displays information about super globals
        // This function dumps the values of the elements of the super 
        // globals as specified with the xdebug.dump.* php.ini settings.
        // echo "<h1>Superglobals of Interest</h1>";
        // xdebug_dump_superglobals();

        // (array) xdebug_get_declared_vars
        // Returns declared variables
        // Returns an array where each element is a variable name defined in
        // the current scope. Requires that xdebug.collect_vars is enabled. .
        // var_dump(xdebug_get_declared_vars());

        // (array) xdebug_get_code_coverage( )
        // Returns code coverage information
        // Returns a structure which contains information about which
        // lines were executed in your script (including include files).
        // var_dump(xdebug_get_code_coverage());

        parent::__destruct();
    }
} // END class SubRosa_DebuggingEnv


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