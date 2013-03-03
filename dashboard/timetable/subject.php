<?php
/**
 * Created by JetBrains PhpStorm.
 * User: NetMonster
 * Date: 21/02/13
 * Time: 07:42
 * To change this template use File | Settings | File Templates.
 */

class Subject{

    private $name,$startHour, $duration ,$room,$day ,$frequency, $type,$order;

    function Subject($subArr){
        $this->day=$subArr[4];
        $this->duration=$subArr[2];
        $this->frequency=$subArr[5];
        $this->name=$subArr[0];
        $this->startHour=$subArr[1];
        $this->type=$subArr[6];
        $this->room=$subArr[3];
        if (isset($subArr[7]))
            $this->order=$subArr[7];
    }

    static function toArray($subj){
        $sArr=null;
        foreach ($subj as $key=>$value){
            $sArr[$key]=$value;
        }
        return $sArr;
    }
}