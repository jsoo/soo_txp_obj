<?php

// soo_txp_ojb
//
// A library for writing object-oriented Textpattern code.
// Copyright 2009 Jeff Soo. 
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
// 
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
// 
// You should have received a copy of the GNU General Public License
// along with this program. If not, see <http://www.gnu.org/licenses/>.

$plugin['version'] = '1.0.a.6';
$plugin['author'] = 'Jeff Soo';
$plugin['author_uri'] = 'http://ipsedixit.net/';
$plugin['description'] = 'Object classes for Txp plugins';
$plugin['type'] = 2; 

@include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---


  //---------------------------------------------------------------------//
 //									Classes								//
//---------------------------------------------------------------------//

abstract class soo_obj {
// Root class for all Soo_Txp_* classes
// low-level utility methods

	public function __get( $property ) {
		return isset($this->$property) ? $this->$property : null;
	}
	
	public function __call( $request, $args ) {
		if ( isset($this->$request) )
			$this->$request = array_pop($args);
		return $this;
	}
	
	public function __toString() {
		return get_class($this);
	}
	
	public function properties() {
	// returns an object's properties as an associative array
		foreach ( $this as $property => $value )
			$out[$property] = $value;
		return $out;
	}
	
	public function property_names() {
	// returns an object's property names as an indexed array
		return array_keys($this->properties());
	}
	
}
////////////////////// end of class soo_obj ////////////////////////////////


abstract class soo_txp_query extends soo_obj {
	
	protected $table		= '';
	protected $where		= array();
	protected $order_by		= array();
	protected $limit		= 0;
	protected $offset		= 0;
	
	protected $numeric_index	= array(
		'textpattern'		=> 'ID',
		'txp_category'		=> 'id',
		'txp_discuss'		=> 'discussid',
		'txp_file'			=> 'id',
		'txp_image'			=> 'id',
		'txp_lang'			=> 'id',
		'txp_link'			=> 'id',
		'txp_log'			=> 'id',
		'txp_prefs'			=> 'prefs_id',
		'txp_users'			=> 'user_id',
	);
	protected $string_index		= array(
		'textpattern'		=> 'Title',
		'txp_category'		=> 'name',
		'txp_css'			=> 'name',
		'txp_discuss_ipban'	=> 'ip',
		'txp_discuss_nonce'	=> 'nonce',
		'txp_file'			=> 'filename',
		'txp_form'			=> 'name',
		'txp_image'			=> 'name',
		'txp_lang'			=> 'lang',
		'txp_page'			=> 'name',
		'txp_plugin'		=> 'name',
		'txp_prefs'			=> 'name',
		'txp_section'		=> 'name',
		'txp_users'			=> 'name',
	);
	
	function __construct( $table, $key = null ) {
		$this->table = trim($table);
		if ( $key )
			$this->where($this->key_column($key), $key);
	}

	function where( $column, $value, $operator = '=', $join = '' ) {
		$join = $this->andor($join);
		$this->where[] = ( $join ? $join . ' ' : '' ) . 
			self::quote($column) . ' ' . $operator . " '" . $value . "'";
		return $this;
	}
	
	function in( $column, $list, $join = '', $in = true ) {
		$in = ( $in ? '' : ' not' ) . ' in (';
		if ( is_string($list) ) $list = do_list($list);
		$join = $this->andor($join);
		$this->where[] = ( $join ? $join . ' ' : '' ) . self::quote($column) . 
			$in . implode(',', quote_list(doSlash($list))) . ')';
		return $this;
	}
	
	function not_in( $column, $list, $join = '' ) {
		return $this->in( $column , $list , $join , false );
	}
	
	function regexp( $pattern, $subject, $join = '' ) {
		$join = $this->andor($join);
		$this->where[] = ( $join ? $join . ' ' : '' ) . 
			self::quote($subject) . " regexp '" . $pattern . "'";
		return $this;
	}
	
	private function andor( $join = 'and' ) {
		$join = strtolower($join);
		return count($this->where) ? 
			( in_list($join, 'and,or') ? $join : 'and' ) : '';
	}
	
	function quote( $identifier ) {
	// quote with backticks only if $identifier consists only of alphanumerics, $, or _
		return preg_match('/^[a-z_$\d]+$/i', $identifier) ?
			'`' . $identifier . '`' : $identifier;
	}
		
	function order_by( $expr, $direction = '' ) {
		
		if ( $expr ) {
			
			if ( is_array($expr) )
				$expr = array_map('strtolower', $expr);
			
			if ( is_string($expr) )
				$expr = do_list(strtolower($expr));
			
			foreach ( $expr as $x ) {
				
				if ( preg_match('/(\S+)\s+(\S+)/', $x, $match) ) {
					$column = $match[1];
					$direction = $match[2];
				}
				else
					$column = $x;
			
				if ( $column == 'random' or $column == 'rand' or $column == 'rand()' ) {
					$column = 'rand()';
					$direction = '';
				}
				else 
					$direction = in_array($direction, array('asc', 'desc')) ?
						$direction : '';
					
				$this->order_by[] = $column . ( $direction ? ' ' . $direction : '');
			}
		}
		
		return $this;
	}
	
	function asc( $col ) {
		$this->order_by($col, 'asc');
		return $this;
	}

	function desc( $col ) {
		$this->order_by($col, 'desc');
		return $this;
	}
	
	function order_by_field( $field, $list ) { // for preserving arbitrary order
		if ( is_string($list) ) $list = do_list($list);
		if ( count($list) > 1 )
			$this->order_by[] = 'field(' . $field . ', ' .
				implode(', ', quote_list(doSlash($list))) . ')';
	}
	
	function limit( $limit ) {
		if ( is_numeric($limit) and $limit > 0 )
			$this->limit = ' limit ' . intval($limit);
		return $this;
	}
	
	function offset( $offset ) {
		if ( is_numeric($offset) and $offset > 0 )
			$this->offset = ' offset ' . intval($offset);
		return $this;
	}
	
	protected function clause_string() {
		return implode(' ', $this->where) .
			( count($this->order_by) ? ' order by ' . implode(', ', $this->order_by) : '' ) .
			( $this->limit ? $this->limit : '' ) . ( $this->offset ? $this->offset : '' );
	}

	public function count() {
		return getCount($this->table, $this->clause_string() ? 
			$this->clause_string() : '1=1'
		);
	}

	function key_column( $key_value = null ) {
		if ( is_numeric($key_value) )
			return $this->numeric_index[$this->table];
		if ( is_string($key_value) )
			return $this->string_index[$this->table];
		if ( isset($this->numeric_index[$this->table]) )
			return $this->numeric_index[$this->table];
		return $this->string_index[$this->table];
	}
	
}
////////////////////// end of class soo_txp_query ////////////////////////////


class soo_txp_select extends soo_txp_query {
	
	protected $select		= array();
	
	function select( $list = '*' ) {
		if ( is_string($list) ) $list = do_list($list);
		foreach ( $list as $col ) $this->select[] = parent::quote($col);
		return $this;
	}
	
	private function init_query() {
		if ( ! count($this->select) ) $this->select();
		if ( ! count($this->where) ) $this->where[] = '1 = 1';
	}
	
	public function row() {
		$this->init_query();
		return safe_row(implode(',', $this->select), $this->table, 
			$this->clause_string());
	}
	
	public function rows() {
		$this->init_query();
		return safe_rows(implode(',', $this->select), $this->table, 
			$this->clause_string());
	}
	
}
////////////////////// end of class soo_txp_select /////////////////////////////


class soo_txp_rowset extends soo_obj {

	protected $table		= '';
	public $rows			= array();

	function __construct( $init = array(), $table = '' ) {
		if ( $init instanceof soo_txp_select ) {
			$table = $init->table;
			$index = $init->key_column();
			$init = $init->rows();
		}
		$this->table = $table;
		foreach ( $init as $r )
			if ( isset($index) )
				$this->add_row($r, $table, $r[$index]);
			else
				$this->add_row($r, $table);
	}

	public function field_vals( $field, $key = null ) {
	// if $key is set, returns an associative array
	// otherwise returns an indexed array
	
		foreach ( $this->rows as $r )
			if ( ! is_null($key) )
				$out[$r->$key] = $r->$field;
			else
				$out[] = $r->$field;
		return isset($out) ? $out : array();
	}
	
	private function add_row( $data, $table, $i = null ) {
		$r = $data instanceof soo_txp_row ? 
			$data : ( $table == 'txp_image' ?
				new soo_txp_img($data) : new soo_txp_row($data, $table) );
		if ( is_null($i) )
			$this->rows[] = $r;
		else
			$this->rows[$i] = $r;
		return $this;
	}
}
////////////////////// end of class soo_txp_rowset /////////////////////////////

class soo_txp_row extends soo_obj {

	protected $table		= '';
	protected $data			= array();
	
	function __construct( $init = array(), $table = '' ) {
		if ( is_scalar($init) and $table )
			$init = new soo_txp_select($table, $init);
		if ( $init instanceof soo_txp_select ) {
			$table = $init->table;
			$init = $init->row();
		}
		if ( is_array($init) )
			foreach ( $init as $k => $v )
				$this->data[$k] = $v;
		$this->table = $table;
	}

	function __get( $property ) {
		return isset($this->data[$property]) ? $this->data[$property] 
			: parent::__get($property);
	}
	
	function data( ) {
		return; // to override parent::__call(), to keep $this->data protected
	}
	
	public function properties( ) {
		return $this->data;
	}
}
////////////////////// end of class soo_txp_row ////////////////////////////////


class soo_txp_img extends soo_txp_row {
			
	protected $full_url		= '';
	protected $thumb_url	= '';
	
	function __construct( $init ) {
		global $img_dir;
		parent::__construct($init, 'txp_image');
		$this->full_url = hu . $img_dir . '/' . $this->id . $this->ext;
		$this->thumb_url = hu . $img_dir . '/' . $this->id . 't' . $this->ext;
	}
		
}
/////////////////////// end of class soo_txp_img ///////////////////////////


abstract class soo_html extends soo_obj {
// HTML element class. Instantiation takes a required 'name' argument and an
// optional 'atts' array: items with keys matching HTML attributes 
// will be transferred to the new object.
// 
// See the soo_html_img class for an example of how to extend this class.
	
	// inherent properties
	protected $element_name	= '';
	protected $is_empty		= 0;		// 0: container; 1: empty (single-tag)
	protected $is_block		= 0;		// 0: inline; 1: block	not sure if this is necessary...
	protected $can_contain	= array();
	protected $contents		= array();		// object (another element) or string
	
	// common HTML attributes
	protected $class			= '';
	protected $dir				= '';
	protected $id				= '';
	protected $lang				= '';
	protected $onclick			= '';
	protected $ondblclick		= '';
	protected $onkeydown		= '';
	protected $onkeypress		= '';
	protected $onkeyup			= '';
	protected $onmousedown		= '';
	protected $onmousemove		= '';
	protected $onmouseout		= '';
	protected $onmouseover		= '';
	protected $onmouseup		= '';
	protected $style			= '';
	protected $title			= '';
	
	function __construct($element_name, $atts, $content = '') {
		$this->element_name($element_name);
		if ( empty($atts) )
			$atts = array();
		foreach ( $this as $property => $value )
			if ( in_array($property, array_keys($atts)) )
				$this->$property($atts[$property]);
		if ( $content )
			$this->contents($content);
	}

	public function id($id) {
		// Valid HTML IDs must begin with a letter
		// Do not confuse with database IDs
		if ( $id and !preg_match('/^[a-z]/', strtolower(trim($id))) ) {
			$this->id = 'invalid_HTML_ID_value_from_Soo_Txp_Obj';
			return false;
		}
		$this->id = $id;
		return $this;
	}
	
	public function contents($content) {
		if ( ! $this->is_empty ) {
			if ( is_array($content) )
				$this->contents[] = array_merge($this->contents, $content);
			else
				$this->contents[] = $content;
		}
		return $this;
	}
	
	// Utilities /////////////////////////////////////

	private function html_attribute_names() {
		$not = array('element_name', 'is_empty', 'is_block', 'contents', 'can_contain');	// gotta be a better way
		$all = $this->property_names();
		return array_diff($all, $not);
	}
	
	private function html_attributes() {
		$out = array();
		foreach ( $this as $property => $value )
			if ( in_array($property, $this->html_attribute_names()) )
				$out[$property] = $value;
		return $out;
	}

	public function tag() {
	
		$out = '<' . $this->element_name;
		
		// next block modified to deal with a PHP 5.2.0 bug fixed PHP 5.2.4 ??
		$hidden = array('element_name', 'is_empty', 'is_block', 'contents', 'can_contain');
		foreach ( $this->properties() as $property => $value )
			if ( ( $value or $property == 'alt' ) and !in_array($property, $hidden) )
				$out .= " $property=\"$value\"";
		
		if ( $this->is_empty )
			return $out . ' />';
					
		$out .= '>';
				
		foreach ( $this->contents as $item )
			
			if ( $item instanceof soo_html )
				$out .= $item->tag() . n;		// recursion ...
				
			else
				$out .= $item;
		
		return $out . "</$this->element_name>";
	}

	protected function html_escape( $property ) {
		$this->$property = htmlspecialchars($this->$property);
		return $this;
	}
	
}
/////////////////////// end of class soo_html //////////////////////////////

class soo_html_anchor extends soo_html {

	protected $href				= '';
	protected $name				= '';
	protected $rel				= '';
	protected $rev				= '';
	protected $type				= '';
	protected $hreflang			= '';
	protected $charset			= '';
	protected $accesskey		= '';
	protected $tabindex			= '';
	protected $shape			= '';
	protected $coords			= '';
	protected $onfocus			= '';
	protected $onblur			= '';

	public function __construct ( $atts = array(), $content = '' ) {
	// $atts can be an array, or just the href value
		if ( ! is_array($atts) )
			$atts = array('href' => $atts);
		$this->is_empty(false)->is_block(false);
		parent::__construct( 'a', $atts, $content );
	}
	
}

class soo_html_br extends soo_html {

	public function __construct ( $atts = array() ) {
		parent::__construct( 'br', $atts );
		$this->is_empty(true)->is_block(false);
	}
}

class soo_html_img extends soo_html {

	protected $alt				= '';
	protected $src				= '';
	protected $width			= '';
	protected $height			= '';
			
	public function __construct ( $init = array(), $thumbnail = false, $escape = true ) {
	
		if ( $init instanceof soo_txp_img ) {
			$src = $thumbnail ? $init->thumb_url : $init->full_url;
			$init = $init->properties();
			$init['height'] = $init['h'];
			$init['width'] = $init['w'];
			$init['title'] = $init['caption'];
			$init['src'] = $src;
			unset($init['id']); // don't want database id as HTML id!
		}
		elseif ( ! is_array($init) )
			$init['src'] = $init;
		parent::__construct('img', $init);
		
		$this->is_empty(true)->is_block(false);
//			->can_contain(array());
		
		if ( $escape )
			$this->html_escape('title')->html_escape('alt');
	}
	
}
/////////////////////// end of class soo_html_img //////////////////////////


class soo_html_p extends soo_html {
	
	public function __construct ( $atts = array(), $content = '' ) {
		$this->is_empty(false)
			->is_block(true)
			->can_contain(array('inline'));
		parent::__construct('p', $atts, $content);
	}
}

class soo_html_table extends soo_html {

	protected $summary				= '';
	protected $width				= '';
	protected $border				= '';
	protected $frame				= '';
	protected $rules				= '';
	protected $cellspacing			= '';
	protected $cellpadding			= '';

	public function __construct ( $atts = array(), $content = '' ) {
		$this->is_empty(false)
			->is_block(true)
			->can_contain(array('caption', 'col', 'colgroup', 
				'thead', 'tfoot', 'tbody'));
			// can also contain tr if only one tbody and no tfoot or thead;
		parent::__construct( 'table', $atts, $content );
	}
}

abstract class soo_html_table_component extends soo_html {

	protected $align				= '';
	protected $char					= '';
	protected $charoff				= '';
	protected $valign				= '';

	public function __construct ( $component, $atts = array(), $content = '' ) {
		$this->is_empty(false)->is_block(true);
		parent::__construct( $component, $atts, $content );
	}
}

class soo_html_thead extends soo_html_table_component {

	public function __construct ( $atts = array(), $content = '' ) {
		$this->can_contain(array('tr'));
		parent::__construct( 'thead', $atts, $content );
	}
}

class soo_html_tbody extends soo_html_table_component {

	public function __construct ( $atts = array(), $content = '' ) {
		$this->can_contain(array('tr'));
		parent::__construct( 'tbody', $atts, $content );
	}
}

class soo_html_tfoot extends soo_html_table_component {

	public function __construct ( $atts = array(), $content = '' ) {
		$this->can_contain(array('tr'));
		parent::__construct( 'tfoot', $atts, $content );
	}
}

class soo_html_tr extends soo_html_table_component {
			
	public function __construct ( $atts = array(), $content = '' ) {
		$this->can_contain(array('th', 'td'));
		parent::__construct( 'tr', $atts, $content );
	}	
}

abstract class soo_html_table_cell extends soo_html_table_component {

	protected $rowspan			= '';
	protected $colspan			= '';
	protected $headers			= '';
	protected $abbr				= '';
	protected $scope			= '';
	protected $axis				= '';

	public function __construct ( $cell_type, $atts = array(), $content = '' ) {
			
		parent::__construct( $cell_type, $atts, $content );		
// 		$this->can_contain(array('caption', 'col', 'colgroup', 
// 				'thead', 'tfoot', 'tbody'));
	}
}

class soo_html_th extends soo_html_table_cell {

	public function __construct ( $atts = array(), $content = '' ) {
		parent::__construct( 'th', $atts, $content );
	}
}

class soo_html_td extends soo_html_table_cell {

	public function __construct ( $atts = array(), $content = '' ) {
		parent::__construct( 'td', $atts, $content );
	}
		
}

class soo_html_ol extends soo_html {

	public function __construct ( $atts = array(), $content = '' ) {
		$this->is_empty(false)
			->is_block(true);
			//->can_contain(array('li'));
		parent::__construct( 'ol', $atts, $content );
	}
}

class soo_html_ul extends soo_html {

	public function __construct ( $atts = array(), $content = '' ) {
		$this->is_empty(false)
			->is_block(true);
			//->can_contain(array('li'));
		parent::__construct( 'ul', $atts, $content );
	}
}

class soo_html_li extends soo_html {

	public function __construct ( $atts = array(), $content = '' ) {
		$this->is_empty(false)->is_block(true);
		parent::__construct('li', $atts, $content);
	}
}

class soo_html_span extends soo_html {

	public function __construct ( $atts = array(), $content = '' ) {
		$this->is_empty(false)->is_block(false);
		parent::__construct('span', $atts, $content);
	}
}

class soo_uri extends soo_obj {
	
	protected $full;
	protected $request_uri;
	protected $query_string;
	protected $query_params;

	public function __construct ( ) {
		$this->request_uri = $_SERVER['REQUEST_URI'];
		$this->query_string = $_SERVER['QUERY_STRING'];
		$this->full = preg_replace('/\/$/', '', hu) . $this->request_uri;
		$this->query_params = $_GET;
	}
	
	public function __call( $request, $args ) {
		return false;
	}
		
	public function set_query_param ( $name, $value = null ) {
		if ( is_null($value) )
			unset($this->query_params[$name]);
		else
			$this->query_params[$name] = $value;
		$this->update_from_params();
		return $this;
	}
	
	private function update_from_params ( ) {
		$this->query_string = http_build_query($this->query_params);
		$this->request_uri = self::strip_query($this->request_uri) . 
			( $this->query_string ? '?' . $this->query_string : '' );
		$this->full = preg_replace('/\/$/', '', hu) . $this->request_uri;
		$_SERVER['QUERY_STRING'] = $this->query_string;
		$_SERVER['REQUEST_URI'] = $this->request_uri;
	}
	
	public function strip_query ( $uri ) {
		return preg_replace ('/(.+)\?.+/', '$1', $uri);
	}

}
/////////////////////// end of class soo_uri ////////////////////////////

# --- END PLUGIN CODE ---

if (0) {
?>
<!-- CSS & HELP
# --- BEGIN PLUGIN CSS ---
<style type="text/css">
div#sed_help pre {padding: 0.5em 1em; background: #eee; border: 1px dashed #ccc;}
div#sed_help h1, div#sed_help h2, div#sed_help h3, div#sed_help h3 code {font-family: sans-serif; font-weight: bold;}
div#sed_help h1, div#sed_help h2, div#sed_help h3 {margin-left: -1em;}
div#sed_help h2, div#sed_help h3 {margin-top: 2em;}
div#sed_help h1 {font-size: 2.4em;}
div#sed_help h2 {font-size: 1.8em;}
div#sed_help h3 {font-size: 1.4em;}
div#sed_help h4 {font-size: 1.2em;}
div#sed_help h5 {font-size: 1em;margin-left:1em;font-style:oblique;}
div#sed_help ul li {list-style-type: disc;}
div#sed_help ul li li {list-style-type: circle;}
div#sed_help ul li li li {list-style-type: square;}
div#sed_help li a code {font-weight: normal;}
div#sed_help li code:first-child {background: #ddd;padding:0 .3em;margin-left:-.3em;}
div#sed_help li li code:first-child {background:none;padding:0;margin-left:0;}
div#sed_help dfn {font-weight:bold;font-style:oblique;}
div#sed_help .required, div#sed_help .warning {color:red;}
div#sed_help .default {color:green;}
div#sed_help sup {line-height:0;}
</style>
# --- END PLUGIN CSS ---
# --- BEGIN PLUGIN HELP ---
 <div id="sed_help">

h1. soo_txp_obj

h2(#overview). Overview

*soo_txp_obj* is a library for creating Textpattern plugins. It has classes for query objects (SQL queries), data objects (Txp database records), and HTML objects (HTML elements).

The design of the library aims for strict separation of data retrieval and HTML output. Of course boundaries do blur and you have to do what works, but a little extra time figuring out how to work with the model rather than around it can pay dividends.

*soo_txp_obj* is currently a beta release. Future releases are not expected to (significantly) break backwards-compatibility.

Suggestions and corrections gladly accepted. "Email the author &rarr;":http://ipsedixit.net/info/2/contact

This is a very minimal guide. More information and examples are available "here":http://ipsedixit.net/txp/21/soo-txp-obj.

h3. Classes

All the classes extend the *soo_obj* base class. Most of the classes fall into three families: queries, data records, and HTML element classes. Another class, soo_uri, is for handling URI query strings.

h4. soo_obj

Abstract base class, with no properties and just a few low-level methods. Has @__get()@ as a generic getter, and a @__call()@ which will work as a generic setter for calls in the form @property($value)@.

h4. soo_txp_query

Abstract base class for building queries. Currently extended to soo_txp_select for making @SELECT@ calls; would be easy to extend to additional child classes for @INSERT@, @UPDATE@, and @DELETE@ calls.

h4. soo_txp_row

Basic class for Txp database records (rows). If you give it an identifier (e.g., article ID) or *soo_txp_query* object on instantiation it will automatically populate the @data@ array. Has been extended to *soo_txp_img* which adds properties for full and thumbnail URL.

h4. soo_txp_rowset

For creating and dealing with groups of *soo_txp_row* objects. Given a *soo_txp_query* object or array of data records it will automatically populate the @rows@ array with *soo_txp_row* objects.

h4. soo_html

Abstract base class for building HTML tags. Currently extended to cover many, but by no means all, HTML elements.

h4. soo_uri

Intended for dealing with query string parameters, allowing you to set, add, or delete specific parameters while preserving the rest. Note that using this class to set parameters will also reset @$_SERVER['REQUEST_URI']@ and @$_SERVER['QUERY_STRING']@, while leaving the @$_GET@ and @$_POST@ arrays untouched.

h2. Version history

h3. 1.0.b.1

* Major re-organization and re-write of most classes. 
** The old *Soo_Txp_Data* family has been divided into separate classes for queries and data. 
** There are no longer separate classes for each Txp table.
** All class names now lowercase (these aren't case sensitive anyway).
** Generic setting is now in the form @obj->property()@ instead of @obj->set_property()@.
** Various renaming, code cleaning, etc.

h3. 1.0.a.6

6/2/2009

What the heck, time to use the same naming convention I'm using with other plugins.

* Added *Soo_Txp_Uri* class, for URI query string manipulation
* *Soo_Html_P* and *soo_html_th* can now have contents set during instantiation. Note that this will break compatibility with some previous uses, e.g. @new Soo_Html_P($atts)@.
* Added @$in@ argument to @where_in()@ method (*Soo_Txp_Data*) to allow "NOT IN" queries

h3. 1.0.a5

5/13/2009

* Corrected SQL syntax in @order_by_field()@ function of @Soo_Txp_Data@ class
* Modified @tag()@ function of @Soo_Html@ class to handle a PHP 5.2.0 bug

h3. 1.0.a4

5/1/2009

Added @count()@ and @field()@ methods to the abstract @Soo_Txp_Data@ class.

h3. 1.0.a3

2/19/2009

Added "Expires" property to @Soo_Txp_Article@ and "load_order" property to @Soo_Txp_Plugin@. These fields were added in Textpattern version 4.0.7.

h3. 1.0.a2

2/5/2009

No significant changes, but added generic getters and setters (thanks to *jm* for the hint), making the file about 35% smaller.

h3. 1.0.a1

2/4/2009


 </div>
# --- END PLUGIN HELP ---
-->
<?php
}
?>