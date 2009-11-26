<?php

define('FLOAT_LENGTH', 11);
define('INT_LENGTH', 11);
define('VARCHAR_LENGTH', 255);

class FlexDB{
	
	public static $db;
	public static $insert_id;
	public static $result;
	public static $table_exists = array();
	public static $columns = array();
	
	/**
	 * Checks if there's an connection available, if false, connection will be made
	 * 
	 * @param string $connect // specify an database other than the default	
	**/
	public static function instance($connect = NULL){
		
		if($connect !== NULL){
			self::connect($connect);			
		}elseif(self::$db === NULL){
			self::connect();	
		}	
		
	}
	
	/**
	 * Connects to a database
	 * 
	 * @param string $database // uses the database config if specified
	**/
	public static function connect($database = NULL){
		if($database === NULL){
			self::$db = Database::instance();
		}else{
			self::$db = Database::instance($database);
		}
	}
	
	
	/**
	 * Inserts a new row in the database
	 * -> if the table doesn't exist yet, it will be created..
	 * -> if (certain) fields don't exist yet, they will be created..
	 *
	 * @param string $table_name // database table name
	 * @param array $data // array('field1' => 'value', ...)
	 * @param boolean $restrict // set to true to enable strict database inserts, the fields from your data array will be filtered against the existing fields in the database
	 * @param string $connect // specify an database other than the default												
	 * @return boolean // true on success
	**/
	public static function insert($table_name, $data, $restrict = false, $connect = NULL){
		
		self::instance($connect);
		
		if($restrict === false){
		
			if( self::exists($table_name) === true){
								
				$fields = self::fields($table_name);
				
				$diff = array_diff_key($data, $fields);
				
				if(count($diff) > 0){
					
					self::alter($table_name, $diff);
					
				}
				
				$data = self::json($data);
				
				if( ($query = self::$db->insert($table_name, $data)) ){
					
					self::$insert_id = $query->insert_id();
					
					return true;
				
				}
				
			}else{
				
				self::create($table_name, $data);
				
				$data = self::json($data);
				
				if( ($query = self::$db->insert($table_name, $data)) ) {
					
					self::$insert_id = $query->insert_id();
					
					return true;
					
				}
				
			}
			
		}else{
						
			$data = self::json($data);			
								
			$insert_data = self::match($table_name, $data);
								
			if( ($query = self::$db->insert($table_name, $insert_data)) ){
				
				self::$insert_id = $query->insert_id();
				
				return true;
				
			}
		}	
	}
	
	/**
	 * Updates a row in the database
	 * -> if (certain) fields don't exist yet, they will be created..
	 *
	 * @param string $table_name // database table name
	 * @param array $data // array('field1' => 'value', ...)
	 * @param array $where // array with where clausules e.g. array('id' => 4)
	 * @param boolean $restrict // set to true to enable strict database updates, the fields from your data array will be filtered against the existing fields in the database
	 * @param string $connect // specify an database other than the default												
	 * @return boolean // true on success
	**/	
	public static function update($table_name, $data, $where, $restrict = false, $connect = NULL){
				
		self::instance($connect);
		
		if($restrict === false){

			if( self::exists($table_name) === true){

				$fields = self::fields($table_name);

				$diff = array_diff_key($data, $fields);

				if(count($diff) > 0){

					self::alter($table_name, $diff);

				}
				
				$data = self::json($data);

				if( self::$db->update($table_name, $data, $where) ){

					return true;

				}

			}

		}else{

			$data = self::json($data);
			
			$update_data = self::match($table_name, $data);

			if( self::$db->update($table_name, $update_data, $where) ){

				return true;

			}
		}	
			
	}
	

	/**
	 * Models a new database table exactly how you want it.. 
	 *
	 * @param string $table_name // name of the new table 
	 * @param array $fields // nested array with following keys: 
	 *												- 'field' => (string) field name (required!)
	 *												- 'type' => (string) MySQL data type (e.g. INT, VARCHAR etc.)
	 *												- 'length' => (int) field length
	 * 												- 'attribute' => (string) attributes of field (e.g. signed, unsigned etc.)
	 *												- 'default' => (mixed) default value of field
	 *												- 'null' => (bool) true if you want default value to be NULL
	 *												- 'extra' => (string) extra attributes of string (e.g. auto_increment etc.)
	 * @param string $engine // MySQL storage engine, defaults to: MyIsam
	 * @param string $charset // table collation
	 * @param string $connect // specify an database other than the default												
	 * @return boolean // true on success								
	**/
	public static function model($table_name, $fields, $engine = 'MyISAM', $charset ='utf8', $connect = NULL){
		
		self::instance($connect);
		
		$tbl = "CREATE TABLE IF NOT EXISTS `{$table_name}`";
		$tbl .= "(";
		$tbl .= "`id` int(11) unsigned NOT NULL auto_increment, ";
		
		foreach ($fields as $field ){

			if( isset($field['field']) && $field['field'] != 'id' ){

				$tbl .= "`".$field['field']."` ";

				if(isset($field['type'])){
	
					$tbl .= $field['type']." ";
			
					if(isset($field['length'])){
						$tbl .= "(".$field['length'].") ";
					}
					
					if(isset($field['attribute'])){
						$tbl .= $field['attribute']." ";
					}
					
					if(isset($field['default'])){
						
						$tbl .= "default ".$field['default']." ";
						
					}
					
					if(isset($field['null']) && !isset($field['default'])){
						
						if($field['null'] === false){
							
							$tbl .= "default NOT NULL ";
							
						}else{
							
							$tbl .= "default NULL ";
							
						}
					}
					
					if(isset($field['extra'])){
						$tbl .= $field['extra']." ";
					}
			
			
				}
				
				$tbl .= ", ";
			}
		}
		
		$tbl .= "PRIMARY KEY (`id`)";		
		$tbl .= ") ";
		
		if(isset($engine)){
			$tbl .= "ENGINE=".$engine." ";
		}
		
		if(isset($charset)){
			$tbl .= "DEFAULT CHARSET=".$charset." ";
		}
		
		if( self::$db->query($tbl) ){
			
			return true;
			
		}
	}
	
	/**
	 * Returns array with fields and corresponding data types of the table
	 *
	 * @param mixed $table_name // database table name OR specify field_name with array('table_name' => 'field_name')
	 * @param string $connect // specify an database other than the default												
	 * @return array $fields 
	**/
	public static function show($table_name, $connect = NULL){
		
		self::instance($connect);
		
		if(is_array($table_name)){
			foreach ($table_name as $k=>$v){
				$table_name = $k;
				$field_name = $v;
			}
		}
			
		$q = "SHOW COLUMNS FROM `".$table_name."` ";
		
		if(isset($field_name)){
			$q .= "WHERE `Field` = '".$field_name."' ";	
		}
			
		
		$fields = self::$db->query($q)->result_array(false);
		
		return $fields;
		
	}
	
	/**
	 * Deletes a field from the table
	 *
 	 * @param string $field_name // field name you want to delete
	 * @param string $table_name // database table name
	 * @param string $connect // specify an database other than the default												
	 * @return boolean // true on success
	**/
	public static function del($field_name, $table_name, $connect = NULL){
	
		self::instance($connect);
	
		if( self::$db->query("ALTER TABLE `{$table_name}` DROP `{$field_name}`") ){
		
			return true;
		
		}
		
	}
	
	/**
	 * Drops a table
	 *
	 * @param string $table_name // database table name
	 * @param string $connect // specify an database other than the default	
	 * @return boolean // true on success
	**/
	public static function drop($table_name, $connect = NULL){
	
		self::instance($connect);
	
		if( self::$db->query("DROP TABLE `{$table_name}`") ){
			
			return true;
			
		}
		
	}
	
	/**
	 * Creates new fields in an existing table
	 *
	 * @param string $table_name // database table name
	 * @param array $array // array('field1' => 'doesnt matter', ...)
	 * @param string $connect // specify an database other than the default	
	 * @return boolean // true on success
	**/
	public static function alter($table_name, $array, $connect = NULL){
	
		self::instance($connect);
		
		foreach ($array as $key => $value){
			
			$datatype = self::setsqltype($value);
			self::$db->query("ALTER TABLE `{$table_name}` ADD `{$key}` {$datatype}");
			
		}
		
		if(isset(self::$columns[$table_name])){
			unset(self::$columns[$table_name]);
		}
		
		return true;
		
	}
	
	/**
	 * Creates a new table
	 *
	 * @param string $table_name // database table name
	 * @param array $data // array('field1' => 'value', ...)
	 * @param string $charset // charset of the table, default: utf8
	 * @param string $storage // storage engine of the table, default: MyIsam
	 * @param string $connect // specify an database other than the default	
	 * @return boolean // true on success
	**/
	public static function create($table_name, $data, $charset = 'utf8', $storage = 'MyIsam', $connect = NULL){
		
		self::instance($connect);
		
		foreach ($data as $name => $value){
			
			$field[$name] = self::setsqltype($value);
			
		}
		
		$tbl = "CREATE TABLE IF NOT EXISTS `{$table_name}`";
		$tbl .= "(";
		$tbl .= "`id` int(".INT_LENGTH.") unsigned NOT NULL auto_increment,";
		
			foreach ($field as $name => $type){
				
				$tbl .= "`{$name}` {$type},";
				
			}
		
		$tbl .= "PRIMARY KEY  (`id`)";
		$tbl .= ")";
		$tbl .= "ENGINE={$storage} ";  
		$tbl .= "DEFAULT CHARSET={$charset}";
		
		if( self::$db->query($tbl) ){
			
			return true;
			
		}
		
	}
	
	/**
	 * Checks if a table exists
	 *
	 * @param string $table_name // database table name
     * @param string $connect // specify an database other than the default	
	 * @return boolean // true if table exists
	**/
	public static function exists($table_name, $connect = NULL){
		
		self::instance($connect);
		
		if(!isset(self::$table_exists)){
			self::$table_exists = array();
		}
		
		if(in_array($table_name, self::$table_exists)){
			
			return true;
			
		}else{
			
			$query = self::$db->query("SHOW TABLES LIKE '{$table_name}'")->result_array(false);

			if( isset($query[0])){

				self::$table_exists[] = $table_name;

				return true;
			}
		}
		
	}
	
	/**
	 * Sets SQL data type for field value
	 *
	 * @param string $value // value that needs to be checked
	 * @return string // SQL datatype string
	**/
	public static function setsqltype($value, $null = true){
				
		if($null === true){
			
			$null = "NULL";
			
		}else{
			
			$null = "NOT NULL";
			
		}
		
		$type = gettype($value);
		
		if(is_numeric($value)){
			
			if( (float)$value != (int)$value ){
				
	            return "FLOAT(".FLOAT_LENGTH.") {$null}";
	
	        }else{
		
	            return "INT(".INT_LENGTH.") {$null}";
	
	        }
		
		}elseif(is_bool($value)){
	
			return "BOOL {$null}";
				
		}elseif(is_array($value) || is_object($value) ){

			return "TEXT {$null}";

		}elseif(is_string($value)){

			if(strlen($value) <= VARCHAR_LENGTH){

				return "VARCHAR(".VARCHAR_LENGTH.") {$null}";

			}else{

				return "TEXT {$null}";

			}
		
		}
		
	}
	
	
	/**
	 * Returns assoc array with only field names of table
	 *
	 * @param string $table_name // database table name
     * @param string $connect // specify an database other than the default	
	 * @return array $array // associative array with field names as key 
	**/	
	public static function fields($table_name, $connect = NULL){
		
		self::instance($connect);
		
		if(isset(self::$columns[$table_name])){
			
			return self::$columns[$table_name];
			
		}else{
		
			$query = self::$db->query("SHOW COLUMNS FROM `{$table_name}`")->result_array(false);
		
			foreach ($query as $k => $v){

				$array[$v['Field']] = $v['Field'];

			}
			
			self::$columns[$table_name] = $array;
			
			return $array;
		}
	}
	
	/**
	 * Filters a data array to match only existing table fields and remove those values who don't match
	 *
	 * @param string $table_name // database table name
	 * @param array $data // array('field1' => 'value', ...)
	 * @return array $array // filtered array with only existing field values
	**/
	public static function match($table_name, $data){

		$fields = self::fields($table_name);
		
		foreach ($fields as $key => $value){
			if(isset($data[$key])){
				$array[$key] = $data[$key];
			}
		}
		if(isset($array)){
			return $array;
		}
	} 
	
	/**
	 * Starts a new database transaction
	 *
	 * @param string $connect // specify an database other than the default	
	 * @return bool // true on success
	**/
	public static function start($connect = NULL){
		
		self::instance($connect);
		
		self::$db->query('START TRANSACTION');
		
		return true;
		
	}
	
	/**
	 * Commits the current database transaction
	 * @return bool // true on success
	**/	
	public static function commit(){
		
		if(self::$db === NULL){
			return false;
		}else{
			self::$db->query('COMMIT');
			return true;	
		}
		
	}

	/**
	 * Rollbacks the current database transaction
	 * @return bool // true on success
	**/	
	public static function rollback(){
		
		if(self::$db === NULL){
			return false;
		}else{
			self::$db->query('ROLLBACK');
			return true;	
		}
		
	}
	
	/**
	 * Converts nested arrays to json encoded strings
	 *
	 * @param array $data // array('field1' => 'value', ...)
	 * @return array $data // array with json encoded nested arrays 
	**/
	public static function json($data){
				
		foreach ($data as $k => &$v){
			
			if(is_array($v)){
				
				$v = json_encode($v);
				
			}
			
		}
		
		return $data;
		
	}
	
	
	/**
	 * Call 'fetchall' collector for common Kohana database query methods so you can use FlexDB to start all your database queries
	 * Examples: FlexDB::getwhere($table, $where); or FlexDB::select('*')->from('table')->where('id', 1)->get();
	 * REQUIRES: PHP 5.3.0 + (!)
	 *
	 * @return returns the result of Kohana Core Database methods
	**/
	
	public static function __callStatic($name, $args){

		self::instance();
		
		$this_funcs = array(
							'select'
							, 'from'
							, 'join'
							, 'where'
							, 'orwhere'
							, 'like'
							, 'orlike'
							, 'notlike'
							, 'ornotlike'
							, 'regex'
							, 'orregex'
							, 'notregex'
							, 'ornotregex'
							, 'groupby'
							, 'having'
							, 'orderby'
							, 'limit'
							, 'offset'
							, 'set'
							, 'in'
							, 'notin'
							, 'query'
							, 'get'
							, 'getwhere'
							, 'merge'
							, 'delete'
						);

		$result_funcs = array(			
							'query'
							, 'get'
							, 'getwhere'
							, 'merge'
							, 'delete');
							
		$result_mod_funcs = array(
							'single_value'
							, 'single_row'
							, 'success'
							, 'last_query'
							, 'count_records'
							, 'count_last_query'
						 );

		if(in_array($name, $this_funcs)){

			call_user_func_array(array(self::$db, $name), $args);
			
			if(in_array($name, $result_funcs)){
				return self::$db->result;
			}else{
				return self::$db;
			}

		}elseif(in_array($name, $result_mod_funcs)){

			return call_user_func_array(array(self::$db->result, $name), $args);
						
		}else{

			return false;
		}

	}
	
	/**
	 * Shortcut to retrieve a single row as an associative array from your query
	 *
	 * @param string $table table name
	 * @param array $where where condition (e.g. array('id' => 1 etc.))
	**/
	
	public function get_row($table, $where){
		
		self::instance();
		
		if(!is_array($where)){
			$where = array('id' => $where);
		}
		
		return self::getwhere($table, $where)->single_row();
	}
	
	/**
	 * Shortcut to retrieve all rows as an associative array from your query
	 *
	 * @param string $table table name
	 * @param array $where where condition (e.g. array('id' => 1 etc.))
	**/
	
	public function get_rows($table, $where){
		
		self::instance();
		
		if(!is_array($where)){
			$where = array('id' => $where);
		}
		
		return self::getwhere($table, $where)->as_array();
	}
	
	/**
	 * Shortcut to retrieve a single value from a field from your query
	 *
	 * @param string $table table name
	 * @param array $where where condition (e.g. array('id' => 1 etc.))
	 * @param string $fieldname field name
	**/
	
	public function get_value($table, $where, $fieldname){
		
		self::instance();
		
		if(!is_array($where)){
			$where = array('id' => $where);
		}
		
		return self::select($fieldname)->from($table)->where($where)->get()->single_value();
	}
	
	public function id_exists($table, $id){
		
		return self::row_exists($table, $id);

	}
	
	public function row_exists($table, $where){
		
		self::instance();
		
		if(!is_array($where)){
			$where = array('id' => $where);
		}
		
		if(self::select('id')->getwhere($table, $where)->success()){
			return true;
		}else{
			return false;
		}
	}	
	
}