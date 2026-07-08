<?php

namespace Coda\Cms\Http\Controllers;

use Coda\Cms\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MediaController extends Controller
{
    public function show(Request $request, Media $media, string $filename)
    {
        return $media->toInlineResponse($request);
    }
}
