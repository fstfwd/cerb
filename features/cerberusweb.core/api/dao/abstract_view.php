<?php
/***********************************************************************
 | Cerb(tm) developed by Webgroup Media, LLC.
 |-----------------------------------------------------------------------
 | All source code & content (c) Copyright 2002-2017, Webgroup Media LLC
 |   unless specifically noted otherwise.
 |
 | This source code is released under the Devblocks Public License.
 | The latest version of this license can be found here:
 | http://cerb.ai/license
 |
 | By using this software, you acknowledge having read this license
 | and agree to be bound thereby.
 | ______________________________________________________________________
 |	http://cerb.ai	    http://webgroup.media
 ***********************************************************************/

abstract class C4_AbstractView {
	public $id = null;
	public $is_ephemeral = 0;
	public $name = "";
	public $options = array();
	
	public $view_columns = array();
	private $_columnsHidden = array();
	
	private $_paramsEditable = array();
	private $_paramsDefault = array();
	private $_paramsRequired = array();
	private $_paramsHidden = array();
	
	public $renderPage = 0;
	public $renderLimit = 10;
	public $renderTotal = true;
	public $renderSortBy = '';
	public $renderSortAsc = 1;

	public $renderFilters = null;
	public $renderSubtotals = null;
	
	public $renderTemplate = null;

	abstract function getData();
	function getDataAsObjects($ids=null) { return array(); }
	function getDataSample($size) {}
	
	private $_placeholderLabels = array();
	private $_placeholderValues = array();
	
	public function __destruct() {
		if(isset($this->__auto_persist) && !$this->__auto_persist)
			return;
		
		if(empty($this->id))
			return;
		
		$this->persist();
	}
	
	public function persist() {
		C4_AbstractViewLoader::setView($this->id, $this);
	}
	
	public function setAutoPersist($auto_persist) {
		if($auto_persist) {
			unset($this->__auto_persist);
		} else {
			$this->__auto_persist = false;
		}
	}
	
	protected function _getDataAsObjects($dao_class, $ids=null, &$total=null) {
		if(is_null($ids)) {
			if(!method_exists($dao_class,'search'))
				return array();
			
			$data = call_user_func_array(
				array($dao_class, 'search'),
				array(
					$this->view_columns,
					$this->getParams(),
					$this->renderLimit,
					$this->renderPage,
					$this->renderSortBy,
					$this->renderSortAsc,
					true
				)
			);
			
			list($results, $total) = $data;
			
			$ids = array_keys($results);
			
		} else {
			$total = count($ids);
		}
		
		if(!is_array($ids) || empty($ids))
			return array();

		$sql = sprintf("id IN (%s)",
			implode(',', $ids)
		);

		if(!method_exists($dao_class, 'getWhere'))
			return array();
		
		$results = array();
		
		$models = call_user_func_array(
			array($dao_class, 'getWhere'),
			array(
				$sql,
				null,
			)
		);
		
		foreach($ids as $id) {
			if(!isset($models[$id]))
				continue;
			
			$results[$id] = $models[$id];
		}
		
		unset($models);
		
		return $results;
	}
	
	protected function _doGetDataSample($dao_class, $size, $id_col = 'id') {
		$db = DevblocksPlatform::getDatabaseService();

		if(!method_exists($dao_class,'getSearchQueryComponents'))
			return array();
		
		$query_parts = call_user_func_array(
			array($dao_class,'getSearchQueryComponents'),
			array(
				$this->view_columns,
				$this->getParams(),
				$this->renderSortBy,
				$this->renderSortAsc
			)
		);
		
		$select_sql = sprintf("SELECT %s.%s ",
			$query_parts['primary_table'],
			$id_col
		);
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$sort_sql = sprintf("ORDER BY RAND() LIMIT %d ", $size);
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			$sort_sql;

		$rs = $db->ExecuteSlave($sql);
		
		$objects = array();
		while($row = mysqli_fetch_row($rs)) {
			$objects[] = $row[0];
		}
		
		return $objects;
	}
	
	protected function _lazyLoadCustomFieldsIntoObjects(&$objects, $search_class) {
		if(!$search_class || !class_exists($search_class) || !class_implements('DevblocksSearchFields'))
			return false;
		
		if(!is_array($objects) || !isset($objects[0]) || !is_array($objects[0]))
			return false;
		
		$fields = $search_class::getFields();
		$custom_fields = DAO_CustomField::getAll();
		
		$cfield_columns = array_values(array_filter($this->view_columns, function($field_key) {
			return 'cf_' == substr($field_key, 0, 3);
		}));
		
		$cfield_contexts = array();
		
		// Remove any cfields that we're sorting on (we already have their values in SELECT)
		$sort_columns = is_array($this->renderSortBy) ? $this->renderSortBy : array($this->renderSortBy);
		$cfield_columns = array_diff($cfield_columns, $sort_columns);
		
		foreach($cfield_columns as $cfield_key) {
			$cfield_id = intval(substr($cfield_key, 3));
			
			if(!$cfield_id || false == (@$cfield = $custom_fields[$cfield_id]))
				continue;
			
			if(false == ($field_key = $search_class::getCustomFieldContextFieldKey($cfield->context))
				|| !isset($fields[$field_key]))
					continue;
				
			if(!isset($cfield_contexts[$cfield->context]))
				$cfield_contexts[$cfield->context] = array();
				
			$cfield_contexts[$cfield->context][$cfield_key] = array('context' => $cfield->context, 'on_key' => $field_key);
		}
		
		foreach($cfield_contexts as $cfield_context => $cfields) {
			foreach($cfields as $cfield_key => $cfield_data) {
				$on_key = $cfield_data['on_key'];
				
				if(empty($on_key))
					continue;
				
				$ids = DevblocksPlatform::extractArrayValues($objects, $on_key);
				$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds($cfield_context, $ids);
				
				array_walk($objects[0], function(&$object) use ($on_key, $custom_field_values) {
					foreach($custom_field_values as $join_id => $values) {
						if(!isset($object[$on_key]) || $object[$on_key] != $join_id)
							continue;
						
						foreach($values as $k => $v) {
							if(is_array($v))
								$v = implode(', ', $v);
							
							$object['cf_' . $k] = $v;
						}
					}
				});
			}
		}
	}
	
	function isCustom() {
		return 'cust_' == substr($this->id, 0, 5);
	}

	function getColumnsAvailable() {
		$columns = $this->getFields();
		
		foreach($this->getColumnsHidden() as $col)
			unset($columns[$col]);
			
		return $columns;
	}
	
	// Columns Hidden

	function getColumnsHidden() {
		$columnsHidden = $this->_columnsHidden;
		
		if(!is_array($columnsHidden))
			$columnsHidden = array();
			
		return $columnsHidden;
	}
	
	function addColumnsHidden($columnsToHide, $replace=false) {
		if($replace)
			$this->_columnsHidden = $columnsToHide;
		else
			$this->_columnsHidden = array_unique(array_merge($this->getColumnsHidden(), $columnsToHide));
	}
	
	// Params Editable
	
	function getParamsAvailable($filter_fieldsets=false) {
		$params = $this->getFields();
		
		if(is_array($this->_paramsHidden))
		foreach($this->_paramsHidden as $param)
			unset($params[$param]);
		
		// Hide other custom fields when filtering to a specific fieldset
		if($filter_fieldsets)
			$params = $this->_filterParamsByFieldset($params);
		
		return $params;
	}

	private function _filterParamsByFieldset($params) {
		$results = $this->findParam('*_has_fieldset', $this->getParams(false));
		
		if(!empty($results)) {
			$fieldset_ids = array();
			
			foreach($results as $result) { /* @var $result DevblocksSearchField */
				if($result->operator == DevblocksSearchCriteria::OPER_IN) {
					$fieldset_ids = array_merge($fieldset_ids, $result->value);
				}
			}
			
			if(!empty($fieldset_ids)) {
				foreach($params as $k => $param) {
					if('cf_' == substr($k,0,3)) {
						list($prefix, $field_id) = explode('_', $k, 2);
						$cfield = DAO_CustomField::get($field_id);
						
						if(empty($cfield->custom_fieldset_id))
							continue;
						
						if(!in_array($cfield->custom_fieldset_id, $fieldset_ids))
							unset($params[$k]);
					}
				}
			}
		}
		
		return $params;
	}
	
	function getParams($parse_placeholders=true) {
		$params = DevblocksPlatform::deepCloneArray($this->_paramsEditable);
		
		// Required should supersede editable
		
		if(is_array($this->_paramsRequired)) {
			$params_required = DevblocksPlatform::deepCloneArray($this->_paramsRequired);
			
			foreach($params_required as $key => $param) {
				$params['req_'.$key] = $param;
			}
		}
		
		if($parse_placeholders) {
			// Translate snippets in filters
			array_walk_recursive(
				$params,
				array('C4_AbstractView', '_translatePlaceholders'),
				array(
					'placeholder_values' => $this->getPlaceholderValues(),
				)
			);
		}
		
		return $params;
	}
	
	function getEditableParams() {
		return $this->_paramsEditable;
	}
	
	function addParam($param, $key=null) {
		if(!$key || is_numeric($key))
			$key = uniqid();
		
		$this->_paramsEditable[$key] = $param;
	}
	
	function addParams($params, $replace=false) {
		if($replace)
			$this->removeAllParams();
		
		if(is_array($params))
		foreach($params as $key => $param) {
			$key = is_numeric($key) ? null : $key;
			$this->addParam($param, $key);
		}
	}
	
	function getParamsFromQuickSearch($query) {
		if(!($this instanceof IAbstractView_QuickSearch))
			return false;
		
		// Replace placeholders

		$tpl_builder = DevblocksPlatform::getTemplateBuilder();
		$dict = new DevblocksDictionaryDelegate($this->getPlaceholderValues());
		$query = $tpl_builder->build($query, $dict);
		
		// Get fields
		
		$fields = CerbQuickSearchLexer::getFieldsFromQuery($query);
		
		// Quick search multi-sorting
		// [TODO] Stacked sorts
		
		foreach($fields as $k => $p) {
			if($p instanceof DevblocksSearchCriteria && $p->key == 'sort') {
				$oper = null;
				$value = null;
				
				if(false == (CerbQuickSearchLexer::getOperStringFromTokens($p->tokens, $oper, $value)))
					continue;
				
				if(false == ($sort_results = $this->_getSortFromQuickSearchQuery($value)))
					continue;
				
				if(isset($sort_results['sort_by']) && !empty($sort_results['sort_by']))
					$this->renderSortBy = $sort_results['sort_by'][0];
				
				if(isset($sort_results['sort_asc']) && !empty($sort_results['sort_asc']))
					$this->renderSortAsc = $sort_results['sort_asc'][0];
					
				unset($fields[$k]);
			}
		}
		
		// Convert fields T_FIELD to DevblocksSearchCriteria
		
		array_walk_recursive($fields, function(&$v, $k) use (&$fields) {
			if($v instanceof DevblocksSearchCriteria) {
				$param = $this->getParamFromQuickSearchFieldTokens($v->key, $v->tokens);
				
				if($param instanceof DevblocksSearchCriteria) {
					$v = $param;
				} else {
					//$v = new DevblocksSearchCriteria('_unknown', DevblocksSearchCriteria::OPER_FALSE);
					unset($fields[$k]);
				}
			}
		});
		
		return $fields;
	}
	
	function addParamsWithQuickSearch($query, $replace=true) {
		$fields = $this->getParamsFromQuickSearch($query);
		$this->addParams($fields, $replace);
		$this->renderPage = 0;
	}
	
	function _getSortFromQuickSearchQuery($sort_query) {
		$sort_results = array(
			'sort_by' => array(),
			'sort_asc' => array(),
		);
		
		if(empty($sort_query) || !($this instanceof IAbstractView_QuickSearch))
			return false;
		
		if(false == ($search_fields = $this->getQuickSearchFields()))
			return false;
		
		// Tokenize the sort string with spaces
		$sort_fields = explode(' ', $sort_query);
		
		if(!is_array($sort_fields) || empty($sort_fields))
			return false;
			
		foreach($sort_fields as $sort_field) {
			$sort_asc = true;
			
			if('-' == substr($sort_field,0,1))
				$sort_asc = false;
			
			$sort_field = ltrim($sort_field, '+-');
			
			@$search_field = $search_fields[$sort_field];
			
			if(!is_array($search_field) || empty($search_field))
				continue;
			
			@$param_key = $search_field['options']['param_key'];
			
			if(empty($param_key))
				continue;
			
			$sort_results['sort_by'][] = $param_key;
			$sort_results['sort_asc'][] = $sort_asc;
		}
		
		return $sort_results;
	}
	
	function removeParam($key) {
		if(isset($this->_paramsEditable[$key]))
			unset($this->_paramsEditable[$key]);
	}
	
	function removeParamByField($field, &$params=null) {
		if(is_null($params))
			$params =& $this->_paramsEditable;
		
		foreach($params as $k => $p) {
		if($p instanceof DevblocksSearchCriteria)
			if($p->field == $field)
				unset($params[$k]);
		}
	}
	
	function removeAllParams() {
		$this->_paramsEditable = array();
	}
	
	function removeAllParamsRequired() {
		$this->_paramsRequired = array();
	}
	
	// Params Default
	
	function addParamsDefault($params, $replace=false) {
		if($replace)
			$this->_paramsDefault = $params;
		else
			$this->_paramsDefault = array_merge($this->_paramsDefault, $params);
	}
	
	function getParamsDefault() {
		return $this->_paramsDefault;
	}
	
	// Params Required
	
	function addParamRequired($param, $key=null) {
		if(!$key || is_numeric($key))
			$key = uniqid();
		
		$this->_paramsRequired[$key] = $param;
	}
	
	function addParamsRequired($params, $replace=false) {
		if($replace)
			$this->removeAllParamsRequired();
		
		if(is_array($params))
		foreach($params as $key => $param) {
			$key = is_numeric($key) ? null : $key;
			$this->addParamRequired($param, $key);
		}
	}
	
	function getParamsRequired() {
		return $this->_paramsRequired;
	}
	
	// Params Hidden
	
	function addParamsHidden($params, $replace=false) {
		if($replace)
			$this->_paramsHidden = $params;
		else
			$this->_paramsHidden = array_unique(array_merge($this->_paramsHidden, $params));
	}
	
	function getParamsHidden() {
		return $this->_paramsHidden;
	}
	
	// Search params
	
	static function findParam($field_key, $params, $recursive=true) {
		$results = array();
		
		if($recursive) {
			array_walk_recursive($params, function(&$v, $k) use (&$results, $field_key) {
				if(!($v instanceof DevblocksSearchCriteria))
					return;
	
				if($v->field == $field_key) {
					$results[$k] = $v;
				}
			});
			
		} else {
			array_walk($params, function(&$v, $k) use (&$results, $field_key) {
				if(!($v instanceof DevblocksSearchCriteria))
					return;
	
				if($v->field == $field_key) {
					$results[$k] = $v;
				}
			});
		}
		
		return $results;
	}
	
	static function hasParam($field_key, $params, $recursive=true) {
		return count(self::findParam($field_key, $params, $recursive)) > 0;
	}
	
	// Placeholders
	
	function setPlaceholderLabels($labels) {
		if(is_array($labels))
			$this->_placeholderLabels = $labels;
	}
	
	function getPlaceholderLabels() {
		return $this->_placeholderLabels;
	}
	
	function setPlaceholderValues($values) {
		if(is_array($values))
			$this->_placeholderValues = $values;
	}
	
	function getPlaceholderValues() {
		return $this->_placeholderValues;
	}
	
	protected static function _translatePlaceholders(&$param, $key, $args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;

		$param_key = $param->field;
		settype($param_key, 'string');

		$tpl_builder = DevblocksPlatform::getTemplateBuilder();

		if(is_string($param->value)) {
			if(false !== ($value = $tpl_builder->build($param->value, $args['placeholder_values']))) {
				$param->value = $value;
			}
			
		} elseif(is_array($param->value)) {
			foreach($param->value as $k => $v) {
				if(is_string($v)) {
					if(false !== ($value = $tpl_builder->build($v, $args['placeholder_values']))) {
						$param->value[$k] = $value;
					}
					
				} elseif(is_array($v)) {
					foreach($v as $idx => $nested_v) {
						if(!is_string($nested_v))
							continue;
						
						if(false !== ($value = $tpl_builder->build($nested_v, $args['placeholder_values']))) {
							$param->value[$k][$idx] = $value;
						}
					}
				}
			}
		}
	}
	
	// Marquee
	
	static function setMarqueeContextCreated($view_id, $context, $context_id) {
		$string = null;
		
		if(null != ($ctx = Extension_DevblocksContext::get($context))) {
			if(null != ($meta = $ctx->getMeta($context_id))) {
				if(!isset($meta['name']) || !isset($meta['permalink']))
					return;
				
				// Use abstract popups if we can
				if($ctx instanceof IDevblocksContextPeek) {
					$string = sprintf("New %s created: <a href='javascript:;' class='cerb-peek-trigger' data-context='%s' data-context-id='%d'><b>%s</b></a>",
						DevblocksPlatform::strEscapeHtml(strtolower($ctx->manifest->name)),
						DevblocksPlatform::strEscapeHtml($context),
						DevblocksPlatform::strEscapeHtml($context_id),
						DevblocksPlatform::strEscapeHtml($meta['name'])
					);
					
				// Otherwise, try linking to profile pages
				} elseif(!empty($meta['permalink'])) {
					$string = sprintf("New %s created: <a href='%s'><b>%s</b></a>",
						DevblocksPlatform::strEscapeHtml(strtolower($ctx->manifest->name)),
						DevblocksPlatform::strEscapeHtml($meta['permalink']),
						DevblocksPlatform::strEscapeHtml($meta['name'])
					);
					
				// Lastly, just output some text
				} else {
					$string = sprintf("New %s created: <b>%s</b>",
						DevblocksPlatform::strEscapeHtml(strtolower($ctx->manifest->name)),
						DevblocksPlatform::strEscapeHtml($meta['name'])
					);
				}
			}
			
		}
		
		if(empty($string))
			self::unsetMarquee($view_id);
		else
			self::setMarquee($view_id, $string);
	}
	
	static function setMarqueeContextImported($view_id, $context, $count) {
		$string = null;
		
		if(null != ($ctx = Extension_DevblocksContext::get($context))) {
			$string = sprintf("Imported <b>%d %s</b> record%s.",
				$count,
				strtolower($ctx->manifest->name),
				($count == 1 ? '' : 's')
			);
		}
		
		if(empty($string))
			self::unsetMarquee($view_id);
		else
			self::setMarquee($view_id, $string);
	}
	
	static function setMarquee($view_id, $string) {
		$visit = CerberusApplication::getVisit();
		$visit->set($view_id . '_marquee', $string);
	}
	
	static function unsetMarquee($view_id) {
		$visit = CerberusApplication::getVisit();
		$visit->remove($view_id . '_marquee');
	}
	
	static function getMarquee($view_id, $pop=true) {
		$visit = CerberusApplication::getVisit();
		
		$string = $visit->get($view_id . '_marquee');
		
		if($pop)
			self::unsetMarquee($view_id);
		
		return $string;
	}
	
	protected function _checkFulltextMarquee() {
		// Add search meta output to the view marquee
		$meta = DevblocksPlatform::getRegistryKey('fulltext_meta', DevblocksRegistryEntry::TYPE_JSON, '[]');
		
		if(!empty($meta)) {
			$marquees = array();
			
			if(is_array($meta))
			foreach($meta as $results) {
				if(is_array($results)
					&& isset($results['results'])
					&& isset($results['took_ms'])
					&& isset($results['engine'])
					) {
					
					$marquees[] = sprintf("Found %s %s hit%s for <b>%s</b> [%s: %d ms%s, max %d]",
						number_format($results['results']),
						str_replace('_',' ', $results['ns']),
						($results['results']==1) ? '' : 's',
						DevblocksPlatform::strEscapeHtml($results['query']),
						$results['engine'],
						$results['took_ms'],
						(isset($results['is_cached']) && $results['is_cached']) ? ', cached' : '',
						$results['max']
					);
				}
			}
			
			if(!empty($marquees)) {
				C4_AbstractView::setMarquee($this->id, implode('<br>', $marquees));
			}
			
			DevblocksPlatform::setRegistryKey('fulltext_meta', array(), DevblocksRegistryEntry::TYPE_JSON, false);
		}
	}
	
	// Render
	
	function render() {
		echo ' '; // Expect Override
	}
	
	function renderCustomizeOptions() {
		echo ' '; // Expect Override
	}
	
	function renderCriteria($field) {
		echo ' '; // Expect Override
	}

	protected function _renderCriteriaCustomField($tpl, $field_id) {
		$field = DAO_CustomField::get($field_id);
		
		switch($field->type) {
			case Model_CustomField::TYPE_DROPDOWN:
			case Model_CustomField::TYPE_MULTI_CHECKBOX:
				$tpl->assign('field', $field);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__cfield_picklist.tpl');
				break;
			case Model_CustomField::TYPE_CHECKBOX:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__cfield_checkbox.tpl');
				break;
			case Model_CustomField::TYPE_DATE:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
			case Model_CustomField::TYPE_LINK:
				$tpl->assign('field', $field);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__cfield_link.tpl');
				break;
			case Model_CustomField::TYPE_NUMBER:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
			case Model_CustomField::TYPE_WORKER:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_worker.tpl');
				break;
			default:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
		}
	}
	
	protected function _renderCriteriaParamString($param, $label_map) {
		$translate = DevblocksPlatform::getTranslationService();
		
		$strings = array();
		
		$values = is_array($param->value) ? $param->value : array($param->value);
		
		if(is_callable($label_map))
			$label_map = $label_map($values);
		
		foreach($values as $v) {
			$strings[] = sprintf("<b>%s</b>",
				DevblocksPlatform::strEscapeHtml((isset($label_map[$v]) ? $label_map[$v] : $v))
			);
		}
		
		$list_of_strings = implode(' or ', $strings);
		
		if(count($strings) > 2) {
			$list_of_strings = sprintf("any of <abbr style='font-weight:bold;' title='%s'>(%d values)</abbr>",
				strip_tags($list_of_strings),
				count($strings)
			);
		}
		
		echo $list_of_strings;
	}
	
	protected function _renderCriteriaParamBoolean($param) {
		$translate = DevblocksPlatform::getTranslationService();
		
		$strings = array();
		
		$values = is_array($param->value) ? $param->value : array($param->value);
		
		foreach($values as $v) {
			$strings[] = sprintf("<b>%s</b>",
				DevblocksPlatform::strEscapeHtml((!empty($v) ? $translate->_('common.yes') : $translate->_('common.no')))
			);
		}
		
		echo implode(' or ', $strings);
	}
	
	protected function _renderCriteriaParamWorker($param) {
		$workers = DAO_Worker::getAll();
		$strings = array();
		
		$values = $param->value;
		
		if(!is_array($values))
			$values = array($values);
		
		foreach($values as $worker_id) {
			if(!is_numeric($worker_id)) {
				$strings[] = sprintf('<b>%s</b>', $worker_id);
			} elseif(isset($workers[$worker_id])) {
				$strings[] = sprintf('<b>%s</b>',DevblocksPlatform::strEscapeHtml($workers[$worker_id]->getName()));
			} elseif (!empty($worker_id)) {
				$strings[] = sprintf('<b>%d</b>',$worker_id);
			} elseif (0 == strlen($worker_id)) {
				$strings[] = '<b>nobody</b>';
			} elseif (empty($worker_id)) {
				$strings[] = '<b>nobody</b>';
			}
		}
		
		$list_of_strings = implode(' or ', $strings);
		
		if(count($strings) > 2) {
			$list_of_strings = sprintf("any of <abbr style='font-weight:bold;' title='%s'>(%d people)</abbr>",
				strip_tags($list_of_strings),
				count($strings)
			);
		}
		
		echo $list_of_strings;
	}
	
	protected function _renderCriteriaHasFieldset($tpl, $context) {
		$options = array();
		
		$fieldsets = DAO_CustomFieldset::getByContext($context);
		
		foreach($fieldsets as $id => $fieldset) {
			$options[$id] = $fieldset->name;
		}
		
		$tpl->assign('options', $options);
		$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__list.tpl');
	}
	
	protected function _renderVirtualContextLinks($param, $label_singular='Link', $label_plural='Links', $label_verb='Linked to') {
		$strings = array();
		
		if($param->operator == DevblocksSearchCriteria::OPER_CUSTOM) {
			@list($alias, $query) = explode(':', $param->value, 2);
			
			if(empty($alias) || (false == ($mft = Extension_DevblocksContext::getByAlias($alias, false))))
				return;
			
			$aliases = Extension_DevblocksContext::getAliasesForContext($mft);
			$alias = !empty($aliases['plural_short']) ? $aliases['plural_short'] : $aliases['plural'];
			
			echo sprintf("%s %s <b>%s</b>", DevblocksPlatform::strEscapeHtml($label_verb), DevblocksPlatform::strEscapeHtml($alias), DevblocksPlatform::strEscapeHtml($query));
			return;
		}
		
		if(is_array($param->value))
		foreach($param->value as $context_data) {
			@list($context, $context_id) = explode(':',$context_data);
			
			if(empty($context))
				continue;
			
			$context_ext = Extension_DevblocksContext::get($context,true);
			
			if(!empty($context_id)) {
				$meta = $context_ext->getMeta($context_id);
				$strings[] = sprintf("<b>%s</b> (%s)", DevblocksPlatform::strEscapeHtml($meta['name']),DevblocksPlatform::strEscapeHtml($context_ext->manifest->name));
			} else {
				$strings[] = sprintf("(<b>%s</b>)", DevblocksPlatform::strEscapeHtml($context_ext->manifest->name));
			}
		}
		
		if(empty($param->value)) {
			switch($param->operator) {
				case DevblocksSearchCriteria::OPER_EQ:
				case DevblocksSearchCriteria::OPER_IN:
				case DevblocksSearchCriteria::OPER_IN_OR_NULL:
				case DevblocksSearchCriteria::OPER_NIN_OR_NULL:
					$param->operator = DevblocksSearchCriteria::OPER_IS_NULL;
					break;
				case DevblocksSearchCriteria::OPER_NEQ:
				case DevblocksSearchCriteria::OPER_NIN:
					$param->operator = DevblocksSearchCriteria::OPER_IS_NOT_NULL;
					break;
			}
		}
		
		$list_of_strings = implode(' or ', $strings);
		
		if(count($strings) > 2) {
			$list_of_strings = sprintf("any of <abbr style='font-weight:bold;' title='%s'>(%d %s)</abbr>",
				strip_tags($list_of_strings),
				count($strings),
				strtolower($label_plural)
			);
		}
		
		switch($param->operator) {
			case DevblocksSearchCriteria::OPER_IS_NULL:
				echo sprintf("There are no <b>%s</b>",
					DevblocksPlatform::strEscapeHtml(strtolower($label_plural))
				);
				break;
			case DevblocksSearchCriteria::OPER_IS_NOT_NULL:
				echo sprintf("There are <b>%s</b>",
					DevblocksPlatform::strEscapeHtml(strtolower($label_plural))
				);
				break;
			case DevblocksSearchCriteria::OPER_IN:
				echo sprintf("%s is %s", DevblocksPlatform::strEscapeHtml($label_singular), $list_of_strings);
				break;
			case DevblocksSearchCriteria::OPER_IN_OR_NULL:
				echo sprintf("%s is blank or %s", DevblocksPlatform::strEscapeHtml($label_singular), $list_of_strings);
				break;
			case DevblocksSearchCriteria::OPER_NIN:
				echo sprintf("%s is not %s", DevblocksPlatform::strEscapeHtml($label_singular), $list_of_strings);
				break;
			case DevblocksSearchCriteria::OPER_NIN_OR_NULL:
				echo sprintf("%s is blank or not %s", DevblocksPlatform::strEscapeHtml($label_singular), $list_of_strings);
				break;
		}
	}
	
	protected function _renderVirtualHasFieldset($param) {
		$strings = array();
		$custom_fieldsets = DAO_CustomFieldset::getAll();
		
		foreach($param->value as $param_data) {
			if(isset($custom_fieldsets[$param_data]))
				$strings[] = sprintf('<b>%s</b>', DevblocksPlatform::strEscapeHtml($custom_fieldsets[$param_data]->name));
		}
		
		$label_singular = 'Fieldset';
		$label_plural = 'Fieldsets';

		$list_of_strings = implode(' or ', $strings);
		
		if(count($strings) > 2) {
			$list_of_strings = sprintf("any of <abbr style='font-weight:bold;' title='%s'>(%d %s)</abbr>",
				strip_tags($list_of_strings),
				count($strings),
				strtolower($label_plural)
			);
		}
		
		switch($param->operator) {
			case DevblocksSearchCriteria::OPER_IS_NULL:
				echo sprintf("There are no <b>%s</b>",
					DevblocksPlatform::strEscapeHtml(strtolower($label_plural))
				);
				break;
			case DevblocksSearchCriteria::OPER_IS_NOT_NULL:
				echo sprintf("There are <b>%s</b>",
					DevblocksPlatform::strEscapeHtml(strtolower($label_plural))
				);
				break;
			case DevblocksSearchCriteria::OPER_IN:
				echo sprintf("%s is %s", DevblocksPlatform::strEscapeHtml($label_singular), $list_of_strings);
				break;
			case DevblocksSearchCriteria::OPER_IN_OR_NULL:
				echo sprintf("%s is blank or %s", DevblocksPlatform::strEscapeHtml($label_singular), $list_of_strings);
				break;
			case DevblocksSearchCriteria::OPER_NIN:
				echo sprintf("%s is not %s", DevblocksPlatform::strEscapeHtml($label_singular), $list_of_strings);
				break;
			case DevblocksSearchCriteria::OPER_NIN_OR_NULL:
				echo sprintf("%s is blank or not %s", DevblocksPlatform::strEscapeHtml($label_singular), $list_of_strings);
				break;
		}
	}
	
	protected function _renderVirtualWatchers($param) {
		return $this->_renderVirtualWorkers($param, 'Watcher', 'Watchers');
	}
	
	protected function _renderVirtualWorkers($param, $label_singular='Worker', $label_plural='Workers') {
		$workers = DAO_Worker::getAll();
		$strings = array();
		
		if(is_array($param->value))
		foreach($param->value as $worker_id) {
			if(isset($workers[$worker_id]))
				$strings[] = sprintf("<b>%s</b>",DevblocksPlatform::strEscapeHtml($workers[$worker_id]->getName()));
			elseif(empty($worker_id)) {
				$strings[] = '<b>nobody</b>';
			} else {
				$strings[] = sprintf("<b>%d</b>",$worker_id);
			}
		}
		
		if(empty($param->value)) {
			switch($param->operator) {
				case DevblocksSearchCriteria::OPER_EQ:
				case DevblocksSearchCriteria::OPER_IN:
				case DevblocksSearchCriteria::OPER_IN_OR_NULL:
				case DevblocksSearchCriteria::OPER_NIN_OR_NULL:
					$param->operator = DevblocksSearchCriteria::OPER_IS_NULL;
					break;
				case DevblocksSearchCriteria::OPER_NEQ:
				case DevblocksSearchCriteria::OPER_NIN:
					$param->operator = DevblocksSearchCriteria::OPER_IS_NOT_NULL;
					break;
			}
		}
		
		$list_of_strings = implode(' or ', $strings);
		
		if(count($strings) > 2) {
			$list_of_strings = sprintf("any of <abbr style='font-weight:bold;' title='%s'>(%d people)</abbr>",
				strip_tags($list_of_strings),
				count($strings)
			);
		}
		
		switch($param->operator) {
			case DevblocksSearchCriteria::OPER_IS_NULL:
				echo sprintf("There are no <b>%s</b>",
					DevblocksPlatform::strEscapeHtml($label_plural)
				);
				break;
			case DevblocksSearchCriteria::OPER_IS_NOT_NULL:
				echo sprintf("There are <b>%s</b>",
					DevblocksPlatform::strEscapeHtml($label_plural)
				);
				break;
			case DevblocksSearchCriteria::OPER_IN:
				echo sprintf("%s is %s", DevblocksPlatform::strEscapeHtml($label_singular), $list_of_strings);
				break;
			case DevblocksSearchCriteria::OPER_IN_OR_NULL:
				echo sprintf("%s is blank or %s", DevblocksPlatform::strEscapeHtml($label_singular), $list_of_strings);
				break;
			case DevblocksSearchCriteria::OPER_NIN:
				echo sprintf("%s is not %s", DevblocksPlatform::strEscapeHtml($label_singular), $list_of_strings);
				break;
			case DevblocksSearchCriteria::OPER_NIN_OR_NULL:
				echo sprintf("%s is blank or not %s", DevblocksPlatform::strEscapeHtml($label_singular), $list_of_strings);
				break;
		}
	}
	
	/**
	 * Enter description here...
	 *
	 * @param string $field
	 * @param string $oper
	 * @param string $value
	 * @abstract
	 */
	function doSetCriteria($field, $oper, $value) {
		// Expect Override
	}

	protected function _doSetCriteriaString($field, $oper, $value) {
		// force wildcards if none used on a LIKE
		if(($oper == DevblocksSearchCriteria::OPER_LIKE || $oper == DevblocksSearchCriteria::OPER_NOT_LIKE)
		&& false === (strpos($value,'*'))) {
			$value = '*'.$value.'*';
		}
		return new DevblocksSearchCriteria($field, $oper, $value);
	}
	
	protected function _doSetCriteriaDate($field, $oper) {
		switch($oper) {
			default:
			case DevblocksSearchCriteria::OPER_BETWEEN:
			case DevblocksSearchCriteria::OPER_NOT_BETWEEN:
				@$from = DevblocksPlatform::importGPC($_REQUEST['from'],'string','big bang');
				@$to = DevblocksPlatform::importGPC($_REQUEST['to'],'string','now');
		
				if(is_null($from) || (!is_numeric($from) && @false === strtotime(str_replace('.','-',$from))))
					$from = 'big bang';
					
				if(is_null($to) || (!is_numeric($to) && @false === strtotime(str_replace('.','-',$to))))
					$to = 'now';
				
				return new DevblocksSearchCriteria($field,$oper,array($from,$to));
				break;
				
			case DevblocksSearchCriteria::OPER_EQ_OR_NULL:
				return new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_EQ_OR_NULL,0);
				break;
		}
		
	}
	
	protected function _doSetCriteriaWorker($field, $oper) {
		@$worker_ids = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'array',array());
		
		switch($oper) {
			case DevblocksSearchCriteria::OPER_IN:
				if(empty($worker_ids)) {
					$worker_ids[] = '0';
				}
				break;
			case DevblocksSearchCriteria::OPER_IN_OR_NULL:
				$oper = DevblocksSearchCriteria::OPER_IN;
				if(!in_array('0', $worker_ids))
					$worker_ids[] = '0';
				break;
			case DevblocksSearchCriteria::OPER_NIN:
				if(empty($worker_ids)) {
					$worker_ids[] = '0';
				}
				break;
			case 'not in and not null':
				$oper = DevblocksSearchCriteria::OPER_NIN;
				if(!in_array('0', $worker_ids))
					$worker_ids[] = '0';
				break;
			case DevblocksSearchCriteria::OPER_EQ:
			case DevblocksSearchCriteria::OPER_NEQ:
				@$worker_ids = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'integer',0);
				break;
		}
		
		return new DevblocksSearchCriteria($field, $oper, $worker_ids);
	}
	
	protected function _doSetCriteriaCustomField($token, $field_id) {
		$field = DAO_CustomField::get($field_id);
		@$oper = DevblocksPlatform::importGPC($_POST['oper'],'string','');
		@$value = DevblocksPlatform::importGPC($_POST['value'],'string','');
		
		$criteria = null;
		
		switch($field->type) {
			case Model_CustomField::TYPE_DROPDOWN:
			case Model_CustomField::TYPE_MULTI_CHECKBOX:
				@$options = DevblocksPlatform::importGPC($_POST['options'],'array',array());
				if(!empty($options)) {
					$criteria = new DevblocksSearchCriteria($token,$oper,$options);
				} else {
					$criteria = new DevblocksSearchCriteria($token,DevblocksSearchCriteria::OPER_IS_NULL);
				}
				break;
				
			case Model_CustomField::TYPE_CHECKBOX:
				$criteria = new DevblocksSearchCriteria($token,$oper,!empty($value) ? 1 : 0);
				break;
				
			case Model_CustomField::TYPE_NUMBER:
				$criteria = new DevblocksSearchCriteria($token,$oper,intval($value));
				break;
				
			case Model_CustomField::TYPE_DATE:
				@$from = DevblocksPlatform::importGPC($_REQUEST['from'],'string','');
				@$to = DevblocksPlatform::importGPC($_REQUEST['to'],'string','');
	
				if(empty($from)) $from = 0;
				if(empty($to)) $to = 'today';
	
				switch($oper) {
					case DevblocksSearchCriteria::OPER_EQ_OR_NULL:
						$criteria = new DevblocksSearchCriteria($token,$oper,0);
						break;
						
					default:
						$criteria = new DevblocksSearchCriteria($token,$oper,array($from,$to));
						break;
				}
				
				break;
				
			case Model_CustomField::TYPE_WORKER:
				@$oper = DevblocksPlatform::importGPC($_REQUEST['oper'],'string','eq');
				@$worker_ids = DevblocksPlatform::importGPC($_POST['worker_id'],'array',array());
				
				if(empty($worker_ids)) {
					switch($oper) {
						case DevblocksSearchCriteria::OPER_IN:
							$oper = DevblocksSearchCriteria::OPER_IS_NULL;
							$worker_ids = null;
							break;
						case DevblocksSearchCriteria::OPER_NIN:
							$oper = DevblocksSearchCriteria::OPER_IS_NOT_NULL;
							$worker_ids = null;
							break;
					}
				}
				
				$criteria = new DevblocksSearchCriteria($token,$oper,$worker_ids);
				break;
				
			case Model_CustomField::TYPE_LINK:
				@$context_id = DevblocksPlatform::importGPC($_POST['context_id'],'integer','');
				$criteria = new DevblocksSearchCriteria($token,$oper,$context_id);
				break;
				
			default: // TYPE_SINGLE_LINE || TYPE_MULTI_LINE
				if(($oper == DevblocksSearchCriteria::OPER_LIKE || $oper == DevblocksSearchCriteria::OPER_NOT_LIKE)
				&& false === (strpos($value,'*'))) {
					$value = $value.'*';
				}
				$criteria = new DevblocksSearchCriteria($token,$oper,$value);
				break;
		}
		
		return $criteria;
	}
	
	protected function _appendVirtualFiltersFromQuickSearchContexts($prefix, $fields=array(), $option='search') {
		$context_mfts = Extension_DevblocksContext::getAll(false, [$option]);
		
		$fields[$prefix] = array(
			'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
			'options' => array(),
		);
		
		foreach($context_mfts as $context_mft) {
			$aliases = Extension_DevblocksContext::getAliasesForContext($context_mft);
			
			$alias = $aliases['uri'];
			
			if(empty($alias))
				continue;
			
			$field = array(
				'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
				'options' => array(),
				'examples' => [
					['type' => 'search', 'context' => $context_mft->id, 'q' => ''],
				]
			);
			
			if($context_mft->id == CerberusContexts::CONTEXT_APPLICATION)
				$field['examples'] = [
					['type' => 'list', 'values' => ['Cerb' => 'Cerb']],
				];
			
			$fields[$prefix.'.'.str_replace(' ', '.', $alias)] = $field;
		}
		
		return $fields;
	}
	
	protected function _appendFieldsFromQuickSearchContext($context, $fields=array(), $prefix=null) {
		$custom_fields = DAO_CustomField::getByContext($context, true, false);
		$custom_fieldsets = DAO_CustomFieldset::getAll();
		
		foreach($custom_fields as $cf_id => $cfield) {
			$search_field_meta = array(
				'type' => null,
				'is_sortable' => true,
				'options' => array(
					'param_key' => sprintf("cf_%d", $cf_id),
					'cf_ctx' => $cfield->context,
					'cf_id' => $cf_id,
				),
			);
			
			switch($cfield->type) {
				case Model_CustomField::TYPE_CHECKBOX:
					$search_field_meta['type'] = DevblocksSearchCriteria::TYPE_BOOL;
					break;
					
				case Model_CustomField::TYPE_DATE:
					$search_field_meta['type'] = DevblocksSearchCriteria::TYPE_DATE;
					break;
					
				case Model_CustomField::TYPE_DROPDOWN:
					$search_field_meta['type'] = DevblocksSearchCriteria::TYPE_TEXT;
					$search_field_meta['options']['match'] = DevblocksSearchCriteria::OPTION_TEXT_PARTIAL;
					if(isset($cfield->params['options']))
						$search_field_meta['examples'] = array_slice(
								array_map(function($e) { 
									return sprintf('("%s")', str_replace(array('(',')'),array('\(','\)'),$e));
								},
								$cfield->params['options']
							),
							0,
							20
						);
					break;
					
				case Model_CustomField::TYPE_MULTI_CHECKBOX:
					$search_field_meta['type'] = DevblocksSearchCriteria::TYPE_TEXT;
					$search_field_meta['options']['match'] = DevblocksSearchCriteria::OPTION_TEXT_PARTIAL;
					if(isset($cfield->params['options']))
						$search_field_meta['examples'] = array_slice(
								array_map(function($e) { 
									return sprintf('("%s")', str_replace(array('(',')'),array('\(','\)'),$e));
								},
								$cfield->params['options']
							),
							0,
							20
						);
					break;
					
				case Model_CustomField::TYPE_MULTI_LINE:
					$search_field_meta['type'] = DevblocksSearchCriteria::TYPE_TEXT;
					$search_field_meta['options']['match'] = DevblocksSearchCriteria::OPTION_TEXT_PARTIAL;
					break;
					
				case Model_CustomField::TYPE_NUMBER:
					$search_field_meta['type'] = DevblocksSearchCriteria::TYPE_NUMBER;
					break;
					
				case Model_CustomField::TYPE_SINGLE_LINE:
					$search_field_meta['type'] = DevblocksSearchCriteria::TYPE_TEXT;
					$search_field_meta['options']['match'] = DevblocksSearchCriteria::OPTION_TEXT_PARTIAL;
					break;
					
				case Model_CustomField::TYPE_URL:
					$search_field_meta['type'] = DevblocksSearchCriteria::TYPE_TEXT;
					$search_field_meta['options']['match'] = DevblocksSearchCriteria::OPTION_TEXT_PARTIAL;
					break;
					
				case Model_CustomField::TYPE_WORKER:
					$search_field_meta['type'] = DevblocksSearchCriteria::TYPE_WORKER;
					break;
			}
			
			// Skip custom field types we can't quick search easily
			if(empty($search_field_meta['type']))
				continue;
			
			@$custom_fieldset = $custom_fieldsets[$cfield->custom_fieldset_id];
			
			// Prefix the custom fieldset namespace
			$field_key = sprintf("%s%s%s",
				!empty($prefix) ? ($prefix . '.') : '',
				$custom_fieldset ? (DevblocksPlatform::strAlphaNum(lcfirst(mb_convert_case($custom_fieldset->name, MB_CASE_TITLE))) . '.') : '',
				DevblocksPlatform::strAlphaNum(lcfirst(mb_convert_case($cfield->name, MB_CASE_TITLE)))
			);
			
			// Make sure the field key is unique by appending the cf_id when it already exists
			if(isset($fields[$field_key])) {
				$field_key .= $cf_id;
			}
			
			$fields[$field_key] = $search_field_meta;
		}
		
		return $fields;
	}
	
	protected function _setSortableQuickSearchFields($fields, $search_fields) {
		foreach($fields as $k => &$field) {
			@$param_key = $field['options']['param_key'];
			$field['is_sortable'] = ($param_key && isset($search_fields[$param_key]) && $search_fields[$param_key]->is_sortable);
		}
		
		return $fields;
	}

	/**
	 * This method automatically fixes any cached strange options, like
	 * deleted custom fields.
	 *
	 */
	protected function _sanitize() {
		$fields = $this->getColumnsAvailable();
		$custom_fields = DAO_CustomField::getAll();
		
		$params = $this->getParams(false);
		
		// Parameter sanity check
		if(is_array($params))
		foreach($params as $pidx => $null) {
			if(substr($pidx,0,3)!="cf_")
				continue;
				
			if(0 != ($cf_id = intval(substr($pidx,3)))) {
				// Make sure our custom fields still exist
				if(!isset($custom_fields[$cf_id])) {
					$this->removeParam($pidx);
				}
			}
		}
		unset($params);
		
		// View column sanity check
		if(is_array($this->view_columns))
		foreach($this->view_columns as $cidx => $c) {
			// Custom fields
			if(substr($c,0,3) == "cf_") {
				if(0 != ($cf_id = intval(substr($c,3)))) {
					// Make sure our custom fields still exist
					if(!isset($custom_fields[$cf_id])) {
						unset($this->view_columns[$cidx]);
					}
				}
			} else {
				// If the column no longer exists (rare but worth checking)
				if(!isset($fields[$c])) {
					unset($this->view_columns[$cidx]);
				}
			}
		}
		
		// Sort by sanity check
		if(substr($this->renderSortBy,0,3)=="cf_") {
			if(0 != ($cf_id = intval(substr($this->renderSortBy,3)))) {
				if(!isset($custom_fields[$cf_id])) {
					$this->renderSortBy = null;
				}
			}
		}
	}
	
	function renderCriteriaParam($param) {
		$field = $param->field;
		$vals = $param->value;

		if(!is_array($vals))
			$vals = array($vals);
		
		$fields = $this->getFields();
		
		if(isset($fields[$field]) && $fields[$field]->type == Model_CustomField::TYPE_DATE) {
			$implode_token = ' to ';
			
		} else if(in_array($param->operator, array(DevblocksSearchCriteria::OPER_BETWEEN, DevblocksSearchCriteria::OPER_NOT_BETWEEN))) {
			$implode_token = ' and ';
			
		} else {
			$implode_token = ' or ';
			
		}

		if($param->operator == DevblocksSearchCriteria::OPER_FULLTEXT)
			unset($vals[1]);
		
		// Do we need to do anything special on custom fields?
		if('cf_'==substr($field,0,3)) {
			$field_id = intval(substr($field,3));
			$custom_fields = DAO_CustomField::getAll();
			
			$translate = DevblocksPlatform::getTranslationService();
			
			if(!isset($custom_fields[$field_id]))
				return;
			
			switch($custom_fields[$field_id]->type) {
				case Model_CustomField::TYPE_CHECKBOX:
					foreach($vals as $idx => $val) {
						$vals[$idx] = !empty($val) ? $translate->_('common.yes') : $translate->_('common.no');
					}
					break;
					
				case Model_CustomField::TYPE_DATE:
					$implode_token = ' to ';
					break;
					
				case Model_CustomField::TYPE_LINK:
					@$context = $custom_fields[$field_id]->params['context'];
					
					if(empty($context) || empty($vals))
						break;
					
					if(false == ($context_ext = Extension_DevblocksContext::get($context)))
						break;
					
					if(false == ($meta = $context_ext->getMeta($vals[0])))
						break;
					
					$vals[0] = $meta['name'];
					break;
					
				case Model_CustomField::TYPE_WORKER:
					$this->_renderCriteriaParamWorker($param);
					return;
					break;
			}
		}
		
		// HTML escape
		if(is_array($vals))
		foreach($vals as $k => $v) {
			$vals[$k] = DevblocksPlatform::strEscapeHtml($v);
		}
		
		echo implode($implode_token, $vals);
	}

	/**
	 * All the view's available fields
	 *
	 * @return array
	 */
	function getFields() {
		// Expect Override
		return array();
	}

	function doCustomize($columns, $num_rows=10, $options=array()) {
		$this->renderLimit = $num_rows;

		$viewColumns = array();
		foreach($columns as $col) {
			if(empty($col))
				continue;
			$viewColumns[] = $col;
		}

		$this->view_columns = $viewColumns;
		
		$this->options = $options;
	}

	function doSortBy($sortBy) {
		$iSortAsc = intval($this->renderSortAsc);

		// [JAS]: If clicking the same header, toggle asc/desc.
		if(0 == strcasecmp($sortBy,$this->renderSortBy)) {
			$iSortAsc = (0 == $iSortAsc) ? 1 : 0;
		} else { // [JAS]: If a new header, start with asc.
			$iSortAsc = 1;
		}

		$this->renderSortBy = $sortBy;
		$this->renderSortAsc = $iSortAsc;
	}

	function doPage($page) {
		$this->renderPage = $page;
	}

	function doRemoveCriteria($key) {
		$this->removeParam($key);
		$this->renderPage = 0;
	}

	function doResetCriteria() {
		$this->addParams($this->_paramsDefault, true);
		$this->renderPage = 0;
	}
	
	function getPresets() {
		if(null == ($active_worker = CerberusApplication::getActiveWorker()))
			return;
		
		// Presets
		// [TODO] Cache?
		return DAO_ViewFiltersPreset::getWhere(
			sprintf("%s = %s AND %s = %d",
				DAO_ViewFiltersPreset::VIEW_CLASS,
				Cerb_ORMHelper::qstr(get_class($this)),
				DAO_ViewFiltersPreset::WORKER_ID,
				$active_worker->id
			)
		);
	}
	
	// [TODO] Cache this?
	function getQuickSearchMenu() {
		if(!$this instanceof IAbstractView_QuickSearch)
			return;
		
		$menu = array();
		
		// Operators
		
		$oper_menu = new DevblocksMenuItemPlaceholder();
		
		$item = new DevblocksMenuItemPlaceholder();
		$item->label = 'AND';
		$item->l = 'AND';
		$item->key = 'AND';
		$oper_menu->children['AND'] = $item;
		
		$item = new DevblocksMenuItemPlaceholder();
		$item->label = 'OR';
		$item->l = 'OR';
		$item->key = 'OR';
		$oper_menu->children['OR'] = $item;
		
		$menu['(operators)'] = $oper_menu;
		
		// Fields
		
		$fields_menu = new DevblocksMenuItemPlaceholder();
		$search_fields = $this->getQuickSearchFields();
		
		if(!empty($search_fields)) {
			$labels = array_keys($search_fields);
			$keys = array_map(function($field) {
				return $field.':';
			}, $labels);
			
			$tree = Extension_DevblocksContext::getPlaceholderTree(array_combine($keys, $labels), '.', '.');
			
			$recurseAddOptions = function(DevblocksMenuItemPlaceholder &$node) use (&$recurseAddOptions, $search_fields) {
				$key = substr($node->key, 0, -1);
				
				foreach($node->children as $child)
					$recurseAddOptions($child);
				
				if(!isset($search_fields[$key]))
					return;
				
				if(!isset($search_fields[$key]['examples'])) {
					switch($search_fields[$key]['type']) {
						case DevblocksSearchCriteria::TYPE_BOOL:
							$search_fields[$key]['examples'] = [
								'yes',
								'no',
							];
							break;
							
						case DevblocksSearchCriteria::TYPE_DATE:
							$search_fields[$key]['examples'] = [
								'"-2 hours"',
								sprintf('"%s-01-01 to %s"', date('Y'), date('Y-m-d')),
								'"-1 month to now"',
								'"big bang to -1 year"',
							];
							break;
							
						case DevblocksSearchCriteria::TYPE_NUMBER:
							$search_fields[$key]['examples'] = [
								'50',
								'<10',
								'>=25',
								'1...100',
								'!10',
							];
							break;
					}
				}
				
				if(isset($search_fields[$key]['examples'])) {
					$examples_menu = new DevblocksMenuItemPlaceholder();
					
					foreach($search_fields[$key]['examples'] as $example) {
						
						// Literal example
						if(is_string($example)) {
							$item = new DevblocksMenuItemPlaceholder();
							$item->label = $example;
							$item->l = $example;
							$item->key = $node->key . $example;
							$examples_menu->children[$example] = $item;
							
						// Structured example
						} else if(is_array($example)) {
							switch($example['type']) {
								case 'chooser':
									$item = new DevblocksMenuItemPlaceholder();
									$item->label = '(chooser)';
									$item->l = '(chooser)';
									$item->key = $node->key;
									$item->type = $example['type'];
									$item->params = $example;
									$node->children[$example['label']] = $item;
									break;
								
								case 'list':
									$key_delimiter = @$example['key_delimiter'] ?: ' ';
									$label_delimiter = @$example['label_delimiter'] ?: ' ';
									
									$values = array_combine(
										array_map(function($k) use ($node) {
											return $node->key . $k;
										}, array_keys($example['values'])),
										$example['values']
									);
									
									$node->children = Extension_DevblocksContext::getPlaceholderTree($values, $label_delimiter, $key_delimiter);
									break;
									
								case 'search':
									$item = new DevblocksMenuItemPlaceholder();
									$item->label = '(search)';
									$item->l = '(search)';
									$item->key = $node->key;
									$item->type = $example['type'];
									$item->params = $example;
									$node->children[$example['label']] = $item;
									break;
							}
						}
					}
					
					if(!empty($examples_menu))
						$node->children['(examples)'] = $examples_menu;
				}
			};

			if(is_array($tree))
			foreach($tree as $node)
				$recurseAddOptions($node);
			
			foreach($tree as $k => $v)
				$menu[$k] = $v;
		}
		
		// Placeholders
		
		$placeholders_menu = new DevblocksMenuItemPlaceholder();
		$labels = $this->getPlaceholderLabels();
		
		if(!empty($labels)) {
			$keys = array_map(function($key) {
				return '{{' . $key . '}}';
			}, array_keys($labels));
			
			$labels = array_combine($keys, array_column($labels, 'label'));
			$placeholders_menu->children = Extension_DevblocksContext::getPlaceholderTree($labels, ' ', '_');
			$menu['(placeholders)'] = $placeholders_menu;
		}
		
		return $menu;
	}
	
	function renderSubtotals() {
		if(!$this instanceof IAbstractView_Subtotals)
			return;
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $this->id);
		$tpl->assign('view', $this);

		$fields = $this->getSubtotalFields();
		$tpl->assign('subtotal_fields', $fields);
		
		$counts = $this->getSubtotalCounts($this->renderSubtotals);
		
		// Unless we're subtotalling by group, limit the results to top 20
		if($this->renderSubtotals != 't_group_id')
			$counts = array_slice($counts, 0, 20);
		
		$tpl->assign('subtotal_counts', $counts);
		
		// Reset any accumulated fulltext meta
		DevblocksPlatform::setRegistryKey('fulltext_meta', array(), DevblocksRegistryEntry::TYPE_JSON, false);
		
		$tpl->display('devblocks:cerberusweb.core::internal/views/sidebar.tpl');
	}
	
	protected function _canSubtotalCustomField($field_key) {
		$custom_fields = DAO_CustomField::getAll();
		
		if('cf_' != substr($field_key,0,3))
			return false;
		
		$cfield_id = substr($field_key,3);
		
		if(!isset($custom_fields[$cfield_id]))
			return false;
			
		$cfield = $custom_fields[$cfield_id]; /* @var $cfield Model_CustomField */

		$pass = false;
		
		switch($cfield->type) {
			case Model_CustomField::TYPE_CHECKBOX:
			case Model_CustomField::TYPE_DROPDOWN:
			case Model_CustomField::TYPE_MULTI_CHECKBOX:
			case Model_CustomField::TYPE_NUMBER:
			case Model_CustomField::TYPE_SINGLE_LINE:
			case Model_CustomField::TYPE_URL:
			case Model_CustomField::TYPE_WORKER:
				$pass = true;
				break;
		}
		
		return $pass;
	}
	
	protected function _getSubtotalDataForColumn($context, $field_key) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$fields = $this->getFields();
		$columns = $this->view_columns;
		$params = $this->getParams();
		
		if(false == ($context_ext = Extension_DevblocksContext::get($context)))
			return array();
		
		if(false == ($dao_class = $context_ext->getDaoClass()))
			return array();
		
		if(!method_exists($dao_class,'getSearchQueryComponents'))
			return array();
		
		if(!isset($columns[$field_key]))
			$columns[] = $field_key;
		
		$query_parts = call_user_func_array(
			array($dao_class,'getSearchQueryComponents'),
			array(
				$columns,
				$params,
				$this->renderSortBy,
				$this->renderSortAsc
			)
		);
		
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		
		$sql = sprintf("SELECT %s.%s as label, count(*) as hits ", //SQL_CALC_FOUND_ROWS
				$fields[$field_key]->db_table,
				$fields[$field_key]->db_column
			).
			$join_sql.
			$where_sql.
			"GROUP BY label ".
			"ORDER BY hits DESC ".
			"LIMIT 0,250 "
		;
		
		$results = $db->GetArraySlave($sql);
//		$total = count($results);
//		$total = ($total < 20) ? $total : $db->GetOneSlave("SELECT FOUND_ROWS()");

		return $results;
	}
	
	protected function _getSubtotalCountForStringColumn($context, $field_key, $label_map=array(), $value_oper='=', $value_key='value') {
		$counts = array();
		$results = $this->_getSubtotalDataForColumn($context, $field_key);
		
		if(is_callable($label_map)) {
			$label_map = $label_map(array_column($results, 'label'));
		}
		
		foreach($results as $result) {
			$label = $result['label'];
			$key = $label;
			$hits = $result['hits'];

			if(is_array($label_map) && isset($label_map[$result['label']]))
				$label = $label_map[$result['label']];
			
			// Null strings
			if(empty($label)) {
				$label = '(none)';
				if(!isset($counts[$key]))
					$counts[$key] = array(
						'hits' => $hits,
						'label' => $label,
						'filter' =>
							array(
								'field' => $field_key,
								'oper' => DevblocksSearchCriteria::OPER_IN_OR_NULL,
								'values' => array($value_key => ''),
							),
						'children' => array()
					);
				
			// Anything else
			} else {
				if(!isset($counts[$key]))
					$counts[$key] = array(
						'hits' => $hits,
						'label' => $label,
						'filter' =>
							array(
								'field' => $field_key,
								'oper' => $value_oper,
								'values' => array($value_key => $key),
							),
						'children' => array()
					);
				
			}
			
		}
		
		return $counts;
	}
	
	protected function _getSubtotalCountForNumberColumn($context, $field_key, $label_map=array(), $value_oper='=', $value_key='value') {
		$counts = array();
		$results = $this->_getSubtotalDataForColumn($context, $field_key);
		
		foreach($results as $result) {
			$label = $result['label'];
			$key = $label;
			$hits = $result['hits'];

			if(isset($label_map[$result['label']]))
				$label = $label_map[$result['label']];
			
			// Null strings
			if(empty($label)) {
				$label = '(none)';
				if(!isset($counts[$key]))
					$counts[$key] = array(
						'hits' => $hits,
						'label' => $label,
						'filter' =>
							array(
								'field' => $field_key,
								'oper' => DevblocksSearchCriteria::OPER_IN,
								'values' => array($value_key => 0),
							),
						'children' => array()
					);
				
			// Anything else
			} else {
				if(!isset($counts[$key]))
					$counts[$key] = array(
						'hits' => $hits,
						'label' => $label,
						'filter' =>
							array(
								'field' => $field_key,
								'oper' => $value_oper,
								'values' => array($value_key => $key),
							),
						'children' => array()
					);
				
			}
			
		}
		
		return $counts;
	}
	
	protected function _getSubtotalCountForBooleanColumn($context, $field_key) {
		$translate = DevblocksPlatform::getTranslationService();
		
		$counts = array();
		$results = $this->_getSubtotalDataForColumn($context, $field_key);
		
		foreach($results as $result) {
			$label = $result['label'];
			$hits = $result['hits'];

			if(!empty($label)) {
				$label = $translate->_('common.yes');
				$value = 1;
				
			} else {
				$label = $translate->_('common.no');
				$value = 0;
			}
			
			if(!isset($counts[$label]))
				$counts[$label] = array(
					'hits' => $hits,
					'label' => $label,
					'filter' =>
						array(
							'field' => $field_key,
							'oper' => '=',
							'values' => array('bool' => $value),
						),
					'children' => array()
				);
		}
		
		return $counts;
	}
	
	protected function _getSubtotalDataForWatcherColumn($context, $field_key) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$fields = $this->getFields();
		$columns = $this->view_columns;
		$params = $this->getParams();

		if(false == ($context_ext = Extension_DevblocksContext::get($context)))
			return array();
		
		if(false == ($dao_class = $context_ext->getDaoClass()))
			return array();
		
		if(false == ($search_class = $context_ext->getSearchClass()))
			return array();
		
		if(!method_exists($dao_class, 'getSearchQueryComponents'))
			return array();
		
		if(!method_exists($search_class, 'getPrimaryKey'))
			return array();
		
		$query_parts = call_user_func_array(
			array($dao_class,'getSearchQueryComponents'),
			array(
				$columns,
				$params,
				$this->renderSortBy,
				$this->renderSortAsc
			)
		);
		
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		
		$join_sql .= sprintf(" LEFT JOIN context_link AS watchers ON (".
			"watchers.to_context = 'cerberusweb.contexts.worker' ".
			"AND watchers.from_context = %s ".
			"AND watchers.from_context_id = %s) ",
			$db->qstr($context),
			$search_class::getPrimaryKey()
		);
		
		$sql = "SELECT watchers.to_context_id as watcher_id, count(*) as hits ". //SQL_CALC_FOUND_ROWS
			$join_sql.
			$where_sql.
			"GROUP BY watcher_id ".
			"ORDER BY hits DESC ".
			"LIMIT 0,250 "
		;
		
		$results = $db->GetArraySlave($sql);

		return $results;
	}
	
	protected function _getSubtotalCountForWatcherColumn($context, $field_key) {
		$workers = DAO_Worker::getAll();
		
		$counts = array();
		$results = $this->_getSubtotalDataForWatcherColumn($context, $field_key);
		
		if(is_array($results))
		foreach($results as $result) {
			$watcher_id = intval($result['watcher_id']);
			$hits = $result['hits'];
			$label = '';

			if(isset($workers[$watcher_id])) {
				$label = $workers[$watcher_id]->getName();
				$oper = DevblocksSearchCriteria::OPER_IN;
				$values = array('worker_id[]' => $watcher_id);
				
			} else {
				$label = '(nobody)';
				$oper = DevblocksSearchCriteria::OPER_IS_NULL;
				$values = array('');
			}
			
			if(!isset($counts[$watcher_id]))
				$counts[$watcher_id] = array(
					'hits' => $hits,
					'label' => $label,
					'filter' =>
						array(
							'field' => $field_key,
							'oper' => $oper,
							'values' => $values,
						),
					'children' => array()
				);
		}
		
		return $counts;
	}
		
	protected function _getSubtotalDataForContextLinkColumn($context, $field_key) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$fields = $this->getFields();
		$columns = $this->view_columns;

		$params = $this->getParams();
		$param_results = C4_AbstractView::findParam($field_key, $params);
		
		if(false == ($context_ext = Extension_DevblocksContext::get($context)))
			return array();
		
		if(false == ($dao_class = $context_ext->getDaoClass()))
			return array();
		
		$has_context_already = false;
		
		if(!empty($param_results)) {
			// Did the worker add this filter?
			$param_results = C4_AbstractView::findParam($field_key, $this->getEditableParams());
			
			if(count($param_results) > 0) {
				$param_result = array_shift($param_results);
				
				if($param_result->operator == DevblocksSearchCriteria::OPER_IN)
				if(is_array($param_result->value)) {
					$context_pair = current($param_result->value);
					@$context_data = explode(':', $context_pair);
	
					if(1 == count($context_data)) {
						$has_context_already = $context_data[0];
						
					} elseif(2 == count($context_data)) {
						$has_context_already = $context_data[0];
	
						$new_params = array(
							$field_key => new DevblocksSearchCriteria($field_key, DevblocksSearchCriteria::OPER_IN, array($has_context_already))
						);
						
						$params = array_merge($params, $new_params);
					}
				}
			}
			
		} else {
			$new_params = array(
				$field_key => new DevblocksSearchCriteria($field_key, DevblocksSearchCriteria::OPER_TRUE),
			);
			
			$params = array_merge($params, $new_params);
		}
		
		if(!method_exists($dao_class, 'getSearchQueryComponents'))
			return array();
		
		$query_parts = call_user_func_array(
			array($dao_class,'getSearchQueryComponents'),
			array(
				$columns,
				$params,
				$this->renderSortBy,
				$this->renderSortAsc
			)
		);
		
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];

		if(empty($has_context_already)) {
			// This intentionally isn't constrained with a LIMIT
			$sql = sprintf("SELECT from_context AS link_from_context, count(*) AS hits FROM context_link WHERE to_context = %s AND to_context_id IN (%s) GROUP BY from_context ORDER BY hits DESC ",
				$db->qstr($context),
				(
					sprintf("SELECT %s.id ", $query_parts['primary_table']).
					$query_parts['join'] .
					$query_parts['where']
				)
			);
			
		} else {
			$sql = sprintf("SELECT from_context AS link_from_context, from_context_id AS link_from_context_id, count(*) AS hits FROM context_link WHERE to_context = %s AND to_context_id IN (%s) AND from_context = %s GROUP BY from_context, from_context_id ORDER BY hits DESC LIMIT 0,250 ",
				$db->qstr($context),
				(
					sprintf("SELECT %s.id ", $query_parts['primary_table']).
					$query_parts['join'] .
					$query_parts['where']
				),
				$db->qstr($has_context_already)
			);
			
		}

		$results = $db->GetArraySlave($sql);

		return $results;
	}
	
	protected function _getSubtotalCountForContextLinkColumn($context, $field_key) {
		$contexts = Extension_DevblocksContext::getAll(false);
		$counts = array();
		
		$results = $this->_getSubtotalDataForContextLinkColumn($context, $field_key);
		
		if(is_array($results))
		foreach($results as $result) {
			$hits = $result['hits'];
			$label = '';
			
			if(isset($result['link_from_context_id'])) {
				$from_context = $result['link_from_context'];
				$from_context_id = $result['link_from_context_id'];
	
				if(!isset($contexts[$from_context]))
					continue;
				
				if(null == ($ext = Extension_DevblocksContext::get($from_context)))
					continue;
				
				if(false == ($meta = $ext->getMeta($from_context_id)) || empty($meta['name']))
					continue;
				
				$label = $meta['name'];
				$field_key = '*_context_link';
				$oper = DevblocksSearchCriteria::OPER_IN;
				$values = array('context_link[]' => $from_context . ':' . $from_context_id);
				
			} elseif(isset($result['link_from_context'])) {
				$from_context = $result['link_from_context'];
	
				if(!isset($contexts[$from_context]))
					continue;
				
				$label = $contexts[$from_context]->name;
				$field_key = '*_context_link';
				$oper = DevblocksSearchCriteria::OPER_IN;
				$values = array('context_link[]' => $from_context);
				
			} else {
				continue;
				
			}
			
			if(!isset($counts[$label]))
				$counts[$label] = array(
					'hits' => $hits,
					'label' => $label,
					'filter' =>
						array(
							'field' => $field_key,
							'oper' => $oper,
							'values' => $values,
						),
					'children' => array()
				);
		}
		
		return $counts;
	}
		
	protected function _getSubtotalDataForContextAndIdColumns($context, $field_key, $context_field, $context_id_field) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$fields = $this->getFields();
		$columns = $this->view_columns;

		$params = $this->getParams();
		$param_results = C4_AbstractView::findParam($field_key, $params);
		
		if(false == ($context_ext = Extension_DevblocksContext::get($context)))
			return array();
		
		if(false == ($dao_class = $context_ext->getDaoClass()))
			return array();
		
		$has_context_already = false;
		
		if(!empty($param_results)) {
			// Did the worker add this filter?
			$param_results = C4_AbstractView::findParam($field_key, $this->getEditableParams());
			
			if(count($param_results) > 0) {
				$param_result = array_shift($param_results);
				
				if($param_result->operator == DevblocksSearchCriteria::OPER_IN)
				if(is_array($param_result->value)) {
					$context_pair = current($param_result->value);
					@$context_data = explode(':', $context_pair);
	
					if(1 == count($context_data)) {
						$has_context_already = $context_data[0];
						
					} elseif(2 == count($context_data)) {
						$has_context_already = $context_data[0];
	
						$new_params = array(
							$field_key => new DevblocksSearchCriteria($field_key, DevblocksSearchCriteria::OPER_IN, array($has_context_already))
						);
						
						$params = array_merge($params, $new_params);
					}
				}
			}
			
		} else {
			$new_params = array(
				$field_key => new DevblocksSearchCriteria($field_key, DevblocksSearchCriteria::OPER_TRUE),
			);
			
			$params = array_merge($params, $new_params);
		}
		
		if(!method_exists($dao_class, 'getSearchQueryComponents'))
			return array();
		
		$query_parts = call_user_func_array(
			array($dao_class,'getSearchQueryComponents'),
			array(
				$columns,
				$params,
				$this->renderSortBy,
				$this->renderSortAsc
			)
		);
		
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];

		if(empty($has_context_already)) {
			// This intentionally isn't constrained with a LIMIT
			$sql = sprintf("SELECT %s AS context_field, count(*) AS hits %s %s GROUP BY context_field ORDER BY hits DESC ",
				$db->escape($context_field),
				$join_sql,
				$where_sql
			);
			
		} else {
			$sql = sprintf("SELECT %s AS context_field, %s AS context_id_field, count(*) AS hits %s %s GROUP BY context_field, context_id_field ORDER BY hits DESC ",
				$db->escape($context_field),
				$db->escape($context_id_field),
				$join_sql,
				$where_sql
			);
		}

		$results = $db->GetArraySlave($sql);

		return $results;
	}
	
	protected function _getSubtotalCountForContextAndIdColumns($context, $field_key, $context_field, $context_id_field, $filter_field='context_link[]') {
		$contexts = Extension_DevblocksContext::getAll(false);
		$counts = array();
		
		$results = $this->_getSubtotalDataForContextAndIdColumns($context, $field_key, $context_field, $context_id_field);
		
		if(is_array($results))
		foreach($results as $result) {
			$hits = $result['hits'];
			$label = '';
			
			if(isset($result['context_id_field'])) {
				$from_context = $result['context_field'];
				$from_context_id = $result['context_id_field'];
	
				if(!isset($contexts[$from_context]))
					continue;
				
				if(null == ($ext = Extension_DevblocksContext::get($from_context)))
					continue;
				
				if(!empty($from_context_id)) {
					if(false == ($meta = $ext->getMeta($from_context_id)) || empty($meta['name']))
						continue;
					
					$label = $meta['name'];
					
				} else {
					$label = $ext->manifest->name;
					
				}

				$oper = DevblocksSearchCriteria::OPER_IN;
				$values = array($filter_field => $from_context . ':' . $from_context_id);
				
			} elseif(isset($result['context_field'])) {
				$from_context = $result['context_field'];
	
				if(!isset($contexts[$from_context]))
					continue;
				
				$label = $contexts[$from_context]->name;
				$oper = DevblocksSearchCriteria::OPER_IN;
				$values = array($filter_field => $from_context);
				
			} else {
				continue;
				
			}
			
			if(!isset($counts[$label]))
				$counts[$label] = array(
					'hits' => $hits,
					'label' => $label,
					'filter' =>
						array(
							'field' => $field_key,
							'oper' => $oper,
							'values' => $values,
						),
					'children' => array()
				);
		}
		
		return $counts;
	}
	
	protected function _getSubtotalCountForHasFieldsetColumn($context, $field_key) {
		$counts = array();
		
		$custom_fieldsets = DAO_CustomFieldset::getAll();
		$data = $this->_getSubtotalDataForHasFieldsetColumn($context, $context);
		
		foreach($data as $row) {
			@$custom_fieldset = $custom_fieldsets[$row['link_fieldset_id']];
			
			if(empty($custom_fieldset))
				continue;
			
			$counts[$custom_fieldset->id] = array(
				'hits' => $row['hits'],
				'label' => $custom_fieldset->name,
				'filter' => array(
					'field' => $field_key,
					'oper' => DevblocksSearchCriteria::OPER_EQ,
					'values' => array(
						'options[]' => $custom_fieldset->id,
					)
				),
				'children' => array(),
			);
		}
		
		return $counts;
	}
	
	protected function _getSubtotalDataForHasFieldsetColumn($dao_class, $context) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$fields = $this->getFields();
		$columns = $this->view_columns;
		$params = $this->getParams();

		if(false == ($context_ext = Extension_DevblocksContext::get($context)))
			return array();
		
		if(false == ($dao_class = $context_ext->getDaoClass()))
			return array();
		
		// [TODO] Is this the best way to go about this?
		// Show all linked custom fieldsets; ignore current fieldset filters
		unset($params['*_has_fieldset']);
		
		if(!method_exists($dao_class, 'getSearchQueryComponents'))
			return array();
		
		$query_parts = call_user_func_array(
			array($dao_class,'getSearchQueryComponents'),
			array(
				$columns,
				$params,
				$this->renderSortBy,
				$this->renderSortAsc
			)
		);
		
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];

		// This intentionally isn't constrained with a LIMIT
		$sql = sprintf("SELECT from_context_id AS link_fieldset_id, count(*) AS hits FROM context_link WHERE to_context = %s AND to_context_id IN (%s) AND from_context = %s GROUP BY from_context_id ORDER BY hits DESC ",
			$db->qstr($context),
			(
				sprintf("SELECT %s.id ", $query_parts['primary_table']).
				$query_parts['join'] .
				$query_parts['where']
			),
			$db->qstr(CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
		);
		
		$results = $db->GetArraySlave($sql);

		return $results;
		
	}
	
	protected function _getSubtotalCountForCustomColumn($context, $field_key) {
		$db = DevblocksPlatform::getDatabaseService();
		$translate = DevblocksPlatform::getTranslationService();
		
		$counts = array();
		$fields = $this->getFields();
		$custom_fields = DAO_CustomField::getAll();
		$columns = $this->view_columns;
		$params = $this->getParams();

		$field_id = substr($field_key, 3);
		
		// If the custom field id is invalid, abort.
		if(!isset($custom_fields[$field_id]))
			return array();

		// Load the custom field
		$cfield = $custom_fields[$field_id];

		if(false == ($context_ext = Extension_DevblocksContext::get($context)))
			return array();
		
		if(false == ($dao_class = $context_ext->getDaoClass()))
			return array();
		
		if(false == ($search_class = $context_ext->getSearchClass()))
			return array();
		
		$cfield_select_sql = null;
		
		$cfield_key = $search_class::getCustomFieldContextWhereKey($cfield->context);
			
		if($cfield_key) {
			$cfield_select_sql .= sprintf("(SELECT field_value FROM %s WHERE context=%s AND context_id=%s AND field_id=%d ORDER BY field_value%s)",
				DAO_CustomFieldValue::getValueTableName($field_id),
				Cerb_ORMHelper::qstr($cfield->context),
				$cfield_key,
				$field_id,
				' LIMIT 1'
			);
		}
		
		// ... and that the DAO object is valid
		if(!method_exists($dao_class,'getSearchQueryComponents'))
			return array();

		// Construct the shared query components
		$query_parts = call_user_func_array(
			array($dao_class,'getSearchQueryComponents'),
			array(
				$columns,
				$params,
				$this->renderSortBy,
				$this->renderSortAsc
			)
		);
		
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
			
		switch($cfield->type) {
			
			case Model_CustomField::TYPE_CHECKBOX:
				$select = sprintf(
					"SELECT COUNT(*) AS hits, %s AS %s ",
					$cfield_select_sql,
					$field_key
				);
				
				$sql =
					$select.
					$join_sql.
					$where_sql.
					sprintf(
						"GROUP BY %s ",
						$field_key
					).
					"ORDER BY hits DESC "
				;
				
				$results = $db->GetArraySlave($sql);
		
				if(is_array($results))
				foreach($results as $result) {
					$label = '';
					$oper = DevblocksSearchCriteria::OPER_EQ;
					$values = null;
					
					switch($result[$field_key]) {
						case '':
							$label = '(no data)';
							$oper = DevblocksSearchCriteria::OPER_IS_NULL;
							break;
						case '0':
							$label = $translate->_('common.no');
							$values = array('value' => $result[$field_key]);
							break;
						case '1':
							$label = $translate->_('common.yes');
							$values = array('value' => $result[$field_key]);
							break;
					}
					
					$counts[$result[$field_key]] = array(
						'hits' => $result['hits'],
						'label' => $label,
						'filter' =>
							array(
								'field' => $field_key,
								'oper' => $oper,
								'values' => $values,
							),
					);
				}
				break;
				
			case Model_CustomField::TYPE_DROPDOWN:
			case Model_CustomField::TYPE_MULTI_CHECKBOX:
			case Model_CustomField::TYPE_NUMBER:
			case Model_CustomField::TYPE_SINGLE_LINE:
			case Model_CustomField::TYPE_URL:
				$select = sprintf(
					"SELECT COUNT(*) AS hits, %s AS %s ", //SQL_CALC_FOUND_ROWS
					$cfield_select_sql,
					$field_key
				);
				
				$sql =
					$select.
					$join_sql.
					$where_sql.
					sprintf(
						"GROUP BY %s ",
						$field_key
					).
					"ORDER BY hits DESC ".
					"LIMIT 20 "
				;
				
				$results = $db->GetArraySlave($sql);
//				$total = count($results);
//				$total = ($total < 20) ? $total : $db->GetOneSlave("SELECT FOUND_ROWS()");
				
				if(is_array($results))
				foreach($results as $result) {
					$label = '';
					$oper = DevblocksSearchCriteria::OPER_IN;
					$values = '';

					if(!empty($result[$field_key])) {
						$label = $result[$field_key];
						switch($cfield->type) {
							case Model_CustomField::TYPE_DROPDOWN:
							case Model_CustomField::TYPE_MULTI_CHECKBOX:
								$oper = DevblocksSearchCriteria::OPER_IN;
								$values = array('options[]' => $label);
								break;
							default:
								$oper = DevblocksSearchCriteria::OPER_EQ;
								$values = array('value' => $label);
								break;
						}
					}
					
					if(empty($label)) {
						$label = '(no data)';
						$oper = DevblocksSearchCriteria::OPER_IS_NULL;
						$values = array('value' => null);
					}
					
					$counts[$result[$field_key]] = array(
						'hits' => $result['hits'],
						'label' => $label,
						'filter' =>
							array(
								'field' => $field_key,
								'oper' => $oper,
								'values' => $values,
							),
					);
				}
				break;
				
			case Model_CustomField::TYPE_WORKER:
				$workers = DAO_Worker::getAll();
				
				$sql =
					sprintf(
						"SELECT COUNT(*) AS hits, %s AS %s ", //SQL_CALC_FOUND_ROWS
						$cfield_select_sql,
						$field_key
					).
					$join_sql.
					$where_sql.
					sprintf(
						"GROUP BY %s ",
						$field_key
					).
					"ORDER BY hits DESC ".
					"LIMIT 20 "
				;
				
				$results = $db->GetArraySlave($sql);
//				$total = count($results);
//				$total = ($total < 20) ? $total : $db->GetOneSlave("SELECT FOUND_ROWS()");
		
				if(is_array($results))
				foreach($results as $result) {
					$label = '';
					$oper = DevblocksSearchCriteria::OPER_EQ;
					$values = '';

					if(!empty($result[$field_key])) {
						$worker_id = $result[$field_key];
						if(isset($workers[$worker_id])) {
							$label = $workers[$worker_id]->getName();
							$oper = DevblocksSearchCriteria::OPER_IN;
							$values = array('worker_id[]' => $worker_id);
						}
					}
					
					if(empty($label)) {
						$label = '(nobody)';
						$oper = DevblocksSearchCriteria::OPER_IN_OR_NULL;
						$values = array('worker_id[]' => 0);
					}
					
					$counts[$result[$field_key]] = array(
						'hits' => $result['hits'],
						'label' => $label,
						'filter' =>
							array(
								'field' => $field_key,
								'oper' => $oper,
								'values' => $values,
							),
					);
				}
				break;
				
		}
		
		return $counts;
	}
	
	public static function _doBulkSetCustomFields($context, $custom_fields, $ids) {
		$fields = DAO_CustomField::getAll();
		
		if(!empty($custom_fields))
		foreach($custom_fields as $cf_id => $params) {
			if(!is_array($params) || !isset($params['value']))
				continue;
			
			@$cf_field = $fields[$cf_id];
			
			if(empty($cf_field))
				continue;
			
			$cf_val = $params['value'];
			
			// Data massaging
			switch($cf_field->type) {
				case Model_CustomField::TYPE_DATE:
					$cf_val = intval(@strtotime($cf_val));
					break;
				case Model_CustomField::TYPE_CHECKBOX:
				case Model_CustomField::TYPE_NUMBER:
					$cf_val = (0==strlen($cf_val)) ? '' : intval($cf_val);
					break;
			}

			// If multi-selection types, handle delta changes
			if(Model_CustomField::TYPE_MULTI_CHECKBOX==$cf_field->type) {
				if(is_array($cf_val))
				foreach($cf_val as $val) {
					$op = substr($val,0,1);
					$val = substr($val,1);
				
					if(is_array($ids))
					foreach($ids as $id) {
						if($op=='+')
							DAO_CustomFieldValue::setFieldValue($context,$id,$cf_id,$val,true);
						elseif($op=='-')
							DAO_CustomFieldValue::unsetFieldValue($context,$id,$cf_id,$val);
					}
				}
					
			// Otherwise, set/unset as a single field
			} else {
				if(is_array($ids))
				foreach($ids as $id) {
					if(0 != strlen($cf_val))
						DAO_CustomFieldValue::setFieldValue($context,$id,$cf_id,$cf_val);
					else
						DAO_CustomFieldValue::unsetFieldValue($context,$id,$cf_id);
				}
			}
		}
		
		// Link any utilized custom fieldsets to these IDs
		if(is_array($ids))
		foreach($ids as $id) {
			DAO_CustomFieldset::linkToContextByFieldIds($context, $id, array_keys($custom_fields));
		}
	}
	
	public static function _doBulkScheduleBehavior($context, array $params, array $ids) {
		if(!isset($params) || !is_array($params))
			return false;
			
		@$behavior_id = $params['id'];
		@$behavior_when = strtotime($params['when']) or time();
		@$behavior_params = isset($params['params']) ? $params['params'] : array();
		
		if(empty($behavior_id))
			return false;
		
		foreach($ids as $batch_id) {
			DAO_ContextScheduledBehavior::create(array(
				DAO_ContextScheduledBehavior::BEHAVIOR_ID => $behavior_id,
				DAO_ContextScheduledBehavior::CONTEXT => $context,
				DAO_ContextScheduledBehavior::CONTEXT_ID => $batch_id,
				DAO_ContextScheduledBehavior::RUN_DATE => $behavior_when,
				DAO_ContextScheduledBehavior::VARIABLES_JSON => json_encode($behavior_params),
			));
		}
		
		return true;
	}
	
	public static function _doBulkChangeWatchers($context, array $params, array $ids) {
		if(!isset($params) || !is_array($params))
			return false;
		
		foreach($ids as $batch_id) {
			if(isset($params['add']) && is_array($params['add']))
				CerberusContexts::addWatchers($context, $batch_id, $params['add']);
			
			if(isset($params['remove']) && is_array($params['remove']))
				CerberusContexts::removeWatchers($context, $batch_id, $params['remove']);
		}
	}
	
	public static function _doBulkBroadcast($context, array $params, array $ids, $to_key, array $options=array()) {
		if(empty($params) || empty($ids))
			return false;
		
		try {
			$tpl_builder = DevblocksPlatform::getTemplateBuilder();
			
			if(
				!isset($params['worker_id'])
				|| empty($params['worker_id'])
				|| !isset($params['subject'])
				|| empty($params['subject'])
				|| !isset($params['message'])
				|| empty($params['message'])
				)
				throw new Exception("Missing parameters for broadcast.");

			$is_queued = (isset($params['is_queued']) && $params['is_queued']) ? true : false;
			$status_id = intval(@$params['status_id']);
			
			$models = CerberusContexts::getModels($context, $ids);
			$dicts = DevblocksDictionaryDelegate::getDictionariesFromModels($models, $context, array('custom_'));
			
			if(is_array($dicts))
			foreach($dicts as $id => $dict) {
				try {
					if(false == ($recipients = CerberusMail::parseRfcAddresses($dict->$to_key)))
						continue;
					
					foreach($recipients as $to) {
						@$callback_recipient_reject = $options['callback_recipient_reject'];
						@$callback_recipient_expand = $options['callback_recipient_expand'];
						
						if(is_callable($callback_recipient_expand))
							$callback_recipient_expand($to, $dict);
						
						// Are we skipping this recipient?
						if(is_callable($callback_recipient_reject))
							if($callback_recipient_reject($dict))
								continue;
						
						$subject = $tpl_builder->build($params['subject'], $dict);
						$body = $tpl_builder->build($params['message'], $dict);
						
						$json_params = array(
							'to' => $to['full_email'],
							'group_id' => $params['group_id'],
							'status_id' => $status_id,
							'is_broadcast' => 1,
							'context_links' => array(
								array($context, $id),
							),
						);
						
						if(isset($params['format']))
							$json_params['format'] = $params['format'];
						
						if(isset($params['html_template_id']))
							$json_params['html_template_id'] = intval($params['html_template_id']);
						
						if(isset($params['file_ids']))
							$json_params['file_ids'] = $params['file_ids'];
						
						$fields = array(
							DAO_MailQueue::TYPE => Model_MailQueue::TYPE_COMPOSE,
							DAO_MailQueue::TICKET_ID => 0,
							DAO_MailQueue::WORKER_ID => $params['worker_id'],
							DAO_MailQueue::UPDATED => time(),
							DAO_MailQueue::HINT_TO => $to['full_email'],
							DAO_MailQueue::SUBJECT => $subject,
							DAO_MailQueue::BODY => $body,
							DAO_MailQueue::PARAMS_JSON => json_encode($json_params),
						);
						
						if($is_queued) {
							$fields[DAO_MailQueue::IS_QUEUED] = 1;
						}
						
						$draft_id = DAO_MailQueue::create($fields);
					}
					
				} catch (Exception $e) {
					return false;
				}
			}
		} catch (Exception $e) {
			return false;
		}
		
		return true;
	}
};

interface IAbstractView_QuickSearch {
	function getQuickSearchFields();
	function getParamFromQuickSearchFieldTokens($field, $tokens);
};

interface IAbstractView_Subtotals {
	function getSubtotalCounts($column);
	function getSubtotalFields();
};

class CerbQuickSearchLexer {
	private static function _recurse($token, $key, $node_callback, $after_children_callback=null) {
		if(!is_callable($node_callback))
			return;
		
		if(empty($key) || $token->type == $key)
			$node_callback($token);
		
		foreach($token->children as $child)
			self::_recurse($child, $key, $node_callback, $after_children_callback);
		
		if(is_callable($after_children_callback))
			$after_children_callback($token);
	}
	
	static function buildParams($token, &$parent) {
		switch($token->type) {
			case 'T_GROUP':
				$param = array(
					$token->value == 'OR' ? DevblocksSearchCriteria::GROUP_OR : DevblocksSearchCriteria::GROUP_AND,
				);
				foreach($token->children as $child)
					self::buildParams($child, $param);
				
				if(!is_array($parent)) {
					$parent = $param;
				} else {
					$parent[] = $param;
				}
				break;
				
			case 'T_FIELD':
				$param = new DevblocksSearchCriteria(null, null);
				$param->key = $token->value;
				$param->tokens = $token->children;
				$parent[] = $param;
				break;
		}
	}
	
	static function getFieldsFromQuery($query) {
		$original_query = $query;
		$tokens = array();
		
		// Extract double-quoted literals text
		
		$quotes = array();
		$start = 0;
		
		while(false !== ($from = strpos($query, '"', $start))) {
			if(false === ($to = strpos($query, '"', $from+1)))
				break;
			
			$idx = count($quotes);
			$cut = substr($query, $from, $to-$from+1);
			$quotes[] = trim($cut,'"');
			$query = str_replace($cut, ' <$Q:'.$idx.'> ', $query);
			$start = $from;
		}
		
		// Tokenize symbols
		
		$query = str_replace(array(' OR ',' AND ','(',')','[',']','"'), array(' <$OR> ',' <$AND> ',' <$PO> ',' <$PC> ',' <$BO> ',' <$BC> '), $query);
		
		// Cap at two continuous whitespace chars
		
		$query = preg_replace('#\s{2,}#', '  ', $query);
		
		// Tokens for lexer
		$token_map = array(
			'[a-zA-Z0-9\_\.]+\:' => 'T_FIELD',
			'\s+' => 'T_WHITESPACE',
			'[^\s]+' => 'T_TEXT',
		);
		
		$token_offsets = array_values($token_map);
		
		// Compile the regexp
		$regexp = '((' . implode(')|(', array_keys($token_map)) . '))Ax';
		
		$offset = 0;
		
		while(isset($query[$offset])) {
			if(!preg_match($regexp, $query, $matches, null, $offset))
				break;
			
			if('' == $matches[0])
				break;
			
			$match = $matches[0];
			array_shift($matches);
			
			if(false === ($idx = array_search($match, $matches)))
				break;
			
			if(!isset($token_offsets[$idx]))
				break;
			
			$token_type = $token_offsets[$idx];
			$token_value = $match;
			
			switch($token_type) {
				case 'T_FIELD':
					$token_value = rtrim($match, ':');
					break;
					
				case 'T_WHITESPACE':
					$token_type = null;
					break;
					
				case 'T_TEXT':
					if($match == '!') {
						$token_type = 'T_NOT';
						
					} elseif (substr($match,0,4) == '<$Q:') {
						$idx = intval(substr($match,4));
						$token_type = 'T_QUOTED_TEXT';
						$token_value = $quotes[$idx];
						
					} else {
						switch($match) {
							case '<$PO>':
								$token_type = 'T_PARENTHETICAL_OPEN';
								$token_value = '(';
								break;
							case '<$PC>':
								$token_type = 'T_PARENTHETICAL_CLOSE';
								$token_value = ')';
								break;
							case '<$BO>':
								$token_type = 'T_BRACKET_OPEN';
								$token_value = '[';
								break;
							case '<$BC>':
								$token_type = 'T_BRACKET_CLOSE';
								$token_value = ']';
								break;
							case '<$AND>':
								$token_type = 'T_BOOL';
								$token_value = 'AND';
								break;
							case '<$OR>':
								$token_type = 'T_BOOL';
								$token_value = 'OR';
								break;
						}
					}
					break;
			}
			
			if($token_type)
				$tokens[] = new CerbQuickSearchLexerToken($token_type, $token_value);
			
			$offset += strlen($match);
		}
		
		// Bracket arrays
		
		reset($tokens);
		$start = null;
		while($token = current($tokens)) {
			switch($token->type) {
				case 'T_BRACKET_OPEN':
					$start = key($tokens);
					break;
					
				case 'T_BRACKET_CLOSE':
					if($start) {
						$len = key($tokens)-$start+1;
						$cut = array_splice($tokens, $start, $len, array(array()));
						
						array_shift($cut);
						array_pop($cut);
						
						$tokens[$start] = new CerbQuickSearchLexerToken('T_ARRAY', null, $cut);
						$start = null;
					}
					break;
			}
			
			next($tokens);
		}
		
		// Group parentheticals
		
		reset($tokens);
		$start_idx = $end_idx = null;
		$opens = array();
		
		while($token = current($tokens)) {
			switch($token->type) {
				case 'T_PARENTHETICAL_OPEN':
					$opens[] = key($tokens);
					next($tokens);
					break;
					
				case 'T_PARENTHETICAL_CLOSE':
					$start = array_pop($opens);
					$len = key($tokens)-$start+1;
					$cut = array_splice($tokens, $start, $len, array(array()));
					
					// Remove the wrappers
					array_shift($cut);
					array_pop($cut);
					
					// If we only had one element in the group, don't bother grouping
					if(count($cut) == 1) {
						$tokens[$start] = $cut[0];
					} else {
						$tokens[$start] = new CerbQuickSearchLexerToken('T_GROUP', null, $cut);
					}
					reset($tokens);
					break;
					
				default:
					next($tokens);
					break;
			}
		}
		
		$tokens = new CerbQuickSearchLexerToken('T_GROUP', null, $tokens);
		
		// Arrays
		
		self::_recurse($tokens, 'T_ARRAY', function($token) {
			$elements = array();
			
			self::_recurse($token, 'T_TEXT', function($token) use (&$elements) {
				$elements = array_merge($elements, DevblocksPlatform::parseCsvString($token->value));
			});
			
			$token->value = $elements;
			$token->children = array();
		});
		
		// Recurse
		
		self::_recurse($tokens, '', function($token) {
			$append_to = null;
			
			foreach($token->children as $k => $child) {
				switch($child->type) {
					case 'T_FIELD':
						$append_to = $k;
						break;
						
					case 'T_ARRAY':
					case 'T_GROUP':
						if(!is_null($append_to)) {
							$token->children[$append_to]->children[] = $child;
							unset($token->children[$k]);
						}
						$append_to = null;
						break;
						
					case 'T_NOT':
					case 'T_TEXT':
					case 'T_QUOTED_TEXT':
						if(!is_null($append_to)) {
							$token->children[$append_to]->children[] = $child;
							unset($token->children[$k]);
							$append_to = null;
						}
						break;
						
					default:
						$append_to = null;
						break;
				}
			}
		});
		
		// Move any unattached text into a fulltext field
		self::_recurse($tokens, 'T_GROUP', function($token) {
			$field = null;
			
			foreach($token->children as $k => $child) {
				switch($child->type) {
					case 'T_QUOTED_TEXT':
					case 'T_TEXT':
						if(is_null($field)) {
							$field = new CerbQuickSearchLexerToken('T_FIELD', 'text');
							$token->children[] = $field;
						}
							
						$field->children[] = $child;
						unset($token->children[$k]);
						break;
						
					default:
						$field = null;
						break;
				}
			}
		});
		
		// Sort out the boolean mode of each group
		self::_recurse($tokens, 'T_GROUP', function($token) {
			$field = null;
			
			// [TODO] Operator precedence AND -> OR
			// [TODO] Handle 'a OR b AND c'
			
			foreach($token->children as $k => $child) {
				switch($child->type) {
					case 'T_BOOL':
						if(empty($token->value))
							$token->value = $child->value ?: 'AND';
						unset($token->children[$k]);
						break;
				}
			}
		});
		
		$params = null;
		self::buildParams($tokens, $params);
		
		// Remove the outer grouping if it's not necessary
		if($params[0] == 'AND') {
			array_shift($params);
			$params = $params;
		} else {
			$params = array($params);
		}
		
		return $params;
	}
	
	static function getOperStringFromTokens($tokens, &$oper, &$value) {
		self::_getOperValueFromTokens($tokens, $oper, $value);
		
		$not = ($oper == DevblocksSearchCriteria::OPER_NIN);
		
		if(0 == count($value)) {
			$oper = $not ? DevblocksSearchCriteria::OPER_IS_NOT_NULL : DevblocksSearchCriteria::OPER_IS_NULL;
			$value = null;
			
		} else {
			$oper = $not ? DevblocksSearchCriteria::OPER_NEQ : DevblocksSearchCriteria::OPER_EQ;
			$value = array_shift($value);
		}
		
		return true;
	}
	
	static function getOperArrayFromTokens($tokens, &$oper, &$value) {
		return self::_getOperValueFromTokens($tokens, $oper, $value);
	}
	
	static function _getOperValueFromTokens($tokens, &$oper, &$value) {
		if(!is_array($tokens))
			return false;
		
		$not = false;
		$oper = DevblocksSearchCriteria::OPER_IN;
		$value = array();
		
		foreach($tokens as $token) {
			if(!($token instanceof CerbQuickSearchLexerToken))
				continue;
			
			switch($token->type) {
				case 'T_NOT':
					$not = !$not;
					break;
					
				case 'T_QUOTED_TEXT':
				case 'T_TEXT':
					$oper = $not ? DevblocksSearchCriteria::OPER_NIN : DevblocksSearchCriteria::OPER_IN;
					$value = array($token->value);
					break;
					
				case 'T_ARRAY':
					$oper = $not ? DevblocksSearchCriteria::OPER_NIN : DevblocksSearchCriteria::OPER_IN;
					$value = $token->value;
					break;
			}
		}
		
		return true;
	}
	
	static function getTokensAsQuery($tokens) {
		$string = null;
		$group_stack = [];
		
		$node_callback = function($token) use (&$string, &$group_stack) {
			switch($token->type) {
				case 'T_NOT':
					$string .= '!';
					break;
					
				case 'T_GROUP':
					$string .= '(';
					$group_stack[] = $token->value;
					break;
					
				case 'T_ARRAY':
					$string .= '[' . implode(',', $token->value) . ']';
					break;
					
				case 'T_QUOTED_TEXT':
					$string .= '"' . $token->value;
					break;
					
				case 'T_TEXT':
					if($string && !DevblocksPlatform::strEndsWith($string, ['(',':']))
						$string .= ' ';
						
					$string .= $token->value;
					break;
					
				case 'T_FIELD':
					// AND/OR separators
					if($string && !DevblocksPlatform::strEndsWith($string, ['(',':']) && end($group_stack)) {
						if(!DevblocksPlatform::strEndsWith($string, [' ']))
							$string .= ' ';
						
						$string .= end($group_stack);
					}
					
					if(!DevblocksPlatform::strEndsWith($string, [' ','(']))
						$string .= ' ';
					
					switch($token->value) {
						case 'text':
							break;
							
						default:
							$string .= $token->value . ':';
							break;
					}
					break;
			}
		};
		
		$after_children_callback = function($token) use (&$string, &$group_stack) {
			switch($token->type) {
				case 'T_GROUP':
					$string = rtrim($string) . ')';
					array_pop($group_stack);
					break;
					
				case 'T_ARRAY':
					break;
					
				case 'T_QUOTED_TEXT':
					$string .= '"';
					break;
					
				case 'T_TEXT':
					break;
					
				case 'T_FIELD':
					$string .= ' ';
					break;
			}
		};
		
		if(is_array($tokens) && isset($tokens[0]))
			self::_recurse($tokens[0], null, $node_callback, $after_children_callback);
		
		return $string;
	}
	
	static function getHumanTimeTokensAsNumbers($tokens, $interval=1) {
		if(!is_array($tokens))
			return false;
		
		$new_tokens = $tokens;
			
		foreach($new_tokens as &$token) {
			switch($token->type) {
				case 'T_QUOTED_TEXT':
				case 'T_TEXT':
					$v = $token->value;
					
					if(preg_match('#^([\!\=\>\<]+)(.*)#', $v, $matches)) {
						$oper_hint = trim($matches[1]);
						$v = trim($matches[2]);
						
						if(!is_numeric($v))
							$v = floor(DevblocksPlatform::strTimeToSecs($v) / $interval);
						
						$v = $oper_hint . $v;
						
					} else if(preg_match('#^(.*)?\.\.\.(.*)#', $v, $matches)) {
						 $from = trim($matches[1]);
						 $to = trim($matches[2]);
						 
						 if(!is_numeric($from))
							$from = floor(DevblocksPlatform::strTimeToSecs($from) / $interval);
						 if(!is_numeric($to))
							$to = floor(DevblocksPlatform::strTimeToSecs($to) / $interval);
						 
						 $v = sprintf("%s...%s", $from, $to);
						 
					} else {
						if(!is_numeric($v))
							$v = floor(DevblocksPlatform::strTimeToSecs($v) / $interval);
					}
					
					$token->value = $v;
					break;
			}
		}
		
		return $new_tokens;
	}
};

class CerbQuickSearchLexerToken {
	public $type = null;
	public $value = null;
	public $children = array();
	
	public function __construct($type, $value, $children=array()) {
		$this->type = $type;
		$this->value = $value;
		$this->children = $children;
	}
};

/**
 * Used to persist a C4_AbstractView instance and not be encumbered by
 * classloading issues (out of the session) from plugins that might have
 * concrete AbstractView implementations.
 */
if(!class_exists('C4_AbstractViewModel')):
class C4_AbstractViewModel {
	public $class_name = '';

	public $id = '';
	public $name = "";
	public $options = array();
	public $is_ephemeral = 0;
	
	public $view_columns = array();
	public $columnsHidden = array();
	
	public $paramsEditable = array();
	public $paramsDefault = array();
	public $paramsRequired = array();
	public $paramsHidden = array();

	public $renderPage = 0;
	public $renderLimit = 10;
	public $renderTotal = true;
	public $renderSortBy = '';
	public $renderSortAsc = 1;
	
	public $renderFilters = null;
	public $renderSubtotals = null;
	
	public $renderTemplate = null;
	
	public $placeholderLabels = array();
	public $placeholderValues = array();
	
	static function loadFromClass($class_name) {
		if(empty($class_name))
			return false;
		
		if(false == ($class = new $class_name))
			return false;
		
		$class->setAutoPersist(false);
		
		if(false == ($inst = C4_AbstractViewLoader::serializeAbstractView($class)))
			return false;
		
		return $inst;
	}
};
endif;

/**
 * This is essentially an AbstractView Factory
 */
class C4_AbstractViewLoader {
	/**
	 * Enter description here...
	 *
	 * @param string $view_id
	 * @param C4_AbstractViewModel $defaults
	 * @return C4_AbstractView | null
	 */
	static function getView($view_id, C4_AbstractViewModel $defaults=null) {
		$worker_id = 0;
		
		if(null !== ($active_worker = CerberusApplication::getActiveWorker()))
			$worker_id = $active_worker->id;

		// Check if we've ever persisted this view
		if(false !== ($model = DAO_WorkerViewModel::getView($worker_id, $view_id))) {
			return self::unserializeAbstractView($model);
			
		} elseif(!empty($defaults) && $defaults instanceof C4_AbstractViewModel) {
			// Load defaults if they were provided
			if(null != ($view = self::unserializeAbstractView($defaults, false)))  {
				return $view;
			}
		}
		
		return NULL;
	}

	/**
	 * Enter description here...
	 *
	 * @param string $class C4_AbstractView
	 * @param string $view_label ID
	 * @param C4_AbstractView $view
	 */
	static function setView($view_id, C4_AbstractView $view) {
		$worker_id = 0;
		
		if(null !== ($active_worker = CerberusApplication::getActiveWorker()))
			$worker_id = $active_worker->id;
		
		// Is the view dirty? (do we need to persist it?)
		if(false != ($_init_checksum = @$view->_init_checksum)) {
			unset($view->_init_checksum);
			$_exit_checksum = sha1(serialize($view));
			
			// If the view model is not dirty (we wouldn't end up changing anything in the database)
			if($_init_checksum == $_exit_checksum) {
				return;
			}
		}
		
		$model = self::serializeAbstractView($view);
		
		DAO_WorkerViewModel::setView($worker_id, $view_id, $model);
	}

	static function deleteView($view_id, $worker_id=null) {
		$worker_id = 0;
		
		if(null !== ($active_worker = CerberusApplication::getActiveWorker()))
			$worker_id = $active_worker->id;

		DAO_WorkerViewModel::deleteView($worker_id, $view_id);
	}
	
	static function serializeAbstractView($view) {
		if(!$view instanceof C4_AbstractView)
			return NULL;
		
		$model = new C4_AbstractViewModel();
		
		$class_name = get_class($view);
		$model->class_name = $class_name;
		
		$parent = new $class_name(); /* @var $parent C4_AbstractView */
		$parent->setAutoPersist(false);

		$model->id = $view->id;
		$model->is_ephemeral = $view->is_ephemeral ? true : false;
		$model->name = $view->name;
		$model->options = $view->options;
		
		$model->view_columns = $view->view_columns;
		
		// Only persist hidden columns that are distinct from the parent (so we can inherit parent changes)
		$model->columnsHidden = array_diff($view->getColumnsHidden(), $parent->getColumnsHidden());
		
		$model->paramsEditable = $view->getEditableParams();
		$model->paramsDefault = $view->getParamsDefault();
		$model->paramsRequired = $view->getParamsRequired();
		// Only persist hidden params that are distinct from the parent (so we can inherit parent changes)
		$model->paramsHidden = array_diff($view->getParamsHidden(), $parent->getParamsHidden());
		
		$model->renderPage = intval($view->renderPage);
		$model->renderLimit = intval($view->renderLimit);
		$model->renderTotal = intval($view->renderTotal);
		$model->renderSortBy = $view->renderSortBy;
		$model->renderSortAsc = $view->renderSortAsc ? true : false;

		$model->renderFilters = $view->renderFilters ? true : false;
		$model->renderSubtotals = $view->renderSubtotals;
		
		$model->renderTemplate = $view->renderTemplate;
		
		$model->placeholderLabels = $view->getPlaceholderLabels();
		$model->placeholderValues = $view->getPlaceholderValues();
		
		return $model;
	}

	static function unserializeAbstractView(C4_AbstractViewModel $model, $checksum=true) {
		if(!class_exists($model->class_name, true))
			return null;
		
		if(null == ($inst = new $model->class_name))
			return null;
		
		/* @var $inst C4_AbstractView */
		
		if(!empty($model->id))
			$inst->id = $model->id;
		if(null !== $model->is_ephemeral)
			$inst->is_ephemeral = $model->is_ephemeral ? true : false;
		if(!empty($model->name))
			$inst->name = $model->name;
		
		if(is_array($model->options) && !empty($model->options))
			$inst->options = $model->options;
		
		if(is_array($model->view_columns)) 
			$inst->view_columns = $model->view_columns;
		if(is_array($model->columnsHidden))
			$inst->addColumnsHidden($model->columnsHidden, false);
		
		if(is_array($model->paramsEditable))
			$inst->addParams($model->paramsEditable, true);
		if(is_array($model->paramsDefault))
			$inst->addParamsDefault($model->paramsDefault, true);
		if(is_array($model->paramsRequired))
			$inst->addParamsRequired($model->paramsRequired, true);
		if(is_array($model->paramsHidden))
			$inst->addParamsHidden($model->paramsHidden, false);

		if(null !== $model->renderPage)
			$inst->renderPage = intval($model->renderPage);
		if(null !== $model->renderLimit)
			$inst->renderLimit = intval($model->renderLimit);
		if(null !== $model->renderTotal)
			$inst->renderTotal = intval($model->renderTotal);
		if(!empty($model->renderSortBy))
			$inst->renderSortBy = $model->renderSortBy;
		if(null !== $model->renderSortBy)
			$inst->renderSortAsc = $model->renderSortAsc ? true : false;

		$inst->renderFilters = $model->renderFilters ? true : false;
		$inst->renderSubtotals = $model->renderSubtotals;
			
		$inst->renderTemplate = $model->renderTemplate;
		
		if(is_array($model->placeholderLabels))
			$inst->setPlaceholderLabels($model->placeholderLabels);
		if(is_array($model->placeholderValues))
			$inst->setPlaceholderValues($model->placeholderValues);
		
		// Enforce class restrictions
		$parent = new $model->class_name;
		$parent->__auto_persist = false;
		// [TODO] This is a rather heavy way to accomplish this, these could be static
		$inst->addColumnsHidden($parent->getColumnsHidden());
		$inst->addParamsHidden($parent->getParamsHidden());
		$inst->addParamsRequired($parent->getParamsRequired());
		unset($parent);
		
		if($checksum) {
			// If the param keys changed during unserialization, then consider everything changed
			if(array_keys($model->paramsEditable) != array_keys($inst->getParams(false))) {
				$inst->_init_checksum = sha1(mt_rand());
				
			} else {
				$inst->_init_checksum = sha1(serialize($inst));
			}
		}
		
		return $inst;
	}
	
	static function serializeViewToAbstractJson(C4_AbstractView $view, $context=null) {
		$model = array(
			'options' => $view->options,
			'columns' => $view->view_columns,
			'params' => json_decode(json_encode($view->getEditableParams()), true),
			'limit' => intval($view->renderLimit),
			'sort_by' => $view->renderSortBy,
			'sort_asc' => !empty($view->renderSortAsc),
			'subtotals' => $view->renderSubtotals,
		);
		
		if(!empty($context))
			$model['context'] = $context;
		
		return json_encode($model);
	}
	
	static function unserializeViewFromAbstractJson($view_model, $view_id) {
		if(!isset($view_model['context']))
			return false;
			
		$view_context = $view_model['context'];
		
		if(empty($view_context))
			return false;
		
		if(null == ($ctx = Extension_DevblocksContext::get($view_context)))
			return false;
		
		if(null == ($view = $ctx->getChooserView($view_id))) /* @var $view C4_AbstractView */
			return false;
		
		if(isset($view_model['options']))
			$view->options = $view_model['options'];
		
		$view->view_columns = $view_model['columns'];
		$view->renderLimit = intval($view_model['limit']);
		$view->renderSortBy = $view_model['sort_by'];
		$view->renderSortAsc = $view_model['sort_asc'] ? true : false;
		$view->renderSubtotals = $view_model['subtotals'];
		
		// Convert JSON params back to objects
		$func = function(&$e) use (&$func) {
			if(is_array($e) && isset($e['field']) && isset($e['operator'])) {
				$e = new DevblocksSearchCriteria($e['field'], $e['operator'], $e['value']);
				
			} elseif(is_array($e)) {
				array_walk(
					$e,
					$func
				);
			} else {
				// Trim?
			}
		};
		
		array_walk(
			$view_model['params'],
			$func
		);
		
		$view->addParams($view_model['params'], true);
		
		if(isset($view_model['params_required'])) {
			array_walk(
				$view_model['params_required'],
				$func
			);
			
			$view->addParamsRequired($view_model['params_required'], true);
		}

		$active_worker = CerberusApplication::getActiveWorker();
		
		$labels = array();
		$values = array();
		
		CerberusContexts::getContext(CerberusContexts::CONTEXT_WORKER, $active_worker, $worker_labels, $worker_values, null, true, true);
		CerberusContexts::merge('current_worker_', null, $worker_labels, $worker_values, $labels, $values);
		
		$view->setPlaceholderValues($values);
		
		// If the param keys changed during unserialization, then consider everything changed
		if(array_keys($view_model['params']) != array_keys($view->getParams(false))) {
			$view->_init_checksum = sha1(mt_rand());
			
		} else {
			$view->_init_checksum = sha1(serialize($view));
		}
		
		return $view;
	}
};

class DAO_WorkerViewModel extends Cerb_ORMHelper {
	/**
	 *
	 * @param string $where
	 * @return C4_AbstractViewModel[]
	 */
	static public function getWhere($where=null) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$objects = array();
		
		$fields = array(
			'worker_id',
			'view_id',
			'is_ephemeral',
			'class_name',
			'title',
			'options_json',
			'columns_json',
			'columns_hidden_json',
			'params_editable_json',
			'params_required_json',
			'params_default_json',
			'params_hidden_json',
			'render_page',
			'render_total',
			'render_limit',
			'render_sort_by',
			'render_sort_asc',
			'render_filters',
			'render_subtotals',
			'render_template',
			'placeholder_labels_json',
			'placeholder_values_json',
		);
		
		$sql = sprintf("SELECT %s FROM worker_view_model %s",
			implode(',', $fields),
			(!empty($where) ? ('WHERE ' . $where) : '')
		);
		
		$rs = $db->ExecuteSlave($sql);
		
		if($rs instanceof mysqli_result)
		while($row = mysqli_fetch_array($rs)) {
			$model = new C4_AbstractViewModel();
			$model->id = $row['view_id'];
			$model->worker_id = $row['worker_id'];
			$model->is_ephemeral = $row['is_ephemeral'];
			$model->class_name = $row['class_name'];
			$model->name = $row['title'];
			$model->renderPage = $row['render_page'];
			$model->renderTotal = $row['render_total'];
			$model->renderLimit = $row['render_limit'];
			$model->renderSortBy = $row['render_sort_by'];
			$model->renderSortAsc = $row['render_sort_asc'];
			$model->renderFilters = $row['render_filters'];
			$model->renderSubtotals = $row['render_subtotals'];
			$model->renderTemplate = $row['render_template'];
			
			// JSON blocks
			$model->options = json_decode($row['options_json'], true);
			$model->view_columns = json_decode($row['columns_json'], true);
			$model->columnsHidden = json_decode($row['columns_hidden_json'], true);
			$model->paramsEditable = self::decodeParamsJson($row['params_editable_json']);
			$model->paramsRequired = self::decodeParamsJson($row['params_required_json']);
			$model->paramsDefault = self::decodeParamsJson($row['params_default_json']);
			$model->paramsHidden = json_decode($row['params_hidden_json'], true);
			
			$model->placeholderLabels = json_decode($row['placeholder_labels_json'], true);
			$model->placeholderValues = json_decode($row['placeholder_values_json'], true);
			
			// Make sure it's a well-formed view
			if(empty($model->class_name) || !class_exists($model->class_name, true))
				return false;
			
			$objects[] = $model;
		}
			
		return $objects;
	}
	
	/**
	 *
	 * @param integer $worker_id
	 * @param string $view_id
	 * @return C4_AbstractViewModel|false
	 */
	static public function getView($worker_id, $view_id) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$results = DAO_WorkerViewModel::getWhere(sprintf("worker_id = %d AND view_id = %s",
			$worker_id,
			$db->qstr($view_id)
		));
		
		if(empty($results) || !is_array($results))
			return false;

		@$model = array_shift($results);
		
		return $model;
	}

	static public function decodeParamsJson($json) {
		if(empty($json) || false === ($params = json_decode($json, true)))
			return array();
		
		self::_walkSerializedParams($params, function(&$node) {
			if(is_array($node) && isset($node['field'])) {
				$node = new DevblocksSearchCriteria($node['field'], $node['operator'], $node['value']);
			}
		});
		
		return $params;
	}
	
	static private function _walkSerializedParams(&$params, $callback) {
		if(is_array($params))
			$callback($params);
		
		if(is_array($params))
		foreach($params as &$param) {
			self::_walkSerializedParams($param, $callback);
		}
	}
	
	static public function setView($worker_id, $view_id, C4_AbstractViewModel $model) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$fields = array(
			'worker_id' => $worker_id,
			'view_id' => $db->qstr($view_id),
			'is_ephemeral' => !empty($model->is_ephemeral) ? 1 : 0,
			'class_name' => $db->qstr($model->class_name),
			'title' => $db->qstr($model->name),
			'options_json' => $db->qstr(json_encode($model->options)),
			'columns_json' => $db->qstr(json_encode($model->view_columns)),
			'columns_hidden_json' => $db->qstr(json_encode($model->columnsHidden)),
			'params_editable_json' => $db->qstr(json_encode($model->paramsEditable)),
			'params_required_json' => $db->qstr(json_encode($model->paramsRequired)),
			'params_default_json' => $db->qstr(json_encode($model->paramsDefault)),
			'params_hidden_json' => $db->qstr(json_encode($model->paramsHidden)),
			'render_page' => abs(intval($model->renderPage)),
			'render_total' => !empty($model->renderTotal) ? 1 : 0,
			'render_limit' => intval($model->renderLimit),
			'render_sort_by' => $db->qstr($model->renderSortBy),
			'render_sort_asc' => !empty($model->renderSortAsc) ? 1 : 0,
			'render_filters' => !empty($model->renderFilters) ? 1 : 0,
			'render_subtotals' => $db->qstr($model->renderSubtotals),
			'render_template' => $db->qstr($model->renderTemplate),
			'placeholder_labels_json' => $db->qstr(json_encode($model->placeholderLabels)),
			'placeholder_values_json' => $db->qstr(json_encode($model->placeholderValues)),
		);
		
		$db->ExecuteMaster(sprintf("REPLACE INTO worker_view_model (%s) ".
			"VALUES (%s)",
			implode(',', array_keys($fields)),
			implode(',', $fields)
		), _DevblocksDatabaseManager::OPT_NO_READ_AFTER_WRITE);
	}
	
	static public function deleteView($worker_id, $view_id) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$db->ExecuteMaster(sprintf("DELETE FROM worker_view_model WHERE worker_id = %d AND view_id = %s",
			$worker_id,
			$db->qstr($view_id)
		));
	}
	
	/**
	 * Prepares for a new session by removing ephemeral views and
	 * resetting all page cursors to the first page of the list.
	 *
	 * @param integer$worker_id
	 */
	static public function flush($worker_id=null) {
		$db = DevblocksPlatform::getDatabaseService();
		
		if($worker_id) {
			$db->ExecuteMaster(sprintf("DELETE FROM worker_view_model WHERE worker_id = %d and is_ephemeral = 1",
				$worker_id
			));
			$db->ExecuteMaster(sprintf("UPDATE worker_view_model SET render_page = 0 WHERE worker_id = %d",
				$worker_id
			));
			
		} else {
			$db->ExecuteMaster("DELETE FROM worker_view_model WHERE is_ephemeral = 1");
		}
	}
};
