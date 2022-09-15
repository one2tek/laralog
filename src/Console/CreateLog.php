<?php

namespace one2tek\laralog\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use one2tek\laralog\Models\LaraLog;

class CreateLog implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	private $data;

	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	public function __construct($data)
	{
		$this->data = $data;
	}

	/**
	 * Execute the job.
	 * @return void
	 * @throws Exception
	 */
	public function handle()
	{
		try {
			(new LaraLog())->create($this->data);
		} catch (Exception $e) {
			throw $e;
		}
	}
}
