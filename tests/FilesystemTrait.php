<?php

namespace OpenFoodFactsTests;

trait FilesystemTrait
{
    public static function recursiveDeleteDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir) ?: [];
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        self::recursiveDeleteDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
    public static function cleanTestFolder(): void
    {
        self::recursiveDeleteDirectory('tests/tmp');
        mkdir('tests/tmp');
        mkdir('tests/tmp/cache');
    }
}
