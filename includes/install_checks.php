<?php

use Bitweaver\KernelTools;
/**
 * @version $Header$
 * @package install
 * @subpackage functions
 * @author xing
 */

// assign next step in installation process
$gBitSmarty->assign( 'next_step', $step + 1 );

$check_settings = check_settings();

$gBitSmarty->assign( "error", $error );
$gBitSmarty->assign( "warning", $warning );

foreach( $check_settings as $type => $checks ) {
	$gBitSmarty->assign( $type, $checks );
}

if( !isset( $_SERVER['HTTP_REFERER'] ) ) {
	$gBitSmarty->assign( "http_referer_error", true );
	$error = true;
}

/**
 * check_settings
 */
function check_settings() {
	global $gBitSmarty, $error, $warning;
	$config_file = KernelTools::clean_file_path( empty( $_SERVER['CONFIG_INC'] ) ? BIT_ROOT_PATH.'config/kernel/config_inc.php' : $_SERVER['CONFIG_INC'] );

	$i = 0;
	// required settings - if not met, are passed into the array $reqd
	// PHP system checks
	$phpvers = '8.3.0';
	if( phpversion() < $phpvers ) {
		$required[$i]['note'] = '<strong>PHP version</strong> should be greater than <strong>'.$phpvers.'</strong>.<br />Your installed version of PHP is <strong>'.phpversion().'</strong>.';
		$required[$i]['passed'] = false;
	} else {
		$required[$i]['note'] = '<strong>PHP version</strong> is greater than <strong>'.$phpvers.'</strong>.<br />Your installed version of PHP is <strong>'.phpversion().'</strong>.';
		$required[$i]['passed'] = true;
	}

	// check file and directory permissisions
	$i++;
	if( @file_exists( $config_file ) && @KernelTools::bw_is_writeable( $config_file ) ) {
		$required[$i]['note'] = 'The Bitweaver configuration file is available and the file is writeable:<br /><code>'.$config_file.'</code>';
		$required[$i]['passed'] = true;
	} elseif( @file_exists( $config_file ) && !@KernelTools::bw_is_writeable( $config_file ) ) {
		$required[$i]['note'] = 'The Bitweaver configuration file is available but the file is not writeable. Please execute something like:<br /><code>chmod 777 '.$config_file.'</code>';
		$required[$i]['passed'] = false;
	} else {
		$required[$i]['note'] = 'The Bitweaver configuration file is not available. Please execute something like:<br /><code>touch '.$config_file.';<br />chmod 777 '.$config_file.'</code>';
		$required[$i]['passed'] = false;
	}
	$i++;

	$dir_check = [
		'storage' => defined( 'STORAGE_PKG_PATH' ) ? STORAGE_PKG_PATH : BIT_ROOT_PATH . 'storage',
		'temp'    => defined( 'TEMP_PKG_PATH' ) ? TEMP_PKG_PATH : sys_get_temp_dir() . '/bitweaver/' . $_SERVER['SERVER_NAME'],
	];
	foreach( $dir_check as $name => $d ) {
		// final attempt to create the required directories
//		@mkdir( $d,0644 );
		if( @is_dir( $d ) && KernelTools::bw_is_writeable( $d ) ) {
			$required[$i]['note'] = "The $name directory is available and it is writeable.<br /><code>$d</code>";
			$required[$i]['passed'] = true;
		} elseif( @is_dir( $d ) && !KernelTools::bw_is_writeable( $d ) ) {
			$required[$i]['note'] = "The $name directory is available but it is not writeable.<br />Please execute something like:<br /><code>chmod -R 777 $d</code>";
			$required[$i]['passed'] = false;
		} else {
			$required[$i]['note'] = "The $name directory is not available and we cannot create it automaticalliy.<br />Please execute something like:<br /><code>mkdir -m 777 $d</code>";
			$required[$i]['passed'] = false;
		}
		$i++;
	}
	foreach( $required as $r ) {
		if( !$r['passed'] ) {
			$error = true;
		}
	}

	// check extensions
	$php_ext = [
		'zlib'       => '<a class="external" href="http://www.zlib.net/">The zlib compression libraries</a> are used to pack and unpack compressed files such as .zip files.',
		'gd'         => '<a class="external" href="http://www.boutell.com/gd/">GD Libraries</a> are used to manipulate images. Bitweaver uses these libraries to create thumbnails and convert images from one format to another. If you are running Red Hat or Fedora Core, you can try: <kbd>yum install php-gd</kbd>. The GD libaries are quite limited and <em>don\'t support</em> a number of image formats including <em>.bmp</em>. If you are planning on uploading and using a lot of images, we recommend you use one of the other image processors.',
		'imagick'    => 'ImageMagick supports a multitude of different image and video formats and <em>can be used instead of the GD Libraries</em>. Using these libraries will allow you to upload most image formats without any difficulties. For installation help, please view <a class="external" href="https://www.bitweaver.org/wiki/ImageMagick">ImageMagick and MagickWand installation instructions</a> or visit the <a class="external" href="http://www.imagemagick.org">ImageMagick homepage</a>.',
//		'ffmpeg'       => '<a class="external" href="http://ffmpeg-php.sourceforge.net/">ffmpeg-php</a> is an extension that will allow you to better process uploaded videos. This extension requires ffmpeg to be installed on your server as well.',
	];
	foreach( $php_ext as $ext => $note ) {
		$extensions[$ext]['note'] = "The extension <strong>$ext</strong> is ";
		if( extension_loaded( $ext ) ) {
			$extensions[$ext]['passed'] = true;
		} else {
			$extensions[$ext]['note'] .= 'not ';
			$extensions[$ext]['passed'] = false;
		}
		$extensions[$ext]['note'] .= "available.<br />$note";
	}

/*	// disable one of the imagick / magickwand warnings
	if( $extensions['magickwand']['passed'] == true && $extensions['imagick']['passed'] == false ) {
		unset( $extensions['imagick'] );
	} elseif( $extensions['imagick']['passed'] == true && $extensions['magickwand']['passed'] == false ) {
		unset( $extensions['magickwand'] );
	}
*/

	// make sure we show the worning flag if there is a need for it
	foreach( $extensions as $info ) {
		if( !$info['passed'] ) {
			$warning = true;
		}
	}

	// output has to be verbose that we can catch the output of the shell_exec
	// using --help, -h or --version should make applications output something to stdout - this is no guarantee though, bunzip2 doesn't...
	$execs = [
		'tar'      => [
			'command'     => 'tar -xvf',
			'dest_params' => '-C',
			'testfile'    => 'test.tar',
			'note'        => '<strong>Tarball</strong> is a common archiving format on Linux and <a class="external" href="http://www.gnu.org/software/tar/">tar</a> is used to extract .tar files. For windows use <a class="external" href="http://gnuwin32.sourceforge.net/downlinks/libarchive.php">bsdtar</a> from gnuwin32 ( See <a class="external" href="https://www.bitweaver.org/wiki/Windows+installation+notes">Windows notes for installation help</a> ).',
		],
		'bzip2'    => [
			'command'     => 'tar -jvxf',
			'dest_params' => '-C',
			'testfile'    => 'test.tar.bz2',
			'note'        => '<strong>Bzip</strong> is a common compression format on Linux and <a class="external" href="http://www.bzip.org/">bzip2</a> is used to extract .bz2 and in combination with tar .tar.bz2 file. ( For windows see bsdtar above. )',
		],
		'gzip'     => [
			'command'     => 'tar -zvxf',
			'dest_params' => '-C',
			'testfile'    => 'test.tar.gz',
			'note'        => '<strong>Gzip</strong> is a common compression format on Linux and <a class="external" href="http://www.gnu.org/software/gzip/gzip.html">gzip</a> is used to extract .gz and in combination with tar .tar.gz file. ( For windows see bsdtar above. )',
		],
		'unzip'    => [
			'command'     => 'unzip -v',
			'dest_params' => '-d',
			'testfile'    => 'test.zip',
			'note'        => '<strong>Zip</strong> is a common compression format on all operating systems and <a class="external" href="http://www.info-zip.org/">unzip</a> is used to extract .zip files.',
		],
		'unrar'    => [
			'command'     => 'unrar x',
			'dest_params' => '',
			'testfile'    => 'test.rar',
			'note'        => '<strong>Rar</strong> is a common compression format on all operating systems and <a class="external" href="http://www.rarlab.com/rar_add.htm">unrar</a> is used to extract .rar files.',
		],
		'gs'       => [
			'command' => 'gs --version',
			'note'    => '<a class="external" href="http://www.cs.wisc.edu/~ghost/">GhostScript</a> is an interpreter for the PostScript language and for PDF and is used to create PDF previews when uploading PDF files to Fisheye. If you do not have this installed, previews of PDF files will not be generated on upload. If you have difficulties with GhostScript, please try installing a different version. Bitweaver was successfully tested with versions 7.5, 8.15.4, 8.5, 8.54. There where difficulties with version 8.1.',
			'result'  => 'Your version of GhostScript: ',
		],
		'graphviz' => [
			'command' => 'dot -V',
			'note'    => '<a class="external" href="http://www.graphviz.org/">Graphviz</a> is a way of representing structural information as diagrams of abstract graphs and networks and visualizing that representation. It is used by the {graphviz} Liberty plugin and you only need to install it if you intend to enable that plugin.<br /><em>The Pear::Image_Graphviz plugin is required as well.</em>',
			'result'  => 'Your version of Graphviz: ',
		],
		'ffmpeg' => [
			'command' => 'ffmpeg',
			'note'    => '<a class="external" href="http://ffmpeg.mplayerhq.hu/">ffmpeg</a> is a hyper fast video and audio encoder that supports many common formats. If you are planning on uploading video and audio files, it\'s recommend that you install this application.',
		],
//		'unstuff' => array(
//			'params'       => '-xf',
//			'testfile'     => 'test.tar',
//			'note'         => 'Unstuff is a common compression format on Mac and <strong>unstuff</strong> is used to extract .sit files.',
//		),
	];
	foreach( $execs as $exe => $app ) {
		$executables[$exe]['note'] = 'The application <strong>'.$exe.'</strong> is ';
		$command = ( !empty( $app['testfile'] ) && is_readable( $file = INSTALL_PKG_PATH . 'testfiles/' . $app['testfile'] ) ) ? $app['command'] . ' "' . $file . '" ' . $app['dest_params'] . ' "' . TEMP_PKG_PATH . '"' : $app['command'];

		if( get_php_setting( 'safe_mode' ) == 'OFF' && $shellResults[$exe] = shell_exec( $command .' 2> '.TEMP_PKG_PATH.'output' ) ) {
//			@unlink( TEMP_PKG_PATH.'test.txt' );
			$executables[$exe]['passed'] = true;
		} elseif ( $shellResults[$exe] = join("", file(TEMP_PKG_PATH.'output')) ) {
			if ( strpos( $shellResults[$exe], 'command' ) and strpos( $shellResults[$exe], 'not' ) ) {
				$executables[$exe]['note'] .= 'not ';
				$executables[$exe]['passed'] = false;
				$shellResults[$exe] = "";
			} else {
//				@unlink( TEMP_PKG_PATH.'test.txt' );
				$executables[$exe]['passed'] = true;
			}
		} else {
			$executables[$exe]['note'] .= 'not ';
			$executables[$exe]['passed'] = false;
		}
		$executables[$exe]['note'] .= 'available.<br />'.$app['note'];
		if( !empty( $app['result'] ) && !empty( $shellResults[$exe] )) {
			$executables[$exe]['note'] .= '<br />'.$app['result'].'<strong>'.$shellResults[$exe].'</strong>';
		}
	}


	// PEAR checks
	$pears = [
		'PEAR'           => [
			'path' => 'PEAR.php',
			'note' => 'This check indicates if PEAR is installed and available. To make use of PEAR extensions, you need to make sure that PEAR is installed and the include_path is set in your php.ini file.',
		],
		'Auth'           => [
			'path'            => 'Auth/Auth.php',
			'note'            => 'This will allow you to use the PEAR::Auth package to authenticate users on your website.',
			'install_command' => 'pear install --onlyreqdeps Auth;',
		],
		'Text_Wiki'      => [
			'path'            => 'Text/Wiki.php',
			'note'            => 'Having PEAR::Text_Wiki installed will make more wiki format parsers available. The following parsers will be recognised and used: Text_Wiki_BBCode, Text_Wiki_Cowiki, Text_Wiki_Creole, Text_Wiki_Doku, Text_Wiki_Mediawiki, Text_Wiki_Tiki',
			'install_command' => 'pear install --onlyreqdeps Text_Wiki_BBCode Text_Wiki_Cowiki Text_Wiki_Creole Text_Wiki_Doku Text_Wiki_Mediawiki Text_Wiki_Tiki;',
		],
		'Text_Diff'      => [
			'path'            => 'Text/Diff.php',
			'note'            => 'PEAR::Text_Diff makes inline diffing of content available.',
			'install_command' => 'pear install --onlyreqdeps Text_Diff;',
		],
		'Image_Graphviz' => [
			'path'            => 'Image/GraphViz.php',
			'note'            => 'Pear::Image_Graphviz makes the {graphviz} plugin available. With it you can draw maps of how your Wiki pages are linked to each other. This can be used for informational purposes or a site-map. It requires the application graphviz to be installed on your server as well.',
			'install_command' => 'pear install --onlyreqdeps Image_Graphviz;',
		],
//		'HTMLPurifier'   => [
//			'path'            => 'HTMLPurifier.php',
//			'note'            => 'HTMLPurifier is an advanced system for defending against Cross Site Scripting (XSS) attacks and ensuring that all code on your site is standards compliant. It is highly recommended if you are going to allow HTML submission to your site. It is not required if you are only going to allow the input of a Wiki format like Tikiwiki or BBcode. You also need to enable it in the Liberty plugins administration after installation. See <a class="external" href="https://www.bitweaver.org/wiki/HTMLPurifier">https://www.bitweaver.org/wiki/HTMLPurifier</a> and <a class = "external" href = "http: // htmlpurifier.org">http://htmlpurifier.org</a> for more information.',
//			'install_command' => 'pear channel-discover htmlpurifier.org;<br />pear install hp/HTMLPurifier;',
//		],
		'HTTP_Download'  => [
			'path'            => 'HTTP/Download.php',
			'note'            => 'Treasury - the file manager of Bitweaver - can make use of PEAR::HTTP_Download to provide more reliable downloads and download resume support when using a download manager.',
			'install_command' => 'pear install --alldeps HTTP_Download;',
		],
	];

	foreach( $pears as $pear => $info ) {
		$pearexts[$pear]['note'] = ( $pear == 'PEAR' ) ? "<strong>$pear</strong> is " : "The extension <strong>PEAR::$pear</strong> is ";

		if ( @include_once( $info['path'] )) {
			$pearexts[$pear]['passed'] = true;
		} else {
			$pearexts[$pear]['note'] .= 'not ';
			$pearexts[$pear]['passed'] = false;
		}

		$pearexts[$pear]['original_note'] = $info['note'];
		$pearexts[$pear]['note'] .= 'available.<br />';
		$pearexts[$pear]['note'] .= $info['note'];

		if( !empty( $info['install_command'] ) && $pearexts[$pear]['passed'] == false ) {
			$pearexts[$pear]['note'] .= '<br /><em>Install using this command:</em><br /><code>'.$info['install_command'].'</code>';
		}
	}

	$i = 0;
	// recommended php toggles - these don't need explicit explanations on how to rectify them
	// start with special cases
	$recommended[$i] = [ 'Memory Limit', 'memory_limit', 'shouldbe' => 'at least 16M', 'actual' => get_cfg_var( 'memory_limit' ) ];
	if( preg_replace( '/M/i','',get_cfg_var( 'memory_limit' ) ) > 15 ) {
		$recommended[$i]['passed'] = true;
	} else {
		$recommended[$i]['passed'] = false;
		$gBitSmarty->assign( 'memory_warning', true );
	}

	$i++;
	// now continue with easy toggle checks
	$php_rec_toggles = [
		[ 'Display Errors', 'display_errors', 'shouldbe' => 'OFF' ],
		[ 'File Uploads', 'file_uploads', 'shouldbe' => 'ON' ],
		[ 'Output Buffering', 'output_buffering', 'shouldbe' => 'OFF' ],
		[ 'Session auto start', 'session.auto_start', 'shouldbe' => 'OFF' ],
		[ 'Log Errors', 'log_errors', 'shouldbe' => 'ON' ],
		[ 'Session Cookie HttpOnly', 'session.cookie_httponly', 'shouldbe' => 'ON' ],
		[ 'Session Cookie Secure', 'session.cookie_secure', 'shouldbe' => 'ON' ],
	];
	foreach( $php_rec_toggles as $php_rec_toggle ) {
		$php_rec_toggle['actual'] = get_php_setting( $php_rec_toggle[1] );
		$php_rec_toggle['passed'] = ( get_php_setting( $php_rec_toggle[1] ) == $php_rec_toggle['shouldbe'] ) ? true : false;
		$recommended[] = $php_rec_toggle;
		$i++;
	}

	// settings that are useful to know about
	$php_ini_gets = [
		[ '<strong>Maximum post size</strong> restricts the size of files uploaded using a form. <br />Recommended: at least <strong>8M</strong>.', 'post_max_size' ],
		[ '<strong>Upload max filesize</strong> is related to maximim post size and will also limit the size of uploads. <br />Recommended: at least <strong>8M</strong>.', 'upload_max_filesize' ],
		[ '<strong>Maximum execution time</strong> is related to time outs in PHP. It affects database upgrades and backups. <br />Recommended: <strong>60</strong>.', 'max_execution_time' ],
	];
	foreach( $php_ini_gets as $php_ini_get ) {
		$value = ini_get( $php_ini_get[1] );
		switch ($value) {
			case 1:
				$value = "On";
				break;
			case 0:
				$value = "Off";
				break;
		}

		$show[$php_ini_get[1]] = $php_ini_get[0]."<br /><strong>{$php_ini_get[1]}</strong> is set to <strong>$value</strong>";
	}

	$res['required']    = $required;
	$res['extensions']  = $extensions;
	$res['executables'] = $executables;
	$res['pearexts']    = $pearexts;
	$res['recommended'] = $recommended;
	$res['show']        = $show;
	return $res;
}

/**
 * get_php_setting
 */
function get_php_setting( $val ) {
	$r = ( ini_get( $val ) == '1' ? 1 : 0 );
	return $r ? 'ON' : 'OFF';
}
