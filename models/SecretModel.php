<?php


class SecretModel
{
    protected mysqli $conn;

    public function __construct($connection)
    {
        $this->conn = $connection;
        $this->__create_table_if_not_exists();
    }

    private function save(string $hash, string $secret, int $remainingViews, DateTime $createdAt, DateTime $expiresAt) : bool
    {
        $sql = 'INSERT INTO secrets VALUES(?, ?, ?, ?, ?);';
        $stmt = $this->conn->prepare($sql);
        $createdAt = $createdAt->format('Y-m-d H:i:s');
        $expiresAt = $expiresAt->format('Y-m-d H:i:s');
        $stmt->bind_param('ssssd', $hash, $secret, $createdAt, $expiresAt, $remainingViews);
        $stmt->execute();
        $this->conn->commit();
        return true;
    }

    public function create(string $hash, string $secret, int $remainingViews, DateTime $createdAt, DateTime $expiresAt) : bool
    {
        if ($this->save($hash, $secret, $remainingViews, $createdAt, $expiresAt)) return true;
        return false;
    }

    public function remove($hash) : bool
    {
        $sql = "DELETE FROM secrets WHERE hash = ?;";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $this->conn->commit();
        return true;
    }

    public function all() : array
    {
        $files = [];
        $sql = "SELECT * FROM secrets;";
        $result = $this->conn->query($sql);
        if ($result->num_rows < 0) return [];
        while ($row = $result->fetch_assoc()) {
            $files[] = [
                'hash' => $row['hash'],
                'secret' => $row['secret'],
                'createdAt' => $row['createdAt'],
                'expiresAt' => $row['expiresAt'],
                'remainingViews' => $row['remainingViews']
            ];
        }
        return $files;
    }

    public function get(string $hash)
    {
        $files = [];
        $sql = 'SELECT * FROM secrets WHERE hash = ?;';
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows <= 0) return false;
        while ($row = $result->fetch_assoc()) {
            $files[] = [
                'hash' => $row['hash'],
                'secret' => $row['secret'],
                'createdAt' => $row['createdAt'],
                'expiresAt' => $row['expiresAt'],
                'remainingViews' => $row['remainingViews']
            ];
        }
        return $files[0];
    }

    public function update(string $hash, string $key, $newValue) : bool
    {
        $newValType = gettype($newValue) == 'integer' ? 'd' : 's';
        $sql = "UPDATE secrets SET $key=? WHERE hash = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($newValType."s", $newValue, $hash);
        $stmt->execute();
        $this->conn->commit();
        return true;
    }
    private function __create_table_if_not_exists() : void
    {
            $sql = 'CREATE TABLE IF NOT EXISTS secrets(
            hash VARCHAR(128) PRIMARY KEY NOT NULL,
            secret LONGTEXT NOT NULL,
            createdAt DATETIME NOT NULL,
            expiresAt DATETIME NOT NULL,
            remainingViews INTEGER(11) NOT NULL
        );';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $this->conn->commit();
    }
}