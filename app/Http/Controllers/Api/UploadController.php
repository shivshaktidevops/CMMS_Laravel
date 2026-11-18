<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function store(Request $r)
    {
        $files = $r->file('photos', []);
        if (! is_array($files)) {
            $files = [$files];
        }if (count($files) > 5) {
            return response()->json(['success' => false, 'message' => 'Maximum 5 photos allowed'], 422);
        }$out = [];
        foreach ($files as $f) {
            if (! $f) {
                continue;
            }if ($f->getSize() > 10 * 1024 * 1024) {
                return response()->json(['success' => false, 'message' => 'Each file must be 10MB or less'], 422);
            }$name = time().'-'.bin2hex(random_bytes(4)).'-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $f->getClientOriginalName());
            $f->move(public_path('uploads'), $name);
            $out[] = '/uploads/'.$name;
        }

return response()->json(['success' => true, 'files' => $out]);
    }
}
