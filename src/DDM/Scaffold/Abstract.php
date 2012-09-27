<?php

abstract class DDM_Scaffold_Abstract {

/*===============================
** Properties
**===============================*/

    protected $config = array();
    protected $databases = array();
    protected $database;
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
     *
     * @return void
     */
    public function __construct($projectRoot, $config = array()) {
        $this->projectRoot = $projectRoot;
        $this->classNameFilter = new DDM_Filter_Word_UnderscoreToCamelCase();

        if (!array_key_exists('databases', $config)) {
            $config['databases'] = null;
        }

        if (!array_key_exists('paths', $config)) {
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
     *
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
     *
     * @return void
     */
    protected function initDatabases($databases) {
        $databaseParams = $this->config['resources']['db']['params'];
        $defaultDatabase = $databaseParams['dbname'];
        $databaseParams['dbname'] = 'information_schema';
        $this->db = Zend_Db::factory($this->config['resources']['db']['adapter'], $databaseParams);

        if ($databases === null && $defaultDatabase != '') {
            $databases = $defaultDatabase;
        }

        if (!is_array($databases)) {
            $databases = array($databases);
        }
        $this->databases = $databases;
    }

    /**
     * Inits the paths used in generating classes
     *
     * @param array $defaults
     *
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
     *
     * @return void
     */
    protected function createDefaultPaths($defaults) {
        foreach ($defaults as $key => $path) {
            if (!array_key_exists($key, $this->paths)) {
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
     *
     * @return void
     */
    protected function makeDirectory($path, $chmod = 0775) {
        if (!is_dir($this->projectRoot . $path)) {
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
        static $echoDate = true;
        if ($echoDate) {
            echo date('d M Y H:i:s') . ' :: ';
        }
        echo $msg;
        if ($linebreaks) {
            echo $linebreaks;
        }
        $echoDate = (bool)$linebreaks;
    }

    /**
     * Convert a table name into something we can use for a class or file name
     *
     * @param string $name
     * @param boolean $upperCaseFirst
     *
     * @return string
     */
    protected function makeClassName($name, $upperCaseFirst = true) {
        $newName = $this->classNameFilter->filter($name);

        if (!$upperCaseFirst) {
            $newName = lcfirst($newName);
        }

        return $newName;
    }

    /**
     * Converts a file name to a class name
     *
     * @param string $filename
     * @param string $remove optional
     *
     * @return string $classname
     */
    protected function convertFileNameToClassName($filename, $remove = '') {
        $pathParts = pathinfo($filename);
        $classname = $pathParts['dirname'] . '/' . $pathParts['filename'];
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
     *
     * @return string
     */
    protected function makeNamespace($name, $lettersToUse = 1) {
        if (isset($this->namespaces[$name])) {
            return $this->namespaces[$name];
        }

        $parts = explode('_', $name);
        $newWord = '';

        foreach ($parts as $part) {
            $newWord .= substr($part, 0, $lettersToUse);
        }
        $newWord = strtoupper($newWord);
        if (!in_array($newWord, $this->namespaces)) {
            $this->namespaces[$name] = $newWord;
            return $newWord;
        } else {
            if (strlen($name) == $lettersToUse) {
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
    protected function writeFile($name, $contents, $type='php') {
        if ($type == 'php' && strpos($contents, '<?php') === false) {
            $contents = "<?php\n\n" . $contents;
        }
        $res = file_put_contents($name, $contents);
        if ($res) {
            @chmod($name, 0755);
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
        if (substr($code, -1, 1) == ';') {
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
     * @param string $database
     * @param boolean $extras optional
     *
     * @return array
     */
    protected function getTables($database, $extras = true) {
        $keyName = $database . (($extras) ? 'Extras' : '');
        if (array_key_exists($keyName, $this->tables)) {
            return $this->tables[$keyName];
        }

        $sql = "SELECT *
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = '$database'";

        $result = $this->db->fetchAll($sql);
        // Create an array based on table name for easier referencing
        $tables = array();
        foreach ($result as $row) {
            $tables[$row['TABLE_NAME']] = $row;
        }

        if ($extras) {
            $namespace = $this->makeNamespace($database);

            foreach ($tables as &$table) {
                $table['namespace'] = $namespace;
                $table['classNamePartial'] = $this->makeClassName($table['TABLE_NAME']);

                $table['COLUMNS'] = $this->getColumns($database, $table['TABLE_NAME']);

                $hasAutoIncrement = false;
                foreach ($table['COLUMNS'] as $column){
                    if ($this->isColumnAutoIncrement($column)) {
                        $hasAutoIncrement = true;
                        break;
                    }
                }
                $table['AUTO_INCREMENT'] = $hasAutoIncrement;

                $table['KEYS'] = $this->getKeys($database, $table['TABLE_NAME']);
                $table['INDEXES'] = $this->getIndexes($database, $table['TABLE_NAME']);

                $table['DEPENDENT_KEYS'] = array();

                // this generates a lot of stuff that we already have but in a format that Zend_Db_Table wants
                $metadata = $this->db->describeTable($table['TABLE_NAME'], $database);
                if (count($table['KEYS'])) {
                    foreach ($table['KEYS'] as $key) {
                        if (array_key_exists($key['COLUMN_NAME'], $metadata)) {
                            $metadata[$key['COLUMN_NAME']]['REFERENCED_TABLE_NAME'] = $key['REFERENCED_TABLE_NAME'];
                        }
                    }
                }
                $table['zend_describe_table'] = $metadata;

                $primaryKeys = array();
                foreach ($metadata as $column) {
                    if (array_key_exists('PRIMARY', $column) && $column['PRIMARY'] == 1) {
                        $primaryKeys[$column['PRIMARY_POSITION']] = $column['COLUMN_NAME'];
                    }
                }
                $table['PRIMARY_COLUMNS'] = $primaryKeys;
            }
            unset($table); // nuke the & from above

            //Find dependent keys
            $dependentKeys = array();
            foreach ($tables as $table) {
                foreach ($table['KEYS'] AS $index => $key) {
                    $relatedKeys = $table['KEYS'];
                    unset($relatedKeys[$index]);
                    $key['RELATED_KEYS'] = $relatedKeys;
                    if (!array_key_exists($key['REFERENCED_TABLE_NAME'], $dependentKeys)) {
                        $dependentKeys[$key['REFERENCED_TABLE_NAME']] = array();
                    }
                    $dependentKeys[$key['REFERENCED_TABLE_NAME']][] = $key;
                }
            }
            foreach ($dependentKeys as $table => $keys) {
                $tables[$table]['DEPENDENT_KEYS'] = $keys;
            }
            unset($table);  // nuke the & from above
        }

        $this->tables[$keyName] = $tables;
        return $tables;
    }

    /**
     * Get columns in a table
     *
     * @param string $database
     * @param string $table
     *
     * @return array
     */
    protected function getColumns($database, $table) {
        $sql = "SELECT *
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = '$database'
            AND TABLE_NAME = '$table'";

        $columns = $this->db->fetchAll($sql);

        // get values for enums
        foreach ($columns as &$column) {
            $this->parseColumnComments($column);
            if ($column['DATA_TYPE'] == 'enum') {
                $this->parseEnumValues($column);
            }

        }

        return $columns;
    }

    /**
     * Parse the column comments out
     *
     * @param array $column
     */
    protected function parseColumnComments(&$column) {
        $comments = array();
        if (!empty($column['COLUMN_COMMENT'])) {
            $comments = preg_split('/\s/',$column['COLUMN_COMMENT']);
        }
        $column['COMMENTS'] = $comments;
    }

    /**
     * Checks to see if a column has the requested comment
     *
     * @param string $comment
     * @param array $column
     *
     * @return boolean
     */
    protected function columnHasComment($comment, $column) {
        return in_array($comment, $column['COMMENTS']);
    }

    /**
     * Parse enum info
     *
     * @param array $column
     */
    protected function parseEnumValues(&$column) {
        $end = strlen($column['COLUMN_TYPE']);
        $list = substr($column['COLUMN_TYPE'], 5, $end - 6);
        $parts = explode(',', $list);
        $list = array();
        foreach ($parts as $part) {
            $list[] = str_replace('\'', '', $part);
        }
        $column['VALUES'] = $list;
    }

    /**
     * Get the keys for a table
     *
     * @param string $database
     * @param string $table
     *
     * @return array
     */
    protected function getKeys($database, $table) {
        $sql = "SELECT *
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = '$database'
            AND TABLE_NAME = '$table'
            AND REFERENCED_COLUMN_NAME IS NOT NULL
            ORDER BY COLUMN_NAME, ORDINAL_POSITION";

        $keys = $this->db->fetchAll($sql);
        return $keys;
    }

    /**
     * Get the indexes for a table
     *
     * @param string $database
     * @param string $table
     *
     * @return array
     */
    protected function getIndexes($database, $table) {
        $sql = "SELECT *
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = '$database'
            AND TABLE_NAME = '$table'
            AND COLUMN_KEY != ''
            ORDER BY COLUMN_NAME, ORDINAL_POSITION";

        $indexes = $this->db->fetchAll($sql);
        return $indexes;
    }

    /**
     * Returns all the triggers for the specified database
     *
     * @param string $database
     * @param boolean $extras
     *
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

        if ($extras) {
            $namespace = $this->makeNamespace($database);

            foreach ($triggers as &$trigger) {
                $trigger['namespace'] = $namespace;
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
     * Checks to see if a column does auto increments
     *
     * @param array $column
     *
     * @return boolean
     */
    protected function isColumnAutoIncrement($column) {
        return strpos($column['EXTRA'], 'auto_increment') !== false;
    }

    /**
     * Checks to see if a column is numeric
     *
     * @param array $column
     *
     * @return boolean
     */
    protected function isColumnNumeric($column) {
        return in_array($column['DATA_TYPE'], array('int', 'smallint', 'tinyint', 'mediumint', 'bigint', 'float', 'decimal', 'double'));
    }

    /**
     * Checks to see if a column is date related
     *
     * @param array $column
     *
     * @return boolean
     */
    protected function isColumnTimeRelated($column) {
        return in_array($column['DATA_TYPE'], array('datetime', 'date', 'time', 'timestamp', 'year'));
    }

    /**
     * Returns the MySQL date format for a column
     *
     * @param array $column
     *
     * @return string
     */
    protected function getDateFormatForColumn($column) {
        if (in_array($column['DATA_TYPE'], array('datetime', 'timestamp'))) {
            return 'Y-m-d H:i:s';
        } else if ($column['DATA_TYPE'] == 'date') {
            return 'Y-m-d';
        } else if ($column['DATA_TYPE'] == 'time') {
            return 'H:i:s';
        } else if ($column['DATA_TYPE'] == 'year') {
            return 'Y';
        }

        return 'Y-m-d H:i:s';
    }

    /**
     * Returns the label for a column
     *
     * @param array $column
     *
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
     *
     * @return string
     */
    protected function getColumnInputName($table, $column) {
        return lcfirst($table['classNamePartial']) . '[' . $column['COLUMN_NAME'] . ']';
    }

    /**
     * Returns the maxLength to use for a column
     *
     * @param array $column
     *
     * @return string
     */
    protected function getColumnMaxLength($column) {
        $maxlength = null;

        if (!empty($column['CHARACTER_MAXIMUM_LENGTH'])) {
            $maxlength = $column['CHARACTER_MAXIMUM_LENGTH'];
        } else if (!empty($column['NUMERIC_PRECISION'])) {
            $maxlength = $column['NUMERIC_PRECISION'];
            // Signed fields should allow for a negative symbol
            if (strpos($column['COLUMN_TYPE'], 'unsigned') === false) {
                ++$maxlength;
            }
            // Numbers that can have decimals need to allow for a decimal symbol
            if (in_array($column['DATA_TYPE'], array('float', 'double', 'decimal'))) {
                ++$maxlength;
            }
        } else if ($this->isColumnTimeRelated($column)) {
            // Dates can be written in so many formats, just allow for 50 characters. 30 September 2011 HH:MM:SS aa
            $maxlength = 50;
            if ($column['DATA_TYPE'] == 'year') {
                $maxlength = 4;
            } else if ($column['DATA_TYPE'] == 'time') {
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
     *
     * @return string
     */
    protected function getColumnFormElement($column) {
        $element = 'Zend_Form_Element_Text';

        if ($this->isColumnAutoIncrement($column)) {
            $element = 'Zend_Form_Element_Hidden';
        } else if ($this->columnHasComment('BOOLEAN', $column)) {
            $element = 'Zend_Form_Element_Checkbox';
        } else if ($column['COLUMN_NAME'] == 'password') {
            $element = 'Zend_Form_Element_Password';
        } else if (in_array($column['DATA_TYPE'], array(
            'text',
            'tinytext',
            'mediumtext',
            'longtext',
            'blob',
        ))) {
            $element = 'Zend_Form_Element_Textarea';
        } else if ($column['DATA_TYPE'] == 'varchar' && $column['CHARACTER_MAXIMUM_LENGTH'] >= 50) {
            $element = 'Zend_Form_Element_Textarea';
        } else if ($column['DATA_TYPE'] == 'enum') {
            $element = 'Zend_Form_Element_Select';
        } else if ($column['DATA_TYPE'] == 'date') {
            $element = 'ZendX_JQuery_Form_Element_DatePicker';
        } else if ($column['DATA_TYPE'] == 'datetime') {
            $element = 'ZendX_JQuery_Form_Element_DateTimePicker';
        } else if ($column['DATA_TYPE'] == 'time') {
            $element = 'ZendX_JQuery_Form_Element_TimePicker';
        } else if ($column['DATA_TYPE'] == 'timestamp') {
            $element = 'Zend_Form_Element_Hidden';
        }

        return $element;
    }

    /**
     * Returns the validators to be used with the column
     *
     * @param array $column
     *
     * @return array $validators
     */
    protected function getColumnValidators($column) {
        $validators = array();

        if ($column['COLUMN_NAME'] == 'email') {
            $validators[] = 'EmailAddress';
        }

        return $validators;
    }

    /**
     * Returns the filters to be used with the column
     *
     * @param array $column
     *
     * @return array $filters
     */
    protected function getColumnFilters($column) {
        $filters = array();
        $filters[] = 'StringTrim';

        if ($column['COLUMN_NAME'] == 'email') {
            $validators[] = 'StringToLower';
        }

        return $filters;
    }

    /**
     * Returns the default value that should be used for a given column
     *
     * @param array $column
     *
     * @return mixed
     */
    protected function getDefaultColumnValue($column) {
        $defaultValue = $column['COLUMN_DEFAULT'];
        if ($this->columnHasComment('CURRENT_TIMESTAMP', $column)) {
            $defaultValue = 'CURRENT_TIMESTAMP';
        }

        if ($defaultValue === null) {
            if ($column['IS_NULLABLE'] != 'YES' && !$this->isColumnAutoIncrement($column)) {
                if ($this->isColumnNumeric($column)) {
                    $defaultValue = 0;
                } else {
                    $defaultValue = '';
                }
            }
        }

        return $defaultValue;
    }

    /**
     * Returns the var type for a mysql column array
     *
     * @param array $column
     *
     * @return string
     */
    protected function getColumnVarType($column) {
        $type = $this->columnTypeToVarType($column['DATA_TYPE']);
        if ($this->columnHasComment('BOOLEAN', $column)) {
            $type = 'boolean';
        }
        return $type;
    }

    /**
     * Map mysql column types to php var types
     *
     * @param string $mysqlColumnType
     *
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
