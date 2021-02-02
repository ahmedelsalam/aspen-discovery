<?php

require_once ROOT_DIR . '/sys/DB/DataObject.php';
require_once ROOT_DIR . '/sys/Browse/BaseBrowsable.php';

class CollectionSpotlightList extends BaseBrowsable
{
	public $__table = 'collection_spotlight_lists';    // table name
	public $id;                      //int(25)
	public $collectionSpotlightId;                    //varchar(255)
	public $name;
	public $displayFor;

	public $weight;

	static function getObjectStructure()
	{
		// Get All User Lists
		require_once ROOT_DIR . '/sys/UserLists/UserList.php';
		$sourceLists = UserList::getSourceListsForBrowsingAndCarousels();

		$spotlightSources = BaseBrowsable::getBrowseSources();

		return array(
			'id' => array(
				'property' => 'id',
				'type' => 'label',
				'label' => 'Id',
				'description' => 'The unique id of the collection spotlight list.'
			),
			'collectionSpotlightId' => array(
				'property' => 'collectionSpotlightId',
				'type' => 'foreignKey',
				'label' => 'Collection Spotlight',
				'description' => 'The spotlight this list is associated with.',
				'editLink' => '/Admin/CollectionSpotlights?id=propertyValue&objectAction=edit'
			),
			'name' => array(
				'property' => 'name',
				'type' => 'text',
				'label' => 'Name',
				'description' => 'The name of the list to display in the tab.',
				'required' => true,
			),
			'displayFor' => array(
				'property' => 'displayFor',
				'type' => 'enum',
				'values' => array('all' => 'Everyone', 'loggedIn' => 'Only when a user is logged in', 'notLoggedIn' => 'Only when no one is logged in'),
				'label' => 'Display For',
				'description' => 'Who this list should be displayed for.'
			),
			'source' => array(
				'property' => 'source',
				'type' => 'enum',
				'values' => $spotlightSources,
				'label' => 'Source',
				'description' => 'The source of the list.',
				'required' => true,
				'onchange' => "return AspenDiscovery.Admin.updateBrowseSearchForSource();"
			),
			'searchTerm' => array('property' => 'searchTerm', 'type' => 'text', 'label' => 'Search Term', 'description' => 'A default search term to apply to the category', 'default' => '', 'hideInLists' => true, 'maxLength' => 500),
			'defaultFilter' => array('property' => 'defaultFilter', 'type' => 'textarea', 'label' => 'Default Filter(s)', 'description' => 'Filters to apply to the search by default.', 'hideInLists' => true, 'rows' => 3, 'cols' => 80),
			'sourceListId' => array('property' => 'sourceListId', 'type' => 'enum', 'values' => $sourceLists, 'label' => 'Source List', 'description' => 'A public list to display titles from'),
			'defaultSort' => array('property' => 'defaultSort', 'type' => 'enum', 'label' => 'Default Sort', 'values' => array('relevance' => 'Best Match', 'popularity' => 'Popularity', 'newest_to_oldest' => 'Date Added', 'author' => 'Author', 'title' => 'Title', 'user_rating' => 'Rating'), 'description' => 'The default sort for the search if none is specified', 'default' => 'relevance', 'hideInLists' => true),
		);
	}

	public function insert()
	{
		if ($this->source == null){
			$this->source = '';
		}
		return parent::insert(); // TODO: Change the autogenerated stub
	}

	/** @noinspection PhpUnused */
	function getSourceListName(){
		require_once ROOT_DIR . '/sys/UserLists/UserList.php';
		if ($this->sourceListId != null && $this->sourceListId > 0){
			$userList = new UserList();
			$userList->id = $this->sourceListId;
			if ($userList->find(true)){
				return $userList->title;
			}else{
				return "Invalid List ({$this->sourceListId})";
			}

		}else{
			return "";
		}
	}

	/** @noinspection PhpUnused */
	function fullListLink()
	{
		global $configArray;
		if ($this->sourceListId != null && $this->sourceListId > 0){
			return $configArray['Site']['url'] . '/MyAccount/MyList/' . $this->sourceListId;
		}else{
			$searchObject = $this->getSearchObject();
			$link = $configArray['Site']['url'] . $searchObject->renderSearchUrl();
			$spotlight = $this->getCollectionSpotlight();
			if ($spotlight->viewMoreLinkMode == 'covers') {
				$link .= '&view=covers';
			}
			return $link;
		}
	}

	function __toString()
	{
		return "{$this->name} ($this->source)";
	}

	/**
	 * @return SearchObject_BaseSearcher
	 */
	public function getSearchObject()
	{
		/** @var SearchObject_GroupedWorkSearcher $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject($this->source);
		if (!empty($this->defaultFilter)) {
			$defaultFilterInfo = $this->defaultFilter;
			$defaultFilters = preg_split('/[\r\n,;]+/', $defaultFilterInfo);
			foreach ($defaultFilters as $filter) {
				$searchObject->addFilter(trim($filter));
			}
		}
		//Set Sorting, this is actually slightly mangled from the category to Solr
		$searchObject->setSort($this->getSolrSort());
		if ($this->searchTerm != '') {
			$searchObject->setSearchTerm($this->searchTerm);
		}

		//Get titles for the list
		$searchObject->clearFacets();
		$searchObject->disableSpelling();
		$searchObject->disableLogging();
		$searchObject->setLimit($this->getCollectionSpotlight()->numTitlesToShow);
		$searchObject->setPage(1);

		return $searchObject;
	}

	private $_collectionSpotlight = null;
	function getCollectionSpotlight(){
		if ($this->_collectionSpotlight == null){
			$this->_collectionSpotlight = new CollectionSpotlight();
			$this->_collectionSpotlight->id = $this->collectionSpotlightId;
			$this->_collectionSpotlight->find(true);
		}
		return $this->_collectionSpotlight;
	}

	function getEditLink(){
		return '/Admin/CollectionSpotlightLists?objectAction=edit&id=' . $this->id;
	}
}