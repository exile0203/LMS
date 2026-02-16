<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserAvatarController extends Controller
{
    public function show(User $user): BinaryFileResponse
    {
        abort_unless($user->avatar_path, 404);

        $path = Storage::disk('public')->path($user->avatar_path);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
