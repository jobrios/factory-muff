<?php

namespace League\FactoryMuffin;

use Faker\Factory as Faker;

/**
 * Class Kind.
 *
 * @package League\FactoryMuffin
 * @author  Zizaco <zizaco@gmail.com>
 * @author  Scott Robertson <scottymeuk@gmail.com>
 * @author  Graham Campbell <graham@mineuk.com>
 * @license <https://github.com/thephpleague/factory-muffin/blob/master/LICENSE> MIT
 */
abstract class Kind
{
    /**
     * The Kind classes that are available.
     *
     * @type string[]
     */
    protected static $availableKinds = array(
        'call',
        'closure',
        'factory',
        'generic',
    );

    /**
     * The kind of attribute that will be generated.
     *
     * @type string
     */
    protected $kind;

    /**
     * The name of the model class.
     *
     * @type string
     */
    protected $model;

    /**
     * The faker factory or generator instance.
     *
     * @type \Faker\Factory|\Faker\Generator
     */
    protected $faker;

    /**
     * Have we saved the parent object, or just created an instance?
     *
     * @type boolean
     */
    protected $save;

    /**
     * Initialise our Kind.
     *
     * @param string                          $kind  The kind of attribute that will be generated.
     * @param string                          $model The name of the model class.
     * @param \Faker\Factory|\Faker\Generator $faker The faker factory or generator instance.
     * @param bool                            $save  Have we saved the parent object?
     */
    public function __construct($kind, $model, $faker, $save = false)
    {
        $this->kind = $kind;
        $this->model = $model;
        $this->faker = $faker;
        $this->save = $save;
    }

    /**
     * Detect the type of Kind we are processing.
     *
     * @param string $kind  The kind of attribute that will be generated.
     * @param string $model The name of the model class.
     * @param bool   $save  Have we saved the parent object?
     *
     * @return \League\FactoryMuffin\Kind
     */
    public static function detect($kind, $model = null, $save = false)
    {
        // TODO: Move this somewhere where its only instantiated once
        $faker = new Faker();

        if ($kind instanceof \Closure) {
            return new Kind\Closure($kind, $model, $faker, $save);
        }

        $class = '\\League\\FactoryMuffin\\Kind\\Generic';
        foreach (static::$availableKinds as $availableKind) {
            if (substr($kind, 0, strlen($availableKind)) === $availableKind) {
                $class = '\\League\\FactoryMuffin\\Kind\\' . ucfirst($availableKind);
                break;
            }
        }

        return new $class($kind, $model, $faker->create(), $save);

    }

    /**
     * Return an array of all options passed to the Kind (after |).
     *
     * @return array
     */
    public function getOptions()
    {
        $options = explode('|', $this->kind);
        array_shift($options);

        if (count($options) > 0) {
            $options = explode(';', $options[0]);
        }

        return $options;
    }

    /**
     * Returns the name of the kind supplied (exploding at |)
     *
     * @return string
     */
    public function getKind()
    {
        $kind = explode('|', $this->kind);
        return reset($kind);
    }

    /**
     * Generate, and return the attribute.
     *
     * @return mixed
     */
    abstract public function generate();
}
