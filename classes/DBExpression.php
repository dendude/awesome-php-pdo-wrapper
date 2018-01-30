<?php

namespace Database;

/**
 * Class DBExpression
 *
 * very simple class for use it in query without escaping
 *
 * @package Database
 */
class DBExpression {

    protected $value;

    public function __construct($value) {
        $this->value;
    }

    public function getValue() {
        return $this->value;
    }
}