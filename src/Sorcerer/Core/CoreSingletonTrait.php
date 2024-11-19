<?php

/**
 * Core Singleton Trait
 * 
 * Defines the base methods, attributes, and CONSTANTS needed for a 
 *      Class to utilize the Singleton Design Pattern.
 *
 * @package     Sorcerer\Core\CoreSingletonTrait
 * @author      Eric Harris <ericktheredd5875@gmail.com>
 * @copyright   2024 - Eric Harris
 * @version     x.x.x - Comments or Notes
 * @deprecated  x.x.x - Comments or Notes
 */

/*----------  Class Namespacing  ----------*/
namespace Sorcerer\Core;

/*==================================================
=            Classes Used by this Class            =
==================================================*/

/*=====  End of Classes Used by this Class  ======*/

trait CoreSingletonTrait
{
    /*========================================
    =            Trait Attributes            =
    ========================================*/
    protected static $class_name = "";
    protected static $instance   = "";
    /*=====  End of Trait Attributes  ======*/

    /**
     * * Locking down the 'construct' method.
     */
    protected function __construct() { }

    /**
     * * Disabling the 'clone' method within the Singleton.
     */
    final private function __clone() { }

    /**
     * * Disabling the 'wakeup' method within the Singleton.
     */
    final private function __wakeup() { }

    final public static function reset(): self
    {
        self::$instance = new static();
        return self::$instance; 
    }

    /*===================================
    =            Get Methods            =
    ===================================*/
    /**
     * Maintains a single instance of itself.
     * 
     * @return      self    Returns an instance of the class object.
     */
    final public static function getInstance(): self
    {
        $class_name = static::class;
        if (!(self::$instance instanceof $class_name)) {
            self::$instance     = new static();
            self::$class_name   = $class_name;
        }

        return self::$instance;
    }

    /**
     * Provides the name of the Class
     * 
     * @return  string   Returns the name of this class.
     */
    final public static function getClassName(): string
    {
        return static::class;
    }
    /*=====  End of Get Methods  ======*/
    

    /*===================================
    =            Set Methods            =
    ===================================*/
    final public static function setInstance($instance): self
    {
        self::$instance = $instance;
        return self::$instance;
    }
    /*=====  End of Set Methods  ======*/    
}
/* End of file CoreSingletonTrait.php */
/* Location: /Sorcerer/Core/CoreSingletonTrait.php */