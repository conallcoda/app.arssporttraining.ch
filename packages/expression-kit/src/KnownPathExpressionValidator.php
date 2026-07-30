<?php

namespace Coda\ExpressionKit;

class KnownPathExpressionValidator
{
    public function __construct(
        private ?ExpressionParser $parser = null,
        private ?ExpressionPathFinder $pathFinder = null,
    ) {}

    /**
     * @param  string[]  $knownPaths
     * @param  string[]  $knownRoots
     */
    public function validate(string $expression, array $knownPaths, array $knownRoots = []): ExpressionValidationResult
    {
        $parser = $this->parser ??= new ExpressionParser;
        $pathFinder = $this->pathFinder ??= new ExpressionPathFinder;
        $knownRoots = $knownRoots !== []
            ? array_values(array_unique($knownRoots))
            : array_values(array_unique(array_map(
                static fn (string $path): string => explode('.', $path)[0],
                $knownPaths,
            )));

        try {
            $parsed = $parser->parse($expression, $knownRoots);
        } catch (\InvalidArgumentException $e) {
            return new ExpressionValidationResult(syntaxError: $e->getMessage());
        }

        $referencedPaths = $pathFinder->fromParsed($parsed);
        $unknownPaths = [];

        foreach ($referencedPaths as $path) {
            if (in_array($path, $knownPaths, true) || in_array($path, $knownRoots, true)) {
                continue;
            }

            $isKnownPrefix = false;

            foreach ($knownPaths as $candidate) {
                if (str_starts_with($candidate, $path.'.')) {
                    $isKnownPrefix = true;

                    break;
                }
            }

            if (! $isKnownPrefix) {
                $unknownPaths[] = $path;
            }
        }

        $unknownPaths = array_values(array_filter(
            array_values(array_unique($unknownPaths)),
            static function (string $path) use ($unknownPaths): bool {
                foreach ($unknownPaths as $candidate) {
                    if ($candidate !== $path && str_starts_with($candidate, $path.'.')) {
                        return false;
                    }
                }

                return true;
            }
        ));

        return new ExpressionValidationResult(
            unknownPaths: $unknownPaths,
        );
    }
}
