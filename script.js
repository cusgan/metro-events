$(function(){
    const eventTemplate = $("#eventsContainer").html();
    const notifTemplate = $("#notifsContainer").html();
    var gevents;
    var usr = "";
    var gid = -1;
    var type= 0;
    var mode = 0;
    $("#theSignUpButton").on("click",function(){
        let uname = $("#usernameSignUp").val();
        let pword = $("#passwordSignUp").val();
        signup(uname,pword);
    });
    //toasts
    const toastTemplate = $("#toastsContainer").html()+" ";
    var toastElList = [].slice.call(document.querySelectorAll('.toast'))
    var toastList = toastElList.map(function (toastEl) {
    return new bootstrap.Toast(toastEl)
    })
    function alertToast(message){
        //alert(message); return;
        $("#toastsContainer").html("");
        $("#toastsContainer").append(toastTemplate.replace("$message$",message));
        toastElList = [].slice.call(document.querySelectorAll('.toast'))
        toastList = toastElList.map(function (toastEl) {
        return new bootstrap.Toast(toastEl)
        })
        toastList.forEach(toast => toast.show());
    }
    //toasts end
    function signup(uname,pword){
        let inputData = {function:"newUser",username:uname, password:pword};
        $.ajax({
            type: "POST",
            url:`/api.php`,
            data: inputData,
            dataType: "html",
            success: function(result){
                console.log("SIGNUP");
                console.log(result);
                result=JSON.parse(result);
                alertToast(result.message);
                return result;
            }
        });
    }
    $("#theLoginButton").on("click",function(){
        let uname = $("#usernameLogin").val();
        let pword = $("#passwordLogin").val();
        login(uname,pword);
    });
    function login(uname,pword){
        let inputData = {function:"login",username:uname, password:pword};
        $.ajax({
            type: "POST",
            url:`/api.php`,
            data: inputData,
            dataType: "html",
            success: function(result){
                console.log("LOGIN");
                console.log(result);
                result=JSON.parse(result);
                alertToast(result.message);
                if(result.success == false) {
                    return result;
                }
                updateID(result.id);
                updateName(result.username);
                updateType(result.usertype);
                setMode(0);
                getEvents();
                return result;
            }
        });
    }
    function updateID(newval){
        gid = newval;
    }
    function updateName(newval){
        usr = newval;
        $(".username").html(usr);
    }
    function updateType(newval){
        type=newval;
        const types = ["User","Organizer","Administrator"];
        $("#userTypeDisplay").html(types[type]);
    }
    function setMode(newval){
        mode=newval;
        const modes = ["Home","Joined Events","Organized Events"];
        $("#filterDisplay").html(modes[mode]);
    }
    function getEvents(){
        let inputData = {function:"getEventsCustom",mode:mode,userID:gid};
        $.ajax({
            type: "POST",
            url:`/api.php`,
            data: inputData,
            dataType: "html",
            success: function(result){
                $("#eventsContainer").html("");
                //console.log("GET EVENTS");
                //console.log(result);
                result=JSON.parse(result);
                let inEvents = [];
                let outEvents = [];
                for(let event of result){
                    addEventDisplay(event);
                    let found = false;
                    for(let ps of event.participants){
                        if(ps==gid){
                            inEvents.push(event.id);
                            found=true;
                            break;
                        }
                    }
                    if(!found){
                        outEvents.push(event.id);
                    }

                }
                getNotifs(gid);
                if(gid==-1){
                    $(".signedIn").hide();
                    $(".signedOut").show();
                } else {
                    $(".signedIn").show();
                    $(".signedOut").hide();
                }
                if(type>=1){
                    $(".organizer").show();
                } else {
                    $(".organizer").hide();
                }
                $(".inEvent").hide();
                $(".outEvent").hide();
                if(gid!=-1){
                for(x of inEvents){
                    $(".inEvent"+x).show();
                }
                for(x of outEvents){
                    $(".outEvent"+x).show();
                }}
                $(".notForOrganizer"+gid).hide();
                
                $(".onlyFor").hide();
                $(".onlyFor"+gid).show();
                $(".notAdmin").hide();
                if(type==2){
                    $(".adminOverride").show();
                } else {
                    $(".notAdmin").show();
                }
                
                gevents = result;
                return result;
            }
        });
    }
    $("#theLogOutButton").on("click",function(){
        updateID(-1);
        updateName("Not Signed In");
        updateType(0);
        $("#userTypeDisplay").html("");
        getEvents();
        setMode(0);
    });
    function formatTime(time){
        time = time.split("-");
        let newtime = time[0]+"-"+time[1]+"-"+time[2]+" ";
        let hour =parseInt(time[3]);
        let end = 'AM';
        if(hour > 12){
            hour-=12;
            end = 'PM'
        }
        return newtime+hour+":"+time[4]+end;
    }
    function addEventDisplay(event){
        let joinRequest = {function:"requestJoin",eventID:event.id,userID:gid};
        let leaveEvent = {function:"leaveEvent",eventID:event.id,userID:gid};
        let cancelEvent = {function:"cancelEvent",eventID:event.id,userID:gid};
        let reviews = event.reviews;
        let upvote = {function:"like",eventID:event.id,userID:gid};
        let engagements = {upvoters:event.upvoters,participants:event.participantNames};
        const colors = ["primary","warning","success","danger"];
        const status = ["Upcoming","Ongoing","Completed","Cancelled"];
        res = eventTemplate
        .replaceAll("$eventID$",event.id)
        .replaceAll("$organizerID$",event.organizerID)
        .replace("$Title$",event.name)
        .replace("$Type$",event.type)
        .replace("$Organizer$","Organizer: "+event.organizer)
        .replace("$Status$",status[event.status])
        .replace('secondary" bleh="true',colors[event.status])
        .replace("$StartEvent$",formatTime(event.dateStart))
        .replace("$EndEvent$",formatTime(event.dateEnd))
        .replace("$Description$",event.desc)
        .replace("$pc$",event.participantsCount)
        .replace("$lc$",event.upvotesCount)
        .replace("$rc$",event.reviewsCount)
        .replace("$joinRequest$",JSON.stringify(joinRequest))
        .replace("$leaveEvent$",JSON.stringify(leaveEvent))
        .replace("$cancelEvent$",JSON.stringify(cancelEvent))
        .replace("$upvote$",JSON.stringify(upvote))
        .replace("$reviews$",JSON.stringify(reviews))
        .replaceAll("$engagements$",JSON.stringify(engagements))
        ;
        if(event.status==1 || event.status == 0){
            //alertToast("YEAH");
            res = res.replaceAll('style="display:none; completed: true;" hidden','');
        }
        $("#eventsContainer").append(res);
    };
    getEvents();
    function getNotifs(uID){
        let inputData = {function:"getNotifs",userID:uID};
        $.ajax({
            type: "POST",
            url:`/api.php`,
            data: inputData,
            dataType: "html",
            success: function(result){
                $("#notifsContainer").html("");
                //console.log("GET NOTIFS");
                //console.log(result);
                result=JSON.parse(result);
                if(result.success == false){
                    return result;
                }
                for(let notif of result){
                    addNotifDisplay(notif);
                }
                return result;
            }
        });
    }
    function addNotifDisplay(notif){
        res = notifTemplate
        .replace("$Title$",notif.title)
        .replace("$Description$",notif.body)
        .replace("$yesFunction$",JSON.stringify(notif.yes))
        .replace("$noFunction$",JSON.stringify(notif.no))
        ;
        if(notif.yes!=null){
            res = res.replaceAll('style="display:none; klinko:yeah"','');
            res = res.replace('$Decline$','Decline');
        } else {
            res = res.replace('$Decline$','X');
        }
        $("#notifsContainer").append(res);
        //console.log(JSON.stringify(notif.yes));
    };
    $("#posts1").on("click",'.replyButton',function(){
        let pid = $(this).children().html();
        let replyText = $("#reppp"+pid).val();
        //alertToast(replyText);
        //replyPost(pid,replyText);
        if ($("#newrep"+pid).is(":hidden")){
            $("#newrep"+pid).show();
        } else {
            replyPost(pid,replyText);
        }
    });
    $("#notifsContainer").on("click",'.notifButton',function(){
        let functions = JSON.parse($(this).children().html());
        let inputData = {function:"processNotif",notif:functions};
        $.ajax({
            type: "POST",
            url:`/api.php`,
            data: inputData,
            dataType: "html",
            success: function(result){
                console.log("PROCESS NOTIFS");
                console.log(result);
                result=JSON.parse(result);
                getNotifs(gid);
                return result;
            }
        });
    });
    $("#postEventButton").on("click",function(){
        console.log("POST EVENT");
        let n = $("#startMin").val();
        let h = parseInt($("#startHour").val()) + (parseInt($("#startAMPM").val()) * 12)-12;
        let d= $("#startDay").val();
        let m= $("#startMonth").val();
        let y= $("#startYear").val();
        let start = y+'-'+pad(m)+'-'+pad(d)+'-'+pad(h)+'-'+pad(n);
         n = $("#endMin").val();
         h = parseInt($("#endHour").val()) + (parseInt($("#endAMPM").val()) * 12)-12;
         d= $("#endDay").val();
         m= $("#endMonth").val();
         y= $("#endYear").val();
        let end = y+'-'+pad(m)+'-'+pad(d)+'-'+pad(h)+'-'+pad(n);
        let inputData = {
            function:"newEvent",
            organizerID:gid,
            eventType:$("#eventType").val(),
            eventName:$("#eventName").val(),
            eventDesc:$("#eventDesc").val(),
            eventDateStart: start,
            eventDateEnd: end
        };
        $.ajax({
            type: "POST",
            url:`/api.php`,
            data: inputData,
            dataType: "html",
            success: function(result){
                console.log("CREATE EVENT");
                console.log(result);
                result=JSON.parse(result);
                alertToast(result.message);
                getEvents();
                return result;
            }
        });
    });
    function pad(num) {
        num = num.toString();
        while (num.length < 2) num = "0" + num;
        return num;
    }
    $("#eventsContainer").on("click",'.eventButton',function(){
        let inputData = JSON.parse($(this).children('p').html());
        $.ajax({
            type: "POST",
            url:`/api.php`,
            data: inputData,
            dataType: "html",
            success: function(result){
                console.log("PROCESS EVENT BUTTON"); console.log(result);
                result=JSON.parse(result);
                alertToast(result.message);  
                getEvents();
                return result;
            }
        });
    });
    $("#reviewsContainer").on("click",'.eventButton',function(){
        let inputData = JSON.parse($(this).children('p').html());
        $.ajax({
            type: "POST",
            url:`/api.php`,
            data: inputData,
            dataType: "html",
            success: function(result){
                console.log("DELETE REVIEW"); console.log(result);
                result=JSON.parse(result);
                alertToast(result.message);  
                getEvents();
                return result;
            }
        });
    });
    $("#submitPromotionRequest").on("click",function(){
        let sel = $(":radio:checked").attr("id").split("-")[1];
        let fn = "requestOrganizer";
        if(sel==2){
            fn = "requestAdmin";
        }
        let inputData = {function:fn,userID:gid};
        $.ajax({
            type: "POST",
            url:`/api.php`,
            data: inputData,
            dataType: "html",
            success: function(result){
                console.log("SUBMIT PROMOTION REQUEST");
                console.log(result);
                result=JSON.parse(result);
                alertToast(result.message);  
                getEvents();
                return result;
            }
        });
    });
    const epartTemplate = $("#participantsContainer").html();
    const eupvoTemplate = $("#upvotesContainer").html();
    $("#eventsContainer").on("click",'.engagementsButton',function(){
        let content = JSON.parse($(this).children('p').html());
        $("#participantsContainer").html("");
        $("#upvotesContainer").html("");
        for(let x of content.participants){
            $("#participantsContainer").append(epartTemplate.replace("$pusername$",x));
        }
        for(let x of content.upvoters){
            $("#upvotesContainer").append(eupvoTemplate.replace("$pupvote$",x));
        }
    });
    const reviewTemplate = $("#reviewsContainer").html();
    $('#eventsContainer').on("click",".reviewsButton",function(){
        let reviews = JSON.parse($(this).children('p').html());
        let eID = JSON.parse($(this).children('h5').html());
        //console.log(reviews);
        $("#reviewsContainer").html("");
        for(let x of reviews){
            if(x.userID==gid || type==2){
                let del = {function:'deleteReview',eventID:eID,userID:gid,review:x.content};
                $("#reviewsContainer").append(
                    reviewTemplate
                    .replace("$ruserID$",x.userID)
                    .replace("$rusername$",x.username)
                    .replace("$rcontent$",x.content)
                    .replace("$rdelete$",JSON.stringify(del))
                    .replace('style="display:none; yule:true;"','')
                );
            } else {
                $("#reviewsContainer").append(
                    reviewTemplate
                    .replace("$ruserID$",x.userID)
                    .replace("$rusername$",x.username)
                    .replace("$rcontent$",x.content)
                );
            }
            
        }
    });
    $("#eventsContainer").on("click",".cancelEventButton",function(){
        let eID = $(this).children('p').html();
        let msg = $("#cancelInput"+eID).val();
        let inputData = {function:"cancelEvent",eventID:eID,userID:gid,message:msg};
        $.ajax({
            type: "POST",
            url:`/api.php`,
            data: inputData,
            dataType: "html",
            success: function(result){
                console.log("CANCEL EVENT");
                console.log(result);
                result=JSON.parse(result);
                alertToast(result.message);  
                getEvents();
                return result;
            }
        });
    });
    
    $("#eventsContainer").on("click",".postReviewButton",function(){
        let eID = $(this).children('p').html();
        let msg = $("#reviewInput"+eID).val();
        let inputData = {function:"newReview",eventID:eID,userID:gid,review:msg};
        $.ajax({
            type: "POST",
            url:`/api.php`,
            data: inputData,
            dataType: "html",
            success: function(result){
                console.log("REVIEW EVENT");
                console.log(result);
                result=JSON.parse(result);
                alertToast(result.message);  
                getEvents();
                return result;
            }
        });
    });
    $("#filterNone").on("click",function(){
        $(".active").toggleClass("active");
        $("#filterNone").toggleClass("active");
        setMode(0);        
        getEvents();
    });
    $("#filterJoined").on("click",function(){
        $(".active").toggleClass("active");
        $("#filterJoined").toggleClass("active");
        setMode(1);
        getEvents();
    });
    $("#filterOrganized").on("click",function(){
        $(".active").toggleClass("active");
        $("#filterOrganized").toggleClass("active");
        setMode(2);
        getEvents();
    });
})