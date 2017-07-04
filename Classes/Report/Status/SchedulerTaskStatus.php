<?php
/**
 * Created by PhpStorm.
 * User: cwolff
 * Date: 24.05.2017
 * Time: 15:04
 */

namespace Mindscreen\SchedulerReports\Report\Status;


use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Scheduler\Task;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Reports\Status;
use TYPO3\CMS\Reports\StatusProviderInterface;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

class SchedulerTaskStatus implements StatusProviderInterface
{
    /**
     * @var DatabaseConnection
     */
    protected $database;

    /**
     * @var \TYPO3\CMS\Scheduler\Scheduler Local scheduler instance
     */
    protected $scheduler;


    /**
     * @return array
     */
    public function getStatus()
    {
        $result = [];

        $result[] = $this->getLastRunStatus();

        $this->database = $GLOBALS['TYPO3_DB'];
        $this->scheduler = GeneralUtility::makeInstance(\TYPO3\CMS\Scheduler\Scheduler::class);

        $registeredClasses = $this->getRegisteredClasses();

        // Get all registered tasks
        $query = array(
            'SELECT' => '
                tx_scheduler_task.*,
                tx_scheduler_task_group.groupName as taskGroupName,
                tx_scheduler_task_group.description as taskGroupDescription,
                tx_scheduler_task_group.deleted as isTaskGroupDeleted
                ',
            'FROM' => '
                tx_scheduler_task
                LEFT JOIN tx_scheduler_task_group ON tx_scheduler_task_group.uid = tx_scheduler_task.task_group
                ',
            'WHERE' => '1=1',
            'ORDERBY' => 'tx_scheduler_task_group.sorting'
        );
        $res = $this->database->exec_SELECT_queryArray($query);

        $dateFormat = $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] . ' ' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'] . ' T (e)';

        while ($schedulerRecord = $this->database->sql_fetch_assoc($res)) {
            /** @var AbstractTask $task */
            $task = unserialize($schedulerRecord['serialized_task_object']);
            $name = ($schedulerRecord['taskGroupName'] ? $schedulerRecord['taskGroupName'] . ' ' : '') . 'Task ' . $schedulerRecord['uid'];

            if ($task->getType() === AbstractTask::TYPE_RECURRING) {
                $class = get_class($task);
                if ($class === '__PHP_Incomplete_Class' && preg_match('/^O:[0-9]+:"(?P<classname>.+?)"/',
                        $schedulerRecord['serialized_task_object'], $matches) === 1
                ) {
                    $class = $matches['classname'];
                }

                if (isset($registeredClasses[get_class($task)]) && $this->scheduler->isValidTaskObject($task)) {
                    $name .= ' (';
                    if ($class == Task::class) {
                        $name .= htmlspecialchars($task->getAdditionalInformation());
                    } else {
                        $name .= htmlspecialchars($registeredClasses[$class]['title']);
                    }
                    $name .= ')';

                    $maximumDelay = $GLOBALS['TYPO3_CONF_VARS']['scheduler_reports']['defaults']['maximumDelay'];
                    $maximumExecutionTime = $GLOBALS['TYPO3_CONF_VARS']['scheduler_reports']['defaults']['maximumExecutionTime'];

                    // TODO: Use task specific delay / execution time if it exists in database

                    if ($schedulerRecord['disable']) {
                        // Warning for disabled task
                        $result[] = GeneralUtility::makeInstance(Status::class,
                            $name,
                            'Task is disabled.',
                            '',
                            Status::WARNING
                        );
                        continue;
                    }

                    $taskInformation = [];
                    $status = Status::OK;
                    $value = 'OK';

                    // Assemble information about last execution
                    if (!empty($schedulerRecord['lastexecution_time'])) {
                        $taskInformation[] = 'Last execution: ' . date($dateFormat, $schedulerRecord['lastexecution_time']);
                        $taskInformation[] = 'Context: ' . $schedulerRecord['lastexecution_context'];
                    }

                    // Assemble information about next execution
                    if (!empty($schedulerRecord['nextexecution'])) {
                        $taskInformation[] = 'Next execution: ' . date($dateFormat, $schedulerRecord['nextexecution']);
                    }

                    // Check if task currently has a running execution
                    if (!empty($schedulerRecord['serialized_executions'])) {
                        $isRunning = true;
                    } else {
                        $isRunning = false;
                    }

                    $late = $GLOBALS['EXEC_TIME'] - $schedulerRecord['nextexecution'];
                    if ($late > $maximumDelay) {
                        $value = 'Task is more than ' . $maximumDelay . ' minutes late.';
                        $status = Status::WARNING;
                    }

                    // Check if the last run failed
                    if (!empty($schedulerRecord['lastexecution_failure'])) {
                        $value = 'Exception during last execution.';
                        $status = Status::ERROR;
                        // Try to get the stored exception array
                        /** @var $exceptionArray array */
                        $exceptionArray = @unserialize($schedulerRecord['lastexecution_failure']);
                        // If the exception could not be unserialized, issue a default error message
                        if (!is_array($exceptionArray) || empty($exceptionArray)) {
                            $taskInformation[] = 'Unknown exception during last execution.';
                        } else {
                            $taskInformation[] = sprintf('Exception %s (%s)', $exceptionArray['code'],
                                $exceptionArray['message']);
                        }
                    }

                    // Add task status
                    $result[] = GeneralUtility::makeInstance(Status::class,
                        $name,
                        $value . ($isRunning ? ' (currently running)' : ''),
                        implode('<br />' . "\n", $taskInformation),
                        $status
                    );

                } else {
                    // Error for invalid task
                    $result[] = GeneralUtility::makeInstance(Status::class,
                        $name .' (' . $class . ')',
                        'Task is invalid.',
                        '',
                        Status::ERROR
                    );
                }
            }
        }
        return $result;
    }

    protected function getLastRunStatus()
    {
        $registry = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Registry::class);
        $lastRun = $registry->get('tx_scheduler', 'lastRun');

        if (!is_array($lastRun)) {
            $value = 'No scheduler run so far.';
            $severity = Status::ERROR;
        } else {
            if (empty($lastRun['end']) || empty($lastRun['start']) || empty($lastRun['type'])) {
                $value = 'Last scheduler run was incomplete.';
                $severity = Status::ERROR;
            } else {
                if (time() - $lastRun['start'] > $GLOBALS['TYPO3_CONF_VARS']['scheduler_reports']['maximumTimeSinceLastExecution'] * 60) {
                    $value = 'Last execution more than ' . $GLOBALS['TYPO3_CONF_VARS']['scheduler_reports']['maximumTimeSinceLastExecution'] . ' minutes ago.';
                    $severity = Status::ERROR;
                } else {
                    if ($lastRun['type'] === 'manual') {
                        $value = 'Last scheduler run triggered manually.';
                        $severity = Status::WARNING;
                    } else {
                        $value = 'OK';
                        $severity = Status::OK;
                    }
                }
            }
        }
        return GeneralUtility::makeInstance(Status::class,
            'Cron configuration',
            $value,
            '',
            $severity
        );
    }

    /**
     * This method is copied from SchedulerModuleController
     *
     * This method fetches a list of all classes that have been registered with the Scheduler
     * For each item the following information is provided, as an associative array:
     *
     * ['extension']	=>	Key of the extension which provides the class
     * ['filename']		=>	Path to the file containing the class
     * ['title']		=>	String (possibly localized) containing a human-readable name for the class
     * ['provider']		=>	Name of class that implements the interface for additional fields, if necessary
     *
     * The name of the class itself is used as the key of the list array
     *
     * @return array List of registered classes
     */
    protected function getRegisteredClasses()
    {
        $list = array();
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'] as $class => $registrationInformation) {
                $title = isset($registrationInformation['title']) ? $this->getLanguageService()->sL($registrationInformation['title']) : '';
                $description = isset($registrationInformation['description']) ? $this->getLanguageService()->sL($registrationInformation['description']) : '';
                $list[$class] = array(
                    'extension' => $registrationInformation['extension'],
                    'title' => $title,
                    'description' => $description,
                    'provider' => isset($registrationInformation['additionalFields']) ? $registrationInformation['additionalFields'] : ''
                );
            }
        }
        return $list;
    }

    /**
     * Returns the Language Service
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}