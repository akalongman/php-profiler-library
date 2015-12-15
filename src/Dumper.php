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

use Symfony\Component\VarDumper\Dumper\HtmlDumper;

/**
 * @package    ProfilerLibrary
 * @author     Avtandil Kikabidze <akalongman@gmail.com>
 * @copyright  Avtandil Kikabidze <akalongman@gmail.com>
 * @license    http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 * @link       http://www.github.com/akalongman/php-profiler-library
 */


class Dumper extends HtmlDumper
{
    /**
     * Colour definitions for output.
     *
     * @var array
     */
    protected $styles = [
        'default' => 'background-color:#fff; color:#222; line-height:1.2em; font-weight:normal; font:14px Menlo, Monaco, Consolas, monospace; word-wrap: break-word; white-space: pre-wrap; position:relative; z-index:100000',
        'num' => 'color:#a71d5d',
        'const' => 'color:#795da3',
        'str' => 'color:#df5000',
        'cchr' => 'color:#222',
        'note' => 'color:#a71d5d',
        'ref' => 'color:#a0a0a0',
        'public' => 'color:#795da3',
        'protected' => 'color:#795da3',
        'private' => 'color:#795da3',
        'meta' => 'color:#b729d9',
        'key' => 'color:#df5000',
        'index' => 'color:#a71d5d',
    ];

    public static function highlight($string)
    {
        $result = highlight_string("<?php\n" . $string, true);
        $result = preg_replace('/&lt;\\?php<br \\/>/', '', $result, 1);
        return $result;
    }


}
