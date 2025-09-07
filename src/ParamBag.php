<?php

/*
 * This file is part of the League\Fractal package.
 *
 * (c) Phil Sturgeon <me@philsturgeon.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Fractal;

/**
 * A handy interface for getting at include parameters.
 *
 * @implements \ArrayAccess<mixed, mixed>
 * @implements \IteratorAggregate<mixed, mixed>
 */
class ParamBag implements \ArrayAccess, \IteratorAggregate
{
    protected array $params = [];

    /**
     * Create a new parameter bag instance.
     */
    public function __construct(
        array                 $params,
        private readonly bool $allowModify = false,
    ) {
        $this->params = $params;
    }

    /**
     * Get parameter values out of the bag.
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        if (false === $this->allowModify) {
            throw new \LogicException('Modifying parameters is not permitted');
        }
        $this->params[$key] = $value;
    }

    /**
     * Get parameter values out of the bag via the property access magic method.
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function __get(string $key): mixed
    {
        return $this->params[$key] ?? null;
    }

    /**
     * Check if a param exists in the bag via an isset() check on the property.
     */
    public function __isset(string $key): bool
    {
        return isset($this->params[$key]);
    }

    /**
     * Disallow changing the value of params in the data bag via property access.
     *
     * @param mixed $value
     *
     * @throws \LogicException
     */
    public function __set(string $key, mixed $value): void
    {
        if (false === $this->allowModify) {
            throw new \LogicException('Modifying parameters is not permitted');
        }
        $this->params[$key] = $value;
    }

    /**
     * Disallow unsetting params in the data bag via property access.
     *
     * @return void
     * @throws \LogicException
     */
    public function __unset(string $key): void
    {
        if (false === $this->allowModify) {
            throw new \LogicException('Modifying parameters is not permitted');
        }
        unset($this->params[$key]);
    }

    /**
     * Check if a param exists in the bag via an isset() and array access.
     *
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return $this->__isset($offset);
    }

    /**
     * Get parameter values out of the bag via array access.
     *
     * @param string $offset
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset): mixed
    {
        return $this->__get($offset);
    }

    /**
     * Disallow changing the value of params in the data bag via array access.
     *
     * @param string $offset
     * @param mixed $value
     *
     * @throws \LogicException
     */
    public function offsetSet($offset, mixed $value): void
    {
        if (false === $this->allowModify) {
            throw new \LogicException('Modifying parameters is not permitted');
        }
        $this->set($offset, $value);
    }

    /**
     * Disallow unsetting params in the data bag via array access.
     *
     * @param string $offset
     *
     * @throws \LogicException
     */
    public function offsetUnset($offset): void
    {
        if (false === $this->allowModify) {
            throw new \LogicException('Modifying parameters is not permitted');
        }
    }

    /**
     * IteratorAggregate for iterating over the object like an array.
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->params);
    }
}
