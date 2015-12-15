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

use Composer\Installer\PackageEvent;
use Symfony\Component\Filesystem\Filesystem;

abstract class Installer
{

    public static function postUpdate(PackageEvent $event)
    {
        self::copyResources($event);

    }

    public static function postInstall(PackageEvent $event)
    {
        self::copyResources($event);

    }

    public static function preUninstall(PackageEvent $event)
    {
        self::deleteResources($event);

    }

    public static function copyResources(PackageEvent $event)
    {
        $packagePath = self::getPackagePath($event);
        if (!$packagePath) {
            return false;
        }

        $rootPath = self::getRootPath($event);

        $status = self::copyController($packagePath, $rootPath);
    }

    public static function deleteResources(PackageEvent $event)
    {
        $packagePath = self::getPackagePath($event);
        if (!$packagePath) {
            return false;
        }

        $rootPath = self::getRootPath($event);

        $status = self::deleteController($packagePath, $rootPath);
    }



    private static function getPackagePath($event)
    {
        $package = $event->getOperation()->getPackage();
        $name = $package->getName();
        if ($name !== 'longman/profiler-library') {
            return false;
        }
        $installationManager = $event->getComposer()->getInstallationManager();
        $originDir = $installationManager->getInstallPath($package);
        return $originDir;
    }

    private static function copyController($packagePath, $rootPath)
    {
        return copy($packagePath.'/src/Packages/itdcms/debug1.php', $rootPath.'/core/controllers/itdc/debug1.php');
    }

    private static function deleteController($rootPath)
    {
        return unlink($rootPath.'/core/controllers/itdc/debug1.php');
    }



    private static function getRootPath(PackageEvent $event)
    {
        $vendorPath = $event->getComposer()->getConfig()->get('vendor-dir');
        return realpath($vendorPath.'/..');
    }

}
