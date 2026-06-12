<?php

namespace Coda\SchemaKit;

final class FacetSetResolver
{
    /**
     * @param  array<int, FacetDefinition>  $facets
     * @return array<int, FacetDefinition>
     */
    public function resolve(array $facets, ?FacetResolutionContext $context = null, ?string $schemaKey = null): array
    {
        $context ??= FacetResolutionContext::make($schemaKey);
        $resolved = [];

        foreach ($facets as $index => $facet) {
            $score = $this->applicabilityScore($facet, $context, $schemaKey);

            if ($score === null) {
                continue;
            }

            $facetGroup = $facet->getFacetGroup() ?? $facet->name();

            if (! isset($resolved[$facetGroup])) {
                $resolved[$facetGroup] = [
                    'facet' => $facet,
                    'score' => $score,
                    'index' => $index,
                ];

                continue;
            }

            if ($score > $resolved[$facetGroup]['score']) {
                $resolved[$facetGroup]['facet'] = $facet;
                $resolved[$facetGroup]['score'] = $score;
                $resolved[$facetGroup]['index'] = $index;
            }
        }

        return array_values(array_map(
            static fn (array $item): FacetDefinition => $item['facet'],
            $resolved,
        ));
    }

    /**
     * @param  array<int, string>|null  $facetNames
     * @return array<int, FacetDefinition>
     */
    public function resolveEntity(EntityDefinition $entity, ?FacetResolutionContext $context = null, ?array $facetNames = null): array
    {
        $facets = $facetNames === null
            ? array_values($entity->getFacets())
            : array_values(array_map(
                static fn (string $facetName): FacetDefinition => $entity->requireFacet($facetName),
                $facetNames,
            ));

        return $this->resolve($facets, $context, $entity->name());
    }

    private function applicabilityScore(FacetDefinition $facet, FacetResolutionContext $context, ?string $schemaKey = null): ?int
    {
        $rules = $facet->getApplicability();

        if ($rules === []) {
            return 0;
        }

        $hasIncludeRules = false;
        $matchedRules = [];

        foreach ($rules as $rule) {
            if ($rule->mode === 'include') {
                $hasIncludeRules = true;
            }

            if (! $this->ruleMatches($rule, $context, $schemaKey)) {
                continue;
            }

            $matchedRules[] = $rule;
        }

        if ($matchedRules === []) {
            return $hasIncludeRules ? null : 0;
        }

        usort($matchedRules, static function (FacetApplicabilityRuleData $left, FacetApplicabilityRuleData $right): int {
            if ($left->priority !== $right->priority) {
                return $right->priority <=> $left->priority;
            }

            if ($left->mode === $right->mode) {
                return 0;
            }

            return $left->mode === 'exclude' ? -1 : 1;
        });

        $winner = $matchedRules[0];

        return $winner->mode === 'exclude' ? null : $winner->priority;
    }

    private function ruleMatches(FacetApplicabilityRuleData $rule, FacetResolutionContext $context, ?string $schemaKey = null): bool
    {
        $resolvedSchemaKey = $context->schemaKey() ?? $schemaKey;

        if ($rule->schemaKey !== null && $rule->schemaKey !== $resolvedSchemaKey) {
            return false;
        }

        $scope = $context->scopeReference();

        if ($rule->scopeType !== null && $rule->scopeType !== $scope->type) {
            return false;
        }

        if ($rule->scopeId !== null && (string) $rule->scopeId !== (string) $scope->id) {
            return false;
        }

        if ($rule->taxonomyType !== null && ! $context->hasTaxonomyTerm($rule->taxonomyType, $rule->taxonomyTerm)) {
            return false;
        }

        if ($rule->segmentSlug !== null && ! $context->hasSegment($rule->segmentSlug)) {
            return false;
        }

        return true;
    }
}
