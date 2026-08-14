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
            $storagePaths = [
                storage_path('app/public'),
                public_path('storage'),
                public_path('uploads'),
                public_path('images')
            ];

            foreach ($storagePaths as $basePath) {
                if (file_exists($basePath)) {
                    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basePath));
                    foreach ($files as $file) {
                        if ($file->isFile()) {
                            $ext = strtolower($file->getExtension());
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'])) {
                                $filename = $file->getFilename();
                                $realPath = str_replace('\\', '/', $file->getRealPath());
                                
                                // Determine relative path for public URL
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
                'image' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:10240'
            ]);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = 'gallery_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                
                $destinationPath = storage_path('app/public/gallery');
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }

                $file->move($destinationPath, $filename);
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
