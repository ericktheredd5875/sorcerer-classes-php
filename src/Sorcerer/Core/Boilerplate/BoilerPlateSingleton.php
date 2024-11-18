<?php
/**
 * Singleton :: Boiler-Plate
 * 
 * Boiler-Plate definition for a Singleton Class
 *
 * @package     Sorcerer\Core\BoilerPlate\Singleton
 * @author      Eric Harris <ericktheredd5875@gmail.com>
 * @copyright   2024 - Eric Harris
 * @version     x.x.x - Comments or Notes
 * @deprecated  x.x.x - Comments or Notes
 */

/*----------  Class Namespacing  ----------*/

namespace Sorcerer\Core\BoilerPlate;

/*==================================================
=            Classes Used by this Class            =
==================================================*/

/*=====  End of Classes Used by this Class  ======*/

class Singleton
{
    /*========================================
    =            Class Attributes            =
    ========================================*/
    private static $class_name = "";
    private static $instance   = "";
    /*=====  End of Class Attributes  ======*/

    /**
     * * 'construct' can only be accessed and used with the 
     * *    static 'getInstance' method.
     */
    protected function __construct()
    {
        /* Initialize the Class */
        // Handle setting default property values within the 'construct'

        return $this;
    }

    /*===================================
    =            Set Methods            =
    ===================================*/

    /*=====  End of Set Methods  ======*/

    /*===================================
    =            Get Methods            =
    ===================================*/

    /*=====  End of Get Methods  ======*/

    /*=======================================
    =            Class Destroyer            =
    =======================================*/
    /**
     * Destruction of the Class
     */
    public function __destruct()
    {
    }
    /*=====  End of Class Destroyer  ======*/

    /*=========================================
    =            Singleton Methods            =
    =========================================*/

    /**
     * * Disabling the 'clone' magic method.
     */
    private function __clone()
    {
    }

    /**
     * * Disabling the 'wakeup' magic method.
     */
    private function __wakeup()
    {
    }

    /**
     * Maintains a single instance of itself.
     * 
     * @return      self    Returns an instance of the class object.
     */
    public static function getInstance(): self
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
    public static function getClassName(): string
    {
        return static::class;
    }
    
    public static function setInstance($instance): self
    {
        self::$instance = $instance;
        return self::$instance;
    }

    /**
     * Resets or Creates a NEW instance of itself, when necessary.
     *
     * @return self
     */
    public static function reset(): self
    {
        self::$instance = new static();
        return self::$instance;
    }
    /*=====  End of Singleton Methods  ======*/
}
/* End of file Singleton.php */
/* Location: /Sorcerer/Core/BoilerPlate/Singleton.php */