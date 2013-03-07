<?php
require_once "config.php";
class DB_Functions
{
    public $conn;
    private $db_name=DB_DATABASE;
    //put your code here
    // constructor
    function __construct()
    {
        require_once 'DB_Connect.php';
        // connecting to database
        $db = new DB_Connect();
        $this->conn=$db->connect();
    }

    // destructor
    function __destruct() {}

    function isUserExistFromId($id){
        $query="SELECT * FROM $this->db_name.student WHERE  idCard='$id'";

        $qRes=$this->conn->query($query);
        if ($qRes->num_rows > 0)
            return true;
        else
            return false;
    }

    function getGrpFromEmail($email){
        $grpQ=$this->conn->query("SELECT gName FROM $this->db_name.student, $this->db_name.group
                                          WHERE (student.grpID=group.grpID) AND (student.email = '$email')") or die($this->conn->error);

        $grp=$grpQ->fetch_assoc();
        return $grp['gName'];
    }

    function getIDFromGroup($group){
        $q=$this->conn->query("SELECT grpID FROM $this->db_name.group WHERE group.gName='$group'");
        $qArray=$q->fetch_assoc();
        return (int) $qArray['grpID'];
    }

    /**
     * Storing new user
     * returns user details
     */
    public function storeUser($fname,$email,$password,$cin,$dname,$grp)
    {

        $hash = $this->hashSSHA($password);
        $encrypted_password = $hash["encrypted"]; // encrypted password
        $salt = $hash["salt"]; // salt
        $userGrp=$this->getIDFromGroup($grp);
        $result = $this->conn->query("INSERT INTO  $this->db_name.student(idCard, displayName, fullName, email, password, grpID, salt)
                                VALUES('$cin', '$dname', '$fname', '$email','$encrypted_password','$userGrp','$salt')") or header('500 : Internal Server Error',true,500);
        // check for successful store
        if ($result<>false)
        {
            // get user details
            $result = $this->conn->query("SELECT idCard, displayName, fullName, email,created_at,group.uName
                                       FROM $this->db_name.student, $this->db_name.group
                                        WHERE (student.grpID=group.grpID) AND (student.email='$email')") or header('500 : Internal Server Error',true,500);

            // return user details
            $resultRet=$result->fetch_assoc();
            $resultRet['gName']=$this->getGrpFromEmail($email);

            return $resultRet;
        }
        else
            return null;
    }

    /**
     * Get user by email and password
     */
    public function getUserByEmailAndPassword($email, $password)
    {

        $result = $this->conn->query("SELECT idCard, displayName, fullName, email, password, student.grpID, created_at, last_login, salt,group.uName
                                       FROM $this->db_name.student, $this->db_name.group
                                        WHERE (student.grpID=group.grpID) AND (student.email='$email')") or header('500 : Internal Server Error',true,500);
        // check for result
        if ($result<>false){
            $no_of_rows = $result->num_rows;
            if ($no_of_rows > 0)
            {
                $result = $result->fetch_assoc();

                $result['gName']=$this->getGrpFromEmail($email);
                $salt = $result['salt'];
                $encrypted_password = $result['password'];
                $hash = $this->checkhashSSHA($salt, $password);
                // check for password equality
                if ($encrypted_password == $hash){
                    // user authentication details are correct
                    $this->conn->query("UPDATE $this->db_name.student SET last_login=NOW() WHERE student.email='$email'");
                    return $result;
                }
                else
                    return false;

            }
            else
                // user not found
                return false;
        }
        else
            return false;
    }

    /**
     * Return available groups for a specific university
     */
    public function grpList($univ){

        $q="SELECT * FROM $this->db_name.group WHERE uName='$univ'";
        $request = $this->conn->query($q) or header('500 : Internal Server Error',true,500);
        if (!($request==FALSE)){
            if ($request->num_rows){
                $i = 1;
                $tab['success']=1;
                $tab1=null;
                while(($result=$request->fetch_assoc())){
                    $tab1[$i-1]=$result['gName'];
                    $i++;
                }
                $tab['group']=$tab1;
                return $tab;
            }
            else{
                $tab["success"] = 0;
                $tab["error_msg"]='Nothing found for such university';
                return $tab;
            }
        }
        else
            return null;
    }

    /**
     * Check user is existed or not
     */
    public function isUserExisted($email)
    {

        $result =$this->conn->query("SELECT email from $this->db_name.student WHERE email = '$email'") or header('500 : Internal Server Error',true,500);
        if ($result){
            $no_of_rows = $result->num_rows;
            if ($no_of_rows > 0)
                // user existed
                return true;

            else
                // user not existed
                return false;
        }
        else
            return false;

    }

    /**
     * Encrypting password
     * @param password
     * @return salt and encrypted password
     */
    public function hashSSHA($password)
    {

        $salt = sha1(rand());
        $salt = substr($salt, 0, 10);
        $encrypted = md5(md5(strrev($password).$salt));
        $hash = array("salt" => $salt, "encrypted" => $encrypted);

        return $hash;
    }

    /**
     * Decrypting password
     * @param $salt
     * @param $password
     * @return hash string
     */
    public function checkhashSSHA($salt, $password)
    {
        return md5(md5(strrev($password).$salt));
    }

    public function getUnivs(){

        $result = $this->conn->query("SELECT uName FROM $this->db_name.university") or header('500 : Internal Server Error',true,500);
        if ($result){
            if ($result->num_rows > 0){
                $i = 1;
                $tab['success']=1;
                //$tab['count']=$result->num_rows;
                while(($req=$result->fetch_assoc())){
                    $tab1[$i-1]=$req['uName'];
                    $i++;
                }
                if (isset($tab1)) {
                    $tab['univ']=$tab1;
                }
                return $tab;
            }
            else{
                $tab["success"] = 0;
                $tab["error_msg"]='No university found';
                return $tab;
            }
        }
        else {
            $tab["success"] = 0;
            $tab["error_msg"]='No university found';
            return $tab;
        }
    }

    /**
     * Function to edit user's info in DB
     */
    public function editUser($oldE,$oldP,$newE,$newP,$newFn,$newDn,$newG){
        $rTab=null;
        if ($this->isUserExisted($oldE)){
            $req= $this->conn->query("SELECT * FROM $this->db_name.student WHERE email='$oldE' ") or header('500 : Internal Server Error',true,500);
            if ($req){
                if($req->num_rows > 0){
                    $res_tab=$req->fetch_assoc();

                    $salt = $res_tab['salt'];
                    $enc_pass = $res_tab['password'];

                    $cin = $res_tab['idCard'];

                    $enc_old_pass = $this->checkhashSSHA($salt,$oldP);

                    if($enc_old_pass == $enc_pass){
                        $new_pass_salt = $this->hashSSHA($newP);
                        $new_pass = $new_pass_salt['encrypted'];
                        $new_salt = $new_pass_salt['salt'];

                        $edit_req = $this->conn->query("UPDATE $this->db_name.student
                                                       SET displayName='$newDn' , fullName='$newFn' , email='$newE' ,password='$new_pass' , grpID = $this->getIDFromGroup($newG) , salt = '$new_salt'
                                                       WHERE idCard = $cin") or header('500 : Internal Server Error',true,500);

                        if($edit_req){
                            //request to deliver an array with new details including University
                            $edit_result = $this->conn->query("SELECT idCard, displayName, fullName, email, created_at,group.uName
                                       FROM $this->db_name.student, $this->db_name.group
                                        WHERE (student.grpIDe=group.grpID) AND (student.idCard='$cin')") or header('500 : Internal Server Error',true,500);

                            $new_res = $edit_result->fetch_assoc();
                            $rTab['success']= 1;
                            $rTab["user"]["cin"] = $new_res["idCard"];
                            $rTab["user"]["fullName"] = $new_res["fullName"];
                            $rTab["user"]["displayName"] = $new_res["displayName"];
                            $rTab["user"]["email"] = $new_res["email"];
                            $rTab["user"]["university"]= $new_res["uName"];
                            $rTab["user"]["grp"]= $this->getGrpFromEmail($new_res["email"]);
                        }
                    }
                    else{
                        $rTab['success']=0;
                        $rTab['error_msg'] = 'Old password is incorrect';
                    }
                }
            }
        }
        else{
            $rTab['success']=0;
            $rTab['error_msg'] = 'Old email not found';
        }
        return $rTab;

    }

    public function getUserByEmailFromSession($email)
    {

        $result = $this->conn->query("SELECT idCard, displayName, fullName, email, created_at,group.uName
                                       FROM $this->db_name.student, $this->db_name.group
                                        WHERE (student.grpID=group.grpID) AND (student.email='$email')") or header('500 : Internal Server Error',true,500);
        // check for result
        if ($result<>false){
            $no_of_rows = $result->num_rows;
            if ($no_of_rows > 0)
            {
                $result['gName']=$this->getGrpFromEmail($email);
                $result = $result->fetch_assoc();
                // user authentication details are correct
                $this->conn->query("UPDATE $this->db_name.student SET last_login=NOW() WHERE student.email='$email'") or header('500 : Internal Server Error',true,500);
                return $result;
            }
            else
                // user not found
                return false;
        }
        else
            return false;
    }

    public function getMarksFromId($idC){
        if ($this->isUserExistFromId($idC)){
            $q="SELECT type, markValue, label FROM $this->db_name.mark, $this->db_name.subject
                WHERE (mark.idCard=$idC) AND (mark.sId=subject.sId)
                 ORDER BY label,type ASC" or header('500 : Internal Server Error',true,500);

            $qRes=$this->conn->query($q) or var_dump($this->conn->error);

            if($qRes){
                if($qRes->num_rows>0){
                    $rTab['success'] = 1;
                    $i = 0 ;
                    $cptTab=0;
                    $qTab = $qRes->fetch_assoc();
                    do{


                        $rTabInter['label'] = utf8_encode($qTab['label']);

                        do{
                            if ($qTab['type']=='DS'){
                                $rTabInter['ds'] = $qTab['markValue'];
                            }

                            if ($qTab['type']=='Exam'){
                                $rTabInter['exam'] = $qTab['markValue'];
                            }

                            if ($qTab['type']=='TP'){
                                $rTabInter['tp'] = $qTab['markValue'];
                            }
                                $i++;
                                $qRes->data_seek($i);
                                $qTab = $qRes->fetch_assoc();

                        }while($qTab['label']==$rTabInter['label']);

                        if (!isset($rTabInter['ds']))
                            $rTabInter['ds']=-1;

                        if (!isset($rTabInter['exam']))
                            $rTabInter['exam']=-1;

                        if (!isset($rTabInter['tp']))
                            $rTabInter['tp']=-1;

                        $tab1[$cptTab]=$rTabInter;
                        $cptTab++;

                    }while($qRes->data_seek($i));
                    $rTab['marks']=$tab1;
                }
                else{
                    $rTab['success']=0;
                    $rTab['error_msg'] = 'No marks found';
                }
            }
        }
        else{
            $rTab['success']=0;
            $rTab['error_msg'] = 'id not found';
        }

        return $rTab;

    }

    function getTTimeTable($grpN,$univN){
        //verify that grp + univ is available and get TimeTable ID from them
        $verQ="SELECT tID FROM $this->db_name.group WHERE (group.gName='$grpN') AND (group.uName='$univN')";
        $verRes=$this->conn->query($verQ) or header('500 : Internal Server Error',true,500);

        if ($verRes->num_rows>0){
            $verRes=$verRes->fetch_assoc();
            $ttid= (int) $verRes['tID'];

            //now get timetable elements from ttid
            $tteltsQ="SELECT ttelement.name, ttelement.startHour, ttelement.duration, ttelement.room, ttelement.day, ttelement.frequency, ttelement.type, ttelement.order
                        FROM $this->db_name.ttelement WHERE (tID = $ttid)";
            $tteltsRes = $this->conn->query($tteltsQ) or header('500 : Internal Server Error',true,500);
            if($tteltsRes->num_rows>0){
                $finalRes=null;
                $i=0;
                while($tteltsRes->data_seek($i)){
                    $currTab=$tteltsRes->fetch_assoc();
                    foreach ($currTab as $key=>$value){
                        $finalRes[$i][$key]= $value;
                    }
                    $i++;
                }



                $rTab['success'] = 1;
                $rTab['timeTable'] = $finalRes;
            }
            else{
                $rTab['success']=0;
                $rTab['error_msg'] = 'No timetable found for such group';
            }
        }
        else
        {
            $rTab['success']=0;
            $rTab['error_msg'] = 'Group not found';
        }
        return $rTab;
    }
}
?>
