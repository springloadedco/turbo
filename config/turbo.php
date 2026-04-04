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
    | Make sure to rerun `turbo:install` after changing this config to update
    | the published templates.
    |
    */
    'feedback_loops' => [
        // Populated by turbo:install based on your project's scripts.
        // Run turbo:install or edit manually.
    ],

    'docker' => [
        /*
        |--------------------------------------------------------------------------
        | Image Name
        |--------------------------------------------------------------------------
        |
        | The fully-qualified OCI registry image passed to `sbx create --template`.
        | sbx pulls templates from registries — the local Docker image store is not
        | shared with the sbx daemon.
        |
        | The default uses the published springloadedco/turbo image from Docker Hub.
        | To extend the image, create a Dockerfile with
        |   FROM springloadedco/turbo:latest
        | set your own registry image here, and run turbo:build.
        |
        */
        'image' => env('TURBO_DOCKER_IMAGE', 'docker.io/springloadedco/turbo:latest'),

        /*
        |--------------------------------------------------------------------------
        | Workspace Path
        |--------------------------------------------------------------------------
        |
        | The local directory mounted into the sandbox via `--workspace`.
        |
        */
        'workspace' => env('TURBO_DOCKER_WORKSPACE', base_path()),

        /*
        |----------------------------------------------------------------------
        | Host Access
        |----------------------------------------------------------------------
        |
        | Hostnames the sandbox can reach on the host machine. Turbo
        | automatically includes the hostname parsed from APP_URL in
        | your .env file. Add extra hosts here for APIs or other
        | services running on the host.
        |
        | Port 80 is used by default. Override per-host with 'host:port'.
        |
        */
        'hosts' => [],
    ],

];
