<?php
/**
 * Joomla! 3.0 component Collector - search plugin
 *
 * @package 	Collector
 * @copyright   Copyright (C) 2010 - 2015 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Collector is a Multi Purpose Listing Tool.
 * Originaly developped to list Collections
 * it can be used for several purpose.
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

require_once(JPATH_ROOT.'/administrator/components/com_collector/classes/field.php');

/**
 * Collector search plugin.
 */
class plgSearchCollector extends JPlugin
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  3.1
	 */
	protected $autoloadLanguage = true;

	/**
	 * Determine areas searchable by this plugin.
	 *
	 * @return  array  An array of search areas.
	 *
	 * @since   1.6
	 */
	public function onContentSearchAreas()
	{
		static $areas = array(
			'collector' => 'PLG_SEARCH_COLLECTOR_COLLECTOR'
		);

		return $areas;
	}

	/**
	 * Method to get a list of collections.
	 *
	 * @return	mixed	An array of data items on success, false on failure.
	 */
	protected function getCollections()
	{
		// Create a new query object.
		$db = JFactory::getDbo();
		$user = JFactory::getUser();
		$app = JFactory::getApplication();
		$groups = implode(',', $user->getAuthorisedViewLevels());

		$jnow		= JFactory::getDate();
		$now		= $jnow->toSql();
		$nullDate	= $db->getNullDate();

		$rows = array();
		$query = $db->getQuery(true);
		
		// Select the required fields from the table.
		$query->select('c.id');
		$query->from('#__collector AS c');
		
		// Filter by access level.
		$query->where('c.access IN ('.$groups.')');
		
		// Filter by published state
		$query->where('( c.created_by = ' . (int) $user->id . ' OR ( c.state = 1 AND ( c.publish_up = '.$db->Quote($nullDate).' OR c.publish_up <= '.$db->Quote($now).' ) AND ( c.publish_down = '.$db->Quote($nullDate).' OR c.publish_down >= '.$db->Quote($now).' ) ) )');
		
		// Add the list ordering clause.
		$query->order('c.name ASC');
		
		$db->setQuery($query);
		$collections = $db->loadObjectList();

		return $collections;
	}

	/**
	 * Method to search in collections.
	 *
	 * @param   string  $text      Target search string.
	 * @param   string  $phrase    Matching option (possible values: exact|any|all).  Default is "any".
	 * @param   string  $ordering  Ordering option (possible values: newest|oldest|popular|alpha|category).  Default is "newest".
	 * @param   string  $limit     Limit option.  Default is "50".
	 *
	 * @return	mixed	An array of data items on success, false on failure.
	 */
	protected function searchCollections($text, $phrase, $ordering, $limit)
	{
		// Create a new query object.
		$db = JFactory::getDbo();
		$user = JFactory::getUser();
		$app = JFactory::getApplication();
		$groups = implode(',', $user->getAuthorisedViewLevels());
		
		$jnow		= JFactory::getDate();
		$now		= $jnow->toSql();
		$nullDate	= $db->getNullDate();
		
		$text = trim($text);

		if ($text == '')
		{
			return array();
		}

		switch ($phrase)
		{
			case 'exact':
				$text = $db->quote('%' . $db->escape($text, true) . '%', false);
				$wheres2 = array();
				$wheres2[] = 'c.name LIKE ' . $text;
				$wheres2[] = 'c.description LIKE ' . $text;
				$where = '(' . implode(') OR (', $wheres2) . ')';
				break;

			case 'all':
			case 'any':
			default:
				$words = explode(' ', $text);
				$wheres = array();

				foreach ($words as $word)
				{
					$word = $db->quote('%' . $db->escape($word, true) . '%', false);
					$wheres2 = array();
					$wheres2[] = 'c.name LIKE ' . $word;
					$wheres2[] = 'c.description LIKE ' . $word;
					$wheres[] = implode(' OR ', $wheres2);
				}

				$where = '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
				break;
		}
		
		switch ($ordering)
		{
			case 'alpha':
				$order = 'c.name ASC';
				break;

			case 'newest':
				$order = 'c.created DESC';
				break;

			case 'oldest':
				$order = 'c.created ASC';
				break;

			case 'category':
			case 'popular':
			default:
				$order = 'c.name ASC';
		}
		
		$rows = array();
		$query = $db->getQuery(true);
		
		// Select the required fields from the table.
		$case_when = ' CASE WHEN ';
		$case_when .= $query->charLength('c.alias', '!=', '0');
		$case_when .= ' THEN ';
		$a_id = $query->castAsChar('c.id');
		$case_when .= $query->concatenate(array($a_id, 'c.alias'), ':');
		$case_when .= ' ELSE ';
		$case_when .= $a_id . ' END as slug';
		$query->select(
			'c.id, c.name AS title, c.description AS text,  c.created AS created, \'2\' AS browsernav, ' . $case_when
		);
		$query->from('#__collector AS c')
			->where($where)
			->group('c.id, c.name, c.description, c.alias');
		
		// Filter by access level.
		$query->where('c.access IN ('.$groups.')');
		
		// Filter by published state
		$query->where('( c.created_by = ' . (int) $user->id . ' OR ( c.state = 1 AND ( c.publish_up = '.$db->Quote($nullDate).' OR c.publish_up <= '.$db->Quote($now).' ) AND ( c.publish_down = '.$db->Quote($nullDate).' OR c.publish_down >= '.$db->Quote($now).' ) ) )');
		
		// Add the list ordering clause.
		$query->order($order);
		
		$db->setQuery($query, 0, $limit);
		$collections = $db->loadObjectList();

		return $collections;
	}

	/**
	 * Method to get a list of fields from a collection.
	 *
	 * @param   int     $collection  Collection id.
	 *
	 * @return	mixed	An array of data items on success, false on failure.
	 */
	protected function getFields($collection)
	{
		// Create a new query object.
		$db = JFactory::getDbo();
		$user = JFactory::getUser();
		$app = JFactory::getApplication();
		$groups = implode(',', $user->getAuthorisedViewLevels());
		
		$jnow		= JFactory::getDate();
		$now		= $jnow->toSql();
		$nullDate	= $db->getNullDate();
		
		$query = $db->getQuery(true);
		
		// Select the required fields from the table.
		$query->select('f.*, u.name AS author');
		$query->from('#__collector_fields AS f');
		
		// Join over the type.
		$query->select('t.type AS type');
		$query->join('LEFT', '#__collector_fields_type AS t ON t.id = f.type');
		
		$query->join('LEFT', '#__users AS u ON u.id = f.created_by');
		$query->where('collection = ' . $collection);
		
		$query->where('( f.created_by = ' . (int) $user->id . ' OR ( f.state = 1 AND ( f.publish_up = '.$db->Quote($nullDate).' OR f.publish_up <= '.$db->Quote($now).' ) AND ( f.publish_down = '.$db->Quote($nullDate).' OR f.publish_down >= '.$db->Quote($now).' ) ) )');
		
		// Filter by access level.
		$query->where('f.access IN ('.$groups.')');
		
		// Add the list ordering clause.
		$query->order('f.ordering ASC');
		
		$db->setQuery($query);
		$results = $db->loadObjectList();
		
		if ( ! $results ) {
			return false;
		}
		
		$fields = array();
		
		foreach ($results as $field)
		{
			$registry = new JRegistry;
			$registry->loadString($field->attribs);
			$field->attribs = $registry->toArray();
			$fields[] = CollectorField::getInstance( $collection, $field );
		}
		
		return $fields;
	}
	
	/**
	 * Search content (collector).
	 *
	 * The SQL must return the following fields that are used in a common display
	 * routine: href, title, section, created, text, browsernav.
	 *
	 * @param   string  $text      Target search string.
	 * @param   string  $phrase    Matching option (possible values: exact|any|all).  Default is "any".
	 * @param   string  $ordering  Ordering option (possible values: newest|oldest|popular|alpha|category).  Default is "newest".
	 * @param   mixed  $areas     An array if the search is to be restricted to areas or null to search all areas.
	 *
	 * @return  array  Search results.
	 */
	public function onContentSearch($text, $phrase = '', $ordering = '', $areas = null)
	{
		$db = JFactory::getDbo();
		$user = JFactory::getUser();
		$app = JFactory::getApplication();
		$groups = implode(',', $user->getAuthorisedViewLevels());

		require_once JPATH_SITE . '/components/com_collector/helpers/route.php';
		
		$jnow		= JFactory::getDate();
		$now		= $jnow->toSql();
		$nullDate	= $db->getNullDate();

		if (is_array($areas))
		{
			if (!array_intersect($areas, array_keys($this->onContentSearchAreas())))
			{
				return array();
			}
		}

		$sCollections = $this->params->get('search_collections', 1);
		$sItems = $this->params->get('search_items', 1);
		$limit = $this->params->def('search_limit', 50);
		$state = array();

		$text = trim($text);

		if ($text == '')
		{
			return array();
		}

		switch ($ordering)
		{
			case 'alpha':
				$order = 'i.fulltitle ASC';
				break;

			case 'category':
				$order = 'c.name ASC';
				break;

			case 'popular':
				$order = 'i.hits DESC';
				break;

			case 'newest':
				$order = 'i.created DESC';
				break;

			case 'oldest':
				$order = 'i.created ASC';
				break;

			default:
				$order = 'i.fulltitle ASC';
		}

		$query = $db->getQuery(true);

		// SQLSRV changes.
		$case_when = ' CASE WHEN ';
		$case_when .= $query->charLength('i.alias', '!=', '0');
		$case_when .= ' THEN ';
		$i_id = $query->castAsChar('i.id');
		$case_when .= $query->concatenate(array($i_id, 'i.alias'), ':');
		$case_when .= ' ELSE ';
		$case_when .= $i_id . ' END as slug';

		$case_when1 = ' CASE WHEN ';
		$case_when1 .= $query->charLength('c.alias', '!=', '0');
		$case_when1 .= ' THEN ';
		$c_id = $query->castAsChar('c.id');
		$case_when1 .= $query->concatenate(array($c_id, 'c.alias'), ':');
		$case_when1 .= ' ELSE ';
		$case_when1 .= $c_id . ' END as catslug';

		$listCollections = $this->getCollections();

		$results = array();

		if ($sCollections)
		{
			$collections = $this->searchCollections($text, $phrase, $ordering, $limit);
			if (isset($collections))
			{
				foreach ($collections as $key => $item)
				{
					$collections[$key]->href = CollectorHelperRoute::getCollectionRoute($item->slug);
					$collections[$key]->section = JText::_('PLG_SEARCH_COLLECTOR_COLLECTOR');
				}
			}
			$results = array_merge($results, (array) $collections);
		}

		if ($sItems)
		{
			foreach($listCollections AS $collection)
			{
				$query->clear();
				
				$fields = $this->getFields($collection->id);

				switch ($phrase)
				{
					case 'exact':
						$wheres2 = array();
						foreach($fields as $field)
						{
							$wheres2[] = $field->getSearchWhereClause($query, $text);
						}
						$where = '(' . implode(') OR (', $wheres2) . ')';
						break;

					case 'all':
					case 'any':
					default:
						$words = explode(' ', $text);
						$wheres = array();

						foreach ($words as $word)
						{
							$wheres2 = array();
							foreach($fields as $field)
							{
								$wheres2[] = $field->getSearchWhereClause($query, $word);
							}
							$wheres[] = implode(' OR ', $wheres2);
						}

						$where = '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
						break;
				}

				$query->select('i.fulltitle AS title, i.fulltitle AS text, i.created AS created, \'2\' AS browsernav')
					->select('c.id AS catid, c.name AS section, ' . $case_when . ',' . $case_when1)
					->from('#__collector_items AS i')
					->join('INNER', '#__collector AS c ON c.id=i.collection')
					->where('( i.created_by = ' . (int) $user->id . ' OR ( i.state = 1 AND ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$db->Quote($now).' ) AND ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$db->Quote($now).' ) ) )')
					->group('i.id, i.fulltitle, i.alias')
					->order($order);

				// Join over the values.
				$query->join('LEFT', '#__collector_items_history_'.$collection->id.' AS h ON h.item = i.id');
				foreach($fields as $field)
				{
					$field->setQuery($query);
				}

				// Filter by history state.
				$query->where('h.state = 1');

				// Filter by access level.
				$query->where('i.access IN ('.$groups.')');

				// Search.
				$query->where( $where );

				$db->setQuery($query, 0, $limit);
				$list = $db->loadObjectList();

				if (isset($list))
				{
					foreach ($list as $key => $item)
					{
						$list[$key]->href = CollectorHelperRoute::getItemRoute($item->slug, $item->catid);
						$list[$key]->text = '';
						foreach($fields as $field)
						{
							if ( $field->_field->listing == 1 )
							{
								$list[$key]->text .= ' ';
								$tablecolumn = $field->_field->tablecolumn;
								$list[$key]->text .= $field->display($list[$key]->$tablecolumn);
							}
						}
					}
				}
				$results = array_merge($results, (array) $list);
			}
		}

		return $results;
	}
}
