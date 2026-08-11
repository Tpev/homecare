<?php

$temporaryUploadMaxKilobytes = max(1024, (int) env('LIVEWIRE_TEMP_UPLOAD_MAX_KB', 65536));
$temporaryUploadMaxMinutes = max(5, (int) env('LIVEWIRE_TEMP_UPLOAD_MAX_MINUTES', 10));

return [
    // Every interactive layout includes Livewire explicitly. Keeping automatic
    // injection off prevents static pages from receiving the full Livewire bundle.
    'inject_assets' => false,

    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:'.$temporaryUploadMaxKilobytes],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'avif',
            'bmp',
            'gif',
            'heic',
            'heif',
            'jpg',
            'jpeg',
            'png',
            'svg',
            'tif',
            'tiff',
            'webp',
            'wav',
            'mp4',
            'mov',
            'avi',
            'wmv',
            'mp3',
            'm4a',
            'mpga',
            'wma',
        ],
        'max_upload_time' => $temporaryUploadMaxMinutes,
        'cleanup' => true,
    ],
];
