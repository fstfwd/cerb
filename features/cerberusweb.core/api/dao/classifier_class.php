<?php
class DAO_ClassifierClass extends Cerb_ORMHelper {
	const _CACHE_ALL = 'cerb_classifier_classes';
	
	const ID = 'id';
	const CLASSIFIER_ID = 'classifier_id';
	const NAME = 'name';
	const UPDATED_AT = 'updated_at';
	const SLOTS_JSON = 'slots_json';
	const DICTIONARY_SIZE = 'dictionary_size';
	const TRAINING_COUNT = 'training_count';
	
	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO classifier_class () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function clearCache() {
		$cache = DevblocksPlatform::getCacheService();
		$cache->remove(self::_CACHE_ALL);
	}
	
	static function update($ids, $fields, $check_deltas=true) {
		if(!is_array($ids))
			$ids = array($ids);
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
				
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges(CerberusContexts::CONTEXT_CLASSIFIER_CLASS, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'classifier_class', $fields);
			
			// Send events
			if($check_deltas) {
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.classifier_class.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_CLASSIFIER_CLASS, $batch_ids);
			}
		}
		
		self::clearCache();
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('classifier_class', $fields, $where);
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_ClassifierClass[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null, $options=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, classifier_id, name, updated_at, slots_json, dictionary_size, training_count ".
			"FROM classifier_class ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		
		if($options & Cerb_ORMHelper::OPT_GET_MASTER_ONLY) {
			$rs = $db->ExecuteMaster($sql, _DevblocksDatabaseManager::OPT_NO_READ_AFTER_WRITE);
		} else {
			$rs = $db->ExecuteSlave($sql);
		}
		
		return self::_getObjectsFromResult($rs);
	}
	
	/**
	 *
	 * @param bool $nocache
	 * @return Model_ClassifierClass[]
	 */
	static function getAll($nocache=false) {
		$cache = DevblocksPlatform::getCacheService();
		if($nocache || null === ($objects = $cache->load(self::_CACHE_ALL))) {
			$objects = self::getWhere(null, DAO_ClassifierClass::NAME, true, null, Cerb_ORMHelper::OPT_GET_MASTER_ONLY);
			
			if(!is_array($objects))
				return false;
			
			$cache->save($objects, self::_CACHE_ALL);
		}
		
		return $objects;
	}

	/**
	 * @param integer $id
	 * @return Model_ClassifierClass
	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$classifications = DAO_ClassifierClass::getAll();

		if(isset($classifications[$id]))
			return $classifications[$id];
		
		return null;
	}
	
	/**
	 * 
	 * @param array $ids
	 * @return Model_ClassifierClass[]
	 */
	static function getIds($ids) {
		if(!is_array($ids))
			$ids = [$ids];
		
		if(empty($ids))
			return [];
		
		$classifications = DAO_ClassifierClass::getAll();
		
		if(empty($classifications))
			return [];
		
		return array_intersect_key($classifications, array_flip($ids));
	}
	
	static function getByClassifierId($classifier_id) {
		$classes = self::getAll();
		
		return array_filter($classes, function($class) use ($classifier_id) { /* @var $class Model_ClassifierClass */
			return $class->classifier_id == $classifier_id;
		});
	}
	
	/**
	 * @param resource $rs
	 * @return Model_ClassifierClass[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_ClassifierClass();
			$object->id = intval($row['id']);
			$object->classifier_id = intval($row['classifier_id']);
			$object->name = $row['name'];
			$object->updated_at = intval($row['updated_at']);
			$object->dictionary_size = $row['dictionary_size'];
			$object->training_count = $row['training_count'];
			
			@$slots_json = json_decode($row['slots_json']);
			$object->attribs = is_array($slots_json) ? $slots_json : [];
			
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function random() {
		return self::_getRandom('classifier_class');
	}
	
	static public function count($classifier_id) {
		$db = DevblocksPlatform::getDatabaseService();
		return $db->GetOneSlave(sprintf("SELECT count(id) FROM classifier_class ".
			"WHERE classifier_id = %d",
			$classifier_id
		));
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM classifier_class WHERE id IN (%s)", $ids_list));
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => CerberusContexts::CONTEXT_CLASSIFIER_CLASS,
					'context_ids' => $ids
				)
			)
		);
		
		self::clearCache();
		return true;
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_ClassifierClass::getFields();
		
		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, 'SearchFields_ClassifierClass', $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"classifier_class.id as %s, ".
			"classifier_class.classifier_id as %s, ".
			"classifier_class.name as %s, ".
			"classifier_class.updated_at as %s, ".
			"classifier_class.dictionary_size as %s, ".
			"classifier_class.training_count as %s ",
				SearchFields_ClassifierClass::ID,
				SearchFields_ClassifierClass::CLASSIFIER_ID,
				SearchFields_ClassifierClass::NAME,
				SearchFields_ClassifierClass::UPDATED_AT,
				SearchFields_ClassifierClass::DICTIONARY_SIZE,
				SearchFields_ClassifierClass::TRAINING_COUNT
			);
			
		$join_sql = "FROM classifier_class ";
		
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields, $select_sql, 'SearchFields_ClassifierClass');
	
		// Virtuals
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
		);
	
		array_walk_recursive(
			$params,
			array('DAO_ClassifierClass', '_translateVirtualParameters'),
			$args
		);
		
		return array(
			'primary_table' => 'classifier_class',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'sort' => $sort_sql,
		);
	}
	
	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
			
		$from_context = CerberusContexts::CONTEXT_CLASSIFIER_CLASS;
		$from_index = 'classifier_class.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
			case SearchFields_ClassifierClass::VIRTUAL_HAS_FIELDSET:
				self::_searchComponentsVirtualHasFieldset($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		}
	}
	
	/**
	 * Enter description here...
	 *
	 * @param array $columns
	 * @param DevblocksSearchCriteria[] $params
	 * @param integer $limit
	 * @param integer $page
	 * @param string $sortBy
	 * @param boolean $sortAsc
	 * @param boolean $withCounts
	 * @return array
	 */
	static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();
		
		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			$sort_sql;
			
		if($limit > 0) {
			if(false == ($rs = $db->SelectLimit($sql,$limit,$page*$limit)))
				return false;
		} else {
			if(false == ($rs = $db->ExecuteSlave($sql)))
				return false;
			$total = mysqli_num_rows($rs);
		}
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_ClassifierClass::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					"SELECT COUNT(classifier_class.id) ".
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}
};

class SearchFields_ClassifierClass extends DevblocksSearchFields {
	const ID = 'c_id';
	const CLASSIFIER_ID = 'c_classifier_id';
	const NAME = 'c_name';
	const UPDATED_AT = 'c_updated_at';
	const DICTIONARY_SIZE = 'c_dictionary_size';
	const TRAINING_COUNT = 'c_training_count';

	const VIRTUAL_CLASSIFIER_SEARCH = '*_classifier_search';
	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	
	static private $_fields = null;
	
	static function getPrimaryKey() {
		return 'classifier_class.id';
	}
	
	static function getCustomFieldContextKeys() {
		// [TODO] Context
		return array(
			'' => new DevblocksSearchFieldContextKeys('classifier_class.id', self::ID),
		);
	}
	
	static function getWhereSQL(DevblocksSearchCriteria $param) {
		switch($param->field) {
			case self::VIRTUAL_CLASSIFIER_SEARCH:
				return self::_getWhereSQLFromVirtualSearchField($param, CerberusContexts::CONTEXT_CLASSIFIER, 'classifier_class.classifier_id');
				break;
			
			case self::VIRTUAL_CONTEXT_LINK:
				return self::_getWhereSQLFromContextLinksField($param, CerberusContexts::CONTEXT_CLASSIFIER_CLASS, self::getPrimaryKey());
				break;
			
			default:
				if('cf_' == substr($param->field, 0, 3)) {
					return self::_getWhereSQLFromCustomFields($param);
				} else {
					return $param->getWhereSQL(self::getFields(), self::getPrimaryKey());
				}
				break;
		}
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		if(is_null(self::$_fields))
			self::$_fields = self::_getFields();
		
		return self::$_fields;
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function _getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'classifier_class', 'id', $translate->_('common.id'), null, true),
			self::CLASSIFIER_ID => new DevblocksSearchField(self::CLASSIFIER_ID, 'classifier_class', 'classifier_id', $translate->_('common.classifier'), null, true),
			self::NAME => new DevblocksSearchField(self::NAME, 'classifier_class', 'name', $translate->_('common.name'), null, true),
			self::UPDATED_AT => new DevblocksSearchField(self::UPDATED_AT, 'classifier_class', 'updated_at', $translate->_('common.updated'), null, true),
			self::DICTIONARY_SIZE => new DevblocksSearchField(self::DICTIONARY_SIZE, 'classifier_class', 'dictionary_size', $translate->_('dao.classifier.dictionary_size'), null, true),
			self::TRAINING_COUNT => new DevblocksSearchField(self::TRAINING_COUNT, 'classifier_class', 'training_count', $translate->_('common.examples'), null, true),

			self::VIRTUAL_CLASSIFIER_SEARCH => new DevblocksSearchField(self::VIRTUAL_CLASSIFIER_SEARCH, '*', 'classifier_search', null, null, false),
			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null, false),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null, false),
		);
		
		// Custom Fields
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array_keys(self::getCustomFieldContextKeys()));
		
		if(!empty($custom_columns))
			$columns = array_merge($columns, $custom_columns);

		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_ClassifierClass {
	public $id = 0;
	public $classifier_id = 0;
	public $name = null;
	public $updated_at = 0;
	public $attribs = [];
	public $dictionary_size = 0;
	public $training_count= 0;
	
	function getClassifier() {
		return DAO_Classifier::get($this->classifier_id);
	}
};

class View_ClassifierClass extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'classifier_classes';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = mb_convert_case($translate->_('common.classifier.classifications'), MB_CASE_TITLE);
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_ClassifierClass::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_ClassifierClass::NAME,
			SearchFields_ClassifierClass::CLASSIFIER_ID,
			SearchFields_ClassifierClass::TRAINING_COUNT,
			SearchFields_ClassifierClass::UPDATED_AT,
		);

		$this->addColumnsHidden(array(
			SearchFields_ClassifierClass::VIRTUAL_CLASSIFIER_SEARCH,
			SearchFields_ClassifierClass::VIRTUAL_CONTEXT_LINK,
			SearchFields_ClassifierClass::VIRTUAL_HAS_FIELDSET,
		));
		
		$this->addParamsHidden(array(
			SearchFields_ClassifierClass::VIRTUAL_CLASSIFIER_SEARCH,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_ClassifierClass::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		
		$this->_lazyLoadCustomFieldsIntoObjects($objects, 'SearchFields_ClassifierClass');
		
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_ClassifierClass', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_ClassifierClass', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
				case SearchFields_ClassifierClass::CLASSIFIER_ID:
					$pass = true;
					break;
					
				// Virtuals
				case SearchFields_ClassifierClass::VIRTUAL_CONTEXT_LINK:
				case SearchFields_ClassifierClass::VIRTUAL_HAS_FIELDSET:
					$pass = true;
					break;
					
				// Valid custom fields
				default:
					if('cf_' == substr($field_key,0,3))
						$pass = $this->_canSubtotalCustomField($field_key);
					break;
			}
			
			if($pass)
				$fields[$field_key] = $field_model;
		}
		
		return $fields;
	}
	
	function getSubtotalCounts($column) {
		$counts = array();
		$fields = $this->getFields();
		$context = CerberusContexts::CONTEXT_CLASSIFIER_CLASS;

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
			case SearchFields_ClassifierClass::CLASSIFIER_ID:
				$classifiers = DAO_Classifier::getAll();
				$label_map = array_column(json_decode(json_encode($classifiers), true), 'name', 'id');
				$counts = $this->_getSubtotalCountForNumberColumn($context, $column, $label_map);
				break;

			case SearchFields_ClassifierClass::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn($context, $column);
				break;

			case SearchFields_ClassifierClass::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn($context, $column);
				break;
				
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn($context, $column);
				}
				
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$search_fields = SearchFields_ClassifierClass::getFields();
	
		$fields = array(
			'classifier' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_ClassifierClass::VIRTUAL_CLASSIFIER_SEARCH),
					'examples' => [
						['type' => 'search', 'context' => CerberusContexts::CONTEXT_CLASSIFIER, 'q' => ''],
					]
				),
			'classifier.id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_ClassifierClass::CLASSIFIER_ID),
					'examples' => [
						['type' => 'chooser', 'context' => CerberusContexts::CONTEXT_CLASSIFIER, 'q' => ''],
					]
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_ClassifierClass::ID),
					'examples' => [
						['type' => 'chooser', 'context' => CerberusContexts::CONTEXT_CLASSIFIER_CLASS, 'q' => ''],
					]
				),
			'text' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_ClassifierClass::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_ClassifierClass::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_ClassifierClass::UPDATED_AT),
				),
		);
		
		// Add quick search links
		
		$fields = self::_appendVirtualFiltersFromQuickSearchContexts('links', $fields, 'links');
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_CLASSIFIER_CLASS, $fields, null);
		
		// Add is_sortable
		
		$fields = self::_setSortableQuickSearchFields($fields, $search_fields);
		
		// Sort by keys
		ksort($fields);
		
		return $fields;
	}	
	
	function getParamFromQuickSearchFieldTokens($field, $tokens) {
		switch($field) {
			case 'classifier':
				return DevblocksSearchCriteria::getVirtualQuickSearchParamFromTokens($field, $tokens, SearchFields_ClassifierClass::VIRTUAL_CLASSIFIER_SEARCH);
				break;
				
			default:
				if($field == 'links' || substr($field, 0, 6) == 'links.')
					return DevblocksSearchCriteria::getContextLinksParamFromTokens($field, $tokens);
				
				$search_fields = $this->getQuickSearchFields();
				return DevblocksSearchCriteria::getParamFromQueryFieldTokens($field, $tokens, $search_fields);
				break;
		}
		
		return false;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		$classifiers = DAO_Classifier::getAll();
		$tpl->assign('classifiers', $classifiers);
		
		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_CLASSIFIER_CLASS);
		$tpl->assign('custom_fields', $custom_fields);

		$tpl->assign('view_template', 'devblocks:cerberusweb.core::internal/classifier/class/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_ClassifierClass::NAME:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_ClassifierClass::CLASSIFIER_ID:
			case SearchFields_ClassifierClass::DICTIONARY_SIZE:
			case SearchFields_ClassifierClass::ID:
			case SearchFields_ClassifierClass::TRAINING_COUNT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_ClassifierClass::UPDATED_AT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_ClassifierClass::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
				
			case SearchFields_ClassifierClass::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, CerberusContexts::CONTEXT_CLASSIFIER_CLASS);
				break;
				
			default:
				// Custom Fields
				if('cf_' == substr($field,0,3)) {
					$this->_renderCriteriaCustomField($tpl, substr($field,3));
				} else {
					echo ' ';
				}
				break;
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			case SearchFields_ClassifierClass::CLASSIFIER_ID:
				$classifiers = DAO_Classifier::getAll();
				$label_map = array_column(json_decode(json_encode($classifiers), true), 'name', 'id');
				parent::_renderCriteriaParamString($param, $label_map);
				break;
				
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		$translate = DevblocksPlatform::getTranslationService();
		
		switch($key) {
			case SearchFields_ClassifierClass::VIRTUAL_CLASSIFIER_SEARCH:
				echo sprintf("Classifier matches <b>%s</b>", DevblocksPlatform::strEscapeHtml($param->value));
				break;
			
			case SearchFields_ClassifierClass::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_ClassifierClass::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_ClassifierClass::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_ClassifierClass::NAME:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_ClassifierClass::CLASSIFIER_ID:
			case SearchFields_ClassifierClass::DICTIONARY_SIZE:
			case SearchFields_ClassifierClass::ID:
			case SearchFields_ClassifierClass::TRAINING_COUNT:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_ClassifierClass::UPDATED_AT:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_ClassifierClass::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_ClassifierClass::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}
};

class Context_ClassifierClass extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek, IDevblocksContextAutocomplete { // IDevblocksContextImport
	static function isReadableByActor($models, $actor) {
		return CerberusContexts::isReadableByDelegateOwner($actor, CerberusContexts::CONTEXT_CLASSIFIER_CLASS, $models, 'classifier_owner_');
	}
	
	static function isWriteableByActor($models, $actor) {
		return CerberusContexts::isWriteableByDelegateOwner($actor, CerberusContexts::CONTEXT_CLASSIFIER_CLASS, $models, 'classifier_owner_');
	}
	
	function getRandom() {
		return DAO_ClassifierClass::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy('c=profiles&type=classifier_class&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$classifier_class = DAO_ClassifierClass::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($classifier_class->name);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $classifier_class->id,
			'name' => $classifier_class->name,
			'permalink' => $url,
			'updated' => $classifier_class->updated_at,
		);
	}
	
	function getDefaultProperties() {
		return array(
			'classifier__label',
			'updated_at',
		);
	}
	
	function autocomplete($term, $query=null) {
		$url_writer = DevblocksPlatform::getUrlService();
		$list = array();
		
		$view = Context_ClassifierClass::getSearchView('autocomplete_classifier');
		$view->renderLimit = 25;
		$view->renderSortBy = SearchFields_ClassifierClass::NAME;
		$view->renderSortAsc = true;
		$view->is_ephemeral = true;
		
		$view->addParamsWithQuickSearch($query, true);
		$view->addParam(new DevblocksSearchCriteria(SearchFields_ClassifierClass::NAME,DevblocksSearchCriteria::OPER_LIKE,'%'.$term.'%'));
		
		list($results, $null) = $view->getData();
		
		foreach($results AS $row){
			$entry = new stdClass();
			$entry->label = $row[SearchFields_ClassifierClass::NAME];
			$entry->value = $row[SearchFields_ClassifierClass::ID];
			$list[] = $entry;
		}
		
		return $list;
	}
	
	function getContext($classifier_class, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Classifier:Class:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_CLASSIFIER_CLASS);

		// Polymorph
		if(is_numeric($classifier_class)) {
			$classifier_class = DAO_ClassifierClass::get($classifier_class);
		} elseif($classifier_class instanceof Model_ClassifierClass) {
			// It's what we want already.
		} elseif(is_array($classifier_class)) {
			$classifier_class = Cerb_ORMHelper::recastArrayToModel($classifier_class, 'Model_ClassifierClass');
		} else {
			$classifier_class = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'name' => $prefix.$translate->_('common.name'),
			'updated_at' => $prefix.$translate->_('common.updated'),
			'record_url' => $prefix.$translate->_('common.url.record'),
				
			'classifier__label' => $prefix.$translate->_('common.classifier'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
			'updated_at' => Model_CustomField::TYPE_DATE,
			'record_url' => Model_CustomField::TYPE_URL,
				
			'classifier__label' => 'context_url',
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = CerberusContexts::CONTEXT_CLASSIFIER_CLASS;
		$token_values['_types'] = $token_types;
		
		if($classifier_class) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $classifier_class->name;
			$token_values['id'] = $classifier_class->id;
			$token_values['name'] = $classifier_class->name;
			$token_values['updated_at'] = $classifier_class->updated_at;
			
			$token_values['classifier_id'] = $classifier_class->classifier_id;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($classifier_class, $token_values);
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=classifier_class&id=%d-%s",$classifier_class->id, DevblocksPlatform::strToPermalink($classifier_class->name)), true);
		}
		
		// Classifier
		$merge_token_labels = array();
		$merge_token_values = array();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_CLASSIFIER, null, $merge_token_labels, $merge_token_values, '', true);

		CerberusContexts::merge(
			'classifier_',
			$prefix.'Classifier:',
			$merge_token_labels,
			$merge_token_values,
			$token_labels,
			$token_values
		);
		
		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_CLASSIFIER_CLASS;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true, true);
		}
		
		switch($token) {
			case 'links':
				$links = $this->_lazyLoadLinks($context, $context_id);
				$values = array_merge($values, $fields);
				break;
			
			default:
				if(DevblocksPlatform::strStartsWith($token, 'custom_')) {
					$fields = $this->_lazyLoadCustomFields($token, $context, $context_id);
					$values = array_merge($values, $fields);
				}
				break;
		}
		
		return $values;
	}
	
	function getChooserView($view_id=null) {
		$active_worker = CerberusApplication::getActiveWorker();

		if(empty($view_id))
			$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
	
		// View
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Classifier Classes';
		/*
		$view->addParams(array(
			SearchFields_ClassifierClass::UPDATED_AT => new DevblocksSearchCriteria(SearchFields_ClassifierClass::UPDATED_AT,'=',0),
		), true);
		*/
		$view->renderSortBy = SearchFields_ClassifierClass::NAME;
		$view->renderSortAsc = true;
		$view->renderLimit = 10;
		$view->renderFilters = false;
		$view->renderTemplate = 'contextlinks_chooser';
		
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Classifier Class';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_ClassifierClass::VIRTUAL_CONTEXT_LINK,'in',array($context.':'.$context_id)),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='', $edit=false) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		$context = CerberusContexts::CONTEXT_CLASSIFIER_CLASS;
		
		if(!empty($context_id)) {
			$model = DAO_ClassifierClass::get($context_id);
		}
		
		if(empty($context_id) || $edit) {
			if(!isset($model)) {
				$model = new Model_ClassifierClass();
				
				if(false != ($view = C4_AbstractViewLoader::getView($view_id))) {
					$filters = $view->findParam(SearchFields_ClassifierClass::CLASSIFIER_ID, $view->getParams());
					
					if(!empty($filters)) {
						$filter = array_shift($filters);
						if(is_numeric($filter->value))
							$model->classifier_id = $filter->value;
					}
				}
			}
			$tpl->assign('model', $model);
			
			$classifiers = DAO_Classifier::getAll();
			$tpl->assign('classifiers', $classifiers);
				
			// Custom fields
			$custom_fields = DAO_CustomField::getByContext($context, false);
			$tpl->assign('custom_fields', $custom_fields);
			
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds($context, $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
			
			$types = Model_CustomField::getTypes();
			$tpl->assign('types', $types);
			
			// View
			$tpl->assign('id', $context_id);
			$tpl->assign('view_id', $view_id);
			$tpl->display('devblocks:cerberusweb.core::internal/classifier/class/peek_edit.tpl');
			
		} else {
			// Counts
			$activity_counts = array(
				'examples' => DAO_ClassifierExample::countByClass($context_id),
				//'comments' => DAO_Comment::count($context, $context_id),
			);
			$tpl->assign('activity_counts', $activity_counts);
			
			// Links
			$links = array(
				$context => array(
					$context_id => 
						DAO_ContextLink::getContextLinkCounts(
							$context,
							$context_id,
							array(CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
						),
				),
			);
			$tpl->assign('links', $links);
			
			// Context
			if(false == ($context_ext = Extension_DevblocksContext::get($context)))
				return;
			
			// Dictionary
			$labels = array();
			$values = array();
			CerberusContexts::getContext($context, $model, $labels, $values, '', true, false);
			$dict = DevblocksDictionaryDelegate::instance($values);
			$tpl->assign('dict', $dict);
			
			$properties = $context_ext->getCardProperties();
			$tpl->assign('properties', $properties);
			
			$tpl->display('devblocks:cerberusweb.core::internal/classifier/class/peek.tpl');
		}
	}
};
