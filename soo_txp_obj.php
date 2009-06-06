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

abstract class Soo_Obj {
// Root class for all Soo_Txp_* classes
// low-level utility methods

	protected $data			= array();
	
	public function __get( $property ) {
		if ( in_array($property, $this->property_names()) )
			return $this->$property;
		elseif ( array_key_exists($property, $this->data) )
			return $this->data[$property];
		else
			return null;
	}
	
	public function __call( $request, $args ) {
	
		$to_set = str_replace('set_', '', $request);
		
		if ( $to_set == $request ) {
			echo $request . '(): method not defined<br />';
			return;
		}
		if ( isset($this->$to_set) ) {
			$this->$to_set = array_pop($args);
			return $this;
		}
		else {
			$this->$data[$to_set] = array_pop($args);
			return $this;
		}		
	}
	
	public function __toString() {
		return get_class($this);
	}
	
	public function properties() {
	// returns an object's properties as an associative array
		$out = array();
		foreach ( $this as $property => $value )
			$out[$property] = $value;
		return $out;
	}
	
	public function property_names() {
	// returns an object's property names as an indexed array
		$out = array();
		foreach ( $this as $property => $value )
			$out[] = $property;
		return $out;
	}
	
}
////////////////////// end of class Soo_Obj ////////////////////////////////


abstract class Soo_Txp_Data extends Soo_Obj {
// Abstract class for retrieving Textpattern database records.

	protected $select		= array();
	protected $from			= 'textpattern';
	protected $where		= array();
	protected $order_by		= array();
	protected $limit		= 0;
	protected $offset		= 0;
	
	function select( $list = '*' ) {
		if ( is_string($list) ) $list = do_list($list);
		foreach ( $list as $col ) $this->select[] = $col;
		return $this;
	}
	
	function from( $table ) {
		$this->from = trim($table);
		return $this;
	}
	
	function where( $column, $value, $operator = '=', $join = '' ) {
		$join = $this->andor($join);
		$this->where[] = ( $join ? $join . ' ' : '' ) . 
			$column . ' ' . $operator . " '" . $value . "'";
		return $this;
	}
	
	function where_in( $column, $list, $join = '', $in = true ) {
		$in = ( $in ? '' : ' not' ) . ' in (';
		if ( is_string($list) ) $list = do_list($list);
		$join = $this->andor($join);
		$this->where[] = ( $join ? $join . ' ' : '' ) . $column . 
			$in . implode(',', quote_list(doSlash($list))) . ')';
		return $this;
	}
	
	function where_not_in( $column, $list, $join = '' ) {
		return $this->where_in( $column , $list , $join , false );
	}
	
	function where_regexp( $pattern, $subject, $join = '' ) {
		$join = $this->andor($join);
		$this->where[] = ( $join ? $join . ' ' : '' ) . 
			$subject . " regexp '" . $pattern . "'";
		return $this;
	}
	
	private function andor( $join = 'and' ) {
		$join = strtolower($join);
		return count($this->where) ? 
			( in_list($join, 'and,or') ? $join : 'and' ) : '';
	}
		
	function order_by( $expr, $direction = '' ) {
		
		if ( $expr ) {
	
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
	
	private function clause_string() {
		return implode(' ', $this->where) .
			( count($this->order_by) ? ' order by ' . implode(', ', $this->order_by) : '' ) .
			( $this->limit ? $this->limit : '' ) . ( $this->offset ? $this->offset : '' );
	}

	private function init_query() {
		if ( !count($this->select) ) $this->select();
		if ( !count($this->where) ) $this->where[] = '1 = 1';
	}
	
	public function echo_query() {
		echo 'select ' .
			( count($this->select) ? implode(',', $this->select) : '*' ) .
			' from ' . $this->from . ' where ' . $this->clause_string();
	}
		
	public function row() {
		$this->init_query();
		return safe_row(implode(',', $this->select), $this->from, 
			$this->clause_string());
	}
	
	public function rows() {
		$this->init_query();
		return safe_rows(implode(',', $this->select), $this->from, 
			$this->clause_string());
	}
	
	public function extract_field( $field, $key = null ) {
	// if $key is set, returns an associative array
	// otherwise returns an indexed array
	
		$rs = $this->rows();
		$out = array();
		
		if ( $rs and array_key_exists($field, $rs[0]) ) {
		
			if ( ! is_null($key) ) {
				if ( array_key_exists($key, $rs[0]) )
					foreach ( $rs as $r ) {
						extract($r);
						$out[$$key] = $$field;
					}
			}
			else
				foreach ( $rs as $r )
					$out[] = $r[$field];
		}

		return $out;	// always returns an array
	}
		
	public function field( $field ) {
		$r = $this->row();
		if ( isset($r[$field]) )
			return $r[$field];
	}

	public function count() {
		return getCount($this->from, $this->clause_string());
	}
	
	protected function retrieve( $key, $value ) {
		if ( ! $key or ! $value )
			return;
		if ( is_numeric($value) )
			$this->where($key, intval($value));
		elseif ( is_string($value) )
			$this->where($key, doSlash($value))->limit(1);
		else
			return false;
		$this->load_properties();
	}
	
	protected function load_properties( $r = null ) {
	// retrieve row if necessary; load record into $this->data
		if ( ! $r )
			$r = $this->row();
		if ( ! is_array($r) ) return;
		foreach ( $r as $k => $v )
			if ( $k == 'date' )
				$r[$k] = strtotime($v);
		$this->set_data($r);
	}
	
	public function properties( ) {
		return $this->data;
	}

}	
////////////////////// end of class Soo_Txp_Data ///////////////////////////

class Soo_Txp_Article extends Soo_Txp_Data {
			
	function __construct( $id = '') {
		$this->retrieve('ID', $id);
		return $this;
	}
	
}
/////////////////// End of class Soo_Txp_Article ///////////////////////////

class Soo_Txp_File extends Soo_Txp_Data {

	function __construct( $key = '') {
		$this->from = 'txp_file';
		if ( is_numeric($key) )
			$this->retrieve('id', $key);
		else
			$this->retrieve('filename', $key);
	}

}
////////////////////// End of class Soo_Txp_File ///////////////////////////

class Soo_Txp_Form extends Soo_Txp_Data {
	
	function __construct( $name = '') {
		$this->from = 'txp_form';
		$this->retrieve('name', $name);
	}
		
}
////////////////////// End of class Soo_Txp_Form ///////////////////////////

class Soo_Txp_Img extends Soo_Txp_Data {
// Database object for Textpattern images
// No setter methods because these properties are inherent to the image;
// adjustments for display should be made on the HTML side (Soo_Txp_Img)
			
	function __construct( $input = null) {
		$this->from = 'txp_image';
		if ( is_numeric($input) )
			$this->retrieve('id', $input);
		elseif ( is_string($input) )
			$this->retrieve('name', $input);
		elseif ( is_array($input) )
			$this->load_properties($input);
	}
			
	// Utilities /////////////////////////////////////
	
	public function full_url() {
		global $img_dir;
		return hu . $img_dir . '/' . $this->id . $this->ext;
	}
		
}
/////////////////////// end of class Soo_Txp_Img ///////////////////////////

class Soo_Txp_Plugin extends Soo_Txp_Data {
	
	function __construct( $name = '') {
		$this->from = 'txp_plugin';
		$this->retrieve('name', $name);
	}
		
}
////////////////////// End of class Soo_Txp_Plugin /////////////////////////

class Soo_Txp_Prefs extends Soo_Txp_Data {
	
	function __construct( $name = '') {
		$this->from = 'txp_prefs';
		$this->retrieve('name', $name);
	}
		
}
////////////////////// End of class Soo_Txp_Prefs //////////////////////////

abstract class Soo_Html extends Soo_Obj {
// HTML element class. Instantiation takes a required 'name' argument and an
// optional 'atts' array: items with keys matching HTML attributes 
// will be transferred to the new object.
// 
// See the Soo_Html_Img class for an example of how to extend this class.
	
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
	
	function __construct($element_name, $atts = array()) {
	// It is up to each child class to know what its protected properties should be
		$this->element_name = $element_name;			
		foreach ( $this as $property => $value )
			if ( in_array($property, array_keys($atts)) )
				$this->$property = $atts[$property];
	}

	public function set_id($id) {
		// Valid HTML IDs must begin with a letter
		// Do not confuse with database IDs
		if ( $id and !preg_match('/^[a-z]/', strtolower(trim($id))) ) {
			$this->id = 'invalid_HTML_ID_value_from_Soo_Txp_Obj';
			return false;
		}
		$this->id = $id;
		return $this;
	}
	
	public function set_contents($content) {
		if ( !$this->is_empty )
			$this->contents[] = $content; // needs validation routine
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
			
			if ( $item instanceof Soo_Html )
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
/////////////////////// end of class Soo_Html //////////////////////////////

class Soo_Html_Anchor extends Soo_Html {

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

	public function __construct ( $url = '', $atts = array() ) {
			
		parent::__construct( 'a', $atts );
		
		return $this->set_is_empty(false)
			->set_is_block(false)
			->set_can_contain(array())
			->set_href($url);
	}
	
}
///////////////////// end of class Soo_Html_Anchor //////////////////////////

class Soo_Html_Br extends Soo_Html {
	public function __construct ( $atts = array() ) {
		parent::__construct( 'br', $atts );
		return $this->set_is_empty(true)
			->set_is_block(false);
	}
}

class Soo_Html_Img extends Soo_Html {

	protected $alt				= '';
	protected $src				= '';
	protected $width			= '';
	protected $height			= '';
			
	public function __construct (
		$obj = null, 
		$thumbnail = false, 
		$escape = true
	) {
	
		$a = array();
	
		if ( $obj instanceof Soo_Txp_Img ) {
			global $img_dir;
			$a = $obj->properties();
			$a['height'] = $a['h'];
			$a['width'] = $a['w'];
			$a['title'] = $a['caption'];
			$a['src'] = hu . $img_dir . '/' . $a['id'] .
				( $thumbnail ? 't' : '' ) . $a['ext'];
			unset($a['id']); // don't want database id as HTML id!
		}
		
		parent::__construct('img', $a);
		
		$this->set_is_empty(true)
			->set_is_block(false)
			->set_can_contain(array());
		
		if ( $escape )
			$this->html_escape('title')->html_escape('alt');
	}
	
}
/////////////////////// end of class Soo_Html_Img //////////////////////////


class Soo_Html_P extends Soo_Html {
	
	public function __construct ( $contents = '', $atts = array() ) {
		parent::__construct('p', $atts);
		
		$this->set_is_empty(false)
			->set_is_block(true)
			->set_can_contain(array('inline'));
			
		if ( $contents )
			$this->set_contents($contents);
	}
}
/////////////////////// end of class Soo_Html_P ////////////////////////////

class Soo_Html_Table extends Soo_Html {

	protected $summary				= '';
	protected $width				= '';
	protected $border				= '';
	protected $frame				= '';
	protected $rules				= '';
	protected $cellspacing			= '';
	protected $cellpadding			= '';

	public function __construct ( $atts = array() ) {
			
		parent::__construct( 'table', $atts );
		
		$this->set_is_empty(false)
			->set_is_block(true)
			->set_can_contain(array('caption', 'col', 'colgroup', 
				'thead', 'tfoot', 'tbody'));
			// can also contain tr if only one tbody and no tfoot or thead;

	}
	
}
/////////////////////// end of class Soo_Html_Table ////////////////////////////

abstract class Soo_Html_Table_Component extends Soo_Html {

	protected $align				= '';
	protected $char					= '';
	protected $charoff				= '';
	protected $valign				= '';

	public function __construct ( $component, $atts = array() ) {
			
		parent::__construct( $component, $atts );
		
		$this->set_is_empty(false)
			->set_is_block(true);
	}
	
}
///////////////// end of class Soo_Html_Table_Component /////////////////////////

class Soo_Html_Thead extends Soo_Html_Table_Component {

	public function __construct ( $atts = array() ) {
			
		parent::__construct( 'thead', $atts );
		
		$this->set_can_contain(array('tr'));
	}
	
}
/////////////////////// end of class Soo_Html_Thead ////////////////////////////

class Soo_Html_Tbody extends Soo_Html_Table_Component {

	public function __construct ( $atts = array() ) {
			
		parent::__construct( 'tbody', $atts );
		
		$this->set_can_contain(array('tr'));
	}
	
}
/////////////////////// end of class Soo_Html_Tbody ////////////////////////////

class Soo_Html_Tfoot extends Soo_Html_Table_Component {

	public function __construct ( $atts = array() ) {
			
		parent::__construct( 'tfoot', $atts );
		
		$this->set_can_contain(array('tr'));
	}
	
}
/////////////////////// end of class Soo_Html_Tfoot ////////////////////////////

class Soo_Html_Tr extends Soo_Html_Table_Component {
			
	public function __construct ( $atts = array() ) {
			
		parent::__construct( 'tr', $atts );
		
		$this->set_can_contain(array('th', 'td'));
	}
	
}
/////////////////////// end of class Soo_Html_Tr ////////////////////////////

abstract class Soo_Html_Table_Cell extends Soo_Html_Table_Component {

	protected $rowspan			= '';
	protected $colspan			= '';
	protected $headers			= '';
	protected $abbr				= '';
	protected $scope			= '';
	protected $axis				= '';

	public function __construct ( $cell_type, $atts = array() ) {
			
		parent::__construct( $cell_type, $atts );
		
// 		$this->set_can_contain(array('caption', 'col', 'colgroup', 
// 				'thead', 'tfoot', 'tbody'));
	}
	
}
/////////////////// end of class Soo_Html_Table_Cell ////////////////////////

class Soo_Html_Th extends Soo_Html_Table_Cell {

	public function __construct ( $contents = '', $atts = array() ) {
			
		parent::__construct( 'th', $atts );
		
		if ( $contents ) $this->set_contents($contents);
		
		return $this;
		
	}
		
}
/////////////////////// end of class Soo_Html_Th ////////////////////////////

class Soo_Html_Td extends Soo_Html_Table_Cell {

	public function __construct ( $contents = '', $atts = array() ) {
			
		parent::__construct( 'td', $atts );
		
		if ( $contents ) $this->set_contents($contents);
		
		return $this;
		
	}
		
}
/////////////////////// end of class Soo_Html_Td ////////////////////////////

class Soo_Html_Ol extends Soo_Html {

	public function __construct ( $atts = array() ) {
			
		parent::__construct( 'ol', $atts );
		
		$this->set_is_empty(false)
			->set_is_block(true);
			//->set_can_contain(array('li'));
	}
	

}
/////////////////////// end of class Soo_Html_Ol ////////////////////////////

class Soo_Html_Ul extends Soo_Html {

	public function __construct ( $atts = array() ) {
			
		parent::__construct( 'ul', $atts );
		
		$this->set_is_empty(false)
			->set_is_block(true);
			//->set_can_contain(array('li'));
	}
	

}
/////////////////////// end of class Soo_Html_Ul ////////////////////////////

class Soo_Html_Li extends Soo_Html {

	public function __construct ( $contents = '', $atts = array() ) {
			
		parent::__construct( 'li', $atts );
		
		$this->set_is_empty(false)
			->set_is_block(true);
			//->set_can_contain(array('ol', 'ul'));	
			// actually can contain almost anything other than li
		
		if ( $contents )
			$this->set_contents($contents);
	}
	

}
/////////////////////// end of class Soo_Html_Li ////////////////////////////

class Soo_Html_Span extends Soo_Html {

	public function __construct ( $contents = '', $atts = array() ) {
			
		parent::__construct( 'span', $atts );
		
		$this->set_is_empty(false)
			->set_is_block(false);
		
		if ( $contents )
			$this->set_contents($contents);
	}
	

}
/////////////////////// end of class Soo_Html_Span ////////////////////////////

class Soo_Txp_Uri extends Soo_Obj {
	
	protected $full;
	protected $request_uri;
	protected $query_string;
	protected $query_params;

	public function __construct ( ) {
		$this->request_uri = $_SERVER['REQUEST_URI'];
		$this->query_string = $_SERVER['QUERY_STRING'];
		$this->full = preg_replace('/\/$/', '', hu) . $this->request_uri;
		$this->set_query_params();
	}
	
	public function __call( $request, $args ) {
		return false;
	}
	
	private function set_query_params ( ) {
		$this->query_params = array();
		if ( $this->query_string )
			foreach ( explode('&', urldecode($this->query_string)) as $chunk ) {
				list($k, $v) = explode('=', $chunk);
				$this->query_params[$k] = $v;
			}
		return $this;
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
		foreach ( $this->query_params as $k => $v )
			$kv_pairs[] = "$k=$v";
		$this->query_string = isset($kv_pairs) ? implode('&', $kv_pairs) : '';
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
/////////////////////// end of class Soo_Txp_Uri ////////////////////////////

  //---------------------------------------------------------------------//
 //							Support Functions							//
//---------------------------------------------------------------------//

function _soo_echo( $item, $show_empty_props = false, $prefix = '&nbsp;' ) {
// for debugging: echo a multidimensional array, object, whatever
	
	if ( ! defined('MY_PREFIX') )
		define('MY_PREFIX', $prefix);
	$prefix .= MY_PREFIX;
	
	if ( $item instanceof Soo_Obj ) {
		echo $prefix . $item . ' object:<br />';
		foreach ( $item->properties() as $p => $v ) {
			if ( is_array($v) ) {
				echo $prefix . $p . ':';
				_soo_echo($v, $show_empty_props, $prefix);
			}
			elseif ( $v or $show_empty_props )
				echo $prefix . $p . ': ' . $v . '<br />';
		}
	}

	elseif ( is_array($item) ) {
		echo $prefix . $item . ': ' . 
			count($item) . ' item' . ( count($item) > 1 ? 's' : '' ) . ':<br />';
		foreach ( $item as $key => $value )
 			if ( ! is_array($value) and ! is_object($value) )
				echo $prefix . $key . ': ' . $value . '<br />';
 			else 
				_soo_echo($value, $show_empty_props, $prefix);
 	}
	elseif ( $item and ! is_object($item) )
		echo $prefix . $item . ':<br />';		
	
}

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

*soo_txp_obj* is a library for creating Textpattern plugins. It has classes for Textpattern objects (queries and data) and HTML objects (HTML elements).

The design of the library aims for strict separation of data retrieval and HTML output. Of course boundaries do blur and you have to do what works, but a little extra time figuring out how to work with the model rather than around it can pay dividends.

*soo_txp_obj* is still an alpha release. It is possible that future releases will not be entirely backward-compatible with this one.

Suggestions and corrections gladly accepted. "Email the author &rarr;":http://ipsedixit.net/info/2/contact

This is a very minimal guide: there are too many classes for that (and most of them are quite easy to figure out). More information and examples are available "here":http://ipsedixit.net/txp/21/soo-txp-obj.

h3. Classes

All the classes are extensions of the very simple *Soo_Obj* base class. Most of the classes fall into two families: data classes and HTML output classes. There is also a support class, Soo_Txp_Uri, mainly for handling URI query strings.

h4. Soo_Obj

Super-parent abstract base class, with no properties and just a few low-level methods. Has @__get()@ as a generic getter, and a @__call()@ which will work as a generic setter for calls in the form @set_property($value)@, replacing "property" with an existing property name.

h4. Soo_Txp_Data

This is the abstract base class for building queries and retrieving records from the Txp database. The class has been extended for several Txp tables, and it is easy to extend it to cover any table you need to work with.

h4. Soo_Html

This is the abstract base class for building HTML tags. As with *Soo_Txp_Data*, it is easy to extend if the HTML element you need doesn't have its own class yet.

h4. Soo_Txp_Uri

Intended for dealing with query string parameters, allowing you to set, add, or delete specific parameters while preserving the rest. Note that using this class to set parameters will also reset @$_SERVER['REQUEST_URI']@ and @$_SERVER['QUERY_STRING']@, while leaving the @$_GET@ and @$_POST@ arrays untouched.

h3. _soo_echo()

A bonus function (not attached to any class) for development. Feed it a variable and it will @echo()@ it, whether it is a simple string or number, an array, or an object -- or even a multi-dimensional array of objects or an object containing multi-dimensional arrays -- you get the idea. Different from the native @var_dump()@ function in that the format is easier to read in a standard browser window, and suppresses empty items by default.

h2. Version history

h3. 1.0.a.6

6/2/2009

What the heck, time to use the same naming convention I'm using with other plugins.

* Added *Soo_Txp_Uri* class, for URI query string manipulation
* *Soo_Html_P* and *Soo_Html_Th* can now have contents set during instantiation. Note that this will break compatibility with some previous uses, e.g. @new Soo_Html_P($atts)@.
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