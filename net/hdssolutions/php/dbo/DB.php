<?php
    namespace net\hdssolutions\php\dbo;

    use PDO;
    use PDOException;
    use DateTime;

    /**
     * Database
     *
     * @author Gilberto Ramos <ramos.amarilla@gmail.com>
     * @author Hermann D. Schimpf <hschimpf@hds-solutions.net>
     */
    final class DB {
        /**
         * Conexiones abiertas
         * @var array
         */
        private static $connections = Array();

        /**
         * Datos de conexion por defecto
         */
        private static $USER = null;
        private static $PASS = null;
        private static $HOST = 'localhost';
        private static $PORT = '3306';
        private static $DDBB = null;
        private static $TIMEOUT = 10;

        /**
         * Almacena los datos de conexion a la DDBB
         * @param String Host
         * @param String Puerto
         * @param String Usuario
         * @param String Contraseña
         * @param String Base de Datos
         */
        public static function setParams($HOST, $PORT, $USER, $PASS, $DDBB) {
        	// los datos
        	self::$HOST = $HOST !== null ? $HOST : self::$HOST;
        	self::$PORT = $PORT !== null ? $PORT : self::$PORT;
        	self::$USER = $USER !== null ? $USER : self::$USER;
        	self::$PASS = $PASS !== null ? $PASS : self::$PASS;
        	self::$DDBB = $DDBB !== null ? $DDBB : self::$DDBB;
        }

        /**
         * Almacena el timeout para las conexiones
         * @param int Timeout
         */
        public static function setTimeout($timeout) {
        	// almacenamos el timeout
        	self::$TIMEOUT = $timeout;
        }

        /**
         * Retorna una conexion a la base de datos
         * @param string Nombre de la transaccion
         * @return DB Conexion a la DB
         */
        public static function getConnection($trxName = null) {
            // si no hay transaccion usamos una clave generica (null como clave no guarda en el array)
            $trxName = $trxName === null ? 'NULL' : $trxName;
            // verificamos si la conexion existe
            if (!array_key_exists($trxName, self::$connections)) {
                // creamos la conexion
                self::$connections[$trxName] = self::newConnection();
                // verificamos si se especifico transaccion
                if ($trxName !== 'NULL')
                    // iniciamos la transaccion en la conexion
                    self::$connections[$trxName]->beginTransaction();
                // sync TZ between PHP and MySQL
                $now = new DateTime();
                $mins = $now->getOffset() / 60;
                $sign = $mins < 0 ? -1 : 1;
                $mins = abs($mins);
                $hours = floor($mins / 60);
                $mins -= $hours * 60;
                $offset = sprintf('%+d:%02d', $hours * $sign, $mins);
                self::getConnection($trxName)->query("SET time_zone='$offset'");
            }
            // retornamos la conexion
            return self::$connections[$trxName];
        }

        /**
         * Finaliza la transaccion
         * @param string Nombre de la transaccion
         * @return boolean
         */
        public static function commitTransaction($trxName) {
            // si no hay transaccion usamos una clave generica (null como clave no guarda en el array)
            $trxName = $trxName === null ? 'NULL' : $trxName;
            // verificmaos si existe la transaccion
            if (!array_key_exists($trxName, self::$connections))
                // retornamos false
                return false;
            // confirmamos la transaccion
            $result = self::$connections[$trxName]->commit();
            // iniciamos una nueva transaccion
            self::$connections[$trxName]->beginTransaction();
            // retornamos el resultado
            return $result;
        }

        /**
         * Cancela una transaccion en curso
         * @param string Nombre de la transaccion
         * @return boolean
         */
        public static function rollbackTransaction($trxName) {
            // si no hay transaccion usamos una clave generica (null como clave no guarda en el array)
            $trxName = $trxName === null ? 'NULL' : $trxName;
            // verificmaos si existe la transaccion
            if (!array_key_exists($trxName, self::$connections))
                // retornamos false
                return false;
            // confirmamos la transaccion
            $result = self::$connections[$trxName]->rollBack();
            // eliminamos la transaccion
            self::$connections[$trxName] = null;
            // eliminamos la clave
            unset(self::$connections[$trxName]);
            // retornamos el resultado
            return $result;
        }

        /**
         * Genera una nueva conexion
         */
        private static function newConnection() {
            // ruta de conexion a la base de datos
            $dsn = 'mysql:dbname=' . self::$DDBB . ';port=' . self::$PORT . ';host=' . self::$HOST;
            try {
                // creamos la conexion
                $dbc = new PDO($dsn, self::$USER, self::$PASS);
                // seteamos los errores a excepcion
                $dbc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                // seteamos el timeout de conexion a 10s
                $dbc->setAttribute(PDO::ATTR_TIMEOUT, self::$TIMEOUT);
                // seteamos los caracteres a UTF8
                $dbc->query("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
                // retornamos la conexion
                return $dbc;
            } catch (PDOException $e) {
                throw $e;
            }
        }
    }