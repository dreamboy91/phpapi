<?php

namespace Model;

use Framework\Entity\Repository;

class UserRepository extends Repository
{
    public function findByUserName($username)
    {
        $sql = "SELECT 
                `system_user`.id,
                `system_user`.password,
                `system_user`.contact_id,
                `system_user`.`userid` as full_name,
                `division_tbl`.`id` AS division_id 
                FROM `system_user` 
                INNER JOIN `hr_tbl`
                  on(`system_user`.contact_id = hr_tbl.`contact_id`)
                INNER JOIN `division_tbl` 
                  ON (hr_tbl.dept = division_tbl.`id`)
                WHERE `system_user`.userid = ? LIMIT 1";

        $stmt = $this->getDB()->prepare($sql);

        $stmt->execute(array($username));

        return $stmt->fetchObject($this->class);
    }
}