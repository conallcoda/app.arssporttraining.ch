<?php

namespace App\Training;

use App\Models\Users\UserTypeEnum;
use Illuminate\Support\Str;

class TrainingAuditContext
{
    private ?string $operationId = null;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function metadata(array $context = []): array
    {
        $request = app()->bound('request') ? request() : null;

        return array_filter([
            'operation_id' => $this->operationId($request?->header('X-Request-ID')),
            'route' => $request?->route()?->getName(),
            'command' => app()->runningInConsole() ? ($_SERVER['argv'][1] ?? null) : null,
            'application_release' => config('app.release')
                ?? config('sentry.release')
                ?? basename(base_path()),
            'outcome' => 'applied',
            ...$context,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @param array<string, mixed> $context */
    public function reason(array $context = []): string
    {
        return json_encode($this->metadata($context), JSON_THROW_ON_ERROR);
    }

    public function source(): string
    {
        if (! auth()->check()) {
            return 'system';
        }

        return match (auth()->user()?->type) {
            UserTypeEnum::Coach => 'coach',
            UserTypeEnum::Admin => 'admin',
            default => 'athlete',
        };
    }

    private function operationId(?string $externalId): string
    {
        if (is_string($externalId) && $externalId !== '') {
            return Str::limit(trim($externalId), 128, '');
        }

        return $this->operationId ??= (string) Str::uuid();
    }
}
