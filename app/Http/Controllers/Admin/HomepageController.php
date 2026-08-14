<?php

namespace App\Http\Controllers\Admin;

use Exception;
use App\Services\HomepageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class HomepageController extends AdminController implements HasMiddleware
{
    public HomepageService $homepageService;

    public function __construct(HomepageService $homepageService)
    {
        parent::__construct();
        $this->homepageService = $homepageService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:settings', only: ['index', 'update']),
        ];
    }

    public function index()
    {
        try {
            return response(['status' => true, 'data' => $this->homepageService->list()]);
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function update(Request $request)
    {
        try {
            return response(['status' => true, 'data' => $this->homepageService->update($request)]);
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function galleryImages()
    {
        try {
            $images = [];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
            
            $storagePaths = [
                storage_path('app/public'),
                public_path('storage'),
                public_path('uploads')
            ];

            foreach ($storagePaths as $basePath) {
                if (\Illuminate\Support\Facades\File::isDirectory($basePath)) {
                    $files = \Illuminate\Support\Facades\File::allFiles($basePath);
                    foreach ($files as $file) {
                        $ext = strtolower($file->getExtension());
                        if (in_array($ext, $allowedExtensions)) {
                            $filename = $file->getFilename();
                            $realPath = str_replace('\\', '/', $file->getRealPath());
                            
                            $url = '';
                            if (str_contains($realPath, '/storage/app/public/')) {
                                $rel = explode('/storage/app/public/', $realPath)[1];
                                $url = asset('storage/' . $rel);
                            } elseif (str_contains($realPath, '/public/storage/')) {
                                $rel = explode('/public/storage/', $realPath)[1];
                                $url = asset('storage/' . $rel);
                            } elseif (str_contains($realPath, '/public/')) {
                                $rel = explode('/public/', $realPath)[1];
                                $url = asset($rel);
                            }

                            if ($url && !in_array($url, array_column($images, 'url'))) {
                                $images[] = [
                                    'name' => $filename,
                                    'url' => $url
                                ];
                            }
                        }
                    }
                }
            }

            return response(['status' => true, 'data' => array_values($images)]);
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function uploadGalleryImage(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,jpg,png,webp,gif,svg|max:10240'
            ]);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = 'gallery_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                
                // Primary storage directory
                $storageDir = storage_path('app/public/gallery');
                if (!\Illuminate\Support\Facades\File::exists($storageDir)) {
                    \Illuminate\Support\Facades\File::makeDirectory($storageDir, 0775, true);
                }
                $file->move($storageDir, $filename);

                // Also mirror to public/storage/gallery for cPanel web server compatibility
                $publicDir = public_path('storage/gallery');
                if (!\Illuminate\Support\Facades\File::exists($publicDir)) {
                    \Illuminate\Support\Facades\File::makeDirectory($publicDir, 0775, true);
                }
                if (\Illuminate\Support\Facades\File::exists($storageDir . '/' . $filename)) {
                    \Illuminate\Support\Facades\File::copy($storageDir . '/' . $filename, $publicDir . '/' . $filename);
                }

                $url = asset('storage/gallery/' . $filename);

                return response([
                    'status' => true,
                    'message' => 'Image uploaded successfully!',
                    'url' => $url,
                    'filename' => $filename
                ]);
            }

            return response(['status' => false, 'message' => 'No image file uploaded.'], 422);
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
