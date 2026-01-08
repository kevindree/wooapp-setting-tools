<?php

/**
 * @package WooApp\Core
 */

namespace WooApp\Core;

defined('ABSPATH') || exit;

/**
 * Service Container
 * Simple dependency injection container
 */
class Container
{
    /**
     * Registered services
     * @var array
     */
    private $services = array();

    /**
     * Service instances
     * @var array
     */
    private $instances = array();

    /**
     * Register a service
     * @param string $name Service name
     * @param string|callable $definition Service class or factory
     */
    public function register($name, $definition)
    {
        $this->services[$name] = $definition;
    }

    /**
     * Resolve a service
     * @param string $name Service name
     * @return object Service instance
     */
    public function resolve($name)
    {
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        if (!isset($this->services[$name])) {
            throw new \Exception("Service '{$name}' not found in container.");
        }

        $definition = $this->services[$name];

        // If it's a callable, call it
        if (is_callable($definition)) {
            $instance = call_user_func($definition, $this);
        } else {
            // Otherwise, instantiate the class
            $instance = new $definition();
        }

        $this->instances[$name] = $instance;
        return $instance;
    }

    /**
     * Boot all registered services
     */
    public function boot()
    {
        foreach (array_keys($this->services) as $name) {
            $service = $this->resolve($name);
            if (method_exists($service, 'boot')) {
                $service->boot();
            }
        }
    }

    /**
     * Get all registered services
     * @return array
     */
    public function getServices()
    {
        return $this->services;
    }
}
