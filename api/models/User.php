<?php
// User Model
class User {
    private $db;
    private $table = 'users';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getById($id) {
        $id = intval($id);
        $query = "SELECT id, username, email, full_name, created_at, last_login, is_active FROM {$this->table} WHERE id = {$id}";
        $result = $this->db->query($query);

        if ($result->num_rows == 0) {
            return null;
        }

        return $result->fetch_assoc();
    }

    public function getByEmail($email) {
        $email = $this->db->real_escape_string($email);
        $query = "SELECT * FROM {$this->table} WHERE email = '$email'";
        $result = $this->db->query($query);
        
        if ($result->num_rows == 0) {
            return null;
        }
        
        return $result->fetch_assoc();
    }

    public function getByUsername($username) {
        $username = $this->db->real_escape_string($username);
        $query = "SELECT * FROM {$this->table} WHERE username = '$username'";
        $result = $this->db->query($query);
        
        if ($result->num_rows == 0) {
            return null;
        }
        
        return $result->fetch_assoc();
    }

    public function create($data) {
        $username = $this->db->real_escape_string($data['username']);
        $email = $this->db->real_escape_string($data['email']);
        $password = password_hash($data['password'], PASSWORD_BCRYPT);
        $fullName = $this->db->real_escape_string($data['fullName'] ?? '');

        $query = "INSERT INTO {$this->table} (username, email, password_hash, full_name)
                  VALUES ('$username', '$email', '$password', '$fullName')";

        if ($this->db->query($query)) {
            return $this->getById($this->db->insert_id);
        }

        return false;
    }

    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public function updateLastLogin($id) {
        $id = intval($id);
        $query = "UPDATE {$this->table} SET last_login = NOW() WHERE id = $id";
        return $this->db->query($query);
    }
}
?>
