<?php
    /*/
	 * Project Name:    Wingman — Database — Connection Manager
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 22 2026
	 * Last Modified:   Jan 26 2026
    /*/

    # Use the Database namespace.
    namespace Wingman\Database;

    # Import the following classes to the current scope.
    use FilesystemIterator;
    use InvalidArgumentException;
    use Wingman\Database\Interfaces\SQLDialect;
    use Wingman\Database\Interfaces\SQLDriver;

    /**
     * Represents a manager for database connections.
     * @package Wingman\Database
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ConnectionManager {
        /**
         * The default connection name.
         * @var string
         */
        public const DEFAULT_CONNECTION_NAME = "default";

        /**
         * Connection counter for unique connection naming.
         * @var int
         */
        protected static int $connectionCounter = 0;

        /**
         * The configuration for a connection manager.
         * @var array
         */
        protected array $config = [];

        /**
         * The configuration for a connection manager's connections.
         * @var array
         */
        protected array $connectionConfigMap = [];

        /**
         * The active connection instances.
         * @var array<string, Connection>
         */
        protected array $connections = [];

        /**
         * Configuration for registered dialects.
         * @var array<string, array>
         */
        protected array $dialectConfigMap = [];

        /**
         * Registry of dialect classes.
         * @var array<string, class-string<SQLDialect>>
         */
        protected array $dialectRegistry = [];

        /**
         * Discovered components cache.
         * @var array<string, array>|null
         */
        protected static ?array $discoveredComponents = null;

        /**
         * Configuration for registered drivers.
         * @var array<string, array>
         */
        protected array $driverConfigMap = [];

        /**
         * Registry of driver classes.
         * @var array<string, class-string<SQLDriver>>
         */
        protected array $driverRegistry = [];

        /**
         * Injected SQL dialect instance.
         * @var SQLDialect|null
         */
        protected ?SQLDialect $injectedDialect = null;

        /**
         * Injected SQL driver instance.
         * @var SQLDriver|null
         */
        protected ?SQLDriver $injectedDriver = null;

        /**
         * Creates a new connection manager.
         * @param array $connectionConfigMap The configuration map for connections.
         * @param array $config Optional configuration for the manager.
         */
        public function __construct (array $connectionConfigMap = [], array $config = []) {
            $this->connectionConfigMap = $connectionConfigMap;
            $this->config = $config;

            $this->registerDefaults();

            register_shutdown_function([$this, "shutDown"]);
        }
        
        /**
         * Destroys a connection manager and shuts down all active connections.
         */
        public function __destruct () {
            $this->shutDown();
        }

        /**
         * Creates a new connection based on the configuration passed to a manager or to this method.
         * @param string $name The name of the connection to create.
         * @param array|null $config Optional configuration to override the manager's configuration.
         * @return Connection The created connection.
         * @throws InvalidArgumentException If the connection is not configured.
         */
        protected function makeConnection (string $name, ?array $config = null) : Connection {
            $currentConfig = $this->connectionConfigMap[$name] ?? [];

            if (!isset($config)) $config = $currentConfig;
            else $config = array_merge($currentConfig, $config);

            if (empty($config)) {
                throw new InvalidArgumentException("Database connection [$name] not configured.");
            }

            $dialect = $config["dialect"] ?? null;
            $dialectClass = $this->dialectRegistry[$dialect] ?? null;

            if (!isset($dialectClass)) {
                throw new InvalidArgumentException("Database dialect [{$config["dialect"]}] not registered.");
            }

            $dialect = $this->injectedDialect && $this->injectedDialect::class === $dialectClass
                ? clone $this->injectedDialect
                : new $dialectClass(...($this->dialectConfigMap[$dialect] ?? []));
            
            $driver = $config["driver"] ?? null;
            $driverClass = $this->driverRegistry[$driver] ?? null;

            if (!isset($driverClass)) {
                throw new InvalidArgumentException("Database driver [{$config["driver"]}] not registered.");
            }

            if ($this->injectedDriver && $this->injectedDriver::class === $driverClass) {
                $driver = clone $this->injectedDriver;
            }
            else {
                $driverConfig = array_merge($this->connectionConfigMap[$name] ?? [], $this->driverConfigMap[$driver] ?? []);
                $driver = new $driverClass($dialect, $driverConfig);
            }
            
            return new Connection($driver, $dialect, $config);
        }

        /**
         * Registers default drivers and dialects by scanning the respective directories.
         * @return static The connection manager.
         */
        protected function registerDefaults () : static {
            if (self::$discoveredComponents === null) {
                self::$discoveredComponents = [
                    "drivers" => [],
                    "dialects" => []
                ];
        
                $baseDir = __DIR__;
                $scan = [
                    "drivers" => "$baseDir/drivers/",
                    "dialects" => "$baseDir/dialects/"
                ];
        
                foreach ($scan as $type => $path) {
                    if (!is_dir($path)) continue;
        
                    $iterator = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
                    
                    foreach ($iterator as $file) {
                        if ($file->getExtension() !== "php") continue;
        
                        $filename = $file->getBasename(".php");
                        require_once $file->getPathname();
        
                        $key = strtolower(str_replace(["Driver", "Dialect"], "", $filename));
                        
                        $fullClassName = __NAMESPACE__ . DIRECTORY_SEPARATOR . ucfirst($type) . DIRECTORY_SEPARATOR . $filename;
        
                        if (class_exists($fullClassName)) {
                            self::$discoveredComponents[$type][$key] = $fullClassName;
                        }
                    }
                }
            }
        
            foreach (self::$discoveredComponents["dialects"] as $name => $class) {
                $this->registerDialect($name, $class);
            }
        
            foreach (self::$discoveredComponents["drivers"] as $name => $class) {
                $this->registerDriver($name, $class);
            }
        
            return $this;
        }

        /**
         * Asserts that a dialect is supported.
         * @param string $name The name of the dialect.
         * @throws InvalidArgumentException If the dialect is not supported.
         */
        public function assertDialectSupported (string $name) : void {
            if (!isset($this->dialectRegistry[$name])) {
                throw new InvalidArgumentException("Unsupported dialect: $name");
            }
        }

        /**
         * Asserts that a driver is supported.
         * @param string $name The name of the driver.
         * @throws InvalidArgumentException If the driver is not supported.
         */
        public function assertDriverSupported (string $name) : void {
            if (!isset($this->driverRegistry[$name])) {
                throw new InvalidArgumentException("Unsupported driver: $name");
            }
        }

        /**
         * Clones an existing connection configuration to a new name.
         * @param string $name The name of the existing connection.
         * @param string $newName The name of the new connection.
         * @return static The connection manager.
         * @throws InvalidArgumentException If the existing connection is not configured.
         */
        public function cloneConfig (string $name, string $newName) : static {
            if (!isset($this->connectionConfigMap[$name])) {
                throw new InvalidArgumentException("Database connection [$name] not configured.");
            }

            $this->connectionConfigMap[$newName] = $this->connectionConfigMap[$name];
            return $this;
        }

        /**
         * Gets an existing connection by name or creates a new one.
         * @param string|null $name The name of the connection. If `null`, the default connection is used.
         */
        public function getConnection (?string $name = null) : Connection {
            $name = $name ?: $this->getDefaultConnectionName();
            if (!isset($this->connections[$name])) {
                $this->connections[$name] = $this->makeConnection($name);
            }
            return $this->connections[$name];
        }

        /**
         * Gets all connections of a manager.
         * @return array<string, Connection> The connections.
         */
        public function getConnections (): array {
            return $this->connections;
        }

        /**
         * Gets the default connection name from the configuration.
         * @return string The default connection name.
         */
        public function getDefaultConnectionName () : string {
            if (isset($this->config["default"])) return $this->config["default"];

            if (count($this->connectionConfigMap) === 1) {
                return (string) array_key_first($this->connectionConfigMap);
            }

            return self::DEFAULT_CONNECTION_NAME;
        }

        /**
         * Gets the open connections of a manager.
         * @return array<string, Connection> The open connections.
         */
        public function getOpenConnections () : array {
            return array_filter($this->connections, fn ($connection) => $connection->isOpen());
        }

        /**
         * Injects a SQL dialect instance to be used by all connections.
         * @param SQLDialect $dialect The SQL dialect instance.
         * @return static The connection manager.
         */
        public function injectDialect (SQLDialect $dialect) : static {
            $this->injectedDialect = $dialect;
            return $this;
        }

        /**
         * Injects a SQL driver instance to be used by all connections.
         * @param SQLDriver $driver The SQL driver instance.
         * @return static The connection manager.
         */
        public function injectDriver (SQLDriver $driver) : static {
            $this->injectedDriver = $driver;
            return $this;
        }

        /**
         * Refreshes an existing connection by name or creates a new one.
         * @param string $name The name of the connection.
         * @return Connection The refreshed connection.
         */
        public function refreshConnection (string $name) : Connection {
            if (isset($this->connections[$name])) {
                $this->connections[$name]->getDriver()->disconnect();
                unset($this->connections[$name]);
            }
            return $this->getConnection($name);
        }

        /**
         * Registers a new connection configuration.
         * @param string $name The name of the connection.
         * @param array $config The configuration for the connection.
         * @return static The connection manager.
         */
        public function registerConfig (string $name, array $config) : static {
            $this->connectionConfigMap[$name] = $config;
            return $this;
        }

        /**
         * Registers a new SQL dialect class.
         * @param string $name The name of the dialect.
         * @param class-string<SQLDialect> $dialectClass The class name of the dialect.
         * @param array $config Optional configuration for the dialect.
         * @return static The connection manager.
         */
        public function registerDialect (string $name, string $dialectClass, array $config = []) : static {
            $this->dialectRegistry[$name] = $dialectClass;
            $this->dialectConfigMap[$name] = $config;
            return $this;
        }

        /**
         * Registers multiple SQL dialect classes.
         * @param array<string, class-string<SQLDialect>> $dialects An associative array of dialect names and their class names.
         * @return static The connection manager.
         */
        public function registerDialects (array $dialects) : static {
            foreach ($dialects as $name => $dialectClass) {
                $this->registerDialect($name, $dialectClass);
            }
            return $this;
        }

        /**
         * Registers a new SQL driver class.
         * @param string $name The name of the driver.
         * @param class-string<SQLDriver> $driverClass The class name of the driver.
         * @param array $config Optional configuration for the driver.
         * @return static The connection manager.
         */
        public function registerDriver (string $name, string $driverClass, array $config = []) : static {
            $this->driverRegistry[$name] = $driverClass;
            $this->driverConfigMap[$name] = $config;
            return $this;
        }

        /**
         * Registers multiple SQL driver classes.
         * @param array<string, class-string<SQLDriver>> $drivers An associative array of driver names and their class names.
         * @return static The connection manager.
         */
        public function registerDrivers (array $drivers) : static {
            foreach ($drivers as $name => $driverClass) {
                $this->registerDriver($name, $driverClass);
            }
            return $this;
        }

        /**
         * Sets the default connection name of a manager.
         * @param string $name The name of the connection.
         * @return static The connection manager.
         */
        public function setDefaultConnection (string $name) : static {
            $this->config["default"] = $name;
            return $this;
        }

        /**
         * Dereferences all active connections of a manager to allow for graceful shutdown.
         * @return static The connection manager.
         */
        public function shutDown () : static {
            $this->connections = [];
            return $this;
        }

        /**
         * Checks whether a dialect is supported.
         * @param string $name The name of the dialect.
         * @return bool Whether the dialect is supported.
         */
        public function supportsDialect (string $name) : bool {
            return isset($this->dialectRegistry[$name]);
        }

        /**
         * Checks whether a driver is supported.
         * @param string $name The name of the driver.
         * @return bool Whether the driver is supported.
         */
        public function supportsDriver (string $name) : bool {
            return isset($this->driverRegistry[$name]);
        }
    }
?>