<?php

require_once 'utils.php';
require_once 'db.php';
require_once 'lang.php';
require_once 'config.php';

require_once "class.phpmailer.php";//获取一个外部文件的内容
require_once "class.smtp.php";


function setupPageLayout($req_method, &$pageLayout)
{
    $pageLayout['showRegFormOrNot'] = 'container';
    $pageLayout['showRegMsgOrNot'] = 'container';
    $pageLayout['userNameMsg'] = Prompt::$msg['invalid_username'];
    $pageLayout['emailNameMsg'] = Prompt::$msg['invalid_emailname'];
    //$pageLayout['userPasswordMsg'] =  Prompt::$msg['invalid_password'];
    $pageLayout['retMsg'] = Prompt::$msg['register_ok'];
    $pageLayout['userName-has-warning'] = '';
    $pageLayout['emailName-has-warning'] = '';
    $pageLayout['password-has-warning'] = '';
    $pageLayout['has-warning'] = false;
    $pageLayout['passwordErrs'] = array();
    //file_put_contents('debug.log', "first has set it  ".$pageLayout['has-warning']."\n",FILE_APPEND);
    switch ($req_method) {
    case 'POST':
        $pageLayout['showRegFormOrNot'] = 'hidden';
        break;
    case 'GET':
        $pageLayout['showRegMsgOrNot'] = 'hidden';
        break;
    default:
        # code...
        break;
    }
}

function checkPassword($pwd, &$errors) {
    $errors_init = $errors;

    foreach(Config::$password['rules'] as $key => $rule) {
      if (!preg_match($rule[0], $pwd)) {
          $errors[] = $rule[1];
      }
    }

    return ($errors == $errors_init);
}


function checkuserName($uNme){
    //不允许的用户名
    //$pat='/[^A-Za-z0-9\x{4e00}-\x{9fa5}\-\._]+/u';
    $pat = '/[^A-Za-z0-9\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2a6d6}\x{2a700}-\x{2b734}\x{2b740}-\x{2b81d}\x{2b820}-\x{2cea1}\x{2ceb0}-\x{2ebe0}\x{2f00}-\x{2fd5}\x{2e80}-\x{2ef3}\x{f900}-\x{fad9}\x{2f800}-\x{2fa1d}\x{e815}-\x{e86f}\x{e400}-\x{e5e8}\x{e600}-\x{e6cf}\x{31c0}-\x{31e3}\x{2ff0}-\x{2ffb}\x{3105}-\x{312f}/x{31a0-31ba}\-\._]+/u';
    if(!preg_match($pat,$uNme)){
        $result=true;
    }else{
        //匹配上不允许的用户名
        $result=false;
    }
    return $result;
}

function isInvalidRegister($postArr, &$pageLayout) {
    if(empty($postArr['emailName']) || !filter_var($postArr['emailName'], FILTER_VALIDATE_EMAIL)) {

        $pageLayout['emailName-has-warning'] = 'has-warning';
        $pageLayout['has-warning'] = true;
        $pageLayout['showRegFormOrNot'] = 'container';
        $pageLayout['showRegMsgOrNot'] = 'hidden';
        $postArr['emailName'] = '';
    }
    if(strlen($postArr['password']) <6 ||strlen($postArr['password']) >36|| empty($postArr['password']) || !checkPassword($postArr['password'], $pageLayout['passwordErrs'])) {
        $pageLayout['password-has-warning'] = 'has-warning';
        $pageLayout['has-warning'] = true;
        $pageLayout['showRegFormOrNot'] = 'container';
        $pageLayout['showRegMsgOrNot'] = 'hidden';
        $pageLayout['userPasswordMsg'] = implode(',', $pageLayout['passwordErrs']);
    }

    if(empty($postArr['userName']) || !checkuserName($postArr['userName'])) {
        $pageLayout['userName-has-warning'] = 'has-warning';
        $pageLayout['has-warning'] = true;
        $pageLayout['showRegFormOrNot'] = 'container';
        $pageLayout['showRegMsgOrNot'] = 'hidden';
        $postArr['userName'] = '';
        $postArr['userName'] = '';
    }

    $_SESSION['userName'] = $postArr['userName'];
    setcookie('userName', $postArr['userName']);

    return $pageLayout['has-warning'];
}

function doRegister($postArr, &$pageLayout)
{    //顺便完成邮箱注册的功能
    file_put_contents('debug.log', "last input   ".json_encode($pageLayout)."\n",FILE_APPEND);

    // 数据校验
    if(isInvalidRegister($postArr, $pageLayout)) {   //检查邮箱 密码 用户名是否符合规范
        return;
    }

    $emailName = $postArr['emailName'];
    $userName = $postArr['userName'];
    $password = $postArr['password'];

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 生成公私钥对，并用用户登录口令加密生成的私钥
    $ret = getPubAndPrivKeys($emailName, $password);

    //var_dump($ret);

    $pubkey = $ret['pubkey'];
    $privkey = $ret['privkey'];

    try {
      // 检查用户名是否可用
      if(empty(checkRegisterInDb($userName))) {      //如果用户名为空
          // 用户注册信息数据库写入操作
          if(!registerInDb($userName, $hashedPassword, $pubkey, $privkey,$emailName)) {
            // 如果注册失败，则设置相应的错误提示信息，否则，默认只显示注册成功消息和对应的DIV片段代码
            setupPageLayout('GET', $pageLayout);
            $pageLayout['has-warning'] = true;
            $pageLayout['retMsg'] = Prompt::$msg['register_failed'];
          }else{    //如果注册成功 下一步就可以直接发送邮件
            $token = hash('sha256',$userName.$hashedPassword);                //创建用于激活识别码
            sendEmail($userName,$token,$emailName);
          }


      } else {
          // 如果注册失败，则设置相应的错误提示信息，否则，默认只显示注册成功消息和对应的DIV片段代码
          setupPageLayout('GET', $pageLayout);
          $pageLayout['userName-has-warning'] = 'has-warning';
          $pageLayout['userNameMsg'] = Prompt::$msg['duplicate_userName'];
          $pageLayout['has-warning'] = true;
      }
    } catch(Exception $e) {
      setupPageLayout('POST', $pageLayout);
      $pageLayout['has-warning'] = true;
      $pageLayout['retMsg'] = Prompt::$msg['db_oops'];
    }

    //file_put_contents('debug.log', "last input   ".json_encode($pageLayout)."\n",FILE_APPEND);

}

function sendEmail($username,$token,$email)
{

    file_put_contents('debug.log', "test token".$token."\n", FILE_APPEND);
    $mail=new PHPMailer();
    $mail->SMTPDebug = 2;              //设置调试信息  如果设置为1或者2 发送不成功会输出报错信息
//    $body = "<div><form name='form'  action ='active.php' method ='post'>亲爱的".$username."：<br/>感谢您在我站注册了新帐号。<br/>请点击链接激活您的帐号。<br/>
//
//
//    <a href='http://192.168.29.122:8080/active.php?verify=".$token."&name=".$username."' target ='_blank'>请点击链接</a><br/>
//    </form></div>";
    $mail->MsgHTML($body);


   $mail->Body = "<span><form name='form'   method ='POST' action ='active.php'>亲爱的".$username."：<br/>感谢您在我站注册了新帐号。<br/>请点击链接激活您的帐号。<br/>
    
    <input type ='hidden' name = 'name' value = 'value' />
    <a href='http://192.168.29.122:8080/active.php?verify=".$token."&name=".$username."' target ='_blank'>请点击链接</a><br/>
    </form></span>";




//设置smtp参数
    $mail->IsSMTP();
    $mail->SMTPAuth=true;
    $mail->SMTPKeepAlive=true;
    $mail->SMTPSecure= "ssl";
//$mail->SMTPSecure= "tls";
    $mail->Host="smtp.qq.com";
    $mail->Port=465;
//$mail->Port=587;

//填写email账号和密码

    $mail->Username="2939906971@qq.com";  //设置发送方
    $mail->Password="ddibwmugnrttdhcj";   //注意这里也要填写授权码就是我在上面QQ邮箱开启SMTP中提到的，不能填邮箱登录的密码哦。
    $mail->From="2939906971@qq.com";      //设置发送方
    $mail->FromName="梧桐树";
    $mail->Subject="梧**发来的一封邮件";
    $mail->AltBody=$body;
    $mail->WordWrap=50;                  // 设置自动换行

    $mail->AddReplyTo("2939906971@qq.com","梧**");//设置回复地址
    $mail->AddAddress($email,"hello");  //设置邮件接收方的邮箱和姓名
    $mail->IsHTML(true);                //使用HTML格式发送邮件
    if(!$mail->Send()){//通过Send方法发送邮件,根据发送结果做相应处理
        echo "Mailer Error:".$mail->ErrorInfo;
    }else{
        echo "Message has been sent"; }


}

