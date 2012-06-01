<?php

/**
 * Generated_Base_Rowset
 *
 * Generated base class file for rowsets
 * Any changes here will be overridden.
 */
abstract class DDM_Scaffold_Template_Base_Rowset extends Zend_Db_Table_Rowset_Abstract
{
    /**
     * The filter used to convert strings to function names
     *
     * @var Zend_Filter_Word_Separator_Abstract|null
     */
    protected $_functionNameFilter = null;

    /**
     * Groups a rowset by a specified columnName
     *
     * @param string $columnName
     *
     * @return array
     */
    public function groupBy($columnName)
    {
        // Call through the getter so we can use custom columns that
        // may not be present in the actual database
        $getter = $this->getFunctionName('get_' . $columnName);
        $groups = array();
        foreach ($this as $row) {
            $key = $row->$getter();
            if (!array_key_exists($key, $groups)) {
                $groups[$key] = $this->getTable()->createRowset();
            }
            $group =& $groups[$key];
            $group->addRow($row);
        }
        return $groups;
    }

    /**
     * Filters a rowset by a specified columnName and value
     *
     * @param string $columnName
     * @param string $value
     *
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function filterBy($columnName, $value)
    {
        // Call through the getter so we can use custom columns that
        // may not be present in the actual database
        $getter = $this->getFunctionName('get_' . $columnName);
        $rowset = $this->getTable()->createRowset();
        foreach ($this as $row) {
            if ($row->$getter() == $value) {
                $rowset->addRow($row);
            }
        }
        return $rowset;
    }

    /**
     * Returns a row matching the set an array of primary keys
     *
     * @param array $keys
     *
     * @return Zend_Db_Table_Row_Abstract
     */
    public function getRowByPrimaryKeys(array $data)
    {
        $primaryKeys = $this->getTable()->getPrimaryKeys();
        $lookupKeys = array();
        foreach ($primaryKeys as $primaryKey) {
            if (array_key_exists($primaryKey, $data)) {
                $lookupKeys[$primaryKey] = $data[$primaryKey];
            }
        }

        if (count($lookupKeys) != count($primaryKeys)) {
            throw new Zend_Db_Table_Rowset_Exception('Expecting '.count($primaryKeys).' primary keys. Only '.count($lookupKeys).' passed to getRowByPrimaryKeys.');
        }

        $rowset = $this;
        foreach ($lookupKeys as $lookupKey => $lookupKeyValue) {
            $rowset = $rowset->filterBy($lookupKey, $lookupKeyValue);
        }

        if (!count($rowset) > 0) {
            throw new Zend_Db_Table_Rowset_Exception('Requested row not found in rowset.');
        }

        return $rowset->current();
    }

    /**
     * Adds a row to the rowset
     *
     * @param Zend_Db_Table_Row_Abstract $row
     */
    public function addRow(Zend_Db_Table_Row_Abstract $row)
    {
        if (get_class($row) != $this->_rowClass) {
            throw new Zend_Db_Table_Rowset_Exception('Row must be of the class ' . $this->_rowClass . ' but is of the class ' . get_class($row));
        }
        $this->_rows[] = $row;
        $this->_data[] = $row->toArray();
        //Count needs to update so we can loop through the rowset correctly still
        $this->_count = count($this->_data);
    }

    /**
     * Returns a new blank row (not from the database)
     *
     * @return Zend_Db_Table_Row_Abstract
     */
    public function createRow()
    {
        return $this->getTable()->createRow();
    }

    /**
     * Saves all the rows in the rowset
     *
     * @return array
     */
    public function save()
    {
        $results = array();
        foreach ($this as $row) {
            $results[] = $row->save();
        }
        return $results;
    }

    /**
     * Deletes all the rows in the rowset
     *
     * @return boolean
     *
     * @throws Zend_Db_Table_Rowset_Exception
     */
    public function delete()
    {
        foreach ($this as $row) {
            $success = $row->delete();
            if(!$success) {
                throw new Zend_Db_Table_Rowset_Exception('Row with the primary keys '.implode(',', $row->getPrimaryKeys()).' could not be deleted!');
            }
        }
        $this->reset();
        return true;
    }

    /**
     * Sets data from an array with all child elements
     *
     * @param array $data
     */
    public function setFromArray(array $data)
    {
        $primaryKeys = $this->getTable()->getPrimaryKeys();

        foreach ($data as $datum) {
            $keys = array();
            foreach ($primaryKeys as $primaryKey) {
                if (array_key_exists($primaryKey, $datum) && $datum[$primaryKey] !== null) {
                    $keys[$primaryKey] = $datum[$primaryKey];
                }
            }

            try {
                $row = $this->getRowByPrimaryKeys($keys);
            } catch (Zend_Db_Table_Rowset_Exception $e) {
                $row = $this->createRow();
                $this->addRow($row);
            }

            $row->setFromArray($datum);
        }
        // This comment to fix bug in code generator - http://framework.zend.com/issues/browse/ZF-9501#comment-44390
    }

    /**
     * Forwards get and set methods not recognized to the individual row objects
     *
     * @param string $method
     * @param array $args
     *
     * @return array
     *
     * @throws Zend_Db_Table_Rowset_Exception If an invalid method is called.
     */
    public function __call($method, array $args)
    {
        if (strpos($method, 'get') === 0) {
            $data = array();
            foreach($this as $row) {
                $data[] = $row->$method();
            }
            return $data;
        } else if (strpos($method, 'set') === 0) {
            foreach($this as $row) {
                $row->$method($args[0]);
            }
            return;
        }

        throw new Zend_Db_Table_Rowset_Exception('Unrecognized method "'.$method.'()"');
    }

    /**
     * Redirects __get to a getColumnName method and ensures we go through the rowsets current()
     *
     * @return array
     */
    public function toArray()
    {
        $data = array();
        $usePrimaryKey = count($this->getTable()->getPrimaryKeys()) == 1;
        foreach ($this as $row) {
            if ($usePrimaryKey) {
                $keys = $row->getPrimaryKeys();
                $key = array_shift($keys);
                if ($key === null) {
                    $key = uniqid('NULL_');
                }
                $data[$key] = $row->toArray();
            } else {
                $data[] = $row->toArray();
            }
        }
        return $data;
    }

    /**
     * Resets a rowset to remove all data and rows
     */
    protected function reset()
    {
        $this->_data = array();
        $this->_rows = array();
        $this->_count = 0;
        $this->_pointer = 0;
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
            $this->_functionNameFilter = new DDM_Filter_Word_UnderscoreToCamelCase();
        }

        return lcfirst($this->_functionNameFilter->filter($functionName));
    }
}
