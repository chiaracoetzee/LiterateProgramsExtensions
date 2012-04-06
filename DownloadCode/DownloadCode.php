<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This file is a MediaWiki extension, it is not a valid entry point' );
}

/**
 * CONFIGURATION
 * These variables may be overridden in LocalSettings.php after you include the
 * extension file.
 */

/* None yet */

/* Implementation. Based in part on PdfBook extension. */

define( 'DOWNLOADCODE_VERSION', "2012-04-06" );

$wgExtensionFunctions[]        = 'wfSetupDownloadCode';
$wgExtensionMessagesFiles['DownloadCode'] = dirname( __FILE__ ) . '/DownloadCode.i18n.php';
$wgAutoloadClasses['SpecialDownloadCode'] = dirname( __FILE__ ) . '/SpecialDownloadCode.php';
$wgExtensionMessagesFiles['DownloadCode'] = dirname( __FILE__ ) . '/DownloadCode.i18n.php';
$wgSpecialPages['DownloadCode'] = 'SpecialDownloadCode';

$wgExtensionCredits['parserhook'][] = array(
	'path'           => __FILE__,
	'name'           => "DownloadCode",
	'author'         => "[http://moonflare.com Derrick Coetzee]",
	'url'            => "http://www.mediawiki.org/wiki/Extension:DownloadCode",
	'version'        => DOWNLOADCODE_VERSION,
	'descriptionmsg' => 'downloadcode-desc',
);

/**
 * Called from $wgExtensionFunctions array when initialising extensions
 */
function wfSetupDownloadCode() {
	global $wgDownloadCode;
	$wgDownloadCode = new DownloadCode();
}

class DownloadCode {

	function __construct() {
		global $wgHooks;
		# Hooks for pre-Vector and Vector addtabs.
		$wgHooks['SkinTemplateNavigation'][] = $this;
		$wgHooks['ArticleAfterFetchContent'][] = $this;
	}

	/**
	 * Add Download code to actions tabs in vector based skins
	 */
	function onSkinTemplateNavigation( $skin, &$actions ) {
		if ($skin->getTitle()->getNamespace() != NS_MAIN) {
		   return true;
		}
		$titleDownloadCode = Title::makeTitle( NS_SPECIAL, "Downloadcode/" . $skin->getTitle()->getPrefixedDbKey());
		if ( $skin->isRevisionCurrent() ) {
		    $urlDownloadCode = $titleDownloadCode->getLocalURL();
		} else {
		    $urlDownloadCode = $titleDownloadCode->getLocalURL('oldid='.$skin->getRevisionId());
		}
		$actions['views']['deletecode'] = array(
			'class' => false,
			'text' => wfMsg( 'downloadcode' ),
			'href' => $urlDownloadCode,
		);
		return true;
	}

	function onArticleAfterFetchContent( &$article, &$content ) {
		# Add download code notice to bottom
		if ($article->getTitle()->getNamespace() == NS_MAIN) {
			$titleDownloadCode = Title::makeTitle( NS_SPECIAL, "Downloadcode/" . $article->getTitle()->getPrefixedDbKey());
			if ( $article->getOldID() == 0 ) {
			    $urlDownloadCode = $titleDownloadCode->getFullURL();
			} else {
			    $urlDownloadCode = $titleDownloadCode->getFullURL('oldid='.$article->getOldID());
			}
			$content .= "\n\n" . wfMsg('downloadcodebottom', $urlDownloadCode);
		}
		return true;
	}
}
