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
     */
    protected $_metadataCacheInClass = true;

    /**
     * Where should the default values come for new empty rows?
     * The code generator replaces self::DEFAULT_CLASS with its value, 'defaultClass'
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
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findByColumnValue($columnName, $value, Zend_Db_Select $select = null)
    {
        if ($select === null) {
            $select = $this->select();
        }
        $select->from($this);
        
        $columnName = $this->getAdapter()->quoteIdentifier($columnName);
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


}
