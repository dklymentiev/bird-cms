<?php
declare(strict_types=1);

// Author registry. Articles can reference an author by slug in their .meta.yaml
// (`author: editorial-team`); the article view looks them up here.
//
// `editorial-team` is the default fallback for articles without an explicit
// author and is required even if you only have one writer -- get_author(null)
// returns this entry.

return [
    'editorial-team' => [
        'name'       => 'Editorial Team',
        'role'       => 'Editorial',
        'bio'        => 'The editorial team behind this Bird CMS site.',
        'avatar'     => '/assets/brand/bird-logo.svg',
        'social'     => [],
    ],
];
