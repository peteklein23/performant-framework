<?php

namespace PeteKlein\Performant\Fields;

use Carbon_Fields\Field;

class TextField extends FieldBase
{
    /**
     * @inheritDoc
     */
    public function __construct(string $key, string $label, array $options = [], $defaultValue = null)
    {
        parent::__construct($key, $label, 'text', $options, $defaultValue, true);
    }

    /**
     * @inheritDoc
     */
    public function createAdminField()
    {
        return Field::make('text', $this->key, $this->label);
    }
}
