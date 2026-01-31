<?php

// config for Springloaded/Turbo
return [

    'docker' => [
        /*
        |--------------------------------------------------------------------------
        | Docker Sandbox Template
        |--------------------------------------------------------------------------
        |
        | This option defines the Docker sandbox template to use when running
        | Turbo in a Docker sandbox environment.
        |
        */
        'sandbox_template' => env('TURBO_DOCKER_SANDBOX_TEMPLATE', 'claude-php-sandbox'),

        /*
        |--------------------------------------------------------------------------
        | Docker Sandbox Workspace
        |--------------------------------------------------------------------------
        |
        | This option defines the workspace directory to mount when running
        | the Docker sandbox. Defaults to the Laravel project root.
        |
        */
        'workspace' => env('TURBO_DOCKER_WORKSPACE', base_path()),
    ],

    'claude' => [
        /*
        |--------------------------------------------------------------------------
        | Claude Model
        |--------------------------------------------------------------------------
        |
        | This option defines the default Claude model to use when interacting
        | with the Claude AI service.
        |
        */
        'model' => env('TURBO_CLAUDE_MODEL', 'opus'),
    ],

    'github' => [
        /*
        |--------------------------------------------------------------------------
        | GitHub Token
        |--------------------------------------------------------------------------
        |
        | This option defines the GitHub token to use for authentication when
        | interacting with the GitHub API.
        |
        */
        'token' => env('GITHUB_TOKEN', null),

        /*
        |--------------------------------------------------------------------------
        | GitHub Repository
        |--------------------------------------------------------------------------
        |
        | This option defines the GitHub repository to use for task management.
        |
        */
        'repository' => env('GITHUB_REPOSITORY', null),
    ]
];
