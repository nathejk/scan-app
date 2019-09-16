<?php
namespace Nathejk;

abstract class Entity
{
    protected $_isChanged = false;

    /**
     * @param string $field
     *
     * @return mixed
     */
    public function get($field)
    {
        if (!property_exists($this, $field)) {
            throw new \OutOfBoundsException('Unknown property ' . get_class($this) . '::' . $field);
        }
        return $this->$field;
    }

    /**
     * @param string $field
     * @param mixed $value
     */
    public function set($field, $value)
    {
        if (!property_exists($this, $field)) {
            throw new \OutOfBoundsException('Unknown property ' . get_class($this) . '::' . $field);
        }
        if ($this->$field !== $value) {
            $this->_isChanged = true;
        }
        return $this->$field = $value;
    }

    public function isChanged()
    {
        return $this->_isChanged;
    }

    /**
     * Magic getters and setters.
     *
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $args)
    {
        $command = substr($method, 0, 3);
        $field = lcfirst(substr($method, 3));
        if ($command == 'get') {
            return $this->get($field);
        } elseif ($command == 'set') {
            $this->set($field, $args[0]);
            return $this;
        } else {
            throw new \BadMethodCallException('Unknown method ' . get_class($this) . '::' . $method . '()');
        }
    }

    /**
     * @param string $field
     *
     * @return bool
     */
    public function __isset($field)
    {
        return isset($this->$field);
    }

    /**
     * @param string $field
     *
     * @return mixed
     */
    public function __get($field)
    {
        return $this->{'get' . ucfirst($field)}();
    }
}
