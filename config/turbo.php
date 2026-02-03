<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feedback Loops
    |--------------------------------------------------------------------------
    |
    | Commands that verify code quality during development. These are injected
    | into skill templates at publish time using {{ $feedback_loops }} (inline)
    | or {{ $feedback_loops_checklist }} (markdown checklist) placeholders.
    |
    */
    'feedback_loops' => [
        'composer lint',
        'composer test',
        'composer analyse',
        'npm run lint',
        'npm run types',
        'npm run build',
        'npm run test',
    ],

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
        | By default, this uses the Dockerfile from the Turbo package. You can
        | override this to use a custom Dockerfile in your application.
        |
        */
        'dockerfile' => env('TURBO_DOCKER_DOCKERFILE'),

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
