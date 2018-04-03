<?php

namespace Directus\Database\Schema\Sources;

use Directus\Bootstrap;
use Directus\Database\Connection;
use Directus\Database\Schema\DataTypes;
use Directus\Database\Schema\SchemaManager;
use Directus\Util\ArrayUtils;
use Zend\Db\Metadata\Object\TableObject;
use Zend\Db\Metadata\Source\SqliteMetadata;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\NotIn;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Where;

class SQLiteSchema extends AbstractSchema
{
    /**
     * @var \Zend\Db\Metadata\Source\SqliteMetadata
     */
    protected $metadata;

    /**
     * @var Connection
     */
    protected $adapter;

    /**
     * @inheritDoc
     */
    public function __construct($adapter)
    {
        $this->adapter = $adapter;
        $this->metadata = new SqliteMetadata($this->adapter);
    }

    protected function formatTablesFromInfo($tablesObject, $directusTablesInfo)
    {
        $tables = [];
        foreach ($tablesObject as $tableObject) {
            $directusTableInfo = [];
            foreach ($directusTablesInfo as $index => $table) {
                if ($table['table_name'] == $tableObject->getName()) {
                    $directusTableInfo = $table;
                    unset($directusTablesInfo[$index]);
                }
            }

            $tables[] = $this->formatCollectionInfo($tableObject, $directusTableInfo);
        }

        return $tables;
    }

    /**
     * @param TableObject $tableObject
     * @param array $directusTableInfo
     *
     * @return array
     */
    protected function formatCollectionInfo($tableObject, $directusTableInfo)
    {
        return [
            'collection' => $tableObject->getName(),
            'item_name_template' => ArrayUtils::get($directusTableInfo, 'item_name_template'),
            'preview_url' => ArrayUtils::get($directusTableInfo, 'preview_url'),
            'hidden' => ArrayUtils::get($directusTableInfo, 'hidden', 0),
            'single' => ArrayUtils::get($directusTableInfo, 'single', 0),
            'comment' => null,
            'managed' => ArrayUtils::has($directusTableInfo, 'collection') ? true : false
        ];
    }

    public function getDirectusCollectionInfo($collectionName)
    {
        $select = new Select();
        $select->columns([
            'collection',
            'item_name_template',
            'preview_url',
            'hidden' => new Expression('IFNULL(hidden, 0)'),
            'single' => new Expression('IFNULL(single, 0)'),
            'comment'

        ]);
        $select->from(SchemaManager::COLLECTION_COLLECTIONS);

        $select->where([
            'collection' => $collectionName
        ]);

        $sql = new Sql($this->adapter);
        $statement = $sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();

        return $result->current();
    }

    /**
     * @param array $columnsInfo
     * @param array $directusColumnsInfo
     *
     * @return array
     */
    protected function formatFieldsInfo($columnsInfo, array $directusColumnsInfo)
    {
        $columns = [];

        foreach ($columnsInfo as $columnInfo) {
            $directusColumnInfo = [];
            foreach ($directusColumnsInfo as $index => $column) {
                if ($column['field'] == $columnInfo['name']) {
                    $directusColumnInfo = $column;
                    unset($directusColumnsInfo[$index]);
                }
            }

            $columns[] = $this->formatFieldInfo($columnInfo, $directusColumnInfo);
        }

        return $columns;
    }

    /**
     * @param array $columnInfo
     * @param array $directusColumnInfo
     *
     * @return array
     */
    protected function formatFieldInfo(array $columnInfo, array $directusColumnInfo)
    {
        return [
            'id' => ArrayUtils::get($directusColumnInfo, 'id'),
            'collection' => ArrayUtils::get($columnInfo, 'table'),
            'field' => ArrayUtils::get($columnInfo, 'name'),
            'type' => ArrayUtils::get($columnInfo, 'type'),
            'key' => ArrayUtils::get($columnInfo, 'key'),
            'extra' => ArrayUtils::get($columnInfo, 'extra'),
            'nullable' => ArrayUtils::get($columnInfo, 'nullable'),
            'default_value' => ArrayUtils::get($columnInfo, 'column_default'),
            'interface' => ArrayUtils::get($directusColumnInfo, 'interface'),
            'options' => ArrayUtils::get($directusColumnInfo, 'options'),
            'locked' => ArrayUtils::get($directusColumnInfo, 'locked'),
            'translation' => ArrayUtils::get($directusColumnInfo, 'translation'),
            'required' => ArrayUtils::get($directusColumnInfo, 'required', false),
            'sort' => ArrayUtils::get($directusColumnInfo, 'sort', ArrayUtils::get($columnInfo, 'ordinal_position')),
            'comment' => ArrayUtils::get($directusColumnInfo, 'comment'),
            'hidden_input' => ArrayUtils::get($directusColumnInfo, 'hidden_input', false),
            'hidden_list' => ArrayUtils::get($directusColumnInfo, 'hidden_list', false)
        ];
    }

    /**
     * Get all the columns information stored on Directus Columns table
     *
     * @param string $collectionName
     * @param array $params
     *
     * @return array
     */
    protected function getDirectusFieldsInfo($collectionName, array $params = null)
    {
        $select = new Select();
        $select->columns([
            'id',
            'collection',
            'field',
            'type',
            'interface',
            'options',
            'locked',
            'translation',
            'required',
            'sort',
            'comment',
            'hidden_input',
            'hidden_list'
        ]);
        $select->from(SchemaManager::COLLECTION_FIELDS);
        $where = new Where();
        $where->equalTo('collection', $collectionName);

        $select->where($where);
        $select->order('sort');

        $sql = new Sql($this->adapter);
        $statement = $sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();

        return iterator_to_array($result);
    }

    /**
     * @inheritDoc
     */
    public function hasColumn($tableName, $columnName)
    {
        // TODO: Implement hasColumn() method.
    }

    /**
     * @inheritDoc
     */
    public function getColumn($tableName, $columnName)
    {
        // TODO: Implement getColumn() method.
    }

    /**
     * @inheritDoc
     */
    public function hasPrimaryKey($tableName)
    {
        // TODO: Implement hasPrimaryKey() method.
    }

    /**
     * @inheritDoc
     */
    public function getPrimaryKey($tableName)
    {
        $columnName = null;

        $constraints = $this->metadata->getConstraints($tableName);
        foreach ($constraints as $constraint) {
            if ($constraint->isPrimaryKey()) {
                // @TODO: Directus should handle multiple columns
                $columns = $constraint->getColumns();
                $columnName = array_shift($columns);
                break;
            }
        }

        return $columnName;
    }


    // ----------------------------------------------------------------------------
    //
    // ----------------------------------------------------------------------------
    /**
     * @inheritDoc
     */
    public function getConnection()
    {
        return $this->adapter;
    }

    /**
     * @inheritDoc
     */
    public function getSchemaName()
    {
        // TODO: Implement getSchemaName() method.
    }

    /**
     * @inheritDoc
     */
    public function getCollections(array $params = [])
    {
        $tablesObject = $this->metadata->getTables();
        $directusTablesInfo = $this->getDirectusTablesInfo();

        return $this->formatTablesFromInfo($tablesObject, $directusTablesInfo);
    }

    /**
     * @inheritDoc
     */
    public function collectionExists($collectionName)
    {
        try {
            $this->metadata->getTable($collectionName);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getCollection($collectionName)
    {
        try {
            $collection = $this->metadata->getTable($collectionName);
        } catch (\Exception $e) {
            return [];
        }

        $directusTablesInfo = $this->getDirectusCollectionInfo($collectionName);
        if (!$directusTablesInfo) {
            $directusTablesInfo = [];
        }

        return $this->formatCollectionInfo($collection, $directusTablesInfo);
    }

    /**
     * @inheritDoc
     */
    public function getFields($collectionName, $params = null)
    {
        $columnsInfo = $this->getFieldsData($collectionName, $this->adapter->getCurrentSchema());
        $directusColumns = $this->getDirectusFieldsInfo($collectionName, $params);

        return $this->formatFieldsInfo($columnsInfo, $directusColumns);
    }

    /**
     * @inheritDoc
     */
    public function getAllFields()
    {
        // TODO: Implement getAllFields() method.
    }

    /**
     * @inheritDoc
     */
    public function hasField($collectionName, $fieldName)
    {
        // TODO: Implement hasField() method.
    }

    /**
     * @inheritDoc
     */
    public function getField($collectionName, $fieldName)
    {
        // TODO: Implement getField() method.
    }

    /**
     * @inheritDoc
     */
    public function getAllRelations()
    {
        // TODO: Implement getAllRelations() method.
    }

    /**
     * @inheritDoc
     */
    public function getRelations($collectionName)
    {
        $select = new Select();
        $select->columns([
            'id',
            'collection_a',
            'field_a',
            'junction_key_a',
            'junction_collection',
            'junction_mixed_collections',
            'junction_key_b',
            'collection_b',
            'field_b'
        ]);
        $select->from(SchemaManager::COLLECTION_RELATIONS);

        $where = $select->where->nest();
        $where->equalTo('collection_a', $collectionName);
        $where->OR;
        $where->equalTo('collection_b', $collectionName);
        $where->unnest();

        $sql = new Sql($this->adapter);
        $statement = $sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();

        return iterator_to_array($result);
    }

    /**
     * @inheritDoc
     */
    public function castValue($data, $type = null, $length = null)
    {
        // TODO: Implement castValue() method.
    }

    /**
     * @inheritDoc
     */
    public function getIntegerTypes()
    {
        // TODO: Implement getIntegerTypes() method.
    }

    /**
     * @inheritDoc
     */
    public function isIntegerType($type)
    {
        // TODO: Implement isIntegerType() method.
    }

    /**
     * @inheritDoc
     */
    public function getDecimalTypes()
    {
        // TODO: Implement getDecimalTypes() method.
    }

    /**
     * @inheritDoc
     */
    public function isDecimalType($type)
    {
        // TODO: Implement isDecimalType() method.
    }

    /**
     * @inheritDoc
     */
    public function getNumericTypes()
    {
        // TODO: Implement getNumericTypes() method.
    }

    /**
     * @inheritDoc
     */
    public function isNumericType($type)
    {
        // TODO: Implement isNumericType() method.
    }

    /**
     * @inheritDoc
     */
    public function getStringTypes()
    {
        // TODO: Implement getStringTypes() method.
    }

    /**
     * @inheritDoc
     */
    public function isStringType($type)
    {
        // TODO: Implement isStringType() method.
    }

    protected function getFieldsData($collection, $schema = null)
    {
        $columns = [];
        $rows = $this->fetchPragma('table_info', $collection, $schema);

        foreach ($rows as $row) {
            $matches = [];
            preg_match('#^([a-zA-Z]+)(\(.*\)){0,1}$#', ArrayUtils::get($row, 'type', ''), $matches);
            $dataType = $matches[1];

            $extra = null;
            if (DataTypes::isIntegerType($dataType) && $row['pk'] == 1) {
                $extra = 'auto_increment';
            }

            $columns[] = [
                'table'                     => $collection,
                'name'                      => $row['name'],
                // cid appears to be zero-based, ordinal position needs to be one-based
                'ordinal_position'          => $row['cid'] + 1,
                'column_default'            => $row['dflt_value'],
                'nullable'                  => ! ((bool) $row['notnull']),
                'type'                      => $dataType,
                'key'                       => $row['pk'] == 1 ? 'PRI' : null,
                'extra'                     => $extra,
                'character_maximum_length'  => null,
                'character_octet_length'    => null,
                'numeric_precision'         => null,
                'numeric_scale'             => null,
                'numeric_unsigned'          => null,
                'erratas'                   => [],
            ];
        }

        return $columns;
    }

    protected function fetchPragma($name, $value = null, $schema = null)
    {
        $p = $this->adapter->getPlatform();

        $sql = 'PRAGMA ';

        if (null !== $schema) {
            $sql .= $p->quoteIdentifier($schema) . '.';
        }
        $sql .= $name;

        if (null !== $value) {
            $sql .= '(' . $p->quoteTrustedValue($value) . ')';
        }

        $results = $this->adapter->execute($sql);

        return $results->toArray();
    }
}
