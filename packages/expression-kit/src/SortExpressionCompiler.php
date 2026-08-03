<?php

namespace Coda\ExpressionKit;

use Symfony\Component\ExpressionLanguage\Node\ConstantNode;
use Symfony\Component\ExpressionLanguage\Node\GetAttrNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\Node;

class SortExpressionCompiler
{
    public function __construct(
        private ?ExpressionParser $parser = null,
    ) {}

    public function compile(string $expression): string
    {
        $parser = $this->parser ??= new ExpressionParser;
        $parsed = $parser->parse($expression, $parser->extractIdentifiers($expression, [
            'concat',
            'asc',
            'desc',
            'true',
            'false',
            'null',
        ]));

        return $this->walk($parsed->getNodes());
    }

    /**
     * @return string[]
     */
    public function splitSegments(string $spec): array
    {
        $segments = [];
        $current = '';
        $depth = 0;

        for ($i = 0; $i < strlen($spec); $i++) {
            $char = $spec[$i];

            if ($char === '(') {
                $depth++;
                $current .= $char;

                continue;
            }

            if ($char === ')') {
                $depth--;
                $current .= $char;

                continue;
            }

            if ($char === ',' && $depth === 0) {
                $segments[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $segments[] = $current;
        }

        return $segments;
    }

    private function walk(Node $node): string
    {
        if ($node instanceof NameNode) {
            return $node->attributes['name'];
        }

        if ($node instanceof ConstantNode) {
            return $node->attributes['value'];
        }

        if ($node instanceof GetAttrNode) {
            $type = $node->attributes['type'];

            if ($type === GetAttrNode::PROPERTY_CALL) {
                return $node->nodes['attribute']->attributes['value'];
            }

            if ($type === GetAttrNode::METHOD_CALL) {
                return $this->walkMethodCall($node);
            }
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported node type "%s" in sort expression.',
            get_class($node)
        ));
    }

    private function walkMethodCall(GetAttrNode $node): string
    {
        $methodName = $node->nodes['attribute']->attributes['value'];

        if ($methodName === 'concat') {
            $firstColumn = $this->walk($node->nodes['node']);
            $args = $this->extractMethodArgs($node->nodes['arguments']);

            return 'CONCAT('.implode(", ' ', ", array_merge([$firstColumn], $args)).')';
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported method "%s" in sort expression. Supported: concat.',
            $methodName
        ));
    }

    /**
     * @return string[]
     */
    private function extractMethodArgs(Node $argumentsNode): array
    {
        $args = [];
        $pairs = array_values($argumentsNode->nodes);

        for ($i = 0; $i < count($pairs); $i += 2) {
            $args[] = $this->walk($pairs[$i + 1]);
        }

        return $args;
    }
}
