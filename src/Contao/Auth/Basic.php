<?php

/**
 * Basic authentication mechanism for Contao.
 * Copyright (C) 2013 Tristan Lins
 *
 * PHP version 5
 *
 * @copyright  bit3 UG 2013
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @package    auth/basic
 * @license    LGPL-3.0+
 * @filesource
 */

namespace Contao\Auth;

class Basic extends \Frontend implements AuthInterface
{
	/**
	 * {@inheritdoc}
	 */
	public function authenticate(\PageModel $rootPage)
	{
		$authorization = \Environment::get('httpAuthorization');

		list($mechanism, $authorization) = preg_split('#\s+#', $authorization, 2);
		if (strtolower($mechanism) == 'basic') {
			$authorization = base64_decode($authorization);
			list($username, $password) = explode(':', $authorization, 2);

			$member = \MemberModel::findByUsername($username);

			// The password has been generated with crypt()
			if (crypt($password, $member->password) == $member->password) {
				return $member;
			}
		}
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function handle403($pageId, \PageModel $rootPage)
	{
		if ($rootPage->browser_auth_basic_realm && !FE_USER_LOGGED_IN) {
			$authenticate = <<<EOF
WWW-Authenticate: Basic realm="{$rootPage->browser_auth_basic_realm}"
EOF;

			header('HTTP/1.1 401 Unauthorized');
			header($authenticate);

			// Look for an error_403 page
			$obj403 = \PageModel::find403ByPid($rootPage->id);

			// Die if there is no page at all
			if ($obj403 === null)
			{
				echo ('403 Forbidden');
				exit;
			}

			// Generate the error page
			if (!$obj403->autoforward || !$obj403->jumpTo)
			{
				global $objPage;

				$objPage = $obj403->loadDetails();
				$objHandler = new $GLOBALS['TL_PTY']['regular']();

				$objHandler->generate($objPage);

				exit;
			}

			// Forward to another page
			$nextPage = \PageModel::findPublishedById($obj403->jumpTo);

			if ($nextPage === null)
			{
				$this->log('Forward page ID "' . $obj403->jumpTo . '" does not exist', 'PageError403 generate()', TL_ERROR);
				die('Forward page not found');
			}

			$url = \Environment::get('base') . \Controller::generateFrontendUrl($nextPage->row(), null, $rootPage->language);

			echo <<<EOF
<!DOCTYPE html>
<html lang="de">
<head>
<meta http-equiv="refresh" content="5; URL={$url}">
</head>
<body>
Redirecting to <a href="{$url}">{$url}</a>
</body>
</html>
EOF;
			exit;
		}
	}
}
