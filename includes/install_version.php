<?php

use Bitweaver\KernelTools;
/**
 * @version $Header$
 * @package install
 * @subpackage functions
 */

// assign next step in installation process
$gBitSmarty->assign( 'next_step', $step );

// check if database version is up to date
if( version_compare( $gBitSystem->getBitVersion(), $gBitSystem->getVersion(), '==' )) {
	$upToDate = TRUE;
	$gBitSmarty->assign( 'upToDate', $upToDate );
}


// ===================== Update version to current one =====================
// Only update the version when the form has been submitted
if( !empty( $_REQUEST['update_version'] )) {
	if( !empty( $upToDate ) || !empty( $_REQUEST['skip'] )) {
		// if we're already up to date, we'll simply move on to the next page
		KernelTools::bit_redirect( $_SERVER['SCRIPT_NAME']."?step=".++$step );
	} else {
		// set the version of bitweaver in the database
		if( $gBitSystem->storeVersion( NULL, $gBitSystem->getBitVersion() )) {
			// display the confirmation page
			$gBitSmarty->assign( 'next_step', $step + 1 );
			$app = '_done';
		}
	}
}