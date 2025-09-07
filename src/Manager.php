<?php

/*
 * This file is part of the League\Fractal package.
 *
 * (c) Phil Sturgeon <me@philsturgeon.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Fractal;

use League\Fractal\Resource\ResourceInterface;
use League\Fractal\Serializer\DataArraySerializer;
use League\Fractal\Serializer\Serializer;

/**
 * Manager
 *
 * Not a wildly creative name, but the manager is what a Fractal user will interact
 * with the most. The manager has various configurable options, and allows users
 * to create the "root scope" easily.
 */
class Manager implements ManagerInterface
{
    /**
     * Array of scope identifiers for resources to include.
     */
    protected array $requestedIncludes = [];

    /**
     * Array of scope identifiers for resources to exclude.
     */
    protected array $requestedExcludes = [];

    /**
     * Array of requested fieldsets.
     */
    protected array $requestedFieldsets = [];

    /**
     * Array containing modifiers as keys and an array value of params.
     *
     * @var array<string, ParamBag>
     */
    protected array $includeParams = [];

    /**
     * The character used to separate modifier parameters.
     *
     * @deprecated Do not pass params as string. Use setIncludeParams() instead.
     */
    protected string $paramDelimiter = '|';

    /**
     * Upper limit to how many levels of included data are allowed.
     */
    protected int $recursionLimit = 10;

    protected ?Serializer $serializer = null;

    /**
     * Factory used to create new configured scopes.
     */
    private ScopeFactoryInterface $scopeFactory;

    /**
     * @var ParamBag For cases then transformer wants to store some data for later use.
     */
    protected ParamBag $artifacts;

    public function __construct(
        ScopeFactoryInterface     $scopeFactory = null,
        private readonly ParamBag $globalParamBag = new ParamBag([]),
    )
    {
        $this->scopeFactory = $scopeFactory ?: new ScopeFactory();
        $this->artifacts = new ParamBag([], true);
    }

    /**
     * Main method to kick this all off. Make a resource then pass it over, and use toArray()
     */
    public function createData(
        ResourceInterface $resource,
        ?string           $scopeIdentifier = null,
        ScopeInterface    $parentScopeInstance = null,
    ): ScopeInterface
    {
        if ($parentScopeInstance !== null) {
            return $this->scopeFactory->createChildScopeFor($this, $parentScopeInstance, $resource, $scopeIdentifier);
        }

        return $this->scopeFactory->createScopeFor($this, $resource, $scopeIdentifier);
    }

    public function getIncludeParams(string $include): ParamBag
    {
        $params = $this->includeParams[$include] ?? null;
        if ($params === null) {
            return $this->globalParamBag;
        }

        return $params;
    }

    public function setIncludeParams(string $includeName, array $params): self
    {
        $this->includeParams[$includeName] = new ParamBag($params, false);
        return $this;
    }

    public function artifacts(): ParamBag
    {
        return $this->artifacts;
    }

    public function getRequestedIncludes(): array
    {
        return $this->requestedIncludes;
    }

    public function getRequestedExcludes(): array
    {
        return $this->requestedExcludes;
    }

    public function getSerializer(): Serializer
    {
        if (!$this->serializer) {
            $this->serializer = new DataArraySerializer();
        }

        return $this->serializer;
    }

    /**
     * @param array $includes Array or csv string of resources to include
     *
     * Include string example
     *
     *    "foo"
     *    "foo:bar"
     *    "foo:bar(baz|qux)" - Include "bar" has params "baz" and "qux".
     *    foo:bar(baz|qux).baz - Include "bar" has params "baz" and "qux", and include "baz".
     */
    public function parseIncludes(array $includes): self
    {
        // Wipe these before we go again
        $this->requestedIncludes = $this->includeParams = [];

        foreach ($includes as $include) {
            [$includeName, $allModifiersStr] = array_pad(explode(':', $include, 2), 2, '');
            $a = $allModifiersStr ? explode('.', $allModifiersStr, 2) : [''];
            [$allModifiersStr, $subRelations] = array_pad($a, 2, null);

            // Trim it down to a cool level of recursion
            $includeName = $this->trimToAcceptableRecursionLevel($includeName);

            if (\in_array($includeName, $this->requestedIncludes)) {
                continue;
            }
            $this->requestedIncludes[] = $includeName;

            // No Params? Bored
            if ($allModifiersStr === null) {
                continue;
            }

            // Matches multiple instances of 'something(foo|bar|baz)' in the string
            // I guess it ignores : so you could use anything, but probably don't do that
            preg_match_all('/([\w]+)(\(([^\)]+)\))?/', $allModifiersStr, $allModifiersArr);

            // [0] is full matched strings...
            $modifierCount = \count($allModifiersArr[0]);

            $modifierArr = [];

            for ($modifierIt = 0; $modifierIt < $modifierCount; $modifierIt++) {
                // [1] is the modifier
                $modifierName = $allModifiersArr[1][$modifierIt];

                // and [3] is delimited params
                $modifierParamStr = $allModifiersArr[3][$modifierIt];

                // Make modifier array key with an array of params as the value
                $modifierArr[$modifierName] = explode($this->paramDelimiter, $modifierParamStr);
            }

            $this->includeParams[$includeName] = new ParamBag($modifierArr);

            if ($subRelations) {
                $this->requestedIncludes[] = $this->trimToAcceptableRecursionLevel($includeName . '.' . $subRelations);
            }
        }

        // This should be optional and public someday, but without it includes would never show up
        $this->autoIncludeParents();

        return $this;
    }

    /**
     * Parse field parameter.
     *
     * @param array $fieldsets Array of fields to include. It must be an array whose keys
     *                         are resource types and values an array or a string
     *                         of the fields to return, separated by a comma
     */
    public function parseFieldsets(array $fieldsets): self
    {
        $this->requestedFieldsets = [];
        foreach ($fieldsets as $type => $fields) {
            if (\is_string($fields)) {
                $fields = explode(',', $fields);
            }

            //Remove empty and repeated fields
            $this->requestedFieldsets[$type] = array_unique(array_filter($fields));
        }
        return $this;
    }

    public function getRequestedFieldsets(): array
    {
        return $this->requestedFieldsets;
    }

    /**
     * Get fieldset params for the specified type.
     */
    public function getFieldset(string $type): ?ParamBag
    {
        return !isset($this->requestedFieldsets[$type]) ?
            null :
            new ParamBag($this->requestedFieldsets[$type]);
    }

    /**
     * @param array $excludes Array or csv string of resources to exclude
     */
    public function parseExcludes(array $excludes): self
    {
        $this->requestedExcludes = [];

        foreach ($excludes as $excludeName) {
            $excludeName = $this->trimToAcceptableRecursionLevel($excludeName);

            if (\in_array($excludeName, $this->requestedExcludes)) {
                continue;
            }

            $this->requestedExcludes[] = $excludeName;
        }

        return $this;
    }

    public function setRecursionLimit(int $recursionLimit): self
    {
        $this->recursionLimit = $recursionLimit;

        return $this;
    }

    public function setSerializer(Serializer $serializer): self
    {
        $this->serializer = $serializer;

        return $this;
    }

    /**
     * Look at the requested includes and automatically include the parents if they
     * are not explicitly requested. E.g: [foo, bar.baz] becomes [foo, bar, bar.baz]
     *
     * @internal
     */
    protected function autoIncludeParents(): void
    {
        $parsed = [];

        foreach ($this->requestedIncludes as $include) {
            $nested = explode('.', $include);

            $part = array_shift($nested);
            $parsed[] = $part;

            while (\count($nested) > 0) {
                $part .= '.' . array_shift($nested);
                $parsed[] = $part;
            }
        }

        $this->requestedIncludes = array_values(array_unique($parsed));
    }

    /**
     * Strip off any requested resources that are too many levels deep, to avoid DiCaprio being chased
     * by trains or whatever the hell that movie was about.
     *
     * @internal
     */
    protected function trimToAcceptableRecursionLevel(string $includeName): string
    {
        return implode('.', \array_slice(explode('.', $includeName), 0, $this->recursionLimit));
    }
}
