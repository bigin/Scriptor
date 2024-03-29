<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit73d792698219df48aa64361d741f71dd
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'PhpParser\\' => 10,
        ),
        'B' => 
        array (
            'Brick\\VarExporter\\' => 18,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'PhpParser\\' => 
        array (
            0 => __DIR__ . '/..' . '/nikic/php-parser/lib/PhpParser',
        ),
        'Brick\\VarExporter\\' => 
        array (
            0 => __DIR__ . '/..' . '/brick/varexporter/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit73d792698219df48aa64361d741f71dd::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit73d792698219df48aa64361d741f71dd::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit73d792698219df48aa64361d741f71dd::$classMap;

        }, null, ClassLoader::class);
    }
}
