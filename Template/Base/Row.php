<?php

/**
 * Generated_Base_Row
 *
 * Generated base class file for rows
 * Any changes here will be overridden.
 */
abstract class DDM_Scaffold_Template_Base_Row extends Zend_Db_Table_Row_Abstract
{

    /**
     * The filter used to convert strings to function names
     *
     * @var Zend_Filter_Word_Separator_Abstract|null
     */
    protected $_functionNameFilter = null;

    /**
     * The filter used to convert function names to strings
     *
     * @var Zend_Filter_Word_Separator_Abstract|null
     */
    protected $_columnNameFilter = null;

    /**
     * Constructor overwritten to use setter methods
     *
     * @param array $config OPTIONAL Array of user-specified config options.
     *
     * @return void
     *
     * @throws Zend_Db_Table_Row_Exception
     */
    public function __construct(array $config = array ())
    {
        if (isset($config['table']) && $config['table'] instanceof Zend_Db_Table_Abstract) {
            $this->_table = $config['table'];
            $this->_tableClass = get_class($this->_table);
        } elseif ($this->_tableClass !== null) {
            $this->_table = $this->_getTableFromString($this->_tableClass);
        }

        if (isset($config['data'])) {
            if (!is_array($config['data'])) {
                throw new Zend_Db_Table_Row_Exception('Data must be an array');
            }
            // We have to set the $_data column keys first because setFromArray is going to verify that the array keys exist before allowing a column to be saved
            $column_keys = array_combine(array_keys($config['data']), array_fill(0, count($config['data']), null));
            $this->_data = $column_keys;
            $this->setFromArray($config['data']);
        }
        if (isset($config['stored']) && $config['stored'] === true) {
            $this->_cleanData = $this->_data;
        }

        if (isset($config['readOnly']) && $config['readOnly'] === true) {
            $this->setReadOnly(true);
        }

        // Retrieve primary keys from table schema
        if (($table = $this->_getTable())) {
            $info = $table->info();
            $this->_primary = (array) $info['primary'];
        }

        $this->init();
    }

    /**
     * Calls the parent method and then sets any addition data parameters the row can
     * accept
     *
     * @param array $data
     *
     * @return Zend_Db_Table_Row_Abstract
     */
    public function setFromArray(array $data)
    {
        parent::setFromArray($data);
        $data = array_diff_key($data, $this->_data);

        foreach ($data as $name => $value) {
            $function = $this->getFunctionName('set_' . $name);
            if (method_exists($this, $function)) {
                $this->$function($value);
            }
        }

        return $this;
    }

    /**
     * Turns get and set method calls to getColumnValue and setColumnValue calls
     *
     * @param string $method
     * @param array $args OPTIONAL Zend_Db_Table_Select query modifier
     *
     * @return Zend_Db_Table_Row_Abstract|Zend_Db_Table_Rowset_Abstract
     *
     * @throws Zend_Db_Table_Row_Exception If an invalid method is called.
     */
    public function __call($method, array $args)
    {
        $columnName = $this->getColumnName($method);

        if (strpos($columnName, 'get_') === 0) {
            $columnName = str_replace('get_', '', $columnName);
            return $this->getColumnValue($columnName);
        } else if (strpos($columnName, 'set_') === 0) {
            $columnName = str_replace('set_', '', $columnName);
            return $this->setColumnValue($columnName, $args[0]);
        }

        return parent::__call($method, $args);
    }

    /**
     * Redirects __get to a getColumnName method
     *
     * @param string $columnName
     *
     * @return mixed
     */
    public function __get($columnName)
    {
        $functionName = $this->getFunctionName('get_' . $columnName);
        return $this->$functionName();
    }

    /**
     * Redirects __set to a setColumnName method
     *
     * @param string $columnName
     * @param mixed $value
     *
     * @return void
     */
    public function __set($columnName, $value)
    {
        $functionName = $this->getFunctionName('set_' . $columnName);
        return $this->$functionName($value);
    }

    /**
     * Retrieve row field value using old __get logic
     *
     * @param string $columnName The user-specified column name.
     *
     * @return string The corresponding column value.
     *
     * @throws Zend_Db_Table_Row_Exception if the $columnName is not a column in the
     * row.
     */
    protected function getColumnValue($columnName)
    {
        return parent::__get($columnName);
    }

    /**
     * Set row field value using old __set logic
     *
     * @param string $columnName The column key.
     * @param mixed $value The value for the property.
     *
     * @return void
     *
     * @throws Zend_Db_Table_Row_Exception
     */
    protected function setColumnValue($columnName, $value)
    {
        return parent::__set($columnName, $value);
    }

    /**
     * Returns the rows primary key fields
     *
     * @return array
     */
    public function getPrimaryKeys()
    {
        return $this->_getPrimaryKey();
    }

    /**
     * Returns the column/value data as an array using getters
     *
     * @return array
     */
    public function toArray()
    {
        $data = array();

        foreach ($this->_data as $columnName => $value) {
            $data[$columnName] = $this->__get($columnName);
        }

        return $data;
    }

    /**
     * Converts a string to the function name
     *
     * @param string $functionName
     *
     * @return string
     */
    protected function getFunctionName($functionName)
    {
        if ($this->_functionNameFilter === null) {
            $this->_functionNameFilter = new Zend_Filter_Word_UnderscoreToCamelCase();
        }

        return lcfirst($this->_functionNameFilter->filter($functionName));
    }

    /**
     * Converts a string to a column name
     *
     * @param string $columnName
     *
     * @return string
     */
    protected function getColumnName($columnName)
    {
        if ($this->_columnNameFilter === null) {
            $this->_columnNameFilter = new Zend_Filter_Word_CamelCaseToUnderscore();
        }

        return strtolower($this->_columnNameFilter->filter($columnName));
    }

    /**
     * Calls the parent method unless a primary key is null, in which case an empty
     * rowset is returned (instead of an exception)
     *
     * @param string|Zend_Db_Table_Abstract $dependentTable
     * @param string $ruleKey OPTIONAL
     * @param Zend_Db_Table_Select $select OPTIONAL
     *
     * @return Zend_Db_Table_Rowset_Abstract Query result from $dependentTable
     */
    public function findDependentRowset($dependentTable, $ruleKey = null, Zend_Db_Table_Select $select = null)
    {
        if (in_array(null, $this->getPrimaryKeys(), true)) {
            if (is_string($dependentTable)) {
                $dependentTable = $this->_getTableFromString($dependentTable);
            }

            // getReference will throw a Zend_Db_Table_Exception if the table reference is invalid.
            $dependentTable->getReference($this->getTableClass(), $ruleKey);

            return $dependentTable->createRowset();
        }
        return parent::findDependentRowset($dependentTable, $ruleKey, $select);
    }


}
