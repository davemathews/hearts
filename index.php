<?php
error_reporting(E_ALL);
session_start();

$start = microtime(true);

require 'functions.php';
require 'Game.php';
require 'Player.php';
require 'Displayer.php';

unset($game);

// Stop game if requested
if (isset($_GET['stop_game'])) {
	$_SESSION['game'] = null;
	session_destroy();
}

// Load or create game
if (isset($_POST['new_game']))
{
	$game = new Game;
	//save_game_to_file($game);
}
else if (isset($_SESSION['game']))
{
	$game = unserialize($_SESSION['game']);
	if (!($game instanceof Game)) {
		session_destroy();
		throw new Exception('Could not load the game! Please reload.');
	}
}
else
{
	// Offer to create a new game if we don't have any session.
	echo '<form action="'.$_SERVER['PHP_SELF'].'" method="post">
<input type="submit" style="margin: auto" value="Create new game" name="new_game" />
</form>';
	exit;
}

$displayer = new Displayer($game);
$state = $game->getState();

if ($state === Game::HAND_START) {
	// If this method didn't change the game state, this would open a door to
	// cheating, because cards would be distributed upon every reload.
	$game->startNewHand();
	$start_player = $game->getCurrentRoundStarter();
	if ($start_player === Game::HUMAN_ID) {
		$displayer->draw('Must start with the two of clubs', true);
	}
	else {
		$msg = 'Player ' . ($start_player+1) . ' begins the hand.';
		$displayer->draw($msg, false);
		$game->playTillHuman();
	}
	save_game_to_session($game);
}
else if ($state === Game::AWAITING_CLUBS) {
	// Important to manually check here that we got the two of clubs
	if (post_is_valid_card_format('card') && $_POST['card'] == Game::CLUBS . 2) {
		$result = $game->processHumanMove($_POST['card']);
		if ($result === Game::MOVE_OK) {
			$nextRoundStarter = $game->playTillEnd();
			$displayer->roundEndMessage($nextRoundStarter);
			save_game_to_session($game);
		} else {
			// Throw an exception since we just checked that we got
			// the two of clubs. We should never be in this clause!
			throw new Exception('Game should have accepted two of Clubs!');
		}
	}
	else {
		$message = '<span class="error">Must start with the two of clubs!</span>';
		$displayer->draw($message, true);
	}
}
else if ($state === Game::AWAITING_HUMAN) {
	if (post_is_valid_card_format('card')) {
		$result = $game->processHumanMove($_POST['card']);
		if ($result === Game::MOVE_OK) {
			$nextRoundStarter = $game->playTillEnd();
			$displayer->roundEndMessage($nextRoundStarter);
			save_game_to_session($game);
		}
		else {
			$displayer->handleCardError($result);
		}
	} else {
		$displayer->draw('Your turn.', true);
	}
}
else if ($state === Game::ROUND_END) {
	$game->playTillHuman();
	$displayer->draw('Your turn', true);
	save_game_to_session($game);
}
else if ($state === Game::GAME_END) {
	$displayer->roundEndMessage(null);
}
else {
	var_dump($game->getState());
	throw new Exception("Encountered unknown game state");
}

$end = microtime(true);
$gen_time = round($end - $start, 3);
echo '<p class="footer">
 Page generated in ' . $gen_time . '&nbsp;s
 <br /><a href="?stop_game">Stop game?</a>
</p>';
?>

 </body>
</html>