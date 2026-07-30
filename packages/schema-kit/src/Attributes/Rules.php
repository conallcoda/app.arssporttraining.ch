<?php

namespace Coda\SchemaKit\Attributes;

use Attribute;
use Spatie\LaravelData\Support\Validation\RuleDenormalizer;
use Spatie\LaravelData\Support\Validation\RuleNormalizer;
use Spatie\LaravelData\Support\Validation\ValidationPath;
use Spatie\LaravelData\Support\Validation\ValidationRuleFactory;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Rules implements ProvidesValidationRules
{
    private array $rules;

    /**
     * @param  mixed  ...$rules
     */
    public function __construct(mixed ...$rules)
    {
        $this->rules = $rules;
    }

    public function rules(): array
    {
        $normalizer = new RuleNormalizer(new ValidationRuleFactory());
        $denormalizer = new RuleDenormalizer();
        $rules = [];

        foreach ($this->rules as $rule) {
            foreach ($normalizer->execute($rule) as $normalizedRule) {
                array_push($rules, ...$denormalizer->execute($normalizedRule, ValidationPath::create()));
            }
        }

        return $rules;
    }
}
