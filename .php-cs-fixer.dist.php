<?php

declare(strict_types=1);


use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
		'@Symfony' => true,
		'declare_strict_types' => true,
    ])
    ->setFinder(
        (new Finder())
            ->ignoreVCSIgnored(true)
			->in(__DIR__)
    )
;
