<?php

/**
 * Crissando CMS - Main
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
 
ini_set('display_errors', 1);
error_reporting(E_ALL^E_STRICT);

// set some globals
define('BASEDIR'     , dirname(__FILE__) );
define('CR_INIT'     , true              );

date_default_timezone_set('UTC');

// load the core
require_once dirname(__FILE__).'/engine/CRCore.php';

// register autoloader
spl_autoload_register('CRBase::autoload');

// load config
CRCore::loadconfig( dirname(__FILE__).'/engine/config/globals.cfg' );

// get local settings for site (if any)
if ( file_exists( SITE_PATH.'/config/globals.cfg' ) ) { CRCore::loadconfig( SITE_PATH.'/config/globals.cfg' ); }

// create database instance(s)
CRDB::init();

if(isset($_GET['style'])) {
    echo CRLoader::css($_GET['style']);
    exit;
}
if(isset($_GET['javascript'])) {
    echo CRLoader::js($_GET['javascript']);
    exit;
}

set_include_path (
    implode(
        PATH_SEPARATOR,
        array(
            CRCore::path(CRCore::get('GLOBALS.library_path')),
            get_include_path(),
        )
    )
);

include dirname(__FILE__).'/library/Zend/Acl/Resource.php';

if ( CRCore::get('GLOBALS.vendor_path') ) {
    foreach( array('init','db','hooks') as $file ) {
        $file = CRCore::path(CRCore::get('GLOBALS.vendor_path').'/'.$file.'.php');
        if( file_exists($file) ) include $file;
    }
}

// run
CRCore::run();

?>