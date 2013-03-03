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

    /**
     * Storing new user
     * returns user details
     */
    public function storeUser($fname,$email,$password,$cin,$dname,$grp)
    {

        $hash = $this->hashSSHA($password);
        $encrypted_password = $hash["encrypted"]; // encrypted password
        $salt = $hash["salt"]; // salt
        $result = $this->conn->query("INSERT INTO  $this->db_name.student(idCard, displayName, fullName, email, password, gName, salt)
                                VALUES('$cin', '$dname', '$fname', '$email','$encrypted_password','$grp','$salt')") or header('500 : Internal Server Error',true,500);
        // check for successful store
        if ($result<>false)
        {
            // get user details
            $result = $this->conn->query("SELECT idCard, displayName, fullName, email, password, student.gName, created_at, last_login, salt,group.uName
                                       FROM $this->db_name.student, $this->db_name.group
                                        WHERE (student.gName=group.gName) AND (student.email='$email')") or header('500 : Internal Server Error',true,500);

            // return user details
            return $result->fetch_array(MYSQL_ASSOC);
        }
        else
            return null;
    }

    /**
     * Get user by email and password
     */
    public function getUserByEmailAndPassword($email, $password)
    {

        $result = $this->conn->query("SELECT idCard, displayName, fullName, email, password, student.gName, created_at, last_login, salt,group.uName
                                       FROM $this->db_name.student, $this->db_name.group
                                        WHERE (student.gName=group.gName) AND (student.email='$email')") or header('500 : Internal Server Error',true,500);
        // check for result
        if ($result<>false){
            $no_of_rows = $result->num_rows;
            if ($no_of_rows > 0)
            {
                $result = $result->fetch_assoc();
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
                                                       SET displayName='$newDn' , fullName='$newFn' , email='$newE' ,password='$new_pass' , gName = '$newG' , salt = '$new_salt'
                                                       WHERE idCard = $cin") or header('500 : Internal Server Error',true,500);

                        if($edit_req){
                            //request to deliver an array with new details including University
                            $edit_result = $this->conn->query("SELECT idCard, displayName, fullName, email, password, student.gName, created_at, last_login, salt,group.uName
                                       FROM $this->db_name.student, $this->db_name.group
                                        WHERE (student.gName=group.gName) AND (student.idCard='$cin')") or header('500 : Internal Server Error',true,500);

                            $new_res = $edit_result->fetch_assoc();
                            $rTab['success']= 1;
                            $rTab["user"]["cin"] = $new_res["idCard"];
                            $rTab["user"]["fullName"] = $new_res["fullName"];
                            $rTab["user"]["displayName"] = $new_res["displayName"];
                            $rTab["user"]["email"] = $new_res["email"];
                            $rTab["user"]["university"]= $new_res["uName"];
                            $rTab["user"]["grp"]= $new_res["gName"];
                        }
                        else{
                            $rTab['success']=0;
                            $rTab['error_msg'] = 'Student edit did not go well';
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

        $result = $this->conn->query("SELECT idCard, displayName, fullName, email, password, student.gName, created_at, last_login, salt,group.uName
                                       FROM $this->db_name.student, $this->db_name.group
                                        WHERE (student.gName=group.gName) AND (student.email='$email')") or header('500 : Internal Server Error',true,500);
        // check for result
        if ($result<>false){
            $no_of_rows = $result->num_rows;
            if ($no_of_rows > 0)
            {
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
}
?>
