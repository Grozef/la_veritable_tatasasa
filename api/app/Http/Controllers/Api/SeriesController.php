<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SeriesResource;
use App\Models\Series;

class SeriesController extends Controller
{
    public function show(Series $series): SeriesResource
    {
        $series->load([
            'works' => fn ($query) => $query->published()
                ->with(['media' => fn ($media) => $media->publics(), 'pigments']),
        ]);

        return new SeriesResource($series);
    }
}
