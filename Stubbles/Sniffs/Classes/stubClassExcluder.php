<?php
/**
 * Class for easy class name excluding
 *
 * @author   Richard Sternagel <richard.sternagel@1und1.de>
 * @package  Classes
 */
/**
 * Class for easy class name excluding
 *
 * @package  Classes
 */
class stubClassExcluder
{
    /**
     * exclude class patterns
     *
     * @var  array<string>
     */
    protected static $classPatterns = array('/^Binford$/',
                                            '/ClassLoader$/',
                                            '/ClassNotFoundException$/',
                                            '/TestSuite$/',
                                            '/^Stubbles_Sniffs/'
                                       );

    /**
     * getter method
     *
     * @return  array<string>
     */
    public static function getPatterns()
    {
        return self::$classPatterns;
    }

    /**
     * getter method
     *
     * @param   string         $className
     * @return  array<string>
     */
    public static function isExcluded($className)
    {
        foreach (self::$classPatterns as $pattern) {
             if (preg_match($pattern, $className) === 1)  return true;
        }
        return false;
    }
}
?>