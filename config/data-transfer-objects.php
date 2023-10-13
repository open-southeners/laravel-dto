<?php

return [

    /**
     * Normalise data transfer objects property names.
     * 
     * For example: user_id (sent) => user (DTO) or is_published (sent) => isPublished (DTO)
     */
    'normalise_properties' => true,

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
