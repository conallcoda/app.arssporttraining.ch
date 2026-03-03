<?php

use App\Models\Exercise\Exercise;
use Coda\Cms\Support\PathGenerator\CollectionPathGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

it('generates collection-based paths for exercise photos', function () {
    $exercise = Exercise::factory()->create();

    $media = new Media;
    $media->id = 99;
    $media->model_id = $exercise->id;
    $media->model_type = Exercise::class;
    $media->collection_name = 'photos';
    $media->setRelation('model', $exercise);

    $generator = new CollectionPathGenerator;

    expect($generator->getPath($media))
        ->toBe('exercises/'.$exercise->id.'/');

    expect($generator->getPathForConversions($media))
        ->toBe('exercises/'.$exercise->id.'/conversions/');

    expect($generator->getPathForResponsiveImages($media))
        ->toBe('exercises/'.$exercise->id.'/responsive-images/');
});

it('falls back to default paths when model does not implement HasCollectionPaths', function () {
    $media = new Media;
    $media->id = 99;
    $media->collection_name = 'default';
    $media->setRelation('model', new class extends \Illuminate\Database\Eloquent\Model {});

    $generator = new CollectionPathGenerator;

    expect($generator->getPath($media))->toBe('99/');
});

it('falls back to default paths when collection returns null', function () {
    $exercise = Exercise::factory()->create();

    $media = new Media;
    $media->id = 99;
    $media->model_id = $exercise->id;
    $media->model_type = Exercise::class;
    $media->collection_name = 'nonexistent_collection';
    $media->setRelation('model', $exercise);

    $generator = new CollectionPathGenerator;

    expect($generator->getPath($media))->toBe('99/');
});
