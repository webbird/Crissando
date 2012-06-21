<?php

/**
 * Crissando CMS - Base class
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * @category   Crissando Core
 * @package    Crissando Engine
 * @author     Crissando Development <development@crissando.de>
 * @copyright  2012 Crissando Development
 * @license
 * @version    SVN: $Id$
 * @link       http://www.crissando.de
 * @since      File available since Release 1.0.0
 */

class CRBase {

	//@{ HTTP status codes (RFC 2616)
	const
		HTTP_100='Continue',
		HTTP_101='Switching Protocols',
		HTTP_200='OK',
		HTTP_201='Created',
		HTTP_202='Accepted',
		HTTP_203='Non-Authorative Information',
		HTTP_204='No Content',
		HTTP_205='Reset Content',
		HTTP_206='Partial Content',
		HTTP_300='Multiple Choices',
		HTTP_301='Moved Permanently',
		HTTP_302='Found',
		HTTP_303='See Other',
		HTTP_304='Not Modified',
		HTTP_305='Use Proxy',
		HTTP_307='Temporary Redirect',
		HTTP_400='Bad Request',
		HTTP_401='Unauthorized',
		HTTP_402='Payment Required',
		HTTP_403='Forbidden',
		HTTP_404='Not Found',
		HTTP_405='Method Not Allowed',
		HTTP_406='Not Acceptable',
		HTTP_407='Proxy Authentication Required',
		HTTP_408='Request Timeout',
		HTTP_409='Conflict',
		HTTP_410='Gone',
		HTTP_411='Length Required',
		HTTP_412='Precondition Failed',
		HTTP_413='Request Entity Too Large',
		HTTP_414='Request-URI Too Long',
		HTTP_415='Unsupported Media Type',
		HTTP_416='Requested Range Not Satisfiable',
		HTTP_417='Expectation Failed',
		HTTP_500='Internal Server Error',
		HTTP_501='Not Implemented',
		HTTP_502='Bad Gateway',
		HTTP_503='Service Unavailable',
		HTTP_504='Gateway Timeout',
		HTTP_505='HTTP Version Not Supported';
	//@}

	//@{ HTTP headers (RFC 2616)
	const
		HTTP_AcceptEnc='Accept-Encoding',
		HTTP_Agent='User-Agent',
		HTTP_Allow='Allow',
		HTTP_Cache='Cache-Control',
		HTTP_Connect='Connection',
		HTTP_Content='Content-Type',
		HTTP_Disposition='Content-Disposition',
		HTTP_Encoding='Content-Encoding',
		HTTP_Expires='Expires',
		HTTP_Host='Host',
		HTTP_IfMod='If-Modified-Since',
		HTTP_Keep='Keep-Alive',
		HTTP_LastMod='Last-Modified',
		HTTP_Length='Content-Length',
		HTTP_Location='Location',
		HTTP_Partial='Accept-Ranges',
		HTTP_Powered='X-Powered-By',
		HTTP_Pragma='Pragma',
		HTTP_Referer='Referer',
		HTTP_Transfer='Content-Transfer-Encoding',
		HTTP_WebAuth='WWW-Authenticate';
	//@}

	//@{ Global variables and references to constants
	protected static $vars = array('ROUTES'=>array(),'GLOBALS'=>array(),'INCPATHS'=>array(),'LOG'=>NULL);
	//@}

	/**
	 * catch static function calls to unknown functions
	 **/
	public static function __callStatic($func,array $args) {
		throw new Exception( 'no such static function: -'.$func.'-' );
	}   // end function __callStatic()

	/**
	 * Convert engineering-notated string to bytes
	 *
	 * @access public
	 * @param  string $str
	 * @return int
	 * @author F3::Factory Bong Cosca <bong.cosca@yahoo.com>
	**/
	public static function bytes($str) {
		$greek = 'KMGT';
		$exp   = strpbrk($str,$greek);
		return pow(1024,strpos($greek,$exp)+1)*(int)$str;
	}   // end function bytes()
	
    /**
	 * handle INI-style config files
	 *
	 * @access public
	 * @param  string $filename - config file to load
	 * @return array
	 *
	 **/
	public static function config($filename) {
	    if (!is_file($filename)) {
			// Configuration file not found
			trigger_error(sprintf('file not found: %s',$filename),E_USER_ERROR);
			return;
		}
        $p_ini   = parse_ini_file($filename, true);
        $config  = array();
        foreach( $p_ini as $namespace => $properties ) {
            $path    = explode( ':', $namespace );
            reset($config);
            $pointer =& $config;
			while( $path ) {
			    $item = trim( array_shift( $path ) );
				// create node; keep existing
				if ( ! isset($pointer[$item]) ) {
					$pointer[$item] = array();
				}
				// move pointer to new node
				$pointer =& $pointer[$item];
			}
			// add values; this also overwrites existing!
			foreach( $properties as $key => $value ) {
			    $pointer[$key] = $value;
			}
        }
        return $config;
    }   // end function config()

	/**
	 * Flatten array values and return as CSV string
	 * @access public
	 * @param  $arg mixed
	 * @return string
	 * @author F3::Factory Bong Cosca <bong.cosca@yahoo.com>
	**/
	public static function csv($args) {
		return implode(',',array_map('stripcslashes',
			array_map('self::stringify',$args)));
	}   // end function csv()

	/**
	 *
	 *
	 * @access public
	 *
	 **/
	public function do_eval($_x_codedata, $_x_varlist, &$content) {
		extract($_x_varlist, EXTR_SKIP);
		return(eval($_x_codedata));
	}   // end function do_eval()

	/**
	 * Get stored value
	 *
	 * @access public
	 * @param  string  $key - value to retrieve (example: GLOBALS.myglobal)
	 * @param  mixed   $default - optional default value if param is not set
	 * @return mixed
	 *
	 **/
	public static function get($key,$default=NULL) {
		if(substr_count($key,'.'))
	    	$path = explode('.',$key);
		$var  = ( isset($path) && is_array($path) ) ? array_shift($path) : $key;
		if(isset(self::$vars[$var])) {
        	$pointer =& self::$vars[$var];
        	if( isset($path) && is_array($path) ) {
				while($path) {
				    $item = trim( array_shift($path) );
					if (isset($pointer[$item])) {
						$pointer =& $pointer[$item];
					}
					else { // no such value
					    return ( isset($default) ? $default : NULL );
					}
				}
			}
		}
	    return ( isset($pointer) ? $pointer : ( isset($default) ? $default : NULL ) );
	}   // end function get()

	/**
	 * enable/disable debug
	 **/
	public static function debug($class,$enable) {
		if($enable) { $class::$DEBUGLEVEL = 9; }
		else        { $class::$DEBUGLEVEL = 0; }
	}   // end function debug()
	
	/**
	 * custom error handler
	 **/
	public static function error($code,$str='',$trace=false) {
	    $dbgtrace = NULL;
		$errfile  = implode('',file(dirname(__FILE__).'/config/error.html'));
		if($trace) {
		    $stack = debug_backtrace();
		    $temp  = array();
		    foreach($stack as $item) {
		        // filter "noise"
		        if( isset($item['function']) && preg_match('~(__callStatic|error)~', $item['function']) ) continue;
		        $temp[] = '<tr class="row'.( (count($temp)%2) ? 'a' : 'b' ).'"><td>'
						. (
							  isset($item['line'])
						    ? (urldecode($item['file']).':'.$item['line'].' ')
						    : ''
						  )
						. '</td><td>'
						. (
							  isset($item['function'])
							? (
								  (
								  	  isset($item['class'])
									? ($item['class'].$item['type'])
								    : ''
								  )
								  . $item['function'].'('.
								  	(
									    !preg_match('/{{.+}}/',$item['function']) && isset($item['args'])
								      ? (self::csv($item['args']))
									  : ''
									)
								  .')'
							  )
							: ''
						  )
						. '</td></tr>';
		    }
		    $dbgtrace = '<br /><br />'
					  . '<table><tr><th colspan="2">Trace</th></tr>'
					  . implode("\n",$temp)
					  . '</table><br /><br />';
		}
		if ( defined('self::HTTP_'.$code) ) {
		    $status = sprintf( '%s (%d)', constant('self::HTTP_'.$code), $code );
		}
		else {
		    $status = 'unknown error code ['.$code.']';
		}
		echo str_ireplace(
		    array( '%title%'        , '%header%'       , '%status%', '%content%', '%trace%', '%footer%' ),
		    array( 'CRISSANDO Error', 'Crissando Error', $status   , $str       , $dbgtrace, ''         ),
		    $errfile
		);
		exit();
	}   // end function error()
	
	/**
	 *
	 **/
	public static function incpaths($ignore,$paths=NULL) {
	    if(!$paths) return self::$vars['INCPATHS'];
	    if(!is_array($paths)&&is_scalar($paths)) {
	        $paths = array($paths);
		}
		if(is_array($paths)&&count($paths)) {
		    foreach($paths as $ignore => $path) {
		        self::$vars['INCPATHS'][] = self::path($path);
			}
		}
		// remove doubles
		array_unique(self::$vars['INCPATHS']);
	}   // end function incpaths()

	/**
	 * this looks for a file <classname>.cfg in the config directory and loads
	 * it if available; <classname> is converted to lowercase!
	 *
	 * @acceess public
	 * @return  array
	 **/
	public static function initdb() {
	    $class    = get_called_class();
	    $files    = array(
	        self::path(ENGINE_PATH.'/engine/config/'.self::get('GLOBALS.database_engine','MySQL').'/'.strtolower($class).'.cfg'),
	        self::path(ENGINE_PATH.'/engine/config/'.self::get('GLOBALS.database_engine','MySQL').'/'.strtolower(str_ireplace('CR','',$class)).'.cfg'),
		);
	    foreach( $files as $filename ) {
		    if ( file_exists($filename) ) {
		    	$config = self::config($filename);
				$class::$statements = $config['statements'];
				if ( is_array( $config['statements'] ) ) {
				    foreach( $config['statements'] as $name => $statement ) {
				        CRDB::registerQuery( $name, $statement );
				    }
				}
			}
		}
	}   // end function initdb()

	/**
	 * sanitize path
	 *
	 * @access public
	 * @param  string  $path
	 * @return string
	 *
	 **/
	public static function path($path){
        $path  = defined('BASEDIR')
			   ? preg_replace('~^\.(\\\.)?/~', BASEDIR.'/'          , $path )
			   : preg_replace('~^\.(\\\.)?/~', dirname(__FILE__).'/', $path );
		$path  = preg_replace( '~/$~'        , ''         , $path );
		$path  = str_replace ( '\\'   		, '/'        , $path );
		$path  = preg_replace('~/\./~'		, '/'        , $path );
		$parts = array();
		foreach ( explode('/', preg_replace('~/+~', '/', $path)) as $part ) {
		    if ($part === ".." || $part == '') {
		        array_pop($parts);
		    }
		    elseif ($part!="") {
		        $parts[] = $part;
		    }
		}
		$new_path = implode("/", $parts);
		// windows
		if ( ! preg_match( '/^[a-z]\:/i', $new_path ) ) {
			$new_path = '/' . $new_path;
		}
		return $new_path;
	}   // end function path()

	/**
	 * create a random string
	 *
	 * @access public
	 * @param  int    $length (default 5)
	 * @return string
	 **/
	public static function randomstring( $length = 5 ) {
        for(
           $code_length = $length, $newcode = '';
           strlen($newcode) < $code_length;
           $newcode .= chr(!rand(0, 2) ? rand(48, 57) : (!rand(0, 1) ? rand(65, 90) : rand(97, 122)))
        );
        return $newcode;
    }   // end function randomstring()

	/**
	 * set global var
	 *
	 * @access public
	 * @param  string  $key - var (key) name
	 * @param  mixed   $val - value
	 * @return void
	 *
	 **/
	public static function set($key,$val,$globalkey='GLOBALS'){
	    $val = is_scalar($val)
			 ? self::split($val)
			 : $val;
	    self::$vars[$globalkey][$key]=(count($val)>1)?$val:$val[0];
	}   // end function set()

	/**
	 * Split pipe-, semi-colon, comma-separated string
	 *
	 * @access public
	 * @param  string  $str
	 * @return array
     * @author F3::Factory Bong Cosca <bong.cosca@yahoo.com>
	 **/
	public static function split($str) {
		return array_map('trim',
			preg_split('/[|;,]/',$str,0,PREG_SPLIT_NO_EMPTY));
	}   // end function split()

	/**
	 * Convert PHP expression/value to compressed exportable string
	 *
	 * @access public
	 * @param  $arg mixed
	 * @return string
	 * @author F3::Factory Bong Cosca <bong.cosca@yahoo.com>
	 **/
	public static function stringify($arg) {
		switch (gettype($arg)) {
			case 'object':
				return method_exists($arg,'__tostring')?
					(string)stripslashes($arg):
					get_class($arg).'::__set_state()';
			case 'array':
				$str='';
				foreach ($arg as $key=>$val)
					$str.=($str?',':'').
						self::stringify($key).'=>'.self::stringify($val);
				return 'array('.$str.')';
			default:
				return var_export($arg,TRUE);
		}
	}   // end function stringify()

	/**
	 * sanitize URL
	 *
	 * @access public
	 * @param  string $href - URL to sanitize
	 * @return string
	 **/
	public static function url($href) {
        $rel_parsed = parse_url($href);
        $path       = $rel_parsed['path'];
        $path       = preg_replace('~/\./~', '/', $path); // bla/./bloo ==> bla/bloo
        $path       = preg_replace('~/$~', '', $path );   // remove trailing
        $parts      = array();                            // resolve /../
        foreach ( explode('/', preg_replace('~/+~', '/', $path)) as $part ) {
            if ($part === ".." || $part == '') {
                array_pop($parts);
            }
            elseif ($part!="") {
                $parts[] = $part;
            }
        }
        return
        (
              array_key_exists( 'scheme', $rel_parsed )
            ? $rel_parsed['scheme'] . '://' . $rel_parsed['host'] . ( isset($rel_parsed['port']) ? ':'.$rel_parsed['port'] : NULL )
            : ""
        ) . "/" . implode("/", $parts);
	}   // end function url()

}

?>