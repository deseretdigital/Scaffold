<?php

/**
 * Generated_Base_Table
 *
 * Generated base class file for tables
 * Any changes here will be overridden.
 */
abstract class DDM_Scaffold_Template_Base_Table extends Zend_Db_Table_Abstract
{

    /**
     * The table metadata has been cached
     *
     * @var boolean
     */
    protected $_metadataCacheInClass = true;

    /**
     * Where should the default values come for new empty rows?
     * The code generator replaces self::DEFAULT_CLASS with its value, 'defaultClass'
     *
     * @var string
     */
    protected $_defaultSource = self::DEFAULT_CLASS;

    /**
     * Returns the schema of the table
     *
     * @return string
     */
    public function getSchema()
    {
        return $this->_schema;
    }

    /**
     * Returns the name of the table
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->_name;
    }

    /**
     * Get an array of primary keys
     *
     * @return array
     */
    public function getPrimaryKeys()
    {
        return $this->_primary;
    }

    /**
     * Returns a new rowset (not from the database) optionally populated with the
     * passed in $data
     *
     * @param array|null $data
     *
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function createRowset(array $data = null)
    {
        $config = array(
            'table' => $this,
            'rowClass' => $this->getRowClass(),
            'stored' => false,
            'readOnly' => false,
        );
        $rowsetClass = $this->getRowsetClass();
        $rowset = new $rowsetClass($config);
        if ($data !== null) {
            $rowset->setFromArray($data);
        }
        return $rowset;
    }

    /**
     * Retrieve Rowset from table where $columnName matches $value
     *
     * @param string $columnName
     * @param string|number|null $value
     * @param Zend_Db_Select|Zend_Db_Table_Select|null OPTIONAL $select
     *
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findByColumnValue($columnName, $value, Zend_Db_Select $select = null)
    {
        if ($select === null) {
            $select = $this->select();
        }
        $select->from($this);

        $tableName = $this->getAdapter()->quoteIdentifier($this->_name);
        $columnName = $this->getAdapter()->quoteIdentifier($columnName);
        $columnName = $tableName . '.' . $columnName;
        if (!is_array($value)) {
            $select->where($columnName . ' = ?', $value);
        } else {
            $expressions = array();
            $inValues = array();

            foreach ($value as $val) {
                if ($val === null) {
                    $expressions['null'] = $columnName . ' IS NULL';
                } else {
                    $inValues[] = $this->getAdapter()->quoteInto('?', $val);
                }
            }

            if (!empty($inValues)) {
                $expressions['in'] = $columnName . ' IN ('.implode(',', $inValues).')';
            }

            $select->where(implode(' OR ', $expressions));
        }

        return $this->fetchAll($select);
    }
    
    /**
     * Finds records by multiple columns
     * @param array $columnsAndValues key is the column and value is either null, an array of values, or a single value
     * @param Zend_Db_Select $select
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findByColumnValues(array $columnsAndValues, Zend_Db_Select $select = null)
    {
        // make sure we have select
        if ($select === null) {
            $select = $this->select();
        }
    
        // set from
        $select->from($this);
    
        // set where for each column
        foreach ($columnsAndValues as $column => $value) {
            $conditions = $this->_buildColumnConditions($column, $value);
            $select->where($conditions);
        }
    
        return $this->fetchAll($select);
    }
    
    /**
     * Used internally to build column conditions for select, update, delete
     * @param string $column
     * @param mixed $value
     * return string
     */
    protected function _buildColumnConditions($column, $value)
    {
        $adapter = $this->getAdapter();
        $columnName = $adapter->quoteIdentifier($this->_name) . '.' . $adapter->quoteIdentifier($column);
    
        if ($value === null) {
    
            $conditions = $columnName . ' IS NULL';
    
        } elseif (is_array($value)) {
    
            $inValues = array_diff($value, array(null));
    
            if (count($inValues) < count($value)) {
                $conditions['null'] = $columnName . ' IS NULL';
            }
    
            $inValues = array_map(array($adapter, 'quote'), $inValues);
            $conditions['in'] = $columnName . ' IN (' . implode(',', $inValues) . ')';
    
            $conditions = implode(' OR ', $conditions);
    
        } else {
    
            $conditions = $adapter->quoteInto($columnName . ' = ?', $value);
    
        }
    
        return $conditions;
    }
}
