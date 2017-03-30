<?php
namespace MysqlToGoogleBigQuery\Database;

use Doctrine\DBAL\Types\Type;

class Mysql
{
    protected $conn;

    /**
     * Configure and connect to MySQL Database
     * @param  string $databaseName      Database name
     * @return Doctrine\DBAL\Connection  Doctrine DBAL Connection
     */
    public function getConnection(string $databaseName)
    {
        // If we are connected, just return the last connection
        if ($this->conn) {
            return $this->conn;
        }

        $config = new \Doctrine\DBAL\Configuration();

        $connParams = array(
            'dbname' => $databaseName,
            'user' => $_ENV['DB_USERNAME'],
            'password' => $_ENV['DB_PASSWORD'],
            'host' => $_ENV['DB_HOST'],
            'charset'  => 'utf8',
            // Special doctrine driver, with reconnect attempts support
            'wrapperClass' => 'Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection',
            'driverClass' => 'Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDOMySql\Driver',
            'driverOptions' => [
                'x_reconnect_attempts' => 9
            ]
        );

        $this->conn = \Doctrine\DBAL\DriverManager::getConnection($connParams, $config);

        // Replace the DateTime conversion
        Type::addType('bigquerydatetime', 'MysqlToGoogleBigQuery\Doctrine\BigQueryDateTimeType');
        Type::addType('bigquerydate', 'MysqlToGoogleBigQuery\Doctrine\BigQueryDateType');

        // Map types to classes
        $this->conn->getDatabasePlatform()->registerDoctrineTypeMapping('date', 'bigquerydate');
        $this->conn->getDatabasePlatform()->registerDoctrineTypeMapping('datetime', 'bigquerydatetime');
        $this->conn->getDatabasePlatform()->registerDoctrineTypeMapping('timestamp', 'bigquerydatetime');

        // Add support for MySQL 5.7 JSON type
        $this->conn->getDatabasePlatform()->registerDoctrineTypeMapping('json', 'text');
        $this->conn->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'text');

        return $this->conn;
    }

    /**
     * Get the number of rows on a table
     * @param  string $databaseName Database name
     * @param  string $tableName    Table name
     * @return int                  Number of rows
     */
    public function getCountTableRows(string $databaseName, string $tableName)
    {
        $mysqlQueryResult = $this->getConnection($databaseName)->query('SELECT COUNT(*) AS count FROM `' . $tableName . '`');

        while ($row = $mysqlQueryResult->fetch()) {
            return (int) $row['count'];
        }

        throw new \Exception('Mysql table ' . $tableName . ' not found');
    }

    /**
     * Return the table columns
     * @param  string $databaseName Database name
     * @param  string $tableName    Table name
     * @return array                Array of Doctrine\DBAL\Schema\Column
     */
    public function getTableColumns($databaseName, $tableName)
    {
        $mysqlConnection = $this->getConnection($databaseName);
        $mysqlPlatform = $mysqlConnection->getDatabasePlatform();
        $mysqlSchemaManager = $mysqlConnection->getSchemaManager();

        $mysqlTableDetails = $mysqlSchemaManager->listTableDetails($tableName);
        return $mysqlTableDetails->getColumns();
    }
}
