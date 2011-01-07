<?php
/** @mainpage notitle
 *  <p><a href="http://ipsedixit.net/txp/21/soo-txp-obj">soo_txp_obj</a> is a support library for <a href="http://textpattern.com/">Textpattern</a> plugins. 
 *  <p>It includes classes for building and running queries, handling results sets, building HTML output, and manipulating URI query strings.
 *  <p><small>This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 2 of the License, or
 *  (at your option) any later version.
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 *  You should have received a copy of the GNU General Public License
 *  along with this program. If not, see <a href="http://www.opensource.org/licenses/lgpl-2.1.php">http://www.opensource.org/licenses/lgpl-2.1.php</a>.</small>
 *  @author Copyright 2009&ndash;2010 <a href="http://ipsedixit.net/info/2/contact">Jeff Soo</a>
 *  @version 1.1.0
 *  @sa <a href="http://ipsedixit.net/txp/21/soo-txp-obj">soo_txp_obj Developer Guide</a>
 */
$plugin['name'] = 			'soo_txp_obj';
$plugin['description'] = 	'Support library for Txp plugins';
$plugin['version'] = 		'1.1.0';
$plugin['author'] = 		'Jeff Soo';
$plugin['author_uri'] = 	'http://ipsedixit.net/txp/';
$plugin['type'] = 2; 
@include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

/// Generic abstract base class, with a few low-level utility methods. 
abstract class soo_obj
{
	/** Generic getter.
	 *  Allow calls in the form $obj->property for protected properties.
	 */
	public function __get( $property )
	{
		return isset($this->$property) ? $this->$property : null;
	}
	
	/** Method overloading for generic setters.
	 *  Allow calls in the form $obj->property($value).
	 *  Returns $this to allow method chaining.
	 */
	public function __call( $request, $args )
	{
		if ( isset($this->$request) )
			$this->$request = array_shift($args);
		return $this;
	}
	
	/** Return an object's class name.
	 *  Allows object to be used in a string context.
	 *  @return string Class name
	 */
	public function __toString()
	{
		return get_class($this);
	}
	
	/** Return an object's properties as an associative array.
	 *  Note: includes protected and private properties. 
	 *  For public properties only, use get_object_vars($obj) directly.
	 *  @return array
	 */
	public function properties()
	{
		return get_object_vars($this);
	}
	
	/** Return an object's properties as an indexed array.
	 *  @return array
	 */
	public function property_names()
	{
		return array_keys($this->properties());
	}
	
}

/// Abstract base class for SQL queries.
abstract class soo_txp_query extends soo_obj
{
	/// Database table name.
	protected $table		= '';
	/// SQL WHERE expressions.
	protected $where		= array();
	/// SQL ORDER BY expressions.
	protected $order_by		= array();
	/// SQL LIMIT.
	protected $limit		= 0;
	/// SQL OFFSET.
	protected $offset		= 0;
		
	/** Constructor.
	 *  Use $key to match a single row matching on appropriate key column
	 *  If $key is an array, current key is the column and current value
	 *  is the value. Otherwise column will be taken from self::key_column().
	 *  @param table Table name
	 *  @param key Key column value (or array) for WHERE expression
	 */
	public function __construct( $table, $key = null )
	{
		$this->table = trim($table);
		if ( is_array($key) )
			$this->where(key($key), current($key));
		elseif ( $key )
			$this->where($this->key_column($key), $key);
	}
	
	/** Add expression to WHERE clause.
	 *  @param column Column name
	 *  @param value Column value(s) (string, array, or soo_txp_select)
	 *  @param operator Comparison operator
	 *  @param join AND or OR
	 */
	public function where( $column, $value, $operator = '=', $join = '' )
	{
		$join = $this->andor($join);
		
		if ( is_array($value) )
			$value = '(' . implode(',', quote_list(doSlash($value))) . ')';
		elseif ( $value instanceof soo_txp_select )
			$value = '(' . $value->sql() . ')';
		else
			$value = "'$value'";
		
		$this->where[] = ( $join ? "$join " : '' ) . 
			self::quote($column) . " $operator $value";
		return $this;
	}
	
	/** Add a raw expression to WHERE clause.
	 *  Use instead of {@link where()} for complex (e.g. nested) expressions
	 *  @param clause WHERE Expression
	 *  @param join AND or OR
	 */
	public function where_clause( $clause, $join = '' )
	{
		$join = $this->andor($join);
		$this->where[] = ( $join ? "$join " : '' ) . $clause;
		return $this;
	}
	
	/** Add an IN expression to the WHERE clause.
	 *  @param column Column name
	 *  @param list Items to compare against
	 *  @param join AND or OR
	 *  @param in true = IN, false = NOT IN
	 */
	public function in( $column, $list, $join = '', $in = true )
	{
		$in = ( $in ? '' : ' not' ) . ' in';
		if ( is_string($list) ) 
			$list = do_list($list);
		return $this->where($column, $list, $in, $join);
	}
	
	/** Alias of in() with $in = false (add a NOT IN expression).
	 *  @param column Column name
	 *  @param list Items to compare against
	 *  @param join AND or OR
	 */
	public function not_in( $column, $list, $join = '' )
	{
		return $this->in( $column , $list , $join , false );
	}
	
	/** Add a MySQL REGEXP expression to the WHERE clause.
	 *  @param pattern MySQL REGEXP pattern
	 *  @param subject Column name or string to match
	 *  @param join AND or OR
	 */
	public function regexp( $pattern, $subject, $join = '' )
	{
		return $this->where($subject, $pattern, 'regexp', $join);
	}
	
	protected function andor( $join = 'and' )
	{
		$join = strtolower($join);
		return count($this->where) ? 
			( in_list($join, 'and,or') ? $join : 'and' ) : '';
	}
	
	/** Quote with backticks.
	 *  Only quote items consisting of alphanumerics, $, and/or _
	 *  @param identifier Item to quote
	 *  @return string
	 */
	public static function quote( $identifier )
	{
		return preg_match('/^[a-z_$\d]+$/i', $identifier) ?
			"`$identifier`" : $identifier;
	}
	
	/** Add an expression to the ORDER BY array.
	 *  Example: $query->order_by('foo ASC, bar DESC');
	 *  @param expr Comma-separated list, or array, of expressions
	 *  @param direction ASC or DESC
	 */
	public function order_by( $expr, $direction = '' )
	{
		if ( $expr )
		{
			if ( ! is_array($expr) ) $expr = do_list($expr);
			foreach ( $expr as $x )
			{
				if ( preg_match('/(\S+)\s+(\S+)/', $x, $match) )
					list( , $column, $direction) = $match;
				else
					$column = $x;
				if ( in_array(strtolower($column), array('random', 'rand', 'rand()')) )
					$column = 'rand()';
				else 
					$direction = in_array(strtolower($direction), array('asc', 'desc')) ? $direction : '';
				$this->order_by[] = $column . ( $direction ? ' ' . $direction : '');
			}
		}
		return $this;
	}
	
	/** Alias of order_by() for a single column ASC.
	 *  @param col Column name
	 */
	public function asc( $col )
	{
		return $this->order_by($col, 'asc');
	}

	/** Alias of order_by() for a single column DESC.
	 *  @param col Column name
	 */
	public function desc( $col )
	{
		return $this->order_by($col, 'desc');
	}
	
	/** Add a FIELD() expression to the ORDER BY array.
	 *  For preserving an arbitrary sort order, e.g. '7,5,12,1'
	 *  Note that FIELD() is a MySQL-specific function (not standard SQL)
	 *  @param field Column name
	 *  @param list Comma-separated list, or array, of values in order
	 */
	public function order_by_field( $field, $list )
	{
		if ( is_string($list) ) $list = do_list($list);
		if ( count($list) )
			$this->order_by[] = 'field(' . self::quote($field) . ', ' .
				implode(', ', quote_list(doSlash($list))) . ')';
		return $this;
	}
	
	/** Add a LIMIT to the query.
	 *  @param limit Maximum number of items to return
	 */
	public function limit( $limit )
	{
		if ( $limit = intval($limit) )
			$this->limit = ' limit ' . $limit;
		return $this;
	}
	
	/** Add an OFFSET to the query.
	 *  @param offset Number of items to skip
	 */
	public function offset( $offset )
	{
		if ( $offset = intval($offset) )
			$this->offset = ' offset ' . $offset;
		return $this;
	}
	
	/** Assemble and return the query clauses as a string.
	 *  @return string
	 */
	protected function clause_string()
	{
		return implode(' ', $this->where) .
			( count($this->order_by) ? ' order by ' . implode(', ', $this->order_by) : '' ) .
			( $this->limit ? $this->limit : '' ) . ( $this->offset ? $this->offset : '' );
	}
	
	/** Number of items the query will return.
	 *  Runs the query with COUNT() as the select expression
	 *  @return int|false
	 */
	public function count()
	{
		return getCount($this->table, $this->clause_string() ? $this->clause_string() : '1=1');
	}
	
	/** Return the key column name for the current table.
	 *  Some Txp tables have multiple indexes. 
	 *  If $key_value is provided, column of the matching type will be returned.
	 *  Otherwise the numeric index will be returned in preference to the string index.
	 *  @param key_value
	 *  @return string
	 */
	public function key_column( $key_value = null )
	{
		$numeric_index	= array(
			'textpattern'		=> 'ID',
			'txp_category'		=> 'id',
			'txp_discuss'		=> 'discussid',
			'txp_file'			=> 'id',
			'txp_image'			=> 'id',
			'txp_lang'			=> 'id',
			'txp_link'			=> 'id',
			'txp_log'			=> 'id',
			'txp_users'			=> 'user_id',
		);
		$string_index		= array(
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
		if ( isset($numeric_index[$this->table]) )
			$nx = $numeric_index[$this->table];
		if ( isset($string_index[$this->table]) )
			$sx = $string_index[$this->table];
			
 		if ( is_numeric($key_value) )
			return isset($nx) ? $nx : null;
 		if ( is_string($key_value) )
			return isset($sx) ? $sx : null;
		return isset($nx) ? $nx : ( isset($sx) ? $sx : null );
	}
	
}

/// Class for SELECT queries.
class soo_txp_select extends soo_txp_query
{
	
	/// SQL SELECT expressions.
	protected $select		= array();
	/// Whether to add DISTINCT
	protected $distinct		= false;
	
	/** Constructor.
	 *  @param table Table name
	 *  @param select item(s) to select
	 *  @param key Optional key for selecting a single record
	 */
	public function __construct( $table, $key = null, $select = null )
	{
		parent::__construct($table, $key);
		if ( $select ) $this->select($select);
	}
	
	/** Add items to the SELECT array.
	 *  @param list	comma-separated list, or array, of items to select
	 */
	public function select( $list = '*' )
	{
		if ( is_string($list) ) $list = do_list($list);
		foreach ( $list as $col ) $this->select[] = parent::quote($col);
		return $this;
	}
	
	/** Add the DISTINCT keyword to the query
	 *  @return $this to allow method chaining
	 */
	public function distinct( )
	{
		$this->distinct = true;
		return $this;
	}
	
	protected function init_query()
	{
		if ( ! count($this->select) ) $this->select();
		if ( ! count($this->where) ) $this->where[] = '1 = 1';
	}
	
	/** Return a single record, or empty array if no matching records.
	 *  @return array
	 */
	public function row()
	{
		$this->init_query();
		return safe_row(implode(',', $this->select), $this->table, 
			$this->clause_string());
	}
	
	/** Return all records, or empty array if no matching records.
	 *  @return array
	 */
	public function rows()
	{
		$this->init_query();
		return safe_rows( ( $this->distinct ? 'distinct ' : '') . 
			implode(',', $this->select), $this->table, $this->clause_string());
	}
	
	/** Return the query as a string.
	 *  @return string
	 */
	public function sql()
	{
		$this->init_query();
		return 'select ' . implode(',', $this->select) . ' from ' . safe_pfx($this->table) . ' where ' . $this->clause_string();
	}
	
}

/// Class for SELECT ... LEFT JOIN queries.
/// Currently very incomplete; needs to override most parent methods
/// to specify which table each expression refers to.
class soo_txp_left_join extends soo_txp_select
{
	/// Join table name
	protected $left_join;
	/// ON expression for join
	protected $join_on;
	/// Left table alias
	const t1 = 't1';
	/// Join table alias
	const t2 = 't2';
	
	/** Constructor.
	 *  @param table Left table
	 *  @param left_join Join table
	 *  @param col1 Key column name for left table
	 *  @param col2 Key column name for join table
	 */
	public function __construct ( $table, $left_join, $col1, $col2 )
	{
		parent::__construct($table);
		$this->left_join = $left_join;
		$this->join_on = self::t1 . '.' . self::quote($col1) . ' = ' . self::t2 . '.' . self::quote($col2);
	}
	
	/** Like parent function, optionally prepending table name/alias.
	 *  @example self::quote('col', self::t1) returns t1.`col`
	 *  @param identifier Column name or alias
	 *  @param prefix Table name or alias
	 */
	public static function quote( $identifier, $prefix = '' )
	{
		return ( $prefix ? $prefix . '.' : '' ) . parent::quote($identifier);
	}
	
	/** Add items to the SELECT array from the left table.
	 *  @param list	comma-separated list, or array, of items to select
	 */
	public function select( $list = '*' )
	{
		return self::select_from($list, self::t1);
	}
	
	/** Add items to the SELECT array from the join table.
	 *  @param list	comma-separated list, or array, of items to select
	 */
	public function select_join( $list = '*' )
	{
		return self::select_from($list, self::t2);
	}

	private function select_from( $list, $table )
	{
		if ( is_string($list) ) $list = do_list($list);
		foreach ( $list as $col ) $this->select[] = self::quote($col, $table);
		return $this;
	}

	/** Add expression to WHERE clause, referring to the left table.
	 *  @param column Column name
	 *  @param value Column value
	 *  @param operator Comparison operator
	 *  @param join AND or OR
	 */
	public function where( $column, $value, $operator = '=', $join = '' )
	{
		return parent::where(self::quote($column, self::t1), $value, $operator, $join);
	}

	/** Add expression to WHERE clause, referring to the join table.
	 *  @param column Column name
	 *  @param value Column value
	 *  @param operator Comparison operator
	 *  @param join AND or OR
	 */
	public function where_join( $column, $value, $operator = '=', $join = '' )
	{
		return parent::where(self::quote($column, self::t2), $value, $operator, $join);
	}
	
	/** Add an IS NULL expression, for selecting only items not in the join table
	 *  @param column Key column in join table
	 */
	public function where_join_null( $column )
	{
		$join = parent::andor('');
		$this->where[] = ( $join ? $join . ' ' : '' ) . self::quote($column, self::t2) . ' is null';
		return $this;
	}

	/** Add a column to the ORDER BY array.
	 *  @param col Column name from left table
	 *  @param direction ASC or DESC
	 */
	public function order_by( $cols, $direction = '' )
	{
		$direction = in_array(strtolower($direction), array('asc', 'desc')) ? $direction : '';
		if ( is_string($cols) ) $cols = do_list($cols);
		foreach ( $cols as $col )
		{
			if ( $col == 'random' or $col == 'rand' or $col == 'rand()' )
			{
				$col = 'rand()';
				$direction = '';
			}
			$this->order_by[] = self::quote($col, self::t1) . ( $direction ? ' ' . $direction : '');
		}
		return $this;
	}

	/** Override parent function; return a single record.
	 *  @return array
	 */
	public function row()
	{
		return getRow($this->sql());
	}
	
	/** Override parent function; return all records
	 *  @return array
	 */
	public function rows()
	{
		return getRows($this->sql());
	}
	
	/** Assemble query.
	 *  @return string
	 */
	public function sql()
	{
		parent::init_query();
		return 'select ' . implode(',', $this->select) . ' from ' . self::quote(safe_pfx($this->table)) . ' as ' . self::t1 . ' left join ' . self::quote(safe_pfx($this->left_join)) . ' as ' . self::t2 . ' on ' . $this->join_on . ' where ' . $this->clause_string();
	}
	
	/** Return result of a SELECT COUNT(*) query
	 *  @int
	 */
	public function count()
	{
		$select = $this->select;
		$this->select = array('count(*)');
		$r = safe_query($this->sql());
		$this->select = $select;
		if ( $r )
			return mysql_result($r, 0);
	}
}

/// Class for INSERT and UPDATE queries.
class soo_txp_upsert extends soo_txp_query
{
	// For use with VALUES() syntax
	/// Columns to be explicitly set.
	public $columns			= array();
	/// VALUES() values.
	public $values			= array();
	/// VALUES() clause
	protected $values_clause	= '';
	
	// For use with SET col_name=value syntax
	/// SET columns and values.
	public $set				= array();
	/// SET clause.
	protected $set_clause	= '';
	
	/** Constructor.
	 *  Use $col to update a single row matching on appropriate key column
	 *  (usually `name` or `id`)
	 *  @param init Table name, soo_txp_rowset, or soo_txp_row
	 *  @param col Key column value for WHERE expression
	 */
	public function __construct( $init, $col = null )
	{
		if ( is_scalar($init) ) 
			parent::__construct($init, $col);
		elseif ( $init instanceof soo_txp_rowset )
		{
			$this->table = $init->table;
			if ( $col )
				$this->columns = is_array($col) ? $col : do_list($col);
			else
				$this->columns = array_keys(current($init->data));
			foreach ( $init->rows as $r )
				$this->values[] = $r->data;
		}
		elseif ( $init instanceof soo_txp_row )
		{
			$this->table = $init->table;
			if ( $col )
				$this->columns = is_array($col) ? $col : do_list($col);
			else
				$this->columns = array_keys($init->data);
			$this->values[] = $init->data;
		}
	}

	/** Add a column:value pair to the $set array.
	 *  @param column Column name
	 *  @param value Column value
	 */
	public function set( $column, $value )
	{
		$this->set[$column] = $value;
		return $this;
	}
	
	private function init_query()
	{
		if ( count($this->set) )
		{
			foreach ( $this->set as $col => $val )
			{
				$val = is_numeric($val) ? $val : "'$val'";
				$set_pairs[] = "$col = $val";
			}
			$this->set_clause = implode(',', $set_pairs);
		}
		elseif ( count($this->values) )
		{
			if ( count($this->columns) )
				$this->values_clause = '(`' . implode('`,`', $this->columns) . '`) ';
			$this->values_clause .= ' values ';
			foreach ( $this->values as $vs )
			{
				$this->values_clause .= '(';
				foreach ( $vs as $v )
					$this->values_clause .= ( is_numeric($v) ? $v : "'$v'" ) . ',';
				$this->values_clause = rtrim($this->values_clause, ',') . '),';
			}
			$this->values_clause = rtrim($this->values_clause, ',') . ';';
		}
	}
	
	/** Run the query.
	 *  Runs an UPDATE query if $where is set, otherwise INSERT
	 *  @return bool success or failure
	 */
	public function upsert()
	{
		$this->init_query();
		if ( count($this->where) )
			return safe_upsert($this->table, $this->set_clause, $this->clause_string());
		if ( $this->set_clause )
			return safe_insert($this->table, $this->set_clause);
		if ( $this->values_clause )
			return safe_query('insert into ' . safe_pfx($this->table) . $this->values_clause);
	}
}

/// Class for DELETE queries.
class soo_txp_delete extends soo_txp_query
{
	/** Execute the DELETE query.
	 *  @return bool	Query success or failure
	 */
	public function delete()
	{
		if ( count($this->where) )
			return safe_delete($this->table, $this->clause_string());
	}
}

/// Class for data results sets.
class soo_txp_rowset extends soo_obj
{

	/// Database table name.
	protected $table		= '';
	
	/// Array of soo_txp_row objects.
	public $rows			= array();
	
	/** Constructor.
	 *  $init can be a soo_txp_select object, mysql result resource,
	 *  or an array of records.
	 *  If $index is provided or if $init is a soo_txp_select object, 
	 *  the $rows array will be indexed by key column values.
	 *  @param init Data array or query object to initialize rowset
	 *  @param table Txp table name
	 */
	public function __construct( $init = array(), $table = '', $index = null )
	{
		if ( $init instanceof soo_txp_select )
		{
			$table = $init->table;
			$index = $init->key_column();
			$init = $init->rows();
		}
		if ( is_resource($init) and mysql_num_rows($init) )
		{
			while ( $r = mysql_fetch_assoc($init) )
				$data[] = $r;
			mysql_free_result($init);
			$init = $data;
		}
		$this->table = $table;
		if ( is_array($init) and count($init) )
		{
			foreach ( $init as $r )
				if ( $index ) 
					$this->add_row($r, $table, $r[$index]);
				else
					$this->add_row($r, $table);
		}
	}
	
	/** Generic getter, overriding parent method.
	 *  If $property is not a property name, look for row object
	 *  matching this index value
	 *  @param property Property name, or rowset index
	 */
	public function __get( $property )
	{
		if ( property_exists($this, $property) )
			return $this->$property;
		if ( array_key_exists($property, $this->rows) )
			return $this->rows[$property];
	}
	
	/** Return an array of all values for a particular column (field).
	 *  If $key is set, make it an associative array, using the value
	 *  of the key column as the array index
	 *  @param field Column (field) name
	 *  @param key Key column name
	 */
	public function field_vals( $field, $key = null )
	{	
		foreach ( $this->rows as $r )
			if ( ! is_null($key) )
				$out[$r->$key] = $r->$field;
			else
				$out[] = $r->$field;
		return isset($out) ? $out : array();
	}
	
	/** Add a soo_txp_row object to $rows.
	 *  @param data soo_txp_row object or key value
	 *  @param table Txp table name
	 *  @param i index value for new row in $rows array
	 */
	public function add_row( $data, $table = null, $i = null )
	{
		$table = is_null($table) ? $this->table : $table;
		$r = $data instanceof soo_txp_row ? 
			$data : ( $table == 'txp_image' ?
				new soo_txp_img($data) : new soo_txp_row($data, $table) );
		if ( is_null($i) )
			$this->rows[] = $r;
		else
			$this->rows[$i] = $r;
		return $this;
	}
	
	/** Split off a subset of rows as a new soo_txp_rowset object
	 *  @param key array key for finding rows for the new set
	 *  @param value key column value to match for rows for the new set
	 *  @param index array index for new rowset rows
	 *  @return soo_txp_rowset
	 */
	public function subset( $key, $value, $index = null )
	{
		$out = new self;
		foreach ( $this->rows as $row )
			if ( $row->$key == $value )
				$out->add_row($row, null, is_null($index) ? null : $row->$index);
		return $out;
	}
}

/// Class for Joe Celko nested sets, aka modified preorder tree
class soo_nested_set extends soo_txp_rowset
{
	/** Constructor.
	 *  $init can be a soo_txp_rowset object, 
	 *  otherwise see parent::__construct()
	 *  @param init Data array or query object to initialize rowset
	 *  @param table Txp table name
	 */
	public function __construct( $init = array(), $table = '', $index = null )
	{
		if ( $init instanceof soo_txp_rowset )
		{
			$this->table = $init->table;
			$this->rows = $init->rows;
		}
		else
			parent::__construct($init, $table);
	}
	
	/** Return all rows as a nested array of row objects.
	 *  Each array item is either a soo_txp_row object,
	 *  or an array of such. If an array, it is the children of the
	 *  immediately preceding item.
	 *  This is a recursive function.
	 *  @param rows Internal use only.
	 *  @param rgt Internal use only.
	 */
	public function as_object_array( &$rows = null, $rgt = null )
	{
		if ( is_null($rows) )
		{
			$rows = $this->rows;
			$root = current($rows);
			$rgt = $root->rgt;
		}
		while ( $out[] = $node = array_shift($rows) and $node->rgt <= $rgt )
			if ( $node->rgt > $node->lft + 1 )
				$out[] = $this->as_object_array($rows, $node->rgt);
 		if ( $node and $node->rgt > $rgt )
 			array_unshift($rows, array_pop($out));
 		if ( is_null($out[count($out) - 1]) )
 			array_pop($out);
		return $out;
	}

	/** Return all rows as a nested array of values.
	 *  Each array item is either a node, as $index_column => $value_column,
	 *  or an array of such. If an array, it is the children of the
	 *  immediately preceding item, and has the key 'x_c' where 'x' is
	 *  the parent node's index.
	 *  This is a recursive function.
	 *  @param index_column Column for node index value
	 *  @param index_column Column for node value
	 *  @param rows Internal use only.
	 *  @param rgt Internal use only.
	 */
	public function as_array( $index_column, $value_column, &$rows = null, $rgt = null )
	{
		if ( is_null($rows) )
		{
			$rows = $this->rows;
			$root = current($rows);
			$rgt = $root->rgt;
		}
		while ( $node = array_shift($rows) and $node->rgt <= $rgt )
		{
			$out[$node->$index_column] = $node->$value_column;
			if ( $node->rgt > $node->lft + 1 )
				$out[$node->$index_column . '_c'] = $this->as_array($index_column, $value_column, $rows, $node->rgt);
		}
 		if ( $node and $node->rgt > $rgt )
 			array_unshift($rows, $node);
		return $out;
	}
	
	/** Split off a subtree of rows as a new soo_nested_set object
	 *  @param root id of subtree root node
	 *  @return soo_txp_rowset
	 */
	public function subtree( $root, $index = null )
	{
		$out = new self;
		$root = $this->rows[$root];
		foreach ( $this->rows as $row )
			if ( $row->lft >= $root->lft and $row->rgt <= $root->rgt )
				$out->add_row($row, null, is_null($index) ? null : $row->$index);
		return $out;
	}
}

/// Class for single data records.
class soo_txp_row extends soo_obj
{
	/// Database table name.
	protected $table		= '';
	/// Database record.
	protected $data			= array();
	
	/** Constructor.
	 *  @param init Key value, soo_txp_select object, or data array
	 *  @param table Txp table name
	 */
	public function __construct( $init = array(), $table = '' )
	{
		if ( is_scalar($init) and $table )
			$init = new soo_txp_select($table, $init);
		if ( $init instanceof soo_txp_select )
		{
			$table = $init->table;
			$init = $init->row();
		}
		if ( is_array($init) )
			foreach ( $init as $k => $v )
				$this->data[$k] = $v;
		$this->table = $table;
	}

	/** Generic getter, overriding parent method.
	 *  Look for $property in the $data array first
	 *  @param property Column or property name
	 *  @return mixed Data field or object property
	 */
	public function __get( $property )
	{
		return isset($this->data[$property]) ? $this->data[$property] 
			: parent::__get($property);
	}
	
	/// Override parent method, to keep $data protected.
	public function data( )
	{
		return;
	}
	
	/// @return array Database record (column:value array)
	public function properties( )
	{
		return $this->data;
	}
}

/// Class for Txp image records.
class soo_txp_img extends soo_txp_row
{
	/// URL of full-size image.
	protected $full_url		= '';
	/// URL of thumbnail image.
	protected $thumb_url	= '';
	
	/** Constructor.
	 *  @param init Txp image id
	 */
	public function __construct( $init )
	{
		global $img_dir;
		parent::__construct($init, 'txp_image');
		$this->full_url = hu . $img_dir . '/' . $this->id . $this->ext;
		if ( $this->thumbnail )
			$this->thumb_url = hu . $img_dir . '/' . $this->id . 't' . $this->ext;
	}
}

/// Abstact base class for (X)HTML elements.
abstract class soo_html extends soo_obj
{
// HTML element class. Instantiation takes a required 'name' argument and an
// optional 'atts' array: items with keys matching HTML attributes 
// will be transferred to the new object.
// 
// See the soo_html_img class for an example of how to extend this class.
	
	/// @name Inherent properties
	//@{
	/// (X)HTML element name
	protected $element_name	= '';
	/// container (false) or empty element (true)
	protected $is_empty		= 0;
	/// Element content array (strings or soo_html objects)
	protected $contents		= array();
	//@}
	
	/// @name Common (X)HTML attributes
	//@{
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
	//@}
	
	/** Constructor.
	 *  @param element_name (X)HTML element name
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct($element_name, $atts, $content = null, $is_empty = 0)
	{
		$this->element_name($element_name)->is_empty($is_empty);
		if ( empty($atts) )
			$atts = array();
		foreach ( $this as $property => $value )
			if ( in_array($property, array_keys($atts)) )
				$this->$property($atts[$property]);
		if ( $content )
			$this->contents($content);
	}
	
	/** Validate and set id attribute.
	 *  Important: (X)HTML attribute, NOT a database ID!
	 *  @param id (must begin with a letter)
	 */
	public function id($id)
	{
		if ( $id and !preg_match('/^[a-z]/', strtolower(trim($id))) )
		{
			$this->id = 'invalid_HTML_ID_value_from_Soo_Txp_Obj';
			return false;
		}
		$this->id = $id;
		return $this;
	}
	
	/** Add string|object|array to $contents array.
	 *  @param content
	 */
	public function contents($content)
	{
		if ( ! $this->is_empty )
		{
			$content = is_array($content) ? $content : array($content);
			foreach ( $content as $i => $item )
				if ( is_null($item) )
					unset($content[$i]);
			$this->contents = array_merge($this->contents, $content);
		}
		return $this;
	}
	
	/** Return an attribute:value array of all (X)HTML attributes.
	 *  Hard-coded list of excluded properties is rather lame,
	 *  but I haven't thought of anything better yet.
	 *  @return array
	 */
	private function html_attributes()
	{
		return array_diff_key($this->properties(), array_flip(array('element_name', 'is_empty', 'contents')));
	}
	
	/** Create (X)HTML tag(s) string for this element.
	 *  Recursively tags contained elements
	 *  @return string
	 */
	public function tag()
	{
		$out = '<' . $this->element_name;
		
		foreach ( $this->html_attributes() as $property => $value )
			if ( $value or $property == 'alt' )
				$out .= " $property=\"$value\"";
		
		if ( $this->is_empty )
			return $out . ' />';
					
		$out .= '>' . $this->newline();
				
		foreach ( $this->contents as $item )
			
			if ( $item instanceof soo_html )
				$out .= $item->tag();		
			else
				$out .= $item;
		
		return $out . $this->newline() . "</$this->element_name>" . $this->newline();
	}
	
	/** Convert $this->$property with htmlspecialchars().
	 *  @param property Attribute name
	 *  @return string (X)HTML-escaped attribute value
	 */
	protected function html_escape( $property )
	{
		$this->$property = htmlspecialchars($this->$property);
		return $this;
	}
	
	private function newline()
	{
		return ( ! $this->is_empty and count($this->contents) > 1 ) ? n : '';
	}
}

/// Class for (X)HTML anchor elements.
class soo_html_anchor extends soo_html
{
	/// @name (X)HTML attributes
	//@{
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
	//@}
	
	/** @param atts URI string (href value) or attribute array.
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $atts = array(), $content = '' )
	{
		if ( ! is_array($atts) )
			$atts = array('href' => $atts);
		parent::__construct( 'a', $atts, $content );
	}
	
}

/// Class for (X)HTML br elements.
class soo_html_br extends soo_html
{
	/** Constructor.
	 *  @param atts Attributes (array of name=>value pairs)
	 */
	public function __construct ( $atts = array() )
	{
		parent::__construct('br', $atts, null, true);
	}
}

/// Class for (X)HTML form elements
class soo_html_form extends soo_html
{
	/// @name (X)HTML attributes
	//@{
	protected $action			= '';
	protected $method			= '';
	protected $enctype			= '';
	protected $accept_charset	= '';
	protected $onsubmit			= '';
	protected $onreset			= '';
	//@}

	/** Constructor.
	 *  @param init Form action or attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $init = array(), $content = '' )
	{
		$atts = is_string($init) ? array('action' => $init) : $init;
		if ( ! isset($atts['method']) )
			$atts['method'] = 'post';
		if ( is_array($atts['action']) )
		{
			foreach ( $atts['action'] as $k => $v )
				$atts['action'][$k] = "$k=$v";
			$atts['action'] = '?' . implode(a, $atts['action']);
		}
		parent::__construct('form', $atts, $content );
	}
}

/// Class for (X)HTML label elements
class soo_html_label extends soo_html
{
	/// @name (X)HTML attributes
	//@{
	protected $for				= '';
	protected $onfocus			= '';
	protected $onblur			= '';
	//@}	

	/** Constructor.
	 *  @param init 'for' attribute or array of name=>value pairs
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $init = array(), $content = '' )
	{
		if ( is_string($init) )
			$init = array('for' => $init);
		parent::__construct('label', $init, $content);
	}
}

abstract class soo_html_form_control extends soo_html
{
	/// @name (X)HTML attributes
	//@{
	protected $name				= '';
	protected $disabled			= '';
	protected $tabindex			= '';
	protected $onfocus			= '';
	protected $onblur			= '';
	//@}	
}

/// Class for (X)HTML input elements
class soo_html_input extends soo_html_form_control
{
	
	/// @name (X)HTML attributes
	//@{
	protected $type				= '';
	protected $value			= '';
	protected $checked			= '';
	protected $size				= '';
	protected $maxlength		= '';
	protected $src				= '';
	protected $alt				= '';
	protected $usemap			= '';
	protected $readonly			= '';
	protected $accept			= '';
	protected $onselect			= '';
	protected $onchange			= '';
	//@}	

	/** Constructor.
	 *  @param type Input type (text|checkbox|radio etc.)
	 *  @param atts Attributes (array of name=>value pairs)
	 */
	public function __construct ( $type = 'text', $atts = array() )
	{
		$this->type($type);
		parent::__construct('input', $atts, null, true);
	}
}

/// Class for (X)HTML select elements
class soo_html_select extends soo_html_form_control
{
	/// @name (X)HTML attributes
	//@{
	protected $multiple			= '';
	protected $size				= '';
	protected $onchange			= '';
	//@}	

	/** Constructor.
	 *  If $content is an array, each item will be added as
	 *  a soo_html_option element (assumes value=>text array)
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (soo_html_option objects)
	 */
	public function __construct ( $atts = array(), $content = array() )
	{
		parent::__construct('select', $atts);
		if ( ! is_array($content) ) $content = array($content);
		foreach ( $content as $i => $item )
		{
			if ( $item instanceof soo_html_option )
				$this->contents($item);
			else
				$this->contents(new soo_html_option(array('value' => $i), $item));
		}
	}
}

/// Class for (X)HTML option elements
class soo_html_option extends soo_html_form_control
{
	/// @name (X)HTML attributes
	//@{
	protected $value			= '';
	protected $selected			= '';
	protected $label			= '';
	//@}	

	/** Constructor.
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (displayed option text)
	 */
	public function __construct ( $atts = array(), $content = array() )
	{
		parent::__construct('option', $atts, $content);
	}
}

/// Class for (X)HTML textarea elements
class soo_html_textarea extends soo_html_form_control
{
	/// @name (X)HTML attributes
	//@{
	protected $rows				= '';
	protected $cols				= '';
	protected $readonly			= '';
	protected $onselect			= '';
	protected $onchange			= '';
	//@}	

	/** Constructor.
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string)
	 */
	public function __construct ( $atts = array(), $content = '' )
	{
		parent::__construct('textarea', $atts, $content);
	}
}

/// Class for (X)HTML img elements
class soo_html_img extends soo_html
{
	/// @name (X)HTML attributes
	//@{
	protected $alt				= '';
	protected $src				= '';
	protected $width			= '';
	protected $height			= '';
	//@}
			
	/** Constructor.
	 *  @param init soo_txp_img object,  attribute array, or src value
	 *  @param thumbnail Thumbnail or full image?
	 *  @param escape HTML-escape title and alt attributes?
	 */
	public function __construct ( $init = array(), $thumbnail = false, $escape = true )
	{
		if ( $init instanceof soo_txp_img )
		{
			$src = $thumbnail ? $init->thumb_url : $init->full_url;
			$init = $init->properties();
			if ( $thumbnail )
			{
				if ( ! empty($init['thumb_h']) )
				{ // pre Txp 4.2 compatibility
					$init['h'] = $init['thumb_h'];
					$init['w'] = $init['thumb_w'];
				}
				else $init['h'] = ( $init['w'] = 0 );
			}
			$init['height'] = $init['h'];
			$init['width'] = $init['w'];
			$init['title'] = $init['caption'];
			$init['src'] = $src;
			unset($init['id']); // don't want database id as HTML id!
		}
		elseif ( ! is_array($init) )
			$init['src'] = $init;
		
		parent::__construct('img', $init, null, true);
		if ( $escape )
			$this->html_escape('title')->html_escape('alt');
	}
	
}

/// Class for (X)HTML p elements
class soo_html_p extends soo_html
{
	/** Constructor.
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $atts = array(), $content = '' )
	{
		parent::__construct('p', $atts, $content);
	}
}

/// Class for (X)HTML table elements
class soo_html_table extends soo_html
{
	/// @name (X)HTML attributes
	//@{
	protected $summary				= '';
	protected $width				= '';
	protected $border				= '';
	protected $frame				= '';
	protected $rules				= '';
	protected $cellspacing			= '';
	protected $cellpadding			= '';
	//@}

	/** Constructor.
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 *  @see contents() for $content options
	 */
	public function __construct ( $atts = array(), $content = null )
	{
		$this->contents($content);
		parent::__construct( 'table', $atts );
	}
	
	/** Add string|object|array to $contents array.
	 *  If this is a soo_html_table_component object, or an array of such,
	 *  it will be added directly. Otherwise it is assumed to be a single
	 *  item or 2-dimensional array of such, and will be formed into a grid
	 *  of table cells/rows.
	 *  @param content
	 */
	public function contents($content)
	{
		if ( is_null($content) ) return $this;
		
		$content = is_array($content) ? $content : array($content);
		foreach ( $content as $item )
		{
			if ( is_object($item) and ( $item instanceof soo_html_table_component or $item instanceof soo_html_caption) )
				$this->contents[] = $item;
			else
			{
				$item = is_array($item) ? $item : array($item);
				foreach ( $item as $i => $cell )
					if ( ! $cell instanceof soo_html_table_component )
						$item[$i] = new soo_html_td(array(), $cell);
				$this->contents[] = new soo_html_tr(array(), $item);
			}
		}
		return $this;
	}
}

/// Class for (X)HTML caption elements
class soo_html_caption extends soo_html_table_component
{
	public function __construct ( $atts = array(), $content )
	{
		parent::__construct( 'caption', $atts, $content );
	}
}

/// Abstract base class for (X)HTML table components
abstract class soo_html_table_component extends soo_html
{
	/// @name (X)HTML attributes
	//@{
	protected $align				= '';
	protected $char					= '';
	protected $charoff				= '';
	protected $valign				= '';
	//@}

	/** Constructor.
	 *  @param component (X)HTML element name
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $component, $atts = array(), $content = '' )
	{
		parent::__construct( $component, $atts, $content );
	}
}

/// Class for (X)HTML thead elements
class soo_html_thead extends soo_html_table_component
{
	/** Constructor.
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $atts = array(), $content = '' )
	{
		parent::__construct( 'thead', $atts, $content );
	}
}

/// Class for (X)HTML tbody elements
class soo_html_tbody extends soo_html_table_component
{
	/** Constructor.
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $atts = array(), $content = '' )
	{
		parent::__construct( 'tbody', $atts, $content );
	}
}

/// Class for (X)HTML tfoot elements
class soo_html_tfoot extends soo_html_table_component
{
	/** Constructor.
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $atts = array(), $content = '' )
	{
		parent::__construct( 'tfoot', $atts, $content );
	}
}

/// Class for (X)HTML tr elements
class soo_html_tr extends soo_html_table_component
{
			
	/** Constructor.
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $atts = array(), $content = '' )
	{
		parent::__construct( 'tr', $atts, $content );
	}	
}

/// Abstract base class for (X)HTML table cells
abstract class soo_html_table_cell extends soo_html_table_component
{
	/// @name (X)HTML attributes
	//@{
	protected $rowspan			= '';
	protected $colspan			= '';
	protected $headers			= '';
	protected $abbr				= '';
	protected $scope			= '';
	protected $axis				= '';
	//@}

	/** Constructor.
	 *  @param cell_type Element name (td, th)
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $cell_type, $atts = array(), $content = '' )
	{
		parent::__construct( $cell_type, $atts, $content );		
	}
}

/// Class for (X)HTML th elements
class soo_html_th extends soo_html_table_cell
{
	/** Constructor.
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $atts = array(), $content = '' )
	{
		parent::__construct( 'th', $atts, $content );
	}
}

/// Class for (X)HTML td elements
class soo_html_td extends soo_html_table_cell
{
	/** Constructor.
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $atts = array(), $content = '' )
	{
		parent::__construct( 'td', $atts, $content );
	}
		
}

/// Base class for (X)HTML ol and ul elements
abstract class soo_html_list extends soo_html
{
	/** Constructor.
	 *  If $content is an array, each item that is not an array will be added
	 *  as a soo_html_li object; each item that is an array will be added
	 *  to the previous soo_html_li object as a new list of the same class
	 *  (hence this is a recursive function).
	 *  Any other content will be added as a soo_html_li object.
	 *  @param element_name Element namd (ol or ul)
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $element_name, $atts, $content, $class )
	{
		if ( ! is_array($content) )
			$content = array($content);
		$prev = null;
		foreach ( $content as $i => &$item )
		{
			if ( is_array($item) )
			{
				if ( ! is_null($prev) and $content[$prev] instanceof soo_html_li )
				{
					$content[$prev]->contents(new $class($atts, $item));
					unset($content[$i]);
				}
				else foreach ( $item as &$li )
					$li = new soo_html_li(array(), $li);
			}
			elseif ( ! $item instanceof soo_html_li )
				$item = new soo_html_li(array(), $item);
			$prev = $i;
		}
		parent::__construct($element_name, $atts, $content);
	}
}

/// Class for (X)HTML ol elements
class soo_html_ol extends soo_html_list
{
	/** Constructor.
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $atts = array(), $content = '' )
	{
		parent::__construct('ol', $atts, $content, __CLASS__);
	}
}

/// Class for (X)HTML ul elements
class soo_html_ul extends soo_html_list
{
	/** Constructor.
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $atts = array(), $content = '' )
	{
		parent::__construct('ul', $atts, $content, __CLASS__);
	}
}

/// Class for (X)HTML li elements
class soo_html_li extends soo_html
{
	/** Constructor.
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $atts = array(), $content = '' )
	{
		parent::__construct('li', $atts, $content);
	}
}

/// Class for (X)HTML span elements
class soo_html_span extends soo_html
{
	/** Constructor.
	 *  @param atts Attributes (array of name=>value pairs)
	 *  @param content Element content (string, soo_html object, or array thereof)
	 */
	public function __construct ( $atts = array(), $content = '' )
	{
		parent::__construct('span', $atts, $content);
	}
}

/////////////////////// MLP Pack compatibility //////////////////////////
// MLP Pack manipulates $_SERVER['REQUEST_URI'], so grab it first

global $plugin_callback;
if( is_array($plugin_callback) 
	and $plugin_callback[0]['function'] == '_l10n_pretext' )
		array_unshift($plugin_callback, array(
			'function'	=>	'soo_uri_mlp', 
			'event'		=>	'pretext', 
			'step'		=>	'', 
			'pre'		=>	0 )
		);

function soo_uri_mlp()
{
	global $soo_request_uri;
	$soo_request_uri =  $_SERVER['REQUEST_URI'];
}
/////////////////////// end MLP Pack compatibility //////////////////////


/// Class for URI query string manipulation
class soo_uri extends soo_obj
{
	/// Full URI
	protected $full;
	
	/// $_SERVER['REQUEST_URI'] value
	protected $request_uri;
	
	/// $_SERVER['QUERY_STRING'] value
	protected $query_string;
	
	/// URI query parameters
	protected $query_params;
	
	/** Constructor.
	 *  Extract REQUEST_URI and QUERY_STRING from $_SERVER,
	 *  and parse into query params and full URI.
	 */
	public function __construct ( )
	{	
		global $soo_request_uri;	// MLP Pack compatibility
		$this->request_uri = $soo_request_uri ? $soo_request_uri :
			$_SERVER['REQUEST_URI'];
		$this->query_string = $_SERVER['QUERY_STRING'];
		$this->full = preg_replace('/\/$/', '', hu) . $this->request_uri();
		parse_str($this->query_string, $this->query_params);
	}
	
	/// Override parent method to prevent direct property manipulation
	public function __call( $request, $args )
	{
		return false;
	}
	
	/** Add, remove, or update a query parameter
	 *  Then run update_from_params() to update $query_string and 
	 *  $request_uri (and the corresponding $_SERVER values) accordingly
	 *  @param name Parameter name
	 *  @param value Parameter value
	 */
	public function set_query_param ( $name, $value = null )
	{
		if ( is_null($value) )
			unset($this->query_params[$name]);
		else
			$this->query_params[$name] = $value;
		$this->update_from_params();
		return $this;
	}
	
	/** Rebuild $query_string and $request_uri (and the corresponding
	 *  $_SERVER values) based on the current $params array
	 */
	private function update_from_params ( )
	{
		$this->query_string = http_build_query($this->query_params);
		$this->request_uri = self::strip_query($this->request_uri) . 
			( $this->query_string ? '?' . $this->query_string : '' );
		$this->full = preg_replace('/\/$/', '', hu) . $this->request_uri();
		$_SERVER['QUERY_STRING'] = $this->query_string;
		$_SERVER['REQUEST_URI'] = $this->request_uri;
	}
	
	/** Remove the query string from a URI
	 *  @return string
	 */
	public function strip_query ( $uri )
	{
		return preg_replace ('/(.+)\?.+/', '$1', $uri);
	}
	
	/** Return the $request_uri after stripping any subdir
	 *  (for Txp subdir installations)
	 *  @return string
	 */
	private function request_uri ( )
	{
		if ( preg_match('&://[^/]+(/.+)/$&', hu, $match) )
		{
			$sub_dir = $match[1];
			return substr($this->request_uri, strlen($sub_dir));
		}
		return $this->request_uri;
	}

}

/// Class for static utility methods
class soo_util
{
	/** Build a Txp tag string.
	 *  @param func Txp tag name (e.g. 'article_custom')
	 *  @param atts Tag attributes
	 *  @param thing Tag contents
	 *  @return string Txp tag
	 */
	public static function txp_tag ( $func, $atts = array(), $thing = null )
	{
		$a = '';
		foreach ( $atts as $k => $v )
			$a .= " $k=\"$v\"";
		return "<txp:$func$a" . ( is_null($thing) ? ' />' : ">$thing</txp:$func>" );		
	}
	
	/** Return a Txp tag string, if it's still the first parse() pass.
	 *  Allows placing a tag with dependencies before its associated controller,
	 *  deferring parsing to the second parse() pass.
	 *  E.g. placing a pagination tag before its associated article tag.
	 *  @param func Txp tag name (e.g. 'article_custom')
	 *  @param atts Tag attributes
	 *  @param thing Tag contents
	 *  @return string Txp tag
	 */
	public static function secondpass ( $func, $atts = array(), $thing = null )
	{
		global $pretext;
		if ( $pretext['secondpass'] ) return; // you only live twice
		return self::txp_tag($func, $atts, $thing);
	}
		
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

A support library for Textpattern plugins. 
 
* "Information and examples":http://ipsedixit.net/txp/21/soo-txp-obj
* "API Documentation":http://ipsedixit.net/api/soo_txp_obj/

h2(#history). Version history

h3(#1_1_0). 1.1.0

* soo_txp_select::distinct() for @SELECT DISTINCT@ queries

h3(#1_1_b_2). 1.1.b.2

* Bugfix: soo_txp_left_join was incompatible with database table prefixes

h3(#b9). 1.1.b.1

9/12/2010

* New class, *soo_txp_left_join* for @SELECT ... LEFT JOIN@ queries
* *soo_txp_upsert* new features:
** properties and methods for @VALUES()@ syntax
** can be initialized with a *soo_txp_rowset* or *soo_txp_row* object
* *soo_txp_rowset* new features:
** can be initialized with a query result resource
** new function @subset()@ to create a new rowset object from an existing one
* New class, *soo_nested_set* for Celko nested sets (modified preorder tree)
* *soo_html_form*, new features and related classes:
** Constructor's @atts@ argument can include an @action@ array for adding query parameters to the form's @action@ attribute
** New classes:
*** *soo_html_label* for labeling form controls
*** *soo_html_input* for input elements
*** *soo_html_select* for select elements (can be initialized with an array which will auto-create appropriate @option@ elements)
*** *soo_html_option* (see above)
*** *soo_html_textarea* for textarea elements
* *soo_html_img* bugfix for pre Txp 4.2 compatibility
* *soo_html_table* can now be initialized with an array of values or table cells; these will automatically be formatted into rows and cells appropriately
* *New class, *soo_html_caption* for table captions
* *soo_html_ol* and *soo_html_ul* can be initialized with nested arrays, automatically generating nested lists (see *soo_nested_set* for a possible source of the nested array)

h3(#b8). 1.0.b.8

7/9/2010

* Documentation updates (DoxyGen compatibility)

h3(#b7). 1.0.b.7

7/4/2010

* *soo_html_img* now adds thumbnail @height@ and @width@ attributes (Txp 4.2.0 or later)

h3(#b6). 1.0.b.6

7/1/2010

* New class: soo_util, for static utility methods

h3(#b5). 1.0.b.5

1/27/2010

* new method: @where_clause()@ in *soo_txp_query*, a catch-all for complex clauses
* minor HTML formatting change to @tag()@ method in *soo_html*

h3(#b4). 1.0.b.4

10/3/2009

* *soo_uri* updated for correct behavior in Txp sub-dir installations

h3(#b3). 1.0.b.3

9/22/2009

* *soo_uri* now gets query params by parsing $_SERVER['QUERY_STRING'] instead of $_GET

h3(#b2). 1.0.b.2

9/16/2009

* New callback function for MLP Pack compatibility with *soo_uri*
* New classes: 
** soo_txp_upsert for SQL insert/update statements
** soo_txp_delete for SQL delete statements
** soo_html_form for form elements
* soo_txp_rowset overrides parent::__get() method

h3(#b1). 1.0.b.1

* Major re-organization and re-write of most classes. 
** The old *Soo_Txp_Data* family has been divided into separate classes for queries and data. 
** There are no longer separate classes for each Txp table.
** All class names now lowercase (these aren't case sensitive anyway).
** Generic setting is now in the form @obj->property()@ instead of @obj->set_property()@.
** Various renaming, code cleaning, etc.

h3(#a6). 1.0.a.6

6/2/2009

What the heck, time to use the same naming convention I'm using with other plugins.

* Added *Soo_Txp_Uri* class, for URI query string manipulation
* *Soo_Html_P* and *soo_html_th* can now have contents set during instantiation. Note that this will break compatibility with some previous uses, e.g. @new Soo_Html_P($atts)@.
* Added @$in@ argument to @where_in()@ method (*Soo_Txp_Data*) to allow "NOT IN" queries

h3(#a5). 1.0.a5

5/13/2009

* Corrected SQL syntax in @order_by_field()@ function of @Soo_Txp_Data@ class
* Modified @tag()@ function of @Soo_Html@ class to handle a PHP 5.2.0 bug

h3(#a4). 1.0.a4

5/1/2009

Added @count()@ and @field()@ methods to the abstract @Soo_Txp_Data@ class.

h3(#a3). 1.0.a3

2/19/2009

Added "Expires" property to @Soo_Txp_Article@ and "load_order" property to @Soo_Txp_Plugin@. These fields were added in Textpattern version 4.0.7.

h3(#a2). 1.0.a2

2/5/2009

No significant changes, but added generic getters and setters (thanks to *jm* for the hint), making the file about 35% smaller.

h3(#a1). 1.0.a1

2/4/2009


</div>
# --- END PLUGIN HELP ---
-->
<?php
}
?>