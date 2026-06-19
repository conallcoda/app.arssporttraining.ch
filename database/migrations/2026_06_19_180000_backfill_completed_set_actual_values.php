<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('training_program_slot_set_values as set_values')
            ->join('training_program_slot_sets as sets', 'sets.id', '=', 'set_values.training_program_slot_set_id')
            ->select([
                'set_values.id',
                'set_values.planned_value_type',
                'set_values.planned_int_value',
                'set_values.planned_decimal_value',
                'set_values.planned_string_value',
                'set_values.planned_json_value',
                'set_values.updated_at',
                'sets.completed_at',
            ])
            ->whereNull('set_values.actual_value_type')
            ->whereNotNull('set_values.planned_value_type')
            ->whereNull('sets.skipped_at')
            ->where(function ($query): void {
                $query
                    ->whereNotNull('sets.completed_at')
                    ->orWhereIn('sets.status', ['completed', 'completed_with_modification']);
            })
            ->orderBy('set_values.id')
            ->chunk(500, function ($values): void {
                $now = now();

                foreach ($values as $value) {
                    DB::table('training_program_slot_set_values')
                        ->where('id', $value->id)
                        ->whereNull('actual_value_type')
                        ->update([
                            'actual_value_type' => $value->planned_value_type,
                            'actual_int_value' => $value->planned_int_value,
                            'actual_decimal_value' => $value->planned_decimal_value,
                            'actual_string_value' => $value->planned_string_value,
                            'actual_json_value' => $value->planned_json_value,
                            'actual_recorded_at' => $value->completed_at ?? $value->updated_at ?? $now,
                            'actual_source' => 'backfill',
                            'actual_is_explicit' => true,
                            'is_modified' => false,
                            'updated_at' => $now,
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('training_program_slot_set_values')
            ->where('actual_source', 'backfill')
            ->update([
                'actual_value_type' => null,
                'actual_int_value' => null,
                'actual_decimal_value' => null,
                'actual_string_value' => null,
                'actual_json_value' => null,
                'actual_recorded_at' => null,
                'actual_source' => null,
                'actual_is_explicit' => false,
                'is_modified' => false,
                'updated_at' => now(),
            ]);
    }
};
