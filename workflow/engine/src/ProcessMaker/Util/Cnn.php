<?php

namespace ProcessMaker\Util;

use Propel;

/**
 * Class Cnn
 * @package ProcessMaker\Util
 */
class Cnn
{
    private $dbFile;
    private $workspace;

    /**
     * Establishes connection for the workspace
     * @param string $workspace Name workspace
     */
    public static function connect($workspace)
    {
        $cnn = new static();
        $cnn->workspace = $workspace;
        Propel::initConfiguration($cnn->buildParams());
    }

    /**
     * Loads the parameters required to connect to each workspace database
     * @return array
     */
    public function buildParams()
    {
        if ($this->readFileDBWorkspace()) {
            return $this->prepareDataSources();
        }
        return [];
    }

    /**
     * Reads the workspace db.php file
     * @return bool
     */
    private function readFileDBWorkspace()
    {
        if (file_exists(PATH_DB . $this->workspace . PATH_SEP . 'db.php')) {
            $this->dbFile = file_get_contents(PATH_DB . $this->workspace . PATH_SEP . 'db.php');
            return true;
        }
        return false;
    }

    /**
     * Prepares data resources
     * @return array
     */
    private function prepareDataSources()
    {
        $credentials = $this->parseDatabaseDefinitions($this->dbFile);

        $dataSources = [];
        $dataSources['datasources'] = array(
            'workflow' => array(
                'connection' => $this->buildDsnString(
                    $this->databaseDefinition($credentials, 'DB_ADAPTER'),
                    $this->databaseDefinition($credentials, 'DB_HOST'),
                    $this->databaseDefinition($credentials, 'DB_NAME'),
                    $this->databaseDefinition($credentials, 'DB_USER'),
                    urlencode($this->databaseDefinition($credentials, 'DB_PASS'))
                ),
                'adapter' => "mysql"
            ),
            'rbac' => array(
                'connection' => $this->buildDsnString(
                    $this->databaseDefinition($credentials, 'DB_ADAPTER'),
                    $this->databaseDefinition($credentials, 'DB_RBAC_HOST'),
                    $this->databaseDefinition($credentials, 'DB_RBAC_NAME'),
                    $this->databaseDefinition($credentials, 'DB_RBAC_USER'),
                    urlencode($this->databaseDefinition($credentials, 'DB_RBAC_PASS'))
                ),
                'adapter' => "mysql"
            ),
            'report' => array(
                'connection' => $this->buildDsnString(
                    $this->databaseDefinition($credentials, 'DB_ADAPTER'),
                    $this->databaseDefinition($credentials, 'DB_REPORT_HOST'),
                    $this->databaseDefinition($credentials, 'DB_REPORT_NAME'),
                    $this->databaseDefinition($credentials, 'DB_REPORT_USER'),
                    urlencode($this->databaseDefinition($credentials, 'DB_REPORT_PASS'))
                ),
                'adapter' => "mysql"
            )
        );
        return $dataSources;
    }

    /**
     * Parses the workspace db.php define() statements without executing the
     * file as PHP.
     *
     * @param string $dbFile
     * @return array
     */
    private function parseDatabaseDefinitions($dbFile)
    {
        $credentials = [];
        if (preg_match_all('/define\s*\(\s*[\x22\x27]([^"\']+)[\x22\x27]\s*,\s*([\x22\x27])((?:\\\\.|(?!\2).)*)\2\s*\)\s*;/i', $dbFile, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $credentials[$match[1]] = stripcslashes($match[3]);
            }
        }
        return $credentials;
    }

    /**
     * @param array $credentials
     * @param string $name
     * @return string
     */
    private function databaseDefinition(array $credentials, $name)
    {
        return isset($credentials[$name]) ? $credentials[$name] : '';
    }

    /**
     * Builds the DSN string to be used by PROPEL
     * @param string $adapter
     * @param string $host
     * @param string $name
     * @param string $user
     * @param string $pass
     * @return string
     */
    private function buildDsnString($adapter, $host, $name, $user, $pass)
    {
        $dns = $adapter . "://" . $user . ":" . $pass . "@" . $host . "/" . $name;
        switch ($adapter) {
            case 'mysql':
                $dns .= '?encoding=utf8';
                break;
        }
        return $dns;
    }
}
