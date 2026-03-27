<?php

declare(strict_types=1);

namespace Puwit\Auth;

class TokenContext
{
    public function __construct(
        public readonly string $type,
        public readonly string|int|null $id,
        public readonly array $scopes,
    ) {}

    public function hasScope(string $scope): bool
    {
        if (in_array('admin', $this->scopes, true)) {
            return true;
        }
        if ($scope === 'read' && in_array('write', $this->scopes, true)) {
            return true;
        }
        return in_array($scope, $this->scopes, true);
    }
}
