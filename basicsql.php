<?php

class BasicSQL
{
    private $db = false;
    public $_last_query = null;

    const DB_NOW = "__DBNOW__";

    // Connects to PDO as necessary
    public function __construct($host, $user, $pass, $db)
    {
        if ($this->db !== false) {
            return;
        }

        $this->db = new PDO(
            "mysql:host=" . $host . ";dbname=" . $db . ";charset=utf8mb4",
            $user,
            $pass
        );

        if (!$this->db) {
            echo "Error connecting to backend database";
            die();
        }

        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        // There appears to be some debate as to what this should be set to - I think true, less trips too/from server
        $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        $this->query("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    }

    /**
     * Handles a pdo query and returns the pdo result object.  Used by all methods
     * that calls the database systemwide.  Can accept pdo style paramaters, ex :
     *   _query("select * from a where id = :id", [':id' => 1])
     */
    private function _query($sql, $params = [])
    {
        if ($this->db === false) {
            throw new Exception("Fatal - not connected to DB");
        }

        // Handle raw value substitution, add a : to the begining of each paramater if it doesnt exist
        $newParams = [];

        foreach ($params as $index => $value) {
            if (substr($index,0,1) != ':') {
                $index = ':'.$index;
            }

            // Handle Rsdb::DB_NOW
            // if ($data === Rsdb::DB_NOW) {
            //     $sql = str_replace($index, 'NOW()', $sql);
            //     unset($params[$index]);
            // }
            //  Null values become NULLS
            if ($value === null) {
                $sql = str_replace($index, "NULL", $sql);
                continue;
            }

            //  True values become 1
            if ($value === true) {
                $value = 1;
            }
            //  False values become 0
            if ($value === false) {
                $value = 0;
            }

            $newParams[$index] = $value;
        }
        

        // Execute query
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($newParams);

            return $stmt;
        } catch (PDOException $ex) {
            // Error handler

            throw new Exception("Fatal - query - " . $ex->getMessage() . " - " . $sql . " " . json_encode($params, JSON_PRETTY_PRINT));
        }
    }

    /* Public interface for the query() function. Returns nothing. */
    public function query($sql, $params = [])
    {
        $stmt = $this->_query($sql, $params);
        $stmt->closeCursor();

        return null;
    }

    /* Executes query, returns all rows and columns as arrays. */
    /* Accepts an array of key => value params that are pdo paramaters */
    public function fetch_all($sql, $params = [])
    {
        $stmt = $this->_query($sql, $params);
        try {
            $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $rs = null;
        }
        $stmt->closeCursor();
        return $rs;
    }

    /* Executes query, returns a single row. */
    /* Accepts an array of key => value params that are pdo paramaters */
    public function fetch($sql, $params = [])
    {
        $stmt = $this->_query($sql, $params);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $rs;
    }

    /* Executes query, returns a single value (the first column returned). */
    /* Accepts an array of key => value params that are pdo paramaters */
    public function fetch_one($sql, $params = [], $index = null)
    {
        $rs = $this->fetch($sql, $params);

        if (is_array($rs) && isset($rs[$index])) {
            return $rs[$index];
        } else if (is_array($rs) &&count($rs) > 0) {
            return current($rs);
        } else {
            return null;
        }
    }

    // Returns the id of the last inserted record
    public function last_insert_id()
    {
        return $this->db->lastInsertId() * 1;
    }

    // Returns last executed query, only works in debug mode
    public function last_query()
    {
        return $this->_last_query;
    }
}
