<?php
class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $national_id;
    public $password;
    public $full_name;
    public $gender;
    public $date_of_birth;
    public $user_type;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function register() {
        $query = "INSERT INTO " . $this->table_name . "
                SET national_id=:national_id, 
                    password=:password,
                    full_name=:full_name,
                    gender=:gender,
                    date_of_birth=:date_of_birth,
                    user_type=:user_type";

        $stmt = $this->conn->prepare($query);

        $this->national_id = htmlspecialchars(strip_tags($this->national_id));
        $this->password = password_hash($this->password, PASSWORD_BCRYPT);
        $this->full_name = htmlspecialchars(strip_tags($this->full_name));
        $this->gender = htmlspecialchars(strip_tags($this->gender));
        $this->date_of_birth = htmlspecialchars(strip_tags($this->date_of_birth));
        $this->user_type = htmlspecialchars(strip_tags($this->user_type));

        $stmt->bindParam(":national_id", $this->national_id);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":full_name", $this->full_name);
        $stmt->bindParam(":gender", $this->gender);
        $stmt->bindParam(":date_of_birth", $this->date_of_birth);
        $stmt->bindParam(":user_type", $this->user_type);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function login($national_id, $password) {
        $query = "SELECT id, national_id, password, full_name, user_type 
                  FROM " . $this->table_name . "
                  WHERE national_id = :national_id 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":national_id", $national_id);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($password, $row['password'])) {
                return $row;
            }
        }
        return false;
    }

    public function getUserById($id) {
        $query = "SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age 
                  FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
