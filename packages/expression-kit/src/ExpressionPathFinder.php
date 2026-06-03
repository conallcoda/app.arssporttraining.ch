<?php

namespace Coda\ExpressionKit;

use Symfony\Component\ExpressionLanguage\Node\ConstantNode;
use Symfony\Component\ExpressionLanguage\Node\GetAttrNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\ParsedExpression;

class ExpressionPathFinder
{
    /**
     * @return string[]
     */
    public function fromParsed(ParsedExpression $parsed): array
    {
        return $this->fromNode($parsed->getNodes());
    }

    /**
     * @return string[]
     */
    public function fromNode(Node $node): array
    {
        $paths = [];

        if ($node instanceof NameNode) {
            $paths[] = $node->attributes['name'];
        }

        if ($node instanceof GetAttrNode) {
            $path = $this->expressionPath($node);

            if ($path !== null) {
                $paths[] = $path;
            }
        }

        foreach ($node->nodes as $child) {
            if ($child instanceof Node) {
                $paths = [...$paths, ...$this->fromNode($child)];
            }
        }

        return array_values(array_unique($paths));
    }

    public function expressionPath(Node $node): ?string
    {
        if ($node instanceof NameNode) {
            return $node->attributes['name'];
        }

        if (! $node instanceof GetAttrNode || $node->attributes['type'] !== GetAttrNode::PROPERTY_CALL) {
            return null;
        }

        $left = $this->expressionPath($node->nodes['node']);
        $right = $node->nodes['attribute'];

        if (! is_string($left) || ! $right instanceof ConstantNode) {
            return null;
        }

        return $left.'.'.$right->attributes['value'];
    }
}
