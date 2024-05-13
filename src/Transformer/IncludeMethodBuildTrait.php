<?php

declare(strict_types=1);

namespace League\Fractal\Transformer;

trait IncludeMethodBuildTrait
{
    /**
     * @var array<string, callable>
     */
    private array $includeMethodCache = [];

    private function buildMethodName(string $includeName): string
    {
        if (!isset($this->includeMethodCache[$includeName])) {
            // Check if the method name actually exists
            $methodName = 'include' . str_replace(
                ' ',
                '',
                ucwords(str_replace(
                    '_',
                    ' ',
                    str_replace(
                        '-',
                        ' ',
                        $includeName
                    )
                ))
            );

            $this->includeMethodCache[$includeName] = $methodName;
        }

        return $this->includeMethodCache[$includeName];
    }
}
