<?php

namespace App\Support\Readiness;

use App\Data\Athlete\Metric\Metrics\ReadinessMetric;
use App\Data\Athlete\Metric\ReadinessMetricData;

class ReadinessSurvey
{
    /** @return array<string, int|null> */
    public static function defaultState(): array
    {
        return [
            'sleepMinutes' => 480,
            'sleepQuality' => 3,
            'altitudeMeters' => 1500,
            'condition' => 3,
            'mood' => 3,
            'motivation' => 3,
            'soreness' => 3,
            'energy' => 3,
            'restingHeartRate' => 60,
            'restingHeartRateBaseline' => 60,
            'hrv' => null,
        ];
    }

    public static function fromState(array $state): ReadinessMetric
    {
        return ReadinessMetric::from(array_merge(self::defaultState(), $state));
    }

    /** @return array<string, mixed> */
    public static function buildViewData(array $state, int $extremeOffset = ReadinessMetricData::DEFAULT_EXTREME_OFFSET): array
    {
        $metric = self::fromState($state);
        $data = $metric->data();
        $sleepDurationScore = ReadinessMetricData::sleepDurationScore($metric->sleepMinutes);
        $altitudeScore = ReadinessMetricData::altitudeScore($metric->altitudeMeters);
        $rhrScore = ReadinessMetricData::rhrScore($metric->restingHeartRate, $metric->restingHeartRateBaseline);
        $trafficLight = $data->trafficLight($extremeOffset);
        $score = $data->readinessScore($extremeOffset);

        return [
            'metric' => $metric,
            'data' => $data,
            'readinessScoreDivisor' => 7,
            'sleepDurationScore' => $sleepDurationScore,
            'sleepDurationFormatted' => match ($metric->sleepMinutes) {
                360 => '<6:00',
                510 => '>8:30',
                default => ReadinessMetricData::formatSleepDuration($metric->sleepMinutes),
            },
            'sleepDurationLabel' => ReadinessMetricData::sleepDurationLabel($metric->sleepMinutes),
            'altitudeScore' => $altitudeScore,
            'altitudeFormatted' => match ($metric->altitudeMeters) {
                1500 => '<1500',
                3000 => '>3000',
                default => $metric->altitudeMeters,
            },
            'altitudeLabel' => ReadinessMetricData::altitudeLabel($metric->altitudeMeters),
            'rhrScore' => $rhrScore,
            'rhrDelta' => $data->rhrDelta(),
            'sleepQualityAdjusted' => ReadinessMetricData::adjustScore($metric->sleepQuality, $extremeOffset),
            'sleepDurationAdjusted' => ReadinessMetricData::adjustScore($sleepDurationScore, $extremeOffset),
            'altitudeAdjusted' => ReadinessMetricData::adjustScore($altitudeScore, $extremeOffset),
            'conditionAdjusted' => ReadinessMetricData::adjustScore($metric->condition, $extremeOffset),
            'moodAdjusted' => ReadinessMetricData::adjustScore($metric->mood, $extremeOffset),
            'motivationAdjusted' => ReadinessMetricData::adjustScore($metric->motivation, $extremeOffset),
            'sorenessAdjusted' => ReadinessMetricData::adjustScore($metric->soreness, $extremeOffset),
            'energyAdjusted' => ReadinessMetricData::adjustScore($metric->energy, $extremeOffset),
            'rhrAdjusted' => ReadinessMetricData::adjustScore($rhrScore, $extremeOffset),
            'sleepQualityWeighted' => ReadinessMetricData::adjustScore($metric->sleepQuality, $extremeOffset) * 0.40,
            'sleepDurationWeighted' => $sleepDurationScore === null ? null : ReadinessMetricData::adjustScore($sleepDurationScore, $extremeOffset) * 0.40,
            'altitudeWeighted' => $altitudeScore === null ? null : ReadinessMetricData::adjustScore($altitudeScore, $extremeOffset) * 0.20,
            'sleepScore' => $data->sleepScore($extremeOffset),
            'readinessComponentsSum' => $data->readinessComponentsSum($extremeOffset),
            'readinessScoreRaw' => $score,
            'readinessScoreRounded' => $score !== null ? round($score, 1) : null,
            'readinessScore' => $score,
            'trafficLight' => $trafficLight,
            'trafficLightLabel' => ReadinessMetricData::trafficLightLabel($trafficLight),
            'trafficLightColor' => ReadinessMetricData::trafficLightColor($trafficLight),
            'trafficLightMeta' => self::trafficLightMeta()[$trafficLight] ?? null,
            'extremeOffset' => $extremeOffset,
        ];
    }

    /** @return array<string, array{label: string, color: string, classes: string}> */
    public static function trafficLightMeta(): array
    {
        return [
            'ready' => ['label' => 'Ready', 'color' => 'green', 'classes' => 'bg-green-500/15 text-green-700 dark:text-green-400 border-green-500/30'],
            'train_smart' => ['label' => 'Train Smart', 'color' => 'amber', 'classes' => 'bg-yellow-500/15 text-yellow-700 dark:text-yellow-400 border-yellow-500/30'],
            'recovery' => ['label' => 'Recovery', 'color' => 'orange', 'classes' => 'bg-orange-500/15 text-orange-700 dark:text-orange-400 border-orange-500/30'],
            'rest' => ['label' => 'Rest', 'color' => 'red', 'classes' => 'bg-red-500/15 text-red-700 dark:text-red-400 border-red-500/30'],
        ];
    }
}
