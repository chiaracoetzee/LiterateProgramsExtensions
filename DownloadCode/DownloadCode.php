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
	$wgDownloadCodeBanner = false;
	$wgDownloadCodeListAlternatives = false;
}

class DownloadCode {
	var $db;

	function __construct() {
		global $wgHooks;
		# Hooks for pre-Vector and Vector addtabs.
		$wgHooks['SkinTemplateNavigation'][] = $this;
		$wgHooks['ArticleAfterFetchContent'][] = $this;
		$this->db = wfGetDB( DB_SLAVE );
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

	function addDownloadCodeBanner($title, $oldID, &$content ) {
		# Add download code notice to bottom
		$titleDownloadCode = Title::makeTitle( NS_SPECIAL, "Downloadcode/" . $title->getPrefixedDbKey());
		if ( $oldID == 0 ) {
		    $urlDownloadCode = $titleDownloadCode->getFullURL();
		} else {
		    $urlDownloadCode = $titleDownloadCode->getFullURL('oldid='.$oldID);
		}
		$content .= "\n\n" . wfMsg('downloadcodebottom', $urlDownloadCode);
	}

	function listAlternatives( $title, &$content ) {
		$encTitlePattern = $this->db->addQuotes( preg_replace( '/\(.*\)/', '(%)', $title->getDBKey() ) );
		$encTitle = $this->db->addQuotes($title);
		$res = $this->db->query("SELECT DISTINCT page_title FROM page WHERE page_title LIKE $encTitlePattern AND page_is_redirect=0 ORDER BY upper(page_title)", $fname);
		$impl_count = 0;
		$langNameMapping = wfMsg('languagenamemapping');
		$linksWikitext = '';
		$first = 0;
		while ( $page = $this->db->fetchObject( $res ) ) {
			if ($first == 0) {
			    $first = 1;
			} else {
			    $linksWikitext .= " | ";
			}
			$impl_count++;

			$pageTitle = Title::makeTitle($page->page_namespace, $page->page_title);
			preg_match( '/\((.*)\)/', $pageTitle->getText(), $matches);
			$pageProgLangText = $matches[1];
			preg_match( '/\((.*)\)/', $pageTitle->getDBKey(), $matches);
			$pageProgLangShortKey = $pageProgLangKey = $matches[1];
			if (preg_match( '/^(.*)[,\/]/',$pageProgLangKey, $matches)) {
			    $pageProgLangShortKey = $matches[1];
			}
			if (preg_match( '/' . $pageProgLangShortKey . ' ([^\n]*)\n/', $langNameMapping, $matches)) {
			    $pageProgLangText = $matches[1];
			}
			$pageProgLangText = str_replace(' ', '&nbsp;', $pageProgLangText);
			$linksWikitext .= '[[' . $page->page_title . '|' . $pageProgLangText . ']]';
		}
		$this->db->freeResult($res);

		if ($impl_count > 1) {

		    $content = wfMsg( 'implementationlistheader', $linksWikitext ) . "\n" . $content;
		}
	}

	function onArticleAfterFetchContent( &$article, &$content ) {
		global $wgDownloadCodeBanner, $wgDownloadCodeListAlternatives;
		$request = $article->getContext()->getRequest();
		if ($request->getVal('action') != '' &&
		    $request->getVal('action') != 'view')
		{
		   	return true;
		}
		$title = $article->getTitle();
		if ($title->getNamespace() == NS_MAIN) {
			if ($wgDownloadCodeBanner) {
			   	$this->addDownloadCodeBanner($title, $article->getOldID(), $content);
			}

			if ($wgDownloadCodeListAlternatives) {
			   	$this->listAlternatives( $title, $content );
			}
		}
		return true;
	}
}
