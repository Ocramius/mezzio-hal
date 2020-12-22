<?php

/**
 * @see       https://github.com/mezzio/mezzio-hal for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-hal/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-hal/blob/master/LICENSE.md New BSD License
 */

namespace MezzioTest\Hal\TestAsset;

use Mezzio\Hal\Metadata\AbstractMetadata;
use stdClass;

class TestMetadata extends AbstractMetadata
{
    public function getClass(): string
    {
        return stdClass::class;
    }
}
