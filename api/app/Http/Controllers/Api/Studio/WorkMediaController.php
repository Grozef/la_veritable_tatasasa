<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Studio;

use App\Enums\MediaRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkMediaRequest;
use App\Http\Resources\MediaResource;
use App\Jobs\ProcessWorkMedia;
use App\Models\Work;
use App\Models\WorkMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Image;

class WorkMediaController extends Controller
{
    /**
     * L'original est conserve tel quel, jamais reencode : c'est la seule copie de la
     * numerisation. Les derivees web et la palette partent en file d'attente, la reponse
     * n'attend pas : l'app studio doit rendre la main des que l'original est en securite.
     */
    public function store(StoreWorkMediaRequest $request, Work $work): JsonResponse
    {
        $this->authorize('update', $work);

        $upload = $request->file('file');
        $path = $upload->store("works/{$work->ulid}", 'public');

        // `orient()` d'abord : sans redressement EXIF, les dimensions lues sont
        // inversees sur toute photo prise en portrait par un telephone.
        $probe = Image::fromPath($upload->getRealPath())->orient();
        [$width, $height] = $probe->dimensions();

        $media = $work->media()->create([
            'role' => $request->enum('role', MediaRole::class) ?? MediaRole::Principal,
            'disk' => 'public',
            'path_original' => $path,
            'path_web' => null,
            'width' => $width,
            'height' => $height,
            'mime' => $upload->getMimeType(),
            'lqip_hex' => $probe->dominantColor(),
            'position' => $request->integer('position'),
        ]);

        // La brute est une archive : ni derivees web, ni nuancier. La deriver couterait
        // deux reencodages par largeur pour un fichier que personne n'affichera.
        if ($media->role->isPublic()) {
            ProcessWorkMedia::dispatch($media);
        }

        return (new MediaResource($media))->response()->setStatusCode(201);
    }

    public function destroy(Work $work, WorkMedia $media): Response
    {
        $this->authorize('update', $work);

        abort_unless($media->work_id === $work->id, 404);

        $media->delete();

        return response()->noContent();
    }
}
