<?php
# Copyright (C) 2006-2012 Derrick Coetzee <dc@moonflare.com>
# 
# Entry point for DownloadCode extension.
# 
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or 
# (at your option) any later version.
# 
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
# http://www.gnu.org/copyleft/gpl.html

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
	}


	/**
	 * Perform the export operation
	 */
	function onUnknownAction( $action, $article ) {
		global $wgOut, $wgUser, $wgTitle, $wgParser, $wgRequest;
		global $wgServer, $wgArticlePath, $wgScriptPath, $wgUploadPath, $wgUploadDirectory, $wgScript;

		if( $action == 'pdfbook' ) {

			$title = $article->getTitle();
			$opt = ParserOptions::newFromUser( $wgUser );

			# Log the export
			$msg = wfMsg( 'pdfbook-log', $wgUser->getUserPage()->getPrefixedText() );
			$log = new LogPage( 'pdf', false );
			$log->addEntry( 'book', $wgTitle, $msg );

			# Initialise PDF variables
			$format  = $wgRequest->getText( 'format' );
			$notitle = $wgRequest->getText( 'notitle' );
			$layout  = $format == 'single' ? '--webpage' : '--firstpage toc';
			$charset = $this->setProperty( 'Charset',     'iso-8859-1' );
			$left    = $this->setProperty( 'LeftMargin',  '1cm' );
			$right   = $this->setProperty( 'RightMargin', '1cm' );
			$top     = $this->setProperty( 'TopMargin',   '1cm' );
			$bottom  = $this->setProperty( 'BottomMargin','1cm' );
			$font    = $this->setProperty( 'Font',	     'Arial' );
			$size    = $this->setProperty( 'FontSize',    '8' );
			$ls      = $this->setProperty( 'LineSpacing', 1 );
			$linkcol = $this->setProperty( 'LinkColour',  '217A28' );
			$levels  = $this->setProperty( 'TocLevels',   '2' );
			$exclude = $this->setProperty( 'Exclude',     array() );
			$width   = $this->setProperty( 'Width',       '' );
			$width   = $width ? "--browserwidth $width" : '';
			if( !is_array( $exclude ) ) $exclude = split( '\\s*,\\s*', $exclude );
 
			# Select articles from members if a category or links in content if not
			if( $format == 'single' ) $articles = array( $title );
			else {
				$articles = array();
				if( $title->getNamespace() == NS_CATEGORY ) {
					$db     = wfGetDB( DB_SLAVE );
					$cat    = $db->addQuotes( $title->getDBkey() );
					$result = $db->select(
						'categorylinks',
						'cl_from',
						"cl_to = $cat",
						'PdfBook',
						array( 'ORDER BY' => 'cl_sortkey' )
					);
					if( $result instanceof ResultWrapper ) $result = $result->result;
					while ( $row = $db->fetchRow( $result ) ) $articles[] = Title::newFromID( $row[0] );
				}
				else {
					$text = $article->fetchContent();
					$text = $wgParser->preprocess( $text, $title, $opt );
					if ( preg_match_all( "/^\\*\\s*\\[{2}\\s*([^\\|\\]]+)\\s*.*?\\]{2}/m", $text, $links ) )
						foreach ( $links[1] as $link ) $articles[] = Title::newFromText( $link );
				}
			}

			# Format the article(s) as a single HTML document with absolute URL's
			$book = $title->getText();
			$html = '';
			$wgArticlePath = $wgServer.$wgArticlePath;
			$wgPdfBookTab  = false;
			$wgScriptPath  = $wgServer.$wgScriptPath;
			$wgUploadPath  = $wgServer.$wgUploadPath;
			$wgScript      = $wgServer.$wgScript;
			foreach( $articles as $title ) {
				$ttext = $title->getPrefixedText();
				if( !in_array( $ttext, $exclude ) ) {
					$article = new Article( $title );
					$text    = $article->fetchContent();
					$text    = preg_replace( "/<!--([^@]+?)-->/s", "@@" . "@@$1@@" . "@@", $text ); # preserve HTML comments
					if( $format != 'single' ) $text .= "__NOTOC__";
					$opt->setEditSection( false );    # remove section-edit links
					$out     = $wgParser->parse( $text, $title, $opt, true, true );
					$text    = $out->getText();
					$text    = preg_replace( "|(<img[^>]+?src=\")(/.+?>)|", "$1$wgServer$2", $text );      # make image urls absolute
					$text    = preg_replace( "|<div\s*class=['\"]?noprint[\"']?>.+?</div>|s", "", $text ); # non-printable areas
					$text    = preg_replace( "|@{4}([^@]+?)@{4}|s", "<!--$1-->", $text );                  # HTML comments hack
					$ttext   = basename( $ttext );
					$h1      = $notitle ? "" : "<center><h1>$ttext</h1></center>";
					$html   .= utf8_decode( "$h1$text\n" );
				}
			}

			# $wgPdfBookTab = false; If format=html in query-string, return html content directly
			if( $format == 'html' ) {
				$wgOut->disable();
				header( "Content-Type: text/html" );
				header( "Content-Disposition: attachment; filename=\"$book.html\"" );
				print $html;
			}
			else {
				# Write the HTML to a tmp file
				$file = "$wgUploadDirectory/" . uniqid( 'pdf-book' );
				$fh = fopen( $file, 'w+' );
				fwrite( $fh, $html );
				fclose( $fh );

				$footer = $format == 'single' ? "..." : ".1.";
				$toc    = $format == 'single' ? "" : " --toclevels $levels";

				# Send the file to the client via htmldoc converter
				$wgOut->disable();
				header( "Content-Type: application/pdf" );
				header( "Content-Disposition: attachment; filename=\"$book.pdf\"" );
				$cmd  = "--left $left --right $right --top $top --bottom $bottom";
				$cmd .= " --header ... --footer $footer --headfootsize 8 --quiet --jpeg --color";
				$cmd .= " --bodyfont $font --fontsize $size --fontspacing $ls --linkstyle plain --linkcolor $linkcol";
				$cmd .= "$toc --no-title --format pdf14 --numbered $layout $width";
				$cmd  = "htmldoc -t pdf --charset $charset $cmd $file";
				putenv( "HTMLDOC_NOCGI=1" );
				passthru( $cmd );
				@unlink( $file );
			}
			return false;
		}

		return true;
	}


	/**
	 * Return a property for htmldoc using global, request or passed default
	 */
	function setProperty( $name, $default ) {
		global $wgRequest;
		if ( $wgRequest->getText( "pdf$name" ) )   return $wgRequest->getText( "pdf$name" );
		if ( isset( $GLOBALS["wgPdfBook$name"] ) ) return $GLOBALS["wgPdfBook$name"];
		return $default;
	}

	/**
	 * Add PDF to actions tabs in vector based skins
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
}

/*
$wgHooks['ParserBeforeStrip'][] = 'wfAddDownloadCodeFooter';

function wfAddDownloadCodeFooter ( &$parser, &$text, &$strip_state ) {
	global $wgOut;
	# Add download code notice to bottom
	if ($parser->getTitle()->getNamespace() == NS_MAIN) {
		$titleDownloadCode = Title::makeTitle( NS_SPECIAL, "Downloadcode/" . $parser->getTitle()->getPrefixedDbKey());
		if ( $oldid == 0 ) {
		    $urlDownloadCode = $titleDownloadCode->getFullURL();
		} else {
		    $urlDownloadCode = $titleDownloadCode->getFullURL('oldid='.$oldid);
		}
		$wgOut->addWikiText( wfMsg('downloadcodebottom', $urlDownloadCode));
	}
	return true;
}
*/