<?php
# Copyright (C) 2006-2012 Derrick Coetzee <dc@moonflare.com>
# 
# A minor extension for hiding certain page text from readers who are
# not logged in.
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

$wgExtensionFunctions[] = "wfShowOnlyTo";

function wfShowOnlyTo() {
    global $wgParser;
    # register the extension with the WikiText parser
    $wgParser->setHook( "showonlyto" /*tag*/, "showOnlyToText" /*hook function*/);
}

# The callback function for converting the input text to HTML output
function showOnlyToText( $input, $argv ) {
    global $wgUser;
    # $argv is an array containing any arguments passed to the
    # extension like <example argument="foo" bar>..
    # Put this on the sandbox page:  (works in MediaWiki 1.5.5)
    #   <example argument="foo" argument2="bar">Testing text **example** in between the new tags</example>

    if (array_key_exists('anonymous', $argv))
        return $wgUser->isLoggedIn() ? '' : $input;
    else if (array_key_exists('registered', $argv))
        return $wgUser->isLoggedIn() ? $input : '';
}
?>
