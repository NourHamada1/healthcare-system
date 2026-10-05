<?php
class Pharmacist {
    private $conn;
    private $table_name = "pharmacists";

    public $id;
    public $user_id;
    public $years_experience;
    public $description;

    public function __construct($db) {
        $this->conn = $db;
    }
}
?>