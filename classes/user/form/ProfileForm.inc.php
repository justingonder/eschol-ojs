<?php

/**
 * @file classes/user/form/ProfileForm.inc.php
 *
 * Copyright (c) 2003-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ProfileForm
 * @ingroup user_form
 *
 * @brief Form to edit user profile.
 */

// $Id$


import('lib.pkp.classes.form.Form');

class ProfileForm extends Form {

	/** @var $user object */
	var $user;

	/**
	 * Constructor.
	 */
	function ProfileForm() {
		parent::Form('user/profile.tpl');

		$user =& Request::getUser();
		$this->user =& $user;

		$site =& Request::getSite();

		// Validation checks for this form
		$this->addCheck(new FormValidator($this, 'firstName', 'required', 'user.profile.form.firstNameRequired'));
		$this->addCheck(new FormValidator($this, 'lastName', 'required', 'user.profile.form.lastNameRequired'));
		$this->addCheck(new FormValidatorUrl($this, 'userUrl', 'optional', 'user.profile.form.urlInvalid'));
		$this->addCheck(new FormValidatorEmail($this, 'email', 'required', 'user.profile.form.emailRequired'));
		$this->addCheck(new FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailExists', array(DAORegistry::getDAO('UserDAO'), 'userExistsByEmail'), array($user->getId(), true), true));
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Deletes a profile image.
	 */
	function deleteProfileImage() {
		$user =& Request::getUser();
		$profileImage = $user->getSetting('profileImage');
		if (!$profileImage) return false;

		import('classes.file.PublicFileManager');
		$fileManager = new PublicFileManager();
		if ($fileManager->removeSiteFile($profileImage['uploadName'])) {
			return $user->updateSetting('profileImage', null);
		} else {
			return false;
		}
	}

	function uploadProfileImage() {
		import('classes.file.PublicFileManager');
		$fileManager = new PublicFileManager();

		$user =& $this->user;

		$type = $fileManager->getUploadedFileType('profileImage');
		$extension = $fileManager->getImageExtension($type);
		if (!$extension) return false;

		$uploadName = 'profileImage-' . (int) $user->getId() . $extension;
		if (!$fileManager->uploadSiteFile('profileImage', $uploadName)) return false;

		$filePath = $fileManager->getSiteFilesPath();
		list($width, $height) = getimagesize($filePath . '/' . $uploadName);

		if ($width > 150 || $height > 150 || $width <= 0 || $height <= 0) {
			$userSetting = null;
			$user->updateSetting('profileImage', $userSetting);
			$fileManager->removeSiteFile($filePath);
			return false;
		}

		$userSetting = array(
			'name' => $fileManager->getUploadedFileName('profileImage'),
			'uploadName' => $uploadName,
			'width' => $width,
			'height' => $height,
			'dateUploaded' => Core::getCurrentDate()
		);

		$user->updateSetting('profileImage', $userSetting);
		return true;
	}

	/**
	 * Display the form.
	 */
	function display() {
		$user =& Request::getUser();

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('username', $user->getUsername());

		$site =& Request::getSite();
		$templateMgr->assign('availableLocales', $site->getSupportedLocaleNames());

		$roleDao =& DAORegistry::getDAO('RoleDAO');
		$journalDao =& DAORegistry::getDAO('JournalDAO');
		$userSettingsDao =& DAORegistry::getDAO('UserSettingsDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');

		$journals =& $journalDao->getEnabledJournals();
		$journals =& $journals->toArray();

		foreach ($journals as $thisJournal) {
			if ($thisJournal->getSetting('publishingMode') == PUBLISHING_MODE_SUBSCRIPTION && $thisJournal->getSetting('enableOpenAccessNotification')) {
				$templateMgr->assign('displayOpenAccessNotification', true);
				$templateMgr->assign_by_ref('user', $user);
				break;
			}
		}

		$templateMgr->assign('genderOptions', $userDao->getGenderOptions());

		$journals =& $journalDao->getEnabledJournals();
		$journals =& $journals->toArray();

		$countryDao =& DAORegistry::getDAO('CountryDAO');
		$countries =& $countryDao->getCountries();

		$templateMgr->assign_by_ref('journals', $journals);
		$templateMgr->assign_by_ref('countries', $countries);
		$templateMgr->assign_by_ref('journalNotifications', $journalNotifications);
		$templateMgr->assign('helpTopicId', 'user.registerAndProfile');

		$journal =& Request::getJournal();
		if ($journal) {
			$roleDao =& DAORegistry::getDAO('RoleDAO');
			$roles =& $roleDao->getRolesByUserId($user->getId(), $journal->getId());
			$roleNames = array();
			foreach ($roles as $role) $roleNames[$role->getRolePath()] = $role->getRoleName();
			$templateMgr->assign('allowRegReviewer', $journal->getSetting('allowRegReviewer'));
			$templateMgr->assign('allowRegAuthor', $journal->getSetting('allowRegAuthor'));
			$templateMgr->assign('allowRegReader', $journal->getSetting('allowRegReader'));
			$templateMgr->assign('roles', $roleNames);
		}

		$templateMgr->assign('profileImage', $user->getSetting('profileImage'));

                // Set list of institutions for eSchol
                $institutionList = array(
                        'UC Berkeley' => 'UC Berkeley',
                        'UC Davis' => 'UC Davis',
                        'UC Irvine' => 'UC Irvine',
                        'UC Los Angeles' => 'UC Los Angeles',
                        'UC Merced' => 'UC Merced',
                        'UC Riverside' => 'UC Riverside',
                        'UC San Diego' => 'UC San Diego',
                        'UC San Francisco' => 'UC San Francisco',
                        'UC Santa Barbara' => 'UC Santa Barbara',
                        'UC Santa Cruz' => 'UC Santa Cruz',
                        'UC Office of the President' => 'UC Office of the President',
                        'Lawrence Berkeley National Lab' => 'Lawrence Berkeley National Lab'
                );
                $templateMgr->assign('institutionList', $institutionList);

		parent::display();
	}

	function getLocaleFieldNames() {
		$userDao =& DAORegistry::getDAO('UserDAO');
		return $userDao->getLocaleFieldNames();
	}

	/**
	 * Initialize form data from current settings.
	 */
	function initData(&$args, &$request) {
		$user =& $request->getUser();
		$interestDao =& DAORegistry::getDAO('InterestDAO');

		// Get all available interests to populate the autocomplete with
		if ($interestDao->getAllUniqueInterests()) {
			$existingInterests = $interestDao->getAllUniqueInterests();
		} else $existingInterests = null;
		// Get the user's current set of interests
		if ($interestDao->getInterests($user->getId())) {
			$currentInterests = $interestDao->getInterests($user->getId());
		} else $currentInterests = null;

		$this->_data = array(
			'salutation' => $user->getSalutation(),
			'firstName' => $user->getFirstName(),
			'middleName' => $user->getMiddleName(),
			'initials' => $user->getInitials(),
			'lastName' => $user->getLastName(),
			'gender' => $user->getGender(),
			'affiliation' => $user->getAffiliation(null), // Localized
			'signature' => $user->getSignature(null), // Localized
			'email' => $user->getEmail(),
			'userUrl' => $user->getUrl(),
			'phone' => $user->getPhone(),
			'fax' => $user->getFax(),
			'mailingAddress' => $user->getMailingAddress(),
			'country' => $user->getCountry(),
			'biography' => $user->getBiography(null), // Localized
			'userLocales' => $user->getLocales(),
			'isAuthor' => Validation::isAuthor(),
			'isReader' => Validation::isReader(),
			'isReviewer' => Validation::isReviewer(),
			'existingInterests' => $existingInterests,
			'interestsKeywords' => $currentInterests,
                        'professionalTitle' => $user->getProfessionalTitle(null)
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array(
			'salutation',
			'firstName',
			'middleName',
			'lastName',
			'gender',
			'initials',
			'affiliation',
			'affiliationOther',
			'signature',
			'email',
			'userUrl',
			'phone',
			'fax',
			'mailingAddress',
			'country',
			'biography',
			'interests',
			'interestsKeywords',
			'userLocales',
			'readerRole',
			'authorRole',
			'reviewerRole',
                        'professionalTitle'
		));

		if ($this->getData('userLocales') == null || !is_array($this->getData('userLocales'))) {
			$this->setData('userLocales', array());
		}

		$interests = $this->getData('interestsKeywords');
		if ($interests != null && is_array($interests)) {
			// The interests are coming in encoded -- Decode them for DB storage
			$this->setData('interestsKeywords', array_map('urldecode', $interests));
		}
	}

	/*
	 * Calculate the proper URL to reach Subi on this server
	 */
	function subi_url()
	{
		$s = $_SERVER;
		$ssl = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on') ? true:false;
		$sp = strtolower($s['SERVER_PROTOCOL']);
		$protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');
		$port = $s['SERVER_PORT'];
		$port = ((!$ssl && $port=='80') || ($ssl && $port=='443')) ? '' : ':'.$port;
		$host = isset($s['HTTP_X_FORWARDED_HOST']) ? $s['HTTP_X_FORWARDED_HOST'] : isset($s['HTTP_HOST']) ? $s['HTTP_HOST'] : $s['SERVER_NAME'];
		// Hack to get to proper subi on localhost
		if ($host == 'localhost' && $port == '') {
			$port = ':8080';
			$protocol = "http";
		}
		return $protocol . '://' . $host . $port . '/subi';
	}

	/**
	 * Save profile settings.
	 */
	function execute() {
		$user =& Request::getUser();

                // set value of affiliation
                // BLH 20131018 I'm not sure how to best deal with the localization here. This is a bit of a kluge since we're only going to use this locally at CDL.
                $affiliation = $this->getData('affiliation');
                if ($affiliation['en_US'] == 'Other') {
                        $affiliationOther = $this->getData('affiliationOther');
                        $affiliation = array('en_US' => $affiliationOther);
                }

		$user->setSalutation($this->getData('salutation'));
		$user->setFirstName($this->getData('firstName'));
		$user->setMiddleName($this->getData('middleName'));
		$user->setLastName($this->getData('lastName'));
		$user->setGender($this->getData('gender'));
		$user->setInitials($this->getData('initials'));
		$user->setAffiliation($affiliation, null); // Localized
		$user->setSignature($this->getData('signature'), null); // Localized
		$emailChanged = false;
		if ($user->getEmail() != $this->getData('email')) {
			$oldEmail = $user->getEmail();
			$user->setEmail($this->getData('email'));
			$emailChanged = true;
		}
                // MCH 20140211: In our OJS, email is the same as username.
		$user->setUsername(strtolower($this->getData('email')));
		$user->setUrl($this->getData('userUrl'));
		$user->setPhone($this->getData('phone'));
		$user->setFax($this->getData('fax'));
		$user->setMailingAddress($this->getData('mailingAddress'));
		$user->setCountry($this->getData('country'));
		$user->setBiography($this->getData('biography'), null); // Localized
		$user->setProfessionalTitle($this->getData('professionalTitle'), null); // Localized
		$userId = $user->getId();

		// Insert the user interests
		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();
		$interestManager->insertInterests($userId, $this->getData('interestsKeywords'), $this->getData('interests'));


		$site =& Request::getSite();
		$availableLocales = $site->getSupportedLocales();

		$locales = array();
		foreach ($this->getData('userLocales') as $locale) {
			if (Locale::isLocaleValid($locale) && in_array($locale, $availableLocales)) {
				array_push($locales, $locale);
			}
		}
		$user->setLocales($locales);

		$userDao =& DAORegistry::getDAO('UserDAO');
		$userDao->updateObject($user);

		$roleDao =& DAORegistry::getDAO('RoleDAO');
		$journalDao =& DAORegistry::getDAO('JournalDAO');

		// Roles
		$journal =& Request::getJournal();
		if ($journal) {
			$role = new Role();
			$role->setUserId($user->getId());
			$role->setJournalId($journal->getId());
			if ($journal->getSetting('allowRegReviewer')) {
				$role->setRoleId(ROLE_ID_REVIEWER);
				$hasRole = Validation::isReviewer();
				$wantsRole = Request::getUserVar('reviewerRole');
				if ($hasRole && !$wantsRole) $roleDao->deleteRole($role);
				if (!$hasRole && $wantsRole) $roleDao->insertRole($role);
			}
			if ($journal->getSetting('allowRegAuthor')) {
				$role->setRoleId(ROLE_ID_AUTHOR);
				$hasRole = Validation::isAuthor();
				$wantsRole = Request::getUserVar('authorRole');
				if ($hasRole && !$wantsRole) $roleDao->deleteRole($role);
				if (!$hasRole && $wantsRole) $roleDao->insertRole($role);
			}
			if ($journal->getSetting('allowRegReader')) {
				$role->setRoleId(ROLE_ID_READER);
				$hasRole = Validation::isReader();
				$wantsRole = Request::getUserVar('readerRole');
				if ($hasRole && !$wantsRole) $roleDao->deleteRole($role);
				if (!$hasRole && $wantsRole) $roleDao->insertRole($role);
			}
		}

		$openAccessNotify = Request::getUserVar('openAccessNotify');

		$userSettingsDao =& DAORegistry::getDAO('UserSettingsDAO');
		$journals =& $journalDao->getEnabledJournals();
		$journals =& $journals->toArray();

		foreach ($journals as $thisJournal) {
			if ($thisJournal->getSetting('publishingMode') == PUBLISHING_MODE_SUBSCRIPTION && $thisJournal->getSetting('enableOpenAccessNotification')) {
				$currentlyReceives = $user->getSetting('openAccessNotification', $thisJournal->getJournalId());
				$shouldReceive = !empty($openAccessNotify) && in_array($thisJournal->getJournalId(), $openAccessNotify);
				if ($currentlyReceives != $shouldReceive) {
					$userSettingsDao->updateSetting($user->getId(), 'openAccessNotification', $shouldReceive, 'bool', $thisJournal->getJournalId());
				}
			}
		}

		if ($user->getAuthId()) {
			$authDao =& DAORegistry::getDAO('AuthSourceDAO');
			$auth =& $authDao->getPlugin($user->getAuthId());
		}

		if (isset($auth)) {
			$auth->doSetUserInfo($user);
		}

		// If email has changed, send a password reset link to the new address.
		if ($emailChanged) {
			// To do that, we need Subi to calculate the link.
			$url = $this->subi_url() . '/ojsResetPwd?oldEmail=' . urlEncode($oldEmail) .
			       '&newEmail=' . urlencode($user->getEmail());
			file_get_contents($url);
		}
	}
}

?>
