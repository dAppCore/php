<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Actions;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Scans directories for Action classes with the #[Scheduled] attribute.
 *
 * Unlike ModuleScanner (which scans Boot.php files), this scanner finds
 * any PHP class with the #[Scheduled] attribute in the given directories.
 *
 * It uses PHP's native reflection to read attributes — no file parsing.
 *
 * @see Scheduled The attribute this scanner discovers
 * @see \Core\ModuleScanner Similar pattern for Boot.php discovery
 */
class ScheduledActionScanner
{
    /**
     * Scan directories for classes with #[Scheduled] attribute.
     *
     * @param  array<string>  $paths  Directories to scan recursively
     * @return array<class-string, Scheduled>  Map of class name to attribute instance
     */
    public function scan(array $paths): array
    {
        $results = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class = $this->classFromFile($file->getPathname());

                if ($class === null || ! class_exists($class)) {
                    continue;
                }

                $attribute = $this->extractScheduled($class);

                if ($attribute !== null) {
                    $results[$class] = $attribute;
                }
            }
        }

        return $results;
    }

    /**
     * Extract the #[Scheduled] attribute from a class.
     */
    private function extractScheduled(string $class): ?Scheduled
    {
        try {
            $ref = new ReflectionClass($class);
            $attrs = $ref->getAttributes(Scheduled::class);

            if (empty($attrs)) {
                return null;
            }

            return $attrs[0]->newInstance();
        } catch (\ReflectionException) {
            return null;
        }
    }

    /**
     * Derive fully qualified class name from a PHP file.
     *
     * Reads the file's namespace declaration and class name.
     */
    private function classFromFile(string $file): ?string
    {
        $contents = file_get_contents($file);

        if ($contents === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        foreach (token_get_all($contents) as $token) {
            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->extractNamespace($contents);
            }

            if ($token[0] === T_CLASS) {
                $class = $this->extractClassName($contents);
                break;
            }
        }

        if ($class === null) {
            return null;
        }

        return $namespace !== null ? "{$namespace}\\{$class}" : $class;
    }

    /**
     * Extract the namespace string from file contents.
     */
    private function extractNamespace(string $contents): ?string
    {
        $tokens = token_get_all($contents);
        $capture = false;
        $parts = [];

        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $capture = true;

                continue;
            }

            if ($capture) {
                if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_STRING, T_NS_SEPARATOR], true)) {
                    $parts[] = $token[1];
                } elseif ($token === ';' || $token === '{') {
                    break;
                }
            }
        }

        return ! empty($parts) ? implode('', $parts) : null;
    }

    /**
     * Extract class name from tokens after T_CLASS.
     */
    private function extractClassName(string $contents): ?string
    {
        $tokens = token_get_all($contents);
        $nextIsClass = false;

        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_CLASS) {
                $nextIsClass = true;

                continue;
            }

            if ($nextIsClass && is_array($token)) {
                if ($token[0] === T_WHITESPACE) {
                    continue;
                }
                if ($token[0] === T_STRING) {
                    return $token[1];
                }

                return null;
            }
        }

        return null;
    }
}
