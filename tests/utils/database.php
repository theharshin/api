<?php

use Directus\Database\Connection;

/**
 * Creates a new connection instance
 *
 * TODO: Accept parameters
 * TODO: Get this info from env/global
 *
 * @return Connection
 */
function create_db_connection()
{
    $charset = get_tests_db('charset');

    return new \Directus\Database\Connection([
        'driver' => 'Pdo_' . get_tests_db('type'),
        'host' => get_tests_db('host'),
        'port' => get_tests_db('port'),
        'database' => get_tests_db('name'),
        'username' => get_tests_db('username'),
        'password' => get_tests_db('password'),
        'charset' => $charset,
        \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        \PDO::MYSQL_ATTR_INIT_COMMAND => sprintf('SET NAMES "%s"', $charset)
    ]);
}

/**
 * Returns a static database connection
 *
 * @return Connection|null
 */
function get_db_connection()
{
    static $connection = null;

    if ($connection === null) {
        $connection = create_db_connection();
    }

    return $connection;
}

/**
 * Fill a table with a array of key values
 *
 * @param Connection $db
 * @param string $table
 * @param array $items
 */
function fill_table(Connection $db, $table, array $items)
{
    $gateway = new \Zend\Db\TableGateway\TableGateway($table, $db);

    foreach ($items as $item) {
        $gateway->insert($item);
    }
}

/**
 * @param Connection $db
 * @param string $table
 */
function truncate_table(Connection $db, $table)
{
    switch (get_platform_name($db)) {
        case 'sqlite':
            $db->execute(sprintf('DELETE FROM "%s"', $table));
            $db->execute(sprintf('DELETE FROM sqlite_sequence WHERE name = "%s"', $table));
            break;
        case 'mysql':
        default:
            $db->execute(sprintf('TRUNCATE `%s`;', $table));
    }
}

/**
 * @param Connection $db
 * @param $table
 *
 * @return bool
 */
function table_exists(Connection $db, $table)
{
    switch (get_platform_name($db)) {
        case 'sqlite':
            try {
                $db->execute('SELECT * FROM ' . $table);
                $exists = true;
            } catch (\Exception $e) {
                $exists = false;
            }
            break;
        case 'mysql':
        default:
            $query = 'SHOW TABLES LIKE "%s";';
            $result = $db->execute(sprintf($query, $table));
            $exists = $result->count() === 1;
    }

    return $exists;
}

/**
 * Checks whether a given column exists in a table
 *
 * @param Connection $db
 * @param $table
 * @param $column
 *
 * @return bool
 */
function column_exists(Connection $db, $table, $column)
{
    $query = 'SHOW COLUMNS IN `%s` LIKE "%s";';

    $result = $db->execute(sprintf($query, $table, $column));

    return $result->count() === 1;
}

/**
 * @param Connection $db
 * @param $table
 * @param array $conditions
 *
 * @return array
 */
function table_find(Connection $db, $table, array $conditions)
{
    $gateway = new \Zend\Db\TableGateway\TableGateway($table, $db);
    $result = $gateway->select($conditions);

    return $result->toArray();
}

/**
 * @param Connection $db
 * @param $table
 * @param array $conditions
 *
 * @return int
 */
function delete_item(Connection $db, $table, array $conditions)
{
    $gateway = new \Zend\Db\TableGateway\TableGateway($table, $db);

    return $gateway->delete($conditions);
}

function table_insert(Connection $db, $table, array $data)
{
    $gateway = new \Zend\Db\TableGateway\TableGateway($table, $db);

    $gateway->insert($data);
}

/**
 * @param Connection $db
 * @param string $table
 */
function drop_table(Connection $db, $table)
{
    $query = 'DROP TABLE IF EXISTS `%s`;';

    if (get_platform_name($db) === 'sqlite') {
        $query .= ' VACUUM';
    }

    $db->execute(sprintf($query, $table));

    delete_item($db, 'directus_collections', [
        'collection' => $table
    ]);
}

/**
 * @param Connection $db
 * @param string $table
 * @param int $value
 */
function reset_autoincrement(Connection $db, $table, $value = 1)
{
    switch (get_platform_name($db)) {
        case 'sqlite':
            $query = sprintf(
                'UPDATE "sqlite_sequence" SET "seq" = %s WHERE name = "%s";',
                $value, $table
            );
            break;
        case 'mysql':
        default:
            $query = sprintf(
                'ALTER TABLE `%s` AUTO_INCREMENT = %d;',
                $table, $value
            );
            break;
    }

    $db->execute($query);
}

/**
 * Resets a table to a given id
 *
 * Removes every item after the $nextId
 *
 * @param Connection $db
 * @param $table
 * @param $nextId
 */
function reset_table_id(Connection $db, $table, $nextId)
{
    $gateway = new \Zend\Db\TableGateway\TableGateway($table, $db);

    $gateway->delete(function (\Zend\Db\Sql\Delete $delete) use ($nextId) {
        $delete->where->greaterThanOrEqualTo('id', $nextId);
    });

    reset_autoincrement($db, $table, $nextId);
}

/**
 * Gets the database platform name
 *
 * @param Connection $db
 *
 * @return string
 */
function get_platform_name(Connection $db)
{
    return strtolower($db->getPlatform()->getName());
}
