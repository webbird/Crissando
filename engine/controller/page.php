<?php

/**
 * Crissando CMS Engine - Page controller
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

class CRPage extends CRCore {

	public static    $DEBUGLEVEL   = 0;
	
    //@{ private static properties
    protected static $statements   = NULL;
    protected static $page_objects = array(); // already loaded page objects
    protected static $current      = NULL;    // holds the ID of the current page
    protected static $active       = array();
    //@}
    
    public function eval_droplets( &$content ) {
		$droplet_tags         = array();
		$droplet_replacements = array();
		if( preg_match_all( '/\[\[(.*?)\]\]/', $content, $found_droplets ) ) {
			foreach( $found_droplets[1] as $droplet ) {
				if(array_key_exists( '[['.$droplet.']]', $droplet_tags) == false) {
					$varlist        = array();
					$tmp            = preg_split('/\?/', $droplet, 2);
					$droplet_name   = $tmp[0];
					$request_string = (isset($tmp[1]) ? $tmp[1] : '');
					if( $request_string != '' ) {
						$request_string = html_entity_decode($request_string, ENT_COMPAT,DEFAULT_CHARSET);
						$argv = preg_split( '/&(?!amp;)/', $request_string );
						foreach ($argv as $argument){
							list( $variable, $value ) = explode('=', $argument,2);
							if( !empty($value) ){
								$varlist[$variable] = htmlentities($value, ENT_COMPAT,DEFAULT_CHARSET);
							}
						}
					} else {
						$droplet_name = $droplet;
					}
					$temp = CRDB::execute('get_droplets', array('name'=>$droplet_name))->fetch();
					$codedata = $temp['code'];
					if (!is_null($codedata)) {
						$newvalue = self::do_eval($codedata, $varlist, $content);
						if ($newvalue == '' && $newvalue !== true) {
							$newvalue = true;
						}
						if ($newvalue === true) { $newvalue = ""; }
						$newvalue = preg_replace('/<style.*>.*<\/style>/siU', '', $newvalue);
						$droplet_tags[]         = '[['.$droplet.']]';
						$droplet_replacements[] = $newvalue;
					}
				}
			}	// End foreach( $found_droplets[1] as $droplet )
			$content = str_replace($droplet_tags, $droplet_replacements, $content);
		}
		return $content;
	}   // end function eval_droplets()
    
    /**
     * check if a page exists
     *
     * @access public
     * @param  integer $id
     * @return boolean
     **/
	public function exists($id) {
  		return true;
	}   // end function exists()
	
    /**
     * get active blocks (aka sections) for page $page_id
     *
     * @access public
     * @param  integer $page
     * @return array
     **/
    public function get_active_blocks($id) {
        CRLogger::debug(sprintf('getting number of active blocks for page [%s]',$id));
	    if(!isset(self::$page_objects[$id])) {
	        CRLogger::debug('creating page object');
		    self::$page_objects[$id] = CRPageObj::getInstance($id);
		}
		return self::$page_objects[$id]->sections;
    }   // end function get_active_blocks()
    
	/**
	 *
	 *
	 *
	 *
	 **/
	public function get_block($block=1,$print=true) {
		global $database; // this is for WB/LEPTON
	    if (!is_numeric($block))  $block = 1;                    // default block
	    #if (!self::get_current()) self::get_page_properties(-1); // get root page
	    $sections = CRPage::get_sections( PAGE_ID, $block );     // get active get_sections
	    CRLogger::debug(sprintf('block_id [%d] page_id [%d] section count [%d]',$block,PAGE_ID,count($sections)));
	    if(!is_array($sections)||!count($sections)) {
			echo 'no active sections';
			return;
		}
		$output = NULL;
		foreach($sections as $section) {
			$section_id = $section['section_id'];
			$module     = $section['module'];
			// make a anchor for every section.
			if(defined('SEC_ANCHOR') && SEC_ANCHOR!='') {
				$output .= '<a class="section_anchor" id="'.SEC_ANCHOR.$section_id.'" name="'.SEC_ANCHOR.$section_id.'"></a>';
			}
			if(file_exists(WB_PATH.'/modules/'.$module.'/view.php')) {
				ob_start(); // fetch original content
				require(WB_PATH.'/modules/'.$module.'/view.php');
				$output .= ob_get_clean();
			}
			else {
                CRLogger::debug(sprintf('no such module: [%s]',$module));
                $output .= '<!-- no such module: '.$module.' -->';
				continue;
			}
		}
		if($output && $print) {
			echo $output;
			return NULL;
		}
		return $output;
	}   // end function get_block()
    
    /**
     * get current page; returns false if no page is loaded
     *
     * @access public
     * @return mixed
     **/
    public function get_current() {
        CRLogger::debug(sprintf('current page is [%s]',self::$current));
        return ( self::$current ? self::$current : false );
	}   // end function get_current()

	public function set_current($id) {
		CRLogger::debug(sprintf('set current [%s]',$id));
	    self::$current = $id;
	    if(!isset(self::$page_objects[$id])) {
			CRLogger::debug('creating instance');
	        self::$page_objects[$id] = CRPageObj::getInstance($id);
		}
	}   // end function set_current()

	/**
	 *
	 *
	 *
	 *
	 **/
	public function get_headers($for,$print_output,$current_section) {

	}   // end function get_headers()
	
	/**
     * get active sections for page
     *
     *
     *
     **/
    public function get_sections($id,$block=null,$backend=false) {
        CRLogger::debug(sprintf('getting properties for page [%s]',$id));
	    if(!isset(self::$page_objects[$id])) {
	        CRLogger::debug('creating page object');
		    self::$page_objects[$id] = CRPageObj::getInstance($id);
		}
		return self::$page_objects[$id]->sections();
    }   // end function get_sections()
	
	/**
	 * get page properties
	 **/
	public static function properties($id) {
		CRLogger::debug(sprintf('getting properties for page [%s]',$id));
	    if(!isset(self::$page_objects[$id])) {
	        CRLogger::debug('creating page object');
		    $pageh = CRPageObj::getInstance($id);
		    $data  = $pageh->resolve($id);
		    if ( is_array($data) ) {
		        self::$page_objects[$id] = $data;
		        CRLogger::debug('page data',self::$page_objects[$id]);
			}
			else {
			    CRLogger::debug(sprintf('no data for page [%s]',$id));
			}
	    }
	    return ( isset(self::$page_objects[$id]) ? self::$page_objects[$id]->properties() : NULL );
	}   // end function properties()

	/**
	 *
	 *
	 *
	 *
	 **/
	public function show($path) {
	    CREvent::raise('before');
	    $page = CRPageObj::getInstance($path);
	    if ( $page->found ) {
			self::set_current($page->page_id);
			self::$page_objects[$path] = $page;
		}
		CREvent::raise('on');
		$id    = self::get_current();
		if(!self::$page_objects[$id]->found) { // --- check if the page exists ---
		    CRLogger::err(sprintf('page not found [%s]',$path));
			self::error('404','no such page!');
		    exit;
		}
		// get page; the template 'pulls' the contents (function page_content())
		$output = CRTemplate::view('index.php',array());
		// raise after event for output filters
		CREvent::raise('after',$output);
		// replace droplets
		CRPage::eval_droplets($output);
		// print page
		echo $output;
	}
	
}

class CRPageObj extends CRBase {

	public           $found      = false;
	public           $page_id    = NULL;
	public           $sections   = NULL;
	
	private   		 $page       = NULL;

	public static    $DEBUGLEVEL = 0;

    //@{ private static properties
    protected static $statements;
    private   static $instances = array();
    //@}

    /**
     *
     *
     *
     *
     **/
	private function __construct($path) {
	    if(!is_array(self::$statements)) self::initdb();
	    CRLogger::debug(sprintf('path to serve: [%s]',$path));
	    $page = $this->resolve($path);
	    if(is_array($page)) {
			$id   = $page['page_id'];
			CRLogger::debug(sprintf('serving page with id [%s]',$id));
			$this->set_page($id);
		}
	}   // end function __construct()
	
	public function getInstance($path) {
	    if(!isset(self::$instances[$path])) self::$instances[$path] = new self($path);
	    return self::$instances[$path];
	}   // end function getInstance()
	
	/**
	 *
	 **/
	public function properties() {
	    return $this->page;
	}   // end function properties()
	
	/**
     * find page (resolve path)
     **/
	public function resolve($id) {
        CRLogger::debug(sprintf('trying to get page with id/path [%s]',$id));
	    if(!isset($this->page_id) || $this->page_id !== $id) {
		    $exec = NULL;
		    if(is_numeric($id)){
				$exec  = 'find_page_by_id';
				$parms = array('id'=>$id);
			}
			else {
	            $exec  = 'find_page_by_path';
	            $parms = array('path'=>$id);
			}
			CRLogger::debug(sprintf('executing statement named [%s]',$exec));
			$page = CRDB::execute($exec,$parms)->fetch();
			if(is_array($page)) {
				CRLogger::debug('page data:',$page);
				$this->page  = $page;
				$this->found = true;
				return $page;
			}
			else {
			    CRLogger::debug(sprintf('no page found for path/id [%s]',$id));
			}
		}
		else {
		    return $this->page;
		}
	}   // end function resolve()
	
	/**
	 *
	 **/
	public function sections($block=NULL) {
		if (!is_array($this->sections)) {
            $sections = CRDB::execute('sections',array('id'=>$this->page_id))->fetchAll();
			if(!is_array($sections)) return;
			foreach( $sections as $section ) {
                // skip this section if it is out of publication-date
                $now = time();
                if (!(($now <= $section['publ_end'] || $section['publ_end'] == 0) && ($now >= $section['publ_start'] || $section['publ_start'] == 0))) {
                    continue;
                }
                $this->sections[$section['block']][] = $section;
            }
        }
        if ( $block ) {
			return (isset($this->sections[$block]))
				? $this->sections[$block]
				: NULL;
		}
		$all = array();
		foreach( $this->sections as $block => $values ) {
			foreach( $values as $value ) {
		    	array_push( $all, $value );
			}
		}
		return $all;
	}   // end function sections()
	
	/**
	 *
	 **/
	public function set_page($id) {
	    $this->page_id = $id;
	    $this->resolve($id);
		$this->sections();
	}   // end function set_page()

}
