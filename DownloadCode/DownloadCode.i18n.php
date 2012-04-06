<?php
# Copyright (C) 2006-2012 Derrick Coetzee <dc@moonflare.com>
# 
# Internationalisation file for the extension DownloadCode.
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

$messages = array();

/** English
 */
$messages['en'] = array(
'downloadcode' => 'Download code',
'downloadcodenoargs' => 'The download code feature allows you to automatically extract and download code from articles. Use it by clicking the "download code" tab at the top of the article, or by using a URL of the form "http://literateprograms.org/Special:Downloadcode/Article_name".',

'downloadcodenopage' => 'You have attempted to access code for an article that does not exist. [[You have attempted to access code for an article that does not exist. Create this article first. If the article used to exist, it may have been deleted.',
'downloadcodenocode' => 'The article you attempted to download code for does not yet have any
associated code files. You can add code files by adding a chunk with a name
that looks like a filename, as in:

<pre>
<<foo.c>>=
int main() { return 0; }
</pre>

Chunks which are not included in any code file chunk will not be output.',

'downloadcodebottom' => '<span class="plainlinks">\'\'\'[$1 Download code]\'\'\'</span>',

'copyrightcomment' => 'Copyright (c) 2006 the authors listed at the following URL:
http://literateprograms.org/$1?action=history&offset=$2

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Retrieved from: http://literateprograms.org/$1?oldid=$3
',

'commentsbyextension' => 'c cc cpp java cs php h hh hpp dylan lid : /* */
hs : {- -}
pl perl sh bash csh awk mak makefile ruby rb python py : #
vb : \'
bas : REM
asm lisp : ;
for f77 f90 : C
sql ada occ : --',

'syntaxhighlightingstylesheet' => '',
'syntaxhighlightingregexps' => '',
'imagepageheader' => '',
'create' => 'Create',
'implementationlistheader' => ':\'\'\'Other implementations\'\'\': $1',
'languagenamemapping' => 'C_Plus_Plus C++
C_Sharp C#
Managed_C_Plus_Plus Managed C++
F_Sharp F#',
);
