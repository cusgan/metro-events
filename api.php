<?php
date_default_timezone_set('Asia/Manila');
// meta JSON
$metaJSON = 'data/meta.json';
// users JSON
$usersJSON = 'data/users.json';

// events JSON
$eventsJSON = 'data/events.json';

function getData($dataJSON) {
    if (!file_exists($dataJSON)) {
        echo 1;
        return [];
    }

    $data = file_get_contents($dataJSON);
    return json_decode($data, true);
}
function getUsersData() {
    global $usersJSON;
    return getData($usersJSON);
}
function getEventsData() {
    global $eventsJSON;
    return getData($eventsJSON);
}
function strequ($str1,$str2){
    return strcmp($str1,$str2)==0;
}
function metaData($key){
    global $metaJSON;
    $data = getData($metaJSON);
    $res = $data[$key];
    $data[$key] += 1;
    file_put_contents($metaJSON,json_encode($data,JSON_PRETTY_PRINT));
    return $res;
}
function newUser($username,$password){
    if(findUser($username) != -1){
        return array("success"=>false,"message"=>"User with that Username already exists.",
        "id"=>-1,"username"=>null);
    }
    global $usersJSON;
    $data = getUsersData();
    $id = metaData("lastUserIndex") + 1;
    $user = array(
        "id"=>$id,
        "username"=>$username,
        "password"=>base64_encode($password),
        "usertype"=>0,
        "notifsNum"=>0,
        "notifs"=>array(),
        "events"=>array()
    );
    $data['0'.$id] = $user;
    file_put_contents($usersJSON,json_encode($data,JSON_PRETTY_PRINT));
    return array("success"=>true,"message"=>"Successfully Signed Up.",
    "id"=>$id,"username"=>$username,"usertype"=>0);
}
function getUser($id){
    $data = getUsersData();
    if(isset($data['0'.$id])){
        return $data['0'.$id];
    } else {
        return $data["null"];
    }
}
function findUser($username){
    $data = getUsersData();
    foreach($data as $user){
        if(strequ($username,$user["username"])){
            return $user["id"];
        }
    }
    return -1;
}
function login($username,$password){
    $data = getUsersData();
    $id = findUser($username);
    if($id==-1 || !strequ(getUser($id)["password"],base64_encode($password))){
        return array("success"=>false,"message"=>"Invalid username or password.",
        "id"=>-1,"username"=>null);
    }
    $type = getUser($id)["usertype"];
    return array("success"=>true,"message"=>"Successfully Logged In.",
    "id"=>$id,"username"=>$username,"usertype"=>$type);
}
function setUserType($id,$type){
    global $usersJSON;
    $user = getUser($id);
    if($user["id"]==-1 || $user["usertype"] == $type){
        return false;
    }
    $user["usertype"] = $type;
    $data = getUsersData();
    $data['0'.$id] = $user;
    file_put_contents($usersJSON,json_encode($data,JSON_PRETTY_PRINT));
    return true;
}
function setUser($id){
    if(setUserType($id,0)){
        return array("success"=>true,"message"=>"Successfully set user type to User.");
    } else {
        return array("success"=>false,"message"=>"Error: User not found or user type already User.");
    }
}
function setOrganizer($id){
    if(setUserType($id,1)){
        return array("success"=>true,"message"=>"Successfully set user type to Organizer.");
    } else {
        return array("success"=>false,"message"=>"Error: User not found or user type already Organizer.");
    }
}
function setAdmin($id){
    if(setUserType($id,2)){
        return array("success"=>true,"message"=>"Successfully set user type to Administrator.");
    } else {
        return array("success"=>false,"message"=>"Error: User not found or user type already Administrator.");
    }
}
function newEvent($organizerID,$eventName,$eventDateStart,$eventDateEnd,$eventType,$eventDesc){
    if($organizerID==null || $eventName == null || $eventDateStart == null || $eventDateEnd == null || $eventType == null || $eventDesc == null){
        return array("success"=>false,"message"=>"Incomplete data supplied.",
        "id"=>-1);
    }
    $regex = '/\\d\\d\\d\\d-\\d\\d-\\d\\d-\\d\\d-\\d\\d/i';
    if(!(preg_match($regex,$eventDateStart)) || !(preg_match($regex,$eventDateEnd))){
        return array("success"=>false,"message"=>"Invalid Date Formatting.",
        "id"=>-1);
    }
    if($eventDateEnd<=$eventDateStart){
        return array("success"=>false,"message"=>"Invalid Date Start and Date End.",
        "id"=>-1);
    }
    global $eventsJSON;
    $data = getEventsData();
    $id = metaData("lastEventIndex") + 1;
    $event = array(
        "id"=>$id,
        "organizerID"=>$organizerID,
        "status"=>0,
        "name"=>$eventName,
        "type"=>$eventType,
        "desc"=>$eventDesc,
        "dateStart"=>$eventDateStart,
        "dateEnd"=>$eventDateEnd,
        "participants"=>array(),
        "upvotes"=>array(),
        "reviews"=>array()
    );
    $data['0'.$id] = $event;
    file_put_contents($eventsJSON,json_encode($data,JSON_PRETTY_PRINT));
    return array("success"=>true,"message"=>"Successfully created Event.",
    "id"=>$id);
}
function getEvent($id){
    $data = getEventsData();
    if(isset($data['0'.$id])){
        return $data['0'.$id];
    } else {
        return $data["null"];
    }
}
function requestJoin($eventID,$userID){
    $errors = array();
    $event = getEvent($eventID);
    if($event["id"]==-1) { array_push($errors,"Event not found.");}
    else {
        if($event["status"]==2) { array_push($errors,"Event already ended.");}
        if($event["status"]==3) { array_push($errors,"Event was cancelled.");}
        $user = getUser($userID);
        if($user["id"]==-1) { array_push($errors,"User not found.");}
        else {
            foreach($event["participants"] as $p){
                if($p == $user["id"]){ array_push($errors,"User already joined event.");}
            }
        }
    }
    if(count($errors) > 0){
        $message = "";
        foreach($errors as $error) {
            $message .= $error." ";
        }
        return array("success"=>false,"message"=>$message);
    }
    $title = "Join Request: ".$user["username"];
    $body = $user["username"]." wants to join your event: ".$event["name"];
    $buttonFunction = array("joinEvent(".$eventID.','.$userID.');');
    $notif = newNotif($event["organizerID"],$title,$body,$buttonFunction);
    if($notif["success"]){
        return array("success"=>true,"message"=>"Join Request has been sent.");
    } else {
        return array("success"=>false,"message"=>"Unsuccessful in sending Request.");
    }
}
function joinEvent($eventID,$userID){
    $errors = array();
    $event = getEvent($eventID);
    if($event["id"]==-1) { array_push($errors,"Event not found.");}
    else {
        if($event["status"]==2) { array_push($errors,"Event already ended.");}
        if($event["status"]==3) { array_push($errors,"Event was cancelled.");}
        $user = getUser($userID);
        if($user["id"]==-1) { array_push($errors,"User not found.");}
        else {
            foreach($event["participants"] as $p){
                if($p == $user["id"]){ array_push($errors,"User already joined event.");}
            }
        }
    }
    if(count($errors) > 0){
        $message = "";
        foreach($errors as $error) {
            $message .= $error." ";
        }
        return array("success"=>false,"message"=>$message);
    } else {
        global $eventsJSON;
        $data = getEventsData();
        $parts = $event["participants"];
        array_push($parts,$userID);
        $event["participants"]=$parts;
        $data['0'.$eventID] = $event;
        file_put_contents($eventsJSON,json_encode($data,JSON_PRETTY_PRINT));
        global $usersJSON;
        $data = getUsersData();
        array_push($data['0'.$userID]["events"],$eventID);
        file_put_contents($usersJSON,json_encode($data,JSON_PRETTY_PRINT));

        $title = "Request Accepted: ".$event["name"];
        $body = "Your request to join ".$event["name"]." has been approved.";
        $buttonFunction = null;
        newNotif($userID,$title,$body,$buttonFunction);

        return array("success"=>true,"message"=>"Successfully joined event.");
    }
}
function leaveEvent($eventID,$userID){
    $errors = array();
    $event = getEvent($eventID);
    if($event["id"]==-1) { array_push($errors,"Event not found.");}
    else {
        if($event["status"]==2) { array_push($errors,"Cannot leave ended event.");}
        if($event["status"]==3) { array_push($errors,"Cannot leave cancelled event.");}
        $user = getUser($userID);
        if($user["id"]==-1) { array_push($errors,"User not found.");}
        else {
            $found=false;
            foreach($event["participants"] as $p){
                if($p == $userID){ $found=true; break;}
            }
            if(!$found){ array_push($errors,"User is already not in event.");}
        }
    }
    if(count($errors) > 0){
        $message = "";
        foreach($errors as $error) {
            $message .= $error." ";
        }
        return array("success"=>false,"message"=>$message);
    } else {
        global $eventsJSON;
        $data = getEventsData();
        $parts = array();
        foreach($event["participants"] as $p){
            if($p != $userID) {array_push($parts,$userID);}
        }
        $event["participants"]=$parts;
        $data['0'.$eventID] = $event;
        file_put_contents($eventsJSON,json_encode($data,JSON_PRETTY_PRINT));
        global $usersJSON;
        $data = getUsersData();
        $newUserEvents = array();
        foreach($user["events"] as $ev){
            if($ev != $eventID){
                array_push($newUserEvents,$ev);
            }
        }
        $user["events"] = $newUserEvents;
        $data['0'.$userID] = $user;
        file_put_contents($usersJSON,json_encode($data,JSON_PRETTY_PRINT));
        return array("success"=>true,"message"=>"Successfully removed this user from the event.");
    }
}
function cancelEvent($eventID,$userID,$message){
    $errors = array();
    $event = getEvent($eventID);
    if($event["id"]==-1) { array_push($errors,"Event not found.");}
    else {
        if($event["status"]==2) { array_push($errors,"Event already ended.");}
        if($event["status"]==3) { array_push($errors,"Event already cancelled.");}
        $user = getUser($userID);
        if($user["id"]==-1) { array_push($errors,"User not found.");}
        else {
            if($user["usertype"]!=2 && !($user["usertype"]==1 && $event["organizerID"]==$userID)){
                array_push($errors, "User does not have sufficient privileges.");
            }
        }
    }
    if(count($errors) > 0){
        $message = "";
        foreach($errors as $error) {
            $message .= $error." ";
        }
        return array("success"=>false,"message"=>$message);
    } else {
        $title = "Event Cancelled: ".$event["name"];
        $body = $message;
        if(strequ($message,"") || $message==null){
            $body = "The event has been cancelled by the organizers/admins.";
        }
        foreach($event["participants"] as $par){
            if($event["organizerID"]==$par){
                continue;
            }
            newNotif($par,$title,$body,null);
        }
        newNotif($event["organizerID"],"Your event has been cancelled","Message: ".$body,null);
        global $eventsJSON;
        $data = getEventsData();
        $data['0'.$eventID]["status"]=3;
        file_put_contents($eventsJSON,json_encode($data,JSON_PRETTY_PRINT));
        return array("success"=>true,"message"=>"Successfully cancelled event.");
    }
}
function updateEvents(){
    global $eventsJSON;
    $now = date('Y-m-d-H-i', time());
    $data = getEventsData();
    $newdata = array();
    foreach($data as $event){
        if($event["id"]==-1){
            $newdata["null"] = $event;
            continue;
        }
        //echo $now.'<br>' ;
        //echo $event["dateEnd"];  
        if($event["status"]<=1 && $now >= $event["dateEnd"]){
            $event["status"]=2;
            $title = "Event is Done!";
            $body = "The event: ".$event["name"]." is now over.";
            foreach($event["participants"] as $par){
                newNotif($par,$title,$body,null);
            }
        }
        if($event["status"]==0 && $now >= $event["dateStart"]){
            $event["status"]=1;
            $title = "Event now Ongoing!";
            $body = "The event: ".$event["name"]." has now started.";
            foreach($event["participants"] as $par){
                newNotif($par,$title,$body,null);
            }
        }
        $newdata['0'.$event["id"]] = $event;
    }
    file_put_contents($eventsJSON,json_encode($newdata,JSON_PRETTY_PRINT));
}
function requestOrganizer($userID){
    $user = getUser($userID);
    if($user["id"]==-1) {return array("success"=>false,"message"=>"User not found.");}
    $title = "Organizer Request";
    $body = "The user: ".$user["username"]." has requested to be an Organizer.";
    
    $btn = array("setOrganizer(".$userID.");" , 'newNotif('.$userID.',"You\'re now an Organizer!","Your request to be Organizer has been approved.",null);');
    $data = getUsersData();
    foreach($data as $u){
        if($u["usertype"]==2){
            newNotif($u["id"],$title,$body,$btn);
        }
    }
    {return array("success"=>true,"message"=>"Successfully sent Request.");}
}
function requestAdmin($userID){
    $user = getUser($userID);
    if($user["id"]==-1) {return array("success"=>false,"message"=>"User not found.");}
    $title = "Administrator Request";
    $body = "The user: ".$user["username"]." has requested to be an Administrator.";
    
    $btn = array("setAdmin(".$userID.");" , 'newNotif('.$userID.',"You\'re now an Administrator!","Your request to be Administrator has been approved.",null);');
    $data = getUsersData();
    foreach($data as $u){
        if($u["usertype"]==2){
            newNotif($u["id"],$title,$body,$btn);
        }
    }
    {return array("success"=>true,"message"=>"Successfully sent Request.");}
}
function newReview($eventID,$userID,$review){
    $errors = array();
    $event = getEvent($eventID);
    if($event["id"]==-1) { array_push($errors,"Event not found.");}
    $user = getUser($userID);
    if($user["id"]==-1) { array_push($errors,"User not found.");}
    if(strequ($review,"") || $review == null){
        array_push($errors,"Review cannot be empty.");
    }
    if(count($errors) > 0){
        $message = "";
        foreach($errors as $error) {
            $message .= $error." ";
        }
        return array("success"=>false,"message"=>$message);
    } else {
        global $eventsJSON;
        $data = getEventsData();
        $reviewObj = array(
            "userID"=>$userID,
            "content"=>$review
        );
        array_push($data['0'.$eventID]["reviews"],$reviewObj);
        file_put_contents($eventsJSON,json_encode($data,JSON_PRETTY_PRINT));
        return array("success"=>true,"message"=>"Successfully added review.");
    }
}
function deleteReview($eventID,$userID,$review){
    $errors = array();
    $event = getEvent($eventID);
    if($event["id"]==-1) { array_push($errors,"Event not found.");}
    $user = getUser($userID);
    if($user["id"]==-1) { array_push($errors,"User not found.");}
    if(strequ($review,"") || $review == null){
        array_push($errors,"Review cannot be empty.");
    }
    if(count($errors) > 0){
        $message = "";
        foreach($errors as $error) {
            $message .= $error." ";
        }
        return array("success"=>false,"message"=>$message);
    } else {
        global $eventsJSON;
        $data = getEventsData();
        $newreviews = array();
        $ctr = 0;
        foreach($event["reviews"] as $rev){
            if(!(strequ($rev["content"],$review) && ($rev["userID"]==$userID || getUser($userID)["usertype"]==2))){
                array_push($newreviews,$rev);
            } else {
                $ctr++;
            }
        }
        if($ctr==0){
            return array("success"=>false,"message"=>"Review not found.");
        }
        $data['0'.$eventID]["reviews"] = $newreviews;
        file_put_contents($eventsJSON,json_encode($data,JSON_PRETTY_PRINT));
        return array("success"=>true,"message"=>"Successfully deleted review.");
    }
}
function newNotif($userID,$title,$body,$buttonFunctions){
    $user = getUser($userID);
    if($user["id"]==-1){return array("success"=>false,"message"=>"User not found.");}
    $id = $user["notifsNum"] + 1;
    $user["notifsNum"] = $id;
    $delete = "deleteNotif(".$userID.','.$id.');';
    if($buttonFunctions != null) {array_push($buttonFunctions,$delete);}
    $notif = array(
        "id"=>$id,
        "title"=>$title,
        "body"=>$body,
        "yes"=>$buttonFunctions,
        "no"=>$delete
    );
    $user["notifs"]['0'.$id] = $notif;
    $data = getUsersData();
    $data['0'.$userID] = $user;
    global $usersJSON;
    file_put_contents($usersJSON,json_encode($data,JSON_PRETTY_PRINT));
    return array("success"=>true,"message"=>"Successfully Added Notification.",
    "id"=>$id);
}
function deleteNotif($userID,$notifID){
    $user = getUser($userID);
    if($user["id"]==-1){return array("success"=>false,"message"=>"User not found.");}
    if(!isset($user["notifs"]['0'.$notifID])) {return array("success"=>false,"message"=>"Notification not found.");}
    $data = getUsersData();
    $newnotifs = $user["notifs"]; 
    unset($newnotifs['0'.$notifID]);
    $user["notifs"] = $newnotifs;
    $data['0'.$userID] = $user;
    global $usersJSON;
    file_put_contents($usersJSON,json_encode($data,JSON_PRETTY_PRINT));
    return array("success"=>true,"message"=>"Successfully Deleted Notification.");
}
function like($eventID,$userID){
    $errors = array();
    $event = getEvent($eventID);
    $isAlready = false;
    if($event["id"]==-1) { array_push($errors,"Event not found.");}
    else {
        $user = getUser($userID);
        if($user["id"]==-1) { array_push($errors,"User not found.");}
        else {
            foreach($event["upvotes"] as $p){
                if($p == $user["id"]){$isAlready=true; break;}
            }
        }
    }
    if(count($errors) > 0){
        $message = "";
        foreach($errors as $error) {
            $message .= $error." ";
        }
        return array("success"=>false,"message"=>$message);
    } else {
        global $eventsJSON;
        $data = getEventsData();
        if($isAlready){
            $newUpvotes = array();
            foreach($event["upvotes"] as $up){
                if($up != $userID){
                    array_push($newUpvotes,$up);
                }
            }
            $event["upvotes"]=$newUpvotes;
            $data['0'.$eventID] = $event;
            file_put_contents($eventsJSON,json_encode($data,JSON_PRETTY_PRINT));
            return array("success"=>true,"message"=>"Unliked.");
        } else {
            $parts = $event["upvotes"];
            array_push($parts,$userID);
            $event["upvotes"]=$parts;
            $data['0'.$eventID] = $event;
            file_put_contents($eventsJSON,json_encode($data,JSON_PRETTY_PRINT));
            return array("success"=>true,"message"=>"Liked.");
        }
    }
}
function processNotif($notif){
    if($notif==null){return;}
    if(strequ(gettype($notif),"string")){
        return eval($notif);
    } else {
        $res = "";
        foreach($notif as $n){
            $a = eval($n);
            if(isset($a["message"])){
                $res.=$a["message"];
            }
        }
        return $res;
    }
    
}
function getEvents(){
    $data = getEventsData(); 
    $res = array();
    foreach($data as $event){
        if($event["id"]==-1){continue;}
        $event["participantsCount"] = count($event["participants"]);
        $event["upvotesCount"] = count($event["upvotes"]);
        $event["reviewsCount"] = count($event["reviews"]);
        $event["organizer"] = getUser($event["organizerID"])["username"];
        $participantNames = array();
        foreach($event["participants"] as $p){
            if(getUser($p)["id"]==-1){
                continue;
            }
            array_push($participantNames,getUser($p)["username"]);
        }
        $event["participantNames"]=$participantNames;
        $upvoters = array();
        foreach($event["upvotes"] as $p){
            if(getUser($p)["id"]==-1){
                continue;
            }
            array_push($upvoters,getUser($p)["username"]);
        }
        $event["upvoters"]=$upvoters;
        $newviews = array();
        foreach($event["reviews"] as $r){
            $r["username"]=getUser($r["userID"])["username"];
            array_push($newviews,$r);
        }
        $event["reviews"]=array_reverse($newviews);
        array_push($res,$event);
    }
    return array_reverse($res);
}
function getEventsCustom($mode,$userID){
    if($mode==1){
        return getEventsJoined($userID);
    } else if($mode==2){
        return getEventsOrganized($userID);
    } else {
        return getEvents();
    }
}
function getEventsJoined($userID){
    $user = getUser($userID);
    if($user["id"]==-1){return getEvents();}
    $joined = $user["events"]; 
    $res = array();
    foreach($joined as $eID){
        $event = getEvent($eID);
        if($event["id"]==-1){continue;}
        $event["participantsCount"] = count($event["participants"]);
        $event["upvotesCount"] = count($event["upvotes"]);
        $event["reviewsCount"] = count($event["reviews"]);
        $event["organizer"] = getUser($event["organizerID"])["username"];
        $participantNames = array();
        foreach($event["participants"] as $p){
            if(getUser($p)["id"]==-1){
                continue;
            }
            array_push($participantNames,getUser($p)["username"]);
        }
        $event["participantNames"]=$participantNames;
        $upvoters = array();
        foreach($event["upvotes"] as $p){
            if(getUser($p)["id"]==-1){
                continue;
            }
            array_push($upvoters,getUser($p)["username"]);
        }
        $event["upvoters"]=$upvoters;
        $newviews = array();
        foreach($event["reviews"] as $r){
            $r["username"]=getUser($r["userID"])["username"];
            array_push($newviews,$r);
        }
        $event["reviews"]=array_reverse($newviews);
        array_push($res,$event);
    }
    return array_reverse($res);
}
function getEventsOrganized($organizerID){
    $data = getEventsData(); 
    $res = array();
    foreach($data as $event){
        if($event["id"]==-1 || $event["organizerID"]!=$organizerID){continue;}
        $event["participantsCount"] = count($event["participants"]);
        $event["upvotesCount"] = count($event["upvotes"]);
        $event["reviewsCount"] = count($event["reviews"]);
        $event["organizer"] = getUser($event["organizerID"])["username"];
        $participantNames = array();
        foreach($event["participants"] as $p){
            if(getUser($p)["id"]==-1){
                continue;
            }
            array_push($participantNames,getUser($p)["username"]);
        }
        $event["participantNames"]=$participantNames;
        $upvoters = array();
        foreach($event["upvotes"] as $p){
            if(getUser($p)["id"]==-1){
                continue;
            }
            array_push($upvoters,getUser($p)["username"]);
        }
        $event["upvoters"]=$upvoters;
        $newviews = array();
        foreach($event["reviews"] as $r){
            $r["username"]=getUser($r["userID"])["username"];
            array_push($newviews,$r);
        }
        $event["reviews"]=array_reverse($newviews);
        array_push($res,$event);
    }
    return array_reverse($res);
}
function getNotifs($userID){
    $user = getUser($userID);
    if($user["id"]==-1) {return array("success"=>false,"message"=>"User not found.");}
    $res = array();
    foreach($user["notifs"] as $n){
        array_push($res,$n);
    }
    return array_reverse($res);
}
if(isset($_POST["function"])){
    updateEvents();
    $function = $_POST["function"];
    if(isset($_POST["username"])) { $username = $_POST["username"]; } else { $username = null;}
    if(isset($_POST["password"])) { $password = $_POST["password"]; } else { $password = null;}
    if(isset($_POST["eventName"])) { $eventName = $_POST["eventName"]; } else { $eventName = null;}
    if(isset($_POST["eventDateStart"])) { $eventDateStart = $_POST["eventDateStart"]; } else { $eventDateStart = null;}
    if(isset($_POST["eventDateEnd"])) { $eventDateEnd = $_POST["eventDateEnd"]; } else { $eventDateEnd = null;}
    if(isset($_POST["eventType"])) { $eventType = $_POST["eventType"]; } else { $eventType = null;}
    if(isset($_POST["eventDesc"])) { $eventDesc = $_POST["eventDesc"]; } else { $eventDesc = null;}
    if(isset($_POST["organizerID"])) { $organizerID = $_POST["organizerID"]; } else { $organizerID = null;}
    if(isset($_POST["eventID"])) { $eventID = $_POST["eventID"]; } else { $eventID = null;}
    if(isset($_POST["userID"])) { $userID = $_POST["userID"]; } else { $userID = null;}
    if(isset($_POST["notifID"])) { $notifID = $_POST["notifID"]; } else { $notifID = null;}
    if(isset($_POST["review"])) { $review = $_POST["review"]; } else { $review = null;}
    if(isset($_POST["message"])) { $message = $_POST["message"]; } else { $message = null;}
    if(isset($_POST["id"])) { $id = $_POST["id"]; } else { $id = null;}
    if(isset($_POST["mode"])) { $mode = $_POST["mode"]; } else { $mode = null;}
    if(isset($_POST["notif"])) { $notif = $_POST["notif"]; } else { $notif = null;}
    if(strequ($function,"login")){
        echo json_encode(login($username,$password));
    } else if(strequ($function,"newUser")){
        echo json_encode(newUser($username,$password));
    } else if(strequ($function,"newEvent")) {
        echo json_encode(newEvent($organizerID,$eventName,$eventDateStart,$eventDateEnd,$eventType,$eventDesc));
    } else if (strequ($function, "joinEvent")) {
        echo json_encode(joinEvent($eventID, $userID));
    } else if (strequ($function, "leaveEvent")) {
        echo json_encode(leaveEvent($eventID, $userID));
    } else if (strequ($function, "cancelEvent")) {
        echo json_encode(cancelEvent($eventID, $userID, $message));
    } else if (strequ($function, "requestJoin")) {
        echo json_encode(requestJoin($eventID, $userID));
    } else if (strequ($function, "like")) {
        echo json_encode(like($eventID, $userID));
    } else if (strequ($function, "newReview")) {
        echo json_encode(newReview($eventID, $userID, $review));
    } else if (strequ($function, "deleteReview")) {
        echo json_encode(deleteReview($eventID, $userID, $review));
    } else if(strequ($function,"processNotif")){
        echo json_encode(processNotif($notif));
    } else if (strequ($function, "requestOrganizer")) {
        echo json_encode(requestOrganizer($userID));
    } else if (strequ($function, "requestAdmin")) {
        echo json_encode(requestAdmin($userID));
    } else if(strequ($function,"getEvents")){
        echo json_encode(getEvents());
    } else if(strequ($function,"getEventsCustom")){
        echo json_encode(getEventsCustom($mode,$userID));
    } else if(strequ($function,"getNotifs")){
        echo json_encode(getNotifs($userID));
    }else{
        echo json_encode(eval($function.';'));
    }
}
?>