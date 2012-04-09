<?php
# Copyright (C) 2006-2012 Derrick Coetzee <dc@moonflare.com>
# 
# A special for running noweb on the wikitext and zipping up and downloading
# the resulting code.
# This is derived from SpecialExport.php, so I must release it under GPL.
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
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

/**
 * @package MediaWiki
 * @subpackage SpecialPage
 */
class SpecialDownloadCode extends SpecialPage {
        function __construct() {
                parent::__construct( 'DownloadCode' );
        }

	var $oldid;
	var $archive;
	var $file;
	var $newlines;
	var $subdir;
	var $db;

	var $pageCallback = null;
	var $revCallback = null;

        function execute( $par ) {
		global $wgOut, $wgRequest;
                $this->setHeaders();

		$title = $wgRequest->getText( 'title' );
		$parts = explode( '/', $title );
		$this->page = implode( '/', array_slice($parts, 1) );

		$this->oldid = $wgRequest->getInt( 'oldid' );
		$this->archive = $wgRequest->getText( 'archive' );
		$this->file = $wgRequest->getText( 'file' );
		$this->newlines = $wgRequest->getText( 'newlines' );
		$this->subdir = $wgRequest->getText( 'subdir' );
		if ($this->newlines == '') {
		    $this->newlines = 'crlf';
		}


		if( $this->page == '' ) {
		    $wgOut->addWikiText( wfMsg( "Downloadcodenoargs" ) );
		    return;
		}

		$this->db = wfGetDB( DB_SLAVE );
		$this->pageByName( $this->page );
        }

	/**
	 * Set a callback to be called after each revision in the output
	 * stream is closed. The callback will be passed a database row
	 * object with the revision data.
	 *
	 * A set callback can be removed by passing null here.
	 *
	 * @param mixed $callback
	 */
	function setRevisionCallback( $callback ) {
		$this->revCallback = $callback;
	}
	
	/**
	 * @param Title $title
	 */
	function pageByTitle( $title ) {
		return $this->dumpFrom(
			'page_namespace=' . $title->getNamespace() .
			' AND page_title=' . $this->db->addQuotes( $title->getDbKey() ) );
	}
	
	function pageByName( $name ) {
		$title = Title::newFromText( $name );
		if( is_null( $title ) ) {
			return WikiError( "Can't download code for invalid title" );
		} else {
			return $this->pageByTitle( $title );
		}
	}
	
	// -------------------- private implementation below --------------------
	
	function dumpFrom( $cond = '' ) {
		$fname = 'WikiDownloadcode::dumpFrom';
		wfProfileIn( $fname );
		
		$page     = $this->db->tableName( 'page' );
		$revision = $this->db->tableName( 'revision' );
		$text     = $this->db->tableName( 'text' );
		
		if( $this->oldid == 0 ) {
			$join = 'page_id=rev_page AND page_latest=rev_id';
		} else {
			$join = "page_id=rev_page AND rev_id='$this->oldid'";
		}
		$where = ( $cond == '' ) ? '' : "$cond AND";
		
		if( $cond == '' ) {
			// Optimization hack for full-database dump
			$pageindex = 'FORCE INDEX (PRIMARY)';
			$revindex = 'FORCE INDEX(page_timestamp)';
		} else {
			$pageindex = '';
			$revindex = '';
		}
		$result = $this->db->query(
			"SELECT * FROM
				$page $pageindex,
				$revision $revindex,
				$text
				WHERE $where $join AND rev_text_id=old_id
				ORDER BY page_id", $fname );
		$wrapper = $this->db->resultObject( $result );
		$this->outputStream( $wrapper );
		
		wfProfileOut( $fname );
	}
	
	/**
	 * Runs through a query result set dumping page and revision records.
	 * The result set should be sorted/grouped by page to avoid duplicate
	 * page records in the output.
	 *
	 * The result set will be freed once complete. Should be safe for
	 * streaming (non-buffered) queries, as long as it was made on a
	 * separate database connection not managed by LoadBalancer; some
	 * blob storage types will make queries to pull source data.
	 *
	 * @param ResultWrapper $resultset
	 * @access private
	 */
	function outputStream( $resultset ) {
		global $wgOut;
		$last = null;
                $found_result = 0;
		while( $row = $resultset->fetchObject() ) {
			# If it's cached and this is an If-Modified-Since request, no need to do anything
			if ($wgOut->checkLastModified($row->rev_timestamp)) {
			    wfDebug("DownloadCode: page/archive in cache up-to-date, page last modified " . $row->rev_timestamp . "\n");
			    return;
			}

			if( is_null( $last ) ||
				$last->page_namespace != $row->page_namespace ||
				$last->page_title     != $row->page_title ) {
				if( isset( $last ) ) {
					$this->closePage( $last );
				}
				$this->openPage( $row );
				$last = $row;
			}
			$this->dumpRev( $row );
                        $found_result = 1;
		}
                if( !$found_result ) {
		    $wgOut->addWikiText( wfMsg( "Downloadcodenopage" ) );
		    return;
                }
		if( isset( $last ) ) {
			$this->closePage( $last );
		}
		$resultset->free();
	}
	
	/**
	 * Opens a <page> section on the output stream, with data
	 * from the given database row.
	 *
	 * @param object $row
	 * @access private
	 */
	function openPage( $row ) {
	}
	
	/**
	 * Closes a <page> section on the output stream.
	 * If a per-page callback has been set, it will be called
	 * and passed the last database row used for this page.
	 *
	 * @param object $row
	 * @access private
	 */
	function closePage( $row ) {
	}

	function tempdir( $dir, $prefix='', $mode=0700 )
	{
	    if (substr($dir, -1) != '/') $dir .= '/';

	    do {
	        $path = $dir.$prefix.mt_rand(0, 9999999);
	    } while (!mkdir($path, $mode));
	    wfDebug("DownloadCode using temporary directory " . $path . "\n");

	    return $path;
	}

        function file_put_contents( $filename, $contents ) {
	    $handle = fopen($filename, 'w');
	    fwrite($handle, $contents);
	    fclose($handle);
	}

        function commentify ( $text, $extension ) {
            $start_block_comment = array();
            $end_block_comment = array();
            $line_comment = array();
            foreach(explode("\n", wfMsg("commentsbyextension")) as $commentsbyextension_line) {
                $sections = explode(':', $commentsbyextension_line);
                if (count($sections) != 2) continue;
                $comment_markers = split("[ \t]+", trim($sections[1]));
                foreach(split("[ \t]+", trim($sections[0])) as $extension_entry) {
                    if (count($comment_markers) == 1) {
                        $line_comment[$extension_entry] = $comment_markers[0];
                    } else if (count($comment_markers) == 2) {
                        $start_block_comment[$extension_entry] = $comment_markers[0];
                        $end_block_comment[$extension_entry] = $comment_markers[1];
                    }
                }
            }
            
	    if (array_key_exists($extension, $start_block_comment)) {
                return $start_block_comment[$extension] . ' ' . $text . "\n" . $end_block_comment[$extension] . "\n\n";
	    } else if (array_key_exists($extension, $line_comment)) {
                $result = "";
                foreach(explode("\n", $text) as $text_line) {
                    $result .= $line_comment[$extension] . ' ' . $text_line . "\n";
                }
                return $result . "\n";
	    } else {
                // If we don't know how to comment it, we better just omit it
                return "";
	    }
        }
	
	function articleTextToNoweb( $text ) {
	    $code_text = '';
	    while (($open_pos = strpos($text, '<codeblock')) !== false) {
		$close_open_pos = strpos($text, '>', $open_pos);
		if ($close_open_pos == false) break; /* Broken tag, bail */
		$close_pos = strpos($text, '</codeblock>');
		if ($close_pos == false) break; /* Just ignore unclosed block */
		$code_text_segment = substr($text, $close_open_pos + 1, $close_pos - ($close_open_pos + 1));
		if ($code_text{strlen($code_text)-1} != "\n") {
		    $code_text_segment .= "\n";
		}

		// Some codeblocks do not start with a chunk identifier
		// such as demonstration code (discouraged but permitted).
		// If such code contains << or >> markers it will confuse
		// noweb. So we strip it out.
		if (preg_match('/(<<([^>]*)>>=)/', $code_text_segment, $matches, PREG_OFFSET_CAPTURE)) {
		    $code_text_segment = substr($code_text_segment, $matches[0][1]);
                } else {
		    $code_text_segment = '';
                }

		$code_text .= "$code_text_segment@ text\n\n";
		$text = substr($text, $close_pos + 1);
	    }
            return $code_text;
        }

        function rowToNoweb( $row, $prefix ) {
            global $wgParser, $wgUser;

	    $text = Revision::getRevisionText( $row );

	    # If it's a redirect, download that instead
	    $rt = Title::newFromRedirect( $text );
	    # process if title object is valid and not special:userlogout
	    if ( $rt && $rt->getInterwiki() == '' && $rt->getNamespace() == NS_MAIN) {
		    return $this->pageByName( $rt->getText() );
	    }

            $title = Title::newFromText($row->page_title);
            $parserOptions = ParserOptions::newFromUser($wgUser);
	    $text = $wgParser->preprocess( $text, $title, $parserOptions );

            $text = preg_replace( '/<<([^\n#>]*)>>/',"<<$prefix$1>>",$text);
	    return $this->articleTextToNoweb($text);
        }

	function randomstring($size) {
	    $alphabet = "abcdefghijklmnopqrstuvwxyz1234567890";
	    for($i=0; $i<$size; $i++) {
	        $result .= $alphabet{rand(0,35)};
	    }
	    return $result;
	}

	/**
	 * Dumps a <revision> section on the output stream, with
	 * data filled in from the given database row.
	 *
	 * @param object $row
	 * @access private
	 */
	function dumpRev( $row ) {
		global $wgParser, $wgUser, $wgOut, $wgTitle;
		$fname = 'WikiDownloadcode::dumpRev';
                $oldids_acquired = array();
		wfProfileIn( $fname );
		$text = $this->rowToNoweb($row,'');
                while (preg_match( '/<<(.*)?#(.*)?#(.*)?>>/',$text,$matches))
                {
                    $article=$matches[1];
                    $oldid=$this->db->addQuotes($matches[2]);
                    $chunk=$matches[3];
                    $prefix = $article . " "; # Space tells noweb it's not an output chunk
		    if (!in_array($oldid, $oldids_acquired)) {
			$page_table     = $this->db->tableName( 'page' );
			$revision_table = $this->db->tableName( 'revision' );
			$text_table     = $this->db->tableName( 'text' );
			$result = $this->db->query(
				"SELECT * FROM $page_table, $revision_table, $text_table
					WHERE page_id=rev_page AND rev_id=$oldid AND rev_text_id=old_id", $fname );
			$resultset = $this->db->resultObject( $result );
			$subrow = $resultset->fetchObject();
			$text .= $this->rowToNoweb($subrow, $prefix);
                        array_push($oldids_acquired, $oldid);
                    }
		    $text = preg_replace( '/<<(.*)?#(.*)?#(.*)?>>/',"<<$prefix$chunk>>",$text,1);
                }

                $dirname = $this->tempdir('/tmp', 'litprog');
                $olddir = getcwd();
                chdir($dirname);

                $this->file_put_contents('input.nw', $text);
                global $wgNowebPath;
                `$wgNowebPath -t input.nw > noweb.log 2> noweb.log`;
		if (file_get_contents($dirname . "/noweb.log") == '') {
		    unlink($dirname . "/input.nw");
		    unlink($dirname . "/noweb.log");
                }

                $files_found = 0;
                $last_file_found = 0;
                $handle = opendir($dirname);
                while (false !== ($file = readdir($handle))) {
		    if ($file != '.' && $file != '..') {
                        $filepath = $dirname . '/' . $file;
                        $extension = '';
                        if (ereg('\.([^.]*)$', $file, $regs)) {
                            $extension = $regs[1];
                        }
                        $extension = strtolower($extension);
                        if ($extension == '' && ereg(strtolower($file), '^makefile')) {
                            $extension = 'makefile';
                        }
         
                        $contents = file_get_contents($filepath);
                        $contents_lines = explode("\n",$contents);
                        $found_exec_line = 0;
                        $header = '';
                        if (ereg('^#!/', $contents_lines[0]))
                        {
                            chmod($file, 0755); # Probably a script, make executable
                            $found_exec_line = 1;
                            $header = $contents_lines[0] . "\n";
                            $contents_lines = array_slice($contents_lines, 1);
                            
                        }
                        if (($extension == 'bat' || $extension == 'cmd') &&
                            ereg('^@?echo off', $contents_lines[0]))
			{
                            $header = $contents_lines[0] . "\n";
                            $contents_lines = array_slice($contents_lines, 1);
			}
                        if (!$found_exec_line && $this->newlines == 'crlf') {
                            $contents = implode("\r\n", $contents_lines);
                        } else {
                            $contents = implode("\n", $contents_lines);
                        }

                        $this->file_put_contents($filepath,
                            $header . 
                            $this->commentify(
                                wfMsg("copyrightcomment", $row->page_title, $row->rev_timestamp, $row->rev_id, date("Y")),
                                $extension) .
                            $contents);
                        
                        if ($extension == 'c') {
                            `gcc -Wall -O2 -ansi -c '$filepath' -o /dev/null 2>> build.log`;
                        }
                        else if ($extension == 'cpp' || $extension == 'cc') {
                            `g++ -Wall -O2 -ansi -c '$filepath' -o /dev/null 2>> build.log`;
                        }
                        else if ($extension == 'perl') {
                            `perl -wc '$filepath' 2>> build.log`;
                        }
                        else if ($extension == 'ruby' || $extension == 'rb') {
                            `ruby -wc '$filepath' 2>> build.log`;
                        }
#                        else if ($extension == 'hs') {
#                            `ghc -C -O '$filepath' -o /dev/null 2>> build.log`;
#                            $hi_filepath = substr($filepath, 0, strlen($filepath)-1) . 'i';
#			    if (file_exists($hi_filepath)) {
#			        unlink($hi_filepath);
#			    }
#                        }
                        else if ($extension == 'py') {
                            `python -c 'import py_compile; py_compile.compile("$filepath")' 2>> build.log`;
                            $pyc_filepath = $filepath . 'c';
			    if (file_exists($pyc_filepath)) {
                                unlink($pyc_filepath);
                            }
                        }
			$files_found++;
			$last_file_found = $file;
                    }
		}
                closedir($handle);

                $build_log_path = $dirname . '/' . 'build.log';
		if (file_exists($build_log_path) && file_get_contents($build_log_path) == '') {
		    unlink($build_log_path);
		}
                if ($files_found == 0) {
                    $wgOut->addWikiText( wfMsg('downloadcodenocode'));
                    `rm -fr $dirname`;
                    return;
                }

                if ($this->archive == '' && $this->file == '') {
                    $wgOut->addWikiText("<small>''Back to [[$row->page_title]]''</small>\n");
                    $archive_download_url = $this->oldid != 0 ? Skin::makeSpecialUrl("Downloadcode/$row->page_title", 'oldid='.$this->oldid.'&archive=') : Skin::makeSpecialUrl("Downloadcode/$row->page_title", 'archive=');
                    $file_download_url = $this->oldid != 0 ? Skin::makeSpecialUrl("Downloadcode/$row->page_title", 'oldid='.$this->oldid.'&file=') : Skin::makeSpecialUrl("Downloadcode/$row->page_title", 'file=');
                    $wgOut->addHTML("<p><b>Download for Windows</b>: " . ($files_found == 1 ? "<a href=\"{$file_download_url}$last_file_found\">single file</a>, " : "") . "<a href=\"{$archive_download_url}zip\">zip</a></p>");
                    $wgOut->addHTML("<p><b>Download for UNIX</b>: " . ($files_found == 1 ? "<a href=\"{$file_download_url}$last_file_found&newlines=lf\">single file</a>, " : "") . "<a href=\"{$archive_download_url}zip&newlines=lf&subdir=1\">zip</a>, <a href=\"{$archive_download_url}tar.gz&newlines=lf&subdir=1\">tar.gz</a>, <a href=\"{$archive_download_url}tar.bz2&newlines=lf&subdir=1\">tar.bz2</a></p>");
		    $handle = opendir($dirname);
		    while (false !== ($file = readdir($handle))) {
			if ($file != '.' && $file != '..') {
                            $filepath = $dirname . '/' . $file;
			    $extension = '';
			    if (ereg('\.([^.]*)$', $file, $regs)) {
				$extension = $regs[1];
			    }
                            $contents = file_get_contents($filepath);
			    $file_download_url = $this->oldid != 0 ? Skin::makeSpecialUrl("Downloadcode/$row->page_title", 'oldid='.$this->oldid.'&file=') : Skin::makeSpecialUrl("Downloadcode/$row->page_title", 'file=');
			    $wgOut->addHTML("<h2><a href=\"{$file_download_url}$file\">$file</a></h2>\n\n");
                            $wgOut->addWikiText("<codeblock language=$extension linenumbers>" . preg_replace('/<</', '@<<', $contents) . "</codeblock>\n\n");
                        }
                    }
                    closedir($handle);
                    `rm -fr $dirname`;
                    return;
                } else if ($this->file != '') {
		    # Send over an individual file
		    $wgOut->disable();
	            header( "Content-type: text/plain; charset=utf-8" );
		    $zip_filename = strtr($this->file, '<>:"/\|', "{};'--!"); # Windows doesn't like these
		    header( "Content-Disposition: attachment; filename=\"$this->file\"" );
		    header("Cache-Control: must-revalidate");
		    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		    print(file_get_contents($dirname . "/$this->file"));
                } else if (in_array($this->archive, array('zip','tar.gz','tar.bz2'))) {
		    if ($this->subdir == 1) {
                        $subdir_name = $row->page_title;
			`mkdir '$subdir_name'`;
			`mv * '$subdir_name'`;
		    }
                    if ($this->archive == 'zip') {
		        `zip -rp code.zip *`;
                    } else if ($this->archive == 'tar.gz') {
		        `tar -zcf code.tar.gz *`;
                    } else if ($this->archive == 'tar.bz2') {
		        `tar -jcf code.tar.bz2 *`;
                    }

		    # Send over the archive
		    $wgOut->disable();
                    if ($this->archive == 'zip') {
                        header( "Content-type: application/zip; charset=utf-8" );
                    } else if ($this->archive == 'tar.gz') {
                        header( "Content-type: application/x-compressed-tar; charset=utf-8" );
                    } else if ($this->archive == 'tar.bz2') {
                        header( "Content-type: application/x-bzip2; charset=utf-8" );
                    }
		    $zip_filename = strtr($row->page_title, '<>:"/\|', "{};'--!"); # Windows doesn't like these
		    header( "Content-Disposition: attachment; filename=\"$zip_filename.$this->archive\"" );
		    # Don't ever cache - want to get updated archives after editing
		    # Don't use the no-cache option though, as this triggers a bug in IE
		    # if they click to download then click Open (file not on disk).
		    header("Cache-Control: must-revalidate");
		    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		    print(file_get_contents($dirname . "/code.$this->archive"));
                } else {
                    $wgOut->addWikiText( wfMsg('downloadcodeunsupportedarchive'));
                    `rm -fr $dirname`;
                    return;
                }
		
               `rm -fr $dirname`;

		wfProfileOut( $fname );
		
		if( isset( $this->revCallback ) ) {
			call_user_func( $this->revCallback, $row );
		}
	}

}

?>
