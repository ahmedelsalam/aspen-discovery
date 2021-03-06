<?php

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/LocalEnrichment/SystemMessage.php';

class Admin_SystemMessages extends ObjectEditor
{

	function getObjectType(){
		return 'SystemMessage';
	}
	function getToolName(){
		return 'SystemMessages';
	}
	function getPageTitle(){
		return 'SystemMessages';
	}
	function canDelete(){
		return UserAccount::userHasPermission(['Administer All System Messages','Administer Library System Messages']);
	}
	function getAllObjects($page, $recordsPerPage){
		$systemMessage = new SystemMessage();
		$systemMessage->orderBy('title');
		$systemMessage->limit(($page - 1) * $recordsPerPage, $recordsPerPage);
		$userHasExistingMessages = true;
		if (!UserAccount::userHasPermission('Administer All System Messages')){
			$librarySystemMessage = new SystemMessageLibrary();
			$library = Library::getPatronHomeLibrary(UserAccount::getActiveUserObj());
			if ($library != null){
				$librarySystemMessage->libraryId = $library->libraryId;
				$systemMessagesForLibrary = [];
				$librarySystemMessage->find();
				while ($librarySystemMessage->fetch()){
					$systemMessagesForLibrary[] = $librarySystemMessage->systemMessageId;
				}
				if (count($systemMessagesForLibrary) > 0) {
					$systemMessage->whereAddIn('id', $systemMessagesForLibrary, false);
				}else{
					$userHasExistingMessages = false;
				}
			}
		}
		$list = array();
		if ($userHasExistingMessages) {
			$systemMessage->find();
			while ($systemMessage->fetch()) {
				$list[$systemMessage->id] = clone $systemMessage;
			}
		}
		return $list;
	}
	function getObjectStructure(){
		return SystemMessage::getObjectStructure();
	}
	function getPrimaryKeyColumn(){
		return 'id';
	}
	function getIdKeyColumn(){
		return 'id';
	}
	function getInstructions()
	{
		return '';
	}
	function getBreadcrumbs()
	{
		$breadcrumbs = [];
		$breadcrumbs[] = new Breadcrumb('/Admin/Home', 'Administration Home');
		$breadcrumbs[] = new Breadcrumb('/Admin/Home#local_enrichment', 'Local Enrichment');
		$breadcrumbs[] = new Breadcrumb('/Admin/SystemMessages', 'SystemMessages');
		return $breadcrumbs;
	}

	function getActiveAdminSection()
	{
		return 'local_enrichment';
	}

	function canView()
	{
		return UserAccount::userHasPermission(['Administer All System Messages','Administer Library System Messages']);
	}
}