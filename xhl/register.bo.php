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
{
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
