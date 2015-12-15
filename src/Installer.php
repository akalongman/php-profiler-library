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
use Composer\Script\Event;
use Composer\Installer\PackageEvent;

abstract class Installer
{



    public static function postUpdate(Event $event)
    {
        echo 'postUpdate';

    }

    public static function postPackageInstall(PackageEvent $event)
    {
        echo 'postPackageInstall';


    }



}
