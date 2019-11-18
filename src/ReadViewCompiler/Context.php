<?php namespace Ewll\CrudBundle\ReadViewCompiler;

class Context
{
    private $items;
    private $parameters = [];

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->parameters) ? $this->parameters[$key] : $default;
    }

    public function set($key, $value): void
    {
        $this->parameters[$key] = $value;
    }

    public function has($key): bool
    {
        return array_key_exists($key, $this->parameters);
    }
}
