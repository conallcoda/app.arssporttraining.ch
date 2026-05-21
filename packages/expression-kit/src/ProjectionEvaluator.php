<?php

namespace Coda\ExpressionKit;

use ArrayAccess;

final class ProjectionEvaluator
{
    /**
     * @param  array<string, mixed>|object  $data
     * @return array<string, mixed>
     */
    public function evaluate(Projection $projection, array|object $data): array
    {
        $output = [];

        foreach ($projection->members() as $member) {
            if ($member instanceof ProjectionField) {
                $output[$member->key] = $this->resolvePath($data, $member->source);

                continue;
            }

            if ($member instanceof ProjectionObject) {
                $output[$member->key] = [];

                foreach ($member->fields() as $field) {
                    $output[$member->key][$field->key] = $this->resolvePath($data, $field->source);
                }
            }
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>|object  $data
     */
    private function resolvePath(array|object $data, string $path): mixed
    {
        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (is_array($current)) {
                if (! array_key_exists($segment, $current)) {
                    return null;
                }

                $current = $current[$segment];

                continue;
            }

            if ($current instanceof ArrayAccess) {
                if (! $current->offsetExists($segment)) {
                    return null;
                }

                $current = $current[$segment];

                continue;
            }

            if (is_object($current)) {
                if (! isset($current->{$segment}) && ! property_exists($current, $segment)) {
                    return null;
                }

                $current = $current->{$segment};

                continue;
            }

            return null;
        }

        return $current;
    }
}
