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

class Page extends CRCore {

	public static    $DEBUGLEVEL = 0;

    //@{ private static properties
    protected static $statements;
    private   static $active;
    private   static $current;
    private   static $default;
    private   static $pages;
	private   static $sections;
    //@}
    
    /**
     * get active blocks (aka sections) for page $page_id
     *
     * @access public
     * @param  integer $page
     * @return array
     **/
    public static function get_active_blocks($page_id) {
        if(!isset(self::$active[$page_id])) {
	        if(!is_array(self::$statements)) self::initdb();
	        self::$active[$page_id] = CRDB::execute('active_sections',array(':now'=>time(),':now2'=>time(),':id'=>$page_id))->fetchAll();
	        CRLogger::debug(sprintf('active sections for page [%d] -> [%d]',$page_id,count(self::$active[$page_id])));
		}
        return self::$active[$page_id];
    }   // end function get_active_blocks()
    
	/**
	 *
	 *
	 *
	 *
	 **/
	public static function get_block($block=1,$print=true) {
	    if (!is_numeric($block))     $block = 1;           // default block
	    if (!self::get_current())    self::get_page_properties(-1);   // get root page
	    $sections = Page::get_sections( PAGE_ID, $block ); // get active get_sections
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
     * get current page properties; returns false if no page is loaded
     *
     * @access public
     * @param  integer  $id (optional)
     * @return mixed
     **/
    public static function get_current($id=false) {
        return ( isset(self::$pages[self::$current]) && is_array(self::$pages[self::$current]))
			? ( ($id) ? self::$current : self::$pages[self::$current] )
			: false;
	}   // end function get_current()
	
	/**
	 *
	 *
	 *
	 *
	 **/
	public static function get_default() {
	    if(!isset(self::$default) || self::$default=='' || self::$default=='/') {
		    $result = CRDB::execute('default_page',array(':now'=>time(),':now2'=>time()))->fetch();
		    if(is_array($result) && count($result)) {
		    	self::$default = $result['page_id'];
		    	self::$pages[self::$default] = CRDB::execute('find_page_by_id',array('id'=>self::$default))->fetch();
			}
		}
		return self::$default;
	}   // end function get_default()
	
	/**
	 * get page description (with fallback to website description)
	 **/
	public static function get_description() {
	    if (!self::get_current()) self::get_page_properties(-1);    // get root page
		$page     = Page::get_current();
		$settings = CRCore::get('GLOBALS.settings');
		$output   = NULL;
		if ( isset($page['description']) && $page['description'] != '' ) {
		    $output = $page['description'];
		}
		else {
			if ( isset($settings['website_description']) ) {
			    $output = $settings['website_description'];
			}
		}
		return ($output) ? $output : NULL;
	}   // end function get_description()
    
	/**
	 *
	 *
	 *
	 *
	 **/
	public static function get_headers($for,$print_output,$current_section) {
	
	}   // end function headers()
	
    /**
     * find page and load the properties
     **/
	public static function get_page_properties($id) {
        CRLogger::debug(sprintf('trying to get page with id/path [%s]',$id));
	    if(!isset(self::$pages[$id]) || !is_array(self::$pages[$id])) {
		    if(!is_array(self::$statements)) self::initdb();
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
				self::$pages[$page['page_id']] = $page;
				$id = $page['page_id'];
				CRLogger::debug('page data:',self::$pages[$id]);
				self::$current = $id;
				return self::$pages[$id];
			}
			else {
			    CRLogger::debug('no page found');
			}
		}
	}   // end function get_page_properties()
	
	/**
     * get active sections for page
     *
     *
     *
     **/
    public static function get_sections($page_id,$block=null,$backend=false) {
        if (!is_array(self::$sections)) {
            if(!is_array(self::$statements)) self::initdb();
            $sections = CRDB::execute('sections', array('id'=>$page_id))->fetchAll();
			if(!is_array($sections)) return;
			foreach( $sections as $section ) {
                // skip this section if it is out of publication-date
                $now = time();
                if (!(($now <= $section['publ_end'] || $section['publ_end'] == 0) && ($now >= $section['publ_start'] || $section['publ_start'] == 0))) {
                    continue;
                }
                self::$sections[$section['block']][] = $section;
            }
        }
        if ( $block ) {
			return (isset(self::$sections[$block]))
				? self::$sections[$block]
				: NULL;
		}

		$all = array();
		foreach( self::$sections as $block => $values ) {
			foreach( $values as $value ) {
		    	array_push( $all, $value );
			}
		}
		return $all;

    }   // end function get_sections()
    
    public static function eval_droplets( &$content ) {
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

}