<?php

namespace PeteKlein\Performant\Fields\CarbonFields;

use Carbon_Fields\Field;

class GroupField extends CFFieldBase
{
    private $fields = [];

    /**
     * @inheritDoc
     */
    public function __construct(string $key, string $label, $defaultValue = null, array $fields, array $options = [])
    {
        $fieldDefaults = [
            'duplicate_groups_allowed' => false,
            'layout' => 'grid',
            'collapsed' => false,
            'min' => -1,
            'max' => -1,
            'labels' => [
                'singular_name' => 'Entry',
                'plural_name' => 'Entries'
            ]
        ];
        $combinedOptions = $this->combineOptions($fieldDefaults, $options);
        
        parent::__construct($key, $label, $defaultValue, $combinedOptions);
        $this->setFields($fields);
    }

    public function getSelectionSQL() : string
    {
        $metaKey = $this->getPrefixedKey();

        return "LIKE '$metaKey|%'";
    }

    /**
     * @inheritDoc
     */
    public function getValue(array $results)
    {
        $value = [];

        foreach($results as $meta) {
            $metaBelongsToThisGroup = strpos($meta->meta_key, $this->getPrefixedKey() . '|') !== false;

            if (!$metaBelongsToThisGroup) {
                continue;
            }
            
            // $this->addMetaToValue($value, $meta->meta_key, $meta->meta_value);

            return carbon_get_post_meta( $meta->post_id, $this->key );
        }

        return empty($value) ? $this->defaultValue : $value;
    }

    private function addMetaToValue(array &$value, string $metaKey, $metaValue): void
    {
        $metaKeyParts = explode('|', $metaKey);
        $keys = explode(':', $metaKeyParts[1]);
        $keyCount = count($keys);
        $positions = explode(':', $metaKeyParts[2]);

        if (count($positions) === 1) {
            $field = $this->getField($keys[0]);
            if (!empty($field) && !$field instanceof GroupField) {
                $value[$positions[0]][$keys[0]] = $metaValue;
            }
        } else {
            // TODO: get this to create a nested structure based on keys
            // Maybe try getting the nesting concept working in a test function outside the project?

            // if the key doesn't exist, create the key
            echo "<pre>$metaKey = $metaValue</pre>";
            echo "<pre>" . print_r($keys, true) . "</pre>";

            $prevValue = null;
            foreach ($keys as $i => $key) {
                $isLastKey = $keyCount === $i + 1;
                if (!$isLastKey) {
                    echo $key . ' needs to be created if it does not exist!!<br>';
                    if(empty($value[$positions[$i]][$key])) {
                        $value[$positions[$i]][$key] = [];
                    }
                }
                else {
                    echo 'set the value for ' . $key . '<br />';
                    $value[$positions[$i]][$key] = $metaValue;
                }
                // echo "<pre>$key</pre>";
                /*
                if(empty($value[$i][$key])){
                    $value[$i][$key] = [];
                }
                $groupIndex = $positions[0];
                $groupKey = $keys[0];
                $valueIndex = $positions[1];
                $valueKey = $keys[1];
                $value[$groupIndex][$groupKey][$valueIndex][$valueKey] = $metaValue;
                */
            }
            
            
        }
    }

    /**
     * @inheritDoc
     */
    public function createAdmin()
    {
        $this->adminField =  Field::make('complex', $this->key, $this->label)
            ->add_fields($this->getAdminFields());
        
        $this->setSharedOptions();

        foreach ($this->options as $option => $value) {
            switch ($option) {
                case 'duplicate_groups_allowed':
                    $this->adminField->set_duplicate_groups_allowed($value);
                    break;
                case 'layout':
                    $this->adminField->set_layout($value);
                    break;
                case 'collapsed':
                    $this->adminField->set_collapsed($value);
                    break;
                case 'min':
                    $this->adminField->set_min($value);
                    break;
                case 'max':
                    $this->adminField->set_max($value);
                    break;
                case 'labels':
                    $this->adminField->setup_labels($value);
                    break;
            }
        }
    }

    private function setFields(array $fields)
    {
        $this->fields = [];
        foreach ($fields as $field) {
            $this->addField($field);
        }
    }

    private function addField(CFFieldBase $field) : void
    {
        $this->fields[] = $field;
    }

    private function getField($key) : ?CFFieldBase
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Return created admin fields
     *
     * @return array admin fields
     */
    public function getAdminFields() : array {
        $subFields = [];
        foreach ($this->fields as $field) {
            $field->createAdmin();
            $subFields[] = $field->getAdminField();
        }

        return $subFields;
    }
}
