<?php

require_once 'utils.php';
require_once 'db.php';
require_once 'lang.php';
require_once 'config.php';

function setupPageLayout($req_method, &$pageLayout)
{
    $pageLayout['showRegFormOrNot'] = 'container';
    $pageLayout['showRegMsgOrNot'] = 'container';
    $pageLayout['userNameMsg'] = Prompt::$msg['invalid_username'];
    $pageLayout['userPasswordMsg'] =  Prompt::$msg['invalid_password'];
    $pageLayout['userEmailMsg'] = Prompt::$msg['invalid_email'];////
    $pageLayout['retMsg'] = Prompt::$msg['register_ok'];
    $pageLayout['userName-has-warning'] = '';
    $pageLayout['password-has-warning'] = '';
    $pageLayout['email-has-warning'] = ''; /////email
    $pageLayout['has-warning'] = false;
    $pageLayout['passwordErrs'] = array();
    

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
    $pat='/[^A-Za-z0-9\x{4e00}-\x{9fa5}\-\._]+/u';
     if(!preg_match($pat,$uNme)){
         $result=true;
     }else{
         //匹配上不允许的用户名
         $result=false;
     }
     return $result;
}


function isInvalidRegister($postArr, &$pageLayout) {

    if(empty($postArr['userName']) || !checkuserName($postArr['userName'])) {
        $pageLayout['userName-has-warning'] = 'has-warning';
        $pageLayout['has-warning'] = true;
        $pageLayout['showRegFormOrNot'] = 'container';
        $pageLayout['showRegMsgOrNot'] = 'hidden';
        $postArr['userName'] = '';
    }
    if(empty($postArr['password']) || !checkPassword($postArr['password'], $pageLayout['passwordErrs'])) {
        $pageLayout['password-has-warning'] = 'has-warning';
        $pageLayout['has-warning'] = true;
        $pageLayout['showRegFormOrNot'] = 'container';
        $pageLayout['showRegMsgOrNot'] = 'hidden';
        $pageLayout['userPasswordMsg'] = implode(',', $pageLayout['passwordErrs']);
    }
    ///////
    if(empty($postArr['Email'])||!filter_var($postArr['Email'],FILTER_VALIDATE_EMAIL)){
        $pageLayout['email-has-warning'] = 'has-warning';
        $pageLayout['has-warning'] = true;
        $pageLayout['showRegFormOrNot'] = 'container';
        $pageLayout['showRegMsgOrNot'] = 'hidden';
        $postArr['Email'] = '';
    }

    $_SESSION['Email'] = $postArr['Email'];
    //setcookie('Email',$postArr['Email']);
    ////
  //  $_SESSION['userName'] = $postArr['userName'];
  //  setcookie('userName', $postArr['userName']);

    return $pageLayout['has-warning'];
}

function doRegister($postArr, &$pageLayout)
{
    // 数据校验
    if(isInvalidRegister($postArr, $pageLayout)) {
        return;
    }

    $userName = $postArr['userName'];
    $password = $postArr['password'];
    $Email = $postArr['Email'];

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 生成公私钥对，并用用户登录邮箱加密生成的私钥
    
    $ret = getPubAndPrivKeys($Email,$password);
  //$ret = getPubAndPrivKeys($userName, $password);
    //var_dump($ret);

    $pubkey = $ret['pubkey'];
    $privkey = $ret['privkey'];

    try {
      // 检查用户名是否可用
      if(empty(checkRegisterInDb($userName))) {
          // 用户注册信息数据库写入操作
          if(!registerInDb($userName, $hashedPassword, $pubkey, $privkey,$Email)) {
            // 如果注册失败，则设置相应的错误提示信息，否则，默认只显示注册成功消息和对应的DIV片段代码
            setupPageLayout('GET', $pageLayout);
            $pageLayout['has-warning'] = true;
            $pageLayout['retMsg'] = Prompt::$msg['register_failed'];
          }
      } else {
          // 如果注册失败，则设置相应的错误提示信息，否则，默认只显示注册成功消息和对应的DIV片段代码
          setupPageLayout('GET', $pageLayout);
          $pageLayout['userName-has-warning'] = 'has-warning';
          $pageLayout['userNameMsg'] = Prompt::$msg['duplicate_username'];
          $pageLayout['has-warning'] = true;
      }
    } catch(Exception $e) {
      setupPageLayout('POST', $pageLayout);
      $pageLayout['has-warning'] = true;
      $pageLayout['retMsg'] = Prompt::$msg['db_oops'];
    }


}
