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
     * Default number of items per page (used for pagination).
     *
     * @var int Default the number of items per page
     */
    protected $defaultPageSize = 12;

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
     * Paginator class name.
     *
     * @var string
     */
    protected $_paginatorClass = 'Zend_Paginator';

    /**
     * Paginates a select query
     *
     * @param Zend_Db_Table_Select_Abstract $select
     * @param array $options Options that contain page and page_size
     *
     * @return Zend_Db_Table_Rowset_Abstract
     */
    protected function paginate(Zend_Db_Table_Select $select, array $options = array())
    {
        $pageDefaults = array(
            'page' => 1,
            'page_size' => $this->defaultPageSize,
        );

        $options = array_merge($pageDefaults, $options);

        $paginator = $this->getPaginator($select);
        $paginator->setItemCountPerPage($options['page_size']);
        $paginator->setCurrentPageNumber($options['page']);

        $rowset = $paginator->getCurrentItems();
        $rowset->setTotalCount($paginator->getTotalItemCount());
        $rowset->setCurrentPage($paginator->getCurrentPageNumber());
        $rowset->setPageSize($paginator->getItemCountPerPage());

        return $rowset;
    }

    /**
     * Returns default page size for pagination.
     *
     * @return int
     */
    public function getDefaultPageSize()
    {
        return $this->defaultPageSize;
    }

    /**
     * Sets paginator class name.
     *
     * @param string $paginatorClass
     */
    protected function setPaginatorClass($paginatorClass)
    {
        $this->_paginatorClass = $paginatorClass;
    }

    /**
     * Returns paginator instance.
     *
     * @param mixed $data
     *
     * @return Zend_Paginator
     */
    protected function getPaginator($data)
    {
        $paginatorClass = (empty($this->_paginatorClass)) ? $this->_paginatorClass : 'Zend_Paginator';
        return $paginatorClass::factory($data);
    }

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
     * Returns rowset containing all rows.
     *
     * @param array $options
     *
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findAll(array $options = array())
    {
        $select = $this->select();
        $select->from($this);
        if (array_key_exists('pagination', $options)) {
            return $this->paginate($select, $options['pagination']);
        }
        return $this->fetchAll($select);
    }

    /**
     * Retrieve Rowset from table where $columnName matches $value
     *
     * @deprecated Use findByColumnValues instead
     *
     * @param string $columnName
     * @param string|number|null $value
     * @param Zend_Db_Select|Zend_Db_Table_Select|null OPTIONAL $select
     * @param array $options
     *
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findByColumnValue($columnName, $value, Zend_Db_Select $select = null, array $options = array())
    {
        return $this->findByColumnValues(array($columnName => $value), $select, $options);
    }

    /**
     * Finds records by multiple columns
     * @param array $columnsAndValues key is the column and value is either null, an array of values, or a single value
     * @param Zend_Db_Select $select
     * @param array $options
     *
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findByColumnValues(array $columnsAndValues, Zend_Db_Select $select = null, array $options = array())
    {
        $select = $this->findByColumnValuesSelect($columnsAndValues, $select);
        if (array_key_exists('pagination', $options)) {
            return $this->paginate($select, $options['pagination']);
        }
        return $this->fetchAll($select);
    }

    /**
     * Constructs the select statement for findByColumnValues
     *
     * @param array $columnsAndValues
     * @param Zend_Db_Select $select
     *
     * @return Zend_Db_Select
     */
    protected function findByColumnValuesSelect(array $columnsAndValues, Zend_Db_Select $select = null)
    {
        $columnsAndValues = array_intersect_key($columnsAndValues, array_flip($this->_getCols()));
        // make sure we have select
        if ($select === null) {
            $select = $this->select();
        }

        // set from only if it hasn't been set yet for this table
        $tableAlias = null;
        $fromParts = $select->getPart(Zend_Db_Select::FROM);
        foreach ($fromParts as $alias => $fromPart) {
            if ($fromPart['tableName'] == $this->_name) {
                $tableAlias = $alias;
            }
        }
        if ($tableAlias === null) {
            $select->from($this);
        }

        // set where for each column
        foreach ($columnsAndValues as $column => $value) {
            $conditions = $this->_buildColumnConditions($column, $value, $tableAlias);
            $select->where($conditions);
        }

        return $select;
    }

    /**
     * Used internally to build column conditions for select, update, delete
     *
     * @param string $column
     * @param mixed $value
     * @param string $tableAlias OPTIONAL
     *
     * @return string
     */
    protected function _buildColumnConditions($column, $value, $tableAlias = null)
    {
        if ($tableAlias === null) {
            $tableAlias = $this->_name;
        }
        $adapter = $this->getAdapter();
        $columnName = $adapter->quoteIdentifier($tableAlias) . '.' . $adapter->quoteIdentifier($column);

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
