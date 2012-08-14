<?php

defined('CR_INIT') or die ('Access denied');

/**
 * Crissando CMS Engine Core
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


// ----- THIS IS FOR DEVELOPMENT ONLY! -----
//include_once dirname(__FILE__).'/helper/class.timer.php';
// ----- THIS IS FOR DEVELOPMENT ONLY! -----

class CRBase {

    const
        AUTH_GUEST=0,
        AUTH_BACKEND=1,
        AUTH_ADMIN=2;

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

    private   static $paths;
    private   static $DEBUGLEVEL = 0;
    //protected static $AUTHLEVEL  = 'guest';
    protected static $vars       = array('ROUTES'=>array(),'GLOBALS'=>array(),'DEBUG'=>array());

    /**
     * Default getInstance method; always creates a new instance of the current
     * class!
     **/
    public static function getInstance() {
        return new self();
    }   // end function getInstance()

	/**
	 * autoloader
	 *
	 * @access public
	 * @param  string  $class
	 * @return void
	 **/
    public static function autoload($class) {
        if(!self::$paths) {
            foreach(array('engine_path','library_path','helper_path','module_path') as $key) {
                $dir = self::path(self::get('GLOBALS.'.$key));
                if($dir) self::$paths[] = $dir;
            }
        }
        foreach(self::$paths as $path) {
            $file = self::path($path.'/'.strtolower($class).'.php');
            if (file_exists($file)) {
				return require $file;
			}
		}
    }   // end function autoload()

	/**
	 * Get stored globals
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
		if(isset(self::$vars[$var])) {                // 'GLOBAL', 'DATABASE',...
        	$pointer =& self::$vars[$var];
        	if( isset($path) && is_array($path) ) {
				while($path) {
				    $item = trim( array_shift($path) );
					if (isset($pointer[$item])) {
						$pointer =& $pointer[$item];
					}
                    elseif ( preg_match( '~\*$~', $item ) ) {
                        $return = array();
                        foreach( $pointer as $key => $ignore ) {
                            if ( preg_match( '~^'.$item.'~i', $key ) ) {
                                $return[str_replace(str_replace('*','',$item),'',$key)] = $pointer[$key];
                            }
                        }
                        return $return;
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
	 * set global var
	 *
	 * @access public
	 * @param  string  $key - var (key) name
	 * @param  mixed   $val - value
	 * @return void
	 *
	 **/
	public static function set($key,$val,$globalkey='GLOBALS'){
	    if(strtoupper($globalkey)=='DEBUG') {
            if(class_exists($key)) {
                $key::$DEBUGLEVEL = ( $val ? 9 : 0 );
	            return;
            }
		}
	    $val = is_scalar($val) ? self::split($val) : $val;
	    if(!$val){$val=array(false);}
	    self::$vars[strtoupper($globalkey)][$key]=(count($val)>1)?$val:$val[0];
	}   // end function set()

    /**
     *
     *
     *
     *
     **/
    public static function clear($key) {
        if(substr_count($key,'.'))
	    	$path = explode('.',$key);
        $path = isset($path) ? $path : array($key);
        eval( 'unset( self::$vars["'.implode('"]["',$path).'"] );' );
    }

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
	        self::path(self::get('GLOBALS.engine_path').'/config/'.self::get('GLOBALS.database_engine','MySQL').'/'.strtolower($class).'.cfg'),
	        self::path(self::get('GLOBALS.engine_path').'/config/'.self::get('GLOBALS.database_engine','MySQL').'/'.strtolower(str_ireplace('CR','',$class)).'.cfg'),
		);
	    foreach( $files as $filename ) {
		    if ( file_exists($filename) ) {
		    	$config = self::readconfig($filename);
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
	 * prints a var_dump() for the given registry key $key; dumps complete
	 * registry if no $key is given
	 *
	 * @access public
	 * @param  string  $key - optional
	 * @return void
	 *
	 **/
	public static function dump($key=NULL) {
	    if($key) { var_dump(self::$vars[$key]); }
	    else     { var_dump(self::$vars);       }
	}   // end function dump()

    /**
	 * import INI-style config files
	 *
	 * @access public
	 * @param  string $filename - config file to load
	 * @return array
	 *
	 **/
	public static function readconfig($filename) {
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
    }   // end function readconfig()

	/**
	 * sanitize path (remove double //, fix relatives like ./../)
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
		$path  = preg_replace( '~/$~'        , ''                   , $path );
		$path  = str_replace ( '\\'   		 , DIRECTORY_SEPARATOR  , $path );
		$path  = preg_replace('~/\./~'		 , DIRECTORY_SEPARATOR  , $path );
		$parts = array();
		foreach ( explode(DIRECTORY_SEPARATOR, preg_replace('~/+~', DIRECTORY_SEPARATOR, $path)) as $part ) {
		    if ($part === ".." ) { // || $part == ''
		        array_pop($parts);
		    }
		    elseif ($part!="") {
		        $parts[] = $part;
		    }
            else {
                // not handled
            }
		}
		$new_path = implode(DIRECTORY_SEPARATOR, $parts);
		// windows
		if ( ! preg_match( '/^[a-z]\:/i', $new_path ) ) {
			$new_path = DIRECTORY_SEPARATOR . $new_path;
		}
		return $new_path;
	}   // end function path()

	/**
	 * sanitize URL (remove double //, fix relatives like ./../)
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

    /**
     * calculate md5 checksum for file
     **/
    public static function checksum($file) {
        $file   = self::path($file);
        if ( ! file_exists($file) ) return NULL;
        return sha1_file($file);
    }   // end function checksum

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
	 * Split pipe-, semi-colon, comma-separated string
	 *
	 * @access public
	 * @param  string  $str
	 * @return array
     * @author F3::Factory Bong Cosca <bong.cosca@yahoo.com>
	 **/
	public static function split($str) {
if(is_array($str)){
echo "<textarea cols=\"100\" rows=\"20\" style=\"width: 100%;\">";
print_r( $str );
print_r(debug_backtrace());
echo "</textarea>";
}
		return array_map('trim',
			preg_split('/[|;,]/',$str,0,PREG_SPLIT_NO_EMPTY));
	}   // end function split()

	/**
	 * custom error handler
	 * @access public
	 * @param  integer $code
	 * @param  string  $str       message to send to browser
	 * @param  string  $trigstr   message to send to error log
	 * @param  boolean $trace     send debug backtrace to browser yes/no
	 * @return void
	 **/
	public static function error($code,$str='',$trigstr='',$trace=false) {
	    $dbgtrace = NULL;
		$errfile  = implode('',file(self::path(self::get('GLOBALS.template_path').'/static/error.html')));
		if($trigstr && $trigstr!='') {
      		error_log(sprintf('Crissando error: %s',$trigstr));
		}
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

}   // ----- END CLASS CRBASE -----

/**
 * Crissando Engine Component Loader
 *
 * @category   Crissando Core
 * @package    Crissando Engine
 * @author     Crissando Development <development@crissando.de>
 * @copyright  2012 Crissando Development
 *
 */
class CRLoader extends CRBase {

    private   static $helper_instances     = array();
    private   static $controller_instances = array();
    public    static $DEBUGLEVEL           = 9;

    /**
     *
     **/
    public static function css($css) {
        CRLogger::debug(sprintf('trying to find CSS [%s]',$css));
        if(!preg_match('/\.css$/i',$css)) $css .= '.css';
        foreach( array( './', '/css' ) as $subdir ) {
            CRLogger::debug(sprintf('subdir [%s]',$subdir));
            foreach( array(SITE_PATH,'template_path','module_path','library_path') as $location ) {
                CRLogger::debug(sprintf('location [%s]',$location));
                switch($location) {
                    case 'template_path':
                        $dir = self::get('GLOBALS.'.$location).'/'.self::get('GLOBALS.template');
                        $css = str_replace(array('template_path',$dir),array('',''),$css);
                        CRLogger::debug(sprintf('resolved template_path to [%s]',$dir));
                        break;
                    case 'module_path':
                    case 'library_path':
                    case SITE_PATH:
                        $dir = self::get('GLOBALS.'.$location);
                        break;
                }
                CRLogger::debug(sprintf('dir1 [%s] dir2 [%s]',$dir.'/'.$subdir.'/'.$css,self::path($dir.'/'.$subdir.'/'.$css)));
                $file = self::path($dir.'/'.$subdir.'/'.$css);
                CRLogger::debug(sprintf('scanning for file [%s]',$file));
                if(file_exists($file)) {
                    CRLogger::debug('found!');
                    $content = self::resolve($file);
                    header("Content-type: text/css");
                    echo $content;
                    break;
                }
            }
        }
    }   // end function css()

    /**
     * resolve url()s in CSS files
     **/
    private static function resolve($file) {
        $content = implode('',file($file));
        $regex   = '~url\(([^\)].+?)\)~ismx';
        $workdir = pathinfo($file,PATHINFO_DIRNAME);
        preg_match_all( $regex, $content, $urls, PREG_SET_ORDER );
        if( is_array($urls) && count($urls) ) {
            foreach( $urls as $i => $item ) {
                $ext = pathinfo($urls[$i][1],PATHINFO_EXTENSION);
                switch($ext) {
                    case 'css':
                        $content = str_replace(
                            $urls[$i][1],
                            self::resolve(self::path($workdir.'/'.$urls[$i][1])),
                            $content
                        );
                        break;
                    default:
                        $content = str_replace(
                            $urls[$i][1],
                            self::helper('Images')->base64_encode_image(self::path($workdir.'/'.$urls[$i][1])),
                            $content
                        );
                }
            }
        }
        return $content;
    }   // end function resolve()

    /**
     *
     **/
    public static function js($js) {
        CRLogger::debug(sprintf('trying to find JS [%s]',$js));
        if(!preg_match('/\.js$/i',$js)) $js .= '.js';
        foreach( array( '.', '/js' ) as $subdir ) {
            CRLogger::debug(sprintf('subdir [%s]',$subdir));
            foreach( array(SITE_PATH,'template_path','module_path','library_path') as $location ) {
                CRLogger::debug(sprintf('location [%s]',$location));
                switch($location) {
                    case 'template_path':
                        $dir = self::get('GLOBALS.'.$location).'/'.self::get('GLOBALS.template');
                        break;
                    case 'module_path':
                    case 'library_path':
                    case SITE_PATH:
                        $dir = self::get('GLOBALS.'.$location);
                        break;
                }
                $file = self::path($dir.'/'.$subdir.'/'.$js);
                CRLogger::debug(sprintf('scanning for JS file [%s]',$file));
                if(file_exists($file)) {
                    CRLogger::debug('found!');
                    $content = implode('',file($file));
                    header("Content-type: text/javascript");
                    echo $content;
                    exit;
                }
                else {
                    CRLogger::debug('not found');
                }
            }
        }
        CRLogger::debug('file not found, nothing to send');
    }   // end function js()

    /**
     * Loads a helper
     *
     * This function is inspired by Concrete5, so thanks to
     * Andrew Embler <andrew@concrete5.org> for the idea!
     *
     * Please note: Helpers NEVER require authentication, so keep an eye on
     * what your helper classes do!
     *
     * A helper class MUST have a static method getInstance() to create an
     * instance.
     *
     * @access public
     * @param  string  $file - helper to load
     * @return object
     **/
    public static function helper($name,$args=NULL) {
        if(isset(self::$helper_instances[$name]) && is_object(self::$helper_instances[$name])) {
            return self::$helper_instances[$name];
        }
        $path = self::path(self::get('GLOBALS.helper_path'));
        foreach( array( $name, 'class.'.$name ) as $fname ) {
            $file = self::path($path.'/'.$fname.'.php');
            if( file_exists($file) ) {
                if( ! class_exists($name) ) include $file;
                self::$helper_instances[$name] = $name::getInstance($args);
                return self::$helper_instances[$name];
            }
        }
        return false;
    }   // end function helper()

    /**
     * Loads a controller
     *
     * This function is inspired by Concrete5, so thanks to
     * Andrew Embler <andrew@concrete5.org> for the idea!
     *
     * A controller class MUST have a static method getInstance() to create an
     * instance.
     *
     * @access public
     * @param  string  $file - controller to load
     * @return object
     **/
    public static function controller($name) {
        if(isset(self::$controller_instances[$name]) && is_object(self::$controller_instances[$name])) {
            CRLogger::debug(sprintf('returning existing instance for controller [%s]',$name));
            return self::$controller_instances[$name];
        }
        $path = self::path(self::get('GLOBALS.controller_path'));
        foreach( array( $name, 'class.'.$name ) as $fname ) {
            $file = self::path($path.'/'.$fname.'.php');
            if( file_exists($file) ) {
                if( ! class_exists($name) ) include $file;
                if( self::get('DEBUG.'.$name) ) $name::$DEBUGLEVEL = self::get('DEBUG.'.$name);
                self::$controller_instances[$name] = $name::getInstance();
                // get db files
                $name::initdb();
                return self::$controller_instances[$name];
            }
        }
        return false;
    }   // end function controller()

}   // ----- END CLASS CRLOADER -----


/**
 * Crissando Engine Hook/Event System
 *
 * @category   Crissando Core
 * @package    Crissando Engine
 * @author     Crissando Development <development@crissando.de>
 * @copyright  2012 Crissando Development
 *
 */
class CREvent extends CRBase {

	//@{ public static properties
	public    static $DEBUGLEVEL = 0;
	//@}

	//@{ private static properties
	private   static $hooks = array('before','on','after');
	private   static $before, $on, $after, $params;
	protected static $statements;
	private   static $initialized = false;
	//@}

	/**
	 * catch static function calls to unknown functions
	 **/
	public static function __callStatic($static,array $args) {
	    if ( in_array( $static, self::$hooks ) ) {
		    $event = array_shift($args);
		    $func  = array_shift($args);
		    CRLogger::debug('adding hook ['.$static.'] for ['.$event.'] by ['.$func.']');
		    if(!is_scalar($func))                return;
		    if(!isset(self::${$static}[$event])) self::${$static}[$event] = array();
		    if(count($args))                     self::$params[$func]     = $args;
		    array_push( self::${$static}[$event], $func );
		}
		else {
		    throw new Exception( 'no such hook: '.$static );
		}
	}   // end function before()

	/**
	 *
	 **/
	private static function init() {
	    if(self::$initialized) return;
	    if(!is_array(CREvent::$statements)) CREvent::initdb();
	    $hooks = CRDB::execute('get_hooks',array('id1'=>1,'id2'=>(defined('VENDOR')?VENDOR:1)))->fetchAll();
	    foreach( $hooks as $id => $hook ) {
	        CREvent::$hook['when']( $hook['for'], $hook['func'], $hook['args'] );
	    }
        self::$initialized = true;
	}   // end function init()

	/**
	 * raise event; this handled registered hooks
	 * throws Exception if the registered callback does not exist
	 * @access public
	 * @return void
	 **/
	public static function raise($event,&$args=NULL) {
		self::init();
	    $temp   = array_slice( debug_backtrace(), 1, 1 );
 	    $caller = ( isset($temp[0]['class']) && isset($temp[0]['function']) )
				? $temp[0]['class'].'::'.$temp[0]['function']
				: NULL;
        CRLogger::debug(sprintf('raised event [%s] from [%s] with args count [%s]',$event,$caller,count($args)));
	    if(isset(self::${$event}[$caller]) && is_array(self::${$event}[$caller])) {
			CRLogger::debug('registered callbacks',self::${$event}[$caller]);
	        foreach(self::${$event}[$caller] as $callback) {
	            CRLogger::debug(sprintf('callback [%s]',$callback));
                try {
                    if(function_exists($callback)) {
                        if(!$args) {
                            CRLogger::debug(sprintf('executing callback [%s] WITHOUT args',$callback));
    						$r=call_user_func($callback);
    						if($r===FALSE){
    							CRLogger::alert(sprintf('callback [%s] failed!',$callback));
    						}
    					}
                        else {
                            CRLogger::debug(sprintf('executing callback [%s] WITH args',$callback),array(&$args));
    						$r=call_user_func_array($callback,array(&$args));
    						if($r===FALSE){
    							CRLogger::alert(sprintf('callback [%s] failed!',$callback));
    						}
    					}
    				}
    				else {
    				    CRLogger::alert(sprintf('no such callback: [%s]',$callback));
    				    throw new Exception('callback function ['.$callback.'] does not exist');
    				}
                }
                catch( Exception $e) {
                    CRBase::error('500','Internal server error',$e->getMessage());
                }
			}
		}
		else {
			CRLogger::debug('no registered callbacks');
			return false;
		}
	}

}   // ----- END CLASS CREVENT -----

/**
 * Crissando Engine Logger
 *
 * @category   Crissando Core
 * @package    Crissando Engine
 * @author     Crissando Development <development@crissando.de>
 * @copyright  2012 Crissando Development
 *
 */

class CRLogger extends CRBase {

    public    static $DEBUGLEVEL = 0;

	//{@
	const EMERG  = 0;  // Emergency: system is unusable
    const ALERT  = 1;  // Alert: action must be taken immediately
    const CRIT   = 2;  // Critical: critical conditions
    const ERR    = 3;  // Error: error conditions
    const WARN   = 4;  // Warning: warning conditions
    const NOTICE = 5;  // Notice: normal but significant condition
    const INFO   = 6;  // Informational: informational messages
    const DEBUG  = 9;  // Debug: debug messages
    const APPEND = true;
	//@}

	private static $num_to_text = array(
	    0 => 'EMERG ',
	    1 => 'ALERT ',
	    2 => 'CRIT  ',
	    3 => 'ERR   ',
	    4 => 'WARN  ',
		5 => 'NOTICE',
		6 => 'INFO  ',
	    9 => 'DEBUG ',
	);

    private static $starsfor = array( '.*pw.*', '.*pass.*' );
    private static $files    = array();
	private static $handles;

	/**
	 * catch static function calls to unknown functions
	 **/
	public static function __callStatic($static,array $args) {
	    if( in_array(strtolower($static),array('emerg','alert','crit','err','warn','notice','info')) ) {
			// always debug
			self::debug($args);
	        return self::log(array_shift($args),constant('CRLogger::'.strtoupper($static)),$args);
	    }
	    else {
	        CRBase::error( '500', 'No such log level: ['.$static.']', NULL, true );
		}
	}   // end function __callStatic()

    /**
     * set debug level for non-classes
     **/
    public static function setLevel( $file, $level ) {
        self::$files[$file] = $level;
    }   // end function setLevel()

	/**
	 * special debug method for this is widely used
	 **/
	public static function debug($msg,$args=NULL) {
	    $trace = debug_backtrace(FALSE);
	    $class = ( isset($trace[1]['class']) ? $trace[1]['class'] : NULL );
        if( !$class && isset(self::$files[$trace[0]['file']]) && self::$files[$trace[0]['file']] == 9 )
            return self::log($msg,9,$args);
	    if(!$class || $class=='CRLogger' || $class::$DEBUGLEVEL<9)
            return;
	    return self::log($msg,9,$args);
	}   // end function debug()

	/**
	 * save the log message to the appropriate file
	 *
	 * @access private
	 * @param  string   $msg   - log message
	 * @param  int      $level - log level
	 * @param  mixed    $args  - additional args
	 **/
    private static function log($msg,$level=0,$args) {
        if($level=='') return;
        $trace    = debug_backtrace(FALSE);
        $cfile    = NULL;
        $cline    = NULL;
        while( ( $caller = array_shift($trace) ) !== false ) {
            if( isset($caller['class']) && trim(strtolower($caller['class'])) === 'crlogger' ) {
                $cfile  = $caller['file'];
                $cline  = $caller['line'];
                $caller = array_shift($trace);
                continue;
            }
            break;
		}
		$logpath = self::path(self::get('GLOBALS.log_path',dirname(__FILE__)));
		$mode    = ( CRLogger::APPEND ? 'a+' : 'w+' );
		if($level==9) {
		    $filename = 'debug';
		    $mode     = ( CRLogger::APPEND ? 'a+' : 'w' );
  		}
  		else {
        	$filename = trim(strtolower(self::$num_to_text[$level]));
		}
        // get handle or create one
        if(!isset(self::$handles[$filename])) {
            self::$handles[$filename] = fopen($logpath.'/'.$filename.'.log',$mode);
        }
        else {
            $temp    = stream_get_meta_data(self::$handles[$filename]);
            $logpath = pathinfo( $temp['uri'], PATHINFO_DIRNAME );
		}
        // lock file
		if (!flock(self::$handles[$filename],LOCK_EX|LOCK_NB)) {
			#trigger_error('Unable to lock log file');
			return;
		}
		// rotate
		clearstatcache();
		if ( filesize($logpath.'/'.$filename.'.log') > self::bytes(self::get('GLOBALS.log_size','1M'))) {
			// Perform log rotation sequence
			if (is_file($logpath.'/'.$filename.'.log'.'.1'))
				copy($logpath.'/'.$filename.'.log'.'.1',$logpath.'/'.$filename.'.log'.'.2');
			copy($logpath.'/'.$filename.'.log',$logpath.'/'.$filename.'.log'.'.1');
			ftruncate(self::$handles[$filename],0);
		}
		// write message to log
		$file     = isset($caller['file']) ? $caller['file'] : $cfile;
        $file     = str_ireplace( array(self::path(BASEDIR)), array('[path]'), self::path($file) );
        $fileinfo = $file.' ('.( isset($caller['line']) ? $caller['line'] : $cline ).') ';
		fwrite(
			self::$handles[$filename],
#			date('r').' ['.$_SERVER['REMOTE_ADDR'].'] '.
				$fileinfo.
                ( (50-strlen($fileinfo)) > 1 ? str_repeat(' ',(50-strlen($fileinfo))) : ' ' ).
                '['.
				( ( isset($caller['class']) && $caller['class'] != '' ) ? $caller['class'].'::' : '' ) .
				( ( isset($caller['function']) && $caller['function'] != '' ) ? $caller['function'].'()' : '' ) .
				'] '.
				$msg."\n"
		);
		if($args) {
            if(!is_array($args)) $args = array($args);
            // make sure we do not log critical data
            foreach( $args as $i => $value ) {
                foreach( self::$starsfor as $item ) {
                    if ( preg_match( '~'.$item.'~i', $i ) ) {
                        $args[$i] = '*****';
                    }
                }
            }
            fwrite(self::$handles[$filename],var_export($args,1));
        }
		flock(self::$handles[$filename],LOCK_UN);
	}   // end function log()

}   // --- END CLASS CRLOGGER -----

/**
 * Crissando Engine Core
 *
 * @category   Crissando Core
 * @package    Crissando Engine
 * @author     Crissando Development <development@crissando.de>
 * @copyright  2012 Crissando Development
 *
 */
class CRCore extends CRBase {

	public  static $DEBUGLEVEL = 0;
    private static $acl;
    private static $route;
    private static $lang;
    public  static $user;

    /**
     * shortcut accessor to language helper class
     **/
    public static function t($msg,$data=array()) {
        CRLogger::debug(sprintf('translate [%s]',$msg));
        if ( !self::$lang || !is_object(self::$lang) ) {
            self::$lang = CRLoader::helper('Lang',self::get('GLOBALS.default_language'));
            self::$lang->addFile(self::get('GLOBALS.default_language').'.php',self::path(self::get('GLOBALS.languages_path')));
        }
        return self::$lang->translate($msg,$data);
    }   // function t()

	/**
	 *
	 *
	 *
	 *
	 **/
	public static function run() {
        // check index.php consistency
        $index_file    = self::path(SITE_PATH.'/index.php');
        $checksum      = CRDB::execute('checksum',array('site_id'=>SITE_ID))->fetch();
        $file_checksum = self::checksum($index_file);
        if( $checksum['checksum'] != $file_checksum ) {
            CRLogger::alert(
                sprintf('Found checksum error for file [%s]! (found [%s] required [%s] modification time [%s]',
                    $index_file,
                    $file_checksum,
                    $checksum['checksum'],
                    date('c',filemtime($index_file))
                ));
        }
        if (!isset($_SESSION)) session_start();
        // get route
	    self::$route
            = isset($_SERVER['ORIG_PATH_INFO'])
            ? $_SERVER['ORIG_PATH_INFO']
            : ( isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/' );
        // pre-dispatch: load ACLs
        self::$acl = CRLoader::helper('Auth');
        self::$acl->loadconfig(self::path(self::get('GLOBALS.engine_path').'/config/acls.cfg'));
        // resolve route
        $cfg   = self::dispatch(self::$route);
        // check permissions for each item on the stack
        $stack = self::permissions($cfg);
        CRLogger::debug('serving stack',$stack);
        foreach( $stack as $item ) {
            list( $item, $method ) = explode('::',$item);
            // try to load controller
            if(!$ctr = CRLoader::controller($item)) {
                CRLogger::debug(sprintf('Unable to load controller [%s]',$item));
			    self::error('500','Application error',sprintf('failed to execute stack item [%s]',$item));
			}
            CRLogger::debug(sprintf('Successfully loaded controller [%s], call method [%s]',$item,$method));
            $ctr->$method();
        }
	}   // end function run()

	/**
     * Dispatcher - resolve path to page
     *
     * @access public
     * @return mixed
     **/
	public static function dispatch($route){
	    CREvent::raise('before',$route);
	    CREvent::raise('on',$route);
	    // there may be a vendor that has already resolved the route
	    if(!self::get('GLOBALS.page_id')) {
            if( ( $cfg = self::route_match($route) ) === false ) {
                $page = CRDB::execute('dispatch',array('route'=>$route))->fetch();
                if($page) {
                    self::set('page_id',$page['page_id']);
                    $cfg[] = 'CRPage::load';
                }
                else {
                    CRBase::error('404','Page not found');
                }
            }
        }
        // NOTE: default_controller may be an array! So $cfg['stack'] may be
        // a string or an array!
        if(!isset($cfg)) $cfg['stack'] = self::get('GLOBALS.default_controller');
        CREvent::raise('after',$route);
        return $cfg;
	}   // end function dispatch()

	/**
	 * load config
	 *
	 * @access public
	 * @param  string  $file
	 * @return void
	 **/
	public static function loadconfig($file) {
	    $config = self::readconfig($file);
	    if(is_array($config) && count($config)) {
	        foreach($config as $global => $items) {
	            foreach($items as $key => $value) {
                    #if( strtoupper($global) == 'ROUTES' ) {
                    #    self::route($key,$value);
                    #}
                    #else {
	            	    self::set($key,$value,$global);
                    #}
				}
	        }
	    }
	}   // end function loadconfig()

    /**
     * Checks the configured routes to match current path
     *
     * @access public
     * @param  string  $match - current path
     * @return mixed   array on match, void if not
     *
     **/
    public static function route_match($match) {
        global $ROUTES;
        include self::path(self::get('GLOBALS.engine_path').'/config/routes.inc.php');
        CRLogger::debug(sprintf('trying to match route [%s]',$match),$ROUTES);
        // Detailed routes get matched first
		krsort($ROUTES);
        foreach( $ROUTES as $route => $cfg ) {
            $regex = preg_replace( '~(/?)(\*)~x', '$1?(.$2)', $route );
            if (
                preg_match('~^'.$regex.'~i', $match, $matches )
            ) {
                CRLogger::debug(sprintf('matched route [%s], request method [%s]',$match,$_SERVER['REQUEST_METHOD']),$cfg);
                // check request method
                $regex = ( isset($cfg['methods']) ? implode('|',explode(',',$cfg['methods'])) : 'GET' );
                if ( $regex ) {
                    return ( preg_match( '~('.$regex.')~i', $_SERVER['REQUEST_METHOD'] ) !== false )
                        ? ( is_array($cfg) ? $cfg : $ROUTES[$cfg] )
                        : NULL;
                }
                return false;
            }
        }
        return false;
    }   // end function route_match()

    /**
     *
     *
     *
     **/
    public static function moduleinfo($name) {
        $info = CRDB::execute('module',array('name'=>$name))->fetch();
        if(!$info || !is_array($info)) return NULL;
        return $info;
    }   // end function moduleinfo()

    /**
     * global authentication handler
     **/
    public static function authenticate(array $cfg) {
        if(    ! isset($_REQUEST['login_name']) || $_REQUEST['login_name'] == ''
            || ! isset($_REQUEST['login_pass']) || $_REQUEST['login_pass'] == ''
        ) {
            CRLoader::controller('CRPage')->view_static(
                self::get('GLOBALS.template_path').'/static/login.html',
                array(
                    '%action%'   => SITE_URL.'/login.html',
                    '%route%'    => self::$route,
                    '%infotext%' => ( isset($cfg['info']) ? self::t($cfg['info']) : self::t('Please login to gain access to this resource') ),
                )
            );
        }
        else {
            $user = CRLoader::helper('Auth')->login('Crissando',$_REQUEST['login_name'],$_REQUEST['login_pass']);
            if ( is_array($user) && count($user) ) {
                // fill user object
                self::$user = CRLoader::controller('CRUser');
                self::$user->init($user);
                return true;
            }
            else {
                echo "login failed<br />";
                unset($_REQUEST['login_pass']);
                CRLoader::controller('CRPage')->view_static(
                    self::path(self::get('GLOBALS.template_path').'/static/login.html'),
                    array(
                        '%action%'   => SITE_URL.'/login.html',
                        '%route%'    => self::$route,
                        '%infotext%' => self::t('Login failed (unknown user or wrong credentials)'),
                    )
                );
                return false;
            }
        }
    }   // end function authenticate()

    /**
     *
     *
     *
     *
     **/
    private static function permissions($cfg) {
        $stack = array();
        if(isset($cfg['stack'])) {
            $stack = is_array($cfg['stack'])
                   ? $cfg['stack']
                   : self::split($cfg['stack']);
        }
        foreach( $stack as $item ) {
            list( $item, $method ) = explode('::',$item);
            // try to load controller
            if(!$ctr = CRLoader::controller($item)) {
                CRLogger::debug(sprintf('Unable to load controller [%s]',$item));
			    self::error('500','Application error',sprintf('failed to execute stack item [%s]',$item));
			}
            CRLogger::debug(sprintf('Successfully loaded controller [%s]',$item));
            if ( isset($cfg['auth']) && $cfg['auth'] ) {
                $role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
                CRLogger::debug(sprintf('Controller requires authentication, current user role [%s]',$role));
                if(method_exists($ctr,'getResourceId')) {
                    CRLogger::debug(sprintf('Controller resource ID [%s]',$ctr->getResourceId()));
                    if(!self::$acl->isAllowed($role,$ctr->getResourceId(),$method)) {
                        if( self::authenticate($cfg) ) {
                            // login okay, just go on
                            CRLogger::debug(sprintf('authentication granted for controller [%s]',$item));
                        }
                    }
                }
            }
        }
        return $stack;
    }   // end function permissions()

}

/**
 * Crissando Engine Template Engine Abstraction
 *
 * @category   Crissando Core
 * @package    Crissando Engine
 * @author     Crissando Development <development@crissando.de>
 * @copyright  2012 Crissando Development
 *
 */

class CRTemplate extends CRCore {

	public  static $DEBUGLEVEL = 0;
    private static $orig_path  = NULL;
    private static $path       = NULL;

    /**
     *
     *
     *
     *
     **/
    public static function setPath($path) {
        if(self::$path != '') self::$orig_path = self::$path;
        self::$path = $path;
    }   // end function setPath()

    /**
     *
     *
     *
     *
     **/
    public static function resetPath() {
        if(self::$orig_path != '') self::$path = self::$orig_path;
    }   // end function resetPath()

	/**
	 *
	 *
	 *
	 *
	 **/
	public static function view($tpl,$data = array()) {
        // no extension, search for file
	    if(!pathinfo($tpl,PATHINFO_EXTENSION)) {
            CRLogger::debug(sprintf('no extension, searching for file [%s].*',$tpl));
	        foreach(array('.php','.tpl','.lte') as $ext) {
                foreach(array(self::$path,self::get('GLOBALS.template_path').'/'.self::get('GLOBALS.template')) as $path ) {
    	            $find = self::path($path.'/'.$tpl.$ext);
    	            if(file_exists($find)) {
                        CRLogger::debug(sprintf('found [%s]',$find));
    					$tpl = $find;
    					break;
    				}
                }
	        }
	    }
	    else {
	        $tpl = self::path(self::get('GLOBALS.template_path').'/'.self::get('GLOBALS.template').'/'.$tpl);
	    }
	    if ( pathinfo($tpl,PATHINFO_EXTENSION)=='php' ) {
            CRLogger::debug('extension is .php, using include()');
	    	ob_start();
	        include $tpl;
	        $output = ob_get_clean();
			return $output;
		}
	    $engine = self::get('GLOBALS.template_engine');
	    if ( ! $engine ) $engine='dwoo';
		return self::$engine($tpl,$data);
	}   // end function view()

	/**
	 * Dwoo accessor
	 *
	 * @access protected
	 * @param  mixed     $tpl
	 * @param  array     $data
	 * @return string
	 *
	 **/
	protected static function dwoo($tpl,$data = array()) {
	    include self::path( implode(
			DIRECTORY_SEPARATOR,
			array(
				self::get('GLOBALS.library_path'),
				'Dwoo',
				'DwooWrapper.php'
			)));
	    $dwoo = new DwooWrapper(array('compile_path'=>self::path('./temp'),'cache_path'=>self::path('./temp')));
	    $dwoo->setPath( self::path(self::get('GLOBALS.template_path').'/'.self::get('GLOBALS.template')) );
	    $dwoo->setGlobals(
	        array(
	            'SITE_URL'    => SITE_URL,
	            'ENGINE_PATH' => self::get('GLOBALS.engine_path'),
			)
		);
	    $output = $dwoo->get($tpl,$data);
		return $output;
	}   // end function dwoo()

}   // ----- END CLASS CRTEMPLATE -----

/**
 * Crissando Engine Database Abstraction
 *
 * @category   Crissando Core
 * @package    Crissando Engine
 * @author     Crissando Development <development@crissando.de>
 * @copyright  2012 Crissando Development
 *
 */

class CRDB extends CRCore {

	//@{ public static properties
	public static  $pdo;
	public static  $DEBUGLEVEL = 0;
	//@}

    protected static $statements;

	//@{ private static properties
	private static $instances = array();
	private static $prepared  = array();
	private static $prefixes  = array();
    private static $current   = 'default';
    private static $last      = NULL;
	//@}

	/**
	 * allow methods to be called in object syntax
	 **/
	function __call($func,array $args) {
		CRLogger::debug(sprintf("trying to call func [%s] with args:",$func),$args);
		return call_user_func_array('CRDB::'.$func,$args);
	}   // end function __call()

    public static function init() {
        $default = self::get('DATABASE.default_*');
        if(!is_array($default) || !count($default)) self::error('500','Database error');
        CRDB::getInstance('default',$default);
        $vendor = self::get('DATABASE.vendor_*');
        if(is_array($vendor) && count($vendor)) {
            foreach( array_keys($default) as $key ) {
                if(!isset($vendor[$key])) $vendor[$key] = $default[$key];
            }
            CRDB::getInstance('vendor',$vendor);
            CRDB::setInstance('default');
        }
    }   // end static function init()

	/**
	 * Create an instance, i.e., database connection
	 * By giving an instance name, the class can handle more than one DB
	 * connection at the same time. To set up the current connection to use,
	 * use setInstance(<Name>)
	 *
	 * @access private
	 * @param  string  $instance - instance/connection name
	 * @return object
	 *
	 **/
	public static function getInstance($instance='default',$options=array()) {
        if ( !array_key_exists( $instance, self::$instances ) ) {
            CRLogger::debug(sprintf('creating instance [%s]',$instance),$options);
            $engine = self::get('GLOBALS.database_engine','MySQL');
            if(strtolower($engine)=='mysql') $engine = 'CRMySQL';
            self::$instances[$instance] = $engine::connect($options);
            if(isset($options['database_prefix']) && $options['database_prefix'] != '') {
                self::$prefixes[$instance] = $options['database_prefix'];
			}
            if(self::$DEBUGLEVEL==9) {
                self::$instances[$instance]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            self::initdb();
        }
        return self::$instances[$instance];
    }   // end function getInstance()

    /**
     * Set the name of the connection/instance to use; the connection must be
     * established using getInstance() before!
     *
     * @access public
     * @param  string   $instance - instance/connection name
     * @return void
     **/
    public static function setInstance($instance) {
        if ( $instance == self::$current ) return;
        CRLogger::debug(sprintf('setting instance [%s] (current [%s] last [%s])',$instance,self::$current,self::$last));
        if( !isset(self::$instances[$instance]) || !is_object(self::$instances[$instance]) ) {
            CRLogger::alert(sprintf('No such database instance: [%s]',$instance),debug_backtrace());
            self::error('500','Database error');
        }
        self::$last    = self::$current;
        self::$current = $instance;
        CRLogger::debug(sprintf('current now [%s] (last [%s])',self::$current,self::$last));
    }   // end function setInstance()

    /**
     * reset current instance to last instance (if any); can be used to reset
     * to default instance after calling setInstance()
     **/
    public static function resetInstance() {
        CRLogger::debug(sprintf('resetting instance to last [%s]',self::$last));
        if(isset(self::$last) && self::$last != '') {
            self::$current = self::$last;
            self::$last    = NULL;
        }
    }   // end function resetInstance()

    /**
     * clone an instance; needed for WB compatibility
     **/
    public static function cloneInstance($instance,$clone) {
        CRLogger::debug(sprintf('cloning instance [%s]',$instance));
        if( !isset(self::$instances[$instance]) || !is_object(self::$instances[$instance]) ) {
            CRLogger::alert(sprintf('No such database instance: [%s]',$instance),self::$instances);
            self::error('500','Database error');
        }
        self::$instances[$clone] =& self::$instances[$instance];
    }   // end function cloneInstance()

    /**
     * check if an instance exists
     **/
    public static function hasInstance($instance) {
        return ( isset(self::$instances[$instance])&&is_object(self::$instances[$instance]) )
            ? true
            : false;
    }   // end function hasInstance()

	/**
	 * executes a named query; this must be registered using registerQuery()
	 * before executing it
	 **/
	public static function execute($name,$params=NULL) {
        $instance = self::$current;
	    if (!isset(self::$prepared[$instance][$name])){
            CRLogger::err(sprintf('no such query [%s] for instance [%s]',$name,$instance),self::$prepared[$instance]);
			self::error('500',sprintf('no such query [%s] for instance [%s]',$name,$instance));
			exit;
		}
		try {
		    if(!is_array($params) && $params!='') $params=array($params);
		    CRLogger::debug(sprintf('executing query [%s] on instance [%s] -> [%s]',$name,$instance,self::interpolateQuery(self::$prepared[$instance][$name]->queryString,$params)));
	        $success = self::$prepared[$instance][$name]->execute($params);
            if(!$success) {
                CRLogger::err(
                    sprintf('Database error: The Statement [%s] failed, error info:',
                        self::interpolateQuery(self::$prepared[$instance][$name]->queryString,$params)
                    ),
                    self::$prepared[$instance][$name]->errorInfo()
                );
                self::error('500','Database Error');
            }
		    return self::$prepared[$instance][$name];
		}
		catch( Exception $e ) {
		    echo $e->getMessage();
		}
	}   // end function execute()

    /**
     * Replaces any parameter placeholders in a query with the value of that
     * parameter. Useful for debugging. Assumes anonymous parameters from
     * $params are are in the same order as specified in $query
     *
     * Source: http://stackoverflow.com/questions/210564/pdo-prepared-statements
     *
     * @param string $query The sql query with parameter placeholders
     * @param array $params The array of substitution parameters
     * @return string The interpolated query
     */
    public static function interpolateQuery($query,$params) {
        $keys = array();
        # build a regular expression for each parameter
        if ( is_array($params) ) {
            foreach ($params as $key => $value) {
                if (is_string($key)) {
                    $keys[] = '/:'.$key.'/';
                } else {
                    $keys[] = '/[?]/';
                }
            }
        }
        $query = preg_replace($keys, $params, $query, 1, $count);
        return $query;
    }   // end function interpolateQuery()

    /**
     * register query
     *
     * @access public
     * @param  string  $name  - query name for later use
     * @param  string  $query - statement
     * @return string  $name
     *
     **/
	public static function registerQuery($name=NULL,$query) {
        $instance = self::$current;
	    $pdo      = self::getInstance($instance);
	    if ( ! $name || $name == '' )
	        $name = self::randomstring();
	    // replace %prefix%
	    if ( ! isset(self::$prefixes[$instance]) || self::$prefixes[$instance] == '' )
	        self::$prefixes[$instance] = self::get('GLOBALS.default_database_prefix','');
	    $query = str_ireplace( '%prefix%', self::$prefixes[$instance], $query );
        // WB / LEPTON
        $query = str_ireplace( 'TABLE_PREFIX', self::$prefixes[$instance], $query );
		// prepare
		try {
	    	self::$prepared[$instance][$name] = $pdo->prepare( $query );
	    	self::$prepared[$instance][$name]->setFetchMode(PDO::FETCH_ASSOC);
	    	return $name;
		}
		catch( Exception $e )
		{
		    self::error( '500', $e->getMessage() );
		}
	}   // end function registerQuery()

    /**
     * clone (copy) a query between instances; returns false if the source
     * query does not exist or the current instance already has a query with
     * the same name.
     *
     * @access public
     * @param  string   $source_instance - instance name
     * @param  string   $name            - query to clone
     * @return boolean
     **/
    public static function cloneQuery( $source_instance, $name ) {
        $instance = self::$current;
        if ( isset(self::$prepared[$source_instance][$name]) && ! self::hasQuery($name) ) {
            self::registerQuery(
                $name,
                str_replace(
                    self::$prefixes[$source_instance],
                    self::$prefixes[$instance],
                    self::$prepared[$source_instance][$name]->queryString
                )
            );
            return true;
        }
        return false;
    }   // end function cloneQuery()

    /**
     * Check if a named query exists
     *
     * @access public
     * @return boolean
     **/
    public static function hasQuery($name) {
        $instance = self::$current;
        if ( isset( self::$prepared[$instance][$name] ) ) return true;
        return false;
    }   // end function hasQuery()

}   // ----- END CLASS CRDB -----

/**
 * Crissando Engine mySQL driver
 *
 * @category   Crissando Core
 * @package    Crissando Engine
 * @author     Crissando Development <development@crissando.de>
 * @copyright  2012 Crissando Development
 *
 */
class CRMySQL extends CRDB {

    //@{ private static properties
	private static $default_port = 3306;
	private static $pdo_driver   = 'mysql';
	private static $dsn;
	//@}

	/**
	 * Creates a mySQL DSN and establishes a DB connection using PDO
	 * This always uses UTF-8!
	 *
	 * @access public
	 * @param  array   $options - array containing DB connection infos
	 * @return object  PDO object
	 **/
	public static function connect($options) {
	    foreach(array('host','db','port','pw','user') as $opt) {
	        if(!isset($options['database_'.$opt])) $options['database_'.$opt] = self::get('DATABASE.default_database_'.$opt);
		}
		// create dsn
		self::$dsn = self::$pdo_driver
			. ':host='  .(isset($options['database_host']) ? $options['database_host'] : 'localhost')
			. ';dbname='.(isset($options['database_db'])   ? $options['database_db']   : 'crissando')
			. ';port='  .(isset($options['database_port']) ? $options['database_port'] : self::$default_port);
		try {
	        if ( ! isset($options['database_pw']) || $options['database_pw'] == ''  ) {
	            return new PDO ( self::$dsn, $options['database_user'], NULL, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8") );
	        }
	        else {
	        	return new PDO ( self::$dsn, $options['database_user'], $options['database_pw'], array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8") );
	        }
		}
		catch( Exception $e ) {
		    echo $e->getMessage();
		}
	}   // end function connect()

}   // ----- END CLASS CRMySQL -----

?>