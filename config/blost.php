<?php

return [
    'token' => env('BLOST_TOKEN', ''),
    'name' => env('APP_NAME', 'Blost connector'),
    'publisher' => null, // Class implementing Corpuscom\Blost\Contract\ArticlePublisher.
];
