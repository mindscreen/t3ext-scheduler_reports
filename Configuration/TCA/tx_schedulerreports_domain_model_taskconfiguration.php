<?php
return array(
    'ctrl' => array(
        'title'	=> 'Scheduler Task Configuration',
        'label' => 'task_uid',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'dividers2tabs' => true,
        'adminOnly' => 1, // Only admin users can edit
        'rootLevel' => 1,

        'searchFields' => '',
        'iconfile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('scheduler_reports') . 'Resources/Public/Icons/tx_schedulerreports_domain_model_taskconfiguration.gif'
    ),
    'interface' => array(
        'showRecordFieldList' => 'task_uid, maximum_execution_time, maxiumm_delay',
    ),
    'types' => array(
        '1' => array('showitem' => 'task_uid, maximum_execution_time, maxiumm_delay'),
    ),
    'palettes' => array(
        '1' => array('showitem' => ''),
    ),
    'columns' => array(
        'task_uid' => array(
            'exclude' => 0,
            'label' => 'Task',
            'config' => array(
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,int,required,unique'
            ),
        ),
        'maximum_execution_time' => array(
            'exclude' => 1,
            'label' => 'Maximum execution time (in minutes)',
            'config' => array(
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,int,required'
            ),
        ),
        'maxiumm_delay' => array(
            'exclude' => 1,
            'label' => 'Maximum delay (in minutes)',
            'config' => array(
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,int,required'
            ),
        ),
    ),
);
