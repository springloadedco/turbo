<?php

return [

    'docker' => [
        /*
        |--------------------------------------------------------------------------
        | Image Name
        |--------------------------------------------------------------------------
        |
        | The tag used when building the sandbox image and the template name
        | passed to `docker sandbox run`.
        |
        */
        'image' => env('TURBO_DOCKER_IMAGE', 'turbo-sandbox'),

        /*
        |--------------------------------------------------------------------------
        | Dockerfile Path
        |--------------------------------------------------------------------------
        |
        | Path to the Dockerfile used to build the sandbox image. The directory
        | containing the Dockerfile is used as the build context.
        |
        */
        'dockerfile' => env('TURBO_DOCKER_DOCKERFILE', __DIR__.'/../Dockerfile'),

        /*
        |--------------------------------------------------------------------------
        | Workspace Path
        |--------------------------------------------------------------------------
        |
        | The local directory mounted into the sandbox via `--workspace`.
        |
        */
        'workspace' => env('TURBO_DOCKER_WORKSPACE', base_path()),
    ],

];
