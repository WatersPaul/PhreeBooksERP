<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2014 PhreeSoft      (www.PhreeSoft.com)       |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /includes/application_top.php
//
define('PAGE_EXECUTION_START_TIME', microtime(true));
if (!get_cfg_var('safe_mode')) {
	if (ini_get('max_execution_time') < 60) set_time_limit(60);
}
if(extension_loaded('apc')) ini_set('apc.enabled','1');
$force_reset_cache = false;
// set php_self in the local scope
if (!isset($PHP_SELF)) $PHP_SELF = $_SERVER['PHP_SELF'];
// Check for application configuration parameters
if     (file_exists('includes/configure.php')) { require('includes/configure.php'); }
elseif (file_exists('install/index.php')) {
	ob_end_flush();
  	session_write_close();
	header('Location: install/index.php'); exit(); }
else  trigger_error('Phreedom cannot find the configuration file. Aborting!', E_USER_ERROR);
// Load some path constants
$path = (ENABLE_SSL_ADMIN == 'true' ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_ADMIN;
if (!defined('PATH_TO_MY_FILES')) define('PATH_TO_MY_FILES','my_files/');
define('DIR_WS_FULL_PATH', $path);	// full http path (or https if secure)
define('DIR_WS_MODULES',   'modules/');
define('DIR_WS_MY_FILES',  PATH_TO_MY_FILES);
// load some file system constants
define('DIR_FS_INCLUDES',  DIR_FS_ADMIN . 'includes/');
define('DIR_FS_MODULES',   DIR_FS_ADMIN . 'modules/');
define('DIR_FS_MY_FILES',  DIR_FS_ADMIN . PATH_TO_MY_FILES);
define('DIR_FS_THEMES',    DIR_FS_ADMIN . 'themes/');
define('FILENAME_DEFAULT', 'index');
define('APC_EXTENSION_LOADED', extension_loaded('apc') && ini_get('apc.enabled'));
// set the type of request (secure or not)
$request_type = (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $_SERVER['HTTPS'] == '1' || strstr(strtoupper($_SERVER['HTTP_X_FORWARDED_BY']),'SSL') || strstr(strtoupper($_SERVER['HTTP_X_FORWARDED_HOST']),'SSL'))) ? 'SSL' : 'NONSSL';
// define the inventory types that are tracked in cost of goods sold
define('COG_ITEM_TYPES','si,sr,ms,mi,ma,sa');
//start session functions
@ini_set('session.gc_maxlifetime', (SESSION_TIMEOUT_ADMIN < 360 ? 360 : SESSION_TIMEOUT_ADMIN));
session_set_cookie_params((SESSION_TIMEOUT_ADMIN < 360 ? 360 : SESSION_TIMEOUT_ADMIN),'/',$path);
session_start();
//end session
$_REQUEST = array_merge($_GET, $_POST);
if(!isset($_REQUEST['module']))	$_REQUEST['module']	= 'phreedom';
if(!isset($_REQUEST['page'])) 	$_REQUEST['page'] 	= 'main';
// define general functions and classes used application-wide
require_once(DIR_FS_MODULES  . 'phreedom/defaults.php');
require_once(DIR_FS_INCLUDES . 'common_functions.php');
set_error_handler("PhreebooksErrorHandler");
set_exception_handler('PhreebooksExceptionHandler');
spl_autoload_register('Phreebooks_autoloader', true, false);
// pull in the custom language over-rides for this module/page
$custom_path = DIR_FS_MODULES . "{$_REQUEST['module']}/custom/pages/{$_REQUEST['page']}/extra_defines.php";
if (file_exists($custom_path)) { include($custom_path); }
gen_pull_language($module);
define('DIR_WS_THEMES', 'themes/' . (isset($_SESSION['admin_prefs']['theme']) ? $_SESSION['admin_prefs']['theme'] : DEFAULT_THEME) . '/');
define('MY_COLORS',isset($_SESSION['admin_prefs']['colors'])?$_SESSION['admin_prefs']['colors']:DEFAULT_COLORS);
define('MY_MENU',  isset($_SESSION['admin_prefs']['menu'])  ?$_SESSION['admin_prefs']['menu']  :DEFAULT_MENU);
define('DIR_WS_IMAGES', DIR_WS_THEMES . 'images/');
if (file_exists(DIR_WS_THEMES . 'icons/')) { define('DIR_WS_ICONS',  DIR_WS_THEMES . 'icons/'); }
else { define('DIR_WS_ICONS', 'themes/default/icons/'); } // use default
$messageStack 	= new \core\classes\messageStack;
$toolbar      	= new \core\classes\toolbar;
$currencies		= APC_EXTENSION_LOADED ? apc_fetch("currencies") : false;
if ($currencies === false) $currencies = new \core\classes\currencies;
$admin 	= APC_EXTENSION_LOADED ? apc_fetch("admin")	: false;
if ($admin === false) $admin = new \core\classes\basis;
// determine what company to connect to
if ($_REQUEST['action']=="ValidateUser") $_SESSION['company'] = $_POST['company'];
if (isset($_SESSION['company']) && $_SESSION['company'] != '' && file_exists(DIR_FS_MY_FILES . $_SESSION['company'] . '/config.php')) {
	define('DB_DATABASE', $_SESSION['company']);
	require_once(DIR_FS_MY_FILES . $_SESSION['company'] . '/config.php');
	define('DB_SERVER_HOST',DB_SERVER); // for old PhreeBooks installs
// dit is de nieuwe pdo
	$dsn = DB_TYPE.":dbname={$_SESSION['company']};host=".DB_SERVER_HOST;
	try {
		$admin->DataBase = new \PDO($dsn, DB_SERVER_USERNAME, DB_SERVER_PASSWORD,array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	} catch (\PDOException $e) {
		trigger_error('database connection failed: ' . $e->getMessage() , E_USER_ERROR);
	}
	if(APC_EXTENSION_LOADED == false || apc_load_constants('configuration') === false) {
		try{
		 	$temp = $admin->DataBase->prepare("select configuration_key, configuration_value from " . DB_PREFIX . "configuration");
		 	$temp->execute();
		 	$array = array ();
	    	foreach($temp->fetch(PDO::FETCH_ASSOC) as $row){
	    		if (APC_EXTENSION_LOADED) {
	    			$array[$row['configuration_key']] = $row['configuration_value'];
	    		}else{
		  			define($row['configuration_key'],$row['configuration_value']);
	    		}
		  	}
		  	if (APC_EXTENSION_LOADED) apc_define_constants("configuration", $array, true);
	    }catch (\PDOException $e) {
	    	trigger_error(LOAD_CONFIG_ERROR . $e->getMessage(), E_USER_ERROR);
	    }
	}

/*
  	// Load queryFactory db classes
  	require_once(DIR_FS_INCLUDES . 'db/' . DB_TYPE . '/query_factory.php');
  	$db = new queryFactory();
  	$admin->DataBase->connect(DB_SERVER_HOST, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
  	// set application wide parameters for phreebooks module
  	if(APC_EXTENSION_LOADED == false || apc_load_constants('configuration') === false) {
  		$result = $admin->DataBase->Execute_return_error("select configuration_key, configuration_value from " . DB_PREFIX . "configuration");
  		if ($admin->DataBase->error_number != '' || $result->RecordCount() == 0) trigger_error(LOAD_CONFIG_ERROR, E_USER_ERROR);
  		$array = array ();
  		while (!$result->EOF) {
  			if (APC_EXTENSION_LOADED) {
  				$array[$result->fields['configuration_key']] = $result->fields['configuration_value'];
  			}else{
  				define($result->fields['configuration_key'], $result->fields['configuration_value']);
  			}
			$result->MoveNext();
  		}
  		if (APC_EXTENSION_LOADED) apc_define_constants("configuration", $array, true);
  	}*/
  	// search the list modules and load configuration files and language files
  	gen_pull_language('phreedom', 'menu');
  	gen_pull_language('phreebooks', 'menu');
  	require(DIR_FS_MODULES . 'phreedom/config.php');
	if (APC_EXTENSION_LOADED) apc_add("admin", $admin, 600);
	if (APC_EXTENSION_LOADED) apc_add("currencies", $currencies, 600);
	if (APC_EXTENSION_LOADED) apc_add("mainmenu", $mainmenu, 600);
  	$currencies->load_currencies();
	// pull in the custom language over-rides for this module (to pre-define the standard language)
  	$path = DIR_FS_MODULES . "{$_REQUEST['module']}/custom/pages/{$_REQUEST['page']}/extra_menus.php";
  	if (file_exists($path)) { include($path); }
}
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > (SESSION_TIMEOUT_ADMIN < 360 ? 360 : SESSION_TIMEOUT_ADMIN))) {
	$_SESSION = array('language'=>$_SESSION['language'], 'companies'=>$_SESSION['companies']);
	gen_redirect(html_href_link(FILENAME_DEFAULT, '', 'SSL'));
}
$_SESSION['LAST_ACTIVITY'] = time();
$prefered_type = ENABLE_SSL_ADMIN == 'true' ? 'SSL' : 'NONSSL';
if ($request_type <> $prefered_type) gen_redirect(html_href_link(FILENAME_DEFAULT, '', 'SSL')); // re-direct if SSL request not matching actual request
?>