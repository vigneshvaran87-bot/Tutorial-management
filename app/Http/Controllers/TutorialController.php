<?php

namespace App\Http\Controllers;

use App\Models\Tutorial;
use Carbon\Carbon;
use Cloudinary\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TutorialController extends Controller
{
    /**
     * Display a listing of tutorials.
     */
    public function index(Request $request)
    {
        $tutorials = Tutorial::latest()
            ->paginate(10);

        return Inertia::render('tutorials/index', [
            'tutorials' => $tutorials,
        ]);
    }

    /**
     * Show the form for creating a new tutorial.
     */
    public function create()
    {
        return Inertia::render('tutorials/create');
    }

    /**
     * Store a newly created tutorial.
     */
    public function store(Request $request)
    {
        /*
         * ============================================================
         * Normalize category
         * ============================================================
         */
        $category = $request->input('category', []);

        if (!is_array($category)) {
            $category = [$category];
        }

        $request->merge([
            'category' => $category,
        ]);

        /*
         * ============================================================
         * Validate
         * ============================================================
         */
        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'category' => [
                'nullable',
                'array',
            ],

            'category.*' => [
                'string',
                'in:ReactJS,Jquery,Typescript',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            /*
             * Frontend sends the actual image File.
             */
            'thumbnail' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            /*
             * Kept for compatibility with an optional
             * direct Cloudinary upload flow.
             */
            'thumbnail_url' => [
                'nullable',
                'url',
            ],

            'thumbnail_public_id' => [
                'nullable',
                'string',
                'max:500',
            ],

            'published' => [
                'nullable',
                'boolean',
            ],

            'published_date' => [
                'nullable',
                'date',
            ],
        ]);

        /*
         * ============================================================
         * Upload thumbnail to Cloudinary
         * ============================================================
         */
        if (
            $request->hasFile('thumbnail') &&
            $request->file('thumbnail')->isValid()
        ) {
            try {
                $uploadResult = $this->uploadCloudinaryImage(
                    $request->file('thumbnail')
                );

                $validated['thumbnail'] =
                    $uploadResult['secure_url'];

                $validated['thumbnail_public_id'] =
                    $uploadResult['public_id'];
            } catch (\Throwable $e) {
                Log::error(
                    'Cloudinary thumbnail upload failed during tutorial creation.',
                    [
                        'error' => $e->getMessage(),
                    ]
                );

                return back()
                    ->withErrors([
                        'thumbnail' =>
                            'Unable to upload the thumbnail to Cloudinary. Please try again.',
                    ])
                    ->withInput();
            }
        }

        /*
         * ============================================================
         * Existing direct Cloudinary URL flow
         * ============================================================
         */
        elseif (
            $request->filled('thumbnail_url')
        ) {
            $validated['thumbnail'] =
                $request->input('thumbnail_url');

            $validated['thumbnail_public_id'] =
                $request->input('thumbnail_public_id');
        }

        /*
         * Frontend-only field.
         */
        unset(
            $validated['thumbnail_url']
        );

        /*
         * ============================================================
         * Create tutorial
         * ============================================================
         */
        Tutorial::create($validated);

        return redirect()
            ->route(
                'tutorials.index',
                [
                    'current_team' =>
                        $request->route('current_team'),
                ]
            )
            ->with(
                'success',
                'Tutorial created successfully.'
            );
    }

    /**
     * Display the specified tutorial.
     */
    public function show(
        string $current_team,
        string $tutorial
    ) {
        $tutorialModel =
            Tutorial::find($tutorial);

        if (!$tutorialModel) {
            abort(
                404,
                'Tutorial not found.'
            );
        }

        return Inertia::render(
            'tutorials/show',
            [
                'tutorial' => [
                    'id' =>
                        (string) $tutorialModel->id,

                    'title' =>
                        $tutorialModel->title,

                    'category' =>
                        $tutorialModel->category,

                    'description' =>
                        $tutorialModel->description,

                    'published' =>
                        (bool) $tutorialModel->published,

                    'published_date' =>
                        $tutorialModel->published_date,

                    'thumbnail' =>
                        $tutorialModel->thumbnail,

                    'thumbnail_url' =>
                        $tutorialModel->thumbnail,

                    'thumbnail_public_id' =>
                        $tutorialModel
                            ->thumbnail_public_id,
                ],

                'currentTeam' => [
                    'slug' =>
                        $current_team,
                ],
            ]
        );
    }

    /**
     * Show the form for editing the specified tutorial.
     */
    public function edit(
        string $current_team,
        string $tutorial
    ) {
        $tutorialModel =
            Tutorial::find($tutorial);

        if (!$tutorialModel) {
            abort(
                404,
                'Tutorial not found.'
            );
        }

        return Inertia::render(
            'tutorials/edit',
            [
                'tutorial' => [
                    'id' =>
                        (string) $tutorialModel->id,

                    'title' =>
                        $tutorialModel->title,

                    'category' =>
                        $tutorialModel->category,

                    'description' =>
                        $tutorialModel->description,

                    'published' =>
                        (bool) $tutorialModel->published,

                    /*
                     * Format the date for
                     * datetime-local input.
                     */
                    'published_date' =>
                        $tutorialModel->published_date
                            ? Carbon::parse(
                                $tutorialModel
                                    ->published_date
                            )->format(
                                'Y-m-d\TH:i'
                            )
                            : null,

                    /*
                     * Cloudinary URL.
                     */
                    'thumbnail' =>
                        $tutorialModel->thumbnail,

                    'thumbnail_url' =>
                        $tutorialModel->thumbnail,

                    /*
                     * Required to delete the old
                     * Cloudinary asset when replacing
                     * or removing the image.
                     */
                    'thumbnail_public_id' =>
                        $tutorialModel
                            ->thumbnail_public_id,
                ],

                'currentTeam' => [
                    'slug' =>
                        $current_team,
                ],
            ]
        );
    }

    /**
     * Update the specified tutorial.
     */
    public function update(
        Request $request,
        string $current_team,
        string $tutorial
    ) {
        $tutorialModel =
            Tutorial::find($tutorial);

        if (!$tutorialModel) {
            abort(
                404,
                'Tutorial not found.'
            );
        }

        /*
         * ============================================================
         * Normalize category
         * ============================================================
         */
        $category =
            $request->input(
                'category',
                []
            );

        if (!is_array($category)) {
            $category = [$category];
        }

        $request->merge([
            'category' => $category,
        ]);

        /*
         * ============================================================
         * Validate
         * ============================================================
         */
        $validated =
            $request->validate([
                'title' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'category' => [
                    'nullable',
                    'array',
                ],

                'category.*' => [
                    'string',
                    'in:ReactJS,Jquery,Typescript',
                ],

                'description' => [
                    'nullable',
                    'string',
                ],

                /*
                 * This is the important field.
                 *
                 * edit.tsx sends the cropped image
                 * as a real File.
                 */
                'thumbnail' => [
                    'nullable',
                    'file',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:2048',
                ],

                /*
                 * Kept for compatibility.
                 */
                'thumbnail_url' => [
                    'nullable',
                    'url',
                ],

                'thumbnail_public_id' => [
                    'nullable',
                    'string',
                    'max:500',
                ],

                'published' => [
                    'nullable',
                    'boolean',
                ],

                'published_date' => [
                    'nullable',
                    'date',
                ],

                'remove_thumbnail' => [
                    'nullable',
                    'boolean',
                ],
            ]);

        /*
         * ============================================================
         * Existing Cloudinary public ID
         * ============================================================
         */
        $oldPublicId =
            $tutorialModel->thumbnail_public_id;

        /*
         * ============================================================
         * Track newly uploaded Cloudinary asset
         * ============================================================
         */
        $newPublicId = null;

        /*
         * ============================================================
         * NEW IMAGE
         *
         * If a new/cropped file was selected:
         *
         * React File
         *      ↓
         * Laravel
         *      ↓
         * Cloudinary
         *      ↓
         * secure_url + public_id
         * ============================================================
         */
        if (
            $request->hasFile('thumbnail') &&
            $request->file('thumbnail')->isValid()
        ) {
            try {
                /*
                 * Upload NEW image first.
                 *
                 * This is important because we don't
                 * want to delete the old image until
                 * the new upload succeeds.
                 */
                $uploadResult =
                    $this->uploadCloudinaryImage(
                        $request->file('thumbnail')
                    );

                $validated['thumbnail'] =
                    $uploadResult['secure_url'];

                $validated[
                    'thumbnail_public_id'
                ] =
                    $uploadResult['public_id'];

                $newPublicId =
                    $uploadResult['public_id'];

            } catch (\Throwable $e) {
                Log::error(
                    'Cloudinary thumbnail upload failed during tutorial update.',
                    [
                        'tutorial_id' =>
                            (string) $tutorialModel->id,

                        'error' =>
                            $e->getMessage(),
                    ]
                );

                return back()
                    ->withErrors([
                        'thumbnail' =>
                            'Unable to upload the thumbnail to Cloudinary. Please try again.',
                    ])
                    ->withInput();
            }
        }

        /*
         * ============================================================
         * REMOVE IMAGE
         *
         * Only execute this when there isn't a new
         * thumbnail being uploaded.
         * ============================================================
         */
        elseif (
            $request->boolean(
                'remove_thumbnail'
            )
        ) {
            /*
             * Delete old Cloudinary asset.
             */
            if ($oldPublicId) {
                $this->deleteCloudinaryImage(
                    $oldPublicId
                );
            }

            /*
             * Clear database values.
             */
            $validated['thumbnail'] =
                null;

            $validated[
                'thumbnail_public_id'
            ] = null;
        }

        /*
         * ============================================================
         * DIRECT CLOUDINARY URL
         *
         * Compatibility with frontend code that may
         * already upload directly to Cloudinary.
         * ============================================================
         */
        elseif (
            $request->filled(
                'thumbnail_url'
            )
        ) {
            $validated['thumbnail'] =
                $request->input(
                    'thumbnail_url'
                );

            $validated[
                'thumbnail_public_id'
            ] =
                $request->input(
                    'thumbnail_public_id'
                );

            $newPublicId =
                $request->input(
                    'thumbnail_public_id'
                );
        }

        /*
         * ============================================================
         * Delete old image AFTER new image succeeds
         * ============================================================
         */
        if (
            $newPublicId &&
            $oldPublicId &&
            $oldPublicId !== $newPublicId
        ) {
            $this->deleteCloudinaryImage(
                $oldPublicId
            );
        }

        /*
         * ============================================================
         * Remove frontend-only fields
         * ============================================================
         */
        unset(
            $validated['thumbnail_url'],
            $validated['remove_thumbnail']
        );

        /*
         * ============================================================
         * Update database
         * ============================================================
         */
        $tutorialModel->update(
            $validated
        );

        return redirect()
            ->route(
                'tutorials.index',
                [
                    'current_team' =>
                        $current_team,
                ]
            )
            ->with(
                'success',
                'Tutorial updated successfully.'
            );
    }

    /**
     * Upload an image to Cloudinary.
     *
     * @return array{
     *     secure_url: string,
     *     public_id: string
     * }
     */
    private function uploadCloudinaryImage(
        $file
    ): array {
        $cloudinary =
            $this->getCloudinary();

        $uploadResult =
            $cloudinary
                ->uploadApi()
                ->upload(
                    $file->getRealPath(),
                    [
                        'folder' =>
                            'tutorials',

                        'resource_type' =>
                            'image',
                    ]
                );

        $secureUrl =
            $uploadResult['secure_url']
            ?? null;

        $publicId =
            $uploadResult['public_id']
            ?? null;

        if (
            !$secureUrl ||
            !$publicId
        ) {
            throw new \RuntimeException(
                'Cloudinary did not return a secure URL and public ID.'
            );
        }

        Log::info(
            'Cloudinary image uploaded successfully.',
            [
                'public_id' =>
                    $publicId,

                'secure_url' =>
                    $secureUrl,
            ]
        );

        return [
            'secure_url' =>
                $secureUrl,

            'public_id' =>
                $publicId,
        ];
    }

    /**
     * Create Cloudinary client.
     */
    private function getCloudinary(): Cloudinary
    {
        $cloudName =
            config(
                'cloudinary.cloud_name'
            );

        $apiKey =
            config(
                'cloudinary.api_key'
            );

        $apiSecret =
            config(
                'cloudinary.api_secret'
            );

        if (
            !$cloudName ||
            !$apiKey ||
            !$apiSecret
        ) {
            throw new \RuntimeException(
                'Cloudinary configuration is missing. Check CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY and CLOUDINARY_API_SECRET.'
            );
        }

        return new Cloudinary([
            'cloud' => [
                'cloud_name' =>
                    $cloudName,

                'api_key' =>
                    $apiKey,

                'api_secret' =>
                    $apiSecret,
            ],

            'url' => [
                'secure' => true,
            ],
        ]);
    }

    /**
     * Delete a Cloudinary image.
     */
    private function deleteCloudinaryImage(
        ?string $publicId
    ): void {
        if (!$publicId) {
            return;
        }

        try {
            $cloudinary =
                $this->getCloudinary();

            $cloudinary
                ->uploadApi()
                ->destroy(
                    $publicId,
                    [
                        'resource_type' =>
                            'image',

                        'type' =>
                            'upload',

                        'invalidate' =>
                            true,
                    ]
                );

            Log::info(
                'Cloudinary image deleted successfully.',
                [
                    'public_id' =>
                        $publicId,
                ]
            );

        } catch (\Throwable $e) {
            /*
             * Don't prevent the tutorial database
             * operation from completing if Cloudinary
             * deletion fails.
             */
            Log::error(
                'Cloudinary image deletion failed.',
                [
                    'public_id' =>
                        $publicId,

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Remove the specified tutorial.
     */
    public function destroy(
        string $current_team,
        string $tutorial
    ) {
        $tutorialModel =
            Tutorial::find($tutorial);

        if (!$tutorialModel) {
            abort(
                404,
                'Tutorial not found.'
            );
        }

        /*
         * Delete Cloudinary thumbnail.
         */
        if (
            $tutorialModel
                ->thumbnail_public_id
        ) {
            $this->deleteCloudinaryImage(
                $tutorialModel
                    ->thumbnail_public_id
            );
        }

        /*
         * Delete tutorial.
         */
        $tutorialModel->delete();

        return redirect()
            ->route(
                'tutorials.index',
                [
                    'current_team' =>
                        $current_team,
                ]
            )
            ->with(
                'success',
                'Tutorial deleted successfully.'
            );
    }
}