<?php

/**
 * Crissando CMS Core
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
 
include 'base.php';
 
/**
 * Crissando CMS Hook System
 *
 * @category   Crissando Core
 * @package    Crissando Engine
 * @author     Crissando Development <development@crissando.de>
 * @copyright  2012 Crissando Development
 *
 */
class CREvent extends CRBase {

	//@{ public static properties
	public static  $DEBUGLEVEL = 0;
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
	private function init() {
	    if(self::$initialized) return;
	    if(!is_array(CREvent::$statements)) CREvent::initdb();
	    $hooks = CRDB::execute('get_hooks')->fetchAll();
	    foreach( $hooks as $id => $hook ) {
	        CREvent::$hook['when']( $hook['for'], $hook['func'] );
	    }
        self::$initialized = true;
	}   // end function init()

	/**
	 * raise event; this handled registered hooks
	 * throws Exception if the registered callback does not exist
	 * @access public
	 * @return void
	 **/
	public static function raise() {
		self::init();
	    $args   = func_get_args();
	    $event  = array_shift($args);
	    $temp   = array_slice( debug_backtrace(), 1, 1 );
 	    $caller = ( isset($temp[0]['class']) && isset($temp[0]['function']) )
				? $temp[0]['class'].'::'.$temp[0]['function']
				: NULL;
        CRLogger::debug('raised event ['.$event.'] from ['.$caller.'] with args count ['.count($args).']');
	    if(isset(self::${$event}[$caller]) && is_array(self::${$event}[$caller])) {
			CRLogger::debug('registered callbacks',self::${$event}[$caller]);
	        foreach(self::${$event}[$caller] as $callback) {
	            CRLogger::debug(sprintf('callback [%s]',$callback));
                if(function_exists($callback)) {
					if(is_array($args) && count($args)) {
						$refs = array();
						foreach( $args as $arg ) {
						    $refs[] = &$arg;
						}
						//$callback($args[0]);
						$ret = call_user_func_array($callback,$refs);
						if($ret===FALSE) return false;
					}
					else {
						$ret = call_user_func($callback);
						if($ret===FALSE) return false;
					}
				}
				else {
				    CRLogger::alert(sprintf('no such callback: [%s]',$callback));
				    throw new Exception('callback function ['.$callback.'] does not exist');
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
 * Crissando CMS CRLogger
 *
 * @category   Crissando Core
 * @package    Crissando Engine
 * @author     Crissando Development <development@crissando.de>
 * @copyright  2012 Crissando Development
 *
 */
 
class CRLogger extends CRBase {

	//{@
	const EMERG  = 0;  // Emergency: system is unusable
    const ALERT  = 1;  // Alert: action must be taken immediately
    const CRIT   = 2;  // Critical: critical conditions
    const ERR    = 3;  // Error: error conditions
    const WARN   = 4;  // Warning: warning conditions
    const NOTICE = 5;  // Notice: normal but significant condition
    const INFO   = 6;  // Informational: informational messages
    const DEBUG  = 9;  // Debug: debug messages
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
	
	private static $handles;
	
	/**
	 * catch static function calls to unknown functions
	 **/
	public static function __callStatic($static,array $args) {
	    if( in_array(strtolower($static),array('emerg','alert','crit','err','warn','notice','info')) ) {
	        return self::log(array_shift($args),constant('CRLogger::'.strtoupper($static)),$args);
	    }
	    else {
	        self::error( '500', 'No such log level: ['.$static.']', true );
		}
	}   // end function __callStatic()
	
	public static function debug($msg,$args=NULL) {
	    $trace = debug_backtrace(FALSE);
	    $class = ( isset($trace[1]['class']) ? $trace[1]['class'] : NULL );
	    if(!$class || $class::$DEBUGLEVEL<9){return;}
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
        array_shift($trace);
        array_shift($trace);
       	$caller   = array_shift($trace);
        if( trim(strtolower($caller['class'])) === 'crlogger' ) {
            $caller   = array_shift($trace);
		}
		$logpath = self::path(self::get('GLOBALS.log_path',dirname(__FILE__)));
		$mode    = 'a+';
		if($level==9) {
		    $filename = 'debug';
		    $mode     = 'w';
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
			trigger_error('Unable to lock log file');
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
		$file = $caller['file'];
		$file = str_ireplace( array(self::path(self::get('GLOBALS.engine_path'))), array('[engine_path]'), self::path($file) );
		fwrite(
			self::$handles[$filename],
			date('r').' ['.$_SERVER['REMOTE_ADDR'].'] '.
				$file.' ('.
				$caller['line'].') '.'['.
				( ( isset($caller['class']) && $caller['class'] != '' ) ? $caller['class'].'::' : '' ) .
				( ( isset($caller['function']) && $caller['function'] != '' ) ? $caller['function'].'()' : '' ) .
				'] '.
				$msg."\n"
		);
		if($args&&$level==9) fwrite(self::$handles[$filename],print_r($args,1));
		flock(self::$handles[$filename],LOCK_UN);
	}   // end function log()
	
}   // --- END CLASS CRLOGGER -----

/**
 * Crissando CMS Core
 *
 * @category   Crissando Core
 * @package    Crissando Engine
 * @author     Crissando Development <development@crissando.de>
 * @copyright  2012 Crissando Development
 * 
 */
if ( ! class_exists( 'CRCore', false ) ) {
	class CRCore extends CRBase {

        //@{ public static properties
		public static $DEBUGLEVEL = 0;
		public static $PATH       = NULL;
		//@}

		/**
		 * Proxy for framework methods
		 *
		 * @access public
		 * @param  string  $func - called function
		 * @param  array   $args - function arguments
		 * @return mixed
		 **/
		function __call($func,array $args) {
			CRLogger::debug(sprintf("trying to call func [%s] with args:",$func),$args);
			return call_user_func_array('CRCore::'.$func,$args);
		}   // end function __call()
		
		function __get($v) {
echo "trying to get: --$v--<br />";
		}   // end function __get()

		/**
		 * autoloader
		 *
		 * @access public
		 * @param  string  $class
		 * @return void
		 **/
	    public static function autoload($class) {
	        $paths = self::get('INCPATHS');
	        $paths = ( is_array($paths) ? array_merge(array(dirname(__FILE__)),$paths) : array(dirname(__FILE__)) );
	        foreach($paths as $path) {
	            // orig. class name
	            if (file_exists($path.DIRECTORY_SEPARATOR.strtolower($class).'.php')) {
					return require $path.DIRECTORY_SEPARATOR.strtolower($class).'.php';
				}
				// class name without leading 'CR'
				$class = str_ireplace('CR','',$class);
				if (file_exists($path.DIRECTORY_SEPARATOR.strtolower($class).'.php')) {
					return require $path.DIRECTORY_SEPARATOR.strtolower($class).'.php';
				}
			}
	    }   // end function autoload()

		/**
		 * load config
		 *
		 * @access public
		 * @param  string  $file
		 * @return void
		 **/
		public static function config($file) {
		    $config = parent::config($file);
			// set globals; this maps [<sectionname>] to <functionname>
			$plan   = array('routes'=>'route','globals'=>'set','debug'=>'debug','incpaths'=>'incpaths');
			ob_start();
			foreach ($config as $sec=>$pairs){
				if (isset($plan[$sec])){
					foreach ($pairs as $key=>$val){
						echo 'self::'.$plan[$sec].'(\''.
							$key.'\','.
							(is_array($val) && $sec!='globals'?
								self::csv($val):self::stringify($val)).');'.
							"\n";
					}
				}
			}
			$save=ob_get_clean();
			eval($save);
		}   // end function config()

	    /**
	     * Dispatcher - resolve path to page
	     *
	     * @access public
	     * @return mixed
	     **/
		public static function dispatch(){
		    CREvent::raise('before');     // raise before event
		    // Process routes
			if (!isset(self::$vars['ROUTES']) || !self::$vars['ROUTES']) {
				CRLogger::emerg('no routes!');
				self::error('500','No routes!');
			}
			$path =   isset($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO']
				  : ( isset($_SERVER['PATH_INFO'])      ? $_SERVER['PATH_INFO']      : '/' );
			// paths having a valid suffix are treated as pages
			if (pathinfo($path,PATHINFO_EXTENSION)){
			    if (!in_array(pathinfo($path,PATHINFO_EXTENSION),self::get('GLOBALS.suffixes'))){
			        self::error('404','invalid suffix');
				}
				$path = str_replace('.'.pathinfo($path,PATHINFO_EXTENSION),'',$path);
				// --- TODO: static page ---
			}
			CRCore::$PATH = $path;
			CREvent::raise('on');        // raise on event for callbacks
			CRLogger::debug(sprintf('dispatching path [%s]',CRCore::$PATH));
			// hand over to page handler
			$page = new CRPage($path);
			$page->show($path);
			CREvent::raise('after');     // raise after event
		}   // end function dispatch()

		/**
		 * Assign handler to route pattern
		 *
		 * @access public
		 * @param  string  $pattern
		 * @param  mixed   $funcs mixed
		 * @param  int     $ttl
		 * @param  int     $throttle
		 * @param  boolean $hotlink
		**/
		public static function route($pattern,$funcs,$ttl=0,$throttle=0,$hotlink=TRUE) {
			list($methods,$uri)=
				preg_split('/\s+/',$pattern,2,PREG_SPLIT_NO_EMPTY);
			foreach (self::split($methods) as $method) {
			    if ( ! isset(self::$vars['ROUTES'][$uri]) ) self::$vars['ROUTES'][$uri] = array();
				// Use pattern and HTTP methods as route indexes
				self::$vars['ROUTES'][$uri][strtoupper($method)]=
					// Save handler, cache timeout and hotlink permission
					array($funcs,$ttl,$throttle,$hotlink);
			}
		}   // end function route()

	}
	
}   // ----- END CLASS CRCORE ----- //

/**
 * Crissando CMS - Template accessor
 *
 * @category   Crissando Core
 * @package    Crissando Engine
 * @author     Crissando Development <development@crissando.de>
 * @copyright  2012 Crissando Development
 *
 */

class CRTemplate extends CRCore {

	/**
	 *
	 *
	 *
	 *
	 **/
	public static function view($tpl,$data = array()){
	    if ( pathinfo(self::path(self::get('GLOBALS.template_path').'/'.self::get('GLOBALS.template').'/'.$tpl),PATHINFO_EXTENSION)=='php' ) {
	    	ob_start();
	        include self::path(self::get('GLOBALS.template_path').'/'.self::get('GLOBALS.template').'/'.$tpl);
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
			    self::get('GLOBALS.engine_path'),
				self::get('GLOBALS.library_path'),
				'Dwoo',
				'DwooWrapper.php'
			)));
	    $dwoo = new DwooWrapper(array('compile_path'=>self::path('./temp'),'cache_path'=>self::path('./temp')));
	    $dwoo->setPath( self::path(self::get('GLOBALS.template_path').'/'.self::get('GLOBALS.template')) );
	    $dwoo->setGlobals(
	        array(
	            'SITE_URL' => SITE_URL,
			)
		);
		ob_start();
	    $dwoo->get($tpl,$data);
	    $output = ob_get_clean();
		return $output;
	}   // end function dwoo()

}   // ----- END CLASS CRTEMPLATE -----

/**
 * Crissando CMS - Database abstraction layer
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

	//@{ private static properties
	private static $instances = array();
	private static $prepared  = array();
	//@}

	public static function execute($name,$params=NULL) {
	    if (!isset(self::$prepared[$name])){
			throw new Exception('no such query: '.$name.'!');
			return;
		}
		try {
		    if(!is_array($params) && $params!='') $params=array($params);
		    CRLogger::debug(sprintf('executing query [%s]',self::interpolateQuery(self::$prepared[$name]->queryString,$params)));
	        self::$prepared[$name]->execute($params);
		    return self::$prepared[$name];
		}
		catch( Exception $e ) {
		    echo $e->getMessage();
		}
	}   // end function execute()

	/**
	 * create an instance, i.e., database connection
	 * @access private
	 * @param  string  $connection - connection name
	 * @return object
	 **/
	private static function getInstance($connection='default') {
        if ( !array_key_exists( $connection, self::$instances ) ) {
            $engine = self::get('GLOBALS.database_engine','MySQL');
            self::$instances[$connection] = $engine::connect();
        }
        return self::$instances[$connection];
    }   // end function getInstance()

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
    public static function interpolateQuery($query, $params) {
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
	    if (!self::$pdo)
			self::$pdo = self::getInstance();
	    if ( ! $name || $name == '' )
	        $name = self::randomstring();
	    // replace %prefix%
	    $query = str_ireplace( '%prefix%', self::get('GLOBALS.database_prefix',''), $query );
		// prepare
		try {
	    	self::$prepared[$name] = self::$pdo->prepare( $query );
	    	self::$prepared[$name]->setFetchMode(PDO::FETCH_ASSOC);
	    	return $name;
		}
		catch( Exception $e )
		{
		    echo $e->getMessage();
		}
	}   // end function registerQuery()

	public static function query() {
	    if (!self::$pdo)
			self::$pdo = self::getInstance();
	}   // end function query()

}   // ----- END CLASS CRDB -----

class MySQL extends CRDB {

    //@{ private static properties
	private static $default_port = 3306;
	private static $pdo_driver   = 'mysql';
	private static $dsn;
	//@}

	/**
	 * connect to CRDB
	 * This always uses UTF-8!
	 **/
	public static function connect() {
		// create dsn
		self::$dsn = self::$pdo_driver.':host='.self::get('GLOBALS.database_host','localhost')
			. ';dbname='.self::get('GLOBALS.database_dbname','crissando')
			. ';port='.self::get('GLOBALS.database_port',self::$default_port);
		try {
	        if ( ! self::get('GLOBALS.database_password') ) {
	            return new PDO ( self::$dsn, self::get('GLOBALS.database_user'), array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8") );
	        }
	        else {
	        	return new PDO ( self::$dsn, self::get('GLOBALS.database_user'), self::get('GLOBALS.database_password'), array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8") );
	        }
		}
		catch( Exception $e ) {
		    echo $e->getMessage();
		}
	}
	
}   // ----- END CLASS MYSQL -----
