<?php
/**
 * @version $Header$
 * @package install
 * @subpackage functions
 */

// Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See below for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details.

// assign next step in installation process
$gBitSmarty->assign( 'next_step',$step );

// Only fall back to ROOT_USER_ID when nobody's actually logged in yet - it deliberately carries no
// role/permission grants (it's not meant to be used as a real actor), so forcing every pump to run
// as root breaks any pump that needs a real permission check, e.g. mapper's file upload
// (p_liberty_attach_attachments). Don't assume install_packages.php's own admin-creation step ran
// in *this* request/session (a resumed or re-visited install run may have created the admin in an
// earlier pass) - look the real admin (user_id 2, the hardcoded convention that same step uses) up
// directly whenever $gBitUser isn't already a real, non-root registered user.
if( empty( $gBitUser->mUserId ) || $gBitUser->mUserId <= ROOT_USER_ID ) {
	$adminInfo = $gBitUser->getUserInfo( [ 'user_id' => 2 ] );
	if( !empty( $adminInfo['user_id'] ) && $adminInfo['user_id'] == 2 ) {
		$gBitUser->mUserId = 2;
		$gBitUser->mInfo   = $adminInfo;
		$gBitUser->loadPermissions( TRUE );
	} else {
		$gBitUser->mUserId = ROOT_USER_ID;
	}
}

// Same reasoning as install_packages.php's explicit bithtml activation: loadConfig() has its own
// BIT_INSTALL guard, so getConfig() never actually reads the DB during install, and
// registerPlugin()'s auto_activate path can't correct for that either (its own in-memory mPlugins
// update happens before the plugin object exists in mPlugins, so it's silently discarded). This
// needs to run fresh in *every* install-context request that might upload a file, not just the
// Packages step - $gLibertySystem is rebuilt from scratch each request, so whatever got fixed
// there doesn't carry over here.
global $gLibertySystem;
if( !empty( $gLibertySystem->mPlugins[LIBERTY_DEFAULT_MIME_HANDLER] ) ) {
	$gLibertySystem->setActivePlugin( LIBERTY_DEFAULT_MIME_HANDLER );
}

$pumpList = [];
foreach( array_keys( $gBitSystem->mPackages ) as $package ) {
	if( $gBitInstaller->isPackageActive( $package ) ) {
		$file = constant( strtoupper( $package ).'_PKG_PATH' ).'admin/pump_'.$package.'_inc.php';
		if( file_exists( $file )) {
			$pumpList[$package] = $file;
		}
	}
}
$gBitSmarty->assign( 'pumpList', $pumpList );

/**
 * datapump setup
 */
if( isset( $_REQUEST['fSubmitDataPump'] ) ) {
	$pumpedData = [];
	if( !empty( $_REQUEST['pump_package'] ) ) {
		foreach( $_REQUEST['pump_package'] as $package ) {
			if( $gBitInstaller->isPackageActive( $package ) ) {
				$file = constant( strtoupper( $package ).'_PKG_PATH' ).'admin/pump_'.$package.'_inc.php';
				include_once( $file );
			}
		}
	}
	$gBitSmarty->assign( 'pumpedData',$pumpedData );
	$app = '_done';
	$gBitSmarty->assign( 'next_step',$step + 1 );

	if( $gBitSystem->isPackageActive( 'wiki' ) && !empty( $pumpedData['Wiki'] )) {
		$gBitSystem->storeConfig( 'wiki_home_page', $pumpedData['Wiki'][0], WIKI_PKG_NAME );
	}
} elseif( isset( $_REQUEST['skip'] )) {
	$app = '_done';
	$goto = $step + 1;
	$gBitSmarty->assign( 'next_step',$goto );
	header( "Location: ".INSTALL_PKG_URL."install.php?step=$goto" );
	die;
}