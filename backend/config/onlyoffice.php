<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OnlyOffice Document Server
    |--------------------------------------------------------------------------
    |
    | Leave ONLYOFFICE_URL empty to disable embedded Word editing (TipTap remains
    | the editor for HTML drafts). When set, uploaded DOCX files open in the
    | OnlyOffice iframe when ONLYOFFICE_JWT_SECRET matches the Document Server.
    |
    */

    'url' => env('ONLYOFFICE_URL'),

    'jwt_secret' => env('ONLYOFFICE_JWT_SECRET'),

    /** Signed download URL lifetime (minutes) for Document Server fetches. */
    'file_url_ttl' => (int) env('ONLYOFFICE_FILE_URL_TTL', 120),

    /** Word MIME types eligible for OnlyOffice editing. */
    'word_mime_types' => [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword',
    ],

];
