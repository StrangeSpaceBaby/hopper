<?php

namespace App\racks\hopper\logic;

/**
 * HopperQuery - Query builder with schema validation and observability
 * 
 * Builds queries through method chaining. Validates against schema.
 * Detects anomalies. Outputs various formats. Never touches the database.
 * 
 * Must be passed to $hopper_app->query( $db_query_obj ) for execution.
 */
class query_logic extends logic_logic
{
	private string $model_name; // Used to instantiate the proper model object
	private object $model;
	private string $tenant_id {
		get => $this->tenant_id;
		set( string $value )
		{
			if( !$this->tenant_id )
			{
				$this->tenant_id = $value;
			}
		}
	}

	// Query state
	private string $table = '';
	private string $type = '';
	private array $columns = [ '*' ];
	private array $data = [];
	private array $where = [];
	private array $params = [];
	private array $order = [];
	private array $group = [];
	private array $join = [];
	private ?int $limit = null;
	private ?int $offset = null;
	
	// Current column for where chaining
	private string $current_column = '';
	
	// Validation errors
	private array $errors = [];
	
	// Alerts (potential security issues)
	private array $alerts = [];
	
	public function __construct( string $model_name, string $tenant_id )
	{
		$this->model_name = $model_name;
		$this->tenant_id = $tenant_id;
		$this->set_model();
	}
	
	private function set_model()
	{
		// Instantiate the hopper model for the passed schema
		p( 'somethign somethign model load' );
		exit;
	}

	/**
	 * Reset query state
	 */
	private function reset()
	{
		$this->table = '';
		$this->type = '';
		$this->columns = [ '*' ];
		$this->data = [];
		$this->where = [];
		$this->params = [];
		$this->order = [];
		$this->group = [];
		$this->join = [];
		$this->limit = null;
		$this->offset = null;
		$this->current_column = '';
		$this->errors = [];
		$this->alerts = [];
		
		return $this;
	}
	
	/**
	 * Validate column exists
	 */
	private function check_column( string $column )
	{
		// Handle table.column format
		if ( str_contains( $column, '.' ) )
		{
			list( $tbl, $col ) = explode( '.', $column, 2 );
			if ( !isset( $this->schema[$tbl]['columns'][$col] ) )
			{
				$this->errors[] = [
					'type' => 'INVALID_COLUMN',
					'message' => "Column does not exist: {$column}",
					'column' => $column
				];
				return FALSE;
			}
			return TRUE;
		}
		
		// Check against current table
		if ( !isset( $this->schema[$this->table]['columns'][$column] ) )
		{
			$this->errors[] = [
				'type' => 'INVALID_COLUMN',
				'message' => "Column does not exist: {$this->table}.{$column}",
				'column' => $column
			];
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	 * Validate data value against column schema
	 */
	private function check_value( string $column, $value )
	{
		$col_schema = $this->schema[$this->table]['columns'][$column] ?? null;
		
		if ( null === $col_schema )
		{
			return FALSE;
		}
		
		// Check for tenant_id manipulation
		if ( 'tenant_id' === $column )
		{
			if ( $value !== $this->tenant_id )
			{
				$this->alerts[] = [
					'type' => 'TENANT_MISMATCH',
					'message' => "Attempted tenant_id manipulation",
					'expected' => $this->tenant_id,
					'received' => $value,
					'trace' => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 5 )
				];
				return FALSE;
			}
		}
		
		// Check NOT NULL constraint
		if ( null === $value && FALSE === $col_schema['nullable'] && null === $col_schema['default'] )
		{
			$this->errors[] = [
				'type' => 'NULL_VIOLATION',
				'message' => "NULL value for NOT NULL column: {$column}",
				'column' => $column
			];
			return FALSE;
		}
		
		// Type validation (basic)
		$type = strtolower( $col_schema['type'] );
		
		if ( null !== $value )
		{
			if ( str_contains( $type, 'int' ) && !is_numeric( $value ) )
			{
				$this->errors[] = [
					'type' => 'TYPE_MISMATCH',
					'message' => "Expected numeric for column: {$column}",
					'column' => $column,
					'expected' => 'numeric',
					'got' => gettype( $value )
				];
				return FALSE;
			}
			
			// Check string length
			if ( preg_match( '/varchar\((\d+)\)/', $type, $matches ) )
			{
				$max_len = (int) $matches[1];
				if ( is_string( $value ) && strlen( $value ) > $max_len )
				{
					$this->errors[] = [
						'type' => 'LENGTH_EXCEEDED',
						'message' => "Value too long for column: {$column}",
						'column' => $column,
						'max_length' => $max_len,
						'got_length' => strlen( $value )
					];
					return FALSE;
				}
			}
		}
		
		return TRUE;
	}
	
	/**
	 * Get validation errors
	 */
	public function errors()
	{
		return $this->errors;
	}
	
	/**
	 * Get security alerts
	 */
	public function alerts()
	{
		return $this->alerts;
	}
	
	/**
	 * Check if query is valid
	 */
	public function is_valid()
	{
		return empty( $this->errors ) && empty( $this->alerts );
	}
	
	/**
	 * Get tenant_id (for hopper_app to inject into query)
	 */
	public function get_tenant_id()
	{
		return $this->tenant_id;
	}
	
	/**
	 * Get schema for table
	 */
	public function get_schema( string $table = '' )
	{
		if ( '' === $table )
		{
			$table = $this->table;
		}
		
		return $this->schema[$table] ?? FALSE;
	}
	
	/**
	 * Get columns for table
	 */
	public function get_columns( string $table = '' )
	{
		$schema = $this->get_schema( $table );
		
		if ( FALSE === $schema )
		{
			return FALSE;
		}
		
		return array_keys( $schema['columns'] );
	}
	
	/**
	 * SELECT
	 */
	public function select( string $table )
	{
		$this->reset();
		
		$this->table = $table;
		$this->type = 'SELECT';
		
		return $this;
	}
	
	/**
	 * INSERT
	 */
	public function insert( string $table )
	{
		$this->reset();

		$this->table = $table;
		$this->type = 'INSERT';
		
		return $this;
	}
	
	/**
	 * UPDATE
	 */
	public function update( string $table )
	{
		$this->reset();

		$this->table = $table;
		$this->type = 'UPDATE';
		
		return $this;
	}
	
	/**
	 * DELETE
	 */
	public function delete( string $table )
	{
		$this->reset();

		$this->table = $table;
		$this->type = 'DELETE';
		
		return $this;
	}
	
	/**
	 * Set columns for SELECT
	 */
	public function cols( ...$columns )
	{
		foreach ( $columns as $col )
		{
			if ( '*' !== $col )
			{
				$this->check_column( $col );
			}
		}
		
		$this->columns = $columns;
		return $this;
	}
	
	/**
	 * Set data for INSERT/UPDATE
	 */
	public function data( array $data )
	{
		// Check for tenant_id in data - should never be passed
		if ( isset( $data['tenant_id'] ) )
		{
			$this->check_value( 'tenant_id', $data['tenant_id'] );
			unset( $data['tenant_id'] );
		}
		
		foreach ( $data as $col => $val )
		{
			$this->check_column( $col );
			$this->check_value( $col, $val );
		}
		
		$this->data = $data;
		return $this;
	}
	
	/**
	 * Set current column for where conditions
	 */
	public function where( string $column )
	{
		$this->check_column( $column );
		$this->current_column = $column;
		return $this;
	}
	
	/**
	 * Comparison: equals
	 */
	public function eq( $val )
	{
		$this->where[] = "{$this->current_column} = ?";
		$this->params[] = $val;
		return $this;
	}
	
	/**
	 * Comparison: not equals
	 */
	public function neq( $val )
	{
		$this->where[] = "{$this->current_column} != ?";
		$this->params[] = $val;
		return $this;
	}
	
	/**
	 * Comparison: greater than
	 */
	public function gt( $val )
	{
		$this->where[] = "{$this->current_column} > ?";
		$this->params[] = $val;
		return $this;
	}
	
	/**
	 * Comparison: greater than or equal
	 */
	public function gte( $val )
	{
		$this->where[] = "{$this->current_column} >= ?";
		$this->params[] = $val;
		return $this;
	}
	
	/**
	 * Comparison: less than
	 */
	public function lt( $val )
	{
		$this->where[] = "{$this->current_column} < ?";
		$this->params[] = $val;
		return $this;
	}
	
	/**
	 * Comparison: less than or equal
	 */
	public function lte( $val )
	{
		$this->where[] = "{$this->current_column} <= ?";
		$this->params[] = $val;
		return $this;
	}
	
	/**
	 * Comparison: LIKE
	 */
	public function like( $val )
	{
		$this->where[] = "{$this->current_column} LIKE ?";
		$this->params[] = $val;
		return $this;
	}
	
	/**
	 * Comparison: NOT LIKE
	 */
	public function not_like( $val )
	{
		$this->where[] = "{$this->current_column} NOT LIKE ?";
		$this->params[] = $val;
		return $this;
	}
	
	/**
	 * Comparison: IN
	 */
	public function in( array $vals )
	{
		$placeholders = array_fill( 0, count( $vals ), '?' );
		$this->where[] = "{$this->current_column} IN (" . implode( ', ', $placeholders ) . ")";
		$this->params = array_merge( $this->params, $vals );
		return $this;
	}
	
	/**
	 * Comparison: NOT IN
	 */
	public function not_in( array $vals )
	{
		$placeholders = array_fill( 0, count( $vals ), '?' );
		$this->where[] = "{$this->current_column} NOT IN (" . implode( ', ', $placeholders ) . ")";
		$this->params = array_merge( $this->params, $vals );
		return $this;
	}
	
	/**
	 * Comparison: BETWEEN
	 */
	public function between( $min, $max )
	{
		$this->where[] = "{$this->current_column} BETWEEN ? AND ?";
		$this->params[] = $min;
		$this->params[] = $max;
		return $this;
	}
	
	/**
	 * Comparison: IS NULL
	 */
	public function is_null()
	{
		$this->where[] = "{$this->current_column} IS NULL";
		return $this;
	}
	
	/**
	 * Comparison: IS NOT NULL
	 */
	public function not_null()
	{
		$this->where[] = "{$this->current_column} IS NOT NULL";
		return $this;
	}
	
	/**
	 * ORDER BY
	 */
	public function order( string $column, string $direction = 'ASC' )
	{
		$this->check_column( $column );
		$this->order[] = "{$column} " . strtoupper( $direction );
		return $this;
	}
	
	/**
	 * GROUP BY
	 */
	public function group( string ...$columns )
	{
		foreach ( $columns as $col )
		{
			$this->check_column( $col );
		}
		$this->group = array_merge( $this->group, $columns );
		return $this;
	}
	
	/**
	 * LIMIT
	 */
	public function limit( int $limit )
	{
		$this->limit = $limit;
		return $this;
	}
	
	/**
	 * OFFSET
	 */
	public function offset( int $offset )
	{
		$this->offset = $offset;
		return $this;
	}
	
	/**
	 * Pagination helper
	 */
	public function page( int $page, int $per_page = 25 )
	{
		$this->limit = $per_page;
		$this->offset = ( $page - 1 ) * $per_page;
		return $this;
	}
	
	/**
	 * JOIN
	 */
	public function join( string $table, string $left, string $right, string $type = 'INNER' )
	{
		$this->join[] = strtoupper( $type ) . " JOIN {$table} ON {$left} = {$right}";
		return $this;
	}
	
	/**
	 * LEFT JOIN
	 */
	public function left_join( string $table, string $left, string $right )
	{
		return $this->join( $table, $left, $right, 'LEFT' );
	}
	
	/**
	 * RIGHT JOIN
	 */
	public function right_join( string $table, string $left, string $right )
	{
		return $this->join( $table, $left, $right, 'RIGHT' );
	}
	
	/**
	 * Get query type
	 */
	public function get_type()
	{
		return $this->type;
	}
	
	/**
	 * Get table
	 */
	public function get_table()
	{
		return $this->table;
	}
	
	/**
	 * Get data (for INSERT/UPDATE)
	 */
	public function get_data()
	{
		return $this->data;
	}
	
	/**
	 * Get params
	 */
	public function get_params()
	{
		return $this->params;
	}
	
	/**
	 * Get where clauses
	 */
	public function get_where()
	{
		return $this->where;
	}
	
	/**
	 * Output: SQL string
	 */
	public function to_sql()
	{
		switch ( $this->type )
		{
			case 'SELECT':
				return $this->build_select();
			case 'INSERT':
				return $this->build_insert();
			case 'UPDATE':
				return $this->build_update();
			case 'DELETE':
				return $this->build_delete();
			default:
				return FALSE;
		}
	}
	
	/**
	 * Output: Count query SQL
	 */
	public function to_count()
	{
		if ( 'SELECT' !== $this->type )
		{
			return FALSE;
		}
		
		$sql = "SELECT COUNT(*) as count FROM {$this->table}";
		
		if ( !empty( $this->join ) )
		{
			$sql .= " " . implode( ' ', $this->join );
		}
		
		// Tenant scoping
		$where = $this->where;
		$where[] = "tenant_id = ?";
		
		if ( !empty( $where ) )
		{
			$sql .= " WHERE " . implode( ' AND ', $where );
		}
		
		if ( !empty( $this->group ) )
		{
			$sql .= " GROUP BY " . implode( ', ', $this->group );
		}
		
		return $sql;
	}
	
	/**
	 * Output: Query as array (for debugging/logging)
	 */
	public function to_array()
	{
		return [
			'type' => $this->type,
			'table' => $this->table,
			'columns' => $this->columns,
			'data' => $this->data,
			'where' => $this->where,
			'params' => $this->params,
			'order' => $this->order,
			'group' => $this->group,
			'join' => $this->join,
			'limit' => $this->limit,
			'offset' => $this->offset,
			'errors' => $this->errors,
			'alerts' => $this->alerts
		];
	}
	
	/**
	 * Build SELECT SQL
	 */
	private function build_select()
	{
		$cols = implode( ', ', $this->columns );
		$sql = "SELECT {$cols} FROM {$this->table}";
		
		if ( !empty( $this->join ) )
		{
			$sql .= " " . implode( ' ', $this->join );
		}
		
		// Tenant scoping
		$where = $this->where;
		$where[] = "{$this->table}.tenant_id = ?";
		
		$sql .= " WHERE " . implode( ' AND ', $where );
		
		if ( !empty( $this->group ) )
		{
			$sql .= " GROUP BY " . implode( ', ', $this->group );
		}
		
		if ( !empty( $this->order ) )
		{
			$sql .= " ORDER BY " . implode( ', ', $this->order );
		}
		
		if ( null !== $this->limit )
		{
			$sql .= " LIMIT {$this->limit}";
		}
		
		if ( null !== $this->offset )
		{
			$sql .= " OFFSET {$this->offset}";
		}
		
		return $sql;
	}
	
	/**
	 * Build INSERT SQL
	 */
	private function build_insert()
	{
		// Add tenant_id to data
		$data = $this->data;
		$data['tenant_id'] = $this->tenant_id;
		
		$cols = array_keys( $data );
		$placeholders = array_fill( 0, count( $cols ), '?' );
		
		$sql = "INSERT INTO {$this->table} (" . implode( ', ', $cols ) . ") VALUES (" . implode( ', ', $placeholders ) . ")";
		
		return $sql;
	}
	
	/**
	 * Build UPDATE SQL
	 */
	private function build_update()
	{
		$set_parts = [];
		foreach ( array_keys( $this->data ) as $col )
		{
			$set_parts[] = "{$col} = ?";
		}
		
		$sql = "UPDATE {$this->table} SET " . implode( ', ', $set_parts );
		
		// Tenant scoping
		$where = $this->where;
		$where[] = "tenant_id = ?";
		
		$sql .= " WHERE " . implode( ' AND ', $where );
		
		return $sql;
	}
	
	/**
	 * Build DELETE SQL
	 */
	private function build_delete()
	{
		$sql = "DELETE FROM {$this->table}";
		
		// Tenant scoping
		$where = $this->where;
		$where[] = "tenant_id = ?";
		
		$sql .= " WHERE " . implode( ' AND ', $where );
		
		return $sql;
	}
	
	/**
	 * Get all params including tenant_id for execution
	 */
	public function get_exec_params()
	{
		$params = $this->params;
		
		switch ( $this->type )
		{
			case 'SELECT':
			case 'DELETE':
				// tenant_id added to WHERE
				$params[] = $this->tenant_id;
				break;
				
			case 'INSERT':
				// tenant_id added to data
				$params = array_values( $this->data );
				$params[] = $this->tenant_id;
				break;
				
			case 'UPDATE':
				// data params, then where params, then tenant_id
				$params = array_merge( array_values( $this->data ), $this->params );
				$params[] = $this->tenant_id;
				break;
		}
		
		return $params;
	}
}