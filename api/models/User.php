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
        $query = "SELECT id, username, email, full_name, role, created_at, last_login, is_active, password_hash, google_id, avatar_url FROM {$this->table} WHERE id = {$id}";
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
        $full_name = $this->db->real_escape_string($data['full_name'] ?? '');

        $query = "INSERT INTO {$this->table} (username, email, password_hash, full_name)
                  VALUES ('$username', '$email', '$password', '$full_name')";

        if ($this->db->query($query)) {
            return $this->getById($this->db->insert_id);
        }

        return false;
    }

    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public function updateProfile($id, $data) {
        $updates = [];
        $params  = [];
        $types   = '';

        if (isset($data['username'])) {
            $updates[] = 'username = ?';
            $params[]  = $data['username'];
            $types    .= 's';
        }
        if (isset($data['full_name'])) {
            $updates[] = 'full_name = ?';
            $params[]  = $data['full_name'];
            $types    .= 's';
        }

        if (empty($updates)) return false;

        $params[] = $id;
        $types   .= 'i';

        $stmt = $this->db->prepare("UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    public function updatePassword($id, $new_password) {
        $hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("UPDATE {$this->table} SET password_hash = ? WHERE id = ?");
        $stmt->bind_param('si', $hash, $id);
        return $stmt->execute();
    }

    public function updateAvatar($id, $avatar_url) {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET avatar_url = ? WHERE id = ?");
        $stmt->bind_param('si', $avatar_url, $id);
        return $stmt->execute();
    }

    public function updateLastLogin($id) {
        $id = intval($id);
        $query = "UPDATE {$this->table} SET last_login = NOW() WHERE id = $id";
        return $this->db->query($query);
    }

    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function unlinkGoogleId($id) {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET google_id = NULL, email = NULL WHERE id = ?");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    public function findByGoogleId($google_id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE google_id = ?");
        $stmt->bind_param('s', $google_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function linkGoogleId($id, $google_id, $email = null, $avatar_url = null) {
        if ($email && $avatar_url) {
            $stmt = $this->db->prepare("UPDATE {$this->table} SET google_id = ?, email = ?, avatar_url = ? WHERE id = ?");
            $stmt->bind_param('sssi', $google_id, $email, $avatar_url, $id);
        } elseif ($email) {
            $stmt = $this->db->prepare("UPDATE {$this->table} SET google_id = ?, email = ? WHERE id = ?");
            $stmt->bind_param('ssi', $google_id, $email, $id);
        } else {
            $stmt = $this->db->prepare("UPDATE {$this->table} SET google_id = ? WHERE id = ?");
            $stmt->bind_param('si', $google_id, $id);
        }
        return $stmt->execute();
    }

    public function createFromGoogle($data) {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (google_id, email, full_name, avatar_url) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('ssss', $data['google_id'], $data['email'], $data['full_name'], $data['avatar_url']);
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        return false;
    }
}
?>
