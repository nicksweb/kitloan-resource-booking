<?php

return [

    /*
    | Passphrase used to encrypt/decrypt backup archives (AES-256-CBC, PBKDF2).
    | The environment wins; if it's blank the app falls back to the
    | `backup_passphrase` setting (stored APP_KEY-encrypted, set from
    | Administration -> Settings -> Backups). One or the other must be present
    | for scheduled backups, on-demand download, or `kitloan:restore` to work.
    */
    'passphrase' => env('KITLOAN_BACKUP_PASSPHRASE'),

    /*
    | Where `kitloan:backup` writes archives. Defaults to a directory on the
    | shared `app_storage` volume; point it at a bind-mounted host path (and
    | rsync that offsite) for real durability.
    */
    'path' => env('KITLOAN_BACKUP_PATH') ?: storage_path('app/backups'),

    /*
    | PBKDF2 parameters. These match `openssl enc -aes-256-cbc -pbkdf2 -iter
    | 100000 -md sha256`, so an archive can be opened with the openssl CLI
    | alone if the app is unavailable (see README -> Backups). Don't change
    | them after archives exist — old archives would stop decrypting.
    */
    'pbkdf2_iterations' => 100000,

];
