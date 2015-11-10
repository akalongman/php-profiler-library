<?php
/*
 * This file is part of the ProfilerLibrary package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Longman\ProfilerLibrary;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @package    ProfilerLibrary
 * @author     Avtandil Kikabidze <akalongman@gmail.com>
 * @copyright  Avtandil Kikabidze <akalongman@gmail.com>
 * @license    http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 * @link       http://www.github.com/akalongman/php-profiler-library
 */



/**
 * Dumper is intended to replace the buggy PHP function var_dump and print_r.
 * It can correctly identify the recursively referenced objects in a complex
 * object structure. It also has a recursive depth control to avoid indefinite
 * recursive display of some peculiar variables.
 *
 * Dumper can be used as follows,
 *
 * ~~~
 * Dumper::dump($var);
 * ~~~
 */
class Dumper
{
    private static $_objects;
    private static $_output;
    private static $_depth;
    private static $_type;

    /**
     * Displays a variable.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as Yii controllers.
     * @param mixed   $var       variable to be dumped
     * @param integer $depth     maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param boolean $highlight whether the result should be syntax-highlighted
     */
    public static function dump($var, $depth = 10, $highlight = false)
    {
        echo self::getDump($var, $depth, $highlight);
    }


    /**
     * Returns a dump data.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as Yii controllers.
     * @param mixed   $var       variable to be dumped
     * @param integer $depth     maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param boolean $highlight whether the result should be syntax-highlighted
     */
    public static function getDump($var, $depth = 10, $highlight = false)
    {
        return static::dumpAsString($var, $depth, $highlight);
    }



    /**
     * Dumps a variable in terms of a string.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as Yii controllers.
     * @param mixed   $var       variable to be dumped
     * @param integer $depth     maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param boolean $highlight whether the result should be syntax-highlighted
     * @return string the string representation of the variable
     */
    public static function dumpAsString($var, $depth = 10, $highlight = false)
    {
        self::$_output = '';
        self::$_objects = array();
        self::$_depth = !empty($depth) ? $depth : 10;
        self::dumpInternal($var, 0);
        if ($highlight) {
            // replace comments with tokens
            $array_replaces = array('/*', '//');
            $array_tokens = array('|***|***|', '|**|**|');

            self::$_output = str_replace($array_replaces, $array_tokens, self::$_output);
            self::$_output = self::highlight(self::$_output);
            self::$_output = str_replace($array_tokens, $array_replaces, self::$_output);
        }
        return self::$_output;
    }


    public static function highlight($string)
    {
        $result = highlight_string("<?php\n" . $string, true);
        $result = preg_replace('/&lt;\\?php<br \\/>/', '', $result, 1);
        return $result;
    }

    /**
     * @param mixed   $var   variable to be dumped
     * @param integer $level depth level
     */
    private static function dumpInternal($var, $level)
    {
        $type = gettype($var);
        self::$_type = $type;
        switch ($type) {
        case 'boolean':
            self::$_output .= $var ? 'true' : 'false';
            break;
        case 'integer':
            self::$_output .= "$var";
            break;
        case 'double':
            self::$_output .= "$var";
            break;
        case 'string':
            //self::$_output .= "'" . addslashes($var) . "'";
            self::$_output .= $var;
            break;
        case 'resource':
            self::$_output .= '{resource}';
            break;
        case 'NULL':
            self::$_output .= "null";
            break;
        case 'unknown type':
            self::$_output .= '{unknown}';
            break;
        case 'array':
            if (self::$_depth <= $level) {
                self::$_output .= '[...]';
            } elseif (empty($var)) {
                self::$_output .= '[]';
            } else {
                $keys = array_keys($var);
                $spaces = str_repeat(' ', $level * 4);
                self::$_output .= '[';
                foreach ($keys as $key) {
                    self::$_output .= "\n" . $spaces . '    ';
                    self::dumpInternal($key, 0);
                    self::$_output .= ' => ';
                    self::dumpInternal($var[$key], $level + 1);
                }
                self::$_output .= "\n" . $spaces . ']';
            }
            break;
        case 'object':
            if (($id = array_search($var, self::$_objects, true)) !== false) {
                self::$_output .= get_class($var) . '#' . ($id + 1) . '(...)';
            } elseif (self::$_depth <= $level) {
                self::$_output .= get_class($var) . '(...)';
            } else {
                $id = array_push(self::$_objects, $var);
                $className = get_class($var);
                $spaces = str_repeat(' ', $level * 4);
                self::$_output .= "$className#$id\n" . $spaces . '(';
                if (method_exists($var, 'toArray')) {
                    $ar_var = $var->toArray();
                } else {
                    $ar_var = (array)$var;
                }

                foreach ($ar_var as $key => $value) {
                    $keyDisplay = strtr(trim($key), array("\0" => ':'));
                    self::$_output .= "\n" . $spaces . "    [$keyDisplay] => ";
                    self::dumpInternal($value, $level + 1);
                }
                self::$_output .= "\n" . $spaces . ')';
            }
            break;
        }
    }

    public static function getType()
    {
        return self::$_type;
    }

}
