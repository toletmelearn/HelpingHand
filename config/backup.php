<?php

return [

    /*
    |--------------------------------------------------------------------------
    | mysqldump Binary Path
    |--------------------------------------------------------------------------
    |
    | Path to the mysqldump executable used to create real database backups.
    | Defaults to the standard XAMPP install location on Windows development
    | machines; override via MYSQLDUMP_PATH in .env for any other
    | environment (a bare "mysqldump" is fine if it's on the system PATH).
    |
    */
    'mysqldump_path' => env(
        'MYSQLDUMP_PATH',
        PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/mysql/bin/mysqldump.exe' : 'mysqldump'
    ),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Number of completed backups to keep. Older completed backups (files
    | and their DB records) are pruned only after a new backup has
    | completed successfully.
    |
    */
    'retention_count' => env('BACKUP_RETENTION_COUNT', 14),

    /*
    |--------------------------------------------------------------------------
    | mysqldump Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum seconds allowed for the mysqldump process before it is
    | considered failed.
    |
    */
    'timeout_seconds' => env('BACKUP_TIMEOUT_SECONDS', 300),

];
