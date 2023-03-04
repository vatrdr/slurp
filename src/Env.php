<?php

declare(strict_types=1);

namespace Vatradar\Slurp;

use RuntimeException;

class Env
{
    public static function get(string $var): string
    {
        if (!array_key_exists($var, $_ENV)) {
            throw new RuntimeException("Environment var does not exist");
        }

        return $_ENV[$var];
    }
}
