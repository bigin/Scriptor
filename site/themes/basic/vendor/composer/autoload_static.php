<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticIniteb8a9bb60e09be1d72e7b91286469415
{
    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Scriptor\\BasicRouter' => __DIR__ . '/../..' . '/lib/BasicRouter.php',
        'Scriptor\\BasicTheme' => __DIR__ . '/../..' . '/lib/Basic.php',
        'Scriptor\\Connector' => __DIR__ . '/../..' . '/lib/subscriber/Connector.php',
        'Scriptor\\MailChimp' => __DIR__ . '/../..' . '/lib/subscriber/MailChimp.php',
        'Scriptor\\Request' => __DIR__ . '/../..' . '/lib/subscriber/Request.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->classMap = ComposerStaticIniteb8a9bb60e09be1d72e7b91286469415::$classMap;

        }, null, ClassLoader::class);
    }
}