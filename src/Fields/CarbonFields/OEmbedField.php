<?php

namespace PeteKlein\Performant\Fields\CarbonFields;

use Carbon_Fields\Field;
use Embed\Embed;

class OEmbedField extends CFFieldBase
{
    public function __construct(string $key, string $label, $defaultValue = null, array $options = [])
    {
        parent::__construct($key, $label, $defaultValue, $options);
    }

    /**
     * @inheritDoc
     */
    public function createAdminField() : \Carbon_Fields\Field\Field
    {
        $this->adminField = Field::make('oembed', $this->key, $this->label);
        $this->setAdminOptions();

        return $this->adminField;
    }

    /**
     * @inheritDoc
     */
    public function getSelectionSQL() : string
    {
        $metaKey = $this->getPrefixedKey();

        return "= '$metaKey'";
    }

    /**
     * @inheritDoc
     */
    public function getValue(array $meta)
    {
        foreach ($meta as $m) {
            if ($m->meta_key === $this->getPrefixedKey() && $m->meta_value) {
                $embed = Embed::create($m->meta_value);
                return $embed->code;
            }
        }

        return $this->defaultValue;
    }

    /**
     * @inheritDoc
     */
    public function setAdminOptions() : void
    {
        $this->setDefaultAdminOptions();
    }
}
