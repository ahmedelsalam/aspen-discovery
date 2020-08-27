<?php

require_once ROOT_DIR . '/sys/DB/DataObject.php';
class User extends DataObject
{
	###START_AUTOCODE
	/* the code below is auto generated do not remove the above tag */

	public $__table = 'user';                            // table name
	public $id;                              // int(11)  not_null primary_key auto_increment
	public $source;
	public $username;                        // string(30)  not_null unique_key
	public $displayName;                     // string(30)
	public $password;                        // string(32)  not_null
	public $firstname;                       // string(50)  not_null
	public $lastname;                        // string(50)  not_null
	public $email;                           // string(250)  not_null
	public $phone;                           // string(30)
	public $cat_username;                    // string(50)
	public $cat_password;                    // string(50)
	public $patronType;
	public $created;                         // datetime(19)  not_null binary
	public $homeLocationId;					 // int(11)
	public $myLocation1Id;					 // int(11)
	public $myLocation2Id;					 // int(11)
	public $trackReadingHistory; 			 // tinyint
	public $initialReadingHistoryLoaded;
	public $bypassAutoLogout;        //tinyint
	public $disableRecommendations;     //tinyint
	public $disableCoverArt;     //tinyint
	public $overdriveEmail;
	public $promptForOverdriveEmail; //Semantics of this have changed to not prompting for hold settings
	public $hooplaCheckOutConfirmation;
	public $preferredLibraryInterface;
	public $noPromptForUserReviews; //tinyint(1)
    public $rbdigitalId;
	public $rbdigitalUsername;
	public $rbdigitalPassword;
	public $rbdigitalLastAccountCheck;
	public $lockedFacets;
	public $alternateLibraryCard;
	public $alternateLibraryCardPassword;
	public $hideResearchStarters;

	/** @var Role[] */
	private $_roles;
	private $_permissions;
	private $_masqueradingRoles;
	private $_masqueradeLevel;

	public $interfaceLanguage;
	public $searchPreferenceLanguage;

	public $rememberHoldPickupLocation;

	public $lastListUsed;

	public $lastLoginValidation;

	public $updateMessage;
	public $updateMessageIsError;

	/** @var User $parentUser */
	private $parentUser;
	/** @var User[] $linkedUsers */
	private $linkedUsers;
	private $viewers;

	//Data that we load, but don't store in the User table
	public $_fullname;
	public $_address1;
	public $_address2;
	public $_city;
	public $_state;
	public $_zip;
	public $_workPhone;
	public $_mobileNumber;
	public $_web_note;
	public $_expires;
	public $_expired;
	public $_expireClose;
	public $_fines;
	public $_finesVal;
	public $_homeLibrary;
	public $_homeLocationCode;
	public $_homeLocation;
	public $_myLocation1;
	public $_myLocation2;
	public $_numCheckedOutIls;
	public $_numHoldsIls;
	public $_numHoldsAvailableIls;
	public $_numHoldsRequestedIls;
	private $_numCheckedOutOverDrive = 0;
	private $_numHoldsOverDrive = 0;
	private $_numHoldsAvailableOverDrive = 0;
	private $_numCheckedOutRBdigital = 0;
    private $_numHoldsRBdigital = 0;
    private $_numHoldsAvailableRBdigital = 0;
	private $_numCheckedOutHoopla = 0;
	public $_numBookings;
	public $_notices;
	public $_noticePreferenceLabel;
	private $_numMaterialsRequests = 0;
	private $_readingHistorySize = 0;

	// CarlX Option
	public $_emailReceiptFlag;
	public $_availableHoldNotice;
	public $_comingDueNotice;
	public $_phoneType;

	function getNumericColumnNames()
	{
		return ['trackReadingHistory', 'hooplaCheckOutConfirmation', 'initialReadingHistoryLoaded', 'updateMessageIsError'];
	}

	function getLists() {
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';

		$lists = array();

		$list = new UserList();
		$list->user_id = $this->id;
		$list->orderBy('title');
		if ($list->find()) {
			while ($list->fetch()) {
				$lists[] = clone($list);
			}
		}

		return $lists;
	}

	private $catalogDriver = null;

	/**
	 * Get a connection to the catalog for the user
	 *
	 * @return CatalogConnection
	 */
	function getCatalogDriver()
	{
		if ($this->catalogDriver == null) {
			//Based off the source of the user, get the AccountProfile
			$accountProfile = $this->getAccountProfile();
			if ($accountProfile) {
				$catalogDriver = trim($accountProfile->driver);
				if (!empty($catalogDriver)) {
					$this->catalogDriver = CatalogFactory::getCatalogConnectionInstance($catalogDriver, $accountProfile);
				}
			}
		}
		return $this->catalogDriver;
	}

	function hasIlsConnection()
	{
		$driver = $this->getCatalogDriver();
		if ($driver == null){
			return false;
		}else{
			if ($driver->driver == null){
				return false;
			}
		}
		return true;
	}

	/** @var AccountProfile */
	private $_accountProfile;

	/**
	 * @return AccountProfile
	 */
	function getAccountProfile(){
		if ($this->_accountProfile != null){
			return $this->_accountProfile;
		}
		require_once ROOT_DIR . '/sys/Account/AccountProfile.php';
		$accountProfile = new AccountProfile();
		$accountProfile->name = $this->source;
		if ($accountProfile->find(true)){
			$this->_accountProfile = $accountProfile;
		}else{
			$this->_accountProfile = null;
		}
		return $this->_accountProfile;
	}

	function __get($name){
		if ($name == 'roles') {
			return $this->getRoles();
		}elseif ($name == 'linkedUsers'){
			return $this->getLinkedUsers();
		}elseif ($name == 'materialsRequestReplyToAddress'){
			if (!isset($this->materialsRequestReplyToAddress)) {
				$this->getStaffSettings();
			}
			return $this->materialsRequestReplyToAddress;
		}elseif ($name == 'materialsRequestEmailSignature'){
			if (!isset($this->materialsRequestEmailSignature)) {
				$this->getStaffSettings();
			}
			return $this->materialsRequestEmailSignature;
		}else{
			return $this->_data[$name];
		}
	}

	function __set($name, $value){
		if ($name == 'roles') {
			$this->setRoles($value);
		}else{
			$this->_data[$name] = $value;
		}
	}

	function setRoles($value){
		$this->_roles = $value;
		//Update the database, first remove existing values
		$this->saveRoles();
	}

	function getRoles($isGuidingUser = false){
		if (is_null($this->_roles)){
			$this->_roles = array();
			//Load roles for the user from the user
			require_once ROOT_DIR . '/sys/Administration/Role.php';
			$role = new Role();
			$canUseTestRoles = false;
			if ($this->id){
				$escapedId = $this->escape($this->id);
				$role->query("SELECT roles.* FROM roles INNER JOIN user_roles ON roles.roleId = user_roles.roleId WHERE userId = " . $escapedId . " ORDER BY name");
				while ($role->fetch()){
					$this->_roles[$role->roleId] = clone $role;
					if ($role->name == 'userAdmin'){
						$canUseTestRoles = true;
					}
				}
			}

			//Setup masquerading as different users
			$testRole = '';
			if (isset($_REQUEST['test_role'])){
				$testRole = $_REQUEST['test_role'];
			}elseif (isset($_COOKIE['test_role'])){
				$testRole = $_COOKIE['test_role'];
			}
			if ($canUseTestRoles && $testRole != ''){
				if (is_array($testRole)){
					$testRoles = $testRole;
				}else{
					$testRoles = array($testRole);
				}
				foreach ($testRoles as $tmpRole){
					$role = new Role();
					if (is_numeric($tmpRole)){
						$role->roleId = $tmpRole;
					}else{
						$role->name = $tmpRole;
					}
					$found = $role->find(true);
					if ($found == true){
						$this->_roles[$role->roleId] = clone $role;
					}
				}
			}
		}

		$masqueradeMode = UserAccount::isUserMasquerading();
		if ($masqueradeMode && !$isGuidingUser) {
			if (is_null($this->_masqueradingRoles)) {
				/** @var User $guidingUser */
				$guidingUser = UserAccount::getGuidingUserObject();
				$guidingUserRoles = $guidingUser->getRoles(true);
				if (in_array('opacAdmin', $guidingUserRoles)) {
					$this->_masqueradingRoles = $this->_roles;
				} else {
					$this->_masqueradingRoles = array_intersect($this->_roles, $guidingUserRoles);
				}
			}
			return $this->_masqueradingRoles;
		}
		return $this->_roles;
	}

	private $materialsRequestReplyToAddress;
	private $materialsRequestEmailSignature;

	function getStaffSettings(){
		require_once ROOT_DIR . '/sys/Account/UserStaffSettings.php';
		$staffSettings = new UserStaffSettings();
		$staffSettings->get('userId', $this->id);
		$this->materialsRequestReplyToAddress = $staffSettings->materialsRequestReplyToAddress;
		$this->materialsRequestEmailSignature = $staffSettings->materialsRequestEmailSignature;
	}

	function getBarcode()
	{
		if ($this->getAccountProfile()->loginConfiguration == 'barcode_pin') {
			return trim($this->cat_username);
		} else {
			return trim($this->cat_password);
		}
	}

	function getPasswordOrPin()
	{
		if ($this->getAccountProfile()->loginConfiguration == 'barcode_pin') {
			return trim($this->cat_password);
		} else {
			return trim($this->cat_username);
		}
	}

	function saveRoles(){
		if (isset($this->id) && isset($this->_roles) && is_array($this->_roles)){
			require_once ROOT_DIR . '/sys/Administration/Role.php';
			$role = new Role();
			$escapedId = $this->escape($this->id);
			$role->query("DELETE FROM user_roles WHERE userId = " . $escapedId);
			//Now add the new values.
			if (count($this->_roles) > 0){
				$values = array();
				foreach ($this->_roles as $roleId => $roleName){
					$values[] = "({$this->id},{$roleId})";
				}
				$values = join(', ', $values);
				$role->query("INSERT INTO user_roles ( `userId` , `roleId` ) VALUES $values");
			}
		}
	}

	/**
	 * @return User[]
	 */
	function getLinkedUsers(){
		if (is_null($this->linkedUsers)){
 			$this->linkedUsers = array();
			/* var Library $library */
			global $library;
			global $memCache;
			global $serverName;
			global $logger;
			if ($this->id && $library->allowLinkedAccounts){
				require_once ROOT_DIR . '/sys/Account/UserLink.php';
				$userLink = new UserLink();
				$userLink->primaryAccountId = $this->id;
				$userLink->linkingDisabled = "0";
				try {
					$userLink->find();
					while ($userLink->fetch()) {
						if (!$this->isBlockedAccount($userLink->linkedAccountId)) {
							$linkedUser = new User();
							$linkedUser->id = $userLink->linkedAccountId;
							if ($linkedUser->find(true)) {
								/** @var User $userData */
								$userData = $memCache->get("user_{$serverName}_{$linkedUser->id}");
								if ($userData === false || isset($_REQUEST['reload'])) {
									//Load full information from the catalog
									$linkedUser = UserAccount::validateAccount($linkedUser->cat_username, $linkedUser->cat_password, $linkedUser->source, $this);
								} else {
									$logger->log("Found cached linked user {$userData->id}", Logger::LOG_DEBUG);
									$linkedUser = $userData;
								}
								if ($linkedUser && !($linkedUser instanceof AspenError)) {
									$this->linkedUsers[] = clone($linkedUser);
								}
							}
						}
					}
				}catch (PDOException $e){
					//Disabling of linking has not been enabled yet. 
				}
			}
		}
		return $this->linkedUsers;
	}

	private $linkedUserObjects;
	function getLinkedUserObjects(){
		if (is_null($this->linkedUserObjects)){
			$this->linkedUserObjects = array();
			try {
				/* var Library $library */
				global $library;
				if ($this->id && $library->allowLinkedAccounts) {
					require_once ROOT_DIR . '/sys/Account/UserLink.php';
					$userLink = new UserLink();
					$userLink->primaryAccountId = $this->id;
					$userLink->linkingDisabled = "0";
					$userLink->find();
					while ($userLink->fetch()) {
						if (!$this->isBlockedAccount($userLink->linkedAccountId)) {
							$linkedUser = new User();
							$linkedUser->id = $userLink->linkedAccountId;
							if ($linkedUser->find(true)) {
								/** @var User $userData */
								$this->linkedUserObjects[] = clone($linkedUser);
							}
						}
					}
				}
			}catch (Exception $e){
				//Tables are likely not fully updated
				global $logger;
				$logger->log("Error loading linked users $e", Logger::LOG_ERROR);
			}
		}
		return $this->linkedUserObjects;
	}

	public function setParentUser($user){
		$this->parentUser =  $user;
	}

	// Account Blocks //
	private $blockAll = null; // set to null to signal unset, boolean when set
	private $blockedAccounts = null; // set to null to signal unset, array when set

	/**
	 * Checks if there is any settings disallowing the account $accountIdToCheck to be linked to this user.
	 *
	 * @param  $accountIdToCheck string   linked account Id to check for blocking
	 * @return bool                       true for blocking, false for no blocking
	 */
	public function isBlockedAccount($accountIdToCheck) {
		if (is_null($this->blockAll)) $this->setAccountBlocks();
		return $this->blockAll || in_array($accountIdToCheck, $this->blockedAccounts);
	}

	private function setAccountBlocks() {
		// default settings
		$this->blockAll = false;
		$this->blockedAccounts = array();

		require_once ROOT_DIR . '/sys/Administration/BlockPatronAccountLink.php';
		$accountBlock = new BlockPatronAccountLink();
		$accountBlock->primaryAccountId = $this->id;
		if ($accountBlock->find()) {
			while ($accountBlock->fetch(false)) {
				if ($accountBlock->blockLinking) $this->blockAll = true; // any one row that has block all on will set this setting to true for this account.
				if ($accountBlock->blockedLinkAccountId) $this->blockedAccounts[] = $accountBlock->blockedLinkAccountId;
			}
		}
	}

	/**
	 * @param string $source
	 * @return User[]
	 */
	function getRelatedEcontentUsers($source)
	{
		$users = array();
		if ($this->isValidForEContentSource($source)) {
			$users[$this->cat_username . ':' . $this->cat_password] = $this;
		}
		foreach ($this->getLinkedUsers() as $linkedUser) {
			if ($linkedUser->isValidForEContentSource($source)) {
				if (!array_key_exists($linkedUser->cat_username . ':' . $linkedUser->cat_password, $users)) {
					$users[$linkedUser->cat_username . ':' . $linkedUser->cat_password] = $linkedUser;
				}
			}
		}

		return $users;
	}

	function isValidForEContentSource($source)
	{
		global $enabledModules;
		if ($this->parentUser == null || ($this->getBarcode() != $this->parentUser->getBarcode())) {
			$userHomeLibrary = Library::getPatronHomeLibrary($this);
			if ($userHomeLibrary) {
				if ($source == 'overdrive') {
					require_once ROOT_DIR . '/Drivers/OverDriveDriver.php';
					$overDriveDriver = new OverDriveDriver();
					return array_key_exists('OverDrive', $enabledModules) && $overDriveDriver->isUserValidForOverDrive($this);
				} elseif ($source == 'hoopla') {
					return array_key_exists('Hoopla', $enabledModules) && $userHomeLibrary->hooplaLibraryID > 0;
				} elseif ($source == 'rbdigital') {
					if (array_key_exists('RBdigital', $enabledModules)){
						require_once ROOT_DIR . '/sys/RBdigital/RBdigitalSetting.php';
						try {
							$rbdigitalSettings = new RBdigitalSetting();
							$rbdigitalSettings->find();
							return $rbdigitalSettings->getNumResults() > 0;
						} catch (Exception $e) {
							return false;
						}
					}
					return false;
				} elseif ($source == 'cloud_library') {
					if (array_key_exists('Cloud Library', $enabledModules)){
						require_once ROOT_DIR . '/sys/CloudLibrary/CloudLibrarySetting.php';
						try {
							$cloudLibrarySettings = new CloudLibrarySetting();
							$cloudLibrarySettings->find();
							return $cloudLibrarySettings->getNumResults() > 0;
						} catch (Exception $e) {
							return false;
						}
					}
					return false;
				} elseif ($source == 'axis_360') {
					if (array_key_exists('Axis 360', $enabledModules)){
						require_once ROOT_DIR . '/sys/Axis360/Axis360Setting.php';
						try {
							$axis360Settings = new Axis360Setting();
							$axis360Settings->find();
							return $axis360Settings->getNumResults() > 0;
						} catch (Exception $e) {
							return false;
						}
					}
					return false;
				}
			}
		}
		return false;
	}

	/**
	 * Returns a list of users that can view this account
	 *
	 * @return User[]
	 */
	/** @noinspection PhpUnused */
	function getViewers(){
		if (is_null($this->viewers)){
			$this->viewers = array();
			/* var Library $library */
			global $library;
			if ($this->id && $library->allowLinkedAccounts){
				require_once ROOT_DIR . '/sys/Account/UserLink.php';
				$userLink = new UserLink();
				$userLink->linkedAccountId = $this->id;
				$userLink->linkingDisabled = "0";
				$userLink->find();
				while ($userLink->fetch()){
					$linkedUser = new User();
					$linkedUser->id = $userLink->primaryAccountId;
					if ($linkedUser->find(true)){
						if (!$linkedUser->isBlockedAccount($this->id)) {
							$this->viewers[] = clone($linkedUser);
						}
					}
				}
			}
		}
		return $this->viewers;
	}

	/**
	 * @param User $user
	 *
	 * @return boolean
	 */
	function addLinkedUser($user){
		/* var Library $library */
		global $library;
		if ($library->allowLinkedAccounts && $user->id != $this->id) { // library allows linked accounts and the account to link is not itself
			$linkedUsers = $this->getLinkedUsers();
			foreach ($linkedUsers as $existingUser) {
				if ($existingUser->id == $user->id) {
					//We already have a link to this user
					return true;
				}
			}

			// Check for Account Blocks
			if ($this->isBlockedAccount($user->id)) return false;

			//Check to make sure the account we are linking to allows linking
			$linkLibrary = $user->getHomeLibrary();
			if (!$linkLibrary->allowLinkedAccounts){
				return false;
			}

			// Add Account Link
			require_once ROOT_DIR . '/sys/Account/UserLink.php';
			$userLink                   = new UserLink();
			$userLink->primaryAccountId = $this->id;
			$userLink->linkedAccountId  = $user->id;
			$result = $userLink->insert();
			if (true == $result) {
				$this->linkedUsers[] = clone($user);
				return true;
			}
		}
		return false;
	}

	function removeLinkedUser($userId){
		/* var Library $library */
		global $library;
		if ($library->allowLinkedAccounts) {
			require_once ROOT_DIR . '/sys/Account/UserLink.php';
			$userLink                   = new UserLink();
			$userLink->primaryAccountId = $this->id;
			$userLink->linkedAccountId  = $userId;
			$ret                        = $userLink->delete(true);

			//Force a reload of data
			$this->linkedUsers = null;
			$this->getLinkedUsers();

			return $ret == 1;
		}
		return false;
	}


	function update(){
		if (empty($this->created)) {
			$this->created = date('Y-m-d');
		}
		$this->fixFieldLengths();
		$result = parent::update();
		$this->saveRoles();
		$this->clearCache(); // Every update to object requires clearing the Memcached version of the object
		return $result;
	}

	function insert(){
		//set default values as needed
		if (!isset($this->homeLocationId)) {
			$this->homeLocationId = 0;
			global $logger;
			$logger->log('No Home Location ID was set for newly created user.', Logger::LOG_WARNING);
		}
		if (!isset($this->myLocation1Id)) $this->myLocation1Id = 0;
		if (!isset($this->myLocation2Id)) $this->myLocation2Id = 0;
		if (!isset($this->bypassAutoLogout)) $this->bypassAutoLogout = 0;

		if (empty($this->created)){
			$this->created = date('Y-m-d');
		}
		$this->fixFieldLengths();
		parent::insert();
		$this->saveRoles();
		$this->clearCache();
	}

	function hasRole($roleName){
		$myRoles = $this->__get('roles');
		return in_array($roleName, $myRoles);
	}

    static function getObjectStructure(){
		//Lookup available roles in the system
		require_once ROOT_DIR . '/sys/Administration/Role.php';
		$roleList = Role::getLookup();

		$structure = array(
				'id' => array('property'=>'id', 'type'=>'label', 'label'=>'Id', 'description'=>'The unique id of the in the system'),
				'firstname' => array('property'=>'firstname', 'type'=>'label', 'label'=>'First Name', 'description'=>'The first name for the user.'),
				'lastname' => array('property'=>'lastname', 'type'=>'label', 'label'=>'Last Name', 'description'=>'The last name of the user.'),
				'homeLibraryName' => array('property'=>'homeLibraryName', 'type'=>'label', 'label'=>'Home Library', 'description'=>'The library the user belongs to.'),
				'homeLocation' => array('property'=>'homeLocation', 'type'=>'label', 'label'=>'Home Location', 'description'=>'The branch the user belongs to.'),
		);

		global $configArray;
		$barcodeProperty = $configArray['Catalog']['barcodeProperty'];
		$structure['barcode'] = array('property'=>$barcodeProperty, 'type'=>'label', 'label'=>'Barcode', 'description'=>'The barcode for the user.');

		$structure['roles'] = array('property'=>'roles', 'type'=>'multiSelect', 'listStyle' =>'checkbox', 'values'=>$roleList, 'label'=>'Roles', 'description'=>'A list of roles that the user has.');

		return $structure;
	}

	function hasRatings(){
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserWorkReview.php';

		$rating = new UserWorkReview();
		$rating->whereAdd("userId = {$this->id}");
		$rating->whereAdd('rating > 0'); // Some entries are just reviews (and therefore have a default rating of -1)
		$rating->find();
		if ($rating->getNumResults() > 0){
			return true;
		}else{
			return false;
		}
	}

	private $_runtimeInfoUpdated = false;
	function updateRuntimeInformation(){
		if (!$this->_runtimeInfoUpdated) {
			if ($this->getCatalogDriver()) {
				$this->getCatalogDriver()->updateUserWithAdditionalRuntimeInformation($this);
			}
			$this->_runtimeInfoUpdated = true;
		}
	}

	function updateOverDriveOptions(){
		if (isset($_REQUEST['promptForOverdriveEmail']) && ($_REQUEST['promptForOverdriveEmail'] == 'yes' || $_REQUEST['promptForOverdriveEmail'] == 'on')){
			// if set check & on check must be combined because checkboxes/radios don't report 'offs'
			$this->promptForOverdriveEmail = 1;
		}else{
			$this->promptForOverdriveEmail = 0;
		}
		if (isset($_REQUEST['overdriveEmail'])){
			$this->overdriveEmail = strip_tags($_REQUEST['overdriveEmail']);
		}
		$this->update();

		require_once ROOT_DIR . '/Drivers/OverDriveDriver.php';
		$overDriveDriver = new OverDriveDriver();
		$overDriveDriver->updateOptions($this);
	}

	function updateHooplaOptions(){
		if (isset($_REQUEST['hooplaCheckOutConfirmation']) && ($_REQUEST['hooplaCheckOutConfirmation'] == 'yes' || $_REQUEST['hooplaCheckOutConfirmation'] == 'on')) {
			// if set check & on check must be combined because checkboxes/radios don't report 'offs'
			$this->hooplaCheckOutConfirmation = 1;
		}else{
			$this->hooplaCheckOutConfirmation = 0;
		}
		$this->update();
	}

	public function updateRbdigitalOptions()
	{
		if (isset($_REQUEST['rbdigitalUsername'])){
			$this->rbdigitalUsername = strip_tags($_REQUEST['rbdigitalUsername']);
		}
		if (isset($_REQUEST['rbdigitalPassword'])){
			$this->rbdigitalPassword = strip_tags($_REQUEST['rbdigitalPassword']);
		}
		$this->update();
		return true;
	}

	function updateStaffSettings(){
		if (isset($_REQUEST['bypassAutoLogout']) && ($_REQUEST['bypassAutoLogout'] == 'yes' || $_REQUEST['bypassAutoLogout'] == 'on')){
			$this->bypassAutoLogout = 1;
		}else{
			$this->bypassAutoLogout = 0;
		}
		if (isset($_REQUEST['materialsRequestEmailSignature'])) {
			$this->setMaterialsRequestEmailSignature($_REQUEST['materialsRequestEmailSignature']);
		}
		if (isset($_REQUEST['materialsRequestReplyToAddress'])) {
			$this->setMaterialsRequestReplyToAddress($_REQUEST['materialsRequestReplyToAddress']);
		}
		$this->update();
	}

	function updateUserPreferences(){
		// Validate that the input data is correct
		if (isset($_POST['myLocation1']) && !is_array($_POST['myLocation1']) && preg_match('/^\d{1,3}$/', $_POST['myLocation1']) == 0){
			return ['success' => false, 'message' => 'The 1st location had an incorrect format.'];
		}
		if (isset($_POST['myLocation2']) && !is_array($_POST['myLocation2']) && preg_match('/^\d{1,3}$/', $_POST['myLocation2']) == 0){
			return ['success' => false, 'message' => 'The 2nd location had an incorrect format.'];
		}

		if (isset($_REQUEST['profileLanguage'])){
			$this->interfaceLanguage = $_REQUEST['profileLanguage'];
		}
		if (isset($_REQUEST['searchPreferenceLanguage'])){
			$this->searchPreferenceLanguage = $_REQUEST['searchPreferenceLanguage'];
		}

		//Make sure the selected location codes are in the database.
		if (isset($_POST['myLocation1'])){
			if ($_POST['myLocation1'] == 0){
				$this->myLocation1Id = $_POST['myLocation1'];
			}else{
				$location = new Location();
				$location->get('locationId', $_POST['myLocation1'] );
				if ($location->getNumResults() != 1) {
					return ['success' => false, 'message' => 'The 1st location could not be found in the database.'];
				} else {
					$this->myLocation1Id = $_POST['myLocation1'];
				}
			}
		}
		if (isset($_POST['myLocation2'])){
			if ($_POST['myLocation2'] == 0){
				$this->myLocation2Id = $_POST['myLocation2'];
			}else{
				$location = new Location();
				$location->get('locationId', $_POST['myLocation2'] );
				if ($location->getNumResults() != 1) {
					return ['success' => false, 'message' => 'The 2nd location could not be found in the database.'];
				} else {
					$this->myLocation2Id = $_POST['myLocation2'];
				}
			}
		}

		$this->noPromptForUserReviews = (isset($_POST['noPromptForUserReviews']) && $_POST['noPromptForUserReviews'] == 'on')? 1 : 0;
		$this->rememberHoldPickupLocation = (isset($_POST['rememberHoldPickupLocation']) && $_POST['rememberHoldPickupLocation'] == 'on')? 1 : 0;
		global $enabledModules;
		global $library;
		if (array_key_exists('EBSCO EDS', $enabledModules) && !empty($library->edsSettingsId)){
			$this->hideResearchStarters = (isset($_POST['hideResearchStarters']) && $_POST['hideResearchStarters'] == 'on')? 1 : 0;
		}

		if ($this->hasEditableUsername()){
			$result = $this->updateEditableUsername($_POST['username']);
			if ($result['success'] == false){
				return $result;
			}
		}

		if ($this->getShowAutoRenewSwitch()){
			$allowAutoRenewal = ($_REQUEST['allowAutoRenewal'] == 'on' || $_REQUEST['allowAutoRenewal'] == 'true');
			$result = $this->updateAutoRenewal($allowAutoRenewal);
			if ($result['success'] == false){
				return $result;
			}
		}
		$this->clearCache();
		$saveResult = $this->update();
		if ($saveResult === false){
			return ['success' => false, 'message' => 'Could not save to the database'];
		}else{
			return ['success' => true, 'message' => 'Your preferences were updated successfully'];
		}
	}

	/**
	 * Clear out the cached version of the patron profile.
	 */
	function clearCache(){
		global $memCache;
		global $serverName;
		$memCache->delete("user_{$serverName}_" . $this->id); // now stored by User object id column
	}

	/**
	 * @param $list UserList           object of the user list to check permission for
	 * @return  bool       true if this user can edit passed list
	 */
	function canEditList($list) {
		if ($this->id == $list->user_id){
			return true;
		}elseif ($this->hasRole('opacAdmin')){
			return true;
		}elseif ($this->hasRole('libraryAdmin') || $this->hasRole('contentEditor') || $this->hasRole('libraryManager')){
			$listUser = new User();
			$listUser->id = $list->user_id;
			$listUser->find(true);
			$listLibrary = Library::getLibraryForLocation($listUser->homeLocationId);
			$userLibrary = Library::getLibraryForLocation($this->homeLocationId);
			if ($userLibrary->libraryId == $listLibrary->libraryId){
				return true;
			}elseif(strpos($list->title, 'NYT - ') === 0 && ($this->hasRole('libraryAdmin') || $this->hasRole('contentEditor'))){
				//Allow NYT Times lists to be edited by any library admins and library managers
				return true;
			}
		}elseif ($this->hasRole('locationManager')){
			$listUser = new User();
			$listUser->id = $list->user_id;
			$listUser->find(true);
			if ($this->homeLocationId == $listUser->homeLocationId){
				return true;
			}
		}
		return false;
	}

	/**
	 * @return Library|null
	 */
	function getHomeLibrary(){
		if ($this->_homeLibrary == null){
			$this->_homeLibrary = Library::getPatronHomeLibrary($this);
		}
		return $this->_homeLibrary;
	}

	function getHomeLibrarySystemName(){
		return $this->getHomeLibrary()->displayName;
	}

	public function getNumCheckedOutTotal($includeLinkedUsers = true) {
		$this->updateRuntimeInformation();
		$myCheckouts = $this->_numCheckedOutIls + $this->_numCheckedOutOverDrive + $this->_numCheckedOutHoopla + $this->_numCheckedOutRBdigital;
		if ($includeLinkedUsers) {
			if ($this->getLinkedUsers() != null) {
				foreach ($this->getLinkedUsers() as $user) {
					$myCheckouts += $user->getNumCheckedOutTotal(false);
				}
			}
		}
		return $myCheckouts;
	}

	public function getNumHoldsTotal($includeLinkedUsers = true) {
		$this->updateRuntimeInformation();
		$myHolds = $this->_numHoldsIls + $this->_numHoldsOverDrive + $this->_numHoldsRBdigital;
		if ($includeLinkedUsers) {
			if ($this->getLinkedUsers() != null) {
				foreach ($this->linkedUsers as $user) {
					$myHolds += $user->getNumHoldsTotal(false);
				}
			}
		}
		return $myHolds;
	}

	/** @noinspection PhpUnused */
	public function getNumHoldsAvailableTotal($includeLinkedUsers = true){
		$this->updateRuntimeInformation();
		$myHolds = $this->_numHoldsAvailableIls + $this->_numHoldsAvailableOverDrive + $this->_numHoldsAvailableRBdigital;
		if ($includeLinkedUsers){
			if ($this->getLinkedUsers() != null) {
				foreach ($this->linkedUsers as $user) {
					$myHolds += $user->getNumHoldsAvailableTotal(false);
				}
			}
		}

		return $myHolds;
	}

	public function getNumBookingsTotal($includeLinkedUsers = true){
		$myBookings = $this->_numBookings;
		if ($includeLinkedUsers){
			if ($this->getLinkedUsers() != null) {
				foreach ($this->linkedUsers as $user) {
					$myBookings += $user->getNumBookingsTotal(false);
				}
			}
		}

		return $myBookings;
	}

	private $totalFinesForLinkedUsers = -1;
	/** @noinspection PhpUnused */
	public function getTotalFines($includeLinkedUsers = true){
		$totalFines = $this->_finesVal;
		if ($includeLinkedUsers){
			if ($this->totalFinesForLinkedUsers == -1){
				if ($this->getLinkedUsers() != null) {
					foreach ($this->linkedUsers as $user) {
						$totalFines += $user->getTotalFines(false);
					}
				}
				$this->totalFinesForLinkedUsers = $totalFines;
			}else{
				$totalFines = $this->totalFinesForLinkedUsers;
			}

		}
		return $totalFines;
	}

	/**
	 * Return all titles that are currently checked out by the user.
	 *
	 * Will check:
	 * 1) The current ILS for the user
	 * 2) OverDrive
	 *
	 * @param bool $includeLinkedUsers
	 * @param string $source
	 * @return array
	 */
	public function getCheckouts($includeLinkedUsers = true, $source = 'all'){
		global $timer;
		//Get checked out titles from the ILS
		if ($source == 'all' || $source == 'ils'){
			if ($this->hasIlsConnection()){
				$ilsCheckouts = $this->getCatalogDriver()->getCheckouts($this);
				$allCheckedOut = $ilsCheckouts;
				$timer->logTime("Loaded transactions from catalog. {$this->id}");
			}else{
				$allCheckedOut = [];
			}
		}else{
			$allCheckedOut = [];
		}

		if ($source == 'all' || $source == 'overdrive') {
			//Get checked out titles from OverDrive
			//Do not load OverDrive titles if the parent barcode (if any) is the same as the current barcode
			if ($this->isValidForEContentSource('overdrive')) {
				require_once ROOT_DIR . '/Drivers/OverDriveDriver.php';
				$driver = new OverDriveDriver();
				$overDriveCheckedOutItems = $driver->getCheckouts($this, false);
				$allCheckedOut = array_merge($allCheckedOut, $overDriveCheckedOutItems);
				$timer->logTime("Loaded transactions from overdrive. {$this->id}");
			}
		}

		if ($source == 'all' || $source == 'hoopla') {
			//Get checked out titles from Hoopla
			//Do not load Hoopla titles if the parent barcode (if any) is the same as the current barcode
			if ($this->isValidForEContentSource('hoopla')) {
				require_once ROOT_DIR . '/Drivers/HooplaDriver.php';
				$hooplaDriver = new HooplaDriver();
				$hooplaCheckedOutItems = $hooplaDriver->getCheckouts($this);
				$allCheckedOut = array_merge($allCheckedOut, $hooplaCheckedOutItems);
				$timer->logTime("Loaded transactions from hoopla. {$this->id}");
			}
		}

		if ($source == 'all' || $source == 'rbdigital') {
			if ($this->isValidForEContentSource('rbdigital')) {
				require_once ROOT_DIR . '/Drivers/RBdigitalDriver.php';
				$rbdigitalDriver = new RBdigitalDriver();
				$rbdigitalCheckedOutItems = $rbdigitalDriver->getCheckouts($this);
				$allCheckedOut = array_merge($allCheckedOut, $rbdigitalCheckedOutItems);
				$timer->logTime("Loaded transactions from rbdigital. {$this->id}");
			}
		}

		if ($source == 'all' || $source == 'cloud_library') {
			if ($this->isValidForEContentSource('cloud_library')) {
				require_once ROOT_DIR . '/Drivers/CloudLibraryDriver.php';
				$cloudLibraryDriver = new CloudLibraryDriver();
				$cloudLibraryCheckedOutItems = $cloudLibraryDriver->getCheckouts($this);
				$allCheckedOut = array_merge($allCheckedOut, $cloudLibraryCheckedOutItems);
				$timer->logTime("Loaded transactions from cloud_library. {$this->id}");
			}
		}

		if ($includeLinkedUsers) {
			if ($this->getLinkedUsers() != null) {
				/** @var User $user */
				foreach ($this->getLinkedUsers() as $linkedUser) {
					$allCheckedOut = array_merge($allCheckedOut, $linkedUser->getCheckouts(false, $source));
				}
			}
		}
		return $allCheckedOut;
	}

	public function getHolds($includeLinkedUsers = true, $unavailableSort = 'sortTitle', $availableSort = 'expire', $source = 'all')
	{
		if ($source == 'all' || $source == 'ils') {
			if ($this->hasIlsConnection()) {
				$ilsHolds = $this->getCatalogDriver()->getHolds($this);
				if ($ilsHolds instanceof AspenError) {
					$ilsHolds = array();
				}
				$allHolds = $ilsHolds;
			} else {
				$allHolds = [];
			}
		} else {
			$allHolds = [];
		}

		if ($source == 'all' || $source == 'overdrive') {
			//Get holds from OverDrive
			if ($this->isValidForEContentSource('overdrive')) {
				require_once ROOT_DIR . '/Drivers/OverDriveDriver.php';
				$driver = new OverDriveDriver();
				$overDriveHolds = $driver->getHolds($this);
				$allHolds = array_merge_recursive($allHolds, $overDriveHolds);
			}
		}

		if ($source == 'all' || $source == 'rbdigital') {
			//Get holds from RBdigital
			if ($this->isValidForEContentSource('rbdigital')) {
				require_once ROOT_DIR . '/Drivers/RBdigitalDriver.php';
				$driver = new RBdigitalDriver();
				$rbdigitalHolds = $driver->getHolds($this);
				$allHolds = array_merge_recursive($allHolds, $rbdigitalHolds);
			}
		}

		if ($source == 'all' || $source == 'cloud_library') {
			//Get holds from Cloud Library
			if ($this->isValidForEContentSource('cloud_library')) {
				require_once ROOT_DIR . '/Drivers/CloudLibraryDriver.php';
				$driver = new CloudLibraryDriver();
				$cloudLibraryHolds = $driver->getHolds($this);
				$allHolds = array_merge_recursive($allHolds, $cloudLibraryHolds);
			}
		}

		if ($includeLinkedUsers) {
			if ($this->getLinkedUsers() != null) {
				foreach ($this->getLinkedUsers() as $user) {
					$allHolds = array_merge_recursive($allHolds, $user->getHolds(false, $unavailableSort, $availableSort, $source));
				}
			}
		}

		$indexToSortBy = 'sortTitle';
		$holdSort = function ($a, $b) use (&$indexToSortBy) {
			$a = isset($a[$indexToSortBy]) ? $a[$indexToSortBy] : null;
			$b = isset($b[$indexToSortBy]) ? $b[$indexToSortBy] : null;

			// Put empty values (except for specified values of zero) at the bottom of the sort
			if (modifiedEmpty($a) && modifiedEmpty($b)) {
				return 0;
			} elseif (!modifiedEmpty($a) && modifiedEmpty($b)) {
				return -1;
			} elseif (modifiedEmpty($a) && !modifiedEmpty($b)) {
				return 1;
			}

			if ($indexToSortBy == 'format') {
				if (is_array($a)){
					$a = implode($a, ',');
				}
				if (is_array($b)){
					$b = implode($b, ',');
				}
			}

			return strnatcasecmp($a, $b);
			// This will sort numerically correctly as well
		};

		if (!empty($allHolds['available'])) {
			switch ($availableSort) {
				case 'author' :
				case 'format' :
					//This is used in the sort function
					$indexToSortBy = $availableSort;
					break;
				case 'title' :
					$indexToSortBy = 'sortTitle';
					break;
				case 'libraryAccount' :
					$indexToSortBy = 'user';
					break;
				case 'expire' :
				default :
					$indexToSortBy = 'expire';
			}
			uasort($allHolds['available'], $holdSort);
		}
		if (!empty($allHolds['unavailable'])) {
			switch ($unavailableSort) {
				case 'author' :
				case 'location' :
				case 'position' :
				case 'status' :
				case 'format' :
					//This is used in the sort function
					$indexToSortBy = $unavailableSort;
					break;
				case 'placed' :
					$indexToSortBy = 'create';
					break;
				case 'libraryAccount' :
					$indexToSortBy = 'user';
					break;
				case 'title' :
				default :
					$indexToSortBy = 'sortTitle';
			}
			uasort($allHolds['unavailable'], $holdSort);
		}

		return $allHolds;
	}

	public function getMyBookings($includeLinkedUsers = true){
		$ilsBookings = $this->getCatalogDriver()->getMyBookings($this);
		if ($ilsBookings instanceof AspenError) {
			$ilsBookings = array();
		}

		if ($includeLinkedUsers) {
			if ($this->getLinkedUsers() != null) {
				foreach ($this->getLinkedUsers() as $user) {
					$ilsBookings = array_merge_recursive($ilsBookings, $user->getMyBookings(false));
				}
			}
		}
		return $ilsBookings;
	}

	private $ilsFinesForUser;
	public function getFines($includeLinkedUsers = true){

		if (!isset($this->ilsFinesForUser)){
			$this->ilsFinesForUser = $this->getCatalogDriver()->getFines($this);
			if ($this->ilsFinesForUser instanceof AspenError) {
				$this->ilsFinesForUser = array();
			}
		}
		$ilsFines[$this->id] = $this->ilsFinesForUser;

		if ($includeLinkedUsers) {
			if ($this->getLinkedUsers() != null) {
				foreach ($this->getLinkedUsers() as $user) {
					$ilsFines += $user->getFines(false); // keep keys as userId
				}
			}
		}
		return $ilsFines;
	}

	public function getNameAndLibraryLabel(){
		return $this->displayName . ' - ' . $this->getHomeLibrarySystemName();
	}

	/**
	 * Get a list of locations where a record can be picked up.  Handles liked accounts
	 * and filtering to make sure that the user is able to
	 *
	 * @param $recordSource string   The source of the record that we are placing a hold on
	 *
	 * @return Location[]
	 */
	public function getValidPickupBranches($recordSource){
		//Get the list of pickup branch locations for display in the user interface.
		// using $user to be consistent with other code use of getPickupBranches()
		$userLocation = new Location();
		if ($recordSource == $this->getAccountProfile()->recordSource){
			$locations = $userLocation->getPickupBranches($this, $this->homeLocationId);
		}else{
			$locations = array();
		}
		$linkedUsers = $this->getLinkedUsers();
		foreach ($linkedUsers as $linkedUser){
			if ($recordSource == $linkedUser->source){
				$linkedUserLocation = new Location();
				$linkedUserPickupLocations = $linkedUserLocation->getPickupBranches($linkedUser, null, true);
				foreach ($linkedUserPickupLocations as $sortingKey => $pickupLocation) {
					foreach ($locations as $mainSortingKey => $mainPickupLocation) {
						// Check For Duplicated Pickup Locations
						if ($mainPickupLocation->libraryId == $pickupLocation->libraryId && $mainPickupLocation->locationId == $pickupLocation->locationId) {
							// Merge Linked Users that all have this pick-up location
							$pickupUsers = array_unique(array_merge($mainPickupLocation->pickupUsers, $pickupLocation->pickupUsers));
							$mainPickupLocation->pickupUsers = $pickupUsers;
							$pickupLocation->pickupUsers = $pickupUsers;

							// keep location with better sort key, remove the other
							if ($mainSortingKey == $sortingKey || $mainSortingKey[0] < $sortingKey[0] ) {
								unset ($linkedUserPickupLocations[$sortingKey]);
							} elseif ($mainSortingKey[0] == $sortingKey[0]) {
								if (strcasecmp($mainSortingKey, $sortingKey) > 0) unset ($locations[$mainSortingKey]);
								else unset ($linkedUserPickupLocations[$sortingKey]);
							} else {
								unset ($locations[$mainSortingKey]);
							}

						}
					}
				}
				$locations = array_merge($locations, $linkedUserPickupLocations);
			}
		}
		ksort($locations);
		return $locations;
	}

    /**
     * Place Hold
     *
     * Place a hold for the current user within their ILS
     *
     * @param   string $recordId            The id of the bib record
     * @param   string $pickupBranch        The branch where the user wants to pickup the item when available
     * @param   null|string $cancelDate     When the hold should be automatically cancelled if desired
     * @return  array                 An array with the following keys
     *                                result - true/false
     *                                message - the message to display
     * @access  public
     */
	function placeHold($recordId, $pickupBranch, $cancelDate = null) {
		$result = $this->getCatalogDriver()->placeHold($this, $recordId, $pickupBranch, $cancelDate);
		$this->updateAltLocationForHold($pickupBranch);
		if ($result['success']){
			$this->clearCache();
		}
		return $result;
	}

	function placeVolumeHold($recordId, $volumeId, $pickupBranch){
		$result = $this->getCatalogDriver()->placeVolumeHold($this, $recordId, $volumeId, $pickupBranch);
		$this->updateAltLocationForHold($pickupBranch);
		if ($result['success']){
			$this->clearCache();
		}
		return $result;
	}

	function bookMaterial($recordId, $startDate, $startTime, $endDate, $endTime){
		$result = $this->getCatalogDriver()->bookMaterial($this, $recordId, $startDate, $startTime, $endDate, $endTime);
		if ($result['success']){
			$this->clearCache();
		}
		return $result;
	}

	function updateAltLocationForHold($pickupBranch){
		if ($this->_homeLocationCode != $pickupBranch) {
			global $logger;
			$logger->log("The selected pickup branch is not the user's home location, checking to see if we need to set an alternate branch", Logger::LOG_NOTICE);
			$location = new Location();
			$location->code = $pickupBranch;
			if ($location->find(true)) {
				$logger->log("Found the location for the pickup branch $pickupBranch {$location->locationId}", Logger::LOG_NOTICE);
				if ($this->myLocation1Id == 0) {
					$logger->log("Alternate location 1 is blank updating that", Logger::LOG_NOTICE);
					$this->myLocation1Id = $location->locationId;
					$this->update();
				} else if ($this->myLocation2Id == 0 && $location->locationId != $this->myLocation1Id) {
					$logger->log("Alternate location 2 is blank updating that", Logger::LOG_NOTICE);
					$this->myLocation2Id = $location->locationId;
					$this->update();
				}
			}else{
				$logger->log("Could not find location for $pickupBranch", Logger::LOG_ERROR);
			}
		}
	}

	function cancelBookedMaterial($cancelId){
		$result = $this->getCatalogDriver()->cancelBookedMaterial($this, $cancelId);
		$this->clearCache();
		return $result;
	}

	function cancelAllBookedMaterial($includeLinkedUsers = true){
		$result = $this->getCatalogDriver()->cancelAllBookedMaterial($this);
		$this->clearCache();

		if ($includeLinkedUsers) {
			if ($this->getLinkedUsers() != null) {
				foreach ($this->getLinkedUsers() as $user) {

					$additionalResults = $user->cancelAllBookedMaterial(false);
					if (!$additionalResults['success']) { // if we received failures
						if ($result['success']) {
							$result = $additionalResults; // first set of failures, overwrite currently successful results
						} else { // if there were already failures, add the extra failure messages
							$result['message'] = array_merge($result['message'], $additionalResults['message']);
						}
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Place Item Hold
	 *
	 * This is responsible for placing item level holds.
	 *
	 * @param   string  $recordId   The id of the bib record
	 * @param   string  $itemId     The id of the item to hold
	 * @param   string  $pickupBranch The branch where the user wants to pickup the item when available
	 * @param null|string $cancelDate The date to automatically cancel the hold if not filled
	 * @return  mixed               True if successful, false if unsuccessful
	 *                              If an error occurs, return a AspenError
	 * @access  public
	 */
	function placeItemHold($recordId, $itemId, $pickupBranch, $cancelDate = null) {
		$result = $this->getCatalogDriver()->placeItemHold($this, $recordId, $itemId, $pickupBranch, $cancelDate);
		$this->updateAltLocationForHold($pickupBranch);
		if ($result['success']){
			$this->clearCache();
		}
		return $result;
	}

	/**
	 * Get the user referred to by id.  Will return false if the specified patron id is not
	 * the id of this user or one of the users that is linked to this user.
	 *
	 * @param $patronId     int  The patron to check
	 * @return User|false
	 */
	function getUserReferredTo($patronId){
		$patron = false;
		//Get the correct patron based on the information passed in.
		if ($patronId == $this->id){
			$patron = $this;
		}else{
			foreach ($this->getLinkedUsers() as $tmpUser){
				if ($tmpUser->id == $patronId){
					$patron = $tmpUser;
					break;
				}
			}
		}
		return $patron;
	}

	/**
	 * Cancels a hold for the user in their ILS
	 *
	 * @param $recordId string  The Id of the record being cancelled
	 * @param $cancelId string  The Id of the hold to be cancelled.  Structure varies by ILS
	 *
	 * @return array            Information about the result of the cancellation process
	 */
	function cancelHold($recordId, $cancelId){
		$result = $this->getCatalogDriver()->cancelHold($this, $recordId, $cancelId);
		$this->clearCache();
		return $result;
	}

//		function changeHoldPickUpLocation($recordId, $itemToUpdateId, $newPickupLocation){
			//$recordId is not used to update change hold pick up location in driver
	function changeHoldPickUpLocation($itemToUpdateId, $newPickupLocation){
		$result = $this->getCatalogDriver()->changeHoldPickupLocation($this, null, $itemToUpdateId, $newPickupLocation);
		$this->clearCache();
		return $result;
	}

	function freezeHold($recordId, $holdId, $reactivationDate){
		$result = $this->getCatalogDriver()->freezeHold($this, $recordId, $holdId, $reactivationDate);
		$this->clearCache();
		return $result;
	}

	function thawHold($recordId, $holdId){
		$result = $this->getCatalogDriver()->thawHold($this, $recordId, $holdId);
		$this->clearCache();
		return $result;
	}

	function freezeOverDriveHold($overDriveId, $reactivationDate){
		require_once ROOT_DIR . '/Drivers/OverDriveDriver.php';
		$overDriveDriver = new OverDriveDriver();
		return $overDriveDriver->freezeHold($this, $overDriveId, $reactivationDate);
	}

	function thawOverDriveHold($overDriveId){
		require_once ROOT_DIR . '/Drivers/OverDriveDriver.php';
		$overDriveDriver = new OverDriveDriver();
		return $overDriveDriver->thawHold($this, $overDriveId);
	}

	function renewCheckout($recordId, $itemId = null, $itemIndex = null){
		$result = $this->getCatalogDriver()->renewCheckout($this, $recordId, $itemId, $itemIndex);
		$this->clearCache();
		return $result;
	}

	function renewAll($renewLinkedUsers = false){
		$renewAllResults = $this->getCatalogDriver()->renewAll($this);
		//Also renew linked Users if needed
		if ($renewLinkedUsers) {
			if ($this->getLinkedUsers() != null) {
				foreach ($this->getLinkedUsers() as $user) {
					$linkedResults = $user->renewAll(false);
					//Merge results
					$renewAllResults['Renewed'] += $linkedResults['Renewed'];
					$renewAllResults['NotRenewed'] += $linkedResults['NotRenewed'];
					$renewAllResults['Total'] += $linkedResults['Total'];
					if ($renewAllResults['success'] && !$linkedResults['success']){
						$renewAllResults['success'] = false;
						$renewAllResults['message'] = $linkedResults['message'];
					}else if (!$renewAllResults['success'] && !$linkedResults['success']){
						//Append the new message

						array_merge($renewAllResults['message'], $linkedResults['message']);
					}
				}
			}
		}
		$this->clearCache();
		return $renewAllResults;
	}

	public function getReadingHistory($page, $recordsPerPage, $selectedSortOption, $filter, $forExport) {
		return $this->getCatalogDriver()->getReadingHistory($this, $page, $recordsPerPage, $selectedSortOption, $filter, $forExport);
	}

	public function doReadingHistoryAction($readingHistoryAction, $selectedTitles){
		$results = $this->getCatalogDriver()->doReadingHistoryAction($this, $readingHistoryAction, $selectedTitles);
		$this->clearCache();
		return $results;
	}

	/**
	 * Used by Account Profile, to show users any additional Admin roles they may have.
	 * @return bool
	 */
	public function isStaff(){
		global $configArray;
		if (count($this->getRoles()) > 0){
			return true;
		}elseif (isset($configArray['Staff P-Types'])){
			$staffPTypes = $configArray['Staff P-Types'];
			$pType = $this->patronType;
			if ($pType && array_key_exists($pType, $staffPTypes)){
				return true;
			}
		}
		return false;
	}

	public function updatePatronInfo($canUpdateContactInfo){
		$result = $this->getCatalogDriver()->updatePatronInfo($this, $canUpdateContactInfo);
		$this->clearCache();
		return $result;
	}

	/**
	 * Update the PIN or password for the user
	 *
	 * @return string[] keys are success and errors or message
	 */
	function updatePin(){
		if (isset($_REQUEST['pin'])){
			$oldPin = $_REQUEST['pin'];
		}else{
			return ['success' => false, 'message' => "Please enter your current pin number"];
		}
		if ($this->cat_password != $oldPin){
			return ['success' => false, 'message' => "The old pin number is incorrect"];
		}
		if (!empty($_REQUEST['pin1'])){
			$newPin = $_REQUEST['pin1'];
		}else{
			return ['success' => false, 'message' => "Please enter the new pin number"];
		}
		if (!empty($_REQUEST['pin2'])){
			$confirmNewPin = $_REQUEST['pin2'];
		}else{
			return ['success' => false, 'message' => "Please enter the new pin number again"];
		}
		if ($newPin != $confirmNewPin){
			return ['success' => false, 'message' => "New PINs do not match. Please try again."];
		}
		$result = $this->getCatalogDriver()->updatePin($this, $oldPin, $newPin);
		if ($result['success']){
			$this->cat_password = $newPin;
			$this->password = $newPin;
			$this->update();
			$this->clearCache();
		}

		return $result;
	}

	function getRelatedPTypes($includeLinkedUsers = true){
		$relatedPTypes = array();
		$relatedPTypes[$this->patronType] = $this->patronType;
		if ($includeLinkedUsers){
			if ($this->getLinkedUserObjects() != null) {
				/** @var User $user */
				foreach ($this->getLinkedUserObjects() as $user) {
					$relatedPTypes = array_merge($relatedPTypes, $user->getRelatedPTypes(false));
				}
			}
		}
		return $relatedPTypes;
	}

	function importListsFromIls(){
		return $this->getCatalogDriver()->importListsFromIls($this);
	}

	/**
	 * @return string
	 */
	public function getMasqueradeLevel()
	{
		if (empty($this->_masqueradeLevel)) $this->setMasqueradeLevel();
		return $this->_masqueradeLevel;
	}

	private function setMasqueradeLevel()
	{
		$this->_masqueradeLevel = 'none';
		if (!empty($this->patronType)) {
			require_once ROOT_DIR . '/Drivers/marmot_inc/PType.php';
			$pType = new pType();
			$pType->get('pType', $this->patronType);
			if ($pType->getNumResults() > 0) {
				$this->_masqueradeLevel = $pType->masquerade;
			}
		}else if ($this->hasRole('userAdmin')){
			$this->_masqueradeLevel = 'any';
		}
	}

	public function canMasquerade() {
		return $this->getMasqueradeLevel() != 'none';
	}

	/**
	 * @param mixed $materialsRequestReplyToAddress
	 */
	public function setMaterialsRequestReplyToAddress($materialsRequestReplyToAddress)
	{
		$this->materialsRequestReplyToAddress = $materialsRequestReplyToAddress;
	}

	/**
	 * @param mixed $materialsRequestEmailSignature
	 */
	public function setMaterialsRequestEmailSignature($materialsRequestEmailSignature)
	{
		$this->materialsRequestEmailSignature = $materialsRequestEmailSignature;
	}

	function setNumMaterialsRequests($val){
		$this->_numMaterialsRequests = $val;
	}

	function getNumMaterialsRequests(){
		$this->updateRuntimeInformation();
		return $this->_numMaterialsRequests;
	}

	function getNumRatings(){
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserWorkReview.php';

		$rating = new UserWorkReview();
		$rating->whereAdd("userId = {$this->id}");
		$rating->whereAdd('rating > 0'); // Some entries are just reviews (and therefore have a default rating of -1)
		return $rating->count();
	}

	function getReadingHistorySize(){
		if ($this->_readingHistorySize == null){
			if ($this->trackReadingHistory && $this->initialReadingHistoryLoaded){
				global $timer;
				require_once ROOT_DIR . '/sys/ReadingHistoryEntry.php';
				$readingHistoryDB = new ReadingHistoryEntry();
				$readingHistoryDB->userId = $this->id;
				$readingHistoryDB->whereAdd('deleted = 0');
				$readingHistoryDB->groupBy('groupedWorkPermanentId');
				$this->_readingHistorySize = $readingHistoryDB->count();
				$timer->logTime("Updated reading history size");
			}else{
				$this->_readingHistorySize = 0;
			}
		}

		return $this->_readingHistorySize;
	}

	function getPatronUpdateForm()
	{
		if ($this->hasIlsConnection()){
			return $this->getCatalogDriver()->getPatronUpdateForm($this);
		}else{
			return null;
		}
	}

	/** @noinspection PhpUnused */
	function showMessagingSettings(){
		if ($this->hasIlsConnection()){
			return $this->getCatalogDriver()->showMessagingSettings();
		}else{
			return false;
		}
	}

	function getMessages(){
		require_once ROOT_DIR . '/sys/Account/UserMessage.php';
		$userMessage = new UserMessage();
		$userMessage->userId = $this->id;
		$userMessage->isDismissed = "0";
		$messages = [];
		$userMessage->find();
		while ($userMessage->fetch()){
			$messages[] = clone $userMessage;
		}
		return $messages;
	}

	function disableLinkingDueToPasswordChange()
	{
		require_once ROOT_DIR . '/sys/Account/UserMessage.php';
		require_once ROOT_DIR . '/sys/Account/UserLink.php';

		$userLinks = new UserLink();
		$userLinks->linkedAccountId = $this->id;
		if ($userLinks->find()){
			$userMessage = new UserMessage();
			$userMessage->userId = $this->id;
			$userMessage->messageType = 'confirm_linked_accts';
			$userMessage->message = "Other accounts have linked to your account.  Do you want to continue allowing them to link to you?";
			$userMessage->action1Title = "Yes";
			$userMessage->action1 = "return AspenDiscovery.Account.enableAccountLinking()";
			$userMessage->action2Title = "No";
			$userMessage->action2 = "return AspenDiscovery.Account.stopAccountLinking()";
			$userMessage->messageLevel = 'warning';
			$userMessage->insert();
			while ($userLinks->fetch()){
				$userMessage = new UserMessage();
				$userMessage->userId = $userLinks->primaryAccountId;
				$userMessage->messageType = 'linked_acct_notify_pause_' . $this->id;
				$userMessage->messageLevel = 'info';
				$userMessage->message = "An account you are linking to changed their login. Account linking with them has been temporarily disabled.";
				$userMessage->insert();
				$userLinks->linkingDisabled = 1;
				$userLinks->update();
			}
		}
	}

	function getOverDriveOptions() {
		require_once ROOT_DIR . '/Drivers/OverDriveDriver.php';
		$overDriveDriver = new OverDriveDriver();
		return $overDriveDriver->getOptions($this);
	}

	function completeFinePayment(UserPayment $payment){
		$result = $this->getCatalogDriver()->completeFinePayment($this, $payment);
		if ($result['success']){
			$payment->completed = 1;
			$payment->update();
		}
		return $result;
	}

	private function fixFieldLengths()
	{
		if (strlen($this->lastname) > 100){
			$this->lastname = substr($this->lastname, 0, 100);
		}
		if (strlen($this->firstname) > 50){
			$this->firstname = substr($this->firstname, 0, 50);
		}
		if (strlen($this->displayName) > 60){
			$this->displayName = substr($this->displayName, 0, 60);
		}
	}

	function eligibleForHolds()
	{
		if (empty($this->getCatalogDriver())){
			return false;
		}
		return $this->getCatalogDriver()->patronEligibleForHolds($this);
	}

	function getShowAutoRenewSwitch()
	{
		if (empty($this->getCatalogDriver())){
			return false;
		}
		return $this->getCatalogDriver()->getShowAutoRenewSwitch($this);
	}

	function isAutoRenewalEnabledForUser(){
		if (empty($this->getCatalogDriver())){
			return false;
		}
		return $this->getCatalogDriver()->isAutoRenewalEnabledForUser($this);
	}

	function updateAutoRenewal($allowAutoRenewal){
		return $this->getCatalogDriver()->updateAutoRenewal($this, $allowAutoRenewal);
	}

	public function getNotInterestedTitles(){
		global $timer;
		$notInterestedTitles = [];
		require_once ROOT_DIR . '/sys/LocalEnrichment/NotInterested.php';
		$notInterested = new NotInterested();
		$notInterested->userId = $this->id;
		$notInterested->find();
		while ($notInterested->fetch()){
			$notInterestedTitles[$notInterested->groupedRecordPermanentId] = $notInterested->groupedRecordPermanentId;
		}
		$timer->logTime("Loaded titles the patron is not interested in");
		return $notInterestedTitles;
	}

	public function getAllIdsNotToSuggest(){
		$idsNotToSuggest = $this->getNotInterestedTitles();
		//Add everything the user has rated
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserWorkReview.php';
		$ratings = new UserWorkReview();
		$ratings->userId = $this->id;
		$ratings->find();
		while ($ratings->fetch()){
			$idsNotToSuggest[$ratings->groupedRecordPermanentId] = $ratings->groupedRecordPermanentId;
		}
		//Add everything in the user's reading history
		require_once ROOT_DIR . '/sys/ReadingHistoryEntry.php';
		$readingHistoryEntry = new ReadingHistoryEntry();
		$readingHistoryEntry->userId = $this->id;
		$readingHistoryEntry->selectAdd();
		$readingHistoryEntry->selectAdd('groupedWorkPermanentId');
		$readingHistoryEntry->groupBy('groupedWorkPermanentId');
		$readingHistoryEntry->find();
		while ($readingHistoryEntry->fetch()){
			if (!empty($readingHistoryEntry->groupedWorkPermanentId)) {
				$idsNotToSuggest[$readingHistoryEntry->groupedWorkPermanentId] = $readingHistoryEntry->groupedWorkPermanentId;
			}
		}

		return $idsNotToSuggest;
	}

	/** @noinspection PhpUnused */
	function getHomeLocationName(){
		if (empty($this->_homeLocation)) {
			$location = new Location();
			$location->locationId = $this->homeLocationId;
			if ($location->find(true)){
				$this->_homeLocation = $location->displayName;
			}
			$location->__destruct();
		}
		return $this->_homeLocation;
	}

	function getHomeLocation(){
		$location = new Location();
		$location->locationId = $this->homeLocationId;
		if ($location->find(true)){
			if (empty($this->_homeLocation)) {
				$this->_homeLocation = $location;
			}
			if (empty($this->_homeLocationCode)) {
				$this->_homeLocationCode = $location->code;
			}
			return $location;
		}
		return null;
	}

	function getHomeLocationCode(){
		return $this->getHomeLocation()->code;
	}

	/**
	 * @param string $pickupBranch
	 * @return bool
	 */
	function validatePickupBranch(string &$pickupBranch): bool
	{
		//Validate the selected pickup branch, we do this in 2 passes, the first looking at the code and the second at the historicCode
		//If the historic code is valid, we replace $pickupBranch with the new code
		$location = new Location();
		$location->code = $pickupBranch;
		$location->find();
		$locationValid = true;
		if ($location->getNumResults() == 1) {
			$location->fetch();
			if ($location->validHoldPickupBranch == 2) {
				//Valid for no one
				$locationValid = false;
			} elseif ($location->validHoldPickupBranch == 0) {
				//Valid for patrons of the branch only
				$locationValid = $location->code == $this->getHomeLocation()->code;
			}
		} else {
			$location = new Location();
			$location->historicCode = $pickupBranch;
			$location->find();
			$locationValid = true;
			if ($location->getNumResults() == 1) {
				$location->fetch();
				if ($location->validHoldPickupBranch == 2) {
					//Valid for no one
					$locationValid = false;
				} elseif ($location->validHoldPickupBranch == 0) {
					//Valid for patrons of the branch only
					$locationValid = $location->code == $this->getHomeLocation()->code;
				}
				if ($locationValid){
					$pickupBranch = $location->code;
				}
			} else {
				//Location is deleted
				$locationValid = false;
			}
		}
		return $locationValid;
	}

	public function hasEditableUsername()
	{
		if ($this->hasIlsConnection()) {
			$homeLibrary = $this->getHomeLibrary();
			if ($homeLibrary->allowUsernameUpdates) {
				return $this->getCatalogDriver()->hasEditableUsername();
			}
		}
		return false;
	}

	public function getEditableUsername()
	{
		if ($this->hasIlsConnection()) {
			return $this->getCatalogDriver()->getEditableUsername($this);
		}else{
			return null;
		}
	}

	private function updateEditableUsername($username)
	{
		if ($this->hasIlsConnection()) {
			return $this->getCatalogDriver()->updateEditableUsername($this, $username);
		}else{
			return [
				'success' => false,
				'message' => 'This user is not connected to an ILS'
			];
		}
	}

	public function logout(){
		if ($this->hasIlsConnection()) {
			$this->getCatalogDriver()->logout($this);
		}
	}

	public function getAdminActions(){
		require_once ROOT_DIR . '/sys/AdminSection.php';
		global $library;
		global $configArray;
		global $enabledModules;
		$sections = [];

		if (count($this->getRoles()) == 0){
			return $sections;
		}
		$sections['system_admin'] = new AdminSection('System Administration');
		$sections['system_admin']->addAction(new AdminAction('Modules', 'Enable and disable sections of Aspen Discovery.', '/Admin/Modules'), 'view_modules');
		$sections['system_admin']->addAction(new AdminAction('Administrators', 'Define who should have administration privileges.', '/Admin/Administrators'), 'view_administrators');
		$sections['system_admin']->addAction(new AdminAction('DB Maintenance', 'Update the database when new versions of Aspen Discovery are released.', '/Admin/DBMaintenance'), 'run_database_maintenance');
		$sections['system_admin']->addAction(new AdminAction('Send Grid Settings', 'Settings to allow Aspen Discovery to send emails via SendGrid.', '/Admin/SendGridSettings'), 'administer_sendgrid');
		$sections['system_admin']->addAction(new AdminAction('Variables', 'Variables set by the Aspen Discovery itself as part of background processes.', '/Admin/Variables'), 'view_system_variables');
		$sections['system_admin']->addAction(new AdminAction('System Variables', 'Settings for Aspen Discovery that apply to all libraries on this installation.', '/Admin/SystemVariables'), 'view_system_variables');

		$sections['system_reports'] = new AdminSection('System Reports');
		$sections['system_reports']->addAction(new AdminAction('Site Status', 'View Status of Aspen Discovery.', '/Admin/SiteStatus'), 'view_system_reports');
		$sections['system_reports']->addAction(new AdminAction('Usage Dashboard', 'Usage Report for Aspen Discovery.', '/Admin/UsageDashboard'), 'view_system_reports');
		$sections['system_reports']->addAction(new AdminAction('Nightly Index Log', 'Nightly indexing log for Aspen Discovery.  The nightly index updates all records if needed.', '/Admin/ReindexLog'), ['view_system_reports', 'view_indexing_logs']);
		$sections['system_reports']->addAction(new AdminAction('Cron Log', 'View Cron Log. The cron process handles periodic cleanup tasks and updates reading history for users.', '/Admin/CronLog'), 'view_system_reports');
		$sections['system_reports']->addAction(new AdminAction('Performance Report', 'View Aspen Performance Report.', '/Admin/PerformanceReport'), 'view_system_reports');
		$sections['system_reports']->addAction(new AdminAction('Error Log', 'View Aspen Error Log.', '/Admin/ErrorReport'), 'view_system_reports');
		$sections['system_reports']->addAction(new AdminAction('PHP Information', 'Display configuration information for PHP on the server.', '/Admin/PHPInfo'), 'view_system_reports');

		$sections['configuration_templates'] = new AdminSection('Theme & Layout');
		$sections['configuration_templates']->addAction(new AdminAction('Themes', 'Define colors, fonts, images etc used within Aspen Discovery.', '/Admin/Themes'), ['view_all_themes', 'view_library_themes']);
		$sections['configuration_templates']->addAction(new AdminAction('Layout Settings', 'Define basic information about how pages are displayed in Aspen Discovery.', '/Admin/LayoutSettings'), ['view_all_layout_settings', 'view_library_layout_settings']);

		$sections['primary_configuration'] = new AdminSection('Primary Configuration');
		$librarySettingsAction = new AdminAction('Library Systems', 'Configure library settings.', '/Admin/Libraries');
		$locationSettingsAction = new AdminAction('Locations', 'Configure location settings.', '/Admin/Locations');
		$ipAddressesAction = new AdminAction('IP Addresses', 'Configure IP addresses for each location and configure rules to block access to Aspen Discovery.', '/Admin/IPAddresses');
		if ($sections['primary_configuration']->addAction($librarySettingsAction, ['view_all_library_settings', 'view_my_library_settings'])){
			$librarySettingsAction->addSubAction($locationSettingsAction, ['view_all_location_settings', 'view_library_location_settings', 'view_my_location_settings']);
			$librarySettingsAction->addSubAction($ipAddressesAction, 'view_ip_addresses');
		}else{
			$sections['primary_configuration']->addAction($locationSettingsAction, ['view_all_location_settings', 'view_library_location_settings', 'view_my_location_settings']);
			$sections['primary_configuration']->addAction($ipAddressesAction, 'view_ip_addresses');
		}
		$sections['primary_configuration']->addAction(new AdminAction('Block Patron Account Linking', 'Prevent accounts from linking to other accounts.', '/Admin/BlockPatronAccountLinks'), 'block_patron_account_linking');
		$sections['primary_configuration']->addAction(new AdminAction('Patron Types', 'Modify Permissions and limits based on Patron Type.', '/Admin/PTypes'), 'view_patron_types');
		$sections['primary_configuration']->addAction(new AdminAction('Account Profiles', 'Define how account information is loaded from the ILS.', '/Admin/AccountProfiles'), 'view_account_profiles');

		//Materials Request if enabled
		if (MaterialsRequest::enableAspenMaterialsRequest()){
			if ($library->enableMaterialsRequest == 1) {
				$sections['materials_request'] = new AdminSection('Materials Requests');
				$sections['materials_request']->addAction(new AdminAction('Manage Requests', 'Manage Materials Requests from users.', '/MaterialsRequest/ManageRequests'), 'manage_library_materials_requests');
				$sections['materials_request']->addAction(new AdminAction('Summary Report', 'A Summary Report of all requests that have been submitted.', '/MaterialsRequest/SummaryReport'), 'view_materials_requests_reports');
				$sections['materials_request']->addAction(new AdminAction('Report By User', 'A Report of all requests that have been submitted by users who submitted them.', '/MaterialsRequest/UserReport'), 'view_materials_requests_reports');
				$sections['materials_request']->addAction(new AdminAction('Manage Statuses', 'Define the statuses of Materials Requests for the library.', '/MaterialsRequest/ManageStatuses'), 'manage_library_materials_request_statuses');
			}
		}

		$sections['translations'] = new AdminSection('Languages and Translations');
		$sections['translations']->addAction(new AdminAction('Languages', 'Define which languages are available within Aspen Discovery.', '/Translation/Languages'), 'view_languages');
		$sections['translations']->addAction(new AdminAction('Translations', 'Translate the user interface of Aspen Discovery.', '/Translation/Translations'), 'view_translations');

		$sections['cataloging'] = new AdminSection('Catalog / Grouped Works');
		$groupedWorkAction = new AdminAction('Grouped Work Display', 'Define information about what is displayed for Grouped Works in search results and full record displays.', '/Admin/GroupedWorkDisplay');
		$groupedWorkAction->addSubAction(new AdminAction('Grouped Work Facets', 'Define information about what facets are displayed for grouped works in search results and Advanced Search.', '/Admin/GroupedWorkFacets'), ['view_all_grouped_work_facets', 'view_library_grouped_work_facets']);
		$sections['cataloging']->addAction($groupedWorkAction, ['view_all_grouped_work_display_settings', 'view_library_grouped_work_display_settings']);
		$sections['cataloging']->addAction(new AdminAction('Records To Not Group', 'Lists records that should not be grouped.', '/Admin/NonGroupedRecords'), 'group_works');

		$sections['local_enrichment'] = new AdminSection('Local Catalog Enrichment');
		$browseCategoryGroupsAction = new AdminAction('Browse Category Groups', 'Define information about what is displayed for Grouped Works in search results and full record displays.', '/Admin/BrowseCategoryGroups');
		$browseCategoryGroupsAction->addSubAction(new AdminAction('Browse Categories', 'Define browse categories shown on the library home page.', '/Admin/BrowseCategories'), ['view_all_browse_categories', 'view_library_browse_categories']);
		$sections['local_enrichment']->addAction($browseCategoryGroupsAction, ['view_all_browse_categories', 'view_library_browse_categories']);
		$sections['local_enrichment']->addAction(new AdminAction('Collection Spotlights', 'Define basic information about how pages are displayed in Aspen Discovery.', '/Admin/CollectionSpotlights'), ['view_all_collection_spotlights', 'view_library_collection_spotlights']);
		$sections['local_enrichment']->addAction(new AdminAction('Placards', 'Placards allow you to promote services that do not have MARC records or APIs for inclusion in the catalog.', '/Admin/Placards'), ['view_all_placards', 'view_library_placards']);

		$sections['third_party_enrichment'] = new AdminSection('Third Party Enrichment');
		$sections['third_party_enrichment']->addAction(new AdminAction('Accelerated Reader Settings', 'Define settings to load Accelerated Reader information directly from Renaissance Learning.', '/Enrichment/ARSettings'), 'view_ar_settings');
		$sections['third_party_enrichment']->addAction(new AdminAction('Coce Server Settings', 'Define settings to load covers from a Coce server.', '/Enrichment/CoceServerSettings'), 'view_contentcafe_settings');
		$sections['third_party_enrichment']->addAction(new AdminAction('ContentCafe Settings', 'Define settings for ContentCafe integration.', '/Enrichment/ContentCafeSettings'), 'view_contentcafe_settings');
		$sections['third_party_enrichment']->addAction(new AdminAction('DP.LA Settings', 'Define settings for DP.LA integration.', '/Enrichment/DPLASettings'), 'view_dpla_settings');
		$sections['third_party_enrichment']->addAction(new AdminAction('Google API Settings', 'Define settings for integrating Google APIs within Aspen Discovery.', '/Enrichment/GoogleApiSettings'), 'view_google_api_settings');
		$nytSettingsAction = new AdminAction('New York Times Settings', 'Define settings for integrating New York Times Content within Aspen Discovery.', '/Enrichment/NewYorkTimesSettings');
		$nytListsAction = new AdminAction('New York Times Lists', 'View Lists from the New York Times and manually refresh content.', '/Enrichment/NYTLists');
		if ($sections['third_party_enrichment']->addAction($nytSettingsAction, 'view_nyt_settings')){
			$nytSettingsAction->addSubAction($nytListsAction, 'view_nyt_lists');
		}else{
			$sections['third_party_enrichment']->addAction($nytListsAction, 'view_nyt_lists');
		}
		$sections['third_party_enrichment']->addAction(new AdminAction('Novelist Settings', 'Define settings for integrating Novelist within Aspen Discovery.', '/Enrichment/NovelistSettings'), 'view_novelist_settings');
		$sections['third_party_enrichment']->addAction(new AdminAction('OMDB Settings', 'Define settings for integrating OMDB within Aspen Discovery.', '/Enrichment/OMDBSettings'), 'view_omdb_settings');
		$sections['third_party_enrichment']->addAction(new AdminAction('reCAPTCHA Settings', 'Define settings for using reCAPTCHA within Aspen Discovery.', '/Enrichment/RecaptchaSettings'), 'view_recaptcha_settings');
		$sections['third_party_enrichment']->addAction(new AdminAction('Rosen LevelUP Settings', 'Define settings for allowing students and parents to register for Rosen LevelUP.', '/Rosen/RosenLevelUPSettings'), 'view_rosen_levelup_settings');
		$sections['third_party_enrichment']->addAction(new AdminAction('Syndetics Settings', 'Define settings for Syndetics integration.', '/Enrichment/SyndeticsSettings'), 'view_syndetics_settings');
		$sections['third_party_enrichment']->addAction(new AdminAction('Wikipedia Integration', 'Modify which Wikipedia content is displayed for authors.', '/Admin/AuthorEnrichment'), 'view_wikipedia_author_enrichment');

		$sections['ils_integration'] = new AdminSection('ILS Integration');
		$indexingProfileAction = new AdminAction('Indexing Profiles', 'Define how records from the ILS are loaded into Aspen Discovery.', '/ILS/IndexingProfiles');
		$translationMapsAction = new AdminAction('Translation Maps', 'Define how field values are mapped between the ILS and Aspen Discovery.', '/ILS/TranslationMaps');
		if ($sections['ils_integration']->addAction($indexingProfileAction, 'view_indexing_profiles')){
			$indexingProfileAction->addSubAction($translationMapsAction, 'view_translation_maps');
		}else{
			$sections['ils_integration']->addAction($translationMapsAction, 'view_translation_maps');
		}
		if ($configArray['Catalog']['ils'] == 'Millennium' || $configArray['Catalog']['ils'] == 'Sierra'){
			$sections['ils_integration']->addAction(new AdminAction('Loan Rules', 'View and load loan rules used by the ILS to determine if an patron is eligible to use materials.', '/ILS/LoanRules'), 'view_loan_rules');
			$sections['ils_integration']->addAction(new AdminAction('Loan Rule Determiners', 'View and load loan rule determiners used by the ILS to determine if an patron is eligible to use materials.', '/ILS/LoanRuleDeterminers'), 'view_loan_rules');
		}
		$sections['ils_integration']->addAction(new AdminAction('Indexing Log', 'View the indexing log for ILS records.', '/ILS/IndexingLog'), 'view_ils_indexing_log');
		$sections['ils_integration']->addAction(new AdminAction('Offline Holds Report', 'View a report of holds that were submitted while the ILS was offline.', '/Circa/OfflineHoldsReport'), 'view_ils_offline_holds_report');
		$sections['ils_integration']->addAction(new AdminAction('Dashboard', 'View the usage dashboard for ILS integration.', '/ILS/Dashboard'), 'view_ils_dashboard');

		$sections['axis360'] = new AdminSection('Axis 360');
		$axis360SettingsAction = new AdminAction('Settings', 'Define connection information between Axis 360 and Aspen Discovery.', '/Axis360/Settings');
		$axis360ScopesAction = new AdminAction('Scopes', 'Define which records are loaded for each library and location.', '/Axis360/Scopes');
		if ($sections['axis360']->addAction($axis360SettingsAction, 'view_axis360_settings')){
			$axis360SettingsAction->addSubAction($axis360ScopesAction, 'view_axis360_settings');
		}else{
			$sections['axis360']->addAction($axis360ScopesAction, 'view_axis360_settings');
		}
		$sections['axis360']->addAction(new AdminAction('Indexing Log', 'View the indexing log for Axis 360.', '/Axis360/IndexingLog'), 'view_axis360_indexing_log');
		$sections['axis360']->addAction(new AdminAction('Dashboard', 'View the usage dashboard for Axis 360 integration.', '/Axis360/Dashboard'), 'view_axis360_dashboard');

		$sections['cloud_library'] = new AdminSection('Cloud Library');
		$cloudLibrarySettingsAction = new AdminAction('Settings', 'Define connection information between Cloud Library and Aspen Discovery.', '/CloudLibrary/Settings');
		$cloudLibraryScopesAction = new AdminAction('Scopes', 'Define which records are loaded for each library and location.', '/CloudLibrary/Scopes');
		if ($sections['cloud_library']->addAction($cloudLibrarySettingsAction, 'view_cloud_library_settings')){
			$cloudLibrarySettingsAction->addSubAction($cloudLibraryScopesAction, 'view_cloud_library_settings');
		}else{
			$sections['cloud_library']->addAction($cloudLibraryScopesAction, 'view_cloud_library_settings');
		}
		$sections['cloud_library']->addAction(new AdminAction('Indexing Log', 'View the indexing log for Cloud Library.', '/CloudLibrary/IndexingLog'), 'view_cloud_library_indexing_log');
		$sections['cloud_library']->addAction(new AdminAction('Dashboard', 'View the usage dashboard for Cloud Library integration.', '/CloudLibrary/Dashboard'), 'view_cloud_library_dashboard');

		$sections['ebsco'] = new AdminSection('EBSCO');
		$sections['ebsco']->addAction(new AdminAction('Settings', 'Define connection information between Hoopla and Aspen Discovery.', '/EBSCO/EDSSettings'), 'view_ebsco_settings');
		$sections['ebsco']->addAction(new AdminAction('Dashboard', 'View the usage dashboard for Hoopla integration.', '/EBSCO/EDSDashboard'), 'view_ebsco_dashboard');

		$sections['hoopla'] = new AdminSection('Hoopla');
		$hooplaSettingsAction = new AdminAction('Settings', 'Define connection information between Hoopla and Aspen Discovery.', '/Hoopla/Settings');
		$hooplaScopesAction = new AdminAction('Scopes', 'Define which records are loaded for each library and location.', '/Hoopla/Scopes');
		if ($sections['hoopla']->addAction($hooplaSettingsAction, 'view_hoopla_settings')){
			$hooplaSettingsAction->addSubAction($hooplaScopesAction, 'view_hoopla_settings');
		}else{
			$sections['hoopla']->addAction($hooplaScopesAction, 'view_hoopla_settings');
		}
		$sections['hoopla']->addAction(new AdminAction('Indexing Log', 'View the indexing log for Hoopla.', '/Hoopla/IndexingLog'), 'view_hoopla_indexing_log');
		$sections['hoopla']->addAction(new AdminAction('Dashboard', 'View the usage dashboard for Hoopla integration.', '/Hoopla/Dashboard'), 'view_hoopla_dashboard');

		$sections['overdrive'] = new AdminSection('OverDrive');
		$overDriveSettingsAction = new AdminAction('Settings', 'Define connection information between OverDrive and Aspen Discovery.', '/OverDrive/Settings');
		$overDriveScopesAction = new AdminAction('Scopes', 'Define which records are loaded for each library and location.', '/OverDrive/Scopes');
		if ($sections['overdrive']->addAction($overDriveSettingsAction, 'view_overdrive_settings')){
			$overDriveSettingsAction->addSubAction($overDriveScopesAction, 'view_overdrive_settings');
		}else{
			$sections['overdrive']->addAction($overDriveScopesAction, 'view_overdrive_settings');
		}
		$sections['overdrive']->addAction(new AdminAction('Indexing Log', 'View the indexing log for OverDrive.', '/OverDrive/IndexingLog'), 'view_overdrive_indexing_log');
		$sections['overdrive']->addAction(new AdminAction('Dashboard', 'View the usage dashboard for OverDrive integration.', '/OverDrive/Dashboard'), 'view_overdrive_dashboard');
		$sections['overdrive']->addAction(new AdminAction('API Information', 'View API information for OverDrive integration to test connections.', '/OverDrive/APIData'), 'view_overdrive_api_data');

		$sections['rbdigital'] = new AdminSection('RBdigital');
		$rbdigitalSettingsAction = new AdminAction('Settings', 'Define connection information between RBdigital and Aspen Discovery.', '/RBdigital/Settings');
		$rbdigitalScopesAction = new AdminAction('Scopes', 'Define which records are loaded for each library and location.', '/RBdigital/Scopes');
		if ($sections['rbdigital']->addAction($rbdigitalSettingsAction, 'view_rbdigital_settings')){
			$rbdigitalSettingsAction->addSubAction($rbdigitalScopesAction, 'view_rbdigital_settings');
		}else{
			$sections['rbdigital']->addAction($rbdigitalScopesAction, 'view_rbdigital_settings');
		}
		$sections['rbdigital']->addAction(new AdminAction('Indexing Log', 'View the indexing log for RBdigital.', '/RBdigital/IndexingLog'), 'view_rbdigital_indexing_log');
		$sections['rbdigital']->addAction(new AdminAction('Dashboard', 'View the usage dashboard for RBdigital integration.', '/RBdigital/Dashboard'), 'view_rbdigital_dashboard');

		$sections['side_loads'] = new AdminSection('Side Loads');
		$sideLoadsSettingsAction = new AdminAction('Settings', 'Define connection information between Side Loads and Aspen Discovery.', '/SideLoads/Settings');
		$sideLoadsScopesAction = new AdminAction('Scopes', 'Define which records are loaded for each library and location.', '/SideLoads/Scopes');
		if ($sections['side_loads']->addAction($sideLoadsSettingsAction, 'view_side_loads_settings')){
			$sideLoadsSettingsAction->addSubAction($sideLoadsScopesAction, 'view_side_loads_settings');
		}else{
			$sections['side_loads']->addAction($sideLoadsScopesAction, 'view_side_loads_settings');
		}
		$sections['side_loads']->addAction(new AdminAction('Indexing Log', 'View the indexing log for Side Loads.', '/SideLoads/IndexingLog'), 'view_side_loads_indexing_log');
		$sections['side_loads']->addAction(new AdminAction('Dashboard', 'View the usage dashboard for Side Loads integration.', '/SideLoads/Dashboard'), 'view_side_loads_dashboard');

		if ($configArray['Islandora']['enabled']){
			$sections['islandora_archive'] = new AdminSection('Islandora Archives');
			$sections['islandora_archive']->addAction(new AdminAction('Authorship Claims', 'View submissions from users that they are the author of materials within the archive.', '/Admin/AuthorshipClaims'), 'view_archive_authorship_claims');
			$sections['islandora_archive']->addAction(new AdminAction('Clear Cache', 'Clear Archive information that has been cached within Aspen Discovery.', '/Admin/ClearArchiveCache'), 'clear_archive_cache');
			$sections['islandora_archive']->addAction(new AdminAction('Material Requests', 'View requests for copies of materials from the archive.', '/Admin/ArchiveRequests'), 'view_archive_materials_requests');
			$sections['islandora_archive']->addAction(new AdminAction('Subject Control', 'Determine how subjects are handled when loading explore more information from the archive.', '/Admin/ArchiveSubjects'), 'view_archive_subject_control');
			$sections['islandora_archive']->addAction(new AdminAction('Private Collections', 'Setup collections within the archive that should not be private.', '/Admin/ArchivePrivateCollections'), 'view_archive_private_collections');
			$sections['islandora_archive']->addAction(new AdminAction('Usage Statistics', 'View statistics for number of records and drive space used by each library contributing content to the archive.', '/Admin/ArchiveUsage'), 'view_archive_usage');
		}

		if (array_key_exists('Open Archives', $enabledModules)){
			$sections['open_archives'] = new AdminSection('Open Archives');
			$sections['open_archives']->addAction(new AdminAction('Collections', 'Define collections to be loaded into Aspen Discovery.', '/OpenArchives/Collections'), 'view_open_archives_collections');
			$sections['open_archives']->addAction(new AdminAction('Indexing Log', 'View the indexing log for Open Archives.', '/OpenArchives/IndexingLog'), 'view_open_archives_indexing_log');
			$sections['open_archives']->addAction(new AdminAction('Dashboard', 'View the usage dashboard for Open Archives integration.', '/OpenArchives/Dashboard'), 'view_open_archives_dashboard');
		}

		if (array_key_exists('Events', $enabledModules)){
			$sections['events'] = new AdminSection('Events');
			$sections['events']->addAction(new AdminAction('Library Market - Calendar Settings', 'Define collections to be loaded into Aspen Discovery.', '/Events/LMLibraryCalendarSettings'), 'view_library_market_calendar_settings');
			$sections['events']->addAction(new AdminAction('Indexing Log', 'View the indexing log for Events.', '/Events/IndexingLog'), 'view_events_indexing_log');
		}

		if (array_key_exists('Web Indexer', $enabledModules)){
			$sections['web_indexer'] = new AdminSection('Website Indexing');
			$sections['web_indexer']->addAction(new AdminAction('Settings', 'Define settings for indexing websites within Aspen Discovery.', '/Websites/Settings'), 'view_web_indexer_settings');
			$sections['web_indexer']->addAction(new AdminAction('Indexing Log', 'View the indexing log for Websites.', '/Websites/IndexingLog'), 'view_web_indexer_indexing_log');
			$sections['web_indexer']->addAction(new AdminAction('Dashboard', 'View the usage dashboard for indexed websites.', '/Websites/Dashboard'), 'view_web_indexer_dashboard');
		}

		$sections['aspen_help'] = new AdminSection('Aspen Discovery Help');
		$sections['aspen_help']->addAction(new AdminAction('Help Manual', 'View Help Manual for Aspen Discovery.', '/Admin/HelpManual?page=table_of_contents'), 'view_aspen_help_manual');
		$sections['aspen_help']->addAction(new AdminAction('Release Notes', 'View release notes for Aspen Discovery which contain information about new functionality and fixes for each release.', '/Admin/ReleaseNotes'), 'view_aspen_release_notes');
		$showSubmitTicket = false;
		try {
			require_once ROOT_DIR . '/sys/SystemVariables.php';
			$systemVariables = new SystemVariables();
			if ($systemVariables->find(true) && !empty($systemVariables->ticketEmail)) {
				$showSubmitTicket = true;
			}
		}catch (Exception $e) {
			//This happens before the table is setup
		}
		if ($showSubmitTicket) {
			$sections['aspen_help']->addAction(new AdminAction('Submit Ticket', 'Submit a support ticket for assistance with Aspen Discovery.', '/Admin/SubmitTicket'), 'submit_aspen_ticket');
		}

		return $sections;
	}

	public function getPermissions(){
		if ($this->_permissions == null){
			$this->_permissions = [];
			$roles = $this->getRoles();
			foreach ($roles as $role){
				$this->_permissions = array_merge($this->_permissions, $role->getPermissions());
			}
		}
		return $this->_permissions;
	}

	/**
	 * @param string[]|string $allowablePermissions
	 * @return bool
	 */
	public function hasPermission($allowablePermissions){
		$permissions = $this->getPermissions();
		if (is_array($allowablePermissions)){
			foreach ($allowablePermissions as $allowablePermission){
				if (array_key_exists($allowablePermission, $permissions)){
					return true;
				}
			}
		}else{
			if (array_key_exists($allowablePermissions, $permissions)){
				return true;
			}
		}
		return false;
	}
}

function modifiedEmpty($var) {
	// specified values of zero will not be considered empty
	return empty($var) && $var !== 0 && $var !== '0';
}