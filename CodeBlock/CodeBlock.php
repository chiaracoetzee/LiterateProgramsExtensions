<?php
# Copyright (C) 2006-2012 Derrick Coetzee <dc@moonflare.com>
# 
# An extension for formatting and syntax highlighting for <codeblock>
# blocks, which may contain noweb formatting. These are also recognized
# by the DownloadCode extension.
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

$wgExtensionFunctions[] = "wfCodeBlockExtension";

function wfCodeBlockExtension() {
    global $wgParser;
    # register the extension with the WikiText parser
    $wgParser->setHook( "codeblock" /*tag*/, "renderCodeBlock" /*hook function*/);
}

function parse_syntax_highlighting_stylesheet( $text ) {
    $start_stylesheet = array();
    $stop_stylesheet = array();
    $text_lines = explode("\n",$text);
    for($i=0; $i<count($text_lines); $i+=3) {
        $start_stylesheet[$text_lines[$i]] = $text_lines[$i+1];
        $stop_stylesheet[$text_lines[$i]] = $text_lines[$i+2];
    }
    return array($start_stylesheet, $stop_stylesheet);
}

class SyntaxHighlightingRegexpsParser {

    var $rule_stack = array();
    var $current_language_name = '';
    var $current_element_name = '';
    var $rules_by_language = array();

    function SyntaxHighlightingRegexpsParser() { }

    function parse_syntax_highlighting_regexps_start_element($parser, $name, $attrs)
    {
	if ($name == 'RULE' || $name == 'LANGUAGE') {
	    array_unshift($this->rule_stack, array());
            $this->rule_stack[0]['regexp'] = '';
            $this->rule_stack[0]['style'] = '';
            $this->rule_stack[0]['rules'] = array();
	}
        if ($name == 'LANGUAGE') {
	    $this->current_language_name = $attrs['NAME'];
	    if (array_key_exists('INHERIT', $attrs)) {
		array_unshift($this->rule_stack, $this->rules_by_language[$attrs['INHERIT']]);
	    }
	}
	$this->current_element_name = $name;
    }

    function parse_syntax_highlighting_regexps_end_element($parser, $name)
    {
	$this->current_element_name = '';
	if ($name == 'RULE') {
	    $rule = array_shift($this->rule_stack);
	    array_push($this->rule_stack[0]['rules'], $rule);
	} else if ($name == 'LANGUAGE') {
	    $this->rules_by_language[$this->current_language_name] =
		array_shift($this->rule_stack);
	} 
    }

    function parse_syntax_highlighting_regexps_character_data($parser, $data)
    {
	if (($this->current_element_name == 'REGEX' || $this->current_element_name == 'STYLE') && $data != '<![CDATA[' && $data != ']]>') {
            $property = strtolower($this->current_element_name);
	    if (!isset($this->rule_stack[0][$property])) { $this->rule_stack[0][$property] = ''; }
	    $this->rule_stack[0][$property] .= $data;
        }
    }

    function parse( $xmltext ) {
	$this->rule_stack = array();
	$this->current_language_name = '';
	$this->current_element_name = '';
	$this->rules_by_language = array();

	$xml_parser = xml_parser_create();
	xml_set_element_handler($xml_parser, array(&$this,"parse_syntax_highlighting_regexps_start_element"), array(&$this, "parse_syntax_highlighting_regexps_end_element"));
	xml_set_default_handler($xml_parser, array(&$this, "parse_syntax_highlighting_regexps_character_data"));
	xml_set_character_data_handler($xml_parser, array(&$this, "parse_syntax_highlighting_regexps_character_data"));

       if (!xml_parse($xml_parser, $xmltext, true)) {
	   print (sprintf("XML error in MediaWiki:SyntaxHighlightingRegexps: %s at line %d",
			   xml_error_string(xml_get_error_code($xml_parser)),
			   xml_get_current_line_number($xml_parser)));
       }
       return $this->rules_by_language;
    }

}

class SyntaxHighlighter {
    var $start_stylesheet;
    var $stop_stylesheet;

    function SyntaxHighlightingRegexpsParser() { }

    function print_rules($regexp_rules, $indent) {
	$indent_spaces = '';
	for($i=0; $i<$indent; $i++) {
	    $indent_spaces .= ' ';
	}
	foreach($regexp_rules as $rule) { 
	    $regexp = $rule['regex'];
	    $style = $rule['style'];
	    print("{$indent_spaces}Rule:\n$indent_spaces  Regexp:$regexp\n$indent_spaces  Style:$style\n");
	    print_rules($rule['rules'], $indent + 2);
	}
    }

    function add_highlighting( $input, $rules ) {
        $result = '';
        $snippets = array();
        for($j=0; $j<count($rules); $j++) {
            $rule = $rules[$j];
	    if (!$rule['style']) {
	        continue;
	    }
	    preg_match_all('/' . str_replace('/', '\/', $rule['regex']) . '/sm',
                           $input, $matches, PREG_OFFSET_CAPTURE);
            for($i=0; $i<count($matches[0]); $i++) {
                $start = $matches[0][$i][1];
                $length = strlen($matches[0][$i][0]);
                $highlighted_substr =
                    $this->add_highlighting( substr($input, $start, $length),
                                             $rule['rules'] );
                if ($rule['style'] == 'noweb chunk header') {
                    $highlighted_substr = preg_replace('/&lt;&lt;(.*?)&gt;&gt;=/', '&lt;&lt;<a name="chunk def:\1" href="#chunk use:\1">\1</a>&gt;&gt;=', $highlighted_substr);
                } else if ($rule['style'] == 'noweb chunk') {
                    $highlighted_substr = preg_replace('/&lt;&lt;(.*?)&gt;&gt;([^=]|$)/', '<a name="chunk use:\1" href="#chunk def:\1">\1</a>\2', $highlighted_substr);
                } else if ($rule['style'] == 'noweb escaped less thans') {
                    $highlighted_substr = preg_replace('/@&lt;&lt;/', '&lt;&lt;', $highlighted_substr);
                }
		$snippet = $this->start_stylesheet[$rule['style']] .
			   $highlighted_substr .
			   $this->stop_stylesheet[$rule['style']];
                if (!array_key_exists($start, $snippets)) {
                    $snippets[$start] = array();
                }
                $snippets[$start][$j] = array($start, $j, $length, $snippet);
            }
	}
        ksort($snippets);
        foreach(array_keys($snippets) as $key) {
            ksort($snippets[$key]);
        }

        $last_uncopied = 0;
        foreach($snippets as $snippets_at_same_pos) {
        foreach($snippets_at_same_pos as $snippet_data) {
            list ($start, $rulenum,$length,$snippet) = $snippet_data;
            #print "\$start=$start,\$length=$length,\$rulenum=$rulenum,\$length=$length,\$snippet=$snippet";
	    if ($start < $last_uncopied) {
                #print " (Skipping)\n";
		continue;
	    }
            #print " \n";
	    $result .= htmlspecialchars(substr($input, $last_uncopied, $start-$last_uncopied));
            $result .= $snippet;
	    $last_uncopied = $start + $length;
        }
        }
        $result .= htmlspecialchars(substr($input, $last_uncopied, strlen($input)-$last_uncopied));
        return $result;
    }

    function add_line_numbers( $input ) {
        $lines = explode("\n",$input);
        if ($lines[count($lines)-1] == '') {
            unset($lines[count($lines)-1]);
        }
        $width = strlen(strval(count($lines)));
        $format_str = "<a name=\"line%d\">%{$width}d</a> %s\n";
        $result = '';
        $line_num = 1;
        foreach($lines as $line) {
            $result .= sprintf($format_str, $line_num, $line_num, $line);
            $line_num++;
        }
        return $result;
    }

    function code_to_html( $input, $language, $line_numbers ) {
	list ($this->start_stylesheet, $this->stop_stylesheet) =
	    parse_syntax_highlighting_stylesheet(wfMsg('SyntaxHighlightingStylesheet'));
	$parser = new SyntaxHighlightingRegexpsParser;
	$parse_result = $parser->parse(wfMsg('SyntaxHighlightingRegexps'));
        if (!array_key_exists($language, $parse_result)) {
            $language = 'plain';
        }
	$regexp_rules = $parse_result[$language]['rules'];
    #   print_rules($regexp_rules,0);
	$input = $this->add_highlighting($input, $regexp_rules);
        if ($line_numbers) $input = $this->add_line_numbers($input);
        $input = '<pre>' . $input . '</pre>';
        return $input;
    }
}

# The callback function for converting the input text to HTML output
function renderCodeBlock( $input, $argv ) {
    # $argv is an array containing any arguments passed to the
    # extension like <example argument="foo" bar>..
    # Put this on the sandbox page:  (works in MediaWiki 1.5.5)
    #   <example argument="foo" argument2="bar">Testing text **example** in between the new tags</example>

    $language = strtolower($argv['language']);
    $linenumbers = (array_key_exists('linenumbers', $argv) &&
                    $argv['linenumbers'] != '0' &&
                    $argv['linenumbers'] != 'false');

    $highlighter = new SyntaxHighlighter;
    $output = ($highlighter->code_to_html($input, $language, $linenumbers));
    return $output;
}
?>
