<?php

namespace Coda\Cms\QueryBuilder;

use Coda\ExpressionKit\Contracts\ExpressionResolver;
use Coda\ExpressionKit\ResolvedExpressionReference;
use Symfony\Component\ExpressionLanguage\Node\GetAttrNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\Node;

class WhitelistFieldResolver implements ExpressionResolver
{
    public function __construct(
        private array $whitelist,
        private bool $hasValue = false,
    ) {}

    public function names(): array
    {
        $names = $this->whitelist['fields'] ?? [];

        foreach (array_keys($this->whitelist['relationships'] ?? []) as $relationship) {
            $names[] = $relationship;
        }

        if ($this->hasValue) {
            $names[] = 'value';
        }

        return $names;
    }

    public function resolve(Node $node): ResolvedExpressionReference
    {
        if ($node instanceof NameNode) {
            $name = $node->attributes['name'];
            $allowedFields = $this->whitelist['fields'] ?? [];

            if (! in_array($name, $allowedFields, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Field "%s" is not allowed for filtering. Allowed fields: %s',
                    $name,
                    implode(', ', $allowedFields)
                ));
            }

            return ResolvedExpressionReference::field($name);
        }

        if ($node instanceof GetAttrNode) {
            return $this->resolveRelationshipField($node);
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported node type "%s" on left side of comparison. Expected field name or relationship.field.',
            get_class($node)
        ));
    }

    private function resolveRelationshipField(GetAttrNode $node): ResolvedExpressionReference
    {
        $baseNode = $node->nodes['node'];
        $attrNode = $node->nodes['attribute'];

        if (! $baseNode instanceof NameNode) {
            throw new \InvalidArgumentException(
                'Only single-level relationship traversal (e.g., "author.name") is supported. Nested relationships are not yet implemented.'
            );
        }

        $relationship = $baseNode->attributes['name'];
        $column = $attrNode->attributes['value'];

        $relationships = $this->whitelist['relationships'] ?? [];

        if (! array_key_exists($relationship, $relationships)) {
            throw new \InvalidArgumentException(sprintf(
                'Relationship "%s" is not allowed for filtering. Allowed relationships: %s',
                $relationship,
                implode(', ', array_keys($relationships))
            ));
        }

        if (! in_array($column, $relationships[$relationship], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Column "%s" is not allowed on relationship "%s". Allowed columns: %s',
                $column,
                $relationship,
                implode(', ', $relationships[$relationship])
            ));
        }

        return ResolvedExpressionReference::relationship($relationship, $column);
    }
}
