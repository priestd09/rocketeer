<?php
/*
 * This file is part of Rocketeer
 *
 * (c) Maxime Fabre <ehtnam6@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Rocketeer\Console\Commands;

use Rocketeer\Abstracts\Task;

/**
 * A command that wraps around a Task class and runs
 * its execute method on fire
 *
 * @author Maxime Fabre <ehtnam6@gmail.com>
 */
class BaseTaskCommand extends AbstractDeployCommand
{
	/**
	 * The default name
	 *
	 * @var string
	 */
	protected $name = 'deploy:custom';

	/**
	 * The Task to execute on fire
	 *
	 * @var Task
	 */
	protected $task;

	/**
	 * Build a new custom command
	 *
	 * @param Task        $task
	 * @param string|null $name A name for the command
	 */
	public function __construct(Task $task, $name = null)
	{
		parent::__construct();

		// Set Task
		$this->task          = $task;
		$this->task->command = $this;

		// Set name
		$this->name = $name ?: $task->getSlug();
		$this->name = 'deploy:'.$this->name;

		// Set description
		$this->setDescription($task->getDescription());
	}

	/**
	 * Fire the custom Task
	 */
	public function fire()
	{
		$this->fireTasksQueue($this->task->getSlug());
	}

	/**
	 * Get the Task this command executes
	 *
	 * @return Task
	 */
	public function getTask()
	{
		return $this->task;
	}
}