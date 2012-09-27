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
     * Holds the total count when the rowset is only a subset of a larger set of
     * rows (used for pagination).
     *
     * @var int
     */
    protected $totalCount;

    /**
     * Current page when the rowset is only a subset of a larger set of
     * rows (used for pagination).
     *
     * @var int
     */
    protected $currentPage;

    /**
     * Page size when the rowset is only a subset of a larger set of
     * rows (used for pagination).
     *
     * @var int
     */
    protected $pageSize;

    /**
     * Checks to see if the rowset is paginated.
     *
     * @return boolean
     */
    public function isPaginated()
    {
        return $this->getTotalCount() > count($this);
    }

    /**
     * Gets the total count when the rowset is only a subset of a larger set of
     * rows (used for pagination). If the total count has not been set, this
     * will return the count of current rows.
     *
     * @return int
     */
    public function getTotalCount()
    {
        if ($this->totalCount === null) {
            return count($this);
        }
        return $this->totalCount;
    }

    /**
     * Sets the total count when the rowset is only a subset of a larger set of
     * rows (used for pagination).
     *
     * @param int $count
     */
    public function setTotalCount($count)
    {
        $this->totalCount = (int)$count;
    }

    /**
     * Returns the current page of the rowset (used for pagination).
     *
     * @return int
     */
    public function getCurrentPage()
    {
        if ($this->currentPage === null) {
            return 1;
        }
        return $this->currentPage;
    }

    /**
     * Sets the current page of the rowset (used for pagination).
     *
     * @param int $page
     */
    public function setCurrentPage($page)
    {
        $this->currentPage = (int) $page;
    }

    /**
     * Gets the size of each page (used for pagination).
     *
     * @return int
     */
    public function getPageSize()
    {
        if (!$this->isPaginated()) {
            return count($this);
        }

        if ($this->currentPage === null) {
            return $this->getTable()->getDefaultPageSize();
        }

        return $this->pageSize;
    }

    /**
     * Sets the size of each page
     *
     * @param int $pageSize
     */
    public function setPageSize($pageSize)
    {
        $this->pageSize = (int) $pageSize;
    }

    /**
     * Calculates the total number of pages for a rowset
     *
     * @return int
     */
    public function getTotalPages()
    {
        if ($this->getPageSize() == 0) {
            return 0;
        }

        return ceil($this->getTotalCount() / $this->getPageSize());
    }

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
        return $this->filterByArray(
            array(
                $columnName => $value,
            )
        );
    }

    /**
     * Filters a rowset by a an array of column names and values
     *
     * @param array $filterColumns Key is column name. Value is column value.
     *
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function filterByArray(array $filterColumns)
    {
        $rowset = $this->getTable()->createRowset();
        foreach ($this as $row) {
            $rowMatches = true;
            foreach ($filterColumns as $filterColumn => $value) {
                // Call through the getter so we can use custom columns that
                // may not be present in the actual database
                $getter = $this->getFunctionName('get_' . $filterColumn);
                if ($row->$getter() != $value) {
                    $rowMatches = false;
                    break;
                }
            }

            if ($rowMatches) {
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
            if (is_object($datum) && get_class($datum) == $this->_rowClass) {
                $this->addRow($datum);
                continue;
            }

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
        // @TODO: Fixed in ZF 1.12.0. Remove when we upgrade to ZF 1.12.0
    }

    /**
     * Forwards get and set methods not recognized to the individual row objects
     *
     * @param string $method
     * @param array $args
     *
     * @return mixed
     *
     * @throws Zend_Db_Table_Rowset_Exception If an invalid method is called.
     */
    public function __call($method, array $args)
    {
        if (strpos($method, 'get') === 0) {
            return $this->getFromEachRow($method);
        } else if (strpos($method, 'set') === 0) {
            return $this->setToEachRow($method, $args[0]);
        }

        throw new Zend_Db_Table_Rowset_Exception('Unrecognized method "'.$method.'()"');
    }

    /**
     * Calls a method on each row and returns the result to an array
     *
     * @param string $method
     *
     * @return array|Zend_Db_Table_Rowset_Abstract
     */
    protected function getFromEachRow($method)
    {
        $data = array();
        foreach ($this as $row) {
            $data[] = $row->$method();
        }

        // If we're returning an array of row objects, return a rowset instead
        if (count($data) > 0) {
            $testRow = reset($data);
            if ($testRow instanceof Generated_Base_Row) {
                return $testRow->getTable()->createRowset($data);
            }
        }
        return $data;
    }

    /**
     * Calls a method on each row to set a value
     *
     * @param string $method
     * @param mixed $value
     */
    protected function setToEachRow($method, $value)
    {
        foreach ($this as $row) {
            $row->$method($value);
        }
        return;
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
