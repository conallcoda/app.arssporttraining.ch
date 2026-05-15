<?php

namespace Coda\Cms\QueryBuilder;

use Symfony\Component\ExpressionLanguage\Node\GetAttrNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\Node;

class WhitelistFieldResolver implements FieldResolver
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

    /** @return array{type: string, column: string, relationship?: string} */
    public function resolve(Node $node): array
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

            return ['type' => 'field', 'column' => $name];
        }

        if ($node instanceof GetAttrNode) {
            return $this->resolveRelationshipField($node);
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported node type "%s" on left side of comparison. Expected field name or relationship.field.',
            get_class($node)
        ));
    }

    /** @return array{type: string, column: string, relationship: string} */
    private function resolveRelationshipField(GetAttrNode $node): array
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

        return [
            'type' => 'relationship',
            'relationship' => $relationship,
            'column' => $column,
        ];
    }
}
