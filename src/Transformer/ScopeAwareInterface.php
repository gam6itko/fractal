<?php

declare(strict_types=1);

namespace League\Fractal\Transformer;

use League\Fractal\Scope;

interface ScopeAwareInterface
{
    public function getCurrentScope(): ?Scope;

    public function setCurrentScope(Scope $currentScope): self;
}
