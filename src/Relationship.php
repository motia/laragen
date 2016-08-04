<?php

namespace Motia\Generator;

abstract class Relationship
{
    protected $processed;
    public $relationshipField;

    public function __construct($relationshipField)
    {
        $this->processed = false;
        $this->relationshipInput = $relationshipField;
    }

    public function getForeignKeys()
    {
    }

    public function process()
    {
    }

    protected function buildForeignKeys()
    {
    }
}
