<?php

namespace OpenFoodFactsTests\Legacy\Helper;

trait FilesystemTrait
{
    public function recursiveDeleteDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir) ?: [];
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->recursiveDeleteDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
