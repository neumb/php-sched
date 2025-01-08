<?php

declare(strict_types=1);


use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
		'@Symfony' => true,
		'declare_strict_types' => true,
		'phpdoc_to_comment' => [
			'ignored_tags' => [
				'var',
			],
		]
    ])
    ->setFinder(
        (new Finder())
            ->ignoreVCSIgnored(true)
			->in(__DIR__)
    )
;
