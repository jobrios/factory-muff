<?php

namespace League\FactoryMuffin;

use Exception;
use League\FactoryMuffin\Exception\DeleteMethodNotFoundException;
use League\FactoryMuffin\Exception\DeletingFailedException;
use League\FactoryMuffin\Exception\DirectoryNotFoundException;
use League\FactoryMuffin\Exception\NoDefinedFactoryException;
use League\FactoryMuffin\Exception\SaveFailedException;
use League\FactoryMuffin\Exception\SaveMethodNotFoundException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Class FactoryMuffin.
 *
 * @package League\FactoryMuffin
 * @author  Zizaco <zizaco@gmail.com>
 * @author  Scott Robertson <scottymeuk@gmail.com>
 * @author  Graham Campbell <graham@mineuk.com>
 * @license <https://github.com/thephpleague/factory-muffin/blob/master/LICENSE> MIT
 */
class FactoryMuffin
{
    /**
     * The array of factories.
     *
     * @type array
     */
    private $factories = array();

    /**
     * The array of objects we have created.
     *
     * @type array
     */
    private $saved = array();

    /**
     * This is the method used when saving objects.
     *
     * @type string
     */
    private $saveMethod = 'save';

    /**
     * This is the method used when deleting objects.
     *
     * @type string
     */
    private $deleteMethod = 'delete';

    /**
     * Set the method we use when saving objects.
     *
     * @param string $method
     *
     * @return void
     */
    public function setSaveMethod($method)
    {
        $this->saveMethod = $method;
    }

    /**
     * Set the method we use when deleting objects.
     *
     * @param string $method
     *
     * @return void
     */
    public function setDeleteMethod($method)
    {
        $this->deleteMethod = $method;
    }

    /**
     * Creates and saves in db an instance of the model.
     *
     * This object will be generated with mock attributes.
     *
     * @param string $model Model class name.
     * @param array  $attr  Model attributes.
     *
     * @throws \League\FactoryMuffin\Exception\SaveFailedException
     *
     * @return object
     */
    public function create($model, array $attr = array())
    {
        $obj = $this->make($model, $attr, true);

        if (!$this->save($obj)) {
            if (isset($obj->validationErrors) && $obj->validationErrors) {
                throw new SaveFailedException($model, $obj->validationErrors);
            }

            throw new SaveFailedException($model);
        }

        return $obj;
    }

    /**
     * Make an instance of the model.
     *
     * @param string $model Model class name.
     * @param array  $attr  Model attributes.
     * @param bool   $save  Are we saving an object, or just creating an instance?
     *
     * @return object
     */
    private function make($model, array $attr, $save)
    {
        // Get the factory attributes for that model
        $attr_array = $this->attributesFor($model, $attr, $save);

        // Create, set, save and return instance
        $obj = new $model();

        foreach ($attr_array as $attr => $value) {
            $obj->$attr = $value;
        }

        return $obj;
    }

    /**
     * Save our object to the db, and keep track of it.
     *
     * @param object $object The model instance.
     *
     * @throws \League\FactoryMuffin\Exception\SaveMethodNotFoundException
     *
     * @return mixed
     */
    public function save($object)
    {
        $method = $this->saveMethod;

        if (!method_exists($object, $method)) {
            throw new SaveMethodNotFoundException($object, $method);
        }

        $result = $object->$method();
        $this->saved[] = $object;

        return $result;
    }

    /**
     * Return an array of saved objects.
     *
     * @return object[]
     */
    public function saved()
    {
        return $this->saved;
    }

    /**
     * Call the delete method on any saved objects.
     *
     * @throws \League\FactoryMuffin\Exception\DeletingFailedException
     * @throws \League\FactoryMuffin\Exception\DeleteMethodNotFoundException
     *
     * @return void
     */
    public function deleteSaved()
    {
        $exceptions = array();
        $method = $this->deleteMethod;
        foreach ($this->saved() as $saved) {
            try {
                if (!method_exists($saved, $method)) {
                    throw new DeleteMethodNotFoundException($saved, $method);
                }

                $saved->$method();
            } catch (Exception $e) {
                $exceptions[] = $e;
            }
        }

        $this->saved = array();

        if ($exceptions) {
            throw new DeletingFailedException($exceptions);
        }
    }

    /**
     * Return an instance of the model.
     *
     * This does not save it in the database. Use the create method to create
     * and save to the db, or pass the object from this method into the save method.
     *
     * @param string $model Model class name.
     * @param array  $attr  Model attributes.
     *
     * @return object
     */
    public function instance($model, array $attr = array())
    {
        return $this->make($model, $attr, false);
    }

    /**
     * Returns the mock attributes for the model.
     *
     * @param string $model Model class name.
     * @param array  $attr  Model attributes.
     * @param bool   $save  Are we saving an object, or just creating an instance?
     *
     * @return array
     */
    public function attributesFor($model, array $attr = array(), $save = false)
    {
        $factory_attrs = $this->getFactoryAttrs($model);

        // Prepare attributes
        foreach ($factory_attrs as $key => $kind) {
            if (!isset($attr[$key])) {
                $attr[$key] = $this->generateAttr($kind, $model, $save);
            }
        }

        return $attr;
    }

    /**
     * Get factory attributes.
     *
     * @param string $model Model class name.
     *
     * @throws \League\FactoryMuffin\Exception\NoDefinedFactoryException
     *
     * @return array
     */
    private function getFactoryAttrs($model)
    {
        if (isset($this->factories[$model])) {
            return $this->factories[$model];
        }

        throw new NoDefinedFactoryException($model);
    }

    /**
     * Define a new model factory.
     *
     * @param string $model      Model class name.
     * @param array  $definition Array with definition of attributes.
     *
     * @return void
     */
    public function define($model, array $definition = array())
    {
        $this->factories[$model] = $definition;
    }

    /**
     * Generate the attributes.
     *
     * This method will return a string, or an instance of the model.
     *
     * @param string $kind  The kind of attribute that will be generated.
     * @param string $model The name of the model class.
     * @param bool   $save  Are we saving an object, or just creating an instance?
     *
     * @return string|object
     */
    public function generateAttr($kind, $model = null, $save = false)
    {
        $kind = Kind::detect($kind, $model, $save);

        return $kind->generate();
    }

    /**
     * Load the specified factories.
     *
     * This method expects either a single path to a directory containing php
     * files, or an array of directory paths, and will include_once every file.
     * These files should contain factory definitions for your models.
     *
     * @param string|string[] $paths
     *
     * @throws \League\FactoryMuffin\Exception\DirectoryNotFoundException
     *
     * @return void
     */
    public function loadFactories($paths)
    {
        foreach ((array) $paths as $path) {
            if (!is_dir($path)) {
                throw new DirectoryNotFoundException($path);
            }

            $this->loadDirectory($path);
        }
    }

    /**
     * Load all the files in a directory.
     *
     * @param string $path
     *
     * @return void
     */
    private function loadDirectory($path)
    {
        $directory = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($directory);
        $files = new RegexIterator($iterator, '/^.+\.php$/i');

        foreach ($files as $file) {
            include_once $file->getPathName();
        }
    }
}
