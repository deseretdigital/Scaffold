<?php

abstract class DDM_Scaffold_Abstract {
	
/*===============================
** Properties
**===============================*/

	protected $config = array();
	protected $databases = array();
	protected $db;
	protected $projectRoot;
	protected $paths = array();
	protected $namespaces = array();
	protected $tables = array();
	
/*=================================
** Constructor and Related Methods
**=================================*/

	/**
	 * Constructor for Scaffold
	 *
	 * @param string $projectRoot
	 * @param array $config OPTIONAL
	 * @return void
	 */
	public function __construct($projectRoot, $config = array()) {
		$this->projectRoot = $projectRoot;
		$this->classNameFilter = new Zend_Filter_Word_UnderscoreToCamelCase();
		
		if(!array_key_exists('databases', $config)) {
			$config['databases'] = null;
		}
		
		if(!array_key_exists('paths', $config)) {
			$config['paths'] = array();
		}
		
		$this->initConfig();
		$this->initDatabases($config['databases']);
		$this->initPaths($config['paths']);
		$this->initDirectories();
		$this->initGeneral($config);
	}
	
	/**
	 * Empty function to allow for an easily place to add custom logic to __construct without having to overwrite it
	 *
	 * @param array $config
	 * @return void
	 */
	protected function initGeneral($config) {
	
	}
	
	/**
	 * Loads up the application.ini config
	 *
	 * @return void
	 */
	protected function initConfig() {
		$application = new Zend_Application(
    		APPLICATION_ENV,
    		APPLICATION_PATH . '/configs/application.ini'
		);
		$this->config = $application->getOptions();
	}
	
	/**
	 * Inits the database connections
	 *
	 * @params array|string|null $databases
	 * @return void
	 */
	protected function initDatabases($databases) {
		$dbParams = $this->config['resources']['db']['params'];
		$defaultDb = $dbParams['dbname'];
		$dbParams['dbname'] = 'information_schema';
		$this->db = Zend_Db::factory($this->config['resources']['db']['adapter'], $dbParams);
		
		if($databases === null && $defaultDb != '') {
			$databases = $defaultDb;
		}
		
		if(!is_array($databases)) {
			$databases = array($databases);
		}
		$this->databases = $databases;
	}
	
	/**
	 * Inits the paths used in generating classes
	 *
	 * @param array $defaults
	 * @return void
	 */
	protected function initPaths($paths) {
		$this->paths = $paths;
		$this->createDefaultPaths(array(
			'library' => 'library/',
			'generated' => 'Generated/',
			'application' => 'application/',
			'base' => 'Base/',
			'modules' => '',
		));
	}
	
	/**
	 * Create default paths if they have not been set by the user
	 *
	 * @param array $defaults
	 * @return void
	 */
	protected function createDefaultPaths($defaults) {
		foreach($defaults as $key => $path) {
			if(!array_key_exists($key, $this->paths)) {
				$this->paths[$key] = $path;
			}
		}
	}
	
	/**
	 * Sets up the directories needed
	 *
	 * @return void
	 */
	protected function initDirectories() {
		$this->makeDirectory($this->paths['library']);
		$this->makeDirectory($this->paths['library'] . $this->paths['generated']);
		$this->makeDirectory($this->paths['application']);
		$this->makeDirectory($this->paths['application'] . $this->paths['modules']);
	}
	
	/**
	 * Checks to see if a directory exists. If not, the directory is created
	 *
	 * @param string $path
	 * @param int $chmod optional
	 * @return void
	 */
	protected function makeDirectory($path, $chmod = 0775) {
		if(!is_dir($this->projectRoot . $path)) {
			$this->output('Making directory ' . $this->projectRoot . $path);
			mkdir($this->projectRoot . $path, $chmod);
		}
	}
	
/*===============================
** Main Generator
**===============================*/
	
	/**
	 * Generate acts as a helper method to call all other generate methods
	 *
	 * @return void
	 */
	abstract public function generate();
	
/*===============================
** Utility and Helper Functions
**===============================*/

	/**
	 * Outputs a message
	 *
	 * @param string $msg
	 * @param string|boolean $linebreaks optional
	 */
	protected function output($msg, $linebreaks = '<br />') {
		static $echo_date = true;
		if($echo_date) {
			echo date('d M Y H:i:s') . ' :: ';
		}
		echo $msg;
		if($linebreaks) {
			echo $linebreaks;
		}
		$echo_date = (bool) $linebreaks;
	}

	/**
	 * Convert a table name into something we can use for a class or file name
	 *
	 * @param string $name
	 * @param boolean $upperCaseFirst
	 * @return string
	 */
	protected function makeClassName( $name, $upperCaseFirst = true ) {
        $newName = $this->classNameFilter->filter($name);
		
		if(!$upperCaseFirst) {
			$newName = lcfirst($newName);
		}
		
		return $newName;
	}
	
	/**
	 * Converts a file name to a class name
	 *
	 * @param string $filename
	 * @param string $remove optional
	 * @return string $classname
	 */
	protected function convertFileNameToClassName($filename, $remove = '') {
		$path_parts = pathinfo($filename);
		$classname = $path_parts['dirname'] . '/' . $path_parts['filename'];
		$classname = str_replace($this->projectRoot, '', $classname);
		$classname = str_replace($remove, '', $classname);
		$classname = str_replace('/', '_', $classname);
		return $classname;
	}

	/**
	 * Make a name into a string we can use for the namespace
	 * The Result will be unique to this runtime
	 *
	 * @param string $name
	 * @param int $lettersToUse
	 * @return string
	 */
	protected function makeNamespace( $name, $lettersToUse = 1 ) {
		if( isset($this->namespaces[$name]) ) {
			return $this->namespaces[$name];
		}

		$parts = explode('_', $name );
		$newWord = '';
		
		foreach($parts as $p) {
			$newWord .= substr($p, 0, $lettersToUse );
		}
		$newWord = strtoupper($newWord);
		if( !in_array($newWord, $this->namespaces) ) {
			$this->namespaces[$name] = $newWord;
			return $newWord;
		} else {
			if( strlen($name) == $lettersToUse ) {
				$this->output('Unable to determine a namespace name for '.$name);
				exit;
			}
			return $this->makeNamespace($name, $lettersToUse++);
		}

	}
	
	/**
	 * Sets a Namespace for a specified string
	 *
	 * @param string $name
	 * @param string $namespace
	 */
	public function setNamespace($name, $namespace) {
		$this->namespaces[$name] = $namespace;
	}

	/**
	 * Write the contents to the file
	 *
	 * @param string $name
	 * @param string $contents
	 * @param string $type
	 * @return int
	 */
	protected function writeFile( $name, $contents, $type='php' ) {
		if( $type == 'php' && strpos($contents, '<?php') === false ) {
			$contents = "<?php\n\n" . $contents;
		}
		$res = file_put_contents( $name, $contents );
		if( $res ) {
			@chmod( $name, 0755);
		}
		return $res;
	}
	
	/**
	 * Converts a value to a php code string
	 *
	 * @param mixed $value
	 * @return string
	 */
	protected function convertToPhpCodeString($value) {
		$defaultValue = new Zend_CodeGenerator_Php_Property_DefaultValue(array('value' => $value));
		$code = $defaultValue->generate();
		// Remove trailing ;
		if(substr($code, -1, 1) == ';') {
			$code = substr($code, 0, -1);
		}
		return $code;
	}
	
/*===============================
** Database Functions
**===============================*/

	/**
	 * Get an array of tables in this database
	 * Optionally by default return extra information about those tables
	 *
	 * @param string $db
	 * @param boolean $extras optional
	 * @return array
	 */
	protected function getTables($database, $extras = true) {
		$keyName = $database . (($extras) ? 'Extras' : '');
		if(array_key_exists($keyName, $this->tables)) {
			return $this->tables[$keyName];
		}
	
		$sql = "SELECT *
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = '$database'";

		$tables = $this->db->fetchAll( $sql );
		
		if($extras) {	
			$ns = $this->makeNamespace($database);
			
			foreach($tables as &$table) {
				$table['namespace'] = $ns;
				$table['classNamePartial'] = $this->makeClassName($table['TABLE_NAME']);
				$table['COLUMNS'] = $this->getColumns($database, $table['TABLE_NAME']);
				$table['KEYS'] = $this->getKeys($database, $table['TABLE_NAME']);
				
				// this generates a lot of stuff that we already have but in a format that Zend_Db_Table wants
				$metadata = $this->db->describeTable($table['TABLE_NAME'], $database);
				if(count($table['KEYS'])) {
					foreach($table['KEYS'] as $key) {
						if(array_key_exists($key['COLUMN_NAME'], $metadata)) {
							$metadata[$key['COLUMN_NAME']]['REFERENCED_TABLE_NAME'] = $key['REFERENCED_TABLE_NAME'];
						}
					}
				}
				$table['zend_describe_table'] = $metadata;
				
				$primary_keys = array();
				foreach($metadata as $column) {
					if(array_key_exists('PRIMARY', $column) && $column['PRIMARY'] == 1) {
						$primary_keys[$column['PRIMARY_POSITION']] = $column['COLUMN_NAME'];
					}
				}
				$table['PRIMARY_COLUMNS'] = $primary_keys;
			}
			unset($table); // nuke the & from above
		}
		
		$this->tables[$keyName] = $tables;
		return $tables;
	}

	/**
	 * Get columns in a table
	 *
	 * @param string $db
	 * @param string $table
	 * @return array
	 */
	protected function getColumns( $db, $table ) {
		$sql = "SELECT *
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = '$db'
			AND TABLE_NAME = '$table'";

		$cols = $this->db->fetchAll( $sql );

		// get values for enums
		foreach( $cols as &$c ) {
			$this->parseColumnComments($c);
			if( $c['DATA_TYPE'] == 'enum' ) {
				$this->parseEnumValues( $c );
			}

		}

		return $cols;
	}
	
	/**
	 * Parse the column comments out
	 *
	 * @param array $column
	 */
	protected function parseColumnComments(&$column) {
		$comments = array();
		if(!empty($column['COLUMN_COMMENT'])) {
            $comments = preg_split('/\s/',$column['COLUMN_COMMENT']);
		}
		$column['COMMENTS'] = $comments;
	}
	
	/**
	 * Checks to see if a column has the requested comment
	 *
	 * @param string $comment
	 * @param array $column
	 * @return boolean
	 */
	protected function columnHasComment($comment, $column) {
		return in_array($comment, $column['COMMENTS']);
	}

	/**
	 * Parse enum info
	 *
	 * @param array $c
	 */
	protected function parseEnumValues( &$c ) {
		$end = strlen( $c['COLUMN_TYPE'] );
		$list = substr( $c['COLUMN_TYPE'], 5, $end - 6 );
		$parts = explode(',', $list);
		$list = array();
		foreach($parts as $p) {
			$list[] = str_replace('\'', '', $p);
		}
		$c['VALUES'] = $list;
	}

	/**
	 * Get the keys for a table
	 *
	 * @param string $db
	 * @param string $table
	 * @return array
	 */
	protected function getKeys( $db, $table ) {
		$sql = "SELECT *
			FROM information_schema.KEY_COLUMN_USAGE
			WHERE TABLE_SCHEMA = '$db'
			AND TABLE_NAME = '$table'
			AND REFERENCED_COLUMN_NAME IS NOT NULL
			ORDER BY COLUMN_NAME, ORDINAL_POSITION";

		$keys = $this->db->fetchAll( $sql );
		return $keys;
	}
	
	/**
	 * Returns all the triggers for the specified database
	 *
	 * @param string $database
	 * @param boolean $extras
	 * @return array
	 */
	protected function getTriggers($database, $extras = true) {
		$sql = '
			SELECT
				*
			FROM
				information_schema.TRIGGERS
			WHERE
				TRIGGER_SCHEMA = "'.$database.'"
		';
		$triggers = $this->db->fetchAll($sql);
		
		if($extras) {	
			$ns = $this->makeNamespace($database);
			
			foreach($triggers as &$trigger) {
				$trigger['namespace'] = $ns;
				$trigger['classNamePartial'] = $this->makeClassName($trigger['TRIGGER_NAME']);
			}
			unset($trigger); // nuke the & from above
		}
		
		return $triggers;
	}
	
/*===============================
** Column Functions
**===============================*/

	/**
	 * Checks to see if a column is numeric
	 *
	 * @param array $column
	 * @return boolean
	 */
	protected function isColumnNumeric($column) {
		return in_array($column['DATA_TYPE'], array('int', 'smallint', 'tinyint', 'mediumint', 'bigint', 'float', 'decimal', 'double'));
	}

	/**
	 * Checks to see if a column is date related
	 *
	 * @param array $column
	 * @return boolean
	 */
	protected function isColumnTimeRelated($column) {
		return in_array($column['DATA_TYPE'], array('datetime', 'date', 'time', 'timestamp', 'year'));
	}
	
	/**
	 * Returns the MySQL date format for a column
	 *
	 * @param array $column
	 * @return string
	 */
	protected function getDateFormatForColumn($column) {
		if(in_array($column['DATA_TYPE'], array('datetime', 'timestamp'))) {
			return 'Y-m-d H:i:s';
		} else if($column['DATA_TYPE'] == 'date') {
			return 'Y-m-d';
		} else if($column['DATA_TYPE'] == 'time') {
			return 'H:i:s';
		} else if($column['DATA_TYPE'] == 'year') {
			return 'Y';
		}
		
		return 'Y-m-d H:i:s';
	}
	
	/**
	 * Returns the label for a column
	 *
	 * @param array $column
	 * @return string $label
	 */
	protected function getColumnLabel($column) {
		$label = $column['COLUMN_NAME'];
    	$label = str_replace('_', ' ', $label);
    	$label = ucfirst($label);
    	return $label;
	}
	
	/**
	 * Returns the input name for a column
	 *
	 * @param array $table
	 * @param array $column
	 * @return string
	 */
	protected function getColumnInputName($table, $column) {
		return lcfirst($table['classNamePartial']) . '[' . $column['COLUMN_NAME'] . ']';
	}
	
	/**
	 * Returns the maxLength to use for a column
	 *
	 * @param array $column
	 * @return string
	 */
	protected function getColumnMaxLength($column) {
		$maxlength = null;
		
		if(!empty($column['CHARACTER_MAXIMUM_LENGTH'])) {
			$maxlength = $column['CHARACTER_MAXIMUM_LENGTH'];
		} else if(!empty($column['NUMERIC_PRECISION'])) {
			$maxlength = $column['NUMERIC_PRECISION'];
			// Signed fields should allow for a negative symbol
			if(strpos($column['COLUMN_TYPE'], 'unsigned') === false) {
				++$maxlength;
			}
			// Numbers that can have decimals need to allow for a decimal symbol
			if(in_array($column['DATA_TYPE'], array('float', 'double', 'decimal'))) {
				++$maxlength;
			}
		} else if($this->isColumnTimeRelated($column)) {
			// Dates can be written in so many formats, just allow for 50 characters. 30 September 2011 HH:MM:SS aa
			$maxlength = 50;
			if($column['DATA_TYPE'] == 'year') {
				$maxlength = 4;
			} else if($column['DATA_TYPE'] == 'time') {
				// Allow for HH:MM:SS aa
				$maxlength = 11;
			}
		}
		
		return $maxlength;
	}
	
	/**
	 * Retursn the form element to be used with the column
	 *
	 * @param array $column
	 * @return string
	 */
	protected function getColumnFormElement($column) {
		$element = 'Zend_Form_Element_Text';
		
		if(strpos($column['EXTRA'], 'auto_increment') !== false) {
			$element = 'Zend_Form_Element_Hidden';
		} else if($this->columnHasComment('BOOLEAN', $column)) {
			$element = 'Zend_Form_Element_Checkbox';
		} else if(in_array($column['DATA_TYPE'], array('text', 'tinytext', 'mediumtext', 'longtext'))) {
			$element = 'Zend_Form_Element_Textarea';
		} else if($column['DATA_TYPE'] == 'varchar' && $column['CHARACTER_MAXIMUM_LENGTH'] <= 50) {
			$element = 'Zend_Form_Element_Textarea';
		} else if($column['DATA_TYPE'] == 'enum') {
			$element = 'Zend_Form_Element_Select';
		} else if($column['DATA_TYPE'] == 'date') {
			$element = 'ZendX_JQuery_Form_Element_DatePicker';
		} else if($column['DATA_TYPE'] == 'datetime') {
			$element = 'ZendX_JQuery_Form_Element_DateTimePicker';
		} else if($column['DATA_TYPE'] == 'time') {
			$element = 'ZendX_JQuery_Form_Element_TimePicker';
		} else if($column['DATA_TYPE'] == 'timestamp') {
			$element = 'Zend_Form_Element_Hidden';
		}
		
		return $element;
	}
	
	/**
	 * Returns the validators to be used with the column
	 *
	 * @param array $column
	 * @return array $validators
	 */
	protected function getColumnValidators($column) {
		$validators = array();
		
		return $validators;
	}
	
	/**
	 * Returns the filters to be used with the column
	 *
	 * @param array $column
	 * @return array $filters
	 */
	protected function getColumnFilters($column) {
		$filters = array();
		$filters[] = 'StringTrim';
		
		return $filters;
	}

	/**
	 * Returns the default value that should be used for a given column
	 *
	 * @param array $column
	 * @return mixed
	 */
	protected function getDefaultColumnValue($column) {
		$default_value = $column['COLUMN_DEFAULT'];
		if($this->columnHasComment('CURRENT_TIMESTAMP', $column)) {
			$default_value = 'CURRENT_TIMESTAMP';
		}
		
		if($default_value === null) {
			if($column['IS_NULLABLE'] != 'YES' && strpos($column['EXTRA'], 'auto_increment') === false) {
				if( $this->isColumnNumeric($column) ) {
					$default_value = 0;
				} else {
					$default_value = '';
				}
			}
		}
		
		return $default_value;
	}
	
	/**
	 * Returns the var type for a mysql column array
	 *
	 * @param array $column
	 * @return string
	 */
	protected function getColumnVarType($column) {
		$type = $this->columnTypeToVarType($column['DATA_TYPE']);
		if($this->columnHasComment('BOOLEAN', $column)) {
			$type = 'boolean';
		}
		return $type;
	}

	/**
	 * Map mysql column types to php var types
	 *
	 * @param string $mysqlColumnType
	 * @return string
	 */
	protected function columnTypeToVarType($mysqlColumnType) {
		$phpType = 'string';
		switch ($mysqlColumnType) {
			case 'int':
			case 'tinyint':
			case 'smallint':
			case 'mediumint':
			case 'bigint':
				$phpType = 'int';
				break;
			case 'float':
			case 'decimal':
			case 'double':
				$phpType = 'float';
				break;
			default:
				break;
		}
		return $phpType;
	}
}