<?php

$EM_CONF[$_EXTKEY] = array (
    'title' => 'Scheduler Reports',
    'description' => 'Add a report about scheduler jobs to the reports module. Helpful for monitoring.',
    'category' => 'be',
    'version' => '0.1.0',
    'state' => 'beta',
    'clearcacheonload' => 0,
    'author' => 'Thomas Heilmann',
    'author_email' => 'heilmann@mindscreen.de',
    'author_company' => 'mindscreen GmbH',
    'constraints' => array(
        'depends' => array(
            'typo3' => '6.2.0-7.9.99',
        ),
        'conflicts' => array(
        ),
        'suggests' => array(
        ),
    ),
);
