<?php
/**
 * Created by JetBrains PhpStorm.
 * User: NetMonster
 * Date: 04/03/13
 * Time: 17:45
 * To change this template use File | Settings | File Templates.
 */
require_once "include/DB_Functions.php";

class Dashboard{
    private $db;


    function Dashboard(){
        $this->db= new DB_Functions();



    }


}

$dash=new Dashboard();