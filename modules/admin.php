<?php

/**
 * This module covers 'administration' commands available only to blueshirts.
 */
class AdminModule extends Module
{
	/**
	 * The username of the user currently using the module.
	 * @todo Remove this, and use $auth->getCurrentUser() in all instances?
	 */
	private $username;

	/**
	 * Standard constructor: installs commands.
	 */
	public function __construct()
	{
		$this->installCommand('team-name-put',   array($this, 'saveTeamName'));
		$this->installCommand('feed-status-get', array($this, 'getBlogFeeds'));
		$this->installCommand('feed-status-put', array($this, 'setFeedStatus'));
	}

	/**
	 * Ensures that we have a valid user.
	 * You can't do anything user related without being authed, but putting
	 * this in the constructor causes issues, since construction occurs
	 * before the auth cycle does.
	 */
	private function ensureAuthed()
	{
		$auth = AuthBackend::getInstance();
		if (!($this->username = $auth->getCurrentUser()))
		{
			throw new Exception('You are not logged in', E_PERM_DENIED);
		}
		if (!$auth->isCurrentUserAdmin())
		{
			throw new Exception('You do not have admin privileges',
			                    E_PERM_DENIED);
		}
	}

	/**
	 * Handles a request for a user team name change via the default strategy.
	 *
	 * This can be considered asynchronous: the actual name change is likely to
	 * be affected manually, a significant amount of time after this call is
	 * made.
	 */
	public function saveTeamName()
	{
		$this->ensureAuthed();

		$auth   = AuthBackend::getInstance();
		$input  = Input::getInstance();
		$output = Output::getInstance();

		$team = $input->getInput('id');
		$name = $input->getInput('name');

        TeamNameStrategy::getDefaultInstance()->writeNameChangeRequest($team,
                                                                       $name);

		$output->setOutput('success', true);
	}

	/**
	 * Get all the info for all user blog feeds we know about
	 */
	public function getBlogFeeds()
	{
		$this->ensureAuthed();
		$output = Output::getInstance();
		$feeds  = Feeds::getInstance()->getFeeds();
		$output->setOutput('feeds', $feeds);
	}

	/**
	 * Sets the status of a blog feed
	 */
	public function setFeedStatus()
	{
		$this->ensureAuthed();
		$input  = Input::getInstance();
		$output = Output::getInstance();
		$feeds  = Feeds::getInstance();

		$feedurl    = $input->getInput('url');
		$feedstatus = $input->getInput('status');

		$userfeed = $feeds->findFeed('url', $feedurl);

		if ($userfeed == null)
		{
			$output->setOutput('success', false);
			return;
		}

		$userfeed->checked = ($feedstatus != 'unchecked');
		$userfeed->valid   = ($feedstatus == 'valid');

		$newfeeds[] = $userfeed;
		$feedsList = $feeds->getFeeds();
		foreach ($feedsList as $feed)
		{
			if ($feed->user != $userfeed->user)
			{
				$newfeeds[] = $feed;
			}
		}

		$success = intval($feeds->putFeeds($newfeeds));
		$output->setOutput('success', $success);
	}
}
