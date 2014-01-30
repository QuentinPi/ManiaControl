<?php
/**
 * Class for managing Timers
 *
 * @author steeffeen & kremsy
 */
namespace ManiaControl\Callbacks;


use ManiaControl\ManiaControl;

class TimerManager {
	private $maniaControl = null;
	private $timerListenings = array();

	/**
	 * Construct a new Timer Manager
	 *
	 * @param \ManiaControl\ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;
	}


	/**
	 * Registers a One Time Listening
	 *
	 * @param TimerListener $listener
	 * @param               $method
	 * @param               $time
	 */
	public function registerOneTimeListening(TimerListener $listener, $method, $time) {
		$this->registerTimerListening($listener, $method, $time, true);
	}

	/**
	 * Registers a Timing Listening, note < 10ms it can get inaccurate
	 *
	 * @param TimerListener $listener
	 * @param               $method
	 * @param               $time
	 * @return bool
	 */
	public function registerTimerListening(TimerListener $listener, $method, $time, $oneTime = false) {
		if (!method_exists($listener, $method)) {
			trigger_error("Given listener (" . get_class($listener) . ") can't handle timer (no method '{$method}')!");
			return false;
		}

		//Init the Timer Listening
		$listening              = new \stdClass();
		$listening->listener    = $listener;
		$listening->method      = $method;
		$listening->deltaTime   = $time / 1000;
		$listening->lastTrigger = -1;
		$listening->oneTime     = $oneTime;

		array_push($this->timerListenings, $listening);

		return true;
	}

	/**
	 * Remove a Script Callback Listener
	 *
	 * @param CallbackListener $listener
	 * @return bool
	 */
	public function unregisterTimerListenings(CallbackListener $listener) {
		$removed = false;
		foreach($this->timerListenings as $key => &$listening) {
			if ($listening->listener != $listener) {
				continue;
			}
			unset($this->timerListenings[$key]);
			$removed = true;
		}
		return $removed;
	}

	/**
	 * Manage the Timings on every ms
	 */
	public function manageTimings() {
		$time = microtime(true);
		foreach($this->timerListenings as $key => &$listening) {
			if (($listening->lastTrigger + $listening->deltaTime) <= $time) {
				call_user_func(array($listening->listener, $listening->method), $time);

				//Unregister one time Listening
				if ($listening->oneTime == true) {
					unset($this->timerListenings[$key]);
					continue;
				}

				if ($listening->lastTrigger != -1) {
					$listening->lastTrigger += $listening->deltaTime;
				} else {
					//Initial Time Initialize (self increment needed to improve accuracy)
					$listening->lastTrigger = microtime(true);
				}
			}
		}
	}
} 