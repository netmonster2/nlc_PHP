<?php
/**
 * Process.php
 *
 * The Process class is meant to simplify the task of processing
 * user submitted forms, redirecting the user to the correct
 * pages if errors are found, or if form is successful, either
 * way. Also handles the logout procedure.
 */

session_start();
require_once 'include/DB_Functions.php';
require_once 'include/session.php';
require_once 'dashboard/timetable/subject.php';

global $handler;



class Process
{
    private $db;
    private $myHandler;
    private $validSession = false ;
    /* Class constructor */
    function Process(){
        $this->db = new DB_Functions();
        $this->myHandler= new FileSessionHandler();
        session_set_save_handler(
            array($this->myHandler, 'open'),
            array($this->myHandler, 'close'),
            array($this->myHandler, 'read'),
            array($this->myHandler, 'write'),
            array($this->myHandler, 'destroy'),
            array($this->myHandler, 'gc')
        );

        $this->myHandler->open(__DIR__.'\sessions','');
        $this->myHandler->gc(150);
        //global $session;
        //check whether there is a current login session

        /* User submitted login form */
        if (isset($_POST['tag'])){

            if($_POST['tag']=='login'){
                $this->procLogin();
            }
            /* User submitted registration form */
            else if($_POST['tag']=='register'){
                $this->procRegister();
            }
            /* User submitted forgot password form */
            else if($_POST['tag']=='forgotPass'){
                $this->procForgotPass();
            }

            else if($_POST['tag']=='getMarks'){
                $this->getMark();
            }
            /* User submitted edit account form */
            else if($_POST['tag']=='editAcc'){
                $this->procEditAccount();
            }
            else if($_POST['tag']=='getUniv'){
                $this->getUniv();
            }
            else if($_POST['tag']=='getGrps'){
                if (isset($_POST['univ'])){
                    $this->getGrps($_POST['univ']);
                }
                else{
                    $this->errorManage('No university provided');
                }
            }
            else if($_POST['tag']='getTimeTable'){
              if (isset($_POST['grp']) && isset($_POST['univ'])){
                  $this->getTT($_POST['grp'],$_POST['univ']);
              }
                else
                    $this->errorManage('Some fields are missing for timetable request');
            }
            /**
             * The only other reason user should be directed here
             * is if he wants to logout, which means user is
             * logged in currently.
             */
            else if($_POST['tag']=='logout'){
                $this->procLogout();
            }
            /**
             * Should not get here, which means user is viewing this page
             * by mistake and therefore is redirected.
             */
            else{
                $this->errorManage("No valid operation specified");
            }
        }
        else if ((isset($_SESSION['email']))&&($this->validSession)){
            echo 'login based on stored session';
        }

        else {
            $this->errorManage('Access denied');
        }

    }

    /**
     * Processes the user submitted login form, if errors
     * are found, the user is redirected to correct the information,
     * if not, the user is effectively logged in to the system.
     */
    function procLogin(){

        if(isset($_POST['sessID'])){
            $sID=$_POST['sessID'];
            if(($userEmail=$this->myHandler->read($sID))<>""){
                if($this->db->isUserExisted($userEmail)){
                    $response=$this->db->getUserByEmailFromSession($userEmail);
                    if($response){
                        $this->grantAccess($response,false);
                    }
                }
                else{
                    $this->errorManage("Invalid session ID");
                }
            }
            else{
                $this->errorManage("Wrong session ID provided");
            }
        }
        else{

            if (isset($_POST['email']) && isset($_POST['password']))
            {
                $email = $_POST['email'];
                $password = $_POST['password'];
                // check for user
                $user = $this->db->getUserByEmailAndPassword($email, $password);
                if ($user != false)
                {
                    // user found
                    // echo json with success = 1
                    if(isset($_POST['startSession']) && $_POST['startSession']==1){

                        $this->grantAccess($user,true);
                    }
                    else

                        $this->grantAccess($user,false);
                }
                else
                {
                    // user not found
                    // echo json with error = 1

                    $this->errorManage('Incorrect Email-Password ');
                }
            }
            else{
                $this->errorManage("Some fields are missing for login");
            }
        }
    }

    /**
     * Simply attempts to log the user out of the system
     * given a session ID .
     */
    function procLogout(){
        if (isset($_POST['sessID'])){
            $sessionID=$_POST['sessID'];
            if($this->myHandler->read($sessionID)){
                $this->myHandler->destroy($sessionID);
                $tab1['success']=1;
                $tab1['msg']="Logged-out successfully";
                echo json_encode($tab1);
            }
            else{
                $this->errorManage("Invalid Session ID");
            }
        }
        else{
            $this->errorManage("Session ID not provided");
        }
    }

    /**
     * Processes the user submitted registration form,
     * if errors are found, the user is redirected to correct the
     * information, if not, the user is effectively registered with
     * the system and an email is (optionally) sent to the newly
     * created user.
     */
    function procRegister(){
        if (isset($_POST['email']) && isset($_POST['password']) && isset($_POST['fullname']) && isset($_POST['cin'])&& isset($_POST['displayname']) && isset($_POST['grp']))
        {
            $fname = $_POST['fullname'];
            $dName= $_POST['displayname'];
            $email = $_POST['email'];
            $password = $_POST['password'];
            //mod Here
            $cin = $_POST['cin'];
            $grp = $_POST['grp'];

            // check if user is already existed
            if ($this->db->isUserExisted($email))
            {
                // user is already existed - error response
                $this->errorManage("User already existed");
            }
            else
            {
                // store user
                $user = $this->db->storeUser($fname, $email, $password,$cin,$dName,$grp);//mod here
                if ($user)
                {
                    // user stored successfully
                    $this->grantAccess($user,false);
                }
            }
        }
        else {
            $this->errorManage("Some fields are missing for registration");
        }
    }

    /**
     * Validates the given username then if
     * everything is fine, a new password is generated and
     * emailed to the address the user gave on sign up.
     */
    function procForgotPass(){

    }


    /**
     * Attempts to edit the user's account
     * information, including the password, which must be verified
     * before a change is made.
     * @return string New student's information after edit
     */
    function procEditAccount(){
        if (isset($_POST['oldEmail']) && isset($_POST['oldPassword']) && isset($_POST['newFullName']) && isset($_POST['newDisplayName'])&& isset($_POST['newGrp']) && isset($_POST['newEmail']) && isset($_POST['newPass'])) {

            echo json_encode($this->db->editUser($_POST['oldEmail'],$_POST['oldPassword'],$_POST['newEmail'],$_POST['newPass'],$_POST['newFullName'],$_POST['newDisplayName'],$_POST['newGrp']));
        }
        else
            $this->errorManage("Some fields are missing for editing");
    }

    /**
     * @param $reason
     * the reason of the error
     * @return string the error in JSON format
     */
    function errorManage($reason){
        $tab["success"] = 0;
        $tab["error_msg"]=$reason;
        echo json_encode($tab);
    }

    /**
     * @param $res
     * Array of user's information
     * @param $sess
     * Boolean - Whether to return a session ID in output
     *<p>To start session</p>
     * @return string Student's information for login
     */
    function grantAccess($res,$sess){
        // response Array
        $_SESSION['email']=$res['email'];

        if ($sess){
            $sessID = md5(md5($res['email']).md5(rand()));
            $this->myHandler->write($sessID,$res['email']);
        }

        $response["success"] = 1;
        $response["user"]["cin"] = $res["idCard"];
        $response["user"]["fullName"] = $res["fullName"];
        $response["user"]["displayName"] = $res["displayName"];
        $response["user"]["email"] = $res["email"];
        $response["user"]["university"]= $res["uName"];
        $response["user"]["grp"]= $res["gName"];
        $response["user"]["createdAt"] = $res["created_at"];

        if ($sess)
            $response["sessID"]=$sessID;
        echo json_encode($response);

    }

    /**
     * @param $university : the university where to search
     * @retun string the available groups in the university
     */
    function getGrps($university){
        echo json_encode($this->db->grpList($university));
    }

    /**
     * @return string the available universities
      */
    function getUniv(){
        echo json_encode($this->db->getUnivs());
    }



    /**
     * @param $grp
     * Student's group
     * @param $univ
     * Student's university
     * @return string the timetable for respective
     * Group and University or the appropriate
     */
    function getTT($grp,$univ){
       echo json_encode($this->db->getTTimeTable($grp,$univ));
    }

    function getMark(){
        if (isset($_POST['idCard'])){
            	$res1=$this->db->getMarksFromId(intval($_POST['idCard']));
		echo json_encode($res1);
        }
        else{
            $this->errorManage('No id specified for mark request');
        }
    }

};

/* Initialize process */
$process = new Process;

?>
