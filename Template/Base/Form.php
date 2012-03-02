<?php

/**
 * Generated_Base_Form
 *
 * Generated base class file for forms
 * Any changes here will be overridden.
 */
abstract class DDM_Scaffold_Template_Base_Form extends ZendX_JQuery_Form
{

    /**
     * Zend_Db_Table_Abstract class name
     */
    protected $_tableClass = null;

    /**
     * Value to use when prefixing the input names
     */
    protected $_inputPrefix = null;

    /**
     * Get the related table class
     *
     * @return Zend_Db_Table_Abstract
     */
    protected function getTable()
    {
        if ($this->_tableClass === null) {
            throw new Zend_Form_Exception('$this->_tableClass is null. Table cannot be returned without first specifying a class.');
        }
        
        $table = new $this->_tableClass();
        return $table;
    }

    /**
     * Returns the form version of the key for us in data arrays and form input names
     *
     * @param string $key
     * @return string
     */
    protected function convertToFormKey($key)
    {
        if (strpos($key, $this->_inputPrefix . '_') !== 0) {
            return $this->_inputPrefix . '_' . $key;
        }
        return $key;
    }

    /**
     * Returns the db version of the key for us in data arrays
     *
     * @param string $key
     * @return string
     */
    protected function convertToDbKey($key)
    {
        return str_replace($this->_inputPrefix . '_', '', $key);
    }

    /**
     * Processes the form
     *
     * @param Zend_Controller_Request_Abstract $request
     * @return mixed
     */
    public function process(Zend_Controller_Request_Abstract $request)
    {
        $primaryKeys = $this->getTable()->getPrimaryKeys();
        $lookupKeys = array();
        foreach ($primaryKeys as $primaryKey) {
            if ($request->getParam($primaryKey) !== null) {
                $lookupKeys[$primaryKey] = $request->getParam($primaryKey);
            } else {
                $formKey = $this->convertToFormKey($primaryKey);
                if ($request->getParam($formKey) !== null) {
                    $lookupKeys[$primaryKey] = $request->getParam($formKey);
                }
            }
        }
        
        if ($this->canLoad($lookupKeys)) {
            $table = $this->getTable();
            $rowset = $table->find($lookupKeys);
            if ($rowset->count() == 1) {
                $row = $rowset->current();
                $data = $row->toArray();
        
                $populate = array();
                foreach ($data as $key => $value) {
                    $formKey = $this->convertToFormKey($key);
                    $populate[$formKey] = $value;
                }
        
                $this->populate($populate);
            }
        }
        
        if ($request->isPost()) {
            $formData = $request->getParams();
        
            if ($this->isValid($formData)) {
                $dbData = array();
                $formData = $this->getValues();
                foreach ($formData as $key => $value) {
                    $dbKey = $this->convertToDbKey($key);
                    $dbData[$dbKey] = $value;
                }
        
                if ($this->canSave($dbData)) {
                    $table = $this->getTable();
                    $result = null;
                    if (count($lookupKeys) > 0) {
                        $rowset = $table->find($lookupKeys);
                        if ($rowset->count() == 1) {
                            $row = $rowset->current();
                            $row->setFromArray($dbData);
                            $result = $row->save();
                        }
                    }
        
                    if ($result === null) {
                        $row = $table->createRow($dbData);
                        $result = $row->save();
                    }
        
                    $this->postProcess($result);
                    return $result;
                }
            }
        }
        return false;
    }

    /**
     * Performs any work that needs to happen after a form has been processed
     *
     * @param unknown $result
     */
    public function postProcess($result)
    {
        return;
    }

    /**
     * Can this item be loaded in the form?
     *
     * @param array $primaryKeys
     * @return boolean
     */
    protected function canLoad(array $primaryKeys)
    {
        return true;
    }

    /**
     * Can this item be saved?
     *
     * @param array $data
     * @return boolean
     */
    protected function canSave($data)
    {
        return true;
    }

    /**
     * Populate Element Multi-Options
     *
     * NOTE: Set element attribute['populateOptions'] = 'methodName' to override the
     * default derived from the element name. To disable the populateOptions
     * altogether, set attribute['populateOptions'] = false
     */
    public function populateOptions()
    {
        $methodFilter = new Zend_Filter_Word_UnderscoreToCamelCase();
        foreach ($this->getElements() as $element) {
            if (is_subclass_of($element, 'Zend_Form_Element_Multi') && !$element->getMultiOptions()) {
                $method = $element->getAttrib('populateOptions');
                if ($method === null) {
                    $method = 'populate_' . $this->convertToDbKey($element->getName());
                    $method = lcfirst($methodFilter->filter($method));
                } else if ($method === false) {
                    continue;
                }
        
                if (method_exists($this, $method)) {
                    $this->$method();
                } else {
                    throw new Zend_Form_Exception('No method ('.$method.') of populating field: ' . $element->getName());
                }
            }
        }
    }


}
