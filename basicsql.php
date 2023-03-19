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

        // Handle raw value substitution
        foreach ($params as $index => $data) {
            // Handle Rsdb::DB_NOW
            // if ($data === Rsdb::DB_NOW) {
            //     $sql = str_replace($index, 'NOW()', $sql);
            //     unset($params[$index]);
            // }
            //  Null values become NULLS
            if ($data === null) {
                $sql = str_replace($index, "NULL", $sql);
                unset($params[$index]);
            }
            //  True values become 1
            if ($data === true) {
                $params[$index] = 1;
            }
            //  False values become 0
            if ($data === false) {
                $params[$index] = 0;
            }
        }

        // Execute query
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt;
        } catch (PDOException $ex) {
            // Error handler

            // Roll back transaction if we are in a transaction
            if ($this->in_transaction) {
                $stmt2 = $this->db->prepare("ROLLBACK");
                $stmt2->execute();
                $stmt2->closeCursor();
                $this->in_transaction = false;
            }

            throw new Exception("Fatal - query - " . $ex->getMessage() . " - " . $sql . " " . json($params));
        }
    }

    /* Public interface for the query() function. Returns nothing. */
    public function query($sql, $params = [])
    {
        global $CONFIG;

        $stmt = $this->_query($sql, $params);
        $stmt->closeCursor();

        return null;
    }

    /* Executes query, returns all rows and columns as arrays. */
    /* Accepts an array of key => value params that are pdo paramaters */
    public function fetch_all($sql, $params = [])
    {
        global $CONFIG;

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
        global $CONFIG;

        $stmt = $this->_query($sql, $params);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $rs;
    }

    /* Executes query, returns a single value (the first column returned). */
    /* Accepts an array of key => value params that are pdo paramaters */
    public function fetch_one($sql, $params = [], $index = null)
    {
        global $CONFIG;

        $rs = $this->fetch($sql, $params);

        if (is_array($rs) && count($rs) > 0) {
            // var_dump(current($rs));
            // die();
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
