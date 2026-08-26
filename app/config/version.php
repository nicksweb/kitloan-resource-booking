<?php

/*
|--------------------------------------------------------------------------
| Application version
|--------------------------------------------------------------------------
|
| Single source of truth for the running Kitloan version. The number itself
| lives in the `VERSION` file (both `app/VERSION`, which ships inside the
| image, and a repo-root copy for convenience — keep them identical). Bump it
| alongside the top CHANGELOG.md heading on every release (see CONTRIBUTING.md
| § "Versioning and releasing").
|
| - `app`               the version of the code in this image.
| - `min_upgrade_from`  the oldest version that can upgrade *directly* to this
|                       one. `php artisan kitloan:upgrade` refuses to run if the
|                       instance's stored version is older, so an operator is
|                       told to step through an intermediate release rather than
|                       skipping a contract-half schema migration.
|
| `stored_version_key` is the settings-table key `kitloan:upgrade` writes the
| completed-upgrade version into, so a later run can tell what actually ran.
|
*/

return [

    'app' => trim((string) @file_get_contents(dirname(__DIR__).'/VERSION')) ?: '1.2.0',

    'min_upgrade_from' => '1.0.0',

    'stored_version_key' => 'installed_app_version',

];
