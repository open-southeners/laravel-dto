<?php

return [

    /**
     * Normalise data transfer objects property names.
     *
     * For example: user_id (sent) => user (DTO) or is_published (sent) => isPublished (DTO)
     */
    'normalise_properties' => true,

    /**
     * Default wrapping for array inputs when no ->through() is given.
     *
     * 'array' returns plain PHP arrays, \Illuminate\Support\Collection::class
     * returns collections. Overridable per-mapping with ->through().
     */
    'map_arrays_through' => 'array',

    /**
     * Types generator config, only used as defaults when no options
     * are passed to the command.
     */
    'types_generation' => [

        'output' => null,

        'source' => null,

        'filename' => null,

        'declarations' => false,

    ],

];
