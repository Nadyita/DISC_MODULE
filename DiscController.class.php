<?php

namespace Budabot\User\Modules;

use DateTime;
use DateTimeZone;

/**
 * @author Nadyita (RK5)
 *
 * @Instance
 *
 * Commands this controller contains:
 *	@DefineCommand(
 *		command     = 'disc',
 *		accessLevel = 'all',
 *		description = 'Show which nano a disc will turn into',
 *		help        = 'disc.txt'
 *	)
 */
class DiscController {

	/**
	 * Name of the module.
	 * Set automatically by module loader.
	 * @var string $moduleName
	 */
	public $moduleName;

	/**
	 * @var \Budabot\Core\Budabot $chatBot
	 * @Inject
	 */
	public $chatBot;

	/**
	 * @var \Budabot\Core\Text $text
	 * @Inject
	 */
	public $text;

	/**
	 * @var \Budabot\Core\DB $db
	 * @Inject
	 */
	public $db;

	/**
	 * @Setup
	 * This handler is called on bot startup.
	 */
	public function setup() {
		// load database tables from .sql-files
		$this->db->loadSQLFile($this->moduleName, 'discs');
	}

	/**
	 * Get the instruction disc from its name and return an array with results
	 *
	 * @param string $discName Name of the instruction disc
	 * @return \Budabot\Core\DBRow[] An array of database entries that matched
	 */
	public function getDiscsByName($discName) {
		$sql = 'SELECT * FROM discs WHERE disc_name LIKE ?';
		return $this->db->query($sql, "%${discName}%");
	}

	/**
	 * Get the instruction disc from its id and return the result or null
	 *
	 * @param int $discId Instruction disc id
	 * @return \Budabot\Core\DBRow|null The database entry or null if not found
	 */
	public function getDiscById($discId) {
		$sql = "SELECT * FROM discs WHERE disc_id = ?";
		return $this->db->queryRow($sql, $discId);
	}

	/**
	 * Command to show what nano a disc will turn into
	 *
	 * @param string                     $message The full command received
	 * @param string                     $channel Where did the command come from (tell, guild, priv)
	 * @param string                     $sender  The name of the user issuing the command
	 * @param \Budabot\Core\CommandReply $sendto  Object to use to reply to
	 * @param string[]                   $args    The arguments to the disc-command
	 * @return void
	 *
	 * @HandlesCommand("disc")
	 * @Matches("/^disc (.+)$/i")
	 */
	public function discCommand($message, $channel, $sender, $sendto, $args) {
		$disc = null;
		// Check if a disc was pasted into the chat and extract its ID
		if (preg_match("|<a href=['\"]itemref://(?<lowId>\d+)/(?<highId>\d+)/(?<ql>\d+)['\"]>(?<name>.+?)</a>|", $args[1], $matches)) {
			$discId = $matches['lowId'];
			// If there is a DiscID deducted, get the nano crystal ID and name
			$disc = $this->getDiscById($discId);
			// None found? Cannot be made into a nano anymore
			if ($disc === null) {
				if (!preg_match('|instruction\s*dis[ck]|i', $matches["name"])) {
					$msg = $args[1] . " is not an instruction disc.";
				} else {
					$msg = $args[1] . " cannot be made into a nano anymore.";
				}
				$sendto->reply($msg);
				return;
			}
		} else {
			// If only a name was given, lookup the disc's ID
			$discs = $this->getDiscsByName($args[1]);
			// Not found? Cannot be made into a nano anymore or simply mistyped
			if (empty($discs)) {
				$msg = "Either <highlight>" . $args[1] . "<end> was mistyped or it cannot be turned into a nano anymore.";
				$sendto->reply($msg);
				return;
			}
			// If there are multiple matches, present a list of discs to chose from
			if (count($discs) > 1) {
				$sendto->reply($this->getDiscChoiceDialogue($discs));
				return;
			}
			// Only one found, so pick this one
			$disc = $discs[1];
		}

		// Now we have exactly one nano. Show it to the user
		$discLink = $this->text->makeItem($disc->disc_id, $disc->disc_id, $disc->disc_ql, $disc->disc_name);
		$nanoLink = $this->text->makeItem($disc->crystal_id, $disc->crystal_id, $disc->crystal_ql, $disc->crystal_name);
		$msg = "${discLink} will turn into ${nanoLink}.";
		$sendto->reply($msg);
	}

	/**
	 * Generate a choice dialogue if multiple discs match the search criteria
	 *
	 * @param \Budabot\Core\DBRow[] $discs The discs that matched the search
	 * @return string The dialogue to display
	 */
	public function getDiscChoiceDialogue($discs) {
		$blob = array();
		foreach ($discs as $disc) {
			$discLink = $this->text->makeItem($disc->disc_id, $disc->disc_id, $disc->disc_ql, $disc->disc_name);
			$text = $this->text->makeChatcmd($disc->disc_name, "/tell <myname> disc ${discLink}");
			$blob []= $text;
		}
		$msg = $this->text->makeBlob(
			count($discs). " matches matching your search",
			implode("\n<pagebreak>", $blob),
			"Multiple matches, please choose one"
		);
		if (is_array($msg)) {
			return array_map(
				function($blob) {
					return "Found ${blob}.";
				},
				$msg
			);
		}
		return "Found ${msg}.";
	}
}
