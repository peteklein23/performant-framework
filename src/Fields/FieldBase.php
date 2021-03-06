<?php

namespace PeteKlein\Performant\Fields;

abstract class FieldBase
{
    public $key;
    public $label;
    public $type;
    public $options;
    public $defaultValue;

    /**
     * Sets data for the field
     *
     * @param string $key - the meta key
     * @param string $label - the label to be shown in the WordPress admin
     * @param string $type - the type of field to be created
     * @param mixed $defaultValue - the default value
     * @param array $options - additional options to be used in field creation
     */
    public function __construct(string $key, string $label, $defaultValue = null, array $options = [])
    {
        $this->key = $key;
        $this->label = $label;
        $this->options = $options;
        $this->defaultValue = $defaultValue;
    }

    /**
     * Creates the field in WordPress admin
     *
     * @return void
     */
    abstract public function createAdmin();

    /**
     * Returns a piece of SQL to use in the WHERE clause: e.g. `"= '$this->key'"` OR `"LIKE '%$this->key%'"`
     */
    abstract public function getSelectionSQL() : string;

    /**
     * Returns the formatted value from meta results
     * 
     * @param string $result meta results for a given object
     */
    abstract public function getValue(array $meta);
    
}
