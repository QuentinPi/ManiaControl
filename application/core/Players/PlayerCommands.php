<?php

namespace ManiaControl\Players;

use FML\Controls\Quads\Quad_Icons128x32_1;
use FML\Controls\Quads\Quad_UIConstruction_Buttons;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Commands\CommandListener;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Server\Server;

/**
 * Class offering various Admin Commands related to Players
 *
 * @author steeffeen & kremsy
 */
class PlayerCommands implements CommandListener, ManialinkPageAnswerListener, CallbackListener {
	/**
	 * Constants
	 */
	const ACTION_BALANCE_TEAMS            = 'PlayerCommands.BalanceTeams';
	const ACTION_OPEN_PLAYERLIST          = 'PlayerCommands.OpenPlayerList';
	const SETTING_PERMISSION_ADD_BOT      = 'Add Bot';
	const SETTING_PERMISSION_TEAM_BALANCE = 'Balance Teams';

	/**
	 * Private Properties
	 */
	private $maniaControl = null;

	/**
	 * Create a new Player Commands Instance
	 *
	 * @param ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Register for admin commands
		$this->maniaControl->commandManager->registerCommandListener('balance', $this, 'command_TeamBalance', true);
		$this->maniaControl->commandManager->registerCommandListener('teambalance', $this, 'command_TeamBalance', true);
		$this->maniaControl->commandManager->registerCommandListener('autoteambalance', $this, 'command_TeamBalance', true);
		$this->maniaControl->commandManager->registerCommandListener('kick', $this, 'command_Kick', true);
		$this->maniaControl->commandManager->registerCommandListener('forcespec', $this, 'command_ForceSpectator', true);
		$this->maniaControl->commandManager->registerCommandListener('forcespectator', $this, 'command_ForceSpectator', true);
		$this->maniaControl->commandManager->registerCommandListener('forceplay', $this, 'command_ForcePlay', true);
		$this->maniaControl->commandManager->registerCommandListener('forceblue', $this, 'command_ForceBlue', true);
		$this->maniaControl->commandManager->registerCommandListener('forcered', $this, 'command_ForceRed', true);
		$this->maniaControl->commandManager->registerCommandListener('addbot', $this, 'command_AddFakePlayers', true);
		$this->maniaControl->commandManager->registerCommandListener('removebot', $this, 'command_RemoveFakePlayers', true);
		$this->maniaControl->commandManager->registerCommandListener('addbots', $this, 'command_AddFakePlayers', true);
		$this->maniaControl->commandManager->registerCommandListener('removebots', $this, 'command_RemoveFakePlayers', true);
		$this->maniaControl->commandManager->registerCommandListener('mute', $this, 'command_MutePlayer', true);
		$this->maniaControl->commandManager->registerCommandListener('unmute', $this, 'command_UnmutePlayer', true);

		// Register for player chat commands
		$this->maniaControl->commandManager->registerCommandListener('player', $this, 'command_playerList');
		$this->maniaControl->commandManager->registerCommandListener('players', $this, 'command_playerList');

		//Define Rights
		$this->maniaControl->authenticationManager->definePermissionLevel(self::SETTING_PERMISSION_ADD_BOT, AuthenticationManager::AUTH_LEVEL_MODERATOR);
		$this->maniaControl->authenticationManager->definePermissionLevel(self::SETTING_PERMISSION_TEAM_BALANCE, AuthenticationManager::AUTH_LEVEL_MODERATOR);

		//CallbackManager
		$this->maniaControl->callbackManager->registerCallbackListener(Server::CB_TEAM_STATUS_CHANGED, $this, 'teamStatusChanged');

		// Action Open Playerlist
		$this->maniaControl->manialinkManager->registerManialinkPageAnswerListener(self::ACTION_OPEN_PLAYERLIST, $this, 'command_playerList');
		$itemQuad = new Quad_UIConstruction_Buttons();
		$itemQuad->setSubStyle($itemQuad::SUBSTYLE_Author);
		$itemQuad->setAction(self::ACTION_OPEN_PLAYERLIST);
		$this->maniaControl->actionsMenu->addMenuItem($itemQuad, true, 15, 'Open Playerlist');
	}

	/**
	 * Handle TeamStatusChanged
	 * @param array $callback
	 */
	public function teamStatusChanged(array $callback) {
		$status =  $callback[1];
		//Add Balance Team Icon if it's a teamMode
		if ($status) {
			// Action Balance Teams
			$this->maniaControl->manialinkManager->registerManialinkPageAnswerListener(self::ACTION_BALANCE_TEAMS, $this, 'command_TeamBalance');
			$itemQuad = new Quad_Icons128x32_1();
			$itemQuad->setSubStyle($itemQuad::SUBSTYLE_RT_Team);
			$itemQuad->setAction(self::ACTION_BALANCE_TEAMS);
			$this->maniaControl->actionsMenu->addMenuItem($itemQuad, false, 40, 'Balance Teams');
		}
	}

	/**
	 * Handle //teambalance command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_TeamBalance(array $chatCallback, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTING_PERMISSION_TEAM_BALANCE)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}

		try {
			$this->maniaControl->client->autoTeamBalance();
		} catch(\Exception $e) {
			$this->maniaControl->chat->sendError('Error occurred: ' . $e->getMessage(), $player->login);
			return;
		}

		$this->maniaControl->chat->sendInformation('$<' . $player->nickname . '$> balanced Teams!');
	}

	/**
	 * Handle //kick command
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function command_Kick(array $chat, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, PlayerActions::SETTING_PERMISSION_KICK_PLAYER)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$params = explode(' ', $chat[1][2], 3);
		if (count($params) <= 1) {
			$this->maniaControl->chat->sendUsageInfo("No Login given! Example: '//kick login'", $player->login);
			return;
		}
		$targetLogin = $params[1];
		$message     = '';
		if (isset($params[2])) {
			$message = $params[2];
		}
		$this->maniaControl->playerManager->playerActions->kickPlayer($player->login, $targetLogin, $message);
	}

	/**
	 * Handle //warn Command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_Warn(array $chat, Player $player) {
		$params = explode(' ', $chat[1][2], 3);
		if (count($params) <= 1) {
			$this->maniaControl->chat->sendUsageInfo("No Login given! Example: '//kick login'", $player->login);
			return;
		}
		$targetLogin = $params[1];
		$this->maniaControl->playerManager->playerActions->warnPlayer($player->login, $targetLogin);
	}

	/**
	 * Handle //forcespec command
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function command_ForceSpectator(array $chat, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, PlayerActions::SETTING_PERMISSION_FORCE_PLAYER_SPEC)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$params = explode(' ', $chat[1][2]);
		if (count($params) <= 1) {
			$this->maniaControl->chat->sendUsageInfo("No Login given! Example: '//forcespec login'", $player->login);
			return;
		}
		$targetLogin = $params[1];

		if (isset($params[2]) && is_numeric($params[2])) {
			$type = (int)$params[2];
			$this->maniaControl->playerManager->playerActions->forcePlayerToSpectator($player->login, $targetLogin, $type);
		} else {
			$this->maniaControl->playerManager->playerActions->forcePlayerToSpectator($player->login, $targetLogin);
		}
	}

	/**
	 * Handle //forceplay command
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function command_ForcePlay(array $chat, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, PlayerActions::SETTING_PERMISSION_FORCE_PLAYER_PLAY)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$params = explode(' ', $chat[1][2]);
		if (!isset($params[1])) {
			$this->maniaControl->chat->sendUsageInfo("No Login given! Example: '//forceplay login'", $player->login);
			return;
		}
		$targetLogin = $params[1];

		$type = 2;
		if (isset($params[2]) && is_numeric($params[2])) {
			$type = intval($params[2]);
		}
		$selectable = false;
		if ($type == 2) {
			$selectable = true;
		}

		$this->maniaControl->playerManager->playerActions->forcePlayerToPlay($player->login, $targetLogin, $selectable);
	}

	/**
	 * Handle //forceblue command
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function command_ForceBlue(array $chat, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, PlayerActions::SETTING_PERMISSION_FORCE_PLAYER_TEAM)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$params = explode(' ', $chat[1][2]);
		if (!isset($params[1])) {
			$this->maniaControl->chat->sendUsageInfo("No Login given! Example: '//forceblue login'", $player->login);
			return;
		}
		$targetLogin = $params[1];

		$this->maniaControl->playerManager->playerActions->forcePlayerToTeam($player->login, $targetLogin, PlayerActions::TEAM_BLUE);
	}

	/**
	 * Handle //forcered command
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function command_ForceRed(array $chat, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, PlayerActions::SETTING_PERMISSION_FORCE_PLAYER_TEAM)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$params = explode(' ', $chat[1][2]);
		if (!isset($params[1])) {
			$this->maniaControl->chat->sendUsageInfo("No Login given! Example: '//forcered login'", $player->login);
			return;
		}
		$targetLogin = $params[1];

		$this->maniaControl->playerManager->playerActions->forcePlayerToTeam($player->login, $targetLogin, PlayerActions::TEAM_RED);
	}

	/**
	 * Handle //addfakeplayers command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_AddFakePlayers(array $chatCallback, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTING_PERMISSION_ADD_BOT)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$amount       = 1;
		$messageParts = explode(' ', $chatCallback[1][2]);
		if (isset($messageParts[1]) && is_numeric($messageParts[1])) {
			$amount = intval($messageParts[1]);
		}
		try {
			for($i = 0; $i < $amount; $i++) {
				$this->maniaControl->client->connectFakePlayer();
			}
		} catch(\Exception $e) {
			$this->maniaControl->chat->sendError('Error occurred: ' . $e->getMessage(), $player->login);
			return;
		}

		$this->maniaControl->chat->sendSuccess('Fake players connected!', $player->login);
	}

	/**
	 * Handle //removefakeplayers command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_RemoveFakePlayers(array $chatCallback, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTING_PERMISSION_ADD_BOT)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}

		try {
			$this->maniaControl->client->disconnectFakePlayer('*');
		} catch(\Exception $e) {
			$this->maniaControl->chat->sendError('Error occurred: ' . $e->getMessage(), $player->login);
			return;
		}

		$this->maniaControl->chat->sendSuccess('Fake players disconnected!', $player->login);
	}

	/**
	 * Handle //mute Command
	 *
	 * @param array  $chatCallback
	 * @param Player $admin
	 */
	public function command_MutePlayer(array $chatCallback, Player $admin) {
		$commandParts = explode(' ', $chatCallback[1][2]);
		if (count($commandParts) <= 1) {
			$this->maniaControl->chat->sendUsageInfo("No login specified! Example: '//mute login'");
			return;
		}
		$targetLogin = $commandParts[1];
		$this->maniaControl->playerManager->playerActions->mutePlayer($admin->login, $targetLogin);
	}

	/**
	 * Handle //unmute Command
	 *
	 * @param array  $chatCallback
	 * @param Player $admin
	 */
	public function command_UnmutePlayer(array $chatCallback, Player $admin) {
		$commandParts = explode(' ', $chatCallback[1][2]);
		if (count($commandParts) <= 1) {
			$this->maniaControl->chat->sendUsageInfo("No login specified! Example: '//unmute login'");
			return;
		}
		$targetLogin = $commandParts[1];
		$this->maniaControl->playerManager->playerActions->unMutePlayer($admin->login, $targetLogin);
	}

	/**
	 * Handle Player list command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_playerList(array $chatCallback, Player $player) {
		$this->maniaControl->playerManager->playerList->addPlayerToShownList($player, PlayerList::SHOWN_MAIN_WINDOW);
		$this->maniaControl->playerManager->playerList->showPlayerList($player);
	}
}
